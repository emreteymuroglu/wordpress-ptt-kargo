<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array  $opts */
/** @var string $opt_key */

$all_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [];

$selected_pids = $opts['urun_idler'] !== '' ? array_map( 'intval', array_filter( explode( ',', $opts['urun_idler'] ) ) ) : [];

// WC enhanced select / product search desteği için scriptleri enqueue et (settings sayfasında ekstra).
if ( function_exists( 'wp_enqueue_script' ) ) {
	wp_enqueue_script( 'wc-enhanced-select' );
	wp_enqueue_style( 'woocommerce_admin_styles' );
}
?>
<h2><?php esc_html_e( 'Sipariş Filtresi', 'wc-ptt-kargo' ); ?></h2>
<p class="description"><?php esc_html_e( 'Aşağıda ürün seçersen sadece o ürünleri içeren siparişler "Kargo Siparişleri" listesinde görünür. Boş bırakırsan tüm uygun durumdaki siparişler listelenir.', 'wc-ptt-kargo' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="urun_idler_picker"><?php esc_html_e( 'Kapsama Alınacak Ürünler', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<?php if ( wp_script_is( 'wc-enhanced-select', 'enqueued' ) ) : ?>
				<select id="urun_idler_picker"
					class="wc-product-search"
					multiple="multiple"
					style="width: 50%; min-width: 320px;"
					data-placeholder="<?php esc_attr_e( 'Ürün adına göre ara...', 'wc-ptt-kargo' ); ?>"
					data-action="woocommerce_json_search_products_and_variations">
					<?php
					if ( ! empty( $selected_pids ) && function_exists( 'wc_get_product' ) ) {
						foreach ( $selected_pids as $pid ) {
							$p = wc_get_product( $pid );
							if ( $p ) {
								echo '<option value="' . esc_attr( $pid ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
							}
						}
					}
					?>
				</select>
				<input type="hidden" id="urun_idler" name="<?php echo esc_attr( $opt_key ); ?>[urun_idler]" value="<?php echo esc_attr( $opts['urun_idler'] ); ?>">
				<p class="description"><?php esc_html_e( 'Ürün adı veya SKU ile arayıp seçin. Seçili ürünleri içeren siparişler kapsama alınır.', 'wc-ptt-kargo' ); ?></p>
				<script>
				(function($){
					$(function(){
						$('#urun_idler_picker').on('change', function(){
							var values = $(this).val() || [];
							$('#urun_idler').val(values.join(','));
						});
					});
				})(jQuery);
				</script>
			<?php else : ?>
				<input type="text" id="urun_idler" name="<?php echo esc_attr( $opt_key ); ?>[urun_idler]" value="<?php echo esc_attr( $opts['urun_idler'] ); ?>" class="regular-text" placeholder="17696, 17700, 17701">
				<p class="description"><?php esc_html_e( 'Virgülle ayrılmış ürün ID listesi. (Ürün arama widget\'ı için WooCommerce admin script\'i yüklenemedi.)', 'wc-ptt-kargo' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>

</table>

<h3><?php esc_html_e( 'Sipariş Durumları', 'wc-ptt-kargo' ); ?></h3>
<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Listelenecek Durumlar', 'wc-ptt-kargo' ); ?></th>
		<td>
			<?php
			$aktif = (array) $opts['sipariş_durumlari'];
			foreach ( $all_statuses as $slug => $label ) :
				$slug_clean = str_replace( 'wc-', '', $slug );
				?>
				<label style="display:inline-block; margin-right:12px; margin-bottom:6px;">
					<input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[sipariş_durumlari][]" value="<?php echo esc_attr( $slug_clean ); ?>" <?php checked( in_array( $slug_clean, $aktif, true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( '"Kargo Siparişleri" listesinde sadece seçili durumdaki siparişler gösterilir.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
</table>
