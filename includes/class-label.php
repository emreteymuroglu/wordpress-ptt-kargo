<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 80mm termal etiket HTML çıktısı + canlı önizleme.
 *
 *  - render()        : gerçek sipariş etiketi, window.print() açar
 *  - render_preview(): ayar formundan POST gelen değerlerle örnek sipariş etiketi (auto-print kapalı)
 */
final class Label {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_post_wc_ptt_kargo_label', [ $this, 'render' ] );
		add_action( 'admin_post_wc_ptt_kargo_preview', [ $this, 'render_preview' ] );
		add_action( 'admin_post_wc_ptt_kargo_bulk_label', [ $this, 'render_bulk' ] );
	}

	/**
	 * Toplu etiket: birden çok sipariş için tek HTML, her sipariş arasına thermal page-break.
	 * URL formatı: admin-post.php?action=wc_ptt_kargo_bulk_label&orders=123,456,789&_wpnonce=...
	 */
	public function render_bulk(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Yetkisiz.', 'wc-ptt-kargo' ), 403 );
		}
		check_admin_referer( 'wc_ptt_kargo_bulk_label' );

		$orders_raw = isset( $_GET['orders'] ) ? sanitize_text_field( wp_unslash( $_GET['orders'] ) ) : '';
		$ids = array_filter( array_map( 'intval', explode( ',', $orders_raw ) ) );
		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'Etiket basılacak sipariş seçilmedi.', 'wc-ptt-kargo' ), 400 );
		}
		$ids = array_slice( $ids, 0, 200 ); // Defansif üst limit — 200 etiket aynı sayfada yeter.

		$opts    = $this->settings->all();
		$blocks  = [];
		$skipped = [];

		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) { $skipped[] = $oid; continue; }
			$barkod = (string) $order->get_meta( Orders::META_BARKOD );
			if ( $barkod === '' ) { $skipped[] = $oid; continue; }

			$data    = $this->order_to_data( $order );
			$blocks[] = [ 'data' => $data, 'barkod' => $barkod, 'order' => $order ];

			$parca_raw = (string) $order->get_meta( Orders::META_PARCA_BARKODLAR );
			if ( $parca_raw !== '' ) {
				$parca = json_decode( $parca_raw, true );
				if ( is_array( $parca ) ) {
					foreach ( $parca as $pb ) {
						if ( $pb !== '' && $pb !== $barkod ) {
							$blocks[] = [ 'data' => $data, 'barkod' => (string) $pb, 'order' => $order ];
						}
					}
				}
			}
		}

		if ( empty( $blocks ) ) {
			wp_die( esc_html__( 'Hiçbir siparişte basılabilir barkod bulunamadı.', 'wc-ptt-kargo' ), 400 );
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_bulk_html( $blocks, $opts ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function build_bulk_html( array $blocks, array $opts ): string {
		// Her etiket için tek build_html çağrısı yapamayız (tam HTML doc dönüyor); sadece body içerikleri gerek.
		// Çözüm: her etiket için bir block render et, aralarına page-break koy.
		$count = count( $blocks );

		$inner_blocks = [];
		foreach ( $blocks as $i => $b ) {
			$is_last = ( $i === $count - 1 );
			// build_html tam doküman dönüyor; oradan <body>...</body> arasını çek.
			$full = $this->build_html( $b['data'], $opts, $b['barkod'], false, $b['order'] );
			if ( preg_match( '~<body[^>]*>(.*?)</body>~is', $full, $m ) ) {
				$body = $m[1];
				// Auto-print script ve dotted ayraç gereksiz; sadece print için tek script altta olacak.
				$body = preg_replace( '~<script>.*?</script>~is', '', $body );
				$inner_blocks[] = '<section class="ptt-label">' . $body . '</section>'
					. ( $is_last ? '' : '<div class="ptt-page-break"></div>' );
			}
		}

		ob_start();
		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( sprintf( __( 'PTT Toplu Etiket (%d adet)', 'wc-ptt-kargo' ), $count ) ); ?></title>
	<style>
		@page { size: 80mm auto; margin: 0; }
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; font-family: 'Courier New', Consolas, monospace; color: #111; }
		body { width: 80mm; padding: 0; background: #f5f5f5; }
		.ptt-label { width: 80mm; padding: 4mm 3mm; background: #fff; }
		/* Önizleme için boşluk; print'te page-break */
		.ptt-page-break { page-break-after: always; height: 6mm; background: #f5f5f5; }
		@media print {
			body { background: #fff; padding: 0; }
			.ptt-label { padding: 2mm; }
			.ptt-page-break { background: transparent; height: 0; }
		}
		.header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 2mm; margin-bottom: 3mm; }
		.header .logo { max-width: 60mm; max-height: 18mm; margin: 0 auto 1mm; display: block; }
		.header h1 { font-size: 11pt; margin: 0 0 1mm; letter-spacing: 1px; }
		.header .sub { font-size: 8pt; margin: 0; }
		.row-sep { border-top: 1px dashed #333; margin: 2mm 0; }
		.siparis-satir { display: flex; justify-content: space-between; align-items: baseline; font-size: 9pt; }
		.siparis-satir strong { font-size: 11pt; }
		.bolum-basligi { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #555; margin: 0 0 1mm; }
		.alici-adi { font-size: 11pt; font-weight: bold; margin: 0 0 1mm; text-transform: uppercase; }
		.alici-adres { font-size: 8.5pt; line-height: 1.3; margin: 0 0 1mm; }
		.alici-il { font-size: 9.5pt; font-weight: bold; margin: 0 0 1mm; }
		.alici-tel { font-size: 9pt; margin: 0; }
		.urun-listesi { font-size: 8.5pt; margin: 0; padding: 0; list-style: none; }
		.urun-listesi li { padding: 0.5mm 0; }
		.barkod-bolum { text-align: center; padding: 2mm 0; }
		.barkod-svg svg { width: 100%; height: auto; max-height: 18mm; display: block; }
		.barkod-no { font-size: 13pt; font-weight: bold; letter-spacing: 2px; margin: 1mm 0 0; text-align: center; }
		.gonderici { font-size: 7.5pt; text-align: center; line-height: 1.35; }
		.dotted { display: none; }
	</style>
</head>
<body>
	<?php echo implode( "\n", $inner_blocks ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<script>
		window.addEventListener('load', function () {
			setTimeout(function () { window.print(); }, 300);
		});
	</script>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	public function bulk_url( array $order_ids ): string {
		return add_query_arg(
			[
				'action'   => 'wc_ptt_kargo_bulk_label',
				'orders'   => implode( ',', array_map( 'intval', $order_ids ) ),
				'_wpnonce' => wp_create_nonce( 'wc_ptt_kargo_bulk_label' ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Yetkisiz.', 'wc-ptt-kargo' ), 403 );
		}
		check_admin_referer( 'wc_ptt_kargo_label' );

		$order_id = isset( $_GET['order'] ) ? (int) $_GET['order'] : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			wp_die( esc_html__( 'Sipariş bulunamadı.', 'wc-ptt-kargo' ), 404 );
		}

		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $barkod === '' ) {
			wp_die( esc_html__( 'Bu sipariş için PTT barkodu henüz oluşturulmamış.', 'wc-ptt-kargo' ), 400 );
		}

		$data = $this->order_to_data( $order );
		$opts = $this->settings->all();

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_html( $data, $opts, $barkod, true, $order ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Canlı önizleme: form POST'undan ayar değerlerini alır, sample sipariş ile etiket render eder.
	 */
	public function render_preview(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Yetkisiz.', 'wc-ptt-kargo' ), 403 );
		}
		check_admin_referer( 'wc_ptt_kargo_preview' );

		$base = $this->settings->all();

		$override = isset( $_POST['preview'] ) && is_array( $_POST['preview'] )
			? $this->sanitize_preview_input( wp_unslash( $_POST['preview'] ) )
			: [];

		$opts = array_merge( $base, $override );

		$data    = $this->sample_data();
		$barkod  = $this->sample_barkod( $opts );

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_html( $data, $opts, $barkod, false, null ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function sanitize_preview_input( array $input ): array {
		$out = [];
		$text_keys = [
			'gonderici_ad', 'gonderici_adres', 'gonderici_il', 'gonderici_ilce',
			'gonderici_posta', 'gonderici_tel',
			'label_header_title', 'label_header_subtitle',
		];
		foreach ( $text_keys as $k ) {
			if ( isset( $input[ $k ] ) ) $out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
		}
		if ( isset( $input['label_logo_url'] ) ) {
			$out['label_logo_url'] = esc_url_raw( (string) $input['label_logo_url'] );
		}
		foreach ( [ 'label_show_order', 'label_show_recipient', 'label_show_products', 'label_show_barcode', 'label_show_sender' ] as $tk ) {
			if ( array_key_exists( $tk, $input ) ) {
				$out[ $tk ] = ! empty( $input[ $tk ] ) ? 1 : 0;
			}
		}
		if ( isset( $input['barkod_prefix'] ) ) {
			$out['barkod_prefix'] = preg_replace( '/\D/', '', (string) $input['barkod_prefix'] );
		}
		return $out;
	}

	private function order_to_data( \WC_Order $order ): array {
		$ad = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( $ad === '' ) $ad = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		$adres_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
		$adres_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
		$adres   = trim( $adres_1 . ( $adres_2 ? ' ' . $adres_2 : '' ) );

		$ilce   = $order->get_shipping_city() ?: $order->get_billing_city();
		$il_kod = $order->get_shipping_state() ?: $order->get_billing_state();
		$il     = $il_kod;
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( 'TR' );
			if ( isset( $states[ $il_kod ] ) ) $il = $states[ $il_kod ];
		}

		$urunler = [];
		foreach ( $order->get_items() as $item ) {
			$urunler[] = $item->get_name();
		}

		return [
			'siparis_no'    => $order->get_order_number(),
			'siparis_tarih' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '',
			'ad'            => $ad,
			'adres'         => $adres,
			'ilce'          => $ilce,
			'il'            => $il,
			'posta'         => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			'tel'           => $order->get_billing_phone(),
			'urunler'       => $urunler,
		];
	}

	private function sample_data(): array {
		return [
			'siparis_no'    => '1234',
			'siparis_tarih' => date_i18n( 'd.m.Y H:i' ),
			'ad'            => __( 'Örnek Müşteri', 'wc-ptt-kargo' ),
			'adres'         => __( 'Örnek Mah. Numune Sok. No:1 D:2', 'wc-ptt-kargo' ),
			'ilce'          => __( 'Kadıköy', 'wc-ptt-kargo' ),
			'il'             => __( 'İstanbul', 'wc-ptt-kargo' ),
			'posta'         => '34710',
			'tel'           => '0555 123 45 67',
			'urunler'       => [ __( 'Örnek Ürün 1', 'wc-ptt-kargo' ), __( 'Örnek Ürün 2', 'wc-ptt-kargo' ) ],
		];
	}

	private function sample_barkod( array $opts ): string {
		$prefix = (string) ( $opts['barkod_prefix'] ?? '' );
		$prefix = preg_replace( '/\D/', '', $prefix );
		if ( strlen( $prefix ) >= 8 ) {
			$prefix = substr( $prefix, 0, 8 );
		} else {
			$prefix = str_pad( $prefix, 8, '0', STR_PAD_LEFT );
		}
		$twelve = $prefix . '0001';
		return $twelve . Barcode::check_digit( $twelve );
	}

	/**
	 * @param array         $data    Sipariş verisi (ad, adres, urunler, vb.)
	 * @param array         $opts    Settings array
	 * @param string        $barkod  13 haneli barkod
	 * @param bool          $auto_print  load'da window.print() çağırılsın mı?
	 * @param \WC_Order|null $order  Filter'lar için (preview'de null)
	 */
	private function build_html( array $data, array $opts, string $barkod, bool $auto_print, $order ): string {
		$gonderici_ad    = (string) ( $opts['gonderici_ad'] ?? '' );
		$gonderici_adres = (string) ( $opts['gonderici_adres'] ?? '' );
		$gonderici_il    = (string) ( $opts['gonderici_il'] ?? '' );
		$gonderici_ilce  = (string) ( $opts['gonderici_ilce'] ?? '' );
		$gonderici_tel   = (string) ( $opts['gonderici_tel'] ?? '' );

		$logo_url     = (string) ( $opts['label_logo_url'] ?? '' );
		$header_title = (string) ( $opts['label_header_title'] ?? '' );
		if ( $header_title === '' ) {
			$header_title = $gonderici_ad !== '' ? $gonderici_ad : (string) get_bloginfo( 'name' );
		}
		$header_sub = (string) ( $opts['label_header_subtitle'] ?? '' );

		$header_data = (array) apply_filters( 'wc_ptt_kargo_label_header', [
			'logo_url' => $logo_url,
			'title'    => $header_title,
			'subtitle' => $header_sub,
		], $order );

		$urunler = (array) apply_filters( 'wc_ptt_kargo_label_products', $data['urunler'], $order );

		$show = [
			'order'     => ! empty( $opts['label_show_order'] ),
			'recipient' => ! empty( $opts['label_show_recipient'] ),
			'products'  => ! empty( $opts['label_show_products'] ),
			'barcode'   => ! empty( $opts['label_show_barcode'] ),
			'sender'    => ! empty( $opts['label_show_sender'] ),
		];

		$barkod_svg = Barcode::svg( $barkod, 90, 2 );

		ob_start();
		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<title>PTT Etiketi #<?php echo esc_html( $data['siparis_no'] ); ?></title>
	<style>
		@page { size: 80mm auto; margin: 0; }
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; font-family: 'Courier New', Consolas, monospace; color: #111; }
		body { width: 80mm; padding: 4mm 3mm; }
		.header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 2mm; margin-bottom: 3mm; }
		.header .logo { max-width: 60mm; max-height: 18mm; margin: 0 auto 1mm; display: block; }
		.header h1 { font-size: 11pt; margin: 0 0 1mm; letter-spacing: 1px; }
		.header .sub { font-size: 8pt; margin: 0; }
		.row-sep { border-top: 1px dashed #333; margin: 2mm 0; }
		.siparis-satir { display: flex; justify-content: space-between; align-items: baseline; font-size: 9pt; }
		.siparis-satir strong { font-size: 11pt; }
		.bolum-basligi { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #555; margin: 0 0 1mm; }
		.alici-adi { font-size: 11pt; font-weight: bold; margin: 0 0 1mm; text-transform: uppercase; }
		.alici-adres { font-size: 8.5pt; line-height: 1.3; margin: 0 0 1mm; }
		.alici-il { font-size: 9.5pt; font-weight: bold; margin: 0 0 1mm; }
		.alici-tel { font-size: 9pt; margin: 0; }
		.urun-listesi { font-size: 8.5pt; margin: 0; padding: 0; list-style: none; }
		.urun-listesi li { padding: 0.5mm 0; }
		.barkod-bolum { text-align: center; padding: 2mm 0; }
		.barkod-svg svg { width: 100%; height: auto; max-height: 18mm; display: block; }
		.barkod-no { font-size: 13pt; font-weight: bold; letter-spacing: 2px; margin: 1mm 0 0; text-align: center; }
		.gonderici { font-size: 7.5pt; text-align: center; line-height: 1.35; }
		.dotted { border-bottom: 1px dotted #999; margin: 2mm 0; height: 0; }
		@media print {
			body { padding: 2mm; }
		}
	</style>
</head>
<body>
	<?php if ( ! empty( $header_data['logo_url'] ) || ! empty( $header_data['title'] ) || ! empty( $header_data['subtitle'] ) ) : ?>
	<div class="header">
		<?php if ( ! empty( $header_data['logo_url'] ) ) : ?>
			<img class="logo" src="<?php echo esc_url( $header_data['logo_url'] ); ?>" alt="">
		<?php endif; ?>
		<?php if ( ! empty( $header_data['title'] ) ) : ?>
			<h1><?php echo esc_html( mb_strtoupper( $header_data['title'], 'UTF-8' ) ); ?></h1>
		<?php endif; ?>
		<?php if ( ! empty( $header_data['subtitle'] ) ) : ?>
			<p class="sub"><?php echo esc_html( $header_data['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $show['order'] ) : ?>
	<div class="siparis-satir">
		<strong>#<?php echo esc_html( $data['siparis_no'] ); ?></strong>
		<span><?php echo esc_html( $data['siparis_tarih'] ); ?></span>
	</div>
	<div class="row-sep"></div>
	<?php endif; ?>

	<?php if ( $show['recipient'] ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Alıcı', 'wc-ptt-kargo' ); ?></p>
	<p class="alici-adi"><?php echo esc_html( mb_strtoupper( (string) $data['ad'], 'UTF-8' ) ); ?></p>
	<p class="alici-adres"><?php echo esc_html( $data['adres'] ); ?></p>
	<p class="alici-il"><?php echo esc_html( $data['ilce'] ); ?> / <?php echo esc_html( $data['il'] ); ?> <?php echo esc_html( $data['posta'] ); ?></p>
	<?php if ( ! empty( $data['tel'] ) ) : ?>
	<p class="alici-tel">Tel: <?php echo esc_html( $data['tel'] ); ?></p>
	<?php endif; ?>
	<div class="row-sep"></div>
	<?php endif; ?>

	<?php if ( $show['products'] && ! empty( $urunler ) ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Ürünler', 'wc-ptt-kargo' ); ?></p>
	<ul class="urun-listesi">
		<?php foreach ( $urunler as $u ) : ?>
			<li>- <?php echo esc_html( $u ); ?></li>
		<?php endforeach; ?>
	</ul>
	<div class="row-sep"></div>
	<?php endif; ?>

	<?php if ( $show['barcode'] ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Kargo Barkodu', 'wc-ptt-kargo' ); ?></p>
	<div class="barkod-bolum">
		<div class="barkod-svg"><?php echo $barkod_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<p class="barkod-no"><?php echo esc_html( $barkod ); ?></p>
	</div>
	<div class="row-sep"></div>
	<?php endif; ?>

	<?php if ( $show['sender'] ) : ?>
	<div class="gonderici">
		<?php echo esc_html( $gonderici_ad ); ?><br>
		<?php echo esc_html( $gonderici_adres ); ?><br>
		<?php echo esc_html( $gonderici_ilce . ' / ' . $gonderici_il ); ?><br>
		<?php if ( $gonderici_tel ) : ?>Tel: <?php echo esc_html( $gonderici_tel ); ?><?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="dotted"></div>

	<?php if ( $auto_print ) : ?>
	<script>
		window.addEventListener('load', function () {
			setTimeout(function () { window.print(); }, 200);
		});
	</script>
	<?php endif; ?>
</body>
</html>
		<?php
		$html = (string) ob_get_clean();
		return (string) apply_filters( 'wc_ptt_kargo_label_html', $html, $order, $barkod );
	}

	public function label_url( int $order_id ): string {
		// wp_nonce_url() HTML-escape uygular (& → &amp;) → JSON üzerinden JS'ye gidince
		// window.open URL'inde _wpnonce parametresi kaybolur. Raw URL üretmek için add_query_arg.
		return add_query_arg(
			[
				'action'   => 'wc_ptt_kargo_label',
				'order'    => $order_id,
				'_wpnonce' => wp_create_nonce( 'wc_ptt_kargo_label' ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public function preview_url(): string {
		return admin_url( 'admin-post.php?action=wc_ptt_kargo_preview' );
	}
}
