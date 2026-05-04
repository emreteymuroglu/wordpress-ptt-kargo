<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PTT entegrasyon log'ları. Custom tabloda saklanır (autoload yok, hızlı sorgu, eski kayıtları rotate eder).
 *
 *  - record(): yeni log satırı ekler, rotation limitini aşan en eski kayıtları siler
 *  - get_recent(): son N kaydı döner (admin panel listelemesi için)
 *  - prune_older_than_days(): manuel temizlik
 *  - install_table() / drop_table(): aktivasyon/uninstall
 */
final class Logs {
	public const TABLE_NAME      = 'wc_ptt_kargo_logs';
	public const RETENTION_LIMIT = 500; // En fazla 500 kayıt tutulur, fazlası otomatik silinir.

	/**
	 * SOAP envelope'taki şifre/credential alanlarını maskeler.
	 * PTT'nin tüm SOAP body'leri <sifre>/<Sifre>/<password> tag'lerinde düz metin şifre taşır;
	 * log tablosuna ya da postmeta'ya yazılmadan önce bu helper'dan geçirilmeli.
	 */
	public static function mask_sensitive( string $xml ): string {
		if ( $xml === '' ) return $xml;
		$xml = preg_replace( '~(<(?:[\w\-]+:)?[Ss]ifre>)[^<]*(</(?:[\w\-]+:)?[Ss]ifre>)~', '$1***$2', $xml );
		$xml = preg_replace( '~(<(?:[\w\-]+:)?[Pp]assword>)[^<]*(</(?:[\w\-]+:)?[Pp]assword>)~', '$1***$2', (string) $xml );
		return (string) $xml;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function install_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			operation VARCHAR(64) NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			success TINYINT(1) NOT NULL DEFAULT 0,
			message TEXT NULL,
			request LONGTEXT NULL,
			response LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY order_id (order_id),
			KEY operation (operation)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table(): void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	public static function record( string $operation, ?int $order_id, bool $success, string $message, string $request = '', string $response = '' ): void {
		global $wpdb;
		$table = self::table();

		// Tablo yoksa sessizce yut (örn. test ortamı, migration olmadan yüklenmiş).
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			// Yine de WP debug log'a düş — test ortamında migration koşmamış kurulumlar için.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( sprintf( '[wc-ptt-kargo] %s | order=%s | %s | %s',
					$operation, $order_id ?? '-', $success ? 'OK' : 'FAIL', $message ) );
			}
			return;
		}

		$wpdb->insert(
			$table,
			[
				'created_at' => current_time( 'mysql' ),
				'operation'  => substr( $operation, 0, 64 ),
				'order_id'   => $order_id,
				'success'    => $success ? 1 : 0,
				'message'    => $message,
				'request'    => $request,
				'response'   => $response,
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
		);

		// WP debug log'a da yansıt — test sürecinde gerçek-zamanlı izleme.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( sprintf( '[wc-ptt-kargo] %s | order=%s | %s | %s',
				$operation, $order_id ?? '-', $success ? 'OK' : 'FAIL', $message ) );
		}

		self::rotate();
	}

	/**
	 * HTTP isteklerini tam detayla loglar. Test sürecinde 3 hak kıt — her envelope, header, status,
	 * timing, network error eksiksiz kaydedilir ki post-mortem analiz için tek log satırı yeterli olsun.
	 *
	 * @param array  $req   ['method'=>, 'endpoint'=>, 'headers'=>[], 'body'=>'']
	 * @param array  $resp  ['code'=>int, 'headers'=>array, 'body'=>'', 'network_error'=>?string, 'wp_error_data'=>?mixed]
	 * @param float  $duration_ms
	 */
	public static function record_http(
		string $operation,
		?int $order_id,
		bool $success,
		string $message,
		array $req,
		array $resp,
		float $duration_ms = 0.0
	): void {
		self::record(
			$operation,
			$order_id,
			$success,
			$message,
			self::format_http_request( $req ),
			self::format_http_response( $resp, $duration_ms )
		);
	}

	/**
	 * Network call yapmayan ama önemli olayları (validation hatası, eksik ayar, parse fail vs.) kaydeder.
	 *
	 * @param array $context  Serileştirilebilir bağlam — JSON olarak request kolonuna yazılır.
	 */
	public static function record_event( string $operation, ?int $order_id, bool $success, string $message, array $context = [] ): void {
		$ctx = '';
		if ( ! empty( $context ) ) {
			$ctx = "-- CONTEXT --\n" . wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		self::record( $operation, $order_id, $success, $message, $ctx, '' );
	}

	private static function format_http_request( array $req ): string {
		$lines = [];
		$method   = strtoupper( (string) ( $req['method']   ?? 'POST' ) );
		$endpoint = (string) ( $req['endpoint'] ?? '' );
		if ( $endpoint !== '' ) $lines[] = $method . ' ' . $endpoint;

		$headers = $req['headers'] ?? [];
		if ( is_array( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				$val = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
				// Şifre tarzı sensitive header maskele (PTT zaten body'de açık, ama defansif).
				$lines[] = $k . ': ' . $val;
			}
		}
		$lines[] = ''; // başlık/body ayracı
		if ( isset( $req['body'] ) ) {
			$lines[] = self::mask_sensitive( (string) $req['body'] );
		}
		return implode( "\n", $lines );
	}

	private static function format_http_response( array $resp, float $duration_ms ): string {
		$lines = [];
		if ( isset( $resp['code'] ) ) {
			$lines[] = 'HTTP ' . (int) $resp['code'];
		}
		if ( ! empty( $resp['network_error'] ) ) {
			$lines[] = '[NETWORK ERROR] ' . (string) $resp['network_error'];
		}
		if ( ! empty( $resp['wp_error_data'] ) ) {
			$lines[] = '[WP_Error data] ' . wp_json_encode( $resp['wp_error_data'], JSON_UNESCAPED_UNICODE );
		}
		if ( $duration_ms > 0 ) {
			$lines[] = sprintf( 'Süre: %.0f ms', $duration_ms );
		}

		$rh = $resp['headers'] ?? [];
		if ( ! empty( $rh ) && is_array( $rh ) ) {
			$lines[] = '';
			$lines[] = '-- Response Headers --';
			foreach ( $rh as $k => $v ) {
				$val = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
				$lines[] = $k . ': ' . $val;
			}
		}

		if ( isset( $resp['body'] ) && (string) $resp['body'] !== '' ) {
			$lines[] = '';
			$lines[] = '-- Response Body --';
			$lines[] = (string) $resp['body'];
		}

		return implode( "\n", $lines );
	}

	private static function rotate(): void {
		global $wpdb;
		$table = self::table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count <= self::RETENTION_LIMIT ) return;

		$delete_count = $count - self::RETENTION_LIMIT;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
			$delete_count
		) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent( int $limit = 100, ?bool $only_success = null, string $operation = '' ): array {
		global $wpdb;
		$table = self::table();

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) return [];

		$where  = [];
		$params = [];
		if ( $only_success !== null ) {
			$where[]  = 'success = %d';
			$params[] = $only_success ? 1 : 0;
		}
		if ( $operation !== '' ) {
			$where[]  = 'operation = %s';
			$params[] = $operation;
		}
		$where_sql = ! empty( $where ) ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

		$params[] = $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d",
				$params
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public static function clear(): void {
		global $wpdb;
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) return;
		$wpdb->query( 'TRUNCATE TABLE ' . $table );
	}

	public static function count(): int {
		global $wpdb;
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) return 0;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
