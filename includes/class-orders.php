<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce siparişlerinin kargoya dahil ürün ID'lerine göre filtrelenmesi.
 * HPOS uyumlu; wc_get_orders() kullanır.
 */
final class Orders {
	public const META_BARKOD    = '_wc_ptt_kargo_barkod';
	public const META_REF       = '_wc_ptt_kargo_ref';
	public const META_STATUS    = '_wc_ptt_kargo_status';
	public const META_SENT_AT   = '_wc_ptt_kargo_sent_at';
	public const META_TAKIP_URL = '_wc_ptt_kargo_takip_url';
	public const META_PTT_LOG   = '_wc_ptt_kargo_last_response';
	public const META_PTT_RAW   = '_wc_ptt_kargo_last_raw';
	public const META_PTT_REQ   = '_wc_ptt_kargo_last_request';
	// İptal sırasında PTT'nin barkodVeriSil/referansVeriSil çağrılarına geçilir.
	public const META_DOSYA_ADI = '_wc_ptt_kargo_dosya_adi';
	// Hata sonrası retry'da reuse edilecek tüketilmiş barkod (PTT cevap dönmediyse veya hata aldıysa).
	// Yeni gönderim attempt'inde bu meta varsa barcode->next() çağrılmaz, mevcut barkod tekrar kullanılır.
	public const META_PENDING_BARKOD = '_wc_ptt_kargo_pending_barkod';
	// Çok parçalı (parcaliBarkod) gönderim sonrası tüm barkod listesi (JSON array).
	public const META_PARCA_BARKODLAR = '_wc_ptt_kargo_parca_barkodlar';
	public const META_PARCA_ADET      = '_wc_ptt_kargo_parca_adet';
	public const META_IRSALIYE_NO     = '_wc_ptt_kargo_irsaliye_no';

	public const STATUS_PENDING  = 'pending';
	public const STATUS_SENT     = 'sent';
	public const STATUS_ERROR    = 'error';
	public const STATUS_CANCELED = 'canceled';

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Kargoya aday siparişleri listeler — ayarlarda tanımlı ürün ID'lerini içeren,
	 * seçili durumdaki (processing/on-hold vs) siparişleri döner.
	 *
	 * @return \WC_Order[]
	 */
	public function eligible_orders( int $limit = 100, string $show = 'pending' ): array {
		$durumlar = (array) $this->settings->get( 'sipariş_durumlari', [ 'processing' ] );
		if ( empty( $durumlar ) ) $durumlar = [ 'processing' ];

		$args = [
			'limit'   => $limit,
			'status'  => $durumlar,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		];

		if ( $show === 'pending' ) {
			$args['meta_query'] = [
				'relation' => 'OR',
				[ 'key' => self::META_STATUS, 'compare' => 'NOT EXISTS' ],
				[ 'key' => self::META_STATUS, 'value' => self::STATUS_SENT, 'compare' => '!=' ],
			];
		} elseif ( $show === 'sent' ) {
			$args['meta_query'] = [
				[ 'key' => self::META_STATUS, 'value' => self::STATUS_SENT ],
			];
		}

		$args = apply_filters( 'wc_ptt_kargo_eligible_orders_args', $args, $show, $limit );

		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) return [];

