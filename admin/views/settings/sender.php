<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Gönderici Bilgileri', 'wc-ptt-kargo' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT\'ye gidecek gönderi kaydında ve etiketin alt bölümünde görünecek bilgiler. Tüm alanları doldurmak gönderi sırasında oluşabilecek "eksik bilgi" hatalarını engeller.', 'wc-ptt-kargo' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="gonderici_ad"><?php esc_html_e( 'Gönderici Adı', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_ad" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_ad]" value="<?php echo esc_attr( $opts['gonderici_ad'] ); ?>" class="large-text" data-preview-key="gonderici_ad">
			<p class="description"><?php esc_html_e( 'Mağazanızın / kurumun adı (kabulEkle2 için). Etiket başlığı boşsa burası başlık olarak da kullanılır.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_soyad"><?php esc_html_e( 'Gönderici Soyadı', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_soyad" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_soyad]" value="<?php echo esc_attr( $opts['gonderici_soyad'] ?? '' ); ?>" class="regular-text">
			<p class="description">
				<?php esc_html_e( 'Yalnızca PTT Kurye Çağırma servisi (siparisIstekEkle2) için ad+soyad ayrı bekler. Boş bırakılırsa "Gönderici Adı"ndaki son kelime soyad olarak ayrılır.', 'wc-ptt-kargo' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_adres"><?php esc_html_e( 'Adres', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_adres" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_adres]" value="<?php echo esc_attr( $opts['gonderici_adres'] ); ?>" class="large-text" data-preview-key="gonderici_adres">
			<p class="description"><?php esc_html_e( 'Mahalle, sokak, bina/daire bilgisi.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_il"><?php esc_html_e( 'İl', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="gonderici_il" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_il]" value="<?php echo esc_attr( $opts['gonderici_il'] ); ?>" class="regular-text" data-preview-key="gonderici_il"></td>
	</tr>
	<tr>
		<th><label for="gonderici_ilce"><?php esc_html_e( 'İlçe', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="gonderici_ilce" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_ilce]" value="<?php echo esc_attr( $opts['gonderici_ilce'] ); ?>" class="regular-text" data-preview-key="gonderici_ilce"></td>
	</tr>
	<tr>
		<th><label for="gonderici_posta"><?php esc_html_e( 'Posta Kodu', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="gonderici_posta" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_posta]" value="<?php echo esc_attr( $opts['gonderici_posta'] ); ?>" class="small-text" data-preview-key="gonderici_posta"></td>
	</tr>
	<tr>
		<th><label for="gonderici_tel"><?php esc_html_e( 'Telefon', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_tel" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_tel]" value="<?php echo esc_attr( $opts['gonderici_tel'] ); ?>" class="regular-text" data-preview-key="gonderici_tel">
			<p class="description"><?php esc_html_e( 'PTT 10 hane bekler (5xxxxxxxxx). Başında 0 veya 90 olsa otomatik kırpılır.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_email"><?php esc_html_e( 'E-posta', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="email" id="gonderici_email" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_email]" value="<?php echo esc_attr( $opts['gonderici_email'] ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="posta_ceki_no"><?php esc_html_e( 'Posta Çeki Hesap No', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="posta_ceki_no" name="<?php echo esc_attr( $opt_key ); ?>[posta_ceki_no]" value="<?php echo esc_attr( $opts['posta_ceki_no'] ); ?>" class="regular-text" pattern="\d{0,8}" maxlength="8" placeholder="12345678">
			<p class="description">
				<?php esc_html_e( 'PTT işyerlerinden açtırdığınız PTT Bank hesap numarası (8 hane). PTT entegrasyon mailinde bildirilir.', 'wc-ptt-kargo' ); ?>
				<br>
				<?php esc_html_e( 'Kapıda ödemeli (COD) gönderiler için zorunludur — PTT envelope\'ında <code>rezerve1</code> alanı olarak iletilir.', 'wc-ptt-kargo' ); ?>
			</p>
		</td>
	</tr>
</table>

<h3 style="margin-top:32px;"><?php esc_html_e( 'İade Adresi', 'wc-ptt-kargo' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'Default\'ta gönderici alıcıya ulaşmadığında kargo bu adrese (Gönderici) iade edilir. Farklı bir iade adresi (örn. depo) gerekiyorsa aşağıyı doldur.', 'wc-ptt-kargo' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Farklı İade Adresi', 'wc-ptt-kargo' ); ?></th>
		<td>
			<label>
				<input type="checkbox" id="iade_adresi_farkli" name="<?php echo esc_attr( $opt_key ); ?>[iade_adresi_farkli]" value="1" <?php checked( ! empty( $opts['iade_adresi_farkli'] ) ); ?>>
				<?php esc_html_e( 'İade adresi gönderici adresinden farklı', 'wc-ptt-kargo' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'İşaretli değilse aşağıdaki alanlar PTT\'ye gönderilmez (default davranış: gönderici adresine iade).', 'wc-ptt-kargo' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="iade_ad"><?php esc_html_e( 'İade Alıcı Adı', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="iade_ad" name="<?php echo esc_attr( $opt_key ); ?>[iade_ad]" value="<?php echo esc_attr( $opts['iade_ad'] ?? '' ); ?>" class="large-text"></td>
	</tr>
	<tr>
		<th><label for="iade_adres"><?php esc_html_e( 'İade Adresi', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="iade_adres" name="<?php echo esc_attr( $opt_key ); ?>[iade_adres]" value="<?php echo esc_attr( $opts['iade_adres'] ?? '' ); ?>" class="large-text"></td>
	</tr>
	<tr>
		<th><label for="iade_il"><?php esc_html_e( 'İade İl', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="iade_il" name="<?php echo esc_attr( $opt_key ); ?>[iade_il]" value="<?php echo esc_attr( $opts['iade_il'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="iade_ilce"><?php esc_html_e( 'İade İlçe', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="text" id="iade_ilce" name="<?php echo esc_attr( $opt_key ); ?>[iade_ilce]" value="<?php echo esc_attr( $opts['iade_ilce'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="iade_tel"><?php esc_html_e( 'İade Telefon', 'wc-ptt-kargo' ); ?></label></th>
		<td>
			<input type="text" id="iade_tel" name="<?php echo esc_attr( $opt_key ); ?>[iade_tel]" value="<?php echo esc_attr( $opts['iade_tel'] ?? '' ); ?>" class="regular-text">
			<p class="description"><?php esc_html_e( 'PTT 10 hane (5xxxxxxxxx). Başında 0 veya 90 olsa otomatik kırpılır.', 'wc-ptt-kargo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="iade_email"><?php esc_html_e( 'İade E-posta', 'wc-ptt-kargo' ); ?></label></th>
		<td><input type="email" id="iade_email" name="<?php echo esc_attr( $opt_key ); ?>[iade_email]" value="<?php echo esc_attr( $opts['iade_email'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
</table>
