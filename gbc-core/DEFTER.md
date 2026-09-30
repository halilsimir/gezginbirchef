# GBC Core — Çalışma Dosyası

Bu dosya eklentinin içinde taşınır ve `gbc_defter` seçeneğine yazılır.
Farklı bir sohbet MCP ile `gbc_defter` seçeneğini okuyarak projenin bütün
kararlarını tek çağrıda öğrenebilir. **Kural: bir şey değiştiyse önce burası
güncellenir, sonra kod yazılır.**

Son güncelleme: 30 Eylül 2026 · Sürüm 1.48.1

---

## 1. Site

- gezginbirchef.com — WordPress **çoklu site (network)** kurulumu, ağda 1 site.
- Eklentiler **ağ genelinde** etkin. `is_plugin_active()` bu kurulumda `false`
  döner; `is_plugin_active_for_network()` de kontrol edilmeli.
- Astra 4.14 + Astra Pro · LiteSpeed Cache 7.9 · Rank Math · Loginizer (+Pro)
- Hostinger · PHP 8.3.33 · MariaDB 11.8 · tablo öneki `wp2b_`
- ~1040 yayımlanmış içerik.

## 2. Çalışma kuralları (Halil)

1. Halil koda girmez. Değişiklik doğrudan uygulanır, sonra anlatılır.
   Yapıştırması için kod verilmez.
2. Her turda **"sen yapacaksın / ben yapacağım"** ayrımı net olur.
3. Rapor daima **canlı veriden** verilir, hafızadan değil.
4. Snippet silme/pasife alma Halil'in işidir. Pasife alınacaklar
   `ZZPASIF · ` ön ekiyle işaretlenir (aramada çıksın, listede dibe insin).
5. Asian_World_WordPress sitesine dokunulmaz.
6. Teslim edilen her dosyanın adında **sürüm + tarih** olur:
   `gbc-core-v1.8.2-28eylul2026.zip`

## 3. Mimari

- Tek eklenti: `gbc-core`. WPCode'dan taşınan modüller `modules/` altında.
- Yükleyici `plugins_loaded` 99'da çalışır, dört kapı: elle kapatıldı mı →
  `function_exists(imza)` (WPCode kopyası hâlâ çalışıyorsa yükleme) →
  imzasız modüllerde kaynak snippet hâlâ `publish` mi → dosya yerinde mi.
- `pre_do_shortcode_tag` köprüsü: `[wpcode id="30350"]` gibi kısa kodlar
  WPCode'a gitmeden modülün render fonksiyonuna yönlenir. 1040 yazı
  düzenlenmeden taşıma tamamlanır, WPCode o snippet'i hiç değerlendirmez.
- Yönetici ekranları `inc/` altında ve **ziyaretçi isteğinde yüklenmez**
  (`is_admin() || DOING_CRON`). Ön yüz maliyeti 0 bayt.

## 4. Ölçülmüş tuzaklar (hepsi canlıda doğrulandı)

1. **WPCode tek blok tuzağı:** WPCode Lite bütün aktif PHP snippet'lerini tek
   `eval()` bloğunda çalıştırır. Tek bir `Cannot redeclare` hatası bloğun
   tamamını öldürür ve WPCode rastgele masum bir snippet'i pasife alır.
   Aylarca "aktif ama çalışmıyor" durumunun sebebi buydu.
2. **PHP derleme zamanı:** koşulsuz `function foo(){}` dosya derlenirken
   tanımlanır. Dosyanın başındaki `if (function_exists) return;` koruması
   çalışmaz. Her fonksiyon **tek tek** sarılmalı.
3. **LiteSpeed ölçüm tuzakları:**
   - Satır içi JS'i `src="data:text/javascript;base64,..."` yapar → aramadan
     önce `base64_decode` gerekir.
   - Satır içi `<style>` ve `<link>` dosyalarını TEK birleşik CSS'te toplar →
     `<style id="...">` parmak izleri kaybolur; birleşik CSS indirilip
     aranmalı.
   - Sayfa önbelleği PHP'yi hiç çalıştırmadan cevap verir → tarayıcılar
     `?gbc_...=<zaman><rastgele>` + `Cache-Control: no-cache` ile atlatmalı.
   - `.htaccess` ayrıştırması birkaç saniye önbelleklenir; değişiklik
     gecikmeli görünür (bir kez yanlış rapor verdirdi).
4. **Loginizer 2FA** tek serileştirilmiş metada durur:
   `loginizer_user_settings = array('pref'=>'2fa_app','app_enable'=>1)`.
   `loginizer_2fa_secret` diye bir anahtar yoktur.
5. **Sunucu başlıkları PHP'yi ezer:** statik bir PNG'nin CSP taşıması,
   başlığın web sunucusundan geldiğini kanıtlar; PHP `header()` onu ezemez.
6. **Yönetici menü ikonu** yalnız `data:image/svg+xml;base64,` kabul eder.
   PNG data URI düzgün basılmaz; PNG bir SVG `<image>` içine sarılmalı.
7. **Uzun yönetici isteği kesilir:** 8 sayfa × 3 indirme + 4 PageSpeed çağrısı
   tek istekte 2-3 dakika sürüyor, sunucu kesiyor, **beyaz ekran** kalıyordu.
   Çözüm: iş adım adım, her istekte tek adım (v1.8.2).
8. **Google API anahtarı IP kısıtı:** sitenin A kaydı (76.13.104.242) ile
   sunucunun dışarı çıkış IP'si aynı değil. IP kısıtı konunca PageSpeed
   `HTTP 403` verdi. Kısıt API bazında yapılmalı (yalnız PageSpeed Insights).
9. **MCP bağlantısı Easy MCP AI üzerinden geliyor.** Araç açıklamalarındaki
   `easy_mcp_ai_*` filtre adları bunu kanıtlar. Royal MCP yetenekleri o
   sunucudan yayınlanıyor. **Easy MCP AI kaldırılırsa site erişimi kesilir.**

## 5. Ölçüm (28 Eylül 2026)

| | Değer |
|---|---|
| Önbellekli cevap | 517 ms |
| Önbelleksiz cevap | 552 ms |
| Ortalama sayfa ağırlığı | 2 MB |
| Kullanılmayan CSS | %58 (1695 sınıfın 985'i) |
| Ana sayfa PageSpeed | mobil 88 · masaüstü 78 |
| Gezi rehberi PageSpeed | mobil 38 · masaüstü 69 |

**En ağır iki dosya (ikisi de dış kaynak):**

1. `widgets.skyscanner.net/widget-server/js/loader.js` — **1,68 MB**.
   Gezi rehberi JS'i 2,5 MB, diğer şablonlar 819 KB; fark bu tek dosya.
   PageSpeed: "Reduce unused JavaScript 5,4 sn". → v1.8.2'de perde modülü.
2. `googletagmanager.com/gtag/js` — 612 KB. Sırada.

**Kayma (CLS):** Gezi rehberi masaüstü 0.623. Sebep: İçindekiler kutusu
JavaScript ile, sayfa boyandıktan sonra H1'in altına ekleniyordu; masaüstünde
açık geldiği için 213-432 px'lik blok her şeyi aşağı itiyordu.
→ v1.8.2'de kutu sunucuda basılıyor, id'ler sunucuda veriliyor.

## 6. Ekranlar

| Ekran | Ne yapar |
|---|---|
| Durum | Hangi modül eklentiden, hangisi hâlâ WPCode'dan çalışıyor |
| Nöbetçi | Her modülü canlı sayfada test eder; tüm site taraması; günlük 06:20 |
| Güvenlik | Kapı taraması, 2FA, başlıklar, `.htaccess`, duvar anahtarları |
| Hız & Sağlık | Şablon bazında ölçüm, ölü CSS, LiteSpeed, Site Sağlığı, PageSpeed, tek tıkla düzelt |
| SEO | Search Console özeti, envanter, sıra dağılımı |
| — SEO Fırsatlar | CTR boşluğu, eşik (11–20), kanibalizasyon, yıl güncellemesi |
| — SEO Sayfa Denetimi | Tek sayfa: skor, başlık ağacı, şema, meta, alt, yıl, motorlar, sıralamalar, dış bağlantılar |
| Zamanlanmış İşler | Cron kuyruğu, takılan işler, son çalışma, elle test |
| Çalışma Dosyası | Bu dosya |

## 7. MCP'den okunabilen seçenekler

`royal_mcp_readable_options` ile açıldı — başka bir sohbet tek çağrıda
canlı durumu okuyabilir:

`gbc_hz_son` · `gbc_hz_psi_test` · `gbc_hz_is` · `gbc_nb_son` · `gbc_nb_tam` ·
`gbc_gv_son` · `gbc_core_kapali` · `gbc_cron_kayit` · `gbc_cron_son` ·
`gbc_sky_son` · `gbc_defter`

PageSpeed API anahtarını tutan `gbc_hz_ayar` bilerek **açılmadı**.

## 8. Açık işler

- [ ] İş Ortaklığı panosu: program başına bağlantı sayısı, sayfa dağılımı,
      tıklama sayısı, ölü bağlantı avı, boşluk listesi.
- [ ] `gtag/js` 612 KB — etkileşime kadar ertelensin mi (GA4 ölçümü bozulmadan).
- [ ] Redis: drop-in kurulu ama WordPress kullanmıyor. Hostinger'dan açılacak,
      açılamıyorsa `wp-content/object-cache.php` silinecek.
- [ ] Varsayılan tema yok (Site Sağlığı önerisi) — Twenty Twenty-Five kurulacak,
      etkinleştirilmeyecek.
- [ ] Şema: Liste ve Detay şablonlarında eksik.
- [ ] GA4 özel boyutlar (10 parametre) GA4 yönetiminde tanımlanacak.
- [ ] `/tum-baglantilar/` 404.

## 9. SEO Komuta (v1.9.0)

**Veri kaynağı:** Rank Math'in kendi Search Console tablosu
(`wp2b_rank_math_analytics_gsc`, 34 MB). Dışarıya API isteği gitmez,
kota harcanmaz. Sütun adları tahmin edilmez, `SHOW COLUMNS` ile okunur;
tablo ya da sütun yoksa ekran sebebini yazar.

**Bozulmaz kural — web / görsel ayrımı:** görsel aramasının tıklanma oranı
web aramasının onda biri kadardır. İkisi toplanırsa iyi bir sayfa kötü
görünür ve yanlış iş yaptırır. Tabloda arama türü sütunu varsa yalnız web
satırları alınır; **yoksa "CTR düşük" kuralı hiç çalıştırılmaz** ve ekran
bunu açıkça yazar. Sıralama, gösterim ve tıklama sayıları her durumda
doğrudur.

**Fırsat kuralları** (hepsi ölçülen veriden üretilir):
1. CTR boşluğu — ilk 10'da, 300+ gösterim, beklenen oranın dörtte biri altı.
   Beklenen oran sıraya göre: 1→%27, 3→%11, 9→%2,8.
2. Eşik — 11–20. sıra, 100+ gösterim. En ucuz kazanç burada.
3. Kanibalizasyon — aynı arama 2+ sayfadan çıkıyor, toplam 200+ gösterim.
4. Yıl — aramada geçen yıl bu yıl ya da sonrası; sayfadaki yıl eski mi.

**Sayfa skoru:** 10 kontrolün kaçının geçtiği. Tek H1 · başlık sırası
atlamıyor · en az 3 H2 · bütün görsellerde alt metni · meta açıklama var ·
meta uzunluğu 120–165 · başlık etiketi 30–62 · şema basılıyor · en az 3 iç
bağlantı · eski yıl ifadesi yok.

**Motor tespiti:** sayfada hangi GBC motoru çalışıyor — şablon, otel, rota,
feribot, ortaklık, kur, içindekiler, yazar kutusu, ilgili yazılar. Hem kısa
koddan hem canlı HTML izinden bakılır. Eksik olabilecekler öneri olarak
yazılır, zorlanmaz (gezi şablonunda otel motoru yoksa söylenir).

**Dış bağlantı denetimi:** her dış bağlantı tek tek çağrılır (HEAD, 405
dönerse GET), HTTP cevabı kaydedilir; ortaklık programı tanınır ve
`rel="sponsored"` eksikse işaretlenir. Yavaş iştir, butona basınca çalışır.

**Ölçülen (28 Eylül 2026, son 3 ay, Search Console):**
- "karides tava" 5.419 gösterim · 3. sıra · 8 tıklama — ama önce arama
  türü ayrılmalı, gösterimler görsel aramasından geliyor olabilir.
- "gezginbirchef" marka kelimesinde 11. sıra.
- Gümülcine sayfası 3.093 gösterim; içinden Dedeağaç (2. sıra) ve Drama
  (10. sıra) çıkıyor — ikisi de kendi sayfasını hak ediyor.
- Atina sayfaları toplam 26 gösterim, 28. sıra.
- Ubersuggest: "atina otel" 3.600/ay zorluk 14; "atina konaklama" 140/ay
  ama tıklama başı değeri 25,25 $; "atina nerede kalınır" yalnız 70/ay.

**Sırada:** silo haritası ekranı, kelime havuzu (Ubersuggest sayfa bazlı),
açılacak sayfa listesi, sezon alarmı.

## 10. Menü düzeni (v1.9.2)

Bütün alt sayfalar **tek yerden** (`inc/durum.php`) kaydedilir; modüller
kendi menüsünü açmaz, böylece sıra tahmine kalmaz. Ekranın fonksiyonu
yoksa satır hiç eklenmez.

GBC → Durum · **SEO** · — Fırsatlar · — Sayfa Denetimi · — Toplama ·
— Google Ayarları · — Google Testi · — GA4 Veri Çek · Nöbetçi · Güvenlik ·
Hız & Sağlık · Zamanlanmış İşler · Çalışma Dosyası

**Menüden kaldırılanlar** (kod duruyor, geri alınabilir):
eski Komuta durum ekranı, eski Envanter (defter 31304 tabanlı), eski
Fırsatlar (28 gün veri bekliyordu), Eşleştirme. Yerlerini yeni SEO
ekranları aldı.

**Ortak tasarım:** `inc/tasarim.php` yalnız `page=gbc*` ekranlarında
çalışır; kart, tablo, buton ve form görünümünü tek dile getirir ve her
ekranın tepesine yol çubuğu basar. WordPress'in kendi ekranlarına
dokunmaz, ziyaretçi tarafında hiç yüklenmez.

**Otomatik toplama:** `gbc_seo_toplama` kancası saat başı çalışır, turda
6 sayfa denetler, sonucu `_gbc_seo` metasına yazar ve son 5 denetimi
saklar. 1.040 sayfa yaklaşık 7 günde dolar, sonra kendini tazeler.

## 11. Günlük Kontrol ve Açık Sorunlar (v1.9.3)

Dört alan **her gün 05:40'ta aynı turda** çalışır: Nöbetçi (motorlar canlı
sayfada çalışıyor mu), Güvenlik (kapılar, başlıklar, 2FA), Hız (yüksek
öncelikli bulgular), GA4 (ölçüm gerçekten gidiyor mu).

**Kalıcı uyarı:** bulunan her sorun tek bir deftere (`gbc_sorunlar`) yazılır
ve **çözülene kadar kapanmaz**. Her GBC ekranının tepesinde "N açık sorun"
uyarısı durur, listede "kaç gündür açık" yazar. Ertesi günkü kontrol sorunu
bulamazsa sorun **kendiliğinden kapanır** — "düzelttim" demek yetmez,
ölçüm kapatır. Sorun kimliği sabittir, aynı sorun ikinci kez açılmaz ve
ilk görülme tarihi korunur.

**GA4 bütünlüğü:** modül 30-olcum.php beş olay gönderiyor
(`affiliate_click`, `outbound_click`, `share_click`, `video_click`,
`scroll_depth`) ve sekiz parametre (`page_type`, `post_id`, `link_url`,
`link_text`, `link_domain`, `aff_program`, `aff_id`, `scroll_pct`).
Ekran canlı sayfayı indirip hangisinin gerçekten basıldığını ölçer;
LiteSpeed satır içi JS'i base64'e çevirdiği için çözerek arar.

**Bilinmesi gereken:** parametrenin gönderilmesi yetmez — GA4 bunları
raporlarda ancak **özel boyut** olarak tanımlanırsa gösterir. O iş GA4
yönetim panelinden yapılır, sitede yapılamaz. Sekiz parametre için sekiz
özel boyut açılmalı.

## 12. YouTube perdesi — ilk video kuralı kaldırıldı (v1.9.3)

Eskiden sayfadaki ilk video perde yapılmıyor, olduğu gibi bırakılıyordu
(`ilkAtlandi`). 28 Eylül 2026'da bu kural kaldırıldı: **ilk video da kapak
resmi olarak gelir**, tıklayınca oynar. VideoObject şeması bu betikten
bağımsız, olduğu gibi duruyor.

## 13. Envanter, Silo ve Kaynaklar (v1.9.4)

**Envanter** (`GBC → SEO → Envanter`) üç sekme:
1. *Bütün içerik* — başlık, silo (kategori zinciri), şablon, GBC skoru,
   90 günlük tıklama ve sıra, iç bağ (gelen/giden), şema sayısı, ortaklık
   bağı. Silo ve arama ile süzülür, sayfalanır.
2. *Silo ağacı* — kategori hiyerarşisi sayılarıyla; altında "bir sayfaya
   gerçekte neler bağlı" bölümü: numara verince o sayfaya bağ verenler ve
   onun bağ verdikleri listelenir.
3. *Yetim sayfalar* — siteden hiç iç bağ almayan içerikler.

Bağlantı verisi **Rank Math'in kendi iç bağlantı tablosundan** okunur
(`rank_math_internal_links`, 448 KB) — tahmin değil, sitenin gerçek grafiği.

**Kaynaklar** (`GBC → SEO → Kaynaklar`): yedi veri kaynağının canlı durumu
— Search Console, Rank Math bağlantı sayacı, PageSpeed API, GA4 ölçüm kodu,
GA4 raporlama (servis hesabı), GBC ortaklık tıklama sayacı, Ubersuggest.
Her satır canlı kontrol sonucudur; bağlı olmayan kaynak "kullanılıyor"
diye gösterilmez.

## 14. Nöbetçi — tarama kuralları (v1.9.5)

Nöbetçi baştan yazıldı. Bozulmaz yedi kural:

1. **Ziyaretçi gözüyle.** Sayfa çerezsiz, oturumsuz, **önbellekli** hâliyle
   indirilir. Önbellek atlatılmaz. Yönetici HTML'i kullanılmaz.
2. **İz gerçek çıktıda aranır.** HTML DOMDocument ile okunur, iz CSS
   seçicisiyle aranır. `<style>`, `<script>` ve CSS/JS dosyası içindeki
   eşleşmeler sayılmaz (DOM'da element aradığımız için zaten eşleşemez —
   test edildi). **Tek istisna:** görünür çıktısı olmayan ölçüm motorları
   (GA4, tıklama sayacı, ortaklık sayacı, YouTube perdesi). Onların izi
   betiğin kendisidir; tablo bunu `betik` diye ayrı işaretler, gizlemez.
3. **Dört durum:** ✓ Çalışıyor (olmalı+var) · ✗ EKSİK (olmalı+yok) ·
   ! FAZLA (olmamalı+var) · – Gerekmez (olmamalı+yok).
4. **Sayfa sayfa tablo**, motor ve şablon süzgeci, CSV indirme.
5. **Sayfa taramasıyla ölçülemeyen motor** (Şablon CSS dosyaya) tablodan
   çıkarılır, sunucuda kontrol edilir: dosyalar var mı, 200 dönüyor mu.
6. **Kaynak sütunu** kodun şu an çalıştığı dosyayı yazar, kapalı WPCode
   ID'sini değil.
7. **Karşılaştırma:** dün ✓ bugün ✗ olan her sayfa+motor ayrı alarm olarak
   Günlük Kontrol'e düşer.

CSS→XPath dönüştürücü etiket, `.sınıf`, `#kimlik`, `[nitelik]`,
`[nitelik*="değer"]` ve alt eleman birleşimini destekler.

**Karar bekleyen motorlar** (kural yazılmadı, taramaya girmiyor): Otel,
Rota, Feribot, Yunanistan haritası, Bölüm medyası, Kart video, AdSense,
Şefe Sor. Kural yazılmadan taramak yanlış sonuç üretir.

Tur saat başı çalışır, turda 8 sayfa. Sonuç her sayfanın `_gbc_nk`
metasında durur.

## 15. Nöbetçi kural düzeltmeleri + YouTube perdesi (v1.9.6)

İlk turdan sonra dört kural düzeltildi:

1. **YouTube perdesi izi** — `gbcYtFacade` sayfada hiç geçmiyordu. Yeni iz:
   `script#gbc-yt-facade` (modül betiğin kendisine bu kimliği basıyor).
2. **İlgili yazılar** — Gezi ve Liste şablonları kendi kutusunu basıyor.
   O iki şablon için iz `.gz-rel-list, .gz2-alakali, .gz-next-row`
   (virgül = VEYA, biri yeterli); diğer şablonlarda `section.gbc-related`.
   CSS→XPath dönüştürücüsüne virgül desteği eklendi.
3. **Canlı kur** — karar sayfanın GÖRÜNÜR metnine göre veriliyor
   (script/style çıkarılıyor). Yabancı para birimi: € EUR euro $ USD dolar
   forint HUF £ GBP. Tutar yoksa "– Gerekmez", ✗ değil. TL tek başına
   saymaz — Canlı kur zaten yabancı tutarı TL'ye çevirmek için var.
4. **Tur sırası** — her tur ana sayfa + yedi şablondan birer sayfa alıyor
   (Gezi, Liste, Detay, Rota, Tarif, Blog, Sözlük), kalan kontenjan hiç
   denetlenmemişlere gidiyor. Tur başına 9 sayfa.

**YouTube perdesi ikinci düzeltme (asıl arıza buydu):** LiteSpeed iframe'in
`src`'sini `about:blank` yapıp gerçek adresi `data-src`'ye taşıyor. Betik
önce `src`'yi okuduğu için `about:blank` alıyor, içinde `/embed/` olmadığı
için işlem yarıda kesiliyor ve perde HİÇ kurulmuyordu. Okuma sırası ters
çevrildi: önce `data-src`, sonra `src`; `about:blank` gelirse boş sayılıyor.
Test sayfası: /bolonya-bologna-gezi-rotalari-plan/

