# Changelog

Bu projedeki tüm önemli değişiklikler bu dosyada belgelenir.

Format [Keep a Changelog](https://keepachangelog.com/tr-TR/1.1.0/) standardına dayanır,
sürüm numaralandırması [Semantic Versioning](https://semver.org/lang/tr/) kurallarına uyar.

## [2.0.1] - 2026-05-04

### Düzeltildi
- `siparisIstekEkle2` (kurye çağırma) envelope'u WSDL ile birebir uyumlu hale getirildi:
  - `ekhizmetler` → `ek_hizmetler` (snake_case)
  - `gondericiBilgi` → `gondericibilgi` (lowercase wrapper)
  - 10 child element camelCase → snake_case
  - `randevuBaslangic` → `randevu_baslangic`
  - Field sırası WSDL alfabetik sequence'a göre
  - Cevap parse: `hataKodu/aciklama` → `sonucKodu/sonucAciklama`
- `kabulEkle2` ve `kabulEkleParcaliBarkod` envelope'larında `gondericibilgi` elementi
  XSD sequence pozisyonuna alındı (`en` ile `iadeAAdres` arasında). Axis2'nin sıkı
  sequence kontrolünden geçmesi için zorunluydu.
- `GondericiBilgi` tipine `gonderici_soyadi` ve `gonderici_sms` alanları eklendi
  (WSDL'de var, kod hiç göndermiyordu).
- Etiket bağlantısı "süresi dolmuş" hatası: `wp_nonce_url()` HTML-escape uyguladığı için
  `&` karakteri JSON üzerinden JS'ye geçince `&amp;` oluyor, `_wpnonce` parametresi
  kayboluyordu. `add_query_arg()` ile raw URL üretimine geçildi.
- WC sipariş düzenleme sayfasındaki metabox'ta "PTT Kargoya Gönder" butonu çalışmıyordu.
  Asset enqueue artık tüm admin sayfalarında JS yüklüyor (HPOS/legacy/farklı WC sürümleri
  arasında hook string varyasyonundan etkilenmemek için).

## [2.0.0] - 2026-04-29

### Eklendi
- WooCommerce HPOS uyumlu sipariş listesi + bulk action.
- `kabulEkle2` ile barkodlu gönderi oluşturma.
- `kabulEkleParcaliBarkod` ile çoklu paket gönderimi.
- `barkodVeriSil` / `referansVeriSil` ile gönderi iptali.
- `gonderiSorgu` / `gonderiSorgu_referansNo` ile takip.
- `getDropPointInfo` ile şu an bulunduğu PTT şubesi bilgisi.
- `siparisIstekEkle2` ile kurye çağırma.
- 80mm termal etiket çıktısı (Code128 SVG).
- Toplu etiket bastırma.
- Atomic barkod cursor (FOR UPDATE ile race-condition güvenliği).
- AES-256-CBC ile şifrelenmiş PTT şifre saklama (WordPress `AUTH_KEY` tabanlı).
- Sipariş bazında sigorta (Değerli Kargo) toggle'ı.
- Kapıda Ödeme (COD) WC payment method eşlemesi.
- Farklı iade adresi desteği.
- Eksik müşteri bilgisi yakalama + popup ile manuel doldurma.
- Custom log tablosu + 500 kayıt rotasyonu.
- Bağlantı testi (sentetik barkodla `gonderiSorgu`).
- Hata sonrası otomatik barkod reuse (META_PENDING_BARKOD).
