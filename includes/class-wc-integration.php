<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce native ekranlarına entegrasyon:
 *  - Sipariş listesi (HPOS + legacy) → "PTT Kargo" sütunu + bulk action
 *  - Sipariş detay sayfası → "PTT Kargo" metabox (gönder / etiket / takip)
 */
final class WC_Integration {
	private Settings   $settings;
	private Orders     $orders;
	private Barcode    $barcode;
	private PTT_Client $client;
	private Label      $label;

	public function __construct( Settings $settings, Orders $orders, Barcode $barcode, PTT_Client $client, Label $label ) {
		$this->settings = $settings;
		$this->orders   = $orders;
		$this->barcode  = $barcode;
		$this->client   = $client;
		$this->label    = $label;
	}

	public function register(): void {
		// HPOS sipariş listesi
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_column' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column_hpos' ], 10, 2 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_action' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_action' ], 10, 3 );

		// Legacy (post-based) sipariş listesi
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column_legacy' ], 10, 2 );
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_action' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_action' ], 10, 3 );

		// Bulk action sonrası admin notice
		add_action( 'admin_notices', [ $this, 'bulk_action_notice' ] );

		// Sipariş detay metabox: HPOS + legacy
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
	}

	public function add_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'order_status' || $key === 'shipping_address' ) {
				$new['wc_ptt_kargo'] = __( 'PTT Kargo', 'wc-ptt-kargo' );
			}
		}
		if ( ! isset( $new['wc_ptt_kargo'] ) ) {
			$new['wc_ptt_kargo'] = __( 'PTT Kargo', 'wc-ptt-kargo' );
		}
		return $new;
	}

	public function render_column_hpos( string $column_name, $order ): void {
		if ( $column_name !== 'wc_ptt_kargo' ) return;
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
			if ( ! $order ) return;
		}
		echo $this->column_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function render_column_legacy( string $column_name, int $post_id ): void {
		if ( $column_name !== 'wc_ptt_kargo' ) return;
		$order = wc_get_order( $post_id );
		if ( ! $order ) return;
		echo $this->column_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function column_html( \WC_Order $order ): string {
		$status = (string) $order->get_meta( Orders::META_STATUS );
		$barkod = (string) $order->get_meta( Orders::META_BARKOD );

		ob_start();
		if ( $status === Orders::STATUS_SENT && $barkod !== '' ) {
			echo '<span class="wc-ptt-col-badge sent">✓ ' . esc_html__( 'Gönderildi', 'wc-ptt-kargo' ) . '</span>';
			echo '<br><code class="wc-ptt-col-barkod">' . esc_html( $barkod ) . '</code>';
		} elseif ( $status === Orders::STATUS_ERROR ) {
			echo '<span class="wc-ptt-col-badge error">!</span> ' . esc_html__( 'Hata', 'wc-ptt-kargo' );
		} elseif ( $status === Orders::STATUS_CANCELED ) {
			echo '<span class="wc-ptt-col-badge canceled">⊘ ' . esc_html__( 'İptal', 'wc-ptt-kargo' ) . '</span>';
		} else {
			echo '<span class="wc-ptt-col-badge pending">—</span>';
		}
		return (string) ob_get_clean();
	}

	public function add_bulk_action( array $actions ): array {
		$actions['wc_ptt_kargo_send']   = __( 'PTT Kargo: Gönder', 'wc-ptt-kargo' );
		$actions['wc_ptt_kargo_label']  = __( 'PTT Kargo: Toplu Etiket Bas', 'wc-ptt-kargo' );
		return $actions;
	}

	public function handle_bulk_action( string $redirect, string $action, array $order_ids ): string {
		// Toplu etiket basma → label endpoint'ine yönlendir, browser print dialogu açar.
		if ( $action === 'wc_ptt_kargo_label' ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) return $redirect;
			$ids = array_filter( array_map( 'intval', $order_ids ) );
			if ( empty( $ids ) ) return $redirect;
			return $this->label->bulk_url( $ids );
		}

		if ( $action !== 'wc_ptt_kargo_send' ) return $redirect;
		if ( ! current_user_can( 'manage_woocommerce' ) ) return $redirect;

		$sent  = 0;
		$skip  = 0;
		$err   = 0;

		foreach ( $order_ids as $oid ) {
			$order = wc_get_order( (int) $oid );
			if ( ! $order ) { $skip++; continue; }

			$existing = (string) $order->get_meta( Orders::META_BARKOD );
			if ( $existing !== '' ) { $skip++; continue; }

			$payload = $this->orders->to_ptt_payload( $order );

			// Retry: pending barkod varsa onu reuse et — yeni barkod yakma.
			$pending = $this->orders->get_pending_barkod( $order );
			$barkod  = $pending !== '' ? $pending : $this->barcode->next();
			if ( $barkod === null || $barkod === '' ) { $err++; continue; }

			$ref    = $this->orders->build_ref( $order );
			$fields = $payload['fields'];
			$fields['barkodNo']          = $barkod;
			$fields['musteriReferansNo'] = $ref;

			$result = $this->client->kabul_ekle( $fields, $order->get_id() );

			if ( empty( $result['success'] ) ) {
				$this->orders->mark_error(
					$order,
					(string) ( $result['mesaj'] ?? 'Bilinmeyen hata' ),
					(string) ( $result['raw'] ?? '' ),
					(string) ( $result['request'] ?? '' ),
					$barkod
				);
				do_action( 'wc_ptt_kargo_after_error', $order, (string) ( $result['mesaj'] ?? '' ), $result );
				$err++;
				continue;
			}

			$returned_barkod = (string) ( $result['barkod'] ?? $barkod );
			$dosya_adi       = (string) ( $result['dosya_adi'] ?? '' );

			$this->orders->mark_sent(
				$order,
				$returned_barkod,
				$ref,
				(string) ( $result['takip_url'] ?? '' ),
				(string) ( $result['raw'] ?? '' ),
				(string) ( $result['request'] ?? '' ),
				(string) ( $result['mesaj'] ?? '' ),
				$dosya_adi
			);
			do_action( 'wc_ptt_kargo_after_send', $order, $returned_barkod, $result );
			$sent++;
		}

		return add_query_arg(
			[ 'wc_ptt_bulk_sent' => $sent, 'wc_ptt_bulk_skip' => $skip, 'wc_ptt_bulk_err' => $err ],
			$redirect
		);
	}

	public function bulk_action_notice(): void {
		if ( ! isset( $_GET['wc_ptt_bulk_sent'] ) ) return;
		$sent = (int) $_GET['wc_ptt_bulk_sent'];
		$skip = (int) ( $_GET['wc_ptt_bulk_skip'] ?? 0 );
		$err  = (int) ( $_GET['wc_ptt_bulk_err'] ?? 0 );

		$class = $err > 0 ? 'notice-warning' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>';
		echo esc_html( sprintf(
			/* translators: 1: gönderilen, 2: atlanan, 3: hata */
			__( 'PTT Kargo bulk: %1$d gönderildi, %2$d atlandı (zaten barkodlu), %3$d hata.', 'wc-ptt-kargo' ),
			$sent, $skip, $err
		) );
		echo '</p></div>';
	}

	public function add_metabox(): void {
		$screens = [ 'shop_order' ];
		// HPOS screen ID
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
		     && function_exists( 'wc_get_page_screen_id' ) ) {
			$hpos_screen = wc_get_page_screen_id( 'shop-order' );
			if ( $hpos_screen ) $screens[] = $hpos_screen;
		}

		foreach ( $screens as $screen ) {
			add_meta_box(
				'wc_ptt_kargo_metabox',
				__( 'PTT Kargo', 'wc-ptt-kargo' ),
				[ $this, 'render_metabox' ],
				$screen,
				'side',
				'high'
			);
		}
	}

	public function render_metabox( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : (int) $post_or_order );
		if ( ! $order ) return;

		$status = (string) $order->get_meta( Orders::META_STATUS );
		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$takip  = (string) $order->get_meta( Orders::META_TAKIP_URL );
		$mesaj  = (string) $order->get_meta( Orders::META_PTT_LOG );

		$context = [
			'order'  => $order,
			'status' => $status,
			'barkod' => $barkod,
			'takip'  => $takip,
			'mesaj'  => $mesaj,
			'label'  => $this->label,
			'orders' => $this->orders,
		];
		extract( $context, EXTR_SKIP );

		include WC_PTT_KARGO_DIR . 'admin/views/order-metabox.php';
	}
}
