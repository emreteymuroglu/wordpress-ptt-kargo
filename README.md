# WC PTT Kargo

> WooCommerce siparişlerini PTT Kargo'nun SOAP servisleri üzerinden işleyen,
> barkod üreten ve 80mm termal etiket basan ücretsiz WordPress eklentisi.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)

Türkiye'de WordPress + WooCommerce kullanan e-ticaret siteleri için PTT Kargo entegrasyonu
sağlayan ücretsiz bir alternatiftir. Mevcut ücretli çözümlerden ya da elle barkod girmekten
kurtarmayı amaçlar.

## ✨ Özellikler

- **WooCommerce HPOS uyumlu** sipariş listesi + bulk action
- **`kabulEkle2`** ile tek paket barkodlu gönderi oluşturma
- **`kabulEkleParcaliBarkod`** ile çoklu paket gönderimi (tek siparişte N parça)
- **`barkodVeriSil` / `referansVeriSil`** ile PTT henüz kabul etmediyse gönderi iptali
- **`gonderiSorgu` / `gonderiSorgu_referansNo`** ile barkod ya da referans no üzerinden takip
- **`getDropPointInfo`** ile gönderinin şu an bulunduğu PTT şubesi bilgisi
- **`siparisIstekEkle2`** ile kurye çağırma (PTT görevlisinin adresinden alma)
- **80mm termal yazıcı** için hazır etiket (Code128 SVG, 13 haneli barkod + alıcı/gönderici)
- **Toplu etiket** bastırma — tek HTML, sayfalar arası page-break
- **Sigorta (Değerli Kargo)** her sipariş için manuel toggle (DK ek hizmet kodu)
- **Kapıda Ödeme (COD)** WC ödeme yöntemleriyle eşleştirme (UA + OS otomatik)
- **Farklı iade adresi** opsiyonu
- **Eksik müşteri bilgisi** yakalama + popup ile manuel doldurma
- **AES-256-CBC** şifrelenmiş PTT şifre saklama (WordPress `AUTH_KEY` tabanlı)
- **Atomic barkod cursor** — `FOR UPDATE` ile race-condition güvenliği
- **Hata sonrası retry** — başarısız denemede tüketilmiş barkod yeniden kullanılır, yeni barkod yakılmaz
- **Custom log tablosu** + 500 kayıt rotasyonu (her SOAP isteği request/response ile birlikte)
- **Bağlantı testi** — kaydetmeden önce sentetik bir barkodla credential doğrulaması
- **Test/Canlı ortam** geçişi tek tıkla

## 📋 Gereksinimler

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+
- PTT ile entegrasyon sözleşmesi (müşteri numarası, şifre, barkod aralığı)

PTT entegrasyon süreci ve gereken belgeler için yerel PTT Başmüdürlüğünüze başvurun.

## 🚀 Kurulum

1. [Releases](../../releases) sayfasından son sürümün ZIP'ini indir.
2. WordPress Admin → **Eklentiler → Yeni Ekle → Eklenti Yükle** yolundan ZIP'i yükle.
3. Eklentiyi etkinleştir.
4. Sol menüde **PTT Kargo → Ayarlar** sayfasını aç.

## ⚙️ Yapılandırma

Ayarlar 7 sekmeden oluşuyor.

### PTT Bağlantı
- **Ortam**: önce *Test*, PTT entegrasyon ekibinin onayından sonra *Canlı*.
- **Müşteri Numarası**: PTT'nin entegrasyon mailinde verdiği numerik ID.
- **Şifre**: aynı mailde verilen şifre. AES-256-CBC ile şifrelenip saklanır.
- **Bağlantıyı Test Et** butonuyla credential'ları kaydetmeden doğrulayabilirsin.

### Barkod
- **Prefix**: PTT'nin tahsis ettiği 8 haneli sabit ön ek (örn. `27918802`).
- **Seri Numara Aralığı**: tahsis edilen başlangıç–bitiş (örn. `0000` – `9999`).
- **Müşteri Referans Öneki**: sipariş ID'sinin önüne eklenecek prefix (opsiyonel).

> 13. hane check digit'tir, otomatik hesaplanır (PTT'nin 1,3,1,3,… algoritması).

### Gönderici
Tüm gönderici bilgileri burada — etikette ve PTT envelope'unda kullanılır.
- **Posta Çeki Hesap No**: PTT işyerlerinden açtığın PTT Bank hesabının 8 haneli numarası.
  Kapıda ödemeli kargolarda zorunlu.
- **Farklı İade Adresi**: kapatılırsa iade gönderici adresine, açılırsa belirttiğin
  adrese gönderilir.