## 16. API Merkezi (v1.10.0)

**Tek kapı.** Sitenin dışarıdan aldığı bütün veri kaynakları ve anahtarları
`GBC → API Merkezi`'nde durur. Anahtarlar bugüne kadar üç ayrı yere
dağılmıştı (`gbc_km_ayar`, `gbc_hz_ayar`, modüller); merkez artık burası.

**Bozmama kuralı:** merkez anahtarı ESKİ YERİNE DE yazar. Eski modüller
kendi seçeneklerinden okumaya devam eder, hiçbir şey kırılmaz. Okurken
önce merkeze, yoksa eski yere bakılır (`gbc_api( $id )`).

Eşleşmeler: `psi_anahtar → gbc_hz_ayar[psi_anahtar]`,
`google_sa / gsc_site / ga4_property → gbc_km_ayar[...]`.

**11 dış kaynak, 4 grup:** Google (PSI, servis hesabı, GSC site, GA4 mülk,
GA4 ölçüm kimliği) · Ortaklık (Travelpayouts API + marker, Booking AID,
GetYourGuide partner) · Kelime (Ubersuggest) · Otomasyon (Make webhook).

**Beşinin gerçek testi var** — test dışarı çıkar, "kayıtlı" ile "çalışıyor"
ayrı şeydir: PSI (puan döner mi), Google servis hesabı (belirteç alınıyor
mu), GA4 ölçüm kodu (5 olay 8 parametre sayfada mı), Travelpayouts
(`api.travelpayouts.com/v2/prices/latest` başarı dönüyor mu), Make webhook
(HTTP kodu). Booking, GetYourGuide ve Travelpayouts marker'ının genel
API'si yok — "test edilemez" diye yazar, uydurmaz.

**Site içi kaynaklar** ayrı tabloda: Rank Math GSC tablosu, Rank Math iç
bağlantı sayacı, GBC ortaklık tık sayacı.

Anahtarlar ekranda maskelenir (`AIza•••••H710`). Boş bırakılan alan
değişmez; silmek için tek başına `-` yazılır.

## 17. Impact.com ve biçim kontrolü (v1.10.1)

**Impact.com** API Merkezi'ne eklendi: Account SID + Auth Token. Skyscanner
ortaklığı bu ağ üzerinden yürüyor. Testi gerçek: Basic auth ile
`api.impact.com/Mediapartners/{SID}/Campaigns` çağrılır; 401/403 gelirse
"kimlik reddedildi", 200 gelirse kaç program göründüğü yazılır.

**Biçim kontrolü** — "doğru girdim mi" sorusunu artık ekran cevaplıyor.
Anahtarın kendisi gösterilmeden beklenen kalıba uyup uymadığı söylenir:
Travelpayouts marker yalnız rakam 4-9 hane, Booking AID yalnız rakam,
Impact SID en az 8 karakter harf/rakam, Travelpayouts belirteci uzun
harf-rakam dizisi. Kalıbı olmayan alanlarda "kontrol tanımlı değil" denir,
uydurma doğrulama yapılmaz.

**Gizli olmayan durum aynası** (`gbc_api_durum`): her anahtar için yalnız
"dolu mu, kaç karakter, biçimi uygun mu, son 4 hane" tutulur — anahtarın
kendisi ASLA yazılmaz. Bu ayna MCP'ye açık, böylece dışarıdan "hangi alan
dolu" sorusu yanıtlanabilir ama hiçbir sır sızmaz. `gbc_api` seçeneğinin
kendisi (anahtarların durduğu yer) MCP'ye kapalıdır.

Toplam 13 dış kaynak, 7'sinin gerçek testi var.

## 18. Ortaklık haritası — ölçüldü (28 Eylül 2026)

Bağlantı defteri (sayfa 30120) sayıldı. Gerçek durum:

**Travelpayouts üzerinden (marker=767959, 580 bağlantı):**
| Program | Bağlantı | campaign_id |
|---|---|---|
| Booking | 267 | 84 |
| GetYourGuide | 169 | 108 |
| DiscoverCars | 89 | 117 |
| Omio | 55 | 91 |

**Doğrudan (Travelpayouts'tan geçmiyor):**
| Program | Bağlantı | Biçim |
|---|---|---|
| Ferryhopper | 76 | `ferryhopper.com/tr/?aff_uid=gzgbr` |
| Skyscanner | 13 | `skyscanner.pxf.io/E0YrMP` (Impact ağı) |
| Yesim | 3 | `yesim.tpx.li/...` |

**Marker doğrulandı:** `767959` defterde 583 bağlantıda geçiyor, hesap
aliumutuyanik@gmail.com. Karışıklık yok.

**Önemli ayrım:** GetYourGuide'ın doğrudan partner ID'si sitede
KULLANILMIYOR — 169 bağlantının tamamı Travelpayouts üzerinden gidiyor.
Doğrudan ortaklığa geçilirse defterdeki bağlantıların yeniden yazılması
gerekir. Booking için de durum aynı: doğrudan AID yok, hepsi Travelpayouts.

**API Merkezi'nden çıkarılanlar:** Make.com (kendi eklentisi var, kendi
bağlanıyor), Booking AID (bağlantılar Travelpayouts'tan gidiyor),
Ubersuggest anahtar alanı (eklentiye açık API'si yok; Claude MCP ile
çekilir). Üçü de "bilgi satırı" olarak duruyor, anahtar istemiyor.

**Eklenenler:** Impact.com (SID + Auth Token, gerçek testli),
Ferryhopper aff_uid, CJ Affiliate (belirteç + CID, ileride kullanılacak,
testi hazır).

## 19. Google Testi ekranı kapatıldı (v1.10.3)

Eski "Google Testi" ekranı üç adım yapıyordu: belirteç, Search Console
sorgusu, GA4 raporu. Üçü de API Merkezi'ne taşındı:

- Belirteç → `google_sa` satırının testi
- Search Console sorgusu → `gsc_site` satırının testi (7 günlük aralıkta
  kaç satır döndüğünü yazar)
- GA4 raporu → `ga4_property` satırının testi (son 7 günde kaç sayfa
  satırı döndüğünü yazar)

Ekran menüden kaldırıldı, kodu duruyor. Artık tek yerden test ediliyor.

## 20. Nöbetçi ikinci düzeltme + kapak LCP (v1.10.4)

**Nöbetçi:**
1. YouTube perdesi izi artık HAM XPath: `//script[@id="gbc-yt-facade"]`.
   Çevirici devre dışı, şüpheye yer yok. LiteSpeed betiği
   `src="data:text/javascript;base64,..."` biçimine çevirse de id duruyor —
   iki biçimde de test edildi, ikisinde de bulunuyor.
2. İlgili yazılar, Gezi ve Liste'de DÖRT seçiciden biri yeterli:
   `.gz-rel-list`, `.gz2-alakali`, `.gz-next-row`, `section.gbc-related`.
   Ana sayfa bu kuralın dışında — orada "–" çıkar.
3. Canlı kur birim listesine **GEL ve lari** eklendi (Tiflis fiyatları).
4. İçindekiler'in olması gereken şablonlarına **Sözlük** eklendi.

**Bayat sonuç uyarısı:** tablo son taramanın sonucunu gösterir. Kural
değiştikten sonra sayfa yeniden taranmadıysa eski sonuç görünür; artık her
satırın yanında kaç önce tarandığı yazıyor ve tablonun üstünde uyarı var.

**YouTube kapak LCP (modül 54):**
- Kapak görseli `maxresdefault` yerine **`hqdefault`** — çoğu videoda
  maxres yok (404 → ikinci istek) ve dosya çok daha küçük.
- Sunucuda, sayfanın içeriğindeki İLK YouTube videosunun kimliği bulunup
  `<head>`'e ön yükleme basılıyor:
  `<link rel="preload" as="image" fetchpriority="high" href="…/hqdefault.jpg">`
  Böylece tarayıcı kapağı JS'i beklemeden indirmeye başlıyor.
  Ölçülen sorun: Rota'da mobil LCP 5,8 sn.

## 21. API Merkezi sayaç hatası (v1.10.5)

**Belirti:** 10 kaynağın 10'u ÇALIŞIYOR görünürken üstteki kutu
"Testte kalan: 1" diyordu.

**Sebep:** listeden çıkarılan kaynakların (Make.com, Booking AID,
Ubersuggest anahtar alanı) eski test sonuçları `gbc_api_test` seçeneğinde
duruyordu. Sayaç bütün depoyu sayıyordu, ekrandaki satırları değil — yani
ekranda karşılığı olmayan bir şeyi rapor ediyordu. Sitede bozukluk yoktu.

**Çözüm:** sayaç artık YALNIZ listede satırı olan kimlikleri sayıyor; ekran
açılırken öksüz kayıtlar siliniyor ve kaç tane silindiği bir kez bildiriliyor.
Kartlar da netleşti: "Testi geçen 12 / 12", "Testte kalan 0",
"Test edilemez 6 — numara ya da kod, denenecek uç yok".

Test edildi: 15 kayıtlı depoda 3 öksüz kayıt bulundu, silindi, "Testte
kalan" 3'ten 0'a düştü.

## 22. Nöbetçi üçüncü düzeltme (v1.10.6)

Halil'in verdiği üç maddenin karşılığı:

1. **YouTube perdesi — DOM seçici bırakıldı.** Artık ham HTML'de
   `#<script[^>]+id=["']gbc-yt-facade["']#i` aranıyor. Sebep: LiteSpeed
   betiği `src="data:text/javascript;base64,…"` biçimine çevirip yerini
   değiştirebiliyor; DOMDocument sayfanın bozuk işaretlemesinde o düğümü
   kaybedebiliyordu. Yeni iz türü: `ham`.
2. **"İlgili yazılar" + "Silo iç linkleme" → tek motor: İç link kutusu.**
   İz: `.gbc-silo, section.gbc-related, .gz-rel-list, .gz2-alakali`
   (biri yeterli). Nerede: ana sayfa hariç her yerde. Silo sayısı
   "yalnız sayılır" sütunu olarak (`silo_sayi`) kaldı.
3. **Kural değişince eski sonuçlar.** `GBC_NK_KURAL_SURUM` sabiti eklendi;
   her tarama kaydına hangi kural setiyle üretildiği yazılıyor. Sürüm
   eskiyse satır "yeniden taranacak" diye işaretleniyor ve turlar sıraya
   ONLARDAN başlıyor. "Tümünü yeniden tara" düğmesi `gbc_nk_zorla`
   damgasını atıyor, o andan eski her satır tazelenmeyi bekliyor.

## 23. İki Nöbetçi tek Kontrol Merkezi oldu (v1.11.0)

**Neden:** iki ayrı ekran aynı işi iki farklı yöntemle yapıyordu; biri
metin arıyor biri DOM okuyordu, sonuçları da çelişiyordu.

**Ad:** "Nöbetçi" → **Kontrol Merkezi**. Slug aynı kaldı
(`gbc-nobetci-kural`) ki eski bağlantılar ve defterdeki kayıtlar kırılmasın.

**Ekran artık iki katman:**

- **Kod sağlığı (yeni · inc/kod-sagligi.php).** Her modül için: dosya
  yerinde mi, kaç bayt, PHP sözdizimi geçerli mi (`token_get_all` ·
  `TOKEN_PARSE`), yüklendi mi, yüklenmediyse neden (WPCode kopyası açık /
  elle kapatılmış / dosya yok), imza fonksiyonu gerçekten tanımlı mı, kısa
  kodu kayıtlı mı, WPCode'daki eski kopya hâlâ yayında mı (çift çıktı
  riski). Ayrıca 13 yönetici ekranının dosyası + geri çağırması ve 3
  zamanlanmış iş denetleniyor. Bulunan her sorun `kod` alanı altında
  Günlük Kontrol defterine yazılıyor, çözülene kadar kapanmıyor.
- **Canlı sayfa taraması** (eskisi, genişletilmiş hâli).

**Eski Nöbetçi'den taşınanlar:**

- `gbc_nk_css_govde()` — LiteSpeed satır içi `<style>` bloklarını birleşik
  CSS dosyasına taşıdığı için görünmeyen izler: aynı alan adındaki stil
  dosyaları (en çok 4, tur boyunca bir kez) indirilip gövdeye katılıyor.
  Yeni iz türü: `css`. İlk kullanan motor: Ortaklık stili (`.gbc-yan-ad`).
- `gbc_nk_izleri_topla()` — otomatik taban. Sayfadaki bütün
  `gbc-* / gz-* / v1-* / tr-* / fb-* / yn-*` id ve sınıf adları kaydediliyor.
  Bir önceki taramada duran 2'den fazla ad bugün yoksa alarm: tanımadığımız
  bir motor sussa bile yakalanıyor.
- Sayfa boyutu karşılaştırması: bir sayfa öncekinin %35 altına düşerse
  "bir şey basılmıyor" alarmı.

**N/A — kuralı olmayan motor artık taramanın dışında değil.** Karar
bekleyen 10 motor (Otel, Rota, Feribot, Yunanistan, Bölüm medyası, Kart
video, Galeri lisansı, Uçuş perdesi, AdSense, Şefe Sor) ölçülüyor ama
sorun sayılmıyor: `◉ var` / `○ yok`. Ekrandaki "Karar bekleyen motorlar"
tablosu her biri için **kaç sayfada çıktı / hangi şablonlarda / örnek
adres** ve cevaplanacak soruyu gösteriyor. Hiç çıkmıyorsa kod
tetiklenmiyor demektir — kural o kanıta bakarak yazılacak.

**Kaldırılanlar:** `inc/nobetci.php` artık yüklenmiyor (`arsiv/` klasörüne
alındı, silinmedi), menüdeki "Nöbetçi (eski)" satırı gitti,
`gbc_nb_gunluk` cronu ve `gbc_nb_alarm` seçeneği kendiliğinden
temizleniyor, MCP listesinden `gbc_nb_son` / `gbc_nb_tam` çıktı,
yerine `gbc_kod_son` girdi. Günlük kontrol turu artık önce kod sağlığını,
sonra birleşik sayfa taramasını (normalin iki katı sayfa) çalıştırıyor.

**Test:** `nk_test4.php` (ham iz 5 biçimde bulur, 4 seçici ayrı ayrı
eşleşir, eskimişlik mantığı), `nk_test5.php` (25 motor · 15 kurallı ·
10 N/A, bütün düzenli ifadeler geçerli, iz toplayıcı, sözdizimi denetçisi
bozuk dosyayı yakalar, 13 ekran geri çağırması gerçekten tanımlı),
`yukleme_testi.php` (çekirdek + 13 ekran + 21 modül, ölümcül hata yok,
ziyaretçi tarafına 0 bayt).

## 24. Gerçek sorun düzeltmeleri (v1.11.1)

Hepsi canlı sayfada ölçülerek bulundu, hepsi birim testli.

**1. Canlı kur — lari (GEL) ve won (KRW)**
TCMB XML'inde GEL yok, KRW var (Unit 100). Artık eksik kalan birimler
open.er-api.com'dan tamamlanıyor (HUF, GEL, KRW). Tanınan yazımlar:
`₾ GEL lari larisi Lari` ve `₩ KRW won wonu Won`. Simge önde aralık
desenleri (`₾10 - ₾20`, `₾3-5`, `₩5.000 - ₩9.000`) tek bir simge
sınıfına (`[€₾₩]`) çevrildi, geri referansla aynı simge zorunlu.
Elle işaretler de genişledi: `{{gel:40}}`, `{{krw:12000}}`.
"GEL" yalnız BÜYÜK harf eşleşir — desen /i değil, böylece "5 gel dedi"
yanlışlıkla çevrilmiyor (testte doğrulandı).

**2. İçindekiler — Rota şablonunda kutu basılmıyordu**
Sebep ölçüldü: Rota şablonu sayfada HİÇ h2 basmıyor, bölümler
`<h3 class="gbc-rota-gun-bas">` ("1. Gün", "2. Gün"…). Kutu üç h2
bulamayınca sessizce çekiliyordu — betik yükleniyor, kutu oluşmuyor.
Çözüm: başlık toplama ayrı bir fonksiyona alındı
(`gbc_toc_basliklara_id`); önce h2 denenir, üç tane yoksa h3'e düşülür.
Karışık liste üretilmez: ya hepsi h2 ya hepsi h3. Liste şablonunda dört
h2 var, eski davranışı aynen korunuyor — kuraldan çıkarmaya gerek kalmadı.

**3. İç link kutusu — üç sayfada hiçbir kutu yoktu**
Canlı ölçüm (/erzincan-gezi-rehberi/): `.gbc-silo 0`, `section.gbc-related 0`,
`.gz-rel-list 0`, `.gz2-alakali 0`. Üç sebep üst üste binmiş:
  1. Silo bloğu (WPCode 28213) kardeş yazıyla ORTAK KONU ETİKETİ
     bulamayınca hiçbir şey basmıyor; "İlgili Yazılar" ise sayfa silo
     kategorisinde diye çoktan çekilmiş oluyordu → ikisi de yok.
  2. Gezi şablonunun kenar kartları ("X Rehberleri", "Sonra Nereye?",
     "Alakalı Yazılar") ACF alanlarından besleniyor; Erzincan'da üçü de boş.
  3. Sayfalar (page) hiç kutu almıyordu — /esim-nedir-yurt-disinda-internet/.
Çözüm iki katlı: (a) İlgili Yazılar artık silonun SEÇİMİNE bakıyor, boşsa
devreye giriyor; (b) yeni **İç link garantisi** süzgeci (öncelik 30, silo ve
ilgili yazılar 20) içerikte dört kutudan biri bile varsa karışmıyor, hiçbiri
yoksa kendi kutusunu basıyor — aynı kategori → aynı etiket → en yeni yazılar
sırasıyla. Artık ana sayfa dışında her yazı ve sayfanın dibinde en az bir iç
link kutusu olması garanti; Kontrol Merkezi'ndeki kural da karşılanabilir.

**4. SEO toplayıcı "kurulu değil" uyarısı — yanlış alarm**
Canlı cron listesinde `gbc_seo_toplama` saat başı kurulu (bir sonraki 20:37).
Uyarıyı v1.11.0'daki kod sağlığı katmanı üretiyordu: yanlış kanca adını
(`gbc_seo_tur`) arıyordu. Düzeltildi; ayrıca `gbc_kur_gunluk` ve
`gbc_gv_tarama` da denetime eklendi.

**5. YouTube kapağı pikselleşiyordu**
LCP için `hqdefault.jpg`'e (480×360, 4:3) geçilmişti; 16:9 kutuda
`object-fit:cover` ile kırpılıp bulanıklaşıyordu. Artık merdiven:
`maxresdefault → hq720 → sddefault → hqdefault → 0`. YouTube eksik kapak
için 404 değil 120×90 gri vekil döndürdüğü için `onerror` yetmiyor;
yüklenen görselin `naturalWidth` değeri de ölçülüyor. `<head>` ön yüklemesi
de maxresdefault'a çekildi. Beş senaryo Node ile test edildi, sonsuz döngü
yok (en çok 5 istek).

**6. UCSS bilerek kapalı**
Yeni `gbc_hz_bilerek()` listesi: ölçümde "eksik" görünen ama kapalı kalması
KARAR olan ayarlar. UCSS açılınca ana sayfa sekmeleri, mobil kart çizgisi ve
SVG bozuldu (28 Eylül 2026) — artık tabloda "bilerek kapalı" rozetiyle
görünüyor, sorun sayılmıyor, "Düzelt" düğmesi onu açmıyor (düzeltilebilir
listesinden çıkarıldı). Ölü CSS uyarısının çözüm metni de değişti: çözüm
UCSS değil, şablon başına CSS ayırmak.

**7. CSS/JS ağırlığı sıkıştırılmış ölçülüyor**
Eskiden gzip'ten çıkmış boyut sayılıyordu, ağırlık 4-5 kat büyük
görünüyordu. Artık ziyaretçinin gerçekten indirdiği bayt:
content-length → (sıkıştırma yoksa) gövde → (chunked ise) `decompress=false`
ile ikinci istek. Açılmış boyut da saklanıyor, ekranda "açılmış X" diye
ikinci satırda. Eşikler buna göre yeniden ayarlandı: CSS 150 KB → 50 KB,
JS 400 KB → 130 KB.

**8. Head'de logo preload'u iki kez basılıyordu**
Canlı ana sayfa başı ölçüldü: 6 preload, ikisi birebir aynı logo satırı
(Astra hem başlık logosu hem LCP görseli için basıyor). Yeni modül
`modules/02-head-temizlik.php` `<head>` çıktısını tamponlayıp AYNI href'li
ikinci preload kopyasını siliyor; sırayı değiştirmiyor, farklı adresleri
birleştirmiyor, href'siz satıra dokunmuyor, admin/REST/AJAX/cron'da hiç
çalışmıyor. 14 senaryo test edildi.

**Sırada (yapılmadı):** Gezi şablonu CSS'inin şablon bazında ayrılması ve
kullanılmayan kuralların sayfa sayfa test edilerek temizlenmesi. Görsel
regresyon riski taşıdığı için ayrı sürümde, ayrı testle yapılacak.

## 25. Kalan sorunlar — canlıda ölçülüp kökünden çözüldü (v1.11.2)

Bu turda hiçbir şey tahmin edilmedi; her maddenin sebebi canlı sayfadan
okunarak bulundu.

**1. Canlı kur — nokta ondalık (asıl sebep)**
`/roma-yakin-yerler-castel-gandolfo-ve-nemi/` sayfasında € üç kez geçiyor,
`.gbc-tl` sıfır. Sebep sayıydı, şablon değil: sayı deseni yalnız
`2.100` (binlik nokta) ve `12,50` (virgül ondalık) tanıyordu. "**2.10 €**"
ikisine de uymuyordu — `\d+(?:,\d+)?` "2"de duruyor, ardından nokta
geldiği için eşleşme düşüyordu. Aynı sebeple "2.5 GEL" de tanınmıyordu.
Yeni kural: noktadan (ya da virgülden) sonra ÜÇ rakam varsa binlik,
BİR-İKİ rakam varsa ondalık. `gbc_kur_sayi()` de buna göre yeniden yazıldı
— eskiden "2.10" → 210 oluyordu.
Test edilen: 2.10 / 2,5 / 2.100 / 2.100,50 / 1.500 / 12,50 / 8.000 —
hepsi doğru sayıya ve doğru TL'ye çevriliyor.