		$filtered = array_values( array_filter( $orders, fn( $order ) => $this->is_eligible( $order ) ) );
		return apply_filters( 'wc_ptt_kargo_eligible_orders', $filtered, $show, $args );
	}

	/**
	 * Ürün filtresi varsa sadece o ürünleri içerenler uygun; filtre boşsa tüm uygun durumdaki siparişler.
	 */
	public function is_eligible( \WC_Order $order ): bool {
		$product_ids = $this->settings->product_ids();
		$result = empty( $product_ids ) ? true : $this->order_contains_products( $order, $product_ids );
		return (bool) apply_filters( 'wc_ptt_kargo_is_eligible', $result, $order, $product_ids );
	}

	public function order_contains_products( \WC_Order $order, array $product_ids ): bool {
		if ( empty( $product_ids ) ) return false;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) continue;
			$pid     = (int) $item->get_product_id();
			$var_id  = (int) $item->get_variation_id();
			if ( in_array( $pid, $product_ids, true ) || ( $var_id > 0 && in_array( $var_id, $product_ids, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sipariştaki alıcı bilgilerini PTT alan adlarına map eder.
	 * Eksik alanlar 'missing' anahtarı altında listelenir (popup için).
	 */
	public function to_ptt_payload( \WC_Order $order ): array {
		$ad       = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( $ad === '' ) {
			$ad = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$adres_1  = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
		$adres_2  = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
		$adres    = trim( $adres_1 . ( $adres_2 ? ' ' . $adres_2 : '' ) );

		$ilce     = $order->get_shipping_city() ?: $order->get_billing_city();
		$il_kod   = $order->get_shipping_state() ?: $order->get_billing_state();
		$il       = $this->resolve_state_name( $il_kod );
		$posta    = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
		$tel      = $order->get_billing_phone();
		$email    = $order->get_billing_email();

		$tel_clean = preg_replace( '/\D/', '', (string) $tel );
		if ( strlen( $tel_clean ) > 10 && strpos( $tel_clean, '90' ) === 0 ) {
			$tel_clean = substr( $tel_clean, 2 );
		} elseif ( strlen( $tel_clean ) === 11 && $tel_clean[0] === '0' ) {
			$tel_clean = substr( $tel_clean, 1 );
		}

		$agirlik    = $this->resolve_weight( $order );
		$dimensions = $this->resolve_dimensions( $order );
		$desi       = $this->resolve_desi( $order, $dimensions );

		$missing = [];
		if ( mb_strlen( $ad ) < 5 )     $missing['aliciAdi']   = 'Alıcı ad soyad';
		if ( mb_strlen( $adres ) < 5 )  $missing['aAdres']     = 'Adres';
		if ( $il === '' )               $missing['aliciIlAdi'] = 'İl';
		if ( $ilce === '' )             $missing['aliciIlceAdi'] = 'İlçe';
		if ( strlen( $tel_clean ) !== 10 ) $missing['aliciSms'] = 'Telefon (10 hane)';

		$fields = [
			'aliciAdi'     => $ad,
			'aAdres'       => $adres,
			'aliciIlAdi'   => $il,
			'aliciIlceAdi' => $ilce,
			'aliciSms'     => $tel_clean,
			'aliciEmail'   => $email,
			'agirlik'      => $agirlik,
			'desi'         => $desi,
			'ekhizmet'     => strtoupper( (string) $this->settings->get( 'ekhizmet', '' ) ),
		];

		if ( $dimensions['en'] > 0 )         $fields['en']        = $dimensions['en'];
		if ( $dimensions['boy'] > 0 )        $fields['boy']       = $dimensions['boy'];
		if ( $dimensions['yukseklik'] > 0 )  $fields['yukseklik'] = $dimensions['yukseklik'];

		// Posta çeki numarası: kapıda ödemeli kargolar için PTT envelope'ında <xsd:rezerve1>.
		$posta_ceki = (string) $this->settings->get( 'posta_ceki_no', '' );
		if ( $posta_ceki !== '' ) {
			$fields['rezerve1'] = $posta_ceki;
		}

		$this->apply_cod_logic( $order, $fields );

		// Sigorta (Değerli Kargo) otomatik eklenmez — popup'tan manuel toggle ile deger_ucreti + DK eklenir.

		$this->apply_iade_logic( $fields );

		$payload = [
			'fields'   => $fields,
			'missing'  => $missing,
			'order_id' => $order->get_id(),
			'order_no' => $order->get_order_number(),
			'posta'    => $posta,
		];
		return (array) apply_filters( 'wc_ptt_kargo_ptt_payload', $payload, $order );
	}

	/**
	 * Sipariş ağırlığını ayarlanan kaynağa göre PTT'nin beklediği gram cinsinden döner.
	 * - static: settings'deki varsayilan_agirlik
	 * - wc_product: tüm line item ürünlerinin (weight × qty) toplamı; ürün ağırlığı yoksa 0 sayar
	 * - wc_product_fallback: wc_product ile aynı; toplam 0 ise varsayilan'a düşer
	 */
	public function resolve_weight( \WC_Order $order ): int {
		$source = (string) $this->settings->get( 'weight_source', 'static' );
		$static = max( 1, (int) $this->settings->get( 'varsayilan_agirlik', 500 ) );

		if ( $source === 'static' ) {
			return (int) apply_filters( 'wc_ptt_kargo_resolved_weight', $static, $order, $source );
		}

		$total_g = 0;
		$wc_unit = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_weight_unit', 'kg' ) : 'kg';
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) continue;
			$product = $item->get_product();
			if ( ! $product ) continue;
			$w = (float) $product->get_weight();
			if ( $w <= 0 ) continue;
			$qty = max( 1, (int) $item->get_quantity() );
			$total_g += self::weight_to_grams( $w, $wc_unit ) * $qty;
		}

		$result = (int) round( $total_g );

		if ( $result <= 0 && $source === 'wc_product_fallback' ) {
			$result = $static;
		}
		if ( $result <= 0 ) {
			// Hatalı 0 göndermek yerine static'e düş — PTT 0 ağırlığı reddedebilir.
			$result = $static;
		}

		return (int) apply_filters( 'wc_ptt_kargo_resolved_weight', $result, $order, $source );
	}

	/**
	 * Sipariş kutu boyutlarını cm cinsinden ['en'=>, 'boy'=>, 'yukseklik'=>] döner.
	 * - static: 0,0,0 (envelope'a eklenmez)
	 * - wc_product: tek paket varsayımıyla her boyut için tüm ürünlerin max'ı alınır.
	 *   Çoklu ürün durumunda kullanıcının popup'tan override etmesi beklenir.
	 */
	public function resolve_dimensions( \WC_Order $order ): array {
		$source = (string) $this->settings->get( 'dimensions_source', 'static' );

		if ( $source !== 'wc_product' ) {
			return apply_filters( 'wc_ptt_kargo_resolved_dimensions', [ 'en' => 0, 'boy' => 0, 'yukseklik' => 0 ], $order, $source );
		}

		$max_l = 0; $max_w = 0; $max_h = 0;
		$wc_unit = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_dimension_unit', 'cm' ) : 'cm';
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) continue;
			$product = $item->get_product();
			if ( ! $product ) continue;
			$l = self::length_to_cm( (float) $product->get_length(), $wc_unit );
			$w = self::length_to_cm( (float) $product->get_width(),  $wc_unit );
			$h = self::length_to_cm( (float) $product->get_height(), $wc_unit );
			if ( $l > $max_l ) $max_l = $l;
			if ( $w > $max_w ) $max_w = $w;
			if ( $h > $max_h ) $max_h = $h;
		}

		// PTT boy / en / yukseklik hep numerik 1-4 hane → tamsayıya yuvarla.
		$dims = [
			'en'        => (int) round( $max_w ),
			'boy'       => (int) round( $max_l ),
			'yukseklik' => (int) round( $max_h ),
		];
		return (array) apply_filters( 'wc_ptt_kargo_resolved_dimensions', $dims, $order, $source );
	}

	/**
	 * Desi: dimensions varsa en×boy×yükseklik/3000 formülünden hesaplanır, aksi halde varsayilan_desi.
	 */
	public function resolve_desi( \WC_Order $order, array $dimensions ): int {
		$static = max( 1, (int) $this->settings->get( 'varsayilan_desi', 1 ) );
		$source = (string) $this->settings->get( 'dimensions_source', 'static' );

		$desi = $static;
		if ( $source === 'wc_product'
			&& $dimensions['en'] > 0 && $dimensions['boy'] > 0 && $dimensions['yukseklik'] > 0
		) {
			$calc = ( $dimensions['en'] * $dimensions['boy'] * $dimensions['yukseklik'] ) / 3000;
			$desi = max( 1, (int) ceil( $calc ) );
		}
		return (int) apply_filters( 'wc_ptt_kargo_resolved_desi', $desi, $order, $dimensions, $source );
	}

	/**
	 * Sipariş'in WC payment method'u kapıda ödeme listesindeyse PTT alanlarını set eder:
	 *  - odemesekli = 'UA' (Ücreti Alıcıdan)
	 *  - odeme_sart_ucreti = sipariş toplamı (12,2 ondalık)
	 *  - ekhizmet'e cod_extra_service_code (default 'OS') eklenir, mevcutla birleştirilir.
	 *
	 * `wc_ptt_kargo_is_cod_order` filtresi ile davranış override edilebilir.
	 */
	private function apply_cod_logic( \WC_Order $order, array &$fields ): void {
		$cod_methods = $this->settings->cod_payment_methods();
		$is_cod = ! empty( $cod_methods ) && in_array( (string) $order->get_payment_method(), $cod_methods, true );
		$is_cod = (bool) apply_filters( 'wc_ptt_kargo_is_cod_order', $is_cod, $order, $cod_methods );

		if ( ! $is_cod ) return;

		// PTT odeme_sart_ucreti: en fazla 12,2 ondalık. WC total float olarak gelir.
		$total = (float) $order->get_total();
		if ( $total <= 0 ) {
			// Sıfır toplamlı sipariş PTT tarafında reddeder; defansif olarak skip et.
			return;
		}

		$fields['odemesekli']        = 'UA';
		$fields['odeme_sart_ucreti'] = number_format( $total, 2, '.', '' );

		$cod_eh = strtoupper( (string) $this->settings->get( 'cod_extra_service_code', 'OS' ) );
		if ( $cod_eh !== '' ) {
			$fields['ekhizmet'] = self::merge_extra_service_codes( (string) ( $fields['ekhizmet'] ?? '' ), $cod_eh );
		}
	}

	/**
	 * Ek hizmet kodlarını PTT'nin beklediği formatta birleştirir: 2 harflik kodlara böl,
	 * eklenecek kodlar yoksa ekle, alfabetik sırala (PTT doc örneği "DKUA" alfabetik).
	 */
	public static function merge_extra_service_codes( string $existing, string $add ): string {
		$existing = strtoupper( preg_replace( '/[^A-Za-z]/', '', $existing ) );
		$add      = strtoupper( preg_replace( '/[^A-Za-z]/', '', $add ) );
		if ( $add === '' ) return $existing;

		// 2 harflik kodlara böl (PTT kodları hep 2 harf: DK, OS, UA, KÖ vb.)
		$codes = [];
		foreach ( str_split( $existing, 2 ) as $c ) {
			if ( strlen( $c ) === 2 ) $codes[] = $c;
		}
		foreach ( str_split( $add, 2 ) as $c ) {
			if ( strlen( $c ) === 2 ) $codes[] = $c;
		}
		$codes = array_unique( $codes );
		sort( $codes ); // alfabetik
		return implode( '', $codes );
	}

	/**
	 * Settings'de "İade adresi farklı" işaretliyse PTT envelope'ına iade* alanları enjekte eder.
	 * Eksik alanlar gönderilmez (PTT default'a, yani gönderici adresine düşer).
	 */
	private function apply_iade_logic( array &$fields ): void {
		if ( empty( $this->settings->get( 'iade_adresi_farkli', 0 ) ) ) return;

		$ad    = trim( (string) $this->settings->get( 'iade_ad', '' ) );
		$adres = trim( (string) $this->settings->get( 'iade_adres', '' ) );
		$il    = trim( (string) $this->settings->get( 'iade_il', '' ) );
		$ilce  = trim( (string) $this->settings->get( 'iade_ilce', '' ) );
		$tel   = trim( (string) $this->settings->get( 'iade_tel', '' ) );
		$email = trim( (string) $this->settings->get( 'iade_email', '' ) );

		if ( $ad !== '' )    $fields['iadeAliciAdi']     = $ad;
		if ( $adres !== '' ) $fields['iadeAAdres']       = $adres;
		if ( $il !== '' )    $fields['iadeAliciIlAdi']   = $il;
		if ( $ilce !== '' )  $fields['iadeAliciIlceAdi'] = $ilce;
		if ( $tel !== '' )   $fields['iadeAliciTel']     = $tel;
		if ( $email !== '' ) $fields['iadeAliciEmail']   = $email;
	}

	private static function weight_to_grams( float $value, string $unit ): float {
		switch ( strtolower( $unit ) ) {
			case 'g':   return $value;
			case 'kg':  return $value * 1000.0;
			case 'lbs': return $value * 453.59237;
			case 'oz':  return $value * 28.349523125;
		}
		return $value; // bilinmeyen unit'i dokunmadan dön
	}

	private static function length_to_cm( float $value, string $unit ): float {
		switch ( strtolower( $unit ) ) {
			case 'mm': return $value / 10.0;
			case 'cm': return $value;
			case 'm':  return $value * 100.0;
			case 'in': return $value * 2.54;
			case 'yd': return $value * 91.44;
		}
		return $value;
	}

	private function resolve_state_name( string $state_code ): string {
		if ( $state_code === '' ) return '';
		if ( function_exists( 'WC' ) ) {
			$states = WC()->countries ? WC()->countries->get_states( 'TR' ) : [];
			if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
				return (string) $states[ $state_code ];
			}
		}
		return $state_code;
	}

	public function mark_sent( \WC_Order $order, string $barkod, string $ref, string $takip_url = '', string $raw = '', string $request = '', string $mesaj = '', string $dosya_adi = '' ): void {
		$order->update_meta_data( self::META_BARKOD, $barkod );
		$order->update_meta_data( self::META_REF, $ref );
		$order->update_meta_data( self::META_STATUS, self::STATUS_SENT );
		$order->update_meta_data( self::META_SENT_AT, current_time( 'mysql' ) );
		if ( $takip_url !== '' ) $order->update_meta_data( self::META_TAKIP_URL, $takip_url );
		if ( $raw !== '' )       $order->update_meta_data( self::META_PTT_RAW, $raw );
		if ( $request !== '' )   $order->update_meta_data( self::META_PTT_REQ, $request );
		if ( $mesaj !== '' )     $order->update_meta_data( self::META_PTT_LOG, $mesaj );
		if ( $dosya_adi !== '' ) $order->update_meta_data( self::META_DOSYA_ADI, $dosya_adi );
		// Başarılı gönderim → PENDING (retry için tutulan tüketilmiş barkod) temizlenir.
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: barkod 2: referans no 3: PTT açıklama */
				__( 'PTT Kargo: Gönderi oluşturuldu. Barkod: %1$s, Referans: %2$s. PTT cevabı: %3$s', 'wc-ptt-kargo' ),
				$barkod,
				$ref,
				$mesaj !== '' ? $mesaj : '-'
			)
		);
	}

	/**
	 * Hata sonrası tüketilmiş barkodun retry'da reuse edilmesi için META_PENDING_BARKOD'a kaydedilir.
	 * PTT'nin barkod aralığı kıt kaynak — başarısız gönderim her seferinde yeni barkod yakmasın.
	 *
	 * @param string $pending_barkod  PTT'ye gönderilmiş ama başarılı olmamış barkod (varsa)
	 */
	public function mark_error( \WC_Order $order, string $mesaj, string $raw = '', string $request = '', string $pending_barkod = '' ): void {
		$order->update_meta_data( self::META_STATUS, self::STATUS_ERROR );
		$order->update_meta_data( self::META_PTT_LOG, $mesaj );
		if ( $raw !== '' )     $order->update_meta_data( self::META_PTT_RAW, $raw );
		if ( $request !== '' ) $order->update_meta_data( self::META_PTT_REQ, $request );
		if ( $pending_barkod !== '' ) {
			$order->update_meta_data( self::META_PENDING_BARKOD, $pending_barkod );
		}
		$order->save();
		$order->add_order_note( __( 'PTT Kargo hatası: ', 'wc-ptt-kargo' ) . $mesaj );
	}

	/**
	 * Çok parçalı gönderim (kabulEkleParcaliBarkod) sonrası ek meta'ları kaydeder.
	 * mark_sent'ten hemen sonra çağrılır.
	 */
	public function mark_parca( \WC_Order $order, array $barkodlar, string $irsaliye_no = '' ): void {
		if ( count( $barkodlar ) > 1 ) {
			$order->update_meta_data( self::META_PARCA_BARKODLAR, wp_json_encode( array_values( array_unique( $barkodlar ) ) ) );
			$order->update_meta_data( self::META_PARCA_ADET, count( $barkodlar ) );
		}
		if ( $irsaliye_no !== '' ) {
			$order->update_meta_data( self::META_IRSALIYE_NO, $irsaliye_no );
		}
		$order->save();
	}

	public function get_pending_barkod( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_PENDING_BARKOD );
	}

	public function clear_pending_barkod( \WC_Order $order ): void {
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->save();
	}

	/**
	 * Sipariş PTT'den (kabulü yapılmadan) iptal edildiğinde çağrılır.
	 * Eski barkod/ref/takip URL meta'ları temizlenir → kullanıcı sipariş'i tekrar
	 * gönderdiğinde yeni bir barkod tüketilir, çakışma olmaz.
	 * Status STATUS_CANCELED'a çekilir; UI bunu "İptal edildi" badge'i ile gösterir.
	 */
	public function mark_canceled( \WC_Order $order, string $eski_barkod, string $mesaj = '', string $raw = '', string $request = '' ): void {
		$order->update_meta_data( self::META_STATUS, self::STATUS_CANCELED );

		// Yeniden gönderim için temiz başlangıç — barkod/ref temizlenir.
		$order->delete_meta_data( self::META_BARKOD );
		$order->delete_meta_data( self::META_REF );
		$order->delete_meta_data( self::META_TAKIP_URL );
		$order->delete_meta_data( self::META_SENT_AT );
		$order->delete_meta_data( self::META_DOSYA_ADI );
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->delete_meta_data( self::META_PARCA_BARKODLAR );
		$order->delete_meta_data( self::META_PARCA_ADET );
		$order->delete_meta_data( self::META_IRSALIYE_NO );

		if ( $mesaj !== '' )   $order->update_meta_data( self::META_PTT_LOG, $mesaj );
		if ( $raw !== '' )     $order->update_meta_data( self::META_PTT_RAW, $raw );
		if ( $request !== '' ) $order->update_meta_data( self::META_PTT_REQ, $request );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: eski barkod 2: PTT açıklama */
				__( 'PTT Kargo: Gönderi iptal edildi (eski barkod: %1$s). PTT cevabı: %2$s', 'wc-ptt-kargo' ),
				$eski_barkod !== '' ? $eski_barkod : '-',
				$mesaj !== '' ? $mesaj : '-'
			)
		);
	}

	public function build_ref( \WC_Order $order ): string {
		$prefix = (string) $this->settings->get( 'referans_prefix', '' );
		return $prefix . $order->get_id();
	}
}
