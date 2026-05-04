<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	public const OPTION_KEY = 'wc_ptt_kargo_settings';

	private const ENC_SALT = 'wc-ptt-kargo|';

	public static function defaults(): array {
		return [
			'environment'        => 'test',
			'musteri_id'         => '',
			'sifre_enc'          => '',
			'barkod_prefix'      => '',
			'barkod_range_start' => '0000',
			'barkod_range_end'   => '9999',
			'referans_prefix'    => '',
			'urun_idler'         => '',
			'gonderici_ad'       => '',
			'gonderici_adres'    => '',
			'gonderici_il'       => '',
			'gonderici_ilce'     => '',
			'gonderici_posta'    => '',
			'gonderici_tel'      => '',
			'gonderici_email'    => '',
			// siparisIstekEkle2 (kurye çağırma) ad+soyad ayrı bekler. Boş bırakılırsa
			// gonderici_ad'tan son kelime soyad olarak ayrılır.
			'gonderici_soyad'    => '',
			// PTT'nin entegrasyon mailinde verdiği posta çeki hesap numarası — kapıda ödemeli kargolarda
			// PTT envelope'ında <xsd:rezerve1> olarak gönderilir.
			'posta_ceki_no'      => '',

			// İade adresi farklı mı? Default off → PTT alıcıya ulaşamadığında gönderici adresine iade eder.
			// Açıksa aşağıdaki iade_* alanları kabulEkle2 envelope'ında iadeAAdres/iadeAliciAdi/... olarak gider.
			'iade_adresi_farkli' => 0,
			'iade_ad'            => '',
			'iade_adres'         => '',
			'iade_il'            => '',
			'iade_ilce'          => '',
			'iade_tel'           => '',
			'iade_email'         => '',
			'varsayilan_agirlik' => 500,
			'varsayilan_desi'    => 1,
			'ekhizmet'           => '',

			// Ağırlık / desi kaynağı: 'static' (sadece varsayilan), 'wc_product' (line item product weight × qty),
			// 'wc_product_fallback' (varsa product weight, yoksa varsayilan).
			'weight_source'      => 'static',
			// 'static' veya 'wc_product' (her ürün boyutlarının max'ını alır → tek paket varsayımı).
			'dimensions_source'  => 'static',

			// Kapıda ödeme: hangi WC payment method id'leri "Ücreti Alıcıdan" sayılır?
			// Boş array = COD logic devre dışı.
			'cod_payment_methods'    => [],
			// Kapıda ödeme aktifse ekhizmet alanına eklenecek kod (PTT default 'OS').
			'cod_extra_service_code' => 'OS',

			// Sigorta aktif olduğunda (popup'tan manuel toggle) ekhizmet'e eklenecek kod (PTT default 'DK').
			'insurance_extra_service_code' => 'DK',

			'sipariş_durumlari'  => [ 'processing', 'on-hold' ],

			// Etiket görünümü
			'label_logo_url'        => '',
			'label_header_title'    => '',
			'label_header_subtitle' => '',

			// Etikette hangi blokların gösterileceği (1/0)
			'label_show_order'    => 1,
			'label_show_recipient'=> 1,
			'label_show_products' => 1,
			'label_show_barcode'  => 1,
			'label_show_sender'   => 1,

			];
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
	}

	public function register_setting(): void {
		register_setting(
			'wc_ptt_kargo_settings_group',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	public function all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	/**
	 * Tek bir alanı doğrudan günceller (programatik kullanım).
	 */
	public function update( string $key, $value ): void {
		$current         = $this->all();
		$current[ $key ] = $value;
		update_option( self::OPTION_KEY, $current );
	}

	public function update_many( array $values ): void {
		$current = array_merge( $this->all(), $values );
		update_option( self::OPTION_KEY, $current );
	}

	public function get( string $key, $default = null ) {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Tab-aware sanitize: mevcut ayarları base alır, sadece submit edilen tab'ın alanlarını günceller.
	 * Aktif tab'ı `__tab` hidden field'ından okur. Checkbox grupları sadece o tab'a aitse reset edilir.
	 */
	public function sanitize( $input ): array {
		$clean = $this->all(); // mevcut ayarları koru
		$input = is_array( $input ) ? $input : [];

		$tab = isset( $input['__tab'] ) ? sanitize_key( (string) $input['__tab'] ) : '';

		// Hangi tab hangi alanları düzenleyebilir?
		$tab_fields = [
			'connection' => [ 'environment', 'musteri_id', 'sifre', 'sifre_enc' ],
			'barcode'    => [ 'barkod_prefix', 'barkod_range_start', 'barkod_range_end', 'referans_prefix' ],
			'sender'     => [ 'gonderici_ad', 'gonderici_soyad', 'gonderici_adres', 'gonderici_il', 'gonderici_ilce', 'gonderici_posta', 'gonderici_tel', 'gonderici_email', 'posta_ceki_no',
			                  'iade_adresi_farkli', 'iade_ad', 'iade_adres', 'iade_il', 'iade_ilce', 'iade_tel', 'iade_email' ],
			'label'      => [ 'label_logo_url', 'label_header_title', 'label_header_subtitle',
			                  'label_show_order', 'label_show_recipient', 'label_show_products', 'label_show_barcode', 'label_show_sender' ],
			'products'   => [ 'urun_idler', 'sipariş_durumlari' ],
			'defaults'   => [ 'varsayilan_agirlik', 'varsayilan_desi', 'ekhizmet', 'weight_source', 'dimensions_source' ],
			'payment'    => [ 'cod_payment_methods', 'cod_extra_service_code', 'insurance_extra_service_code' ],
		];

		// Bilinmeyen tab → tüm alanları işle (geriye dönük uyumluluk)
		$active_keys = isset( $tab_fields[ $tab ] ) ? $tab_fields[ $tab ] : array_merge( ...array_values( $tab_fields ) );

		$apply = function ( string $key ) use ( &$clean, $input, $active_keys ) {
			if ( ! in_array( $key, $active_keys, true ) ) return false;
			return true;
		};

		if ( $apply( 'environment' ) ) {
			$clean['environment'] = in_array( $input['environment'] ?? '', [ 'test', 'prod' ], true ) ? $input['environment'] : 'test';
		}
		if ( $apply( 'musteri_id' ) ) {
			$clean['musteri_id'] = preg_replace( '/\D/', '', $input['musteri_id'] ?? '' );
		}
		if ( $apply( 'sifre' ) ) {
			$raw_sifre = $input['sifre'] ?? '';
			if ( is_string( $raw_sifre ) && $raw_sifre !== '' ) {
				$clean['sifre_enc'] = self::encrypt( $raw_sifre );
			}
		}

		if ( $apply( 'barkod_prefix' ) )      $clean['barkod_prefix']      = preg_replace( '/\D/', '', $input['barkod_prefix'] ?? '' );
		if ( $apply( 'barkod_range_start' ) ) $clean['barkod_range_start'] = preg_replace( '/\D/', '', $input['barkod_range_start'] ?? '0000' );
		if ( $apply( 'barkod_range_end' ) )   $clean['barkod_range_end']   = preg_replace( '/\D/', '', $input['barkod_range_end'] ?? '9999' );
		if ( $apply( 'referans_prefix' ) )    $clean['referans_prefix']    = sanitize_text_field( $input['referans_prefix'] ?? '' );

		// Barkod validation — sadece "barcode" tab'ında. PTT 12 hane (prefix + seri) + 1 check digit dağıtır.
		// prefix + range_end mutlaka 12 hane olmalı; range_start ≤ range_end ve aynı uzunlukta.
		// Geçersizse kullanıcının değişikliklerini reddet, eski değeri koru ve admin notice göster.
		if ( $tab === 'barcode' ) {
			$bp = (string) $clean['barkod_prefix'];
			$bs = (string) $clean['barkod_range_start'];
			$be = (string) $clean['barkod_range_end'];

			$errors = [];
			if ( strlen( $bp . $be ) !== 12 ) {
				$errors[] = sprintf(
					/* translators: 1: prefix uzunluğu, 2: bitiş uzunluğu, 3: toplam */
					__( 'Barkod aralığı geçersiz: prefix (%1$d hane) + bitiş (%2$d hane) toplamı 12 olmalı, şu an %3$d. PTT size 12-haneli aralık tahsis eder (13. hane otomatik check digit).', 'wc-ptt-kargo' ),
					strlen( $bp ), strlen( $be ), strlen( $bp . $be )
				);
			}
			if ( strlen( $bs ) !== strlen( $be ) ) {
				$errors[] = __( 'Barkod aralığı başlangıç ve bitiş aynı hane sayısında olmalı.', 'wc-ptt-kargo' );
			}
			if ( strlen( $bs ) === strlen( $be ) && $bs !== '' && (int) $bs > (int) $be ) {
				$errors[] = __( 'Barkod aralığı başlangıcı bitişten büyük olamaz.', 'wc-ptt-kargo' );
			}

			if ( ! empty( $errors ) ) {
				$old = $this->all();
				$clean['barkod_prefix']      = $old['barkod_prefix'];
				$clean['barkod_range_start'] = $old['barkod_range_start'];
				$clean['barkod_range_end']   = $old['barkod_range_end'];
				foreach ( $errors as $i => $msg ) {
					add_settings_error( self::OPTION_KEY, 'barkod_invalid_' . $i, $msg, 'error' );
				}
			}
		}

		if ( $apply( 'gonderici_ad' ) )    $clean['gonderici_ad']    = sanitize_text_field( $input['gonderici_ad'] ?? '' );
		if ( $apply( 'gonderici_soyad' ) ) $clean['gonderici_soyad'] = sanitize_text_field( $input['gonderici_soyad'] ?? '' );
		if ( $apply( 'gonderici_adres' ) ) $clean['gonderici_adres'] = sanitize_text_field( $input['gonderici_adres'] ?? '' );
		if ( $apply( 'gonderici_il' ) )    $clean['gonderici_il']    = sanitize_text_field( $input['gonderici_il'] ?? '' );
		if ( $apply( 'gonderici_ilce' ) )  $clean['gonderici_ilce']  = sanitize_text_field( $input['gonderici_ilce'] ?? '' );
		if ( $apply( 'gonderici_posta' ) ) $clean['gonderici_posta'] = preg_replace( '/\D/', '', $input['gonderici_posta'] ?? '' );
		if ( $apply( 'gonderici_tel' ) )   $clean['gonderici_tel']   = preg_replace( '/\D/', '', $input['gonderici_tel'] ?? '' );
		if ( $apply( 'gonderici_email' ) ) $clean['gonderici_email'] = sanitize_email( $input['gonderici_email'] ?? '' );
		// Posta çeki hesap numarası: PTT 8 hane bekler (rezerve1).
		if ( $apply( 'posta_ceki_no' ) ) {
			$digits = preg_replace( '/\D/', '', (string) ( $input['posta_ceki_no'] ?? '' ) );
			$clean['posta_ceki_no'] = strlen( $digits ) > 8 ? substr( $digits, 0, 8 ) : $digits;
		}

		// İade adresi alanları: checkbox + 6 text alanı. Sender tab dışında dokunulmaz.
		if ( $apply( 'iade_adresi_farkli' ) ) $clean['iade_adresi_farkli'] = ! empty( $input['iade_adresi_farkli'] ) ? 1 : 0;
		if ( $apply( 'iade_ad' ) )            $clean['iade_ad']            = sanitize_text_field( $input['iade_ad'] ?? '' );
		if ( $apply( 'iade_adres' ) )         $clean['iade_adres']         = sanitize_text_field( $input['iade_adres'] ?? '' );
		if ( $apply( 'iade_il' ) )            $clean['iade_il']            = sanitize_text_field( $input['iade_il'] ?? '' );
		if ( $apply( 'iade_ilce' ) )          $clean['iade_ilce']          = sanitize_text_field( $input['iade_ilce'] ?? '' );
		if ( $apply( 'iade_tel' ) ) {
			$digits = preg_replace( '/\D/', '', (string) ( $input['iade_tel'] ?? '' ) );
			if ( strlen( $digits ) > 10 && strpos( $digits, '90' ) === 0 ) $digits = substr( $digits, 2 );
			elseif ( strlen( $digits ) === 11 && $digits[0] === '0' )       $digits = substr( $digits, 1 );
			$clean['iade_tel'] = $digits;
		}
		if ( $apply( 'iade_email' ) )         $clean['iade_email']         = sanitize_email( $input['iade_email'] ?? '' );

		if ( $apply( 'label_logo_url' ) )        $clean['label_logo_url']        = esc_url_raw( $input['label_logo_url'] ?? '' );
		if ( $apply( 'label_header_title' ) )    $clean['label_header_title']    = sanitize_text_field( $input['label_header_title'] ?? '' );
		if ( $apply( 'label_header_subtitle' ) ) $clean['label_header_subtitle'] = sanitize_text_field( $input['label_header_subtitle'] ?? '' );

		// label_show_* checkbox'ları: sadece label tab'ında reset edilir (yoksa unchecked = 0).
		foreach ( [ 'label_show_order', 'label_show_recipient', 'label_show_products', 'label_show_barcode', 'label_show_sender' ] as $tk ) {
			if ( $apply( $tk ) ) {
				$clean[ $tk ] = ! empty( $input[ $tk ] ) ? 1 : 0;
			}
		}

		if ( $apply( 'urun_idler' ) ) $clean['urun_idler'] = $this->clean_id_list( $input['urun_idler'] ?? '' );
		if ( $apply( 'sipariş_durumlari' ) ) {
			$durumlar = $input['sipariş_durumlari'] ?? [];
			$clean['sipariş_durumlari'] = array_values( array_filter( array_map( 'sanitize_key', (array) $durumlar ) ) );
		}

		if ( $apply( 'varsayilan_agirlik' ) ) $clean['varsayilan_agirlik'] = max( 1, (int) ( $input['varsayilan_agirlik'] ?? 500 ) );
		if ( $apply( 'varsayilan_desi' ) )    $clean['varsayilan_desi']    = max( 1, (int) ( $input['varsayilan_desi'] ?? 1 ) );
		if ( $apply( 'ekhizmet' ) )           $clean['ekhizmet']           = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['ekhizmet'] ?? '' ) ) );

		if ( $apply( 'weight_source' ) ) {
			$clean['weight_source'] = in_array( $input['weight_source'] ?? 'static', [ 'static', 'wc_product', 'wc_product_fallback' ], true )
				? $input['weight_source']
				: 'static';
		}
		if ( $apply( 'dimensions_source' ) ) {
			$clean['dimensions_source'] = in_array( $input['dimensions_source'] ?? 'static', [ 'static', 'wc_product' ], true )
				? $input['dimensions_source']
				: 'static';
		}

		if ( $apply( 'cod_payment_methods' ) ) {
			$raw = $input['cod_payment_methods'] ?? [];
			$clean['cod_payment_methods'] = array_values( array_filter( array_map( 'sanitize_key', (array) $raw ) ) );
		}
		if ( $apply( 'cod_extra_service_code' ) ) {
			// Ek hizmet kodları PTT'de her zaman büyük harf alfabetik (örn. OS, DK, UA, DKUA).
			$clean['cod_extra_service_code'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['cod_extra_service_code'] ?? 'OS' ) ) );
			if ( $clean['cod_extra_service_code'] === '' ) $clean['cod_extra_service_code'] = 'OS';
		}

		if ( $apply( 'insurance_extra_service_code' ) ) {
			$clean['insurance_extra_service_code'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['insurance_extra_service_code'] ?? 'DK' ) ) );
			if ( $clean['insurance_extra_service_code'] === '' ) $clean['insurance_extra_service_code'] = 'DK';
		}

		return $clean;
	}

	private function clean_id_list( $raw ): string {
		$parts = preg_split( '/[\s,;]+/', (string) $raw );
		$ids   = [];
		foreach ( $parts as $p ) {
			$p = preg_replace( '/\D/', '', $p );
			if ( $p !== '' ) {
				$ids[] = $p;
			}
		}
		return implode( ',', array_unique( $ids ) );
	}

	public function product_ids(): array {
		$raw = (string) $this->get( 'urun_idler', '' );
		if ( $raw === '' ) return [];
		return array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	public function cod_payment_methods(): array {
		$raw = $this->get( 'cod_payment_methods', [] );
		return is_array( $raw ) ? array_values( array_filter( array_map( 'sanitize_key', $raw ) ) ) : [];
	}

	/**
	 * siparisIstekEkle2 metodunda Gönderici Bilgisi içinde "Ad" ve "Soyad" ayrı bekleniyor.
	 * Kullanıcı `gonderici_soyad` doldurmuşsa direkt o; aksi halde `gonderici_ad`'ı son boşluktan
	 * ayırıp (Ali Veli Yılmaz → "Ali Veli", "Yılmaz") yarısını döner.
	 *
	 * @return array{ad:string,soyad:string}
	 */
	public function gonderici_ad_soyad(): array {
		$ad    = trim( (string) $this->get( 'gonderici_ad', '' ) );
		$soyad = trim( (string) $this->get( 'gonderici_soyad', '' ) );

		if ( $soyad !== '' ) {
			return [ 'ad' => $ad, 'soyad' => $soyad ];
		}
		if ( $ad === '' ) {
			return [ 'ad' => '', 'soyad' => '' ];
		}
		// Son boşluğa kadar ad, sonrası soyad. Tek kelimelik gönderici için soyad ad ile aynı olur (PTT min 1 char).
		$pos = strrpos( $ad, ' ' );
		if ( $pos === false ) {
			return [ 'ad' => $ad, 'soyad' => $ad ];
		}
		return [
			'ad'    => trim( substr( $ad, 0, $pos ) ),
			'soyad' => trim( substr( $ad, $pos + 1 ) ),
		];
	}

	public function sifre_plain(): string {
		$enc = (string) $this->get( 'sifre_enc', '' );
		return $enc === '' ? '' : (string) self::decrypt( $enc );
	}

	public function endpoint_kabul(): string {
		return $this->get( 'environment' ) === 'prod'
			? 'https://pttws.ptt.gov.tr/PttVeriYukleme/services/Sorgu'
			: 'https://pttws.ptt.gov.tr/PttVeriYuklemeTest/services/Sorgu';
	}

	public function endpoint_takip(): string {
		return $this->get( 'environment' ) === 'prod'
			? 'https://pttws.ptt.gov.tr/GonderiTakipV2/services/Sorgu'
			: 'https://pttws.ptt.gov.tr/GonderiTakipV2Test/services/Sorgu';
	}

	private static function key(): string {
		$k = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wc-ptt-kargo-fallback-key';
		return hash( 'sha256', self::ENC_SALT . $k, true );
	}

	public static function encrypt( string $plain ): string {
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	public static function decrypt( string $enc ): string {
		$raw = base64_decode( $enc, true );
		if ( $raw === false || strlen( $raw ) < 17 ) return '';
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		return $plain === false ? '' : $plain;
	}
}