### Etiket
80mm termal etiketin görünümünü kontrol eder.
- Logo, başlık, alt başlık.
- Hangi blokların görüneceği (sipariş no, alıcı, ürünler, barkod, gönderici).
- Sağdaki canlı önizleme form değiştikçe yenilenir.

### Ürün & Filtreler
- **Kapsama alınacak ürünler**: bu ürünleri içeren siparişler "Kargo Siparişleri"
  listesinde görünür. Boş bırakılırsa tüm siparişler kapsam içine alınır.
- **Sipariş durumları**: hangi WC durumundaki siparişlerin listeleneceği.

### Gönderi Varsayılanları
- **Ağırlık kaynağı**: `Sabit varsayılan`, `WC ürün ağırlığı`, ya da `WC ürün ağırlığı + fallback`.
- **Boyut kaynağı**: `Boyut gönderme` ya da `WC ürün boyutu`.
- **Sabit ağırlık** (gram), **sabit desi**.
- **Ek hizmet kodu**: PTT'nin alfabetik 2-harflik kodları (DK = Değerli Kargo,
  UA = Ücreti Alıcıdan, vb.). Birleştirme örneği: `DKUA`.

### Ödeme
- **Kapıda Ödeme yöntemleri**: hangi WC payment method ID'lerinin "kapıda ödeme"
  sayılacağı. İşaretlenen yöntemlerle yapılan siparişler PTT'ye iletilirken
  `odemesekli=UA`, `odeme_sart_ucreti=<sipariş toplamı>`, `ekhizmet`'e `OS` eklenir.
- **Sigorta ek hizmet kodu**: varsayılan `DK`. Sigorta her sipariş için popup'tan
  manuel açılır.

## 📦 Kullanım

### Tek sipariş — "Kargoya İlet" akışı

1. Sol menü: **PTT Kargo → Kargo Siparişleri**.
2. Listede sipariş satırında **Kargoya İlet** butonu.
3. Açılan popup'ta:
   - Müşteri bilgileri görünür; eksik alan varsa kırmızı kenarlıkla işaretlenir.
   - Kargo detayları placeholder'larla gelir (auto-compute); ihtiyaç varsa
     ağırlık/desi/boyut elle override edilebilir.
   - Kapıda Ödeme aktifse mavi bilgi kutusu görünür.
   - Sigorta toggle'ı isteğe bağlı açılır, tutar girilir.
   - Çoklu paket istenirse parça adedi 1'den büyük yapılır + irsaliye no girilebilir.
4. **Onayla ve Gönder** → PTT'ye SOAP isteği gider, başarılıysa yeni sekmede etiket açılır.