**2. Canlı kur — GEL kuru önbellekte yoktu (ikinci sebep)**
v1.11.1 GEL'i tanıyordu ama `gbc_kur_son` seçeneği GEL eklenmeden önce
yazılmıştı; `gbc_kur()` EUR doluysa hiç tazelemiyordu, dolayısıyla
`gbc_kur_oran('GEL')` 0 dönüyor ve rozet basılmıyordu. Günlük cron'u
(18:38) beklemek gerekiyordu. Çözüm: `GBC_KUR_SURUM` sabiti. Kayıtlı kur
eski sürümdense bir kez tazelenir; 10 dakikalık kilit sayesinde aynı anda
gelen ziyaretçiler peş peşe istek atmaz, tazeleme başarısız olursa eldeki
kurla devam edilir.

**3. İçindekiler — Rota (v11) şablonu**
`/bolonya-bologna-gezi-rotalari-plan/` ham içeriği yalnızca iki kısa kod:
`[wpcode id="23340"][wpcode id="23342"]`. İşlenmiş sayfada **tek bir h2 ya
da h3 yok** — bölüm başlıkları `<div class="v11-block-header">` ve
`<div class="v11-controls-header">`. Bu yüzden ne h2 ne h3 yedeği işe
yarıyordu. Üçüncü basamak eklendi: bu sınıflar başlık sayılır. Etiket
DEĞİŞTİRİLMEZ (h2'ye çevirmek görünümü bozardı), yalnız id verilir.
Tek geçişli desen kullanıldı, başlıklar sayfadaki sırayla listeleniyor.
Liste şablonunda dört gerçek h2 var; orası zaten çalışıyordu, kuraldan
çıkarmaya gerek kalmadı.

**4. YouTube kapağı ön yüklemesi — hiçbir sayfada basılmıyordu**
Sebep aynı: `gbc_yt_ilk_video()` HAM içeriğe bakıyordu, videolar ise
şablonun okuduğu alanlardan geliyor. Üç katman yapıldı:
(a) yazının içeriği, (b) yazının meta alanları (ACF; sonuç bir gün
`_gbc_yt_ilk` meta'sında tutulur, her istekte meta taranmaz),
(c) **işlenmiş içerik** — `the_content` 999'da ilk `/embed/ID` bulunur ve
preload satırı gövdenin en başına konur. Üçüncü katman her durumda
çalışır, çünkü sayfada gerçekten ne varsa ona bakar. `<head>`'de zaten
basıldıysa ikinci kez basılmaz.

**5. Logo preload'u — wp_head tamponu yetmedi**
Canlı ana sayfada satır ÜÇ kez görünüyordu ve hepsi LiteSpeed'in
birleştirilmiş CSS linkinin hemen ardındaydı: yani kopyalar LiteSpeed
sayfayı yeniden düzenledikten sonraki hâlde duruyor, wp_head tamponu
onları göremiyor. Çözüm: LiteSpeed varsa onun kendi son tampon süzgecine
(`litespeed_buffer_finalize`, öncelik 99) bağlanmak; yoksa `template_redirect`
ile tam sayfa tamponu. İkisi de yalnız `</head>`'e kadar olan kısımda ve
yalnız aynı href'li ikinci preload kopyasında iş yapar; gövdeye hiç
dokunmaz, bir şey değişmezse çıktı olduğu gibi geçer.

**6. Kullanılmayan CSS %61 — sayı yanıltıcıydı, ölçüm düzeltildi**
Şablon CSS'i ZATEN şablon başına yükleniyor (`modules/01-sablon-css.php`:
Gezi CSS'i yalnız `[wpcode id="22608"]` taşıyan sayfalara iniyor). Toplam
yüzde bütün dosyaların sınıflarını tek havuzda topladığı için, Gezi'ye
özel bir sınıf Tarif sayfasında görünmediğinde "ölü" sayılıyordu.
Hız ekranına **dosya dosya** tablo eklendi: her CSS dosyasının kaç sınıfı
var, kaçı kullanılmıyor, dosya kaç bayt iniyor, örnek ölü sınıflar.
Ölü sınıf sayısına göre sıralı — en üstteki dosya en çok kazancı getirir.
Temizlik artık tahminle değil, bu listeyle yapılacak.

**Sırada:** JS 794 KB / 9 dosya. Ağır varlıklar tablosunda dosya dosya
listeli; hangisinin kaldırılabileceği Halil'in kararı (GA4 bilerek duruyor).

## 26. Sayfa ağırlığı — ölçülüp parçalandı (v1.12.0)

Gezi rehberinin 378 KB'lık HTML'i satır satır tartıldı
(/budapeste-gezi-rehberi/, 28 Eylül 2026):

| Parça | Boyut |
|---|---|
| JSON-LD şema (3 blok) | **48 KB** — 33,2 + 11,6 + 3,2 |
| Satır içi `<script>` (13 blok) | **73 KB** |
| LiteSpeed'in base64'e çevirdiği betikler | **43 KB** |
| Satır içi SVG | 40 KB |
| Kalan işaretleme ve metin | ~174 KB |

JS tarafı: 9 dosya. gtag.js (Google), 6 LiteSpeed birleşik paketi,
jquery-migrate, wp-consent-api. Satır içi `<style>` sıfır — şablon CSS'i
zaten dosyada.

**1. Görsel lisansı şeması 33 KB → ~6 KB**
Tek başına en büyük parça buydu: ~90 görsel × ~350 bayt ImageObject.
İki değişiklik: (a) düğüm sayısına üst sınır (20, süzgeçle değişir:
`gbc_gal_sema_ust_sinir`), (b) `copyrightNotice` kaldırıldı — `creditText`
ve `creator` zaten hak sahibini söylüyor, üçüncü tekrardı.
Google'ın görsel lisansı için şart olan `contentUrl` + `license` +
`acquireLicensePage` üçü de duruyor.

**2. Kendi satır içi betiklerimiz dosyaya alındı**
Yeni modül `modules/03-satir-ici-js.php`. İçindekiler betiği (5,8 KB) ve
YouTube perdesi (5,0 KB) artık `uploads/gbc-js/<ad>-<özet>.js` dosyasından
bağlanıyor. Dosya adında içeriğin özeti var; betik değişince yeni dosya
üretilir, eskisi silinir.
Neden önemli: LiteSpeed satır içi betiği `data:text/javascript;base64`
adresine çeviriyor, base64 boyutu ÜÇTE BİR büyütüyor — 10,8 KB'lık betik
HTML'de ~14,4 KB yer kaplıyor ve HER SAYFADA yeniden iniyor. Dosya olunca
bir kez inip tarayıcı önbelleğinde kalıyor.
`<script>` etiketindeki id korundu, Kontrol Merkezi izleri bozulmadı.
Dosya yazılamazsa betik eskisi gibi satır içi basılıyor — en kötü senaryo
bugünkü davranış.

**Toplam beklenen kazanç:** gezi sayfasında ~37 KB HTML (%10), ikinci
sayfadan itibaren 11 KB daha (betikler önbellekte).

**Dokunulmayanlar ve sebebi:**
- `jquery-migrate` (~13 KB): eski jQuery API'lerini yamalıyor. Kaldırmak
  bir eklentiyi sessizce bozabilir; 13 KB için o risk alınmadı.
- `gtag.js`: en ağır tek dosya. Etkileşime ya da 3. saniyeye ertelenebilir
  ama hemen ayrılan ziyaretçinin sayfa görüntülemesi kaybolur — ölçüm
  verisini değiştiren bir karar, Halil'e sorulacak.
- LiteSpeed'in kendi lazyload betiği (8 KB) ve wp-consent-api: bizim
  değil.
- Satır içi betiklerin geri kalanı (şablon snippet'leri): genel bir
  "hepsini dosyaya taşı" süzgeci çalışma sırasını bozabilir. Yalnız
  kendi modüllerimizin betikleri taşındı.

## 27. Google Ayarları ve Komuta veri çekimi kaldırıldı (v1.12.1)

**Soru:** Google Ayarları ekranı ve GA4/Search Console veri çekimi gerekli mi,
otomatik olsa daha mı iyi?

**Ölçülen cevap — üçü de gereksizdi:**

1. **Google Ayarları ekranı (gbc-komuta-ayar).** Aynı anahtarları API
   Merkezi tutuyor ve eski seçeneğe (`gbc_km_ayar`) de yazıyor. İki yerde
   aynı anahtar demek, biri eskiyince sessiz hata demek. Menüden kaldırıldı;
   anahtarların tek adresi artık API Merkezi.

2. **Veri Çek ekranı ve günlük/haftalık çekim.** Zaten otomatikti
   (`gbc_km_gunluk_cek` 04:10, `gbc_km_sezon_cek` haftalık). Ama çektiği
   veriyi hiçbir yeni ekran kullanmıyor: `wp2b_gbc_km_sayfa` ve
   `wp2b_gbc_km_sorgu` tablolarını YALNIZ 90-komuta.php'nin kendi ekranları
   okuyor (grep ile doğrulandı). Yeni SEO ekranları Rank Math'in kendi
   Search Console tablosundan (`wp2b_rank_math_analytics_gsc`, 34 MB)
   besleniyor. Yani her gün aynı veri ikinci kez indirilip ikinci kez
   saklanıyordu; Google kotası boşuna harcanıyordu.

3. **Komuta nöbetçisi (`gbc_km_nobetci_cek`, saat başı).** Aynı işi Kontrol
   Merkezi saat başı zaten yapıyor. İkisi birden çalışınca aynı sayfalar
   iki kez indiriliyordu.

**GA4 tarafı:** Komuta GA4'ten hiç veri çekmiyordu — yalnız property
numarasını saklıyordu, çekim Search Console'a gidiyordu. GA4 ölçümü sitede
gtag ile çalışıyor, Günlük Kontrol de GA4 olay/parametre denetimini yapıyor.
Çekilecek bir şey yok.

**Yapılan:** üç cron temizlendi ve `gbc_km_cekim_kapali` bayrağıyla yeniden
kurulmaları engellendi; iki ekran menüden çıkarıldı.
**Yapılmayan:** hiçbir tablo, hiçbir seçenek, hiçbir fonksiyon silinmedi.
Ekran fonksiyonları yerinde duruyor. Geri açmak için tek iş:
`gbc_km_cekim_kapali` seçeneğini silmek.

Menü artık 15 satır: Durum · SEO (Envanter, Fırsatlar, Sayfa Denetimi,
Toplama, API Merkezi, Kaynak) · Günlük Kontrol · Kontrol Merkezi ·
Güvenlik · Hız & Sağlık · Zamanlanmış İşler · Çalışma Dosyası.

## 28. WPCode → eklenti taşıması, birinci parti (v1.13.0)

Karar: ortaklık tarafı tamamen eklentiye alınacak, WPCode boşaltılacak.
Bu sürümde iki snippet taşındı.

**1. Hayalet Cron Temizliği (snippet 30190 → modules/04-hayalet-cron.php)**
Silinmiş eklentilerden kalan 12 kanca izleniyor (The Events Calendar,
Wordfence LS, Sucuri, Imagify, Yoast, Glossary, BetterStudio, YotuWP).
Her yönetici sayfasında kendiliğinden temizleniyor — sebebi şu: WordPress
tekrarlayan bir görevi ÇALIŞTIRMADAN ÖNCE yeniden planlıyor, yani eklenti
silinmiş olsa bile görev kendini sonsuza kadar geri kuruyor. "Bir kez
çalıştır ve bayrak dik" yaklaşımı bu yüzden yetmemişti.

Halil'in istediği tek tuş **Zamanlanmış İşler** ekranına eklendi: kaç
hayalet kanca var, sıradaki çalışma zamanları, "Hayalet kancaları şimdi
sil" düğmesi ve son beş temizliğin geçmişi. Liste artık süzgeçle de
değiştirilebilir (`gbc_hayalet_cron_listesi`).

Snippet'teki iki kritik satır da taşındı — ziyaretçi tetiklemeli WP-Cron
kapalı kalıyor (`DISABLE_WP_CRON` + `remove_action('init','wp_cron')`).
Sunucuda gerçek cron zaten 5 dakikada bir çalışıyor; ikisi birden açıkken
kuyruğu tetikleyen ziyaretçi bekliyordu.

**2. Travelpayouts Link Üretici (snippet 30243 → modules/74-travelpayouts.php)**
Defter, link üretimi, trs otomatik bulucu, Impact program listesi ve
üretilen linkler tablosu birebir taşındı.

TAŞIRKEN DÜZELEN DUPLİKASYON: snippet anahtarları kendi seçeneklerinde
tutuyordu (`gbc_tp_token`, `gbc_tp_marker`, `gbc_ip_sid`, `gbc_ip_token`).
Aynı anahtarlar API Merkezi'nde de duruyordu ve ikisi birbirinden
habersizdi — API Merkezi'nde belirteci değiştirince link üretici hâlâ
eskisini kullanıyordu. Artık anahtarlar ÖNCE API Merkezi'nden okunuyor,
yoksa eski seçeneğe düşülüyor (hiçbir şey kaybolmaz). Ekrandaki dört
anahtar kutusu kaldırıldı; yerine "Anahtarlar API Merkezi'nde" kutusu ve
hangisinin eksik olduğunu gösteren satır kondu.

Ekran da yer değiştirdi: Ayarlar → GBC Travelpayouts DEĞİL, artık
GBC → Travelpayouts. Belirteçler MCP'ye açılmıyor; yalnız trs, üretilmiş
linkler ve Impact program listesi okunabilir.

**Sırada:** Ortaklık Durum Ekranı (30992, 20,7 KB) ve Ortaklık Kılavuzu
(30870, 51,9 KB). İkisi de salt okunur ekran; ayrı sürümde taşınacak.

## 29. Kaynak haritası — Kontrol Merkezi'ne eklendi (v1.13.1)

Halil'in sorusu: "Kontrol Merkezi'nde hangi kodlar çalışıyor, hangisi
WPCode snippet'i, hangilerini pasife almak lazım?"

Kontrol Merkezi → Kod sağlığı bölümünün altına **Kaynak haritası** geldi.
WPCode'da YAYINDA olan bütün snippet'ler listeleniyor ve her biri için
tek cümlelik karar yazılıyor:

- **ÖNCE BUNU KAPAT** (kırmızı) — snippet açık olduğu için eklenti modülü
  hiç yüklenmiyor. Bunlar sırayı tıkıyor.
- **pasife al** (sarı) — eklenti sürümü zaten çalışıyor, snippet boşuna
  duruyor.
- **kalsın** (yeşil) — eklentide karşılığı yok; ya da şablon CSS'i, ki onu
  eklenti zaten dosyaya çeviriyor.
- **bak** (kırmızı) — modül dosyası eksik ya da yükleyici işaretlememiş.

Üstte üç sayaç: yayındaki snippet, modülü bekleten, pasife alınabilir.
Satırlar iş gerektirene göre sıralı; ID sütunu doğrudan WPCode düzenleme
ekranına bağlanıyor.

Hayalet kanca özeti de Kontrol Merkezi'ne kondu: kaç tane var, hangileri,
ve Zamanlanmış İşler'deki silme düğmesine doğrudan bağlantı
(`#hayalet` çapası). Temizlik zaten her yönetici sayfasında kendiliğinden
çalışıyor; düğme elle tetiklemek içindir.

Eşleştirme `gbc_core_moduller()` içindeki `kaynak` ve `kapat` alanlarından
üretiliyor — yani yeni bir modül taşındığında harita kendiliğinden
güncelleniyor, ayrıca liste tutulmuyor.

## 30. Kaynak haritası genişletildi + hız eşikleri (v1.13.2)

**A) Kaynak haritası artık PASİF snippet'leri de gösteriyor.**
Sebep: asıl tehlikeli durum "snippet kapatılmış ama devralması gereken
modül de çalışmıyor" hâli — o zaman o iş sitede HİÇ yapılmıyor ve kimse
fark etmiyor. Yeni karar tipleri:

- **AÇIKTA** (kırmızı) — snippet kapalı, modül de çalışmıyor. Bu iş
  yapılmıyor.
- **ÖNCE BUNU KAPAT** (kırmızı) — snippet açık olduğu için modül hiç
  yüklenmiyor.
- **pasife al** (sarı) — eklenti sürümü çalışıyor, snippet boşuna duruyor.
- **devredildi** (yeşil) — snippet kapalı, işi eklenti yapıyor. Doğru hâl.
- **kalsın** / **pasif** — eklentide karşılığı yok.

Her satırda snippet'in WPCode durumu (açık/kapalı), kod türü, konumu ve
WPCode'un bildirdiği hata da yazılı. Beş sayaç: açık snippet, modülü
bekleten, pasife alınabilir, eklentiye devredilen, açıkta kalan iş.
Hayalet kanca özeti de aynı ekranda; silme düğmesine doğrudan bağlantı.

**B) Hız eşikleri — Halil'in verdiği değerler**
- CSS: 60 KB üstü sarı, 80 KB üstü kırmızı (sıkıştırılmış).
- HTML: artık SIKIŞTIRILMIŞ ölçülüyor (`Accept-Encoding: br, gzip`),
  150 KB üstü sarı, 250 KB üstü kırmızı. Açılmış boyut ikinci satırda.
- JS: `optm-js_defer` zaten 1 ise "Load JS Deferred aç" önerisi artık
  yazılmıyor; yerine "zaten açık, sıradaki iş şu" yazıyor.
- GA4 (gtag.js) JS toplamından ayrıldı: sütun GA4 hariç toplamı gösteriyor,
  gtag ayrı satırda. Bilerek duran bir dosya yüzünden kırmızı uyarı
  çıkmıyor.

**C) Kullanılmayan CSS — bizim / tema ayrımı**
LiteSpeed bütün CSS'i tek dosyada birleştirdiği için dosya adından ayırmak
mümkün değil; ayrım sınıf adının önekinden yapılıyor.
Bizim: `gbc- gz- gz2- v1- v11- tr- fb- yn-`. Geri kalan her şey
tema/çekirdek sayılıyor — yani temizlik listesine yanlışlıkla bizim
olmayan bir sınıf girmiyor. Ekranda iki satır: "Bizim şablonlarımız"
(temizlenecek yer burası) ve "Tema / çekirdek" (bize ait değil, silinmez).
Dosya dosya tabloda da her dosyanın bizim/tema dağılımı yazıyor.

## 31. Travelpayouts Link Üretici KALDIRILDI — link artık API'siz (v1.14.0)

Halil'in sorusu: "Link üreticiye gerek var mı? Ortaklık zaten otomatik
üretmeli."

**Ölçüm (ortaklık defteri, sayfa 30120 — 197 KB içerik tarandı):**
577 Travelpayouts bağlantısının HEPSİ tek kalıpta:

```
https://tp.media/r?campaign_id=<CID>&marker=767959&p=<P>&sub_id=<SUB>&trs=565047&u=<hedef>
```

marker tek (767959), trs tek (565047), program dört tane:

| Program | campaign_id | p | Defterdeki bağlantı |
|---|---|---|---|
| Booking.com | 84 | 2076 | 264 |
| GetYourGuide | 108 | 3965 | 168 |
| DiscoverCars | 117 | 3555 | 89 |
| Omio | 91 | 2078 | 55 |

**Sonuç: link üretmek için API'ye gerek yok.** Travelpayouts API'si yalnız
kısa biçimi (booking.tpx.li/xxxx) üretiyordu; uzun biçim birebir aynı işi
yapıyor ve zaten defterdeki 577 bağlantının kendisi.

Ayrıca ölçüldü: `gbc_tp_get()` sayfa basılırken HİÇ çalışmıyordu.
`modules/40-otel.php` onu yalnız `! shortcode_exists('gbc_aff')` iken
çağırıyor; `gbc_aff` kısa kodu ortaklık motorunda koşulsuz kayıtlı, yani
o dal hiç girilmiyor. Defter (30120) tek kaynak.

**Yapılan:**
- `modules/71-ortaklik-motoru.php` içine API'siz üreteç:
  `gbc_aff_tp_uret( $hedef, $sub_id )`. Ağ isteği yok, belirteç yok, kota
  yok. Program tablosu süzgeçle genişletilebilir
  (`gbc_aff_tp_programlar`). Sorgu dizisi elle kuruluyor —
  `add_query_arg` değerleri ikinci kez kodlayıp hedef adresi bozuyordu.
- Üretilen bağlantı, defterden alınan iki gerçek satırla bire bir
  karşılaştırılarak doğrulandı (Booking/Atina ve DiscoverCars/Atina).
- `gbc_tp_get()` okuyucusu da 71'e taşındı; `gbc_tp_links` seçeneği
  (57 kayıt) yerinde, silinmedi.
- `modules/74-travelpayouts.php` arşive alındı, menüden çıkarıldı.
  Impact program listesi de gitti; Impact bağlantısını API Merkezi'nin
  kendi testi zaten doğruluyor.

## 32. Önbellek kalıntısı tespiti (v1.14.0)

Halil iki snippet'i pasife aldı ama Kod sağlığı hâlâ "WPCode sürümü
çalışıyor" diyordu. Sebep: fonksiyon bellekte tanımlı olduğu için yükleyici
snippet'in hâlâ açık olduğunu sanıyordu. Oysa WPCode tarafında ikisi de
kapalı — kod, WPCode'un kendi snippet önbelleğinden (Redis nesne
önbelleğinde) geliyordu.

Yükleyici artık ayrım yapıyor: fonksiyon tanımlı VE snippet yayında ise
"WPCode sürümü çalışıyor"; fonksiyon tanımlı ama snippet KAPALI ise yeni
durum **`kalinti`** — "önbellek kalıntısı: LiteSpeed → Purge All ve nesne
önbelleğini temizle". Snippet durum sorgusu yalnız yönetici tarafında
çalışıyor, ziyaretçi isteğine hiç maliyeti yok. Dosya yine yüklenmiyor
(çift tanım ölümcül hatası olmasın diye), ama artık sebep doğru yazılıyor
ve Kod sağlığı bunu sorun olarak sayıyor.

---

