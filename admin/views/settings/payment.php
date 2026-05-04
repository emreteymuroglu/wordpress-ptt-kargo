<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array  $opts */
/** @var string $opt_key */

// WC'de tanımlı tüm payment gateway'leri çek (aktif olmasalar bile listele).
$gateways = [];
if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
	$all = WC()->payment_gateways->payment_gateways();
	foreach ( $all as $id => $gateway ) {
		// Sadece "real" gateway'ler (id'si olanlar)
		if ( ! is_object( $gateway ) || empty( $gateway->id ) ) continue;
		$gateways[ $gateway->id ] = [
			'title'   => $gateway->get_method_title(),
			'enabled' => ( method_exists( $gateway, 'is_available' ) && $gateway->is_available() ) || ( $gateway->enabled ?? '' ) === 'yes',
		];
	}
}

$selected = (array) ( $opts['cod_payment_methods'] ?? [] );
?>
<h2><?php esc_html_e( 'Kapıda Ödeme (COD) Eşleşmesi', 'wc-ptt-kargo' ); ?></h2>
<p class="description">
	<?php esc_html_e( "Burada işaretlediğin WooCommerce ödeme yöntemleri \"kapıda ödeme\" sayılır. Bu yöntemlerle tamamlanmış siparişler PTT'ye iletilirken:", 'wc-ptt-kargo' ); ?>
</p>
<ul style="list-style:disc; margin-left:24px; font-size:13px; color:#50575e;">
	<li><code>odemesekli</code> = <strong>UA</strong> (Ücreti Alıcıdan)</li>
	<li><code>odeme_sart_ucreti</code> = sipariş toplam tutarı</li>
	<li><code>ekhizmet</code> alanına aşağıdaki kod (varsayılan <strong>OS</strong>) eklenir</li>
</ul>
<p class="description">
	<?php esc_html_e( '⚠ COD aktifse "Gönderici → Posta Çeki Hesap No" alanı doldurulmuş olmalıdır; aksi halde PTT gönderiyi reddeder.', 'wc-ptt-kargo' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Kapıda Ödeme Yöntemleri', 'wc-ptt-kargo' ); ?></th>
		<td>
			<?php if ( empty( $gateways ) ) : ?>
				<p><em><?php esc_html_e( 'Hiç ödeme yöntemi tespit edilemedi. WooCommerce > Ayarlar > Ödemeler\'den en az bir yöntem etkinleştirin.', 'wc-ptt-kargo' ); ?></em></p>
			<?php else : ?>
				<fieldset>
					<?php foreach ( $gateways as $gw_id => $gw ) :
						$is_checked = in_array( $gw_id, $selected, true );
						?>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox"
								name="<?php echo esc_attr( $opt_key ); ?>[cod_payment_methods][]"
								value="<?php echo esc_attr( $gw_id ); ?>"
								<?php checked( $is_checked ); ?>>
							<strong><?php echo esc_html( $gw['title'] ); ?></strong>
							<code style="background:#f0f0f1; padding:1px 6px; border-radius:2px; font-size:11px;"><?php echo esc_html( $gw_id ); ?></code>
							<?php if ( ! $gw['enabled'] ) : ?>
								<small style="color:#646970;">(<?php esc_html_e( 'şu an pasif', 'wc-ptt-kargo' ); ?>)</small>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Tipik seçim: WooCommerce Cash on Delivery (cod). Sanal POS / kart ödemeleri NORMAL gönderim sayılır, işaretlenmemelidir.', 'wc-ptt-kargo' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label for="cod_extra_service_code"><?php esc_html_e( 'COD Ek Hizmet Kodu', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="cod_extra_service_code" name="<?php echo esc_attr( $opt_key ); ?>[cod_extra_service_code]" value="<?php echo esc_attr( $opts['cod_extra_service_code'] ?? 'OS' ); ?>" class="small-text" maxlength="10" pattern="[A-Za-z]+">
			<p class="description">
				<?php esc_html_e( "PTT'nin \"Ödeme Şartlı\" ek hizmet kodu — varsayılan: ", 'wc-ptt-kargo' ); ?>
				<code>OS</code>.
				<?php esc_html_e( 'PTT entegrasyon ekibinden farklı bir kod aldıysan değiştirebilirsin (örn. kombinasyon: DKUA).', 'wc-ptt-kargo' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Kod, "Gönderi Varsayılanları → Ek Hizmet Kodu" alanındaki sabit kodla otomatik birleştirilir (örn. DK + OS = DKOS).', 'wc-ptt-kargo' ); ?>
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <strong><?php esc_html_e( 'Hatırlatma:', 'wc-ptt-kargo' ); ?></strong>
	<?php esc_html_e( 'Kapıda ödemeli kargolar PTT tarafına \"Tahsilatlı\" olarak iletilir. Toplanan tutar PTT tarafından senin Posta Çeki hesabına aktarılır. Hesap doğrulaması için PTT şubene başvur.', 'wc-ptt-kargo' ); ?>
</div>

<h2 style="margin-top:32px;"><?php esc_html_e( 'Sigorta (Değerli Kargo)', 'wc-ptt-kargo' ); ?></h2>
<p class="description">
	<?php esc_html_e( "Sigorta her sipariş için \"Kargoya İlet\" popup'ında manuel olarak açılır/kapatılır. Açıldığında PTT envelope'ında:", 'wc-ptt-kargo' ); ?>
</p>
<ul style="list-style:disc; margin-left:24px; font-size:13px; color:#50575e;">
	<li><code>deger_ucreti</code> = popup'ta girilen tutar</li>
	<li><code>ekhizmet</code> alanına <strong>DK</strong> (varsayılan) eklenir, mevcutla birleştirilir (DKUA gibi)</li>
</ul>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="insurance_extra_service_code"><?php esc_html_e( 'Sigorta Ek Hizmet Kodu', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="insurance_extra_service_code" name="<?php echo esc_attr( $opt_key ); ?>[insurance_extra_service_code]" value="<?php echo esc_attr( $opts['insurance_extra_service_code'] ?? 'DK' ); ?>" class="small-text" maxlength="10" pattern="[A-Za-z]+">
			<p class="description">
				<?php esc_html_e( 'PTT\'nin Değerli Kargo kodu — varsayılan: ', 'wc-ptt-kargo' ); ?>
				<code>DK</code>.
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <?php esc_html_e( 'Hem kapıda ödeme hem değerli kargo birlikte aktifse PTT envelope\'ında ek hizmet kodları birleştirilir (örn. DKUA + OS = DKUAOS).', 'wc-ptt-kargo' ); ?>
</div>
