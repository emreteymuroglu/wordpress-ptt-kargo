<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menü + "Kargo Siparişleri" + "Ayarlar" sayfalarını kaydeder ve AJAX handler'larını yönetir.
 */
final class Admin_Page {
	public const MENU_SLUG    = 'wc-ptt-kargo';
	public const CAPABILITY   = 'manage_woocommerce';
	public const AJAX_SEND        = 'wc_ptt_kargo_send';
	public const AJAX_REFRESH     = 'wc_ptt_kargo_refresh';
	public const AJAX_TAKIP       = 'wc_ptt_kargo_takip';
	public const AJAX_PREPARE     = 'wc_ptt_kargo_prepare';
	public const AJAX_TEST        = 'wc_ptt_kargo_test_conn';
	public const AJAX_LOGS        = 'wc_ptt_kargo_clear_logs';
	public const AJAX_CANCEL      = 'wc_ptt_kargo_cancel';
	public const AJAX_COURIER     = 'wc_ptt_kargo_courier';
	public const AJAX_DROP_POINT  = 'wc_ptt_kargo_drop_point';
	public const NONCE_ACTION = 'wc_ptt_kargo';

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
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );

		add_action( 'wp_ajax_' . self::AJAX_SEND,    [ $this, 'ajax_send' ] );
		add_action( 'wp_ajax_' . self::AJAX_REFRESH, [ $this, 'ajax_refresh' ] );
		add_action( 'wp_ajax_' . self::AJAX_TAKIP,   [ $this, 'ajax_takip' ] );
		add_action( 'wp_ajax_' . self::AJAX_PREPARE, [ $this, 'ajax_prepare' ] );
		add_action( 'wp_ajax_' . self::AJAX_TEST,    [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_' . self::AJAX_LOGS,    [ $this, 'ajax_clear_logs' ] );
		add_action( 'wp_ajax_' . self::AJAX_CANCEL,         [ $this, 'ajax_cancel' ] );
		add_action( 'wp_ajax_' . self::AJAX_COURIER,        [ $this, 'ajax_courier' ] );
		add_action( 'wp_ajax_' . self::AJAX_DROP_POINT,     [ $this, 'ajax_drop_point' ] );
	}

	public function menu(): void {
		add_menu_page(
			__( 'WC PTT Kargo', 'wc-ptt-kargo' ),
			__( 'PTT Kargo', 'wc-ptt-kargo' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ],
			'dashicons-archive',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Kargo Siparişleri', 'wc-ptt-kargo' ),
			__( 'Kargo Siparişleri', 'wc-ptt-kargo' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Kurye Çağır', 'wc-ptt-kargo' ),
			__( 'Kurye Çağır', 'wc-ptt-kargo' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-kurye',
			[ $this, 'render_courier_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Ayarlar', 'wc-ptt-kargo' ),
			__( 'Ayarlar', 'wc-ptt-kargo' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-ayarlar',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'PTT Loglar', 'wc-ptt-kargo' ),
			__( 'Loglar', 'wc-ptt-kargo' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-logs',
			[ $this, 'render_logs_page' ]
		);
	}

	public function assets( string $hook ): void {
		// Hook string'i WC/WP versiyonuna göre değişiyor (HPOS edit screen'de yakalayamıyorduk).
		// JS event handler'ları zaten DOM seviyesinde (`document.on('click', '.js-ptt-send', ...)`)
		// çalışıyor — script'i tüm admin sayfalarında yüklemek minimal overhead, maksimum güvenilirlik.
		// CSS+JS toplam ~50KB; sadece login'li admin'de yüklenir.
		wp_enqueue_style( 'wc-ptt-kargo-admin', WC_PTT_KARGO_URL . 'admin/assets/admin.css', [], WC_PTT_KARGO_VERSION );
		wp_enqueue_script( 'wc-ptt-kargo-admin', WC_PTT_KARGO_URL . 'admin/assets/admin.js', [ 'jquery' ], WC_PTT_KARGO_VERSION, true );

		// Settings sayfasında ek JS (live preview, media library, test connection, ürün arama)
		$is_plugin_page = strpos( $hook, self::MENU_SLUG ) !== false;
		$is_settings    = isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG . '-ayarlar';
		if ( $is_plugin_page && $is_settings ) {
			wp_enqueue_media();
			// WooCommerce enhanced select (ürün arama widget'ı için)
			if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
				wp_enqueue_script( 'wc-enhanced-select' );
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
			wp_enqueue_script(
				'wc-ptt-kargo-settings',
				WC_PTT_KARGO_URL . 'admin/assets/settings.js',
				[ 'jquery', 'wc-ptt-kargo-admin' ],
				WC_PTT_KARGO_VERSION,
				true
			);
		}
		wp_localize_script(
			'wc-ptt-kargo-admin',
			'WcPttKargo',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'actions'   => [
					'send'         => self::AJAX_SEND,
					'refresh'      => self::AJAX_REFRESH,
					'takip'        => self::AJAX_TAKIP,
					'prepare'      => self::AJAX_PREPARE,
					'cancelKargo'  => self::AJAX_CANCEL,
					'courier'      => self::AJAX_COURIER,
					'dropPoint'    => self::AJAX_DROP_POINT,
				],
				'i18n'      => [
					'sending'       => __( 'Gönderiliyor...', 'wc-ptt-kargo' ),
					'success'       => __( 'Başarılı! Barkod: ', 'wc-ptt-kargo' ),
					'error'         => __( 'Hata: ', 'wc-ptt-kargo' ),
					'summaryTitle'  => __( 'Kargo Özeti', 'wc-ptt-kargo' ),
					'customer'      => __( 'Müşteri', 'wc-ptt-kargo' ),
					'missingWarn'   => __( 'Bazı alanlar eksik. Boş bırakabilirsin ya da aşağıdan doldur.', 'wc-ptt-kargo' ),
					'confirm'       => __( 'Onayla ve Gönder', 'wc-ptt-kargo' ),
					'cancel'        => __( 'İptal', 'wc-ptt-kargo' ),
					'orderWord'     => __( 'sipariş', 'wc-ptt-kargo' ),
					'shipBtn'       => __( 'Kargoya İlet', 'wc-ptt-kargo' ),
					'unknownErr'    => __( 'Bilinmeyen hata', 'wc-ptt-kargo' ),
					'serverErr'     => __( 'Sunucu hatası.', 'wc-ptt-kargo' ),
					'prepareErr'    => __( 'Hazırlanamadı.', 'wc-ptt-kargo' ),
					'trackErr'      => __( 'Takip sorgulanamadı.', 'wc-ptt-kargo' ),
					'trackBarkod'   => __( 'Barkod:', 'wc-ptt-kargo' ),
					'trackStatus'   => __( 'Durum:', 'wc-ptt-kargo' ),
					'trackEvents'   => __( 'Hareketler:', 'wc-ptt-kargo' ),
					'testing'       => __( 'Test ediliyor...', 'wc-ptt-kargo' ),
					'cancelConfirm' => __( "Bu sipariş için PTT'ye gönderilen kayıt silinecek. Eski barkod yeniden kullanılamaz; sipariş tekrar gönderilirse yeni bir barkod tüketilir. Devam edilsin mi?", 'wc-ptt-kargo' ),
					'canceling'     => __( 'İptal ediliyor...', 'wc-ptt-kargo' ),
					'cancelOk'      => __( 'PTT gönderisi iptal edildi.', 'wc-ptt-kargo' ),
					'cancelErr'     => __( 'İptal başarısız: ', 'wc-ptt-kargo' ),
					'cancelBtn'     => __( 'PTT Gönderisini İptal Et', 'wc-ptt-kargo' ),
					'insuranceLabel' => __( 'Sigortalı Gönder (Değerli Kargo)', 'wc-ptt-kargo' ),
					'insuranceAmount' => __( 'Sigorta Tutarı (TL)', 'wc-ptt-kargo' ),
					'codInfo'        => __( 'Kapıda Ödeme aktif:', 'wc-ptt-kargo' ),
					'dropPointTitle' => __( 'Şu an bulunduğu PTT şubesi', 'wc-ptt-kargo' ),
					'courierSending' => __( 'Kurye çağrılıyor...', 'wc-ptt-kargo' ),
					'courierOk'      => __( 'Kurye siparişi alındı!', 'wc-ptt-kargo' ),
					'courierErr'     => __( 'Kurye çağırma başarısız: ', 'wc-ptt-kargo' ),
				],
			]
		);
	}

	public function render_orders_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Yetkisiz', 'wc-ptt-kargo' ) );

		$show   = isset( $_GET['show'] ) && in_array( $_GET['show'], [ 'pending', 'sent', 'all' ], true ) ? $_GET['show'] : 'pending';
		$orders = $this->orders->eligible_orders( 100, $show );

		include WC_PTT_KARGO_DIR . 'admin/views/orders-list.php';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Yetkisiz', 'wc-ptt-kargo' ) );
		include WC_PTT_KARGO_DIR . 'admin/views/settings.php';
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Yetkisiz', 'wc-ptt-kargo' ) );
		include WC_PTT_KARGO_DIR . 'admin/views/logs.php';
	}

	public function render_courier_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( esc_html__( 'Yetkisiz', 'wc-ptt-kargo' ) );
		$settings = $this->settings;
		include WC_PTT_KARGO_DIR . 'admin/views/courier.php';
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		// İsteğe bağlı: form'dan gelen geçici credential'larla test et (kaydetmeden).
		// Settings'i runtime override etmek için yeni Settings instance kullanmak yerine,
		// PTT_Client kendi settings'ini okuyor — geçici override için global bir filter kullanırız.
		$override_env   = isset( $_POST['environment'] ) && in_array( $_POST['environment'], [ 'test', 'prod' ], true ) ? sanitize_key( $_POST['environment'] ) : null;
		$override_id    = isset( $_POST['musteri_id'] ) ? preg_replace( '/\D/', '', (string) $_POST['musteri_id'] ) : null;
		$override_pwd   = isset( $_POST['sifre'] ) ? (string) $_POST['sifre'] : null;
		$override_pwd   = $override_pwd !== null ? wp_unslash( $override_pwd ) : null;

		$has_override = $override_env !== null || $override_id !== null || ( $override_pwd !== null && $override_pwd !== '' );
		$applied_filter = null;

		if ( $has_override ) {
			$applied_filter = function ( $value, $option ) use ( $override_env, $override_id, $override_pwd ) {
				if ( $option !== Settings::OPTION_KEY || ! is_array( $value ) ) return $value;
				if ( $override_env !== null ) $value['environment'] = $override_env;
				if ( $override_id !== null )  $value['musteri_id']  = $override_id;
				if ( $override_pwd !== null && $override_pwd !== '' ) {
					$value['sifre_enc'] = Settings::encrypt( $override_pwd );
				}
				return $value;
			};
			add_filter( 'option_' . Settings::OPTION_KEY, $applied_filter, 10, 2 );
		}

		$result = $this->client->test_connection();

		if ( $applied_filter !== null ) {
			remove_filter( 'option_' . Settings::OPTION_KEY, $applied_filter, 10 );
		}

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( [ 'message' => $result['mesaj'] ] );
		}
		wp_send_json_error( [ 'message' => $result['mesaj'] ?? __( 'Bilinmeyen hata', 'wc-ptt-kargo' ) ] );
	}

	public function ajax_clear_logs(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		Logs::clear();
		wp_send_json_success( [ 'message' => __( 'Loglar temizlendi.', 'wc-ptt-kargo' ) ] );
	}

	public function ajax_refresh(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$show   = isset( $_POST['show'] ) ? sanitize_key( $_POST['show'] ) : 'pending';
		$orders = $this->orders->eligible_orders( 100, $show );

		ob_start();
		include WC_PTT_KARGO_DIR . 'admin/views/orders-table.php';
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html, 'count' => count( $orders ) ] );
	}

	public function ajax_prepare(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'wc-ptt-kargo' ) ], 404 );

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu sipariş için zaten barkod oluşturulmuş: ', 'wc-ptt-kargo' ) . $existing ], 409 );
		}

		$payload = $this->orders->to_ptt_payload( $order );

		// Retry için bekleyen barkod varsa popup'ta bilgi göster (yeni barkod tüketilmeyecek).
		$pending_barkod = $this->orders->get_pending_barkod( $order );

		// Sigorta default kapalı — popup'tan manuel açılır. Default tutar olarak sipariş toplamı önerilir.
		$insurance_default = false;
		$insurance_amount  = (float) $order->get_total();
		$is_cod = false;
		$cod_methods = $this->settings->cod_payment_methods();
		if ( ! empty( $cod_methods ) ) {
			$is_cod = in_array( (string) $order->get_payment_method(), $cod_methods, true );
		}

		wp_send_json_success(
			[
				'order_id'           => $order_id,
				'order_no'           => $payload['order_no'],
				'fields'             => $payload['fields'],
				'missing'            => $payload['missing'],
				'posta'              => $payload['posta'],
				'insurance_default'  => $insurance_default,
				'insurance_amount'   => number_format( $insurance_amount, 2, '.', '' ),
				'is_cod'             => $is_cod,
				'cod_amount'         => number_format( (float) $order->get_total(), 2, '.', '' ),
				'payment_method'     => $order->get_payment_method_title(),
				'pending_barkod'     => $pending_barkod,
			]
		);
	}

	public function ajax_send(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$override = isset( $_POST['override'] ) && is_array( $_POST['override'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['override'] ) ) : [];

		$order = wc_get_order( $order_id );
		if ( ! $order ) wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'wc-ptt-kargo' ) ], 404 );

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu sipariş için zaten barkod oluşturulmuş: ', 'wc-ptt-kargo' ) . $existing ], 409 );
		}

		$payload = $this->orders->to_ptt_payload( $order );

		$shipping_int_keys   = [ 'agirlik', 'desi', 'en', 'boy', 'yukseklik' ];
		$money_keys          = [ 'deger_ucreti' ]; // ondalıklı (number_format 12,2)
		$customer_field_keys = [ 'aliciAdi', 'aAdres', 'aliciIlAdi', 'aliciIlceAdi', 'aliciSms', 'aliciEmail' ];
		$allowed_override    = array_unique( array_merge( $customer_field_keys, $shipping_int_keys, $money_keys ) );

		// Sigorta açma/kapama bayrağı (popup toggle). 'on' ise deger_ucreti & DK eklenir; 'off' ise temizlenir.
		$insurance_toggle = isset( $override['__insurance'] ) ? sanitize_key( (string) $override['__insurance'] ) : '';
		unset( $override['__insurance'] );

		foreach ( $override as $k => $v ) {
			if ( $v === '' ) continue;
			if ( ! in_array( $k, $allowed_override, true ) ) continue;

			if ( in_array( $k, $shipping_int_keys, true ) ) {
				// Boyut/ağırlık alanları yalnızca pozitif tamsayı kabul eder; PTT 0 ya da negatif değerleri reddeder.
				$num = (int) $v;
				if ( $num <= 0 ) continue;
				$payload['fields'][ $k ] = $num;
			} elseif ( in_array( $k, $money_keys, true ) ) {
				$num = (float) $v;
				if ( $num <= 0 ) continue;
				$payload['fields'][ $k ] = number_format( $num, 2, '.', '' );
			} else {
				$payload['fields'][ $k ] = $v;
			}

			if ( isset( $payload['missing'][ $k ] ) ) unset( $payload['missing'][ $k ] );
		}

		// Sigorta toggle'ı: 'on' ise deger_ucreti override'tan geldiği için ekhizmet'e DK eklenir;
		// 'off' ise deger_ucreti silinir + DK kodu varsa temizlenir.
		// PTT doc: deger_ucreti gönderiliyorsa ekhizmet 'DK' içermek zorunda.
		$ins_eh = strtoupper( (string) $this->settings->get( 'insurance_extra_service_code', 'DK' ) );
		if ( $insurance_toggle === 'on' && ! empty( $payload['fields']['deger_ucreti'] ) && $ins_eh !== '' ) {
			$payload['fields']['ekhizmet'] = Orders::merge_extra_service_codes(
				(string) ( $payload['fields']['ekhizmet'] ?? '' ),
				$ins_eh
			);
		} elseif ( $insurance_toggle === 'off' ) {
			unset( $payload['fields']['deger_ucreti'] );
			if ( $ins_eh !== '' && isset( $payload['fields']['ekhizmet'] ) ) {
				$payload['fields']['ekhizmet'] = str_replace( $ins_eh, '', strtoupper( (string) $payload['fields']['ekhizmet'] ) );
			}
		}

		$parca_adet  = isset( $_POST['parca_adet'] ) ? max( 1, (int) $_POST['parca_adet'] ) : 1;
		$irsaliye_no = isset( $_POST['irsaliye_no'] ) ? sanitize_text_field( (string) $_POST['irsaliye_no'] ) : '';

		// Retry: önceki başarısız denemede tüketilmiş barkod varsa onu reuse et — yeni barkod yakma.
		$pending = $this->orders->get_pending_barkod( $order );
		$barkodlar = [];

		if ( $parca_adet === 1 ) {
			if ( $pending !== '' ) {
				$barkodlar[] = $pending;
			} else {
				$next = $this->barcode->next();
				if ( $next === null ) {
					wp_send_json_error( [ 'message' => __( 'Barkod aralığı tükendi. Lütfen ayarlardan yeni aralık tanımlayın.', 'wc-ptt-kargo' ) ], 500 );
				}
				$barkodlar[] = $next;
			}
		} else {
			// Çok parçalı: pending varsa ilk parça olarak kullan, kalanlar yeni next() ile alınır.
			if ( $pending !== '' ) $barkodlar[] = $pending;
			while ( count( $barkodlar ) < $parca_adet ) {
				$next = $this->barcode->next();
				if ( $next === null ) {
					wp_send_json_error( [ 'message' => __( 'Barkod aralığı yetersiz; tüm parçalar için yeterli barkod yok.', 'wc-ptt-kargo' ) ], 500 );
				}
				$barkodlar[] = $next;
			}
		}

		$ref = $this->orders->build_ref( $order );

		$base_fields = $payload['fields'];
		$base_fields['musteriReferansNo'] = $ref;

		if ( $parca_adet === 1 ) {
			$base_fields['barkodNo'] = $barkodlar[0];
			$result = $this->client->kabul_ekle( $base_fields, $order->get_id() );
		} else {
			$result = $this->client->kabul_ekle_parcali_barkod( $base_fields, $barkodlar, $irsaliye_no, $order->get_id() );
		}

		if ( empty( $result['success'] ) ) {
			$err_msg = (string) ( $result['mesaj'] ?? __( 'Bilinmeyen hata', 'wc-ptt-kargo' ) );
			// Tüketilmiş barkod(lar) — ilk parçayı pending olarak sakla; kalanlar yeni denemede next()'le tamamlanır.
			$pending_to_store = $barkodlar[0] ?? '';
			$this->orders->mark_error(
				$order,
				$err_msg,
				(string) ( $result['raw'] ?? '' ),
				(string) ( $result['request'] ?? '' ),
				$pending_to_store
			);
			do_action( 'wc_ptt_kargo_after_error', $order, $err_msg, $result );
			wp_send_json_error(
				[
					'message'        => $result['mesaj'] ?? __( 'PTT gönderimi başarısız.', 'wc-ptt-kargo' ),
					'raw'            => $result['raw'] ?? '',
					'request'        => $result['request'] ?? '',
					'parca_results'  => $result['parca_results'] ?? null,
				],
				502
			);
		}

		$returned_barkod = (string) ( $result['barkod'] ?? $barkodlar[0] );
		$takip_url       = (string) ( $result['takip_url'] ?? '' );
		$dosya_adi       = (string) ( $result['dosya_adi'] ?? '' );

		$this->orders->mark_sent(
			$order,
			$returned_barkod,
			$ref,
			$takip_url,
			(string) ( $result['raw'] ?? '' ),
			(string) ( $result['request'] ?? '' ),
			(string) ( $result['mesaj'] ?? '' ),
			$dosya_adi
		);
		if ( $parca_adet > 1 ) {
			$this->orders->mark_parca( $order, $barkodlar, $irsaliye_no );
		}

		do_action( 'wc_ptt_kargo_after_send', $order, $returned_barkod, $result );

		wp_send_json_success(
			[
				'barkod'    => $returned_barkod,
				'barkodlar' => $barkodlar,
				'takip_url' => $takip_url,
				'label_url' => $this->label->label_url( $order_id ),
				'mesaj'     => $result['mesaj'] ?? __( 'Gönderi oluşturuldu.', 'wc-ptt-kargo' ),
				'parca_results' => $result['parca_results'] ?? null,
			]
		);
	}

	/**
	 * Henüz PTT tarafında kabulü yapılmamış bir gönderiyi iptal eder.
	 * Önce barkod ile dener; barkod yoksa veya başarısız olursa referans no ile fallback yapar.
	 *
	 * Başarı sonrası META_BARKOD/REF/TAKIP_URL temizlenir, status STATUS_CANCELED'a çekilir.
	 * Kullanıcı dilerse aynı siparişi tekrar göndererek yeni bir barkod tüketir.
	 */
	public function ajax_cancel(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'wc-ptt-kargo' ) ], 404 );

		$status = (string) $order->get_meta( Orders::META_STATUS );
		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );
		$dosya  = (string) $order->get_meta( Orders::META_DOSYA_ADI );

		if ( $status !== Orders::STATUS_SENT || $barkod === '' ) {
			wp_send_json_error(
				[ 'message' => __( 'Bu sipariş için iptal edilebilir bir PTT kaydı bulunamadı.', 'wc-ptt-kargo' ) ],
				400
			);
		}

		$result = $this->client->barkod_veri_sil( $barkod, $dosya, $order_id );

		if ( empty( $result['success'] ) && $ref !== '' ) {
			$ref_result = $this->client->referans_veri_sil( $ref, $dosya, $order_id );
			if ( ! empty( $ref_result['success'] ) ) {
				$result = $ref_result;
				$result['fallback'] = 'referansVeriSil';
			}
		}

		if ( empty( $result['success'] ) ) {
			$err = (string) ( $result['mesaj'] ?? __( 'PTT iptal isteği başarısız.', 'wc-ptt-kargo' ) );
			do_action( 'wc_ptt_kargo_after_cancel_error', $order, $barkod, $err, $result );
			wp_send_json_error(
				[
					'message' => $err,
					'raw'     => (string) ( $result['raw'] ?? '' ),
					'request' => (string) ( $result['request'] ?? '' ),
				],
				502
			);
		}

		$this->orders->mark_canceled(
			$order,
			$barkod,
			(string) ( $result['mesaj'] ?? '' ),
			(string) ( $result['raw'] ?? '' ),
			(string) ( $result['request'] ?? '' )
		);

		do_action( 'wc_ptt_kargo_after_cancel', $order, $barkod, $result );

		wp_send_json_success(
			[
				'message'      => $result['mesaj'] ?? __( 'PTT gönderisi iptal edildi.', 'wc-ptt-kargo' ),
				'old_barkod'   => $barkod,
				'used_method'  => $result['fallback'] ?? 'barkodVeriSil',
			]
		);
	}

	/**
	 * Kurye çağırma AJAX'ı: siparisIstekEkle2 servisine form alanlarını iletir.
	 * Form sadece koleksiyon parametreleri (adet, ağırlık, desi vb.) bekler — gönderici bilgisi
	 * Sender settings'inden okunur.
	 */
	public function ajax_courier(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$params = [
			'adet'                 => isset( $_POST['adet'] ) ? max( 1, (int) $_POST['adet'] ) : 0,
			'agirlik'              => isset( $_POST['agirlik'] )   ? max( 0, (int) $_POST['agirlik'] )   : 0,
			'desi'                 => isset( $_POST['desi'] )      ? max( 0, (int) $_POST['desi'] )      : 0,
			'en'                   => isset( $_POST['en'] )        ? max( 0, (int) $_POST['en'] )        : 0,
			'boy'                  => isset( $_POST['boy'] )       ? max( 0, (int) $_POST['boy'] )       : 0,
			'yukseklik'            => isset( $_POST['yukseklik'] ) ? max( 0, (int) $_POST['yukseklik'] ) : 0,
			'ekhizmet'             => isset( $_POST['ekhizmet'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $_POST['ekhizmet'] ) ) : '',
			'deger_konulmus_ucret' => isset( $_POST['deger_konulmus_ucret'] ) ? number_format( (float) $_POST['deger_konulmus_ucret'], 2, '.', '' ) : '',
			'randevu_baslangic'    => isset( $_POST['randevu_baslangic'] ) ? sanitize_text_field( (string) $_POST['randevu_baslangic'] ) : '',
			'randevu_bitis'        => isset( $_POST['randevu_bitis'] ) ? sanitize_text_field( (string) $_POST['randevu_bitis'] ) : '',
			'ucret'                => isset( $_POST['ucret'] ) ? (float) $_POST['ucret'] : 0,
		];

		if ( $params['adet'] <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Geçerli bir paket sayısı girin.', 'wc-ptt-kargo' ) ], 400 );
		}

		$result = $this->client->siparis_istek_ekle2( $params );

		if ( ! empty( $result['success'] ) ) {
			do_action( 'wc_ptt_kargo_after_courier', $params, $result );
			wp_send_json_success( [
				'message'    => $result['mesaj']    ?? __( 'Kurye siparişi alındı.', 'wc-ptt-kargo' ),
				'siparis_id' => $result['siparis_id'] ?? '',
				'http_code'  => $result['http_code']  ?? null,
			] );
		}

		wp_send_json_error( [
			'message' => $result['mesaj']    ?? __( 'Kurye siparişi başarısız.', 'wc-ptt-kargo' ),
			'raw'     => $result['raw']      ?? '',
			'request' => $result['request']  ?? '',
		], 502 );
	}

	/**
	 * Verilen bir sipariş için PTT'nin GETDROPPOINTINFO cevabını döner — ajax_takip'in
	 * ek olarak çağırdığı zenginleştirme isteği.
	 */
	public function ajax_drop_point(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$barkod   = isset( $_POST['barkod'] ) ? preg_replace( '/\D/', '', (string) $_POST['barkod'] ) : '';

		if ( $barkod === '' && $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order ) $barkod = (string) $order->get_meta( Orders::META_BARKOD );
		}
		if ( $barkod === '' ) wp_send_json_error( [ 'message' => __( 'Barkod gerekli.', 'wc-ptt-kargo' ) ], 400 );

		$result = $this->client->get_drop_point_info( $barkod );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( [
			'message' => $result['mesaj']    ?? __( 'Drop point bilgisi alınamadı.', 'wc-ptt-kargo' ),
			'raw'     => $result['raw']      ?? '',
			'http_code' => $result['http_code'] ?? null,
		], 502 );
	}

	public function ajax_takip(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'wc-ptt-kargo' ) ], 403 );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'wc-ptt-kargo' ) ], 404 );

		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );

		if ( $barkod === '' && $ref === '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu siparişte takip edilebilecek barkod ya da referans yok.', 'wc-ptt-kargo' ) ], 400 );
		}

		$result        = [];
		$used_method   = '';
		$ref_fallback  = false;

		if ( $barkod !== '' ) {
			$result      = $this->client->takip_sorgula_barkod( $barkod );
			$used_method = 'gonderiSorgu';

			if ( empty( $result['success'] ) && $ref !== '' ) {
				$ref_result = $this->client->takip_sorgula_referans( $ref );
				if ( ! empty( $ref_result['success'] ) ) {
					$result       = $ref_result;
					$used_method  = 'gonderiSorgu_referansNo';
					$ref_fallback = true;
				}
			}
		} else {
			// Barkod yok, sadece referans var (eski INU verisi vb.).
			$result      = $this->client->takip_sorgula_referans( $ref );
			$used_method = 'gonderiSorgu_referansNo';
		}

		$result['used_method']  = $used_method;
		$result['ref_fallback'] = $ref_fallback;

		// Drop point info'yu da çek — sadece barkod varsa (referans no üzerinden çalışmıyor).
		$with_drop = (bool) apply_filters( 'wc_ptt_kargo_takip_with_drop_point', true, $order );
		if ( $with_drop && $barkod !== '' ) {
			$drop = $this->client->get_drop_point_info( $barkod );
			if ( ! empty( $drop['success'] ) ) {
				$result['drop_point'] = $drop;
			} else {
				$result['drop_point_error'] = $drop['mesaj'] ?? '';
			}
		}

		wp_send_json_success( $result );
	}
}
