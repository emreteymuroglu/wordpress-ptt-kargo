<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array  $opts */
/** @var string $opt_key */

$cursor_row = get_option( \WC_PTT_Kargo\Barcode::CURSOR_OPTION );
?>
<h2><?php esc_html_e( 'Barkod Aralığı ve Referans', 'wc-ptt-kargo' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT size 13 haneli bir barkod aralığı tahsis eder. İlk 8 hane sabit prefix\'tir, sonraki 4 hane sıralı seri numarası, son hane otomatik check digit\'tir.', 'wc-ptt-kargo' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="barkod_prefix"><?php esc_html_e( 'Barkod Prefix (8 hane)', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="barkod_prefix" name="<?php echo esc_attr( $opt_key ); ?>[barkod_prefix]" value="<?php echo esc_attr( $opts['barkod_prefix'] ); ?>" class="regular-text" pattern="\d{8}" placeholder="27918802" data-preview-key="barkod_prefix">
			<p class="description"><?php esc_html_e( 'PTT\'nin entegrasyon yazısında belirtilen 8 haneli sabit prefix.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label><?php esc_html_e( 'Seri Numara Aralığı', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" name="<?php echo esc_attr( $opt_key ); ?>[barkod_range_start]" value="<?php echo esc_attr( $opts['barkod_range_start'] ); ?>" class="small-text" pattern="\d+" placeholder="0000">
			→
			<input type="text" name="<?php echo esc_attr( $opt_key ); ?>[barkod_range_end]" value="<?php echo esc_attr( $opts['barkod_range_end'] ); ?>" class="small-text" pattern="\d+" placeholder="9999">
			<p class="description">
				<?php esc_html_e( 'PTT\'nin tahsis ettiği başlangıç ve bitiş seri numaraları. Prefix + seri = 12 hane.', 'wc-ptt-kargo' ); ?>
				<?php if ( $cursor_row !== false ) : ?>
					<br><strong><?php esc_html_e( 'Sıradaki kullanılacak seri:', 'wc-ptt-kargo' ); ?></strong> <?php echo esc_html( $cursor_row ); ?>
					<?php
					$end       = (int) $opts['barkod_range_end'];
					$remaining = max( 0, $end - (int) $cursor_row + 1 );
					echo ' <em>(' . esc_html( sprintf( __( '%d adet kaldı', 'wc-ptt-kargo' ), $remaining ) ) . ')</em>';
					?>
				<?php endif; ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="referans_prefix"><?php esc_html_e( 'Müşteri Referans Öneki', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="referans_prefix" name="<?php echo esc_attr( $opt_key ); ?>[referans_prefix]" value="<?php echo esc_attr( $opts['referans_prefix'] ); ?>" class="regular-text" placeholder="SHOP-">
			<p class="description"><?php esc_html_e( 'Sipariş ID\'sinin önüne eklenecek prefix. Örn: SHOP- → PTT\'ye SHOP-12345 gider. Boş bırakılabilir.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <strong><?php esc_html_e( 'Örnek:', 'wc-ptt-kargo' ); ?></strong>
	<?php esc_html_e( 'Prefix "27918802" + seri "0001" → 12 hane "279188020001" + check digit (otomatik) → 13 hane "2791880200017".', 'wc-ptt-kargo' ); ?>
</div>
