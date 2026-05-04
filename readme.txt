=== WC PTT Kargo ===
Tags: woocommerce, ptt, kargo, barkod, turkey, shipping
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce siparişlerini PTT Kargo SOAP API'si üzerinden işleyen, barkod üreten ve termal etiket basan eklenti.

== Açıklama ==

WC PTT Kargo, WooCommerce mağazalarınız için PTT Kargo entegrasyonunu sağlar. Admin panelinden sipariş listesi, "Kargoya İlet" butonu ile PTT'nin SOAP servisi (`kabulEkle2`) üzerinden barkodlu gönderi oluşturma, 80mm termal yazıcı için hazır etiket çıktısı.

Özellikler:

* WooCommerce HPOS uyumlu sipariş listesi.
* PTT SOAP API entegrasyonu (Test / Canlı ortam geçişi).
* Barkod aralık yönetimi + check digit otomatik hesaplama (PTT'nin 1,3,1,3,... algoritması).
* 80mm termal etiket çıktısı (ESC/POS uyumlu yazıcılar için).
* Eksik müşteri bilgisi algılayıp popup ile elle doldurma.
* Şifre AES-256 ile şifrelenmiş saklanır (WP `AUTH_KEY` tabanlı).
* Gönderi takip sorgusu (GonderiTakipV2).
* Opsiyonel ürün ID filtresi ile siparişleri kapsama alır.

== Kurulum ==

1. Eklentiyi `/wp-content/plugins/wc-ptt-kargo/` dizinine kopyalayın ya da admin panelden ZIP olarak yükleyin.
2. "WC PTT Kargo"yu aktifleştirin.
3. Sol menüde "PTT Kargo" → "Ayarlar" sayfasından:
   - Ortam (Test/Canlı)
   - PTT müşteri numarası ve şifresi
   - Barkod prefix'i ve aralığı
   - Gönderici bilgileri
   - (Opsiyonel) Kargoya dahil ürün ID'leri
4. Test ortamında bir sipariş oluşturup "Kargoya İlet" ile deneyin. Dönen barkodu `entegrasyon@ptt.gov.tr` adresine iletin. PTT onayından sonra Ortam'ı "Canlı"ya çevirin.

== Sık Sorulan Sorular ==

= Termal yazıcı driver'ı gerekli mi? =

Yazıcı işletim sisteminde kurulu olsun yeterli. Eklenti HTML+CSS ile 80mm etiket üretir, tarayıcının yazdır diyaloğu yazıcıyı seçer.

= Şifre nerede saklanıyor? =

`wp_options` tablosunda `wc_ptt_kargo_settings.sifre_enc` alanında AES-256-CBC ile şifrelenmiş olarak. Anahtar WP'nin `AUTH_KEY`'inden türetilir.

= Barkod aralığı bittiğinde ne olur? =

"Kargoya İlet" butonu hata döner, ayarlar sayfasından yeni aralık tanımlamanız gerekir.

== Changelog ==

= 2.0.0 =
* İlk topluluk sürümü: WooCommerce HPOS uyumlu PTT Kargo entegrasyonu — `kabulEkle2` ile barkodlu gönderi oluşturma, `barkodVeriSil`/`referansVeriSil` ile iptal, `GONDERISORGU2`/`GONDERISORGU_REFERANSNO`/`getDropPointInfo` ile takip, `kabulEkleParcaliBarkod` ile çoklu paket, `siparisIstekEkle2` ile kurye çağırma, 80mm termal etiket baskısı.
