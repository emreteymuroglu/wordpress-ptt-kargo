<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Barcode {
	public const CURSOR_OPTION = 'wc_ptt_kargo_barcode_cursor';

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Sıradaki 13 haneli barkodu (12 hane + check digit) atomik şekilde alır.
	 * Aralık bitmişse veya prefix+seri 12 haneye eşit değilse null döner — yanlış
	 * konfigürasyon sessizce yanlış aralıkta barkod yakmasın diye.
	 */
	public function next(): ?string {
		global $wpdb;

		$prefix    = (string) $this->settings->get( 'barkod_prefix', '' );
		$start     = (int) $this->settings->get( 'barkod_range_start', '0000' );
		$end       = (int) $this->settings->get( 'barkod_range_end', '9999' );
		$range_end = (string) $this->settings->get( 'barkod_range_end', '9999' );
		$pad       = strlen( $range_end );

		// Erken doğrulama: prefix + range_end uzunluğu 12 değilse barkod üretme.
		// (Settings sanitize'da da yakalanıyor; burası defansif yedek.)
		if ( strlen( $prefix ) + $pad !== 12 || ! ctype_digit( $prefix ) || ! ctype_digit( $range_end ) ) {
			return null;
		}

		$wpdb->query( 'START TRANSACTION' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
				self::CURSOR_OPTION
			)
		);

		if ( ! $row ) {
			// on_activation pre-init etmiş olmalı; race olduysa burada yine de defansif insert.
			$current  = $start;
			$inserted = $wpdb->insert(
				$wpdb->options,
				[
					'option_name'  => self::CURSOR_OPTION,
					'option_value' => (string) $current,
					'autoload'     => 'no',
				],
				[ '%s', '%s', '%s' ]
			);
			if ( $inserted === false ) {
				// Başka bir process aynı anda insert etmiş — rollback et, çağrı yeniden denenebilir.
				$wpdb->query( 'ROLLBACK' );
				return null;
			}
		} else {
			$current = (int) $row->option_value;
			if ( $current < $start ) {
				$current = $start;
			}
		}

		if ( $current > $end ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$twelve = $prefix . str_pad( (string) $current, $pad, '0', STR_PAD_LEFT );
		if ( strlen( $twelve ) !== 12 || ! ctype_digit( $twelve ) ) {
			// Bu noktaya gelmemeli (erken doğrulama yapıldı) — ama defansif fail.
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => (string) ( $current + 1 ) ],
			[ 'option_name'  => self::CURSOR_OPTION ],
			[ '%s' ],
			[ '%s' ]
		);
		$wpdb->query( 'COMMIT' );

		$barkod = $twelve . self::check_digit( $twelve );

		$filtered = apply_filters( 'wc_ptt_kargo_barkod', $barkod, $current, $prefix );
		return is_string( $filtered ) && $filtered !== '' ? $filtered : null;
	}

	/**
	 * 12 haneli barkodun check digit'ini döner.
	 * Her basamak 1,3,1,3,... çarpanlarıyla çarpılır. Toplamı 10'un üst katına tamamlayan rakam check digit'tir.
	 */
	public static function check_digit( string $twelve ): string {
		if ( ! preg_match( '/^\d{12}$/', $twelve ) ) {
			return '0';
		}
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$d       = (int) $twelve[ $i ];
			$weight  = ( $i % 2 === 0 ) ? 1 : 3;
			$sum    += $d * $weight;
		}
		$mod = $sum % 10;
		return (string) ( $mod === 0 ? 0 : 10 - $mod );
	}

	/**
	 * Code128B SVG barkodu üretir. İstenen width = toplam piksel genişliği (modül bazında ölçeklenir).
	 */
	public static function svg( string $value, int $height = 80, int $module = 2 ): string {
		$patterns = self::code128_patterns();
		$chars    = str_split( $value );

		// Code128B start = 104, Code128C varsa daha kısa ama basitlik için B.
		$codes = [ 104 ];
		foreach ( $chars as $c ) {
			$ord = ord( $c );
			if ( $ord < 32 || $ord > 126 ) continue;
			$codes[] = $ord - 32;
		}

		$sum = $codes[0];
		for ( $i = 1; $i < count( $codes ); $i++ ) {
			$sum += $codes[ $i ] * $i;
		}
		$codes[] = $sum % 103;
		$codes[] = 106; // stop

		$bars = '';
		foreach ( $codes as $code ) {
			$bars .= $patterns[ $code ];
		}
		$bars .= '11'; // final bar

		$width = strlen( $bars ) * $module;
		$svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" shape-rendering="crispEdges">';
		$x     = 0;
		for ( $i = 0; $i < strlen( $bars ); $i++ ) {
			$w = $module;
			if ( $bars[ $i ] === '1' ) {
				$svg .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000"/>';
			}
			$x += $w;
		}
		$svg .= '</svg>';
		return $svg;
	}

	private static function code128_patterns(): array {
		return [
			'11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
			'10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
			'11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
			'10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
			'11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
			'11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
			'11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
			'10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
			'11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
			'10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
			'11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
			'11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
			'11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
			'10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
			'10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
			'11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
			'10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
			'10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
			'11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
			'10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
			'10111101110', '11101011110', '11110101110',
			'11010000100', '11010010000', '11010011100', '11000111010',
		];
	}
}