## 33. İş Ortaklığı paneli — tek ekran, altı sekme (v1.15.0)

Eski `İş Ortaklığı Denetimi` ekranı (menü `gbc-aff-denetim`) menüden kaldırıldı;
o adrese gelen istekler yeni panele yönlendiriliyor. Yeni dosya:
`inc/ortaklik.php`, menüde **GBC → İş Ortaklığı** (`gbc-ortaklik`).

Modül `modules/70-ortaklik-denetim.php` **pasife alınmıyor**: tarama
(`gbc_aff_panel_tarama`), tıklama sayacı (`gbc_aff_tik_yaz`, `gbc_aff_tik_son`)
ve bağlantı testi hâlâ oradan geliyor, yeni panel onları çağırıyor.

### Sekmeler

| Sekme | Ne yapar |
|---|---|
| Genel Bakış | Sayılar, dikkat isteyen kayıtlar, GA4 karşılaştırması, ana şalter, yedekler |
| Bağlantılar | 667 kaydın tamamı; satır satır düzenle / sil / ekle, süzgeç ve arama |
| Programlar | Hangi ağ hangi alan adına izin veriyor; komisyon, çerez, onay alanları; karar tablosu |
| Sayfalar | Hangi sayfada hangi bağlantı var; ortaklığı hiç olmayan gezi yazıları |
| Üret | Hedef adresten bağlantı üretir; toplu üretim; kısa bağlantı çoğaltma |
| Kısa Kodlar | Kayıtlı GBC kısa kodları (WordPress'ten okunur), bozulmaz kurallar, GA4 ölçümü |

### Defter yazma yeniden açıldı — ama başka türlü

21 Eylül 2026'da `gbc_aff_panel_kaydet()` kapatılmıştı. **Gerekçesi geçerliydi:**
eski panel defterin TAMAMINI tek textarea'dan üzerine yazıyordu, bir hata bütün
defteri siliyordu. O yol hâlâ kapalı ve öyle kalacak.

Yeni yazma yolu üç noktada farklı:

1. **Satır kapsamlı.** `gbc_ort_defter_guncelle/ekle/sil` yalnız o kimliğin
   satırına dokunur; blok içindeki yorum satırları, `TAKMA` satırları ve diğer
   666 kayıt karaktere kadar aynı kalır (canlı veriyle doğrulandı).
2. **Yedekli.** Her yazmadan önce sayfanın TAMAMI `gbc_aff_defter_yedek`
   seçeneğine kopyalanır (son 10 kopya) + WordPress revizyonu. Genel Bakış'tan
   tek tuşla geri dönülür; geri alma da geri alınabilir.
3. **Frenli.** Defter bir hamlede %40'tan fazla küçülemez; küçülüyorsa işlem
   durdurulur ve hiçbir şey yazılmaz.

### Defter beş değil iki blokta

Ölçüm (28 Eylül 2026): sayfada 5 `<pre>` bloğu var, bağlantılar **ikisine
yayılmış** — blok 2'de 349, blok 4'te 318, toplam **667**. Eski panelin
"en büyük bloğu al" yaklaşımı kayıtların yarısını görmüyordu. Yeni okuyucu
bütün blokları tarar, her satır hangi bloktan geldiğini yanında taşır, yazma
işlemi doğru bloğa gider.

### Bağlantı üretimi — üç yol, hiçbiri API istemiyor

1. **Travelpayouts motoru** (`gbc_aff_tp_uret`) — Booking, GetYourGuide,
   DiscoverCars, Omio. Kesin yol.
2. **Defterden öğrenilen şablon** — gerçek bağlantılardan hedef `%HEDEF%`,
   etiket `%SUB%` yer tutucusuna çevrilerek kalıp çıkarılır. Ferryhopper'ın
   doğrudan ortaklığı da böyle: `aff_uid=gzgbr&utm_source=affiliate-link&utm_medium=in-house`
   parametreleri defterden ölçüldü.
3. **Kısa bağlantı çoğaltma** — Skyscanner (Impact) ve Yesim hedefi bağlantıda
   taşımıyor, ağ her hedef için ayrı kod üretiyor (`skyscanner.pxf.io/E0YrMP`).
   Bu kodu sıfırdan kuramayız; ama var olan bir kısa bağlantıyı yeni bir
   etiketle çoğaltmak tamamen otomatik. Panel 10 Skyscanner + 1 Yesim kısa
   bağlantısını defterden çıkarıp listeliyor.

Doğrulama: her programdan bir kayıt alınıp hedefi çıkarıldı, yeniden üretildi
ve defterdeki satırla parametre parametre karşılaştırıldı — altısı da birebir aynı.

### Program tablosu

Tohum listede 7 program var (alan adları, ağ, not). Komisyon / çerez / onay
alanları `gbc_aff_program_ayar` seçeneğinde tutuluyor ve **boş bırakıldı** —
Travelpayouts ve Impact panellerinden doldurulacak. En az iki program dolunca
"aynı hedef iki ağda: hangisi?" karar tablosu kendiliğinden çıkıyor.

### GA4

`gbc_km_ga4()` üzerinden `outbound_click` olay sayısı çekiliyor (28 gün, 12 saat
önbellek). Bağlantı bazlı kırılım için `customEvent:aff_id` deneniyor; özel
boyut tanımlı değilse panel bunu açıkça söylüyor. Özel boyut tanımı GA4
yönetiminde yapılır, siteden yapılamaz.

### Ölçülen dağılım (28 Eylül 2026)

| Program | Ağ | Bağlantı |
|---|---|---|
| Booking.com | Travelpayouts | 264 |
| GetYourGuide | Travelpayouts | 168 |
| DiscoverCars | Travelpayouts | 89 |
| Ferryhopper | Doğrudan | 75 |
| Omio | Travelpayouts | 55 |
| Skyscanner | Impact | 13 |
| Yesim | Travelpayouts | 3 |
| **Toplam** | | **667** |

Sabit tarih içeren bağlantı: 0. Adresi boş kayıt: 0.
Skyscanner'ın 13 bağlantısının 13'ünde de `subId1` etiketi **var** — mockup'taki
"etiketsiz" notu yanlıştı, canlı ölçüm düzeltti.

### Testler

`ortaklik_testi.php` (73 kontrol) — çok bloklu okuma, satır kapsamlı yazma,
güvenlik freni, şablon öğrenme, üretim, ekran çizimi, `<tr>` içinde `<form>`
kontrolü.
`gercek_defter_testi.php` (32 kontrol) — 30120 sayfasının **gerçek içeriğiyle**:
667 kayıt, iki blok, altı programın yeniden üretimi, iki bloktan da yazma,
667 kayıtla ekran çizimi (en yavaş sekme 31 ms).

---

## 34. Açık sorunların kökü bulundu — dördü yanlış alarmmış (v1.15.1)

Kontrol Merkezi'nde 11 açık sorun vardı. Canlı sayfalar tek tek ölçüldü;
**ikisi gerçek bir okuma hatası, dördü kendi kodumuzun yanlış alarmıydı,
biri de bilerek konmuş bir kuralın yanlış raporlanmasıydı.**

### 34.1 Hız paneli hiçbir şey ölçemiyordu (GERÇEK)

Bütün şablon satırlarında `cURL error 61: Unrecognized content encoding type`.
Sebep: `gbc_hz_sayfa_olc()` `Accept-Encoding: br, gzip, deflate` gönderiyordu.
libcurl brotli açamıyor; sunucu br dönünce curl gövdeyi çözmeye çalışıp
patlıyordu. Panel de buna rağmen **"Ölçülen her şey eşiklerin içinde"**
yazıyordu — yani hiçbir ölçüm yokken yeşil yanıyordu.

Düzeltme:
- Gövdesi okunacak istek artık `gzip, deflate` istiyor.
- Brotli boyutu AYRI bir istekte, `decompress => false` ile ölçülüyor:
  gövde hiç açılmıyor, `Content-Length` okunuyor, 61 hatası çıkmıyor.
- Hüküm üçe ayrıldı: hepsi ölçülemediyse **"Ölçülemedi"**, bir kısmı
  ölçüldüyse **"Kısmen ölçüldü"**, ancak hepsi ölçülünce yeşil.

### 34.2 Kritik CSS yanlış anahtardan okunuyordu (GERÇEK)

Panel `optm-ccss_gen`'e bakıp "Kritik CSS Açık" diyordu. Oysa kritik CSS'in
sayfaya UYGULANMASI `optm-css_async`'e bağlı ve canlıda o **0**. Yani üretilen
kritik CSS hiç kullanılmıyordu.

Düzeltme: belirleyici satır artık `optm-css_async` ("Kritik CSS uygulanıyor
mu", yüksek önem), `optm-ccss_gen` ayrı bir satır ("Kritik CSS üretimi", orta).
`optm-css_async` tek-tıkla-düzelt listesine de eklendi.

### 34.3 "Ekran açılmıyor: durum.php / tasarim.php" (YANLIŞ ALARM)

`gbc_core_durum_ekran()` ve `gbc_tasarim_yol()` yerli yerinde. Sorun tarayıcıda:
bu iki dosya `gbc-core.php` içinde `is_admin()` kapısının arkasında yükleniyor,
tarama ise cron'dan da koşuyor. Cron'da dosyalar yüklenmediği için fonksiyonlar
"tanımsız" görünüyordu.

Düzeltme: `gbc_kod_sadece_admin_ekranlar()` eklendi; cron taramasında bu iki
dosya yalnız varlık ve sözdizimi düzeyinde denetleniyor, fonksiyon aranmıyor.

### 34.4 "5 sınıf adı kayboldu" — ana sayfa (YANLIŞ ALARM)

Kayıp denen adlar `gbc-css-23533, gbc-css-customizer, gbc-in-et-stil,
gbc-kur-stil, gbc-vp-css` — hepsi `<style>` / `<link>` **tutamağı**, içerik
işareti değil. Satır içi stiller koşullu basılıyor, LiteSpeed CSS birleştirmesi
de bunları toplayıp id'lerini siliyor.

Düzeltme: `gbc_nk_izleri_topla()` artık `<style>`, `<link>`, `<script>` ve
`<noscript>` etiketlerinin id'lerini saymıyor. Gövdedeki sınıf adları
(asıl izler) aynen toplanıyor.

### 34.5 "Sayfa küçüldü: 335 KB → 142 KB" (YANLIŞ ALARM)

Ana sayfa canlıda ölçüldü: 132 KB, 10 h2, 158 bağlantı, altbilgi yerinde,
JSON-LD yerinde. Sayfa sağlam; küçülme sıkıştırma ve bizim kendi
optimizasyonlarımızdan.

Düzeltme: küçülme TEK BAŞINA alarm açmıyor. Alarm ancak küçülmeye **bir motorun
susması ya da ikiden çok sınıf kaybı** eşlik ederse açılıyor; yoksa taban
sessizce yenileniyor.

### 34.6 İçindekiler 7 sayfada yok — ama BİLEREK (KURAL, alarm değil)

Canlı ölçüm (28 Eylül 2026):

| Sayfa | h2 | id'li h2 | v1-lejant | İçindekiler |
|---|---|---|---|---|
| /italyanca-kelimeler/ | 23 | 0 | 19 | yok |
| /bolonya-bologna-gezilecek-yerler/ | 29 | 0 | 27 | yok |
| /ipsala-sinir-kapisi/ | 29 | 0 | 23 | yok |
| /macaristan-vize-rehberi/ | 25 | 0 | 19 | yok |
| /atina-nerede-kalinir/ | 22 | 0 | 17 | yok |
| **/budapeste-gezi-rehberi/** | 18 | **16** | **0** | **var** |

Desen tek: `v1-lejant` olan her sayfada kutu yok, olmayanda var. Sebep
`gbc_toc_uygula()` içindeki `if ( strpos( $html, 'v1-lejant' ) ) return;`
satırı — eski JS sürümünden devralınmış **kasıtlı** bir kural.

Düzeltme (davranış DEĞİŞMEDİ, raporlama düzeldi):
- Kural `gbc_toc_lejant_atla()` fonksiyonuna alındı, varsayılan açık.
  Bu şablonlarda da kutu istenirse:
  `add_filter( 'gbc_toc_lejant_atla', '__return_false' );`
- Motor tanımına `istisna_desen` alanı eklendi. Kontrol Merkezi bu sayfalarda
  İçindekiler'i artık **"eksik"** değil **"gerekmez"** sayıyor.

### 34.7 Geç tampon — başlıkları the_content dışında basan şablonlar

v1 şablonunda başlıklar ACF alanlarından şablonun kendisi tarafından basılıyor,
`the_content`'e hiç uğramıyorlar. Sunucu tarafı İçindekiler onları göremiyordu
(eski JS sürümü tarayıcıda çalıştığı için görüyordu).

`gbc_toc_sayfa_tamponu()` eklendi: `the_content`'te kutu basılamadıysa sayfanın
tamamına bakar — ama YALNIZ `</h1>` ile altbilgi arasındaki ana içerik
bölgesinde, böylece menü ve altbilgi başlıkları listeye girmez. LiteSpeed varsa
`litespeed_buffer_finalize` 90'a bağlanır (head temizliğinden önce), yoksa
`ob_start`. Lejant istisnasına saygı duyar, yani bugün hiçbir sayfanın çıktısını
değiştirmez; kural kapatıldığı anda devreye girer.

### Testler

`toc_v1_testi.php` (24 kontrol) — lejant istisnası, geç tampon, altbilgi
başlıklarının listeye girmemesi, çiftlenme, mevcut id'lerin korunması,
190 KB sayfada 1 ms.
`hiz_kodlama_testi.php` (27 kontrol) — br isteyen her çağrının
`decompress => false` ile olması, "Ölçülemedi" hükmü, css_async okuması,
cron yanlış alarmı, iz toplayıcının varlık tutamaklarını saymaması.

Toplam takım: **546 kontrol, hepsi geçiyor.**

---

## 35. Bağlantı sağlığı + program kuralları (v1.16.0)

### 35.1 "Bu link çalışıyor mu" — kesin cevap

Yeni bölüm `inc/ortaklik.php` §11. Seçenek `gbc_aff_saglik`:
`id => { durum, kod, not, zaman, son_iyi }`.

**Parti parti çalışır.** Defterde 667 bağlantı var; hepsini tek sayfa
açılışında denemek zaman aşımına düşer. Her turda EN ESKİ denenmiş N
bağlantı sınanır, sıra kendiliğinden döner. Günlük cron
`gbc_ort_saglik_cron` sabah 06:10'da 60 tane yapıyor (günlük kontrolle
çakışmasın diye 05:40 değil). Panelden elle 10-200 arası seçilebiliyor.

**403 ve 429 ÖLÜ SAYILMAZ.** 28 Eylül 2026'da ölçüldü: ortaklık ağları
(tp.media, pxf.io) sunucudan gelen isteği bot sanıp reddediyor, aynı
bağlantı tarayıcıda açılıyor. Hükümler:

| HTTP | Hüküm | Anlamı |
|---|---|---|
| 2xx, 3xx | çalışıyor | sağlam |
| 403, 429 | ağ engeli | sağlam sayılır, ağ botu engelliyor |
| 404, 410 | **ÖLÜ** | gerçek arıza, acil |
| 5xx, bağlantı hatası | ulaşılamadı | karşı taraf geçici sorunlu |

`son_iyi` ayrı tutuluyor: bugün engel yese de "en son ne zaman çalıştı"
bilgisi kaybolmuyor. Ölü bağlantı çıkarsa günlük deftere yüksek öncelikli
sorun açılıyor, düzelince kendiliğinden kapanıyor. Bağlantılar sekmesinde
"Çalışıyor mu" sütunu ve iki yeni süzgeç: **ÖLÜ bağlantı**, **hiç denenmemiş**.

### 35.2 Program verileri araştırıldı ve dolduruldu (28-29 Eylül 2026)

Tohum tablodaki her satırın kaynağı ve kaynak sayfanın güncelleme tarihi
kodda duruyor. **Bulunamayan değer boş bırakıldı, hiçbir rakam uydurulmadı.**
Panelden elle girilen değer tohumu ezer.

| Program | Ağ | Komisyon | Çerez | Onay |
|---|---|---|---|---|
| Booking.com | Travelpayouts | %5 otel / 1,5 € uçuş / %5-3 araç | **tek oturum** | 60-90 gün (check-out sonrası) |
| GetYourGuide | Travelpayouts | %8 | 31 gün | tur tarihi sonrası |
| DiscoverCars | Travelpayouts | %60 + %25 | **365 gün** | araç teslimi sonrası |
| Omio | Travelpayouts | %6 | 30 gün | bulunamadı |
| Yesim | Travelpayouts | **%18** | 90 gün | bulunamadı |
| Skyscanner | Impact | değişken, yayınlanmıyor | 30 gün | bulunamadı |
| Ferryhopper | Doğrudan | bulunamadı | bulunamadı | bulunamadı |

Çözülmemiş çelişkiler kodda `uyari` alanında yazılı:
- **DiscoverCars** kendi sayfasında %70 + %30 diyor, Travelpayouts %60 + %25.
- **Ferryhopper** şartları kamuya açık değil, sadece başvuru formunda onaylanıyor.
- **Skyscanner** oranı yalnız Impact sözleşme ekranında görünür; internetteki
  %50 / %20 / %5 / %1,8 rakamlarının hepsi çelişiyor, hiçbiri resmi değil.

### 35.3 Hangi programı nerede paylaşabilirim — izin matrisi

Yeni `gbc_ort_mecralar()`: site, YouTube, Instagram, TikTok, Pinterest,
Facebook, e-posta, WhatsApp/Telegram, marka üstüne reklam. Program × mecra
matrisi, dört değer: izinli / YASAK / şartlı / bilinmiyor.

**EN ÖNEMLİ BULGU — Booking.com sosyal medyayı yasaklıyor.** Program
sayfasında birebir şöyle yazıyor: *"No Social Media (unless explicitly
approved by the Booking.com team)"*. Yani Booking bağlantısı YouTube,
Instagram, TikTok, Pinterest, Facebook ve WhatsApp gruplarında
paylaşılamaz. Doğru yol: sosyalde gezginbirchef.com'a yönlendirmek,
Booking bağlantısını site üzerinden vermek. Defterdeki 264 Booking
bağlantısı sitede olduğu sürece sorun yok.

**Skyscanner'da iki ayrı program var.** Normal Affiliate Programme web
sitesi ve ayda 5.000+ tekil ziyaretçi istiyor. Sosyal kanallar için ayrı
**Creator Programme** (1.000+ takipçi) — YouTube açıklama linki ve
link-in-bio bu kapsamda uygun. Halil'in durumunda ikisine de başvurmak
gerekiyor: site için Affiliate, kanallar için Creator.

**Yesim** en serbest olanı: Travelpayouts sözleşmesi web sitesi zorunluluğu
getirmiyor, sosyal medya serbest (platformun kendi kuralına uymak şartıyla).
Komisyonu da en yüksek: %18.

**Marka üstüne reklam** hiçbir programda izinli değil; Skyscanner ve
GetYourGuide'da sözleşmeyle açıkça yasak.

Kuralı bilinen program, henüz hiç bağlantı verilmemiş olsa da matriste
görünür — "nerede paylaşabilirim" sorusunun cevabı kullanımdan önce lazım.

### 35.4 Test tezgâhı düzeltmesi

`wpstub.php` içindeki `update_option` hiçbir şey yazmıyordu (`return true`),
bu yüzden seçeneğe yazıp geri okuyan hiçbir şey test edilemiyordu. Artık
`$GLOBALS['gbc_stub_options']` içine gerçekten yazıyor; `delete_option` ve
`add_option` da öyle. Bu değişiklikten sonra bütün eski testler yeniden
koşturuldu, hiçbiri kırılmadı.

### Testler

`ortaklik_saglik_testi.php` (71 kontrol) — 12 HTTP kodunun hükmü, parti
taraması, hücre çizimi, yedi programın araştırma verisi, 7×9 izin matrisi,
altı sekmenin çizimi.
`gercek_defter_testi.php` (55 kontrol) — 667 kayıtlık gerçek defterle sağlık
taraması, sıranın dönmesi, özet sayıları, ÖLÜ ve "hiç denenmemiş" süzgeçleri.

Toplam takım: **630 kontrol, hepsi geçiyor.**

---

## 36. CJ Affiliate desteği — kanal bazlı kazanç takibi (v1.17.0)

Kaynak: **CJ New Publisher Welcome Kit 2026** (Halil'in yüklediği PDF, 67 sayfa).
Kodda kullanılan her şeyin slayt numarası yazılı.

### 36.1 CJ bağlantı anatomisi (slayt 24)

```
https://www.jdoqocy.com/click-9265128-10463300?sid=TEST&url=https%3A%2F%2F...
                        ^^^^^ ^^^^^^^ ^^^^^^^^  ^^^      ^^^
                        yol   PID     AID       SID      hedef
```

- **PID** = Promotional Property ID, **her mecranın ayrı PID'i var**
- **AID** = bağlantının kimliği, advertiser'a ait, değişmez
- **SID** = kendi etiketin, serbest
- **url=** = hedef adres

CJ takip sunucusu tek alan adı değil, dönüşümlü kullanıyor: jdoqocy.com,
dpbolvw.net, tkqlhce.com, anrdoezrs.net, kqzyfj.com, ftjcfx.com, awltovhc.com,
lduhtrp.net, tqlkg.com, gopjn.com, emjcd.com. Hepsi `gbc_ort_cj_alanlar()`
içinde, filtreyle genişletilebilir.

### 36.2 Asıl kazanç: aynı hedef, kanala göre ayrı bağlantı

`gbc_ort_cj_uret( $url, $pid, $sid )` — bir CJ bağlantısının **AID'ini ve
hedefini aynen koruyup yalnız PID'ini değiştirir**. Böylece aynı otel
bağlantısı site için ayrı, YouTube için ayrı, Instagram için ayrı üretilir ve
CJ raporunda **hangi kanalın kazandırdığı ayrı ayrı görünür**. Halil CJ'de
her kanalı ayrı promotional property olarak tanımlamıştı (27 Eylül 2026);
bu bölüm o kurulumun karşılığı.

Üret sekmesinde "Bir CJ bağlantısını bütün kanallar için çoğalt" kutusu:
bağlantıyı yapıştır → her mülk için ayrı bağlantı çıkar.

### 36.3 Mülk (PID) defteri

Seçenek `gbc_aff_cj_mulk`. On bir mülk tohumda hazır: site, YouTube Gezi,
YouTube Yemek Tarifleri, Instagram, TikTok, Pinterest, Facebook, Living Greece
(YouTube / Instagram / TikTok), e-posta bülteni. PID'ler **boş** — CJ'de
*Account → Promotional Properties* sayfasından kopyalanacak. Kullanıcı yeni
mülk de ekleyebiliyor.

### 36.4 İzin matrisiyle bağlantı kuruldu

Her mülkün bir mecrası var (site / youtube / instagram / …). Çoğaltma tablosu
her satırda o kanalın O PROGRAMDA izinli olup olmadığını gösteriyor ve
**yasak olan kanal için bağlantı hiç üretilmiyor**. Yani Booking bağlantısını
YouTube mülküyle çoğaltmaya çalışırsan tabloda "YASAK" yazıp boş bırakıyor —
35.3'teki Booking sosyal medya yasağı burada fiilen uygulanıyor.

### 36.5 Ödeme takvimi (slayt 28 ve 41)

| Durum | Ne demek |
|---|---|
| New | İşlem CJ sunucusuna düştü, kesinleşmedi |
| Extended | Advertiser bir kez, bir ay uzatma hakkını kullandı |
| Locked | İnceleme bitti, değiştirilemez. Genelde ayın 10'unda |
| Closed | Ödenecek. 11'inde kapananlar ~16'sında, 22'sinde kapananlar ~28'inde |

Türkiye notu (slayt 42-43): CJ doğrudan banka havalesini 39 ülkede
destekliyor; listede olmayan ülkeler **Payoneer** kullanıyor. PayPal ve kredi
kartına ödeme YOK. Hesabın *functional currency*'si bir kez seçiliyor ve
**geri alınamıyor**; farklı para biriminden gelen satışlarda CJ **%3 dönüşüm
farkı** uyguluyor.

### 36.6 Ağ tanıma güncellendi

`gbc_ort_ag()` artık dört ağ ayırt ediyor: Travelpayouts, Impact, **CJ
Affiliate**, Doğrudan. CJ bağlantıları "kısa bağlantı" sayılmıyor — kendi
çözücüleri var. Impact tespitinden `sjv.io` çıkarıldı (o da CJ'nin alanı).

### Testler

`cj_testi.php` (69 kontrol) — kitin slayt 24'teki **gerçek örneğiyle**
çözümleme, on bir CJ alan adı, PID değiştirip AID ve hedefi koruma, çift
kodlama kontrolü, mülk defteri, mülk × program izni, çoğaltma ekranı,
defterde CJ satırı.

Toplam takım: **699 kontrol, hepsi geçiyor.**

---

## 37. Kontrol sisteminin kendisi onarıldı (v1.18.0)

57 açık sorunun 53'ü yanlış alarmdı. Sebep tek bir eksikti ve kodda bulundu.

### 37.1 KÖK NEDEN — nöbetçi sorunları hiç kapanmıyordu

`gbc_sorun_temizle()` güvenlik, hız, ga4 ve kod alanları için çağrılıyordu.
**`nobetci` alanı için HİÇ çağrılmıyordu.** Açılan her `nk-*` sorunu
sonsuza kadar açık kalıyordu — sayfa düzelse bile.

Alan geneli temizlik burada yanlış olurdu: nöbetçi her turda sayfaların
yalnız bir kısmını tarar, alan süpürülürse taranmamış sayfaların GERÇEK
sorunları da kapanır. Çözüm **sayfa bazlı temizlik**:

`gbc_sorun_sayfa_temizle( $pid, $bulunanlar )` — bir sayfa hatasız
tarandığında, o sayfaya ait olup bu turda bulunmayan `nk-*-<pid>`
sorunları kapanır. Başka sayfaya, başka alana dokunulmaz. Sayfa
indirilemediyse hiçbir şey kapanmaz: **bilgi yok, sorun yok demek değildir.**

### 37.2 İkinci hata — aynı saniyede kapanamıyordu

`gbc_sorun_temizle()` içinde `son_gorulme >= tur_zamani ise dokunma`
koşulu vardı. Tur bir saniyeden kısa sürerse HİÇBİR ŞEY kapanmıyordu.
`$bulunanlar` listesi neyin açık kalacağını zaten söylüyor; zaman koşulu
kaldırıldı. Bu dört alanı birden etkiliyordu.

### 37.3 B1 — kural sürümü damgası

Her sorun hangi kuralla açıldığını yanında taşıyor (`ks` =
eklenti sürümü / Kontrol Merkezi kural sürümü). Kural değişince
`gbc_sorun_kural_supur()` eski damgalı sorunları
"kural değişti (X → Y); yeniden değerlendirilecek" notuyla kapatıyor ve
bütün sayfaları yeniden tarama kuyruğuna alıyor. Hâlâ varsa yeniden açılır.

### 37.4 B2 — "Şimdi kontrol et" gerçekten tarıyor

İki değişiklik:
- `gbc_nk_sira_al()` artık **açık sorunu olan sayfaları sıranın en başına**
  alıyor (`gbc_nk_sorunlu_kodlar()`). Sorun düzeldiyse en çabuk orada anlaşılır.
- Elle basıldığında sayfa sayısı normal turun **üç katı**
  (`gbc_gunluk_calistir( 3 )`). Ekran kaç sayfa indirildiğini, kaç sorun
  kapandığını ve kaç sayfanın hâlâ sırada beklediğini yazıyor.

### 37.5 B3 — "Tümünü yeniden tara" sayacı

Sayı iki ayrı yerden okunuyordu: turun eski raporundan ve canlı kayıtlardan.
Düğmeye basınca ekranda eski sayı kalıyordu. `gbc_nk_bekleyen_sayisi()`
eklendi, tek yerden ve her seferinde canlı hesaplanıyor; düğme kaç satır
işaretlediğini söylüyor.

### 37.6 B4 — imza denetimi (A1'i bulan test)

`gbc_kod_imza_denetle()` dosyayı PHP belirteçlerine ayırıp imza
fonksiyonunun **gerçekten tanımlı** olup olmadığına bakıyor. Yorum
içindeki `function x()` metni sayılmıyor.

**A1 böyle bulundu:** `gbc-core.php` 70. satırda modül 70'in imzası
`gbc_aff_panel_menu` idi; o fonksiyon v1.15.0'da yorum bloğunun içine
alınmıştı. Kod sağlığı "modül çalışmıyor" diyordu, oysa modül çalışıyordu.
İmza `gbc_aff_tik_yaz`'a çevrildi.

**Ön şart canlıda doğrulandı:** `gbc_aff_tik_yaz` başka hiçbir yerde
tanımlı değil. Aktif 32 PHP snippet'i listelendi, 30992 ve 30870 tek tek
okundu — ikisi de bu fonksiyonu tanımlamıyor. Yükleyici modülü atlamayacak,
tıklama sayacı düşmeyecek.

Test 20 imzalı modülün hepsini denetliyor; hiçbiri kırık değil.

### 37.7 B5 — sınıf izi karşılaştırması

- Taban zaten ziyaretçi ve optimize edilmiş sayfadan alınıyordu
  (`gbc_nk_indir`: çerezsiz, önbellek atlatılmıyor) — değişiklik gerekmedi.
- `gbc_nk_kosullu_izler()` eklendi: içeriğe göre bilerek bazen basılmayan
  sınıflar kayıp sayılmıyor — `gbc-yt-onyukle` (videosuz sayfa),
  `gbc-toc` ve `gbc-b/c/h/t/x` (v1-lejant istisnası), `gbc-kur-`, `gbc-vp-`,
  `gbc-ad-`, `gbc-gal-`, `gbc-affk`, `gbc-related-`, `gbc-sor-`.
- Sınıf kaybı **tek başına alarm değil**. Aynı sayfada bir motor da
  sustuysa alarm açılıyor; susmadıysa yalnız sayıya giriyor.

### 37.8 B6 — kabul edilenler

Seçenek `gbc_kabul`. Her kararın tarihi, gerekçesi ve kararı vereni
yazılıyor. Kabul edilen sorun **açık sorun sayısından çıkıyor ama
kaybolmuyor** — ayrı listede duruyor, tek tuşla geri alınabiliyor.

Ölçü bağlanabiliyor: `array( 'ad' => 'JS', 'deger' => 176000, 'yon' =>
'artarsa', 'pay' => 1.15 )`. Değer %15'ten fazla kötüleşirse
**kabul kendiliğinden düşüyor** ve sorun yeniden açılıyor.
Gerekçesiz kabul edilmiyor.

### 37.9 B8 — kontrolün kendisi

`gbc_sistem_denetle()` üç şeye bakıyor: günlük tur hiç çalışmış mı,
24 saatten eski mi, zamanlanmış iş kurulu mu. Üçü de yüksek öncelikli.
Hem cron'da hem yönetici ekranı açılışında koşuyor — cron hiç
çalışmıyorsa alarm yalnız cron içinde açılırsa asla görünmez.

Ekranda ayrıca: veri hiç yoksa **"0 açık sorun SORUN YOK demek değil,
BİLGİ YOK demektir"**, veri eskiyse "bu sayılar o günün fotoğrafı".

### 37.10 Kapanan sorunlar günlüğü

Sorunlar artık sessizce kaybolmuyor. `gbc_sorun_kapanan` (son 200 kayıt)
her kapanışın sebebini tutuyor: "sayfa yeniden tarandı", "kural değişti",
"kabul edildi: <gerekçe>". Günlük Kontrol ekranında son 25 tanesi görünüyor.

### 37.11 B7 — Uçuş perdesi: karar verildi

152 sayfanın hiçbirinde çıkmıyordu. İki ihtimal vardı: bozuk ya da gereksiz.

**Canlı ölçüm (29 Eylül 2026, /budapeste-gezi-rehberi/):**
`widgets.skyscanner.net` sayfada **hiç geçmiyor**. 1,68 MB'lık loader.js
şablondan kaldırılmış. Sayfada "skyscanner" yalnız 3 kez geçiyor, o da
ortaklık bağlantıları.

**Sonuç: perde bozuk değil, yapacak işi kalmamış.** Motor `na`'dan
çıkarılıp `kosul => sky_widget` yapıldı: yalnız widget'ı olan sayfada
beklenir. Şu an hiçbiri yok, bu yüzden her yerde "gerekmez" diyor.
Widget geri gelirse koşul sağlanır ve motor kendiliğinden denetlenir.

Not: perdenin `the_content` 25'e bağlı olması ayrı bir tuzaktı — betik
şablon snippet'inden basılıyor, `the_content`'e hiç uğramıyor. İçindekiler'le
aynı sınıf hata (DEFTER 34.7). Widget geri gelirse perde bu yüzden yine
çalışmayacak; o zaman geç tampon yöntemi uygulanmalı.

### 37.12 v1.15.0 regresyonu — yetim menüler

`gbc-aff-denetim` menüsü v1.15.0'da kaldırıldı. Ama canlıda **iki aktif
snippet** alt sayfasını hâlâ oraya asıyor: 30992 (Ortaklık Durum) ve
30870 (Ortaklık Kılavuzu). Üst menü olmayınca ikisi de menüde görünmez
olmuştu.

`gbc_ort_eski_menu_koprusu()` eklendi (`admin_menu` 999): snippet'lerin
astığı alt sayfaları GBC menüsüne taşıyor. Snippet'lere dokunulmuyor.

### Testler

`kontrol_sistemi_testi.php` (60 kontrol) — sayfa bazlı temizliğin başka
sayfaya ve başka alana dokunmaması, kural sürümü süpürmesi, kabul
mekanizması (ölçü kötüleşince düşmesi, iyileşince durması), öz denetim,
koşullu izler, sorunlu sayfaların sıraya alınması ve **kasıtlı deneme**:
motor susunca sorun açılıyor, dönünce kendiliğinden kapanıyor.

`imza_testi.php` (12 kontrol) — yorum içindeki fonksiyonun tanımlı
sayılmaması, A1 hatasının yakalanması, 20 modül imzasının denetimi.

Toplam takım: **772 kontrol, hepsi geçiyor.**

### Yapılmayanlar ve sebepleri

- **A4 (Kritik CSS)** — canlıda açılıp 8 şablonda PSI ile ölçülmesi
  gerekiyor. Düşük trafikli saatte ve Halil'in onayıyla yapılacak;
  kendi başıma canlı ayar değiştirmedim.
- **A6** — politika ve lisans sayfalarında İçindekiler/ortaklık bildirimi
  kuralı Halil'in kararı.
- **B7'nin kalan 9 motoru** — otel, rota, feribot, Yunanistan, bölüm
  medyası, kart video, galeri lisansı, AdSense, Şefe Sor. Canlı dağılıma
  bakılıp kural önerilecek, karar Halil'in.

---

## 38. Kalan üç alarmın incelenmesi (v1.18.1)

v1.18.0'dan sonra açık sorun 57'den **3**'e indi ve üçü de Hız alanında.
Üçü tek tek canlıda incelendi.

### 38.1 "CSS 252 KB" — ÖLÇÜM HATASIYDI

Panelin dosya dosya tablosu sekiz CSS dosyası gösteriyordu: yedisi
**40-52 KB**, biri **252 KB**. Ama sınıf sayıları (1009-1093) ve ölü sınıf
örnekleri birebir aynıydı — yani aynı yapıdaki dosya, altı kat farklı rapor.

**Canlı ölçüm (29 Eylül 2026, Rota şablonu):**
`content-length` başlığı **yok** (chunked), `content-encoding: br`,
açılmış boyut 237.744 bayt.

Sebep `gbc_hz_varlik_boyut()` içindeki üç dallı ölçümdü:

1. content-length varsa onu kullan → 7 dosyada bu çalıştı (40-52 KB, doğru)
2. kodlama yoksa AÇILMIŞ GÖVDEYİ kullan → 1 dosyada bu çalıştı (252 KB, YANLIŞ)
3. yoksa ikinci istek

İkinci dal açılmış boyutu "ziyaretçinin gerçekten indirdiği bayt" diye
raporluyordu. Sahte bir "acil" alarmı doğuyordu.

**Düzeltme — tek yol:** br ve gzip isteyen AYRI bir istek,
`decompress => false`. content-length varsa o, yoksa ham gövdenin
uzunluğu. Sıkıştırılmış boyut açılmıştan büyük çıkarsa ölçüm güvenilmez
sayılıp açılmışa düşülüyor. Açılmış boyut ayrı tutuluyor.

Gerçek CSS ağırlığı **40-52 KB sıkıştırılmış** — yani 60 KB sarı eşiğinin
ALTINDA. Bu alarm gerçek değildi.

### 38.2 Ölü sınıf listesi TEHLİKELİYDİ

Panelin ölü sınıf listesinde **ilk iki sırada `.gbc-col` ve `.gbc-ac`**
vardı. Kaynak tarandı: ikisi de `classList.add()` ile, **tıklamada**
ekleniyor. Statik HTML'de hiç görünmezler. Silinselerdi açılır kapanır
bölümler ve sütun geçişleri **sessizce** bozulacaktı.

Aynı tuzak `.gbc-flash`, `.gbc-ok`, `.gbc-err`, `.gbc-sale`,
`.gbc-yt-facade`, `.gbc-vp`, `.gbc-2` için de geçerli — toplam 22 sınıf.

**Düzeltme:** her ölü sınıf artık eklenti kaynağında aranıp üç kovadan
birine konuyor:

| Kova | Anlamı | Ne yapılır |
|---|---|---|
| **silinebilir** | Kaynakta hiç geçmiyor, eski şablon kalıntısı | Güvenle silinir |
| **koşullu** | Kaynakta var ama ölçülen sayfalarda basılmamış | Önce nerede kullanıldığı bulunur |
| **SİLME — JS ekliyor** | classList/className ile çalışma anında ekleniyor | Silinmez |

`gbc_hz_js_siniflari()` kaynağı tarayıp 22 sınıf buldu. Panel artık her
sınıfın yanında kararı rozet olarak gösteriyor, bizim sınıflarımızı tema
sınıflarından ayırıp öne alıyor.

**Gerçekten silinebilir bulunanlar:** `gz-grid-3`, `gz-v7-grid-square`,
`v1-video-responsive`, `tr-video-wrap`, `gz-video-responsive` — beşi de
eklenti kaynağında hiç geçmiyor.

### 38.3 Ölü sınıfların çoğu bizim değil

Dosya başına ~1010 sınıfın **687-714'ü tema ve WordPress çekirdeği**
(`wp-element-button`, `has-very-light-gray-background-color`, degrade
sınıfları). Bunlar bizim silebileceğimiz şeyler değil. Bizim payımız
dosya başına 299-404 sınıf.

Site geneli: 728 sınıfımızdan 328'i (%45) ölçülen sayfalarda geçmiyor —
ama yukarıdaki üç kova uygulanmadan bu sayı bir şey ifade etmiyor.

### 38.4 Kalan tek gerçek madde

"Kritik CSS (CCSS) uygulanıyor mu kapalı" — bu gerçek bir ayar ve A4 test
planına bağlı. Canlıda açılıp 8 şablonda PSI ile ölçülmeden açılmayacak.

### Testler

`olu_css_testi.php` (36 kontrol) — JS ile eklenen sınıfların bulunması,
`.gbc-col` ve `.gbc-ac`'ın "SİLME" damgası alması, gerçekten ölü beş
sınıfın "silinebilir" çıkması, bizim/tema ayrımı, ölçümün tek yola
indirilmesi.

Toplam takım: **808 kontrol, hepsi geçiyor.**

---

## 39. Kritik CSS test tezgâhı — ziyaretçiye sıfır etki (v1.19.0)

A4'ün "canlıda test edilmeden açılmayacak" şartı vardı. Araştırma sonucu
test **ziyaretçi hiç görmeden** yapılabiliyor.

### 39.1 Araştırma bulguları (LSCWP 7.9 kaynak kodundan)

**1) Ayar tek istek için açılabiliyor.** LiteSpeed'in kendi kancası:

```php
do_action( 'litespeed_conf_force', 'optm-css_async', true );
```

Resmî belge: *"Override a setting for the current request only… No settings
are persisted to the database."* Kaynakta karşılığı
`conf.cls.php → force_option() → set_conf()` — veritabanına **yazmıyor**.
`litespeed_conf` ise yalnız okuyucu bir filtre, değer değiştiremiyor.

Zamanlama kritik: `Optimize::init()` ayarı okumadan önce çalışmalı.
Bu yüzden `inc/ccss-test.php` modül kayıtlarıyla değil, **eklenti dosyası
yüklenirken** require ediliyor ve kanca `plugins_loaded` 1'e takılıyor.

**2) Kritik CSS hazır değilse FOUC YOK.** `optimize.cls.php`:

```php
if ( ! $this->_ccss ) {
    self::debug('❌ CCSS set to OFF due to CCSS not generated yet');
    $this->cfg_css_async = false;
}
```

Yani kritik CSS üretilmemişse eklenti async'i o istek için kendisi
kapatıyor, sayfa normal yükleniyor. "Stilsiz sayfa" korkusu yersizmiş.
Tek dokümante FOUC senaryosu: CCSS üretilmeden ÖNCE önbelleğe alınmış
sayfalar — çözümü üretimden sonra Purge All.

**3) CCSS ile UCSS'in riski aynı değil.**

| | Ne yapar | Riski |
|---|---|---|
| **UCSS** | Sayfa başına kullanılmayan kuralları **siler** | JS ile eklenen sınıflar, hover, menü kalıcı bozulur |
| **CCSS / css_async** | Yükleme **sırasını** değiştirir, kural silmez | En kötüsü geçici görsel kayma; geri alması anında |

UCSS'in bozduğu şeyler bu yüzden CCSS'te beklenmiyor. Yine de ölçülecek.

### 39.2 Tezgâh

`inc/ccss-test.php`. Adresinde gizli anahtar olan isteklerde — ve yalnız
onlarda — `optm-css_async` açılıyor ve o istek `litespeed_control_set_nocache`
ile önbelleğe alınmıyor. Test sürümü başka ziyaretçiye servis edilemiyor.

Hız panelinde sekiz şablon için iki sütun: **şimdiki hâli** ve
**kritik CSS açıkken**. İkisi de önbelleksiz, karşılaştırma adil.
Panel ayrıca kritik CSS'in üretilip üretilmediğini
(`wp-content/litespeed/ccss/` ve `litespeed.css._summary`) ve kuyrukta kaç
sayfa olduğunu gösteriyor.

**Ziyaretçiye etki sıfır, veritabanına yazma sıfır.** Test bunu ayrıca
doğruluyor: parametresiz ve yanlış anahtarlı isteklerde hiçbir kanca
çalışmıyor; çalıştığında da seçenek tablosuna yalnız anahtarın kendisi
yazılmış oluyor, LiteSpeed ayarı yazılmıyor.

### Testler

`ccss_testi.php` (32 kontrol) — anahtar üretimi ve sabitliği, yalnız doğru
anahtarın testi açması (boş, yanlış, parça anahtar reddediliyor), yönetici
ekranında çalışmaması, veritabanına ayar yazılmaması, sekiz şablonun
taban/test adresleri, kritik CSS durumunun okunması.

Toplam takım: **840 kontrol, hepsi geçiyor.**


## Bize Sor kutusu logo alt metni (v1.35.1)

- 30 Eylül 2026: Sayfa denetimi Sorrento'da "Bütün görsellerde alt metni" KALDI verdi.
  21 görselin tek eksiği `modules/10-guvenlik.php` içindeki Bize Sor başlık kartının
  logosuydu (`alt=""` elle yazılmıştı). `alt="Gezginbirchef logosu"` yapıldı.
- Kutu yorum alanı açık bütün yazılarda basıldığı için düzeltme site genelidir.
- 1.35.0'ın (içerik denetimi, alan okuma) üstüne kuruldu; o değişiklik aynen korundu.


---

# 30 EYLÜL 2026 — v1.20 → v1.36 · Denetimin kendisi onarıldı

Bugünün konusu içerik değil, **ölçen aletlerdi**. Üç ayrı yerde defter
gerçeği söylemiyordu; hepsi ölçülerek bulundu ve kökünden kapatıldı.

## 36. Düzenleme ekranını öldüren ACF tip uyuşmazlığı (v1.30.0 / v1.30.1)

**Belirti:** Dört yeni Gezi Rehberi taslağında (31480 Strazburg, 31496
Riquewihr, 31498 Obernai, 31500 Freiburg) yazı düzenleme ekranı bembeyaz
açılıyordu. Meta kutuları HTML'de vardı ama blok editör hiç açılmıyordu.

**Sebep:** `trip_links` alanı ACF'te **textarea**. Bir yazıcı oraya metin
yerine yazı ID dizisi yazmıştı: `[31496, 31498, 31500, 16045]`. ACF alanı
basarken `esc_textarea()` çağırıyor; PHP 8'de bu fonksiyon dizi alınca
TypeError fırlatıyor. **Sayfa tam o alanda kesiliyor** — altında kalan her
şey, blok editör betikleri dahil, hiç basılmıyor. Sağlam sayfa 1.87 MB,
bozuk sayfa 1.05 MB'da kesiliyordu.

**Yanlış teşhisler (elenenler):** PHP hata kaydında fatal YOK (kayıt yalnız
MCP gürültüsü tutuyordu). Tarayıcı konsolunda hata YOK. `.maintenance`
dosyası değil — o ayrı bir olaydı, aynı gün iki kez kısa 503 yaşandı ve
kendiliğinden düzeldi.

**Çözüm — `inc/alan-koruma.php`:** sebep değil SONUÇ kapatıldı. İki kapı:
- `acf/load_value` (öncelik 20) — okurken skaler bekleyen alana dizi
  gelirse güvenli metne çevirir. Ekran ölmez.
- `acf/update_value` (öncelik 20) — yazarken aynı çeviri. Bozuk değer
  veritabanına hiç girmez.

Düz skaler dizi satır satır birleştirilir (`[31496,31498]` → `"31496\n31498"`),
veri görünür kalır. İç içe/nesne çevrilemez; alan boşaltılır ama ham değer
`gbc_ak_yakalananlar` seçeneğine yazılır.

**Sıcak yol:** filtrenin ilk satırı değer skalerse hiçbir şey yapmadan döner.
Alanların neredeyse tamamı skaler; ölçülebilir maliyet yok.

**DOKUNULMAYAN tipler (kritik):** relationship, post_object, gallery, image,
file, taxonomy, user, link, group, repeater, flexible_content, select,
checkbox, radio, true_false, button_group, clone, google_map. Bunlar meşru
olarak dizi döner; listeye eklenirlerse çalışan alanlar bozulur.

**v1.30.1 eklemesi:** canlı testte görüldü ki bozuk değer çoğunlukla REST
üzerinden geliyor ve REST isteğinde `is_admin()` FALSE olduğu için
`inc/gunluk.php` yüklenmiyor, `gbc_sorun_ac()` tanımsız kalıyor. Kayıt
seçenekte duruyordu ama deftere düşmüyordu. `admin_init` kancası eklendi:
bir sonraki yönetici sayfasında deftere düşürüyor.

**Canlı kanıt:** ekranı öldüren birebir dizi 31480'e tekrar yazıldı. Yazma
kapısı diziyi metne çevirdi, ekran 1845 KB tam açıldı, ölüm mesajı yok.

## 37. Sorun defteri her sürümde kendini siliyordu (v1.31.0)

**Belirti:** Defter "1 açık sorun" diyordu. Kapananlar listesine bakınca
"298 sayfada boş alan", "6 yazının kalıcı bağlantısı boş", "CCSS kapalı"
hepsi **"kural değişti (1.28.0/5 → 1.28.1/5)"** gerekçesiyle kapanmıştı.
Hiçbiri çözülmemişti.

**Sebep:** `gbc_sorun_kural_surumu()` imzayı `GBC_CORE_SURUM`'dan kuruyordu.
Yani eklentinin HER sürümü, kuralla ilgisi olmasa bile, açık sorunların
tamamını süpürüyordu. Tek günde üç kez oldu.

**Çözüm:** yeni `GBC_KURAL_SURUM` sabiti (gbc-core.php). İmza artık yalnız
ona bağlı. **Bu sabit SADECE bir tespit kuralı gerçekten değiştiğinde elle
artırılır**; yeni özellik, hata düzeltmesi veya paketleme için ASLA.

**Geçiş:** `gbc_sorun_eski_imza_mi()` eski biçimi (üç parçalı semver) tanır;
o durumda süpürme YAPILMAZ, açık sorunlar yeni damgayla olduğu gibi korunur.

## 38. Defter kendini onaramıyordu (v1.32.0)

`gbc_ic_sorunlari_isle()` yalnız tarama BİTİNCE çağrılıyordu. Tarama
1065/1065 tamken defter başka bir sebeple boşalırsa, bulgular diskte
durduğu hâlde geri gelmiyordu: ekran "309 bulgu", defter "0 sorun" diyordu.

**Çözüm:** `gbc_ic_ekran()` her açılışta `gbc_ic_sorunlari_isle()` çağırıyor.
Veri zaten diskte; yeni tarama GEREKMEZ. Defter kendini onarır.

Ayrıca **"Koruma defterini temizle"** düğmesi eklendi (`ak_temizle` işlemi):
alan koruma kayıtları tabloyla gösteriliyor, tek tıkla temizlenip sorun
kapanıyor.

## 39. Üç yanlış alarm kuralı (v1.33.0 · KURAL 2)

Defterdeki maddeler tek tek açıldı; üçü gerçek çıkmadı:

| Sorun | Gerçek mi | Neden |
|---|---|---|
| Yayında 4 sayfada şablon kısa kodu yok | HAYIR | Dördü de kurumsal SAYFA: Gizlilik #3, İletişim #3939, Food Stylist #8636, Proje & İş Birlikleri #11209. Şablon kullanmamaları doğru. |
| 6 yazının kalıcı bağlantısı boş | HAYIR | Altısı da TASLAK. WordPress slug'ı yayında üretir. |
| 7 sayfada öne çıkan görsel yok | HAYIR | Hepsi TASLAK, yarım içerik. |

**Yeni kurallar:** şablon zorunluluğu yalnız `post` türüne; slug, öne çıkan
görsel ve boş alan yalnız `publish` durumuna. Tanınmayan kısa kod istisna —
taslakta bile sayılır, yayına alınınca kırılır.

Hepsi bulgular tablosunda GÖRÜNMEYE devam ediyor, yalnız defter kusur
saymıyor. Başlıklar "Yayındaki %d …" olarak netleştirildi.

## 40. Tarama işaretçisiz alanları GÖREMİYORDU (v1.34.0 tanı → v1.35.0 çözüm)

**En önemli bulgu.** Denetim, dolu alanları boş raporluyordu: Kefir #3867 ve
Kete #3871'de `tarif_faq` doluydu (815 ve 812 karakter, sayfada SSS bölümü
görünüyordu) ama denetim "eksik" diyordu. Yönetici hesabından baştan tam
tarama çalıştırıldı, sonuç değişmedi — veri eski değil, OKUMA hatalıydı.

**Tanı aracı (`inc/alan-tani.php`, v1.34.0):** tek yazıyı taramanın yolundan
okuyup ham veriyle yan yana koyar. SALT OKUMA. Çıktı:

```
Taramanın gördüğü alan: 1   (30+ alanlık Tarif şablonunda!)
alan                taramada  get_field()  ham meta  ACF işaretçisi
tarif_eslikciler    evet      dizi(3)      dizi(3)   var
tarif_faq           HAYIR     815          815       YOK
tarif_giris         HAYIR     470          470       YOK
tarif_temel         HAYIR     238          238       YOK
```

**Sebep:** `get_fields()` alanları **ACF İŞARETÇİSİNDEN** sayar — her alanın
yanında duran `_alanadi => field_xxx` eşleniği. Değer doğrudan postmeta'ya
yazıldıysa (Make senaryosu, REST, toplu SQL) işaretçi oluşmaz ve alan
`get_fields()` için GÖRÜNMEZ olur. `get_field('ad', $pid)` ise çalışır,
çünkü adı açıkça verilince ACF tanımdan bulur.

**Çözüm (v1.35.0) — işaretçiye hiç bakmaz:**
1. Alan ADLARI şablonun ACF grup TANIMINDAN alınır (`gbc_ic_grup_alanlari()`,
   grup başına istek içi önbellek).
2. Değerler tek bir `get_post_meta( $pid )` çağrısıyla okunur.
3. `get_fields()` sonucu üstüne birleştirilir ama YALNIZ dolu olanlar —
   boş ACF değeri ham metadaki dolu değeri ezmemeli.

**Ölçülen sonuç:** Kefir'de görülen alan 1 → **30**, ayrışma 3 → **0**.
Tam tarama: bulgu 313 → **246**, yayında bulgulu 290 → **227**,
`tarif_faq` eksiği **96 → 0** (tamamı yanlış alarmmış).

**Not:** 30 alanın 29'unda işaretçi hâlâ YOK. Veri gerçekten işaretçisiz
yazılmış; yeni yol buna bağışık.

## 41. Puan alanları norma girmez (v1.36.0 · KURAL 4)

1.35.0 işaretçisiz alanları görmeye başlayınca `tarif_puan` ve
`tarif_puan_sayisi` ilk kez ortaya çıktı ve 24'er tarifte sahte eksik
üretti. Halil doğruladı: *"Puanlama insanlardan geliyor, boş çıkması çok
normal."*

`gbc_ic_norm_disi()` eklendi — norma girmeyen alanlar. Buraya **YALNIZCA
değeri dışarıdan (ziyaretçiden) gelen alanlar** yazılır. Editörün
doldurması gereken bir alan buraya konursa gerçek eksik gizlenir.
`gbc_ic_norm_disi` süzgeciyle genişletilebilir.

## 42. Hız tarafı — VPI kapatıldı, UCSS açıldı

**VPI (Viewport Images) kapatıldı.** Sebep: QUIC.cloud Sayfa İyileştirme
kotası aylık 2000; UCSS, CCSS ve VPI **aynı havuzdan** yiyor. UCSS/CCSS
sayfa türü başına çalışır (10-20 istek, biter); VPI URL başına çalışır
(1065 URL, her önbellek boşaltmada yeniden kuyruğa girer) — bu kotayla
asla bitmez ama kalan krediyi yiyip UCSS'i aç bırakır. Kuyruk temizlendi,
10 dakikada 0'dan 20'ye çıktı; kapatıldıktan sonra boş kaldı.

**Kayıp yok:** VPI'nın işini tembel yükleme dışlama listesi zaten yapıyor
(`custom-logo, gz-v7-img, skip-lazy, nolazy, wp-post-image, attachment-full,
featured-image`). 5 sayfada ölçüldü: öne çıkan görsel, logo ve gz-v7
görselleri VPI kapalıyken de tembel yüklenmiyor.

**UCSS açıldı ve ölçüldü.** Liste şablonunda: 115 KB açık / 19 KB sıkışık.
UCSS'siz benzer sayfalar 231-302 KB / 35-47 KB. **~%55 düşüş.**
Beyaz liste 80 → 118 satır; çalışma anında eklenen 4 sınıf
(`ast-popup-nav-open`, `ast-off-canvas-active`, `active`, `show`) korundu.
Mobil menü 390px'de açılıp kapanıyor, masaüstü 1200px'de taşma yok.

**gtag.js DOKUNULMAZ.** Sıkışık 194 KB, inen her şeyin ~%65'i. Ama 15
Eylül'de geciktirme denendi ve geri alındı: 5 saniye dolmadan ayrılan okur
GA4'te sayılmıyordu, Google Ads yanlış veriyle çalışıyordu. Bu kapı kapalı.

**Görseller zaten tamam:** 48 görselin hepsi WebP, tembel yükleme açık.

## 43. Çalışma kuralı eklendi

**Değişiklikler TEK TEK yayına alınır.** Bir düzeltme yüklenir, bozulma var
mı kontrol edilir, temizse sonrakine geçilir. Toplu paket yok.
(Halil, 30 Eylül 2026: *"hepsini toplu olarak yapmayalım"*.)

**Paralel sohbet uyarısı:** 30 Eylül'de v1.35.1 başka bir sohbetten geldi
(Bize Sor logosunun `alt` metni). Çakışmadı çünkü o sohbet 1.35.0'ın üstüne
kurmuştu. İki sohbet aynı eklentiye dokunuyorsa, zip üretmeden ÖNCE canlı
sürüm kontrol edilmeli — yoksa son yükleyen diğerinin işini siler.

## 44. Bugün açık kalanlar

- **150 tarifte `tarif_alt_linkler` boş** — gerçek, içerik yazımı gerekiyor.
  Boş olması bir şey bozmuyor: modül alanı boşsa kutuyu HİÇ basmıyor
  (kefir'de kutu yok, focaccia'da var — ölçüldü).
- **30 tarifte özet, 8 Liste + 7 Gezi alanı** — gerçek, az sayıda.
- **CCSS kapalı** — bilerek. UCSS kanıtlanmadan ikisi üst üste açılmaz.
- **Bulunamayan:** `tarif_eslikciler` doğruluğu hiç denetlenmedi.

## Bağlantı raporu + ortaklık link yapısı (v1.37.0, 30 Eylül 2026)

- Sayfa Denetimi'nin altına dört gruplu **Bağlantı raporu** eklendi (`inc/bag-raporu.php`):
  Ortaklık · YouTube · Diğer dış · İç. Yalnız içerik alanı sayılır; menü, footer, paylaş düğmeleri hariç.
- Her satırda **Sayfa durumu**: ✓ çalışıyor · ↪ yönlendiriyor (iç linkte 301 = linki son adrese çevir) ·
  ✗ ÖLÜ (404/410) · ⚠ bot engeli (403/429, tarayıcıda açılır) · ? ulaşılamadı · – kısa bağlantı.
- Ortaklık satırlarında ayrıca **Link yapısı** (hiç istek atmadan adresten okunur):
  tp.media → marker 767959, trs 565047, campaign_id/p eşleşmesi (Booking 84/2076, Omio 91/2078,
  GetYourGuide 108/3965, DiscoverCars 117/3555), sub_id dolu ve data-aff ile aynı, u= geçerli.
  tpx.li → HATALI (sub_id rapora düşmüyor). pxf.io → subId1 şart. CJ → sid şart.
  Ağ parametresi olmayan doğrudan satıcı linki → "doğrulanamadı".
- **SAHTE TIKLAMA ÖNLEMİ:** ortaklık ağ adresi (tp.media, pxf.io, tpx.li, CJ) sunucudan ASLA
  açılmaz. Linkin gittiği asıl sayfa (u=) sınanır. Hedefi yazmayan kısa link hiç açılmaz.
  Aynı kural günlük ortaklık sağlık taramasına da uygulandı (`gbc_ort_saglik_dene`); eskiden her
  gün 60 ağ adresini doğrudan açıyordu.
- İstekler paralel gider, sonuç 12 saat saklanır ("Bağlantıları şimdi yeniden kontrol et" düğmesi
  saklananı yok sayar). YouTube videosu oEmbed ile, iç link HEAD ile (yönlendirme izlenmeden) sınanır.
- Sıradaki: v1.38.0 Google dizin işareti, v1.39.0 günlük kontrol.

## Sayfa Denetimi özeti: bağlantı ayrımı, meta, başlık, URL (v1.37.1, 30 Eylül 2026)

- Üst karttaki "Bağlantı iç/dış" (menü ve footer dahil sayım) yerine içerik alanının ayrımı basılıyor:
  ortaklık · YouTube · diğer dış · iç, benzersiz adres ve sayfada toplam geçiş.
- Yeni kartlar: Meta açıklama (120–165), Başlık etiketi (30–62), URL (kaç kontrol kaldı).
- Yeni **URL kontrolü** tablosu (istek atmaz): HTTP 200 · Türkçe/kodlanmış karakter yok · yalnız
  küçük harf-rakam-tire · çift/uçta tire yok · -2/-3 kopya izi yok · adreste yıl yok · en çok 60
  karakter ve 7 kelime · canonical bu adres · noindex yok.
- GBC skoru (10 kontrol) DEĞİŞMEDİ; toplu tarama (seo-toplayici) aynı puanı kullanmaya devam ediyor.
- İlk canlı bulgu (Sorrento 31232): /esim-nedir/ iç linki 404. GetYourGuide ve Omio sayfaları
  sunucu isteğine 403 veriyor ("bot engeli"); bu linklerin yapısı doğru, sayfa durumu sunucudan
  doğrulanamıyor.

## Puanlı GBC skoru, tıklanabilir kartlar, şema baloncukları (v1.37.2, 30 Eylül 2026)

- Sayfa Denetimi'ndeki GBC skoru artık PUANLI ve 100 üzerinden (`gbc_br_skor`):
  İçerik 25 (tek H1 6 · başlık sırası 5 · 3 H2 4 · alt metin 6, eksik görsel başına −2 · eski yıl 4) ·
  Meta 15 (açıklama var 5 · 120–165 5 · başlık etiketi 30–62 5) ·
  URL 15 (HTTP 200, canonical, noindex 3'er; diğer altı madde 1'er) ·
  Bağlantılar 30 (3 iç link 5 · ölü link yok 10, her ölü −4 · iç yönlendirme yok 3, her biri −1 ·
  ortaklık yapısı 8, her hatalı −3 · rel="sponsored" 4, her eksik −2) ·
  Şema 15 (basılıyor 3 · beklenenlerin hepsi 12, her eksik −4).
  Bot engeli ve kısa bağlantı puan KIRMAZ (sunucudan doğrulanamayan şey ceza sebebi değildir).
- DİKKAT: toplu tarama (seo-toplayici) hâlâ eski 10 maddelik `gbc_seo_skor`'u kullanıyor; iki ekranın
  yüzdesi farklı çıkabilir. Günlük kontrol sürümünde (v1.39.0) hizalanacak.
- Üst kartların hepsi tıklanıyor ve aşağıdaki bölüme iniyor. Bağlantı kartında her grup ayrı link
  (#br-ortaklik, #br-youtube, #br-dis, #br-ic). Yeni kartlar: Bağlantı sağlığı, Şema.
- Kontroller tablosu beş başlıkta gruplandı, her madde "puan / en çok" gösteriyor. URL kontrolleri
  bu tabloya girdi.
- Şema bölümü baloncuklu: basılan (yeşil, adediyle) · olması gereken ama eksik (kırmızı) ·
  öneriler (mavi, kesikli). İlk öneriler: TouristAttraction (gezi sayfasında yer kartları var ama
  gbc_schema_yerler boş), GeoCoordinates (gbc_schema_geo boş), ItemList (liste şablonu).
  Gezi şablonunda TouristDestination artık "olması gereken" listesinde.
- 30 Eylül 2026: Sorrento 31232 trip_links'teki ölü /esim-nedir/ → /esim-nedir-yurt-disinda-internet/
  düzeltildi (canlıda doğrulandı).

## Sayı netliği, URL kartı, sayfayı adıyla bulma (v1.37.3, 30 Eylül 2026)

- Sayfa Denetimi kutusu artık ad, numara ya da adres kabul ediyor (`ara`). Tek sonuç çıkarsa doğrudan
  denetim açılır, birden fazlaysa liste (taslaklar dahil, durumu yazılı), yoksa "bulunamadı".
- Sorrento'da "Ortaklık bağı 30" ile raporun "29" farkı ölçüldü: Skyscanner linki sayfada 2 kez geçiyor.
  Eski kart geçişi, rapor linki sayıyordu. Yeni "Ortaklık linki" kartı ikisini birden yazar
  (29 link · sayfada 30 kez · 1 link birden fazla yerde · program dağılımı).
- Bağlantı raporundaki kart satırı yerine grup × durum tablosu: her grubun link, geçiş, çalışıyor, ölü,
  yönlendiren, bot engeli, ulaşılamadı, kısa link sayısı ve toplam satırı. Üstteki bütün sayılar buradan.
- Sayfa adresi, HTTP kodu ve boyut ayrı satırdan kaldırıldı; URL kartına ve URL kontrol başlığına taşındı.

## Google bölümü: dizin, Search Console, Analytics (v1.38.0, 30 Eylül 2026)

- Yeni dosya `inc/google-sayfa.php`. Sayfa Denetimi'nde Kontroller tablosunun hemen altında.
- **Dizin:** URL Inspection API (`gbc_km_index`, 12 saat saklanır). ✓ Google dizininde / ✗ değil,
  son tarama tarihi, Google'ın seçtiği canonical farklıysa uyarı. "Yeniden sor" düğmesi saklananı siler.
  Üstte "Google dizini" kartı. Dizin durumu skoru ETKİLEMEZ (yeni sayfa kalite sorunu değildir).
- **Search Console:** yalnız bu sayfanın adresiyle süzülür (page equals). Tıklama, gösterim, CTR,
  aralık ortalaması sıra ve **güncel sıra** = son 7 günün gösterime göre ağırlıklı sırası
  (dataState=all, kesinleşmemiş taze veri dahil) + verisi olan en son günün sırası.
  Kelime tablosu: aralık değerleri + her kelimenin son 7 gün sırası, ▲/▼ fark.
- **Analytics (GA4):** pagePath EXACT. Görüntülenme, oturum, kullanıcı, etkileşim oranı; kanal ve
  kaynak/araç kırılımı. Her istek 90-komuta günlük sayacından düşer (tavan 200). Sonuçlar 6 saat saklanır.
- **Tarih:** 7 / 30 / 90 / 180 gün düğmeleri + özel aralık. Varsayılan son 30 gün.
- Eski "Bu sayfanın sıralamaları" (Rank Math tablosu) Google bölümü varsa basılmıyor.
- Sıradaki: v1.39.0 günlük kontrol (dizin durumu değişince haber, bağlantı sağlığı, toplu skor hizalama).

## Kelime evreni ve kapsama (v1.39.0, 30 Eylül 2026)

- Yeni dosya `inc/kelime-evreni.php`. Sayfa Denetimi'nde Google bölümünün altında + üstte "Kelime kapsama" kartı.
- Veri: post meta `gbc_kw_evren` (JSON metin): tohum, güncelleme zamanı, kelimeler
  [k, h (aylık arama), hk (hacim kaynağı: google_ads | ubersuggest), ht (tarih), kay (bulunduğu kaynaklar), alaka (elle)].
- **Tara** düğmesi (admin-post `gbc_kw`, is=tara): Google otomatik tamamlama (tohum + 16 ek, paralel) + sayfanın son 90
  gün Search Console kelimeleri. Var olanı silmez, yenisini ekler. Hacim getirmez.
- **Alaka:** otomatik eleme (tohum geçmeyen, futbol/borsa/marka kelimeleri → alakasız; gezi kelimeleri → alakalı;
  İngilizce ve belirsiz → kararsız). Elle ✓/✗ her zaman kazanır.
- **Kapsama:** canlı HTML başlık/H2/H3/SSS/metin bloklarına bölünür; kelimenin bütün kökleri AYNI blokta geçmeli
  (yakınlık yöntemi). Türkçe ek için ön ek eşleşmesi, İngilizce aramalar için eşanlam (hotel→otel, italy→italya...).
- **Oran:** ana kelime hariç uzun kuyruk hacminin kapsanan yüzdesi (ana kelime tek başına oranı şişirmesin);
  ana kelime dahil oran da yazılır.
- **Öneri** (geçmeyen alakalılar): ≥500 ve ayrı konu (konaklama/yemek/ulaşım/gezi, sayfanın konusundan farklı) → ayrı
  sayfa · ≥100 → yeni H2 · soru kalıbı → SSS · ≥20 → H3/paragraf · altı → paragrafta geçir. Geçiyor ama yalnız
  metinde/SSS'de ve ≥100 → başlığa taşı.
- **HACİM KAYNAĞI KARARI (Halil, 30 Eylül 2026):** kalıcı kaynaklar Ubersuggest, Search Console, Analytics.
  Adspirer (Google Ads Keyword Planner erişimi) YALNIZ ilk toplu çekimde kullanılır, sistem ona bağlı kalmaz.
  Google Ads Keyword Planner doğrudan bağlanacaksa API Merkezi'ne Google Ads geliştirici anahtarı + OAuth eklenmeli
  (henüz yok). Öncelik: Google Ads hacmi varsa Ubersuggest onun üstüne yazmaz.
- Düzeltme: Google bölümünde gösterimi sıfır olan günler "güncel sıra" hesabından çıkarıldı ("29 Eyl: 0,0. sıra" hatası).
- Sorrento 31232 ilk veri: 53 kelime (Google Ads 3, Ubersuggest 34, otomatik tamamlama 23). Uzun kuyruk kapsaması %82,
  ana kelime dahil %95. Sıradaki: 1.40.0 kanibalizasyon, 1.41.0 silo ağacı.

## Claude kararları, toplu onay, gereksizler, kanibalizasyon (v1.40.0, 30 Eylül 2026)

- Kelime kaydına Claude alanları: `ck` (evet/hayir), `cn` (gerekçe, SERP özeti), `ce` (eylem: h2|h3|sss|paragraf|atla|sayfa),
  `cy` (sayfada nereye), `ct` (tarih). Karar sırası: elle > Claude > otomatik.
- Claude kararı arama niyetine ve Google ilk sayfasına (Ubersuggest serp_analysis, TR/tr) bakar. Örnek (Sorrento):
  "sorrento pizza" 390 → Konya'daki pizzacı, alakasız · "sorrento hava durumu" 70 → SERP tamamen hava tahmin siteleri, alakasız ·
  "sorrento ne demek" → sözlük · "sorrento mykonos" → parfüm · "sorrento limonu" → karışık (içecek + limon turu), paragraf.
- "Claude'un N kararını onayla" düğmesi (is=onayla): elle kararı olmayanlara ck yazılır.
- Yakalama ihtimali: başlık/H2/ayrı sayfa yüksek · H3/SSS orta · paragraf düşük.
- Gereksiz: alakalı ama aylık 20'nin altında ve soru değil (ya da Claude "atla" dedi) → kapalı listede, silinmez
  (silinirse Tara geri getirir).
- Gruplar: Yapılacaklar · Sayfada geçenler · Hacim bekleyen · Kararsız · Gereksiz · Alakasız.
- Yeni `inc/kanibal.php`: KESİN = Search Console'da aynı kelimede başka sayfamız gösterim alıyor (site geneli, sorgu
  tohum içerir, 90 gün, 12 saat saklanır). RİSK = başka sayfanın başlığı ya da odak kelimesi aynı kelimeyi hedefliyor
  (taslak dahil; tek kelimelik şehir adı başlıkta geçmek için sayılmaz). Sahip kuralı: rakibin ana konusu (başlığın ilk
  parçası, örn. "amalfi sahili") kelimede geçiyorsa kelime rakibin; aynı özel konu (otel/ulaşım/yemek) rakibin; aksi hâlde
  hub (bu sayfa) sahip, rakipten hedefleme kaldırılır ve buraya link verilir.
- İlk bulgu: Amalfi Sahili taslağı (31227) başlığında "Gezilecek Yerler, Sorrento" → "sorrento gezilecek yerler" ve
  "sorrento gezi" için RİSK. Taslak yayına girmeden başlığı düzeltilmeli.
- Sıradaki: v1.41.0 sayfa düzeni (iki sütun), Yapılacaklar/Öneriler kutusu, GBC skoruna kelime ve kanibalizasyon puanı.

## Sayfa düzeni, Yapılacaklar kutusu, 7 bölümlü GBC skoru (v1.41.0, 30 Eylül 2026)

- Yeni `inc/sayfa-duzen.php`. `gbc_seo_sayfa_ekran` rapor hazırsa `gbc_sd_ekran()`'a devreder; eski akış yedek olarak durur.
- Sıra: üst kartlar (GBC skoru, Yapılacak, Kelime kapsama, Kanibalizasyon, Google dizini, Bağlantı, Bağlantı sağlığı,
  Ortaklık, Meta, Başlık, URL, Şema, Görsel) → [Yapılacaklar | Kontroller] iki sütun → Kelime evreni → Kanibalizasyon →
  Google → Bağlantı raporu → Şema → Başlık yapısı → Meta ve yıl → Motorlar. Her kart aşağıdaki bölüme çapa ile gider;
  çapa kapalı bir details'a düşerse JS açar.
- GBC skoru = 7 bölümün toplamı (100): İçerik 20 · Meta 10 · URL 12 · Bağlantılar 18 · Şema 10 · Kelime 20 ·
  Kanibalizasyon 10. Kelime verisi yoksa bölüm "yok" sayılır ve toplamdan çıkarılır (puan orantılanır).
- Kontroller katlanır gruplar: tamamı geçen grup kapalı tek satır, eksiği olan açık.
- Yapılacaklar: bütün bölümlerden toplanır, öncelik Yüksek/Orta/Düşük, ilk 12 görünür, "Kalan N işi göster".
- Mobil: kartlar 2 sütun ızgara, tablolar kendi içinde yatay kayar, arama kutusu %100 (390 px'te taşma yok, ölçüldü).
- Uyarı: seo-toplayici (toplu liste) hâlâ eski `gbc_seo_skor`'u kullanıyor; 1.43.0'da yeni skora bağlanacak.
- Sıradaki: v1.42.0 silo ağacı (hub → sayfa → alt sayfalar, dikey/yatay bağlar), v1.43.0 günlük kontrol (dizin durumu değişimi).

## Kelime fırsatları Fırsatlar ekranında (v1.42.0, 30 Eylül 2026)

- Yeni `inc/firsat-kelime.php`. Sayfa Denetimi her açıldığında o sayfanın kelime analizinden özet çıkar ve
  `gbc_kw_firsat` meta'sına yazılır (yalnız değiştiyse; ikinci açılışta yazmadığı test edildi).
- SEO · Fırsatlar artık iki kaynağı tek listede gösterir. Search Console verisi okunamasa bile kelime fırsatları görünür.
  - Eklenecek kelime: alakalı, hacmi var, sayfada yok → H2 / H3 / SSS / paragraf önerisi, yakalama ihtimaliyle.
  - Yeni sayfa: hacim ≥500 ve ayrı konu.
  - Başlığa taşı: geçiyor ama yalnız metinde/SSS'de, hacim ≥100.
  - Onay bekliyor: Claude'un alaka kararları onaylanmamış sayfa.
  - Taranmamış sayfa: Rank Math GSC tablosunda gösterim alıyor, kelime evreni hiç yok (en çok 25), satırda "Tara" düğmesi.
  - Kanibalizasyon: Sayfa Denetimi'nin bulduğu kesin/risk kayıtları da Search Console'unkilerle aynı süzgeçte.
- Sıralama puanı: aylık hacim × 3 (90 güne denk) × yakalama ağırlığı (yüksek 1 · orta 0,6 · düşük 0,3);
  Google sayfayı o kelimede zaten gösteriyorsa ×1,5; kanibalizasyon kaydı varsa ×0,5.
- Üstte kartlar, türe göre süzgeç, "bu sayfanın fırsatları" (fpid) süzgeci. Liste en çok 100 satır.
- Sıradaki: v1.43.0 silo ağacı, v1.44.0 günlük kontrol.

## Birleşik Envanter, plan tablosu, mevsim alarmı, toplamada yeni skor (v1.43.0, 30 Eylül 2026)

- Envanter ve Toplama tek ekran. Toplama menüden kalktı; eski adres (gbc-seo-toplama) Envanter Özet'i gösterir.
  Sekmeler: Özet · Öncelik ve takvim · Plan (tablo) · Bütün içerik · Silo ağacı · Yetim sayfalar.
- Yeni `inc/plan.php`: Halil'in Google tablosu GBC_Gezi_Rehberleri_IsListesi_v2
  (1UPTZT6IwQKsO_Zb2A47VuQ6g8z5d_XEx0pKLFm93UGA) sitenin planı. Okuma sırası: servis hesabı (Sheets API,
  spreadsheets.readonly, ayrı belirteç) → herkese açık CSV (gviz) → elle yapıştırılan CSV. Günlük cron `gbc_plan_gunluk`.
  Tabloya yazmaz. Hiçbiri okunamazsa pakete gömülü `inc/plan-ilk.json` (29 Eyl dışa aktarımı, 218 satır) kullanılır.
- Satır biçimi: # (↳ alt sayfalar üst numaraya bağlanır, 7.1 gibi), bölge, ülke, ad, durum (acik/duzeltme/acilacak),
  şablon, mevcut/hedef URL, Post ID, YouTube, not, 12 ay hacmi; Kış Temaları numaraları "kış" işareti.
- Eşleştirme: Post ID → mevcut URL → hedef URL. Gerçek durum: yok / taslak / acik (şablon ve adres hedefle aynı) / duzeltme.
  Hedef şablon: adı "Gezi Rehberi" olan ana satır Gezi, "Ana Hub" Liste, diğerleri tablodaki.
- Bulgular (1 tablo tutarsızlığı · 2 iş · 3 bilgi): tabloda Açılacak ama yayında; tabloda Açık/Düzeltme ama sitede yok;
  Mevcut URL eski; adres hedeften farklı (301, Rank Math yönlendirmesi var mı); şablon yanlış; Post ID yanlış/boş;
  hacim yok; skor düşük/eski/ölçülmedi; dizinde değil.
- Mevsim: 12 ayın ≥10'u dolu ve en yüksek ay ≥100 ve ortalamanın 1,4 katıysa zirve ayı (eşitlikte sezonun başladığı ay).
  Hacim yoksa: not sütunundaki "N/mo", sonra kelime evreni tohum hacmi. Kış teması ANA satırı hacimsizse zirve Aralık varsayılır (*).
  Son tarih = zirve ayından 2 ay önce. Hazır = gerçek durum acik ve yeni GBC skoru ≥70.
  Alarm: acil (hazır değil, son tarih geldi/geçti) · yaklaşıyor (≤45 gün) · gözden geçir (hazır, zirveye ≤45 gün).
- Öncelik puanı ≈ 0–100: min(40, 13·log10(1+aylık)) + durum (yok/taslak 25, düzeltme 20, açık (100−skor)/4)
  + alarm (25/15/8) + video 5 + Search Console gösterimi min(10, 3·log10(1+gösterim)).
- Takvim: önümüzdeki aylara göre hangi sayfanın hazır olması gerektiği.
- Toplama artık `gbc_sd_tam_denetim()` ile 7 bölümlü GBC skorunu yazar (skor_surum=2): bölüm puanları, yapılacak sayısı,
  ilk 5 iş, dizin durumu (yok→var geçişinde `dizine_girdi` tarihi), kelime kapsama; kelime fırsatlarını da kaydeder.
  Sayfa Denetimi ekranı aynı kaydı yazar (`gbc_sd_kayit`). Geçmiş 8 ölçüm. Eski (sürüm 1) skorlar Envanter'de gri ve * ile.
  Sıra: gösterimli ve yeni skorsuz → plandaki sayfalar (7 günden eski) → hiç ölçülmemiş → en eski.
- Bütün içerik: bütün yayındaki yazı/sayfa tek indeks (15 dk saklanır); gösterim/tık/skor/tarih/başlığa göre sıralama;
  süzgeç: şablon, skor bandı (düşük/orta/iyi/düşenler/ölçülmedi/dizinde değil), tabloda olan/olmayan. ▲▼ önceki ölçüme göre.
- Planda olmayan: Gezi/Liste şablonlu ya da başlığında "Gezi Rehberi/Gezilecek Yerler" geçen, tabloyla eşleşmeyen sayfalar.
- Test: 218 satırlık gerçek dışa aktarımla sahte site üzerinde; 390 px'te taşma yok; ortaklık adreslerine istek yok.
- Sıradaki: v1.44.0 silo ağacı (plan satırlarındaki ↳ ilişkisi + Rank Math iç link grafiği).

## Yük koruması: veritabanı bağlantı hatası sonrası (v1.43.1, 30 Eylül 2026)

- 30 Eyl 17:25'te site kısa süre "Error establishing a database connection" verdi. Veritabanı birkaç saniye içinde döndü;
  PHP günlüğünde veritabanı ya da ölümcül hata yok; MariaDB max_connections 2000.
- Bizim payımız: bağlantı raporu iç linkleri `Cache-Control: no-cache` ile HEAD isteğiyle ve HEPSİNİ AYNI ANDA soruyordu.
  Önbellek atlandığı için her iç link = sitenin kendisine tam PHP + veritabanı isteği. Toplama 6 sayfada bunu art arda yapıyordu.
- Düzeltme 1: `gbc_br_ic_yerel()`. İç link istek atmadan çözülür (url_to_postid + yayında mı + adres aynı mı → 200 / 301 güncel adres).
  Çözülemeyen (kategori, eski slug) önbellekten sorulur. Bütün istekler 4'erli gruplar hâlinde.
- Düzeltme 2: `inc/kilit.php` iş kilidi. Toplama, Kontrol Merkezi turu, günlük kontrol, içerik partisi ve ortaklık sağlığı
  aynı anda çalışmaz. Kilit 15 dk (günlük 30 dk) sonra kendiliğinden düşer; aynı istekteki iç içe çağrı takılmaz.
  Atlanan günlük kontrol ve ortaklık sağlığı 10–15 dk sonra yeniden kurulur.
- Düzeltme 3: Envanter ekranı hesap yapmaz. Plan analizi 6 saat, içerik indeksi 2 saat saklanır ve saatlik toplamanın
  sonunda arka planda yenilenir. Sayfa Denetimi önbelleği silmez, o sayfanın satırını yerinde günceller.
- Düzeltme 4: `wp-content/db-error.php` (işaret: GBC-DB-HATA). Veritabanı koparsa Türkçe kısa sayfa, 503 + Retry-After 30,
  noindex, 20 sn'de kendini yeniler. Başkasının db-error.php dosyasına dokunulmaz.

## Tek çatı: menü 5 gruba indi, Kontrol Paneli (v1.44.0, 30 Eylül 2026)

- Halil: "ona git buna git uğraşmayayım, tek ekrana bakayım". Yeni `inc/merkez.php`.
- Menü: Kontrol Paneli · SEO · İş Ortaklığı · Bağlantılar · Çalışma Dosyası (17 kayıttan 5 görünür).
  Kontrol Paneli sekmeleri: Özet (yeni) · Sorunlar (gbc-gunluk) · Sayfa taraması (gbc-nobetci-kural) · İçerik alanları (gbc-icerik)
  · Güvenlik · Hız · Zamanlama (gbc-cron) · Modüller (eski Durum, yeni slug gbc-moduller).
  SEO: Envanter · Sayfa Denetimi · Fırsatlar · Search Console (gbc-seo). Bağlantılar: API anahtarları (gbc-api) · Veri kaynakları (gbc-kaynak).
- Eski ekranlar silinmedi; menüden `admin_menu` 999'da kaldırılır, adresleri çalışır, `submenu_file` doğru grubu parlatır.
  Ortak üst çubuk `gbc_tasarim_yol()` → `gbc_merkez_ust()`: grup düğmeleri + o grubun sekmeleri.
- Özet ekranı ölçüm yapmaz; kayıtlı sonuçları okur: genel durum (Sağlıklı/Dikkat/Sorun), açık sorunlar, sayfa taraması,
  güvenlik puanı, hız, içerik alanları, SEO (Envanter önbelleği), ortaklık linkleri, zamanlayıcı; otomatik işler tablosu
  (sıklık, sıradaki, son çalışma, süre).
- Tek düğme "Hepsini şimdi kontrol et": günlük tam kontrolü arka plana tek seferlik iş olarak koyar (args 'elle'),
  sunucu zamanlayıcısı birkaç dakika içinde başlatır; kilit sayesinde başka işle üst üste binmez.
- Kalıcı sorun uyarısı Kontrol Paneli ve Sorunlar sekmesinde basılmaz (zaten ekranda).

## Silo ağacı Sayfa Denetimi'nde, GBC skorunun 8. bölümü (v1.45.0, 30 Eylül 2026)

- Yeni `inc/silo.php`. Hiyerarşi plan tablosundan: bölge hub'ı ("… Ana Hub") → şehir rehberi (ana satır) → ↳ alt sayfalar;
  hub'ın ↳ satırları (vize, dil) bölge yardımcı sayfaları; aynı bölgedeki diğer ana satırlar kardeş (yatay).
  Sayfa hub'sa altı = bölgenin bütün ana satırları + kendi ↳'leri. Sayfa ↳ ise üstü = numarasının üst satırı, kardeşi = aynı üstün ↳'leri.
- Gerçek bağlar: giden = bağlantı raporunun iç linkleri (render edilmiş sayfa, ACF dahil). Gelen = `_gbc_giden` meta'sı
  (",12,345," biçimi; her denetim yazar) + Rank Math iç link tablosu. Rank Math tek başına eksik: içerik ACF'de, onun sayacı
  gövdedeki kısa koda bakıyor. Karşı sayfa denetlenmediyse "?" (ölçülmedi) yazılır, "yok" denmez.
- Puan (10): üst sayfaya bağ 4 · üstten gelen bağ 2 · alt sayfaların hepsine bağ 2 (oranlı) · en az 2 yatay bağ 2.
  Uygulanmayan madde katılmaz; plan tablosunda olmayan sayfada bölüm "veri yok". Kart ve Kontroller "N bölüm" yazar.
- Yapılacaklar'a silo işleri düşer (üst sayfaya link 1 · üstten link 2 · alt sayfa 2 · yatay 3 · açılmamış üst 3).
- Toplama da aynı hesabı yapar; `_gbc_giden` her sayfa ölçüldükçe dolar, "?" işaretleri kendiliğinden kalkar.

## Sekmeler açılmıyordu: gizleme düzeltmesi (v1.45.1, 30 Eylül 2026)

- 1.44.0–1.45.0: gizli sekmeler (Sayfa Denetimi, Fırsatlar, Sorunlar, Güvenlik…) "Üzgünüz, bu sayfaya erişmenize izin
  verilmiyor" verdi. Sebep: `$submenu['gbc']`'den silinen sayfanın üst menüsü bulunamıyor; get_plugin_page_hookname
  'admin_page_…' üretiyor, kayıtlı kanca 'gbc_page_…' olduğu için user_can_access_admin_page() false dönüyor.
- Düzeltme: satırlar menüden SİLİNMEZ; 5. alanına (sınıf) "gbc-gizli" yazılır, CSS ile gizlenir (WordPress'in
  Özelleştir satırı için kullandığı "hide-if-no-customize" yöntemi). Sıra ve etiketler yine ayarlanır.
- Kural: WordPress'te bir yönetici sayfasını menüden gizlemek için remove_submenu_page/unset KULLANMA; sınıfla gizle.

## Çalışma Dosyası kılavuza dönüştü (v1.46.0, 30 Eylül 2026)

- Halil: "her şeyi tekrar öğrenmeyelim; yazım kuralları, gezi kuralları… sayfa tiplerine böl, geliştirme tekniklerini ayrı koy;
  her gittiğinde oradan yapılsın."
- Yeni `kilavuz/` klasörü, 12 Markdown dosyası (her biri bir sekme): Çalışma Kuralları · Yazım Kuralları · Şablonlar ve Silo ·
  Gezi Rehberi · Liste · Detay · Rota · Tarif · Blog ve Sözlük · Ortaklık · Tasarım Kuralları · Geliştirme Teknikleri.
  Kaynak: bellekteki 9 kural dosyası + bu DEFTER + eski WPCode "Ortaklık Kılavuzu" (30870) + "Ortaklık Durum Ekranı" (30992).
  Şifre, token, vergi bilgisi yazılmadı. Kaynaklar çeliştiğinde en yeni tarihli kural alındı.
- Dosya biçimi: 1. satır "# Başlık" (sekme adı), 3. satır "> özet". Kural eklemek = ilgili .md dosyasına madde eklemek.
- Ekran: sekmeler + arama (bütün kurallarda ve geçmişte; eşleşen satırlar vurgulu) + "Sürüm geçmişi" (bu DEFTER, bölümler
  kapalı, en yenisi üstte). Küçük Markdown çevirici: başlık, liste, alıntı, tablo, kod, kalın, bağlantı.
- `gbc_defter` seçeneği artık `kilavuz` (dosya adı => metin) ve `okuma` (hangi sırayla okunur) alanlarını da taşır; MCP'den
  tek çağrıda bütün kurallar okunur.
- Eski WPCode ekranları "— Kılavuz" (gbc-aff-kilavuz) ve "— Durum" (gbc-aff-durum) menüde gizli (1.45.1 kuralı);
  içerikleri kılavuza taşındı. Snippet'lerin pasife alınması Halil'in onayıyla.

## Eklenti denetimi (v1.47.0, 30 Eylül 2026)

- Halil: "Pluginler çalışmıyorsa, aktif değilse, yavaşlatıyorsa silebiliriz; denetimini denetim sayfasına koy."
- Yeni `inc/eklentiler.php`, Kontrol Paneli → Eklentiler sekmesi (gbc-eklentiler). Hiçbir eklentiyi kapatmaz/silmez.
- Ölçülen: durum (ağda etkin/etkin/kapalı), bekleyen güncelleme, autoload payı (her istekte okunan ayar), veritabanı tabloları
  ve boyutu, SAHİPSİZ tablolar (adı kurulu eklentiyle eşleşmeyen), zamanlanmış işler, ziyaretçi sayfasına yüklenen CSS/JS
  (ana sayfa + son gezi sayfası, önbellekli hâl; LiteSpeed'in birleştirdikleri görünmez), yönetimde bağlanan kanca sayısı.
  Ölçüm 12 saat saklanır (`gbc_ek_olcum`), "yeniden ölç" bağlantısı var.
- Bu siteye özel görev ve karar (`gbc_ek_bilgi`): Gerekli (ACF, WPCode, Astra Pro, LiteSpeed, Rank Math, Loginizer, GBC Core) ·
  Kullanılıyor (Hostinger, Make, Easy MCP) · İncele (Royal MCP: ikinci Claude bağlantısı; AI Provider for Anthropic: kullanan özellik yok)
  · GBC Core'a taşınabilir (Disable Feeds WP) · Kaldırma adayı (WP Consent API: onu kullanan izin eklentisi yok).
- Kural: eklenti silme kalıcıdır, Halil yapar; Claude yalnız onayla kapatabilir (kapatma geri alınabilir).

## Kanibalizasyon yalnız yayındaki sayfalarla (v1.47.1, 30 Eylül 2026)

- Halil: "Taslak olanı kanibalizasyona almana gerek yok; aktif olduktan sonra kanibalizasyon var, o zaman çözülür."
- `gbc_kn_komsular()` yalnız 'publish' sayfaları rakip sayar. Taslak/bekleyen/zamanlanmış sayfalar `taslak` listesine düşer,
  Kanibalizasyon bölümünde "Yayına girince kontrol edilecek" notu olarak görünür; puanı, Yapılacaklar'ı ve Fırsatlar'ı etkilemez.
- Sorrento (31232) içerik: "sorrento gezi rehberi" ve "sorrento gezisi" girişe, "napoli sorrento arası kaç km/kaç saat"
  yalnız SSS 7'ye (TEK YER KURALI: tren paragrafı eski hâline döndü), "sorrento limonu" yemek özetine yerleştirildi. Rakamlar: karayolu ~50 km, Circumvesuviana
  ~1 saat 10 dk (Wikipedia: 47 km hat, 68 dk), Campania Express ~50 dk (freetoursbyfoot), feribot 35–45 dk (Ferryhopper);
  kontrol 30 Eylül 2026.

## Tepe düğme, Google dizini puanı, kelime kararları onaysız (v1.47.2, 30 Eylül 2026)

- "Düzelttim — yeniden kontrol et" kutusu Fırsatlar ekranında arama formunun sağına, sayfanın tepesine taşındı; alttaki düğme kalktı.
- GBC skoru 9. bölüm: Google dizini (4 puan). Dizin durumu bilinmiyorsa bölüm hiç sayılmaz; "var" olunca tam puan.
- Halil: "Alakalı/alakasız kararını sen ver, benden onay bekleme, Claude diye de yazma."
  Kelime kontrolünün kararı (`ck`) artık doğrudan geçerli: "onay bekliyor" durumu, "kararını onayla" düğmesi,
  "Onay bekleyen" kartı ve Fırsatlar'daki "onay" türü kalktı. Karar notu adsız görünür. Elle verilen karar yine kazanır.
- TEK YER KURALI kılavuza (02-yazim, 04-gezi) yazıldı: aynı bilgi sayfada bir kez, en önemli yerde; SSS için istisna yok.
- Şema alanları `gbc_schema_yerler` ve `gbc_schema_geo` REST'e açıldı (yalnız düzenleme yetkisi olan yazar); duraklar araçla doldurulabiliyor.
  Koordinatların kaynağı sayfanın kendi Google Haritalarım haritası (Sorrento: mid 17dWG_498…).
- Sorrento (31232) TEK YER: SSS 4 (Napoli'den nasıl gidilir) Ulaşım/tren alanının, SSS 5 (plaj) İpuçları ve Bütçe'nin aynısıydı; ikisi kaldırıldı,
  kalan SSS'ler 1-5'e kaydırıldı. Yedek: faq_yedek.json (çalışma klasörü).

## Sayfa Denetimi kısayolu (v1.47.3, 30 Eylül 2026)

- Halil: "Edit'e girince bir düğmeyle hızlıca SEO detaylarına geçeyim."
- inc/kisayol.php: düzenleme ekranının sağ sütununda en üstte "GBC Sayfa Denetimi" kutusu (son skor, acil iş sayısı, "SEO detaylarını gör" düğmesi);
  üst siyah çubukta "GBC %skor" bağlantısı (düzenleme ekranında ve sitede sayfaya bakarken); Yazılar/Sayfalar listesinde "Sayfa Denetimi (%skor)" satır bağlantısı.
- Yalnız yönetici görür. Ölçüm yapmaz, son kayıtlı skoru (_gbc_seo) okur; ziyaretçi sayfasına yük eklemez.

## Haritadan şema durakları (v1.47.4, 30 Eylül 2026)

- Halil: "Hepsi varsa yazılacak, yoksa yazılmayacak; kurala göre direkt gelecek." + Sorrento My Maps KML'leri (Merkez, Deniz, Yeme İçme).
- inc/kml-yerler.php: sayfadaki Google Haritalarım iframe'inden `mid` bulunur, KML (google.com/maps/d/kml?mid=…&forcekml=1) sunucudan okunur,
  iğneler "Tür | Ad | enlem,boylam | #çapa" satırına çevrilir ve _gbc_kml_yerler metasına yazılır. Şema motoru `gbc_schema_yerler` boşsa bunu basar.
- Okuma: Sayfa Denetimi açılınca ve saatlik toplamada, 7 günde bir; "yeniden kontrol et" hemen. Okunamazsa eski liste kalır, bir saat sonra yeniden dener.
  Harita sayfadan kalkarsa liste silinir. Liste değişince LiteSpeed o sayfayı temizler.
- Çapa yalnız sayfada o id gerçekten varsa yazılır. Sorrento KML'indeki bölüm bağlantıları (…piazza-tasso-dan-…) sayfada yok:
  sayfanın başlık id'leri kesme işaretini "039" diye yazıyor (…piazza-tasso-039-dan-…, gbc_toc_slug HTML kodlu metin alıyor). Düzeltme ayrı iş; eski bağlantılar bozulmasın diye takma id gerekir.

## Düzenleme ekranı sadeleşti (v1.47.5, 30 Eylül 2026)

- Halil: "Komuta, GBC Komuta ve SEO kutuları artık kullanılmıyor, kaldır; en üstte denetim sayfasına giden bir düğme olsun."
- modules/90-komuta.php: "GBC Komuta · Sayfa Kartı" ve "GBC Komuta · SEO Denetimi" kutuları artık eklenmiyor (fonksiyonlar duruyor, veri silinmedi).
- Yerine sağ sütunun en üstünde "GBC Sayfa Denetimi" kutusu (v1.47.3) ve üst çubuktaki "GBC %skor" bağlantısı.

## Başlık çapalarında "039" düzeltmesi (v1.47.6, 30 Eylül 2026)

- gbc_toc_slug başlık metnini HTML kodlu alıyordu; kesme işareti id'ye "039", & işareti "amp" olarak giriyordu
  (…piazza-tasso-039-dan-…). Artık metin önce çözülüyor: …piazza-tasso-dan-… (haritadaki "Rehber" bağlantılarıyla aynı).
- Eski biçim farklıysa başlığın içine boş bir takma çapa (span.gbc-eski-capa, eski id) konuyor; eski iç ve dış bağlantılar aynı başlığa iner.
- Sorrento (31232) tek yer temizliği: asansör bileti ayrıntısı yalnız Falez Asansörü kartında; Bütçe kartından "Sahile inmek" ve
  "Almadan önce şunu yapın" (İpuçları 5 ile aynı) paragrafları çıktı; düşük ve orta bütçe metinleri rakamsız yazıldı. Yedek: butce_yedek.json.
- Kelime tablosunda "Alaka" sütununda düğme kalmadı: karar yazılı durur (✓ alakalı / ✗ alakasız), yanında küçük "değiştir" bağlantısı.
- Sorrento'nun 7 kararsız kelimesi (futbol, borsa, mutfak ürünü aramaları) alakasız işaretlendi; kararsız 0.
- Kılavuz 04-gezi: Sorrento (31232) örnek sayfa olarak yazıldı.

## Harita çapası düz metinden okunur (v1.47.7, 30 Eylül 2026)

- Canlı ölçüm: Google Haritalarım KML'i iğne açıklamasındaki bağlantıyı href olarak değil düz metin olarak veriyor
  ("Rehber: Sorrento gezi rehberi (https://gezginbirchef.com/sorrento/#bolum)"). 1.47.4 yalnız href arıyordu, çapa hiç bulunmuyordu.
  Artık açıklamadaki her adres okunuyor; sayfanın kendi adresi ve sayfada var olan çapa ise yer o bölüme bağlanıyor.

## Harita çapası: tanı ve yönlendirme bağlantısı (v1.47.8, 30 Eylül 2026)

- 1.47.7 canlıda çapaları yine yazmadı; tarayıcıdan alınan aynı KML yerelde doğru çözülüyor. Sunucunun gördüğü KML farklı olabilir.
- Google'ın dış bağlantıyı sardığı biçim (google.com/url?q=…%23bolum) açılıyor, adres kodu çözülüyor.
- _gbc_kml_yerler'e 'tani' eklendi: KML'den bulunan çapa sayısı, sayfada doğrulanan çapa sayısı, sayfadaki id sayısı, ilk iğne açıklamasından örnek.
- Sayfanın id'leri okunamazsa çapa atılmıyor (doğrulanamayan durum ceza sayılmaz).

## Harita çapası: sayfa eşleşmesi gevşetildi (v1.47.9, 30 Eylül 2026)

- 1.47.8 tanısı: sunucunun gördüğü KML'de bağlantı düz metin olarak var (örnek alındı), sayfada 96 id var, ama KML'den bulunan çapa 0.
  Tek açık kalan koşul, bağlantının yolunun get_permalink() yoluyla birebir aynı olması. Artık son parça (slug) aynıysa da kabul ediliyor;
  yanlış eşleşmeye karşı çapa yine sayfadaki id'lerle doğrulanıyor. Tanıya get_permalink yolu ('yol') eklendi.

## Harita çapası: eski adres (v1.47.10, 30 Eylül 2026)

- 1.47.9 tanısı sebebi gösterdi: Sorrento'nun gerçek adresi /sorrento-gezi-rehberi/, haritadaki "Rehber" bağlantıları eski adrese (/sorrento/) gidiyor
  (WordPress eski adresi yeni adrese yönlendiriyor). Eklenti bağlantıyı başka sayfa sanıyordu.
- Artık sayfanın eski adları (_wp_old_slug) da bu sayfa sayılıyor; çapa yine sayfadaki id'lerle doğrulanıyor.


## Dizin Durumu — site geneli Search Console (v1.48.0, 30 Eylül 2026)

**Yeni dosya:** `inc/dizin.php` · **Ekran:** SEO → Dizin Durumu (`admin.php?page=gbc-dizin`)

### Ne yapar
- Sitedeki her adresi (yayındaki bütün gönderi türleri + dolu taksonomi terimleri + ana sayfa)
  Google'ın **URL Inspection API**'sine sorar, `{prefix}gbc_dizin` tablosuna yazar.
- Sonucu 14 sınıfa ayırır ve her sınıf için **ne yapılması gerektiğini** Türkçe yazar:
  dizinde · tarandı-dizinsiz · keşfedildi · Google bilmiyor · kanonik farklı · noindex ·
  robots · 404 · yumuşak 404 · sunucu hatası · yönlendirme hatası · erişim engeli ·
  sorgulanamadı · henüz bakılmadı.
- **Ölü adresler:** Search Console'un son 90 günde gösterdiği ama sitede artık karşılığı
  olmayan adresleri bulur. Ağ isteği yok — WordPress'in kendi çözümleyicisi (`url_to_postid`)
  ve Rank Math yönlendirme tablosu kullanılıyor, bu yüzden hem hızlı hem kesin.
- Deftere üç sorun açar/kapatır: `dizin_hata` (yüksek), `dizin_yok` (orta), `dizin_olu` (orta).
- Her gece 03:20'de `gbc_dizin_gunluk` 150 adres yeniler → yaklaşık haftada bir tam tur.

### Sınıflandırma neye bakıyor — ve NEYE BAKMIYOR
`coverageState` İngilizce serbest metin ("Crawled - currently not indexed"). Google bu
metni değiştirebilir, üstelik `languageCode` ile çevrilir. **Karar ona verilmiyor.**
Karar sırası tamamen yapısal alanlardan: `pageFetchState` → `robotsTxtState` →
`indexingState` → `verdict` → kanonik karşılaştırması → `lastCrawlTime`.
`coverageState` yalnız ekranda "Google'ın kendi açıklaması" diye gösteriliyor.
Bu yüzden istek `languageCode: en` ile gidiyor: metin sabit kalsın diye.

### KOTA
URL Inspection site başına **günde 2000**, dakikada 600. Buradaki tavan bilerek **1800**:
`inc/google-sayfa.php` (tek sayfa denetimi) aynı havuzdan içiyor. Tavana değince tarama
kendini durdurur, ertesi gün kaldığı yerden devam eder. Kotaya hiç çarpmıyoruz.

### "Günde 10 URL otomatik gönderelim" — NEDEN OLMUYOR
Halil'in isteği buydu ve **yapılamıyor**; gizlemek yerine buraya yazıyoruz.
Google'ın "İndekslemeyi iste" düğmesinin **API'si yok**. Indexing API
(`indexing.googleapis.com`) Google'ın kendi belgesinde açıkça sınırlı:

> "The Indexing API can only be used to crawl pages with either JobPosting or
> BroadcastEvent embedded in a VideoObject."

Tarif ya da gezi sayfası göndermek işe yaramıyor. Search Console API'sinde de
gönderim ucu yok — yalnız okuma var. Bu yüzden bu modül **sahte bir otomatik
gönderim yapmıyor**. `dizin_testi.php` bunu kural olarak sınıyor: kodda
`indexing.googleapis.com` ya da `urlNotifications` geçerse test kırmızıya döner.

Gerçek yol üç parçalı, v1.49.0'da tamamlanacak:
1. Modül günün 10 adresini önceliğe göre seçer (en çok gösterim alan, en yeni, en iyi içerik).
2. IndexNow ile Bing/Yandex'e **gerçekten** gönderilir (onların API'si var ve ücretsiz).
3. Google tarafı: zamanlanmış görev Search Console ekranını tarayıcıyla açıp o 10 adres için
   düğmeye basar. Halil hiçbir şeye basmaz.

### Sınama
`dizin_testi.php` — 124 onay. Kapsam: yol normalleştirme, adres karşılaştırma,
14 sınıflandırma dalının hepsi ayrı ayrı, öncelik sırası (teknik hata `verdict: PASS`'i ezer),
sınıf haritasının eksiksizliği, günlük sayaç ve tavan, ölü adres çözümleyici,
**dürüstlük kuralı** (sahte gönderim yok), **canlı ayar kuralı** (modül yalnız kendi üç
seçeneğini yazar; LiteSpeed/içerik/silme yok), bağlantı yokken çökmeme, menü ve sekme kaydı.

---

## Test paketi onarımı (v1.48.0 ile birlikte, 30 Eylül 2026)

v1.47.10'da paket 22 kırıkla geliyordu. Hiçbiri v1.48.0'ın sebep olduğu değildi
(değişiklik öncesi ve sonrası aynı sayıyla ölçüldü). Üçü onarıldı, biri **gerçek hata çıktı**:

1. **`surum_testi.php` — yanlış alarm.** "Tanımsız GBC_ sabiti" diyordu. `GBC_FK_META`
   `define()` ile değil `const` ile tanımlıydı; test yalnız `define()` arıyordu. Test düzeltildi.
2. **`ortaklik_saglik_testi.php` ve `gercek_defter_testi.php` — eskimiş test.**
   v1.37.0'da ortaklık sağlık taraması ağ adresini (tp.media) değil bağlantının gittiği
   **asıl sayfayı** denemeye başlamıştı (sahte tıklama olmasın diye). Testler hedef
   çözücüyü yüklemediği için her bağlantı `kisa` dönüyor, hiçbir kovaya girmiyordu.
   Testlere çözücü taklidi eklendi, kodlar hedef adrese göre verildi.
3. **YENİ DENGE KURALI — ve bulduğu gerçek hata.** İki teste şu kural eklendi:
   `denenen = iyi + engel + kirik + ulasilamadi + bos`. Gerçek 667 kayıtlık defterle
   koştuğunda **kırmızı yanıyor**: 40 bağlantı deneniyor, 33'ü kovalara giriyor, 7'si
   kayboluyor. Sebep: v1.37.0'ın eklediği `kisa` durumunun `gbc_ort_saglik_parti()` ve
   `gbc_ort_saglik_ozet()` içinde **kovası yok**. Hedefi çözülemediği için bilerek
   açılmayan bağlantılar raporda hiç görünmüyor.
   **Bu kırık test bilerek kırık bırakıldı.** Kapatmak v1.49.0'ın ilk işi; düzeltmeden
   "tamam" demek, sorunu gizleyerek sıfıra indirmek olurdu.

Paket durumu: **51 dosya · 1743 onay · 1 bilinen kırık** (yukarıdaki `kisa` kovası).

---

## v1.48.1 — canlıda çıkan üç hata (30 Eylül 2026)

v1.48.0 kurulur kurulmaz canlı sitede denendi. Modül çalıştı (17 adres soruldu,
13'ü dizinde, 4'ü "tarandı-dizinsiz" — Ibiza, Sardunya, Sorrento, eSIM), ama üç
hata çıktı. Üçü de **canlıda** bulundu, testte değil; testler sonradan yazıldı.

**1. "25 adres sorgula" düğmesi 503 verdi.**
Barındırma (Hostinger/LiteSpeed) tek isteği ~60 saniyede kesiyor. Koddaki koruma
120 saniyeydi — yani hiç devreye giremiyordu, sunucu önce davranıyordu.
Tur süresi **18 saniye**ye indirildi, elle tarama 25 → **10** adrese düştü,
"150 adres" düğmesi tamamen kaldırıldı (bir web isteğinde asla bitemezdi).
`usleep(120000)` de kaldırıldı: Google'ın sınırı dakikada 600, biz saniyede
~1 istek yapıyoruz; bekleme yalnız elimizdeki 18 saniyeyi yiyordu.

**2. 1231 adres tek turda bitmiyor — ZİNCİR.**
Bir turda ancak 15-20 adres soruluyor. Gece 03:20'deki tur, işi ve kotası
kaldıysa **90 saniye sonrasına bir tur daha** koyuyor (`gbc_dizin_devam`).
Gece boyunca kendiliğinden ilerliyor, hiçbir istek zaman aşımına düşmüyor.
Sonsuz döngüye karşı üç durdurucu: bakılmamış adres kalmadıysa · günlük kota
bittiyse · zincir 120 halkaya ulaştıysa. Ayrıca tur hiç ilerleyemediyse
(bağlantı bozuk) zincir sürmüyor, ve aynı anda iki halka sıraya girmiyor.

**3. Hiç tarama yokken ekran "hepsi Google dizininde, açık sorun yok" diyordu.**
0 adres sorulmuşken iyi haber vermek, tam olarak sorunu gizlemek demek.
Artık bakılmamışken sarı kutuda "henüz hiçbir adres sorulmadı" yazıyor;
iyi haber verirken de kaç adresin sorulduğunu ve kaçının kaldığını söylüyor.

Sınama: `dizin_testi.php` 124 → **146 onay**. Yeni bölümler süre sınırını,
zincirin üç durdurucusunu ve boş durum mesajını kural olarak tutuyor.
Paket: 51 dosya · 1765 onay · 1 bilinen kırık (`kisa` kovası, v1.49.0).

### Not: `.gbc-yan-ad` alarmı YANLIŞ ALARM — nöbetçi kuralı UCSS'den eski
Defterde "Ortaklık stili durdu: Sebze Kesim Şekilleri" diye yüksek öncelikli
bir sorun açıldı. Canlıda konuk gözüyle ölçüldü:

| Sayfa | HTML'de `gbc-yan-ad` | CSS'te `.gbc-yan-ad` | UCSS |
|---|---|---|---|
| /sorrento-gezi-rehberi/ | 5 | 4 | yok |
| /ibiza-gezi-rehberi/ | 0 | 4 | yok |
| /sebze-kesim-sekilleri/ | 0 | **0** | **var** |

Yani UCSS, seçiciyi **onu kullanmayan** bir sayfadan kaldırmış — tam olarak
yapması gereken şey. Ortaklık kutusu olan sayfada stil yerinde duruyor.
Bozulma yok.

Hatalı olan **kural**: `inc/nobetci-kural.php` içindeki `aff_stil` kuralı
`kapsam => 'hepsi'` diyor, yani `.gbc-yan-ad` HER sayfanın CSS'inde olsun
istiyor. Bu, UCSS açılmadan önce doğru bir varsayımdı; UCSS'in işi tam da
sayfa başına kullanılmayan seçiciyi atmak. Kural koşullu olmalı: seçici,
yalnız HTML'inde `gbc-yan-ad` GEÇEN sayfaların CSS'inde aranmalı. Böylece
gerçek bozulma (stil, ihtiyacı olan sayfadan silinmiş) hâlâ yakalanır.
**v1.48.2'nin işi.** Alarmı susturmak değil, kuralı düzeltmek.