### Sipariş düzenleme sayfasından
WC sipariş detayında sağ panelde "PTT Kargo" metabox'ı vardır:
- Henüz gönderilmediyse "PTT Kargoya Gönder" butonu (aynı popup'ı açar).
- Gönderildiyse barkod, takip linki, "Etiket Yazdır", "Takip", "İptal Et".

### Toplu işlemler

WC siparişler listesinde checkbox ile birden çok sipariş seçip:
- **PTT Kargo: Gönder** → her sipariş için ayrı `kabulEkle2` çağrısı.
- **PTT Kargo: Toplu Etiket Bas** → tek HTML'de N etiket, sayfa atlamalı.

### İptal
PTT henüz fiziksel kabulü yapmadıysa (kurye gelmediyse) iptal edilebilir.
"İptal Et" butonu → `barkodVeriSil` → başarısız olursa `referansVeriSil` fallback.
İptal sonrası eski barkod meta'ları temizlenir; sipariş tekrar gönderilirse
yeni bir barkod tüketilir.

### Takip
"Takip" butonu modal açar:
- `gonderiSorgu` ile barkod hareketleri (kabul, transit, dağıtım, teslim).
- `getDropPointInfo` ile şu an bulunduğu PTT şubesi (ad, adres, telefon, çalışma
  saatleri, harita linki).

### Kurye çağırma
Sol menü: **PTT Kargo → Kurye Çağır**. PTT görevlisinin adresinden gönderileri
toplaması için sipariş geçer (`siparisIstekEkle2`).

## 🔧 Geliştirici Notları

### Filter ve action hook'ları

```php
// Envelope alanları PTT'ye gitmeden önce
apply_filters( 'wc_ptt_kargo_kabul_fields', $fields );

// Ham SOAP body üretildikten sonra
apply_filters( 'wc_ptt_kargo_soap_request_body', $body, $operation, $context );

// HTTP timeout (varsayılan 30 sn)
apply_filters( 'wc_ptt_kargo_http_timeout', 30, $operation );

// SSL doğrulaması (varsayılan true)
apply_filters( 'wc_ptt_kargo_sslverify', true, $operation );

// Üretilen barkod
apply_filters( 'wc_ptt_kargo_barkod', $barkod, $cursor, $prefix );

// Sipariş'in COD sayılıp sayılmayacağı
apply_filters( 'wc_ptt_kargo_is_cod_order', $is_cod, $order, $cod_methods );

// Auto-compute ağırlık / boyut / desi
apply_filters( 'wc_ptt_kargo_resolved_weight', $grams, $order, $source );
apply_filters( 'wc_ptt_kargo_resolved_dimensions', $dims, $order, $source );
apply_filters( 'wc_ptt_kargo_resolved_desi', $desi, $order, $dims, $source );

// Etiket render'ı
apply_filters( 'wc_ptt_kargo_label_html', $html, $order, $barkod );
apply_filters( 'wc_ptt_kargo_label_header', $header_data, $order );
apply_filters( 'wc_ptt_kargo_label_products', $items, $order );

// Gönderim sonrası
do_action( 'wc_ptt_kargo_after_send', $order, $barkod, $result );
do_action( 'wc_ptt_kargo_after_error', $order, $message, $result );
do_action( 'wc_ptt_kargo_after_cancel', $order, $old_barkod, $result );
do_action( 'wc_ptt_kargo_after_courier', $params, $result );
```

### Sipariş meta anahtarları

| Meta key | Açıklama |
|---|---|
| `_wc_ptt_kargo_barkod` | 13 haneli PTT barkodu |
| `_wc_ptt_kargo_ref` | Müşteri referans numarası |
| `_wc_ptt_kargo_status` | `pending` / `sent` / `error` / `canceled` |
| `_wc_ptt_kargo_takip_url` | PTT'nin döndürdüğü takip linki |
| `_wc_ptt_kargo_dosya_adi` | İptal için kullanılan dosya adı |
| `_wc_ptt_kargo_pending_barkod` | Hata sonrası retry için saklanan barkod |
| `_wc_ptt_kargo_parca_barkodlar` | Çoklu paket gönderimde tüm parça barkodları (JSON) |

### Loglar
**PTT Kargo → Loglar** sayfasında her SOAP isteğinin tam request/response
çıktısı saklanır. Şifre alanları otomatik maskelenir (`<sifre>***</sifre>`).
500 kayıt sınırı, eskiler otomatik silinir.

## 🐛 Bilinen sınırlamalar

- **Test ortamı izolasyonu**: PTT'nin test ortamında `PttVeriYukleme` (kabul) ve
  `GonderiTakipV2` (takip) sistemleri ayrı veritabanlarında çalışıyor — test ortamında
  kabul edilen barkod, takip sorgusunda "kayıt bulunamadı" döner. Canlıda sorun yok.
- **COD için PTT Bank hesabı zorunlu**: Kapıda ödeme ile gönderim yapacaksan PTT
  işyerinden PTT Bank posta çeki hesabı açtırman gerekir. Tahsil edilen tutar bu
  hesaba aktarılır.
- **Çoklu il/ilçe**: Plugin sadece tek il + tek ilçe alanı kullanıyor. PTT'nin doc'ta
  belirttiği `aIlKodu2/3`, `aIlceKodu2/3` alternatif adresler desteklenmiyor.
- **PTT etiket servisi (`etiketGetir`) kullanılmıyor**: Kendi termal etiketimizi
  üretiyoruz çünkü PTT'nin döndüğü PDF formatı 80mm termal yazıcılarda sorunlu
  render oluyor.

## 🤝 Katkı

Issue'lar ve PR'lar memnuniyetle karşılanır. Büyük değişiklikler için önce issue
açıp tartışalım.

Geliştirme ortamı:
- WordPress + WooCommerce kurulu test sitesi
- PTT'den alınmış test ortam credential'ları (canlı ile test etmeyin — barkodlar gerçek tüketilir)

## 📝 Lisans

[GPL-2.0-or-later](LICENSE) — WordPress eklentileri için standart lisans.

## 🙏 Teşekkürler

- PTT Genel Müdürlüğü Müşteri Entegrasyonu Yazılımları Müdürlüğü — WSDL ve dokümantasyon için.
- WooCommerce ekibi — HPOS API'sı için.

---

**Not**: Bu eklenti PTT Genel Müdürlüğü tarafından geliştirilmemiştir, resmi bir PTT
ürünü değildir. PTT'nin sağladığı SOAP servislerine standart bir istemcidir.
