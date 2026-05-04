<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Gönderi Varsayılanları', 'wc-ptt-kargo' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT\'ye gönderilirken ağırlık/desi belirtilmek zorunda. Aşağıdaki kaynak ayarları auto-compute davranışını belirler; "Kargoya İlet" popup\'ında her sipariş için manuel override yapabilirsin.', 'wc-ptt-kargo' ); ?></p>

<h3><?php esc_html_e( 'Ağırlık / Desi Kaynağı', 'wc-ptt-kargo' ); ?></h3>
<table class="form-table" role="presentation">
	<tr>
		<th><label for="weight_source"><?php esc_html_e( 'Ağırlık Kaynağı', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<?php $ws = (string) ( $opts['weight_source'] ?? 'static' ); ?>
			<select id="weight_source" name="<?php echo esc_attr( $opt_key ); ?>[weight_source]">
				<option value="static" <?php selected( $ws, 'static' ); ?>><?php esc_html_e( 'Sabit varsayılan kullan', 'wc-ptt-kargo' ); ?></option>
				<option value="wc_product" <?php selected( $ws, 'wc_product' ); ?>><?php esc_html_e( 'WooCommerce ürün ağırlığından hesapla (Σ ağırlık × adet)', 'wc-ptt-kargo' ); ?></option>
				<option value="wc_product_fallback" <?php selected( $ws, 'wc_product_fallback' ); ?>><?php esc_html_e( 'Ürün ağırlığından hesapla (yoksa sabit varsayılana düş)', 'wc-ptt-kargo' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'WC ürün ağırlık birimi otomatik gram\'a çevrilir (g/kg/lbs/oz desteklenir). Aktif birim:', 'wc-ptt-kargo' ); ?>
				<code><?php echo esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ); ?></code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="varsayilan_agirlik"><?php esc_html_e( 'Sabit Ağırlık (gram)', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="number" id="varsayilan_agirlik" name="<?php echo esc_attr( $opt_key ); ?>[varsayilan_agirlik]" value="<?php echo esc_attr( $opts['varsayilan_agirlik'] ); ?>" min="1" class="small-text">
			<span class="description"><?php esc_html_e( 'gram', 'wc-ptt-kargo' ); ?></span>
			<p class="description"><?php esc_html_e( 'Yukarıda "Sabit varsayılan" seçiliyse her gönderi bu değerle gider. Fallback modunda da eşik altı için kullanılır.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="dimensions_source"><?php esc_html_e( 'Boyut Kaynağı', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<?php $ds = (string) ( $opts['dimensions_source'] ?? 'static' ); ?>
			<select id="dimensions_source" name="<?php echo esc_attr( $opt_key ); ?>[dimensions_source]">
				<option value="static" <?php selected( $ds, 'static' ); ?>><?php esc_html_e( 'Boyut gönderme (sadece desi)', 'wc-ptt-kargo' ); ?></option>
				<option value="wc_product" <?php selected( $ds, 'wc_product' ); ?>><?php esc_html_e( 'WooCommerce ürün boyutlarından hesapla (max boyut)', 'wc-ptt-kargo' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( '"WC ürün boyutlarından" seçilirse: en/boy/yükseklik için tüm sipariş kalemlerinin max\'ı alınır (tek paket varsayımı). Desi formülü en×boy×yükseklik/3000.', 'wc-ptt-kargo' ); ?>
				<br>
				<?php esc_html_e( 'Aktif WC boyut birimi:', 'wc-ptt-kargo' ); ?>
				<code><?php echo esc_html( get_option( 'woocommerce_dimension_unit', 'cm' ) ); ?></code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="varsayilan_desi"><?php esc_html_e( 'Sabit Desi', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="number" id="varsayilan_desi" name="<?php echo esc_attr( $opt_key ); ?>[varsayilan_desi]" value="<?php echo esc_attr( $opts['varsayilan_desi'] ); ?>" min="1" class="small-text">
			<p class="description"><?php esc_html_e( 'Boyut kaynağı "Boyut gönderme" iken kullanılır. Genelde 1 ile başlayın, paket hacmine göre güncellersiniz.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ekhizmet"><?php esc_html_e( 'Ek Hizmet Kodu', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="ekhizmet" name="<?php echo esc_attr( $opt_key ); ?>[ekhizmet]" value="<?php echo esc_attr( $opts['ekhizmet'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Boş bırakılabilir', 'wc-ptt-kargo' ); ?>" pattern="[A-Za-z]*" maxlength="40">
			<p class="description">
				<?php esc_html_e( 'PTT ek hizmet kodları büyük harflerle alfabetik (örn. DK = Değerli Kargo). Birden fazla kod birleştirilebilir (DKUA gibi). Kapıda ödeme aktifse OS otomatik eklenir; burada manuel yazmana gerek yok.', 'wc-ptt-kargo' ); ?>
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <?php esc_html_e( 'WC ürün ağırlığı tanımlı değilse "Ürün → Genel → Ağırlık" alanını doldurun. Ağırlık birimi WooCommerce > Ayarlar > Ürünler > Ölçümler\'den değiştirilebilir.', 'wc-ptt-kargo' ); ?>
</div>
