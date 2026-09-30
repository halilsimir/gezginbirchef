# Geliştirme Teknikleri

> GBC Core mimarisi, veri yazma yolları, ölçülmüş teknik tuzaklar, hız kuralları ve veri kaynakları; kod yazmadan ya da siteye teknik müdahaleden önce okunur.

## Site

- gezginbirchef.com WordPress **çoklu site (network)**, ağda 1 site. Eklentiler ağ genelinde etkin; kurulum ve güncelleme Network Admin'den.
- Astra + Astra Pro (Pro kalıyor, kapatma önerisi tekrar getirilmez) · LiteSpeed Cache · Rank Math (ücretsiz sürüm; PRO bitti, Pro özellikleri varsayılmaz) · Loginizer (+Pro) · ACF · Royal MCP · Easy MCP AI.
- Hostinger · PHP 8.3 · MariaDB 11.8 · tablo öneki `wp2b_`. Tema yazılmaz; Astra kalır.
- Google Tag Manager yok. GA4 ve bütün olay ölçümü kendi kodumuzda; GTM ya da hazır ölçüm eklentisi varsayılmaz.

## GBC Core mimarisi

- Bütün özel kod (motorlar + şablonlar) WPCode'dan çıkarılıp tek eklentiye (`gbc-core`) taşınır. Gerekçe: WPCode Lite bütün PHP snippet'lerini tek `eval()` bloğunda çalıştırır; tek bir `Cannot redeclare` hatası bloğu öldürür ve rastgele bir snippet'i pasife alır.
- Taşınan modüller `modules/` altında, yönetici ekranları `inc/` altında.
- Yönetici ekranları ziyaretçi isteğinde yüklenmez (`is_admin() || DOING_CRON`); ön yüz maliyeti 0 bayt. Cron'da yüklenmeyen ekran dosyaları yalnız varlık ve sözdizimi düzeyinde denetlenir.
- Yükleyici `plugins_loaded` 99'da çalışır, dört kapı: elle kapatıldı mı → `function_exists(imza)` (WPCode kopyası hâlâ çalışıyorsa modül kendini YÜKLEMEZ) → imzasız modülde kaynak snippet hâlâ `publish` mi → dosya yerinde mi.
- Fonksiyon tanımlı ama snippet KAPALI ise durum `kalinti`dır (WPCode'un kendi önbelleğinden geliyor): LiteSpeed Purge All + nesne önbelleği temizlenir.
- `pre_do_shortcode_tag` köprüsü: `[wpcode id="…"]` kısa kodları WPCode'a gitmeden modülün render fonksiyonuna yönlenir; yazılar düzenlenmeden taşıma tamamlanır.
- PHP derleme zamanı: koşulsuz `function foo(){}` dosya derlenirken tanımlanır; dosya başındaki `if (function_exists) return;` işe yaramaz. Her fonksiyon tek tek sarılır.
- İmza fonksiyonu gerçekten tanımlı olmalı; yorum bloğu içindeki `function x()` sayılmaz.
- Eski kod silinmez: arşive (`arsiv/`) alınır, menüden kaldırılır, geri açılabilir kalır.
- Anahtarlar tek yerde: API Merkezi (`gbc_api`, MCP'ye kapalı). Merkez anahtarı eski yerine de yazar (bozmama kuralı); okurken önce merkeze, yoksa eski yere bakılır. Ekranda maskeli gösterilir; boş bırakılan alan değişmez, silmek için tek başına `-` yazılır. Gizli olmayan durum aynası `gbc_api_durum` (dolu mu, uzunluk, biçim, son 4 hane) MCP'ye açıktır.

## Menü

- Menü 5 grup: Kontrol Paneli · SEO · İş Ortaklığı · Bağlantılar · Çalışma Dosyası. Alt sayfalar tek yerden (`inc/durum.php` / `inc/merkez.php`) kaydedilir; modüller kendi menüsünü açmaz.
- Bir yönetici sayfasını menüden gizlemek için `remove_submenu_page` / `unset` KULLANILMAZ (sayfa "erişim izni yok" verir); satır yerinde kalır, 5. alanına `gbc-gizli` sınıfı yazılıp CSS ile gizlenir.
- Eski ekran adresleri çalışmaya devam eder; slug değiştirilmez ki eski bağlantılar kırılmasın.
- Yönetici menü ikonu yalnız `data:image/svg+xml;base64,` kabul eder; PNG bir SVG `<image>` içine sarılır.

## Ağır işler

- Uzun yönetici isteği sunucuda kesilir (beyaz ekran). İş adım adım, her istekte tek adım yapılır.
- Ağır işler iş kilidiyle (`inc/kilit.php`) çalışır: toplama, Kontrol Merkezi turu, günlük kontrol, içerik partisi, ortaklık sağlığı aynı anda çalışmaz. Kilit 15 dk (günlük 30 dk) sonra kendiliğinden düşer.
- İç linkler istek atmadan yerelde çözülür (`url_to_postid` + yayında mı + adres aynı mı). Çözülemeyen önbellekten sorulur; önbelleği atlayarak (`no-cache`) toplu HEAD isteği atılmaz — her biri siteye tam PHP + veritabanı isteğidir ve siteyi düşürdü. İstekler 4'erli gruplar hâlinde.
- Yönetici ekranı ağır hesap yapmaz; sonuç saklanır ve arka planda yenilenir.
- Ziyaretçi tetiklemeli WP-Cron kapalı (`DISABLE_WP_CRON`); sunucuda gerçek cron 5 dakikada bir çalışıyor. Silinmiş eklentilerden kalan hayalet cron kancaları her yönetici sayfasında temizlenir.
- Veritabanı koparsa `wp-content/db-error.php` (işaret GBC-DB-HATA) Türkçe 503 sayfası basar; başkasının db-error.php dosyasına dokunulmaz.

## Kontrol sistemi kuralları

- Bulunan her sorun tek deftere (`gbc_sorunlar`) yazılır ve çözülene kadar kapanmaz; ertesi kontrol bulamazsa kendiliğinden kapanır. Kapanış sebebi günlüğe yazılır.
- Nöbetçi/sayfa taraması sayfa bazında temizler; sayfa indirilemediyse hiçbir şey kapanmaz ("bilgi yok, sorun yok demek değildir").
- `GBC_KURAL_SURUM` yalnız tespit kuralı değişince artırılır (bkz. Çalışma).
- Kabul edilen sorun gerekçeyle ve tarihle kaydedilir; bağlı ölçü %15'ten fazla kötüleşirse kabul düşer.
- Kural yazılmamış motor taranır ama sorun sayılmaz; kural kanıta bakılarak yazılır.
- Sayfa küçülmesi ya da sınıf kaybı tek başına alarm değildir; bir motorun susması eşlik ederse alarmdır.
- Norma girmeyen alanlara (`gbc_ic_norm_disi`) YALNIZ değeri ziyaretçiden gelen alanlar yazılır (ör. `tarif_puan`).
- Şablon zorunluluğu yalnız `post` türüne; slug, öne çıkan görsel ve boş alan kontrolleri yalnız `publish` durumuna uygulanır.

## Test yöntemi

- Her sürüm birim test takımıyla gelir; bütün kontroller geçmeden paket verilmez. Test tezgâhındaki `update_option` gerçekten yazar (`$GLOBALS['gbc_stub_options']`).
- Ziyaretçi gözü: sayfa çerezsiz, oturumsuz, önbellekli hâliyle indirilir; iz gerçek çıktıda (DOM) aranır, `<style>/<script>` içindeki eşleşme sayılmaz. Görünür çıktısı olmayan motorlar (GA4, sayaçlar, video perdesi) betik izinden bulunur.
- Önbelleği atlatmak gerekiyorsa `?gbc_...=<zaman><rastgele>` + `Cache-Control: no-cache`.
- Mobil ölçüm: aynı köken sayfa 390 px genişlikte bir iframe'e yüklenir (`position:fixed;left:-9999px;width:390px;height:800px`), 5 sn beklenir, `#wpadminbar`, `.rank-math-analytics`, `rank-math-*` kutuları silinir, sonra `scrollWidth > clientWidth` ve `getBoundingClientRect().right` bakılır. `resize_window` viewport'u değiştirmez.
- Görünüm karşılaştırması: ziyaretçi HTML'i srcdoc iframe'de 1200 ve 390 px açılıp öğelerin stil/konumu kaydedilir, değişiklikten sonra tekrarlanır.
- PageSpeed: tek ölçüme güvenilmez. Web arayüzü arka plan sekmesinde sonuç vermez; sekme ekranda olmalı ya da düzenli ekran görüntüsü alınır. Anahtarsız API kotaya takılır (429).
- Uzun tarama (1.000+ yazı) tek JS çağrısında zaman aşımına düşer; arka planda `window.__X={done:false}` nesnesine yazan IIFE başlatılıp yoklanır.
- Tarayıcı JS aracının çıktısı kesilebilir; uzun metin MCP ile okunur.
- Claude'un dahili tarayıcısında wp-admin oturumu olmayabilir; wp-admin işi Claude in Chrome ile yapılır.
- Arka plandaki sekmede LiteSpeed tembel yüklemesi çalışmaz; görsel yüklenmiş sanılmaz, ışık kutusu testi orada yapılmaz.
- Gutenberg düzenleme sekmesinden başka sayfaya gidilince "Leave site?" çıkar; ölçüm ayrı sekmede yapılır.

## Veri yazma yolları (ACF ve meta)

- Her ACF alanı iki meta satırı tutar: değer `alan_adi`, referans `_alan_adi = field_xxxxx`. Yeni sayfada yalnız değer yazılırsa `get_field()` boş döner. Alan anahtarları kart başına farklıdır; dolu bir sayfadan kopyalanır.
- `get_fields()` alanları işaretçiden sayar; işaretçisiz yazılmış alanları görmez. Doluluk denetimi için alan adları grup tanımından alınır, değerler tek `get_post_meta($pid)` ile okunur.
- `royal_mcp_acf_get_fields` İŞLENMİŞ değer verir (`do_shortcode` + `wptexturize`); yalnız alan adlarını görmek için kullanılır. Değer her zaman `royal_mcp_wp_get_post_meta` ile ham okunur, `wp_acf_update_fields` ile yazılır, sonra ham okumayla doğrulanır.
- `royal_mcp_acf_update_field` wysiwyg alanlarda kısa kodu siler. Kısa kod içermeyen wysiwyg ve iframe içeren textarea (harita) ise onunla yazılır.
- `royal_mcp_wp_update_post_meta` dizi/nesne kabul etmez, JSON'u metin olarak saklar (`gbc_otel_dest_data` gibi dizi metalara asla bununla yazılmaz). İframe'i sessizce siler. Girdi `{post_id, key, value}`. 72 saat geri alma jetonu verir.
- `wp_update_post_meta` ACF alan adlarını kabul etmez.
- İç içe grup alt alanı `wp_acf_update_fields` ile yazılmaz; düz meta anahtarıyla `royal_mcp_wp_update_post_meta` ile yazılır. Tarayıcıdan REST ile grubun tamamı `{acf:{grup:{alt:...}}}` gönderilir.
- `rehber_faq` REST'te top-level alandır, `meta` içinde değildir; royal MCP ile okunur/yazılır.
- REST'e kayıtlı olmayan metalar (`gz_yan_sutun_*`, `gz_yakin_yerler`, `hb_capa_*`) royal MCP ile yazılır.
- Büyük ACF alanı bağlama sokulmadan tarayıcıda düzenlenir: wp-admin'de `wpApiSettings.nonce` ile `/wp-json/wp/v2/posts/<id>?context=edit&_fields=acf` okunur, JS'te `split('<div class="gz-place-card">')` ile parçalanır, POST `{acf:{alan:yeni}}` yazılır. Kısa kodlar korunur. DOM round-trip yapılmaz; cerrahi regex kullanılır.
- Taslakta autosave revizyonu varsa ACF önizlemede alanları o boş revizyondan okur; yayınlayınca düzelir.
- Skaler bekleyen ACF alanına (textarea) dizi yazılırsa PHP 8'de düzenleme ekranı ölür. Alan koruma kapıları (`acf/load_value`, `acf/update_value`) bunu metne çevirir. Dizi döndürmesi meşru tipler (relationship, gallery, repeater, group, select, checkbox vb.) korumaya eklenmez.
- `wp_acf_update_fields` yanıtı bütün alan setini geri basar; doğrulama o yanıttan ya da ham okumayla yapılır.
- Satır sonları `\n` ya da `\r\n` olabilir; birebir eşleşmede gerçek değere (JSON.stringify) bakılır, tutmazsa indexOf + slice.
- GBC-mcp art arda ~4 yazmadan sonra "Rate limit" verir; 20 sn arayla 3'erli gidilir.
- MCP koparsa yedek yol: Claude in Chrome'da wp-admin sekmesinden POST `/wp-json/wp-abilities/v1/abilities/<ad>/run` gövde `{"input":{...}}` (`royal-mcp/wp-get-post-meta`, `wp-update-post-meta`, `wp-replace-in-post` dry_run ve expected_count destekli).
- MCP bağlantısı Easy MCP AI üzerinden gelir; Easy MCP AI kaldırılırsa site erişimi kesilir.

## WPCode (taşıma bitene kadar)

- `wpcode` yazı tipinin REST rotası yoktur; snippet'ler `wp_get_post` ile okunur, `wp_replace_in_post` ile cerrahi düzenlenir (`expected_count` koruyucusuyla).
- `wp_replace_in_post` ile değişen PHP snippet'i çalışmaya eski hâliyle devam edebilir (WPCode önbelleği); wp-admin'de snippet açılıp Güncelle'ye basılır. Kısa kodla çağrılan şablon snippet'leri anında yansır; "Run Everywhere" snippet'leri Güncelle ister.
- Bazı dosyalarda satır sonları karışık (CRLF + LF); çok satırlı arama tutmaz, tek satırlık benzersiz parçalarla çalışılır.
- "Everywhere" snippet'i aktif görünüp sessizce çalışmayabilir; çıktısı sayfada aranarak doğrulanır.
- Snippet 28829 sabit ID listesiyle H1 ekler; şablona taşınan eski yazının ID'si çıkarılır.

## Önbellek (LiteSpeed)

- Şablon CSS'i `<id>-<özet>.css` dosyasına yazılır, eskisi silinir. CSS düzenlemesinden sonra Purge All ZORUNLU; yoksa önbellekteki HTML eski dosyayı ister, 404 alır, şablonun bütün sayfaları stilsiz kalır.
- Purge tarayıcıdan: `/wp-admin/admin.php?page=litespeed-toolbox` sayfasındaki `LSCWP_CTRL=purge&type=purge_all` bağlantısı çağrılır. Bu çağrı sayfayı yeniden yükler, tarayıcıdaki test değişkenleri silinir.
- LiteSpeed satır içi JS'i `src="data:text/javascript;base64,..."` yapar; aramadan önce base64 çözülür. Satır içi `<style>`'ları birleşik CSS'te toplar; parmak izleri kaybolur, birleşik CSS indirilip aranır.
- Tembel yükleme img `src`'sini yer tutucuyla değiştirir, gerçek adres `data-src`'dedir; iframe `src`'si `about:blank` olur. Okuma sırası: önce `data-src`, sonra `src`.
- `.htaccess` ayrıştırması birkaç saniye önbelleklenir; değişiklik gecikmeli görünür.
- Sunucu başlıkları PHP `header()` ile ezilemez.
- Kendi betiklerimiz satır içi değil dosyadan (`uploads/gbc-js/<ad>-<özet>.js`) bağlanır; dosya yazılamazsa satır içi basılır.

## Ölçüm tuzakları

- JS `\b` Türkçe harflerde kırılır; doğru kalıp `(?<![\p{L}\p{N}_])` + `u` bayrağı. `<style>` ve `/* */` blokları taramadan çıkarılır (ASCII Türkçe yorumlar yanlış eşleşir).
- Emoji regex'i U+2300-U+23FF'i de içerir; oklar (U+2190-U+21FF) dışarıda bırakılır.
- Gezi'nin sağ sütunu `aside.gz-sidebar-column`, ortaklık bloğu `aside.gz-aff-son`; taramada `aside` toptan silinmez, yalnız `.gz-author-box` çıkarılır.
- Gezi haritası `.gz-map-container` içinde tembel iframe'dir; "yok" sanılmaz.
- Pseudo-element (ok) kontrolü WebFetch ile yapılamaz; tarayıcıda hem `content` hem `maskImage` bakılır.
- Mobil taşmada yalancı suçlular: yönetici çubuğu, Rank Math tooltip'leri, kapalı off-canvas menü (`ast-mobile-popup-*`). Kendi içinde kayan (`overflow-x:auto|scroll`) kutular elenir, `hidden` elenmez.
- Sosyal paylaşım bağlantıları (`?text=`) HEAD isteğinde 403 döner; iç link denetiminde ayıklanır.
- Ortaklık sayımı canlı sayfadan yapılır; ACF'de `[gbc_aff` aramak yanıltır (elle yazılmış bağlantılar görünmez).
- Boyut ölçümü sıkıştırılmış olarak yapılır: ayrı istek, `decompress => false`, `Accept-Encoding: br, gzip`; content-length yoksa ham gövde uzunluğu. Gövdesi okunacak istek brotli istemez (libcurl "error 61").
- CSS'te "ölü" görünen sınıfların bir kısmı JS ile tıklamada ekleniyor (`.gbc-col`, `.gbc-ac`, `.gbc-flash` vb.); kaynakta `classList` ile eklenen sınıf silinmez.
- Bizim sınıf önekleri: `gbc- gz- gz2- v1- v11- tr- fb- yn-`; gerisi tema/çekirdek, silinmez.
- Google Haritalar'da adres doğrulama: `/maps/search/<sorgu>` açılır; URL `/maps/place/.../@lat,lon` olursa tek kayda çözülmüştür.
- `is_plugin_active()` ağda etkin eklentiye "pasif" der; `is_plugin_active_for_network()` de kontrol edilir. Yönetici denetiminde `get_super_admins()` de katılır.
- Loginizer 2FA tek serileştirilmiş metadadır: `loginizer_user_settings`.

## Görsel üretimi

- Kaynak 16:9 ise kırpma yok; PIL `Image.LANCZOS` ile 1200x675, `save(WEBP, quality=85, method=6)`. ImageMagick kullanılmaz.
- Kart görseli yerleştirme kalıbı ve rozet: bkz. Gezi.

## Hız kuralları

- Hiçbir değişiklik hızı düşürmez; eklenti ve panel hızı etkilemez.
- **UCSS açık** (30 Eylül 2026): Liste şablonunda ~%55 CSS düşüşü ölçüldü. Beyaz liste 118 satır; çalışma anında eklenen sınıflar (`ast-popup-nav-open`, `ast-off-canvas-active`, `active`, `show`) korunur. Açılmadan önce 28 Eylül'de ana sayfa sekmeleri, mobil kart çizgisi ve SVG değişkeni bozulmuştu; her CSS değişikliğinden sonra bu üçü kontrol edilir.
- **Kritik CSS (CCSS / css_async) kapalı** — bilerek. UCSS kanıtlanmadan ikisi üst üste açılmaz. Test ziyaretçiye etkisiz tezgâhta (`litespeed_conf_force`, gizli anahtarlı istek) yapılır; açılırsa Purge All.
- **VPI kapalı:** QUIC.cloud kotasını (aylık 2000, UCSS/CCSS ile ortak) yiyor; işini tembel yükleme dışlama listesi yapıyor.
- **gtag.js DOKUNULMAZ:** geciktirme denendi ve geri alındı (erken ayrılan okur GA4'te sayılmıyordu). GA4 kalır, ertelenmiş yüklenir; JS toplamında ayrı gösterilir.
- Google Fonts yerelden yüklenir ve önyüklenir (Astra → Performans); CLS'nin ana kaynağıydı.
- LiteSpeed JS erteleme istisnaları: `__gbcTocInit`, `gbc-yt-facade`, `astra/assets/js/minified/frontend.min.js` ve `break_point` (son ikisi birlikte olmalı).
- LiteSpeed önbellek dışı: `/wp-json/easy-mcp-ai/`, `/.well-known/oauth-`, `/.well-known/openid-configuration`.
- YouTube: bütün videolar (ilk video dahil) kapak resmi (facade), tıklayınca oynar. Kapak merdiveni `maxresdefault → hq720 → sddefault → hqdefault → 0` (gri vekil için `naturalWidth` ölçülür). İlk videonun kapağı `<head>`'de preload edilir. VideoObject şeması facade'dan bağımsız durur.
- İçindekiler kutusu sunucuda basılır (CLS için).
- Aynı href'li ikinci preload satırı temizlenir (LiteSpeed son tampon süzgecinde).
- Görsel lisansı şemasında düğüm üst sınırı 20; `copyrightNotice` basılmaz.
- `jquery-migrate` kaldırılmaz (bir eklentiyi sessizce bozabilir).
- Skyscanner widget'ı ağırlığı düşürülemiyorsa kullanılmaz; kullanılırsa ekrana 600 px yaklaşınca yüklenir.
- Hız eşikleri (sıkıştırılmış): CSS 60 KB sarı / 80 KB kırmızı; HTML 150 KB sarı / 250 KB kırmızı.
- Kalıcı nesne önbelleği çalışıyor (28 Eylül akşam hPanel'den açıldı). Ağ ayarı Claude'a kapalı.
- Görseller WebP, tembel yükleme açık; öne çıkan görsel, logo ve `gz-v7` görselleri tembel yüklenmez.

## Veri kaynakları

- **Kalıcı kaynaklar:** Ubersuggest (Claude MCP ile), Google Search Console, Google Analytics (GA4), Google Ads. **Adspirer** yalnız ilk toplu çekimde kullanılır, sistem ona bağlı kalmaz (30 Eylül 2026). Google Ads hacmi varsa Ubersuggest onun üstüne yazmaz.
- Search Console verisi Rank Math'in kendi tablosundan (`wp2b_rank_math_analytics_gsc`) okunur; sütun adları tahmin edilmez, `SHOW COLUMNS` ile okunur. İç bağlantı grafiği `rank_math_internal_links` tablosundan (ACF içeriğini görmez; `_gbc_giden` metasıyla tamamlanır).
- Görsel araması ile web araması toplanmaz; arama türü ayrılamıyorsa "CTR düşük" kuralı çalıştırılmaz.
- GA4 olayları (kendi kodumuz): `affiliate_click`, `outbound_click`, `share_click`, `video_click`, `scroll_depth`; parametreler `page_type`, `post_id`, `link_url`, `link_text`, `link_domain`, `aff_program`, `aff_id`, `scroll_pct`. Raporlarda görünmeleri için GA4 yönetiminde özel boyut tanımlanır (siteden yapılamaz).
- Search Console mülkü (sc-domain) ve GA4 property 291790321 aliumutuyanik@gmail.com altında. Google kurulumu neseliseflercom@gmail.com hesabındaki `gbc-website-509908` projesinde.
- Google API anahtarı IP ile kısıtlanmaz (sunucunun çıkış IP'si A kaydından farklı, 403 verir); API bazında kısıtlanır.
- Plan tablosu: bkz. Şablonlar.

## MCP'den okunabilen seçenekler

`royal_mcp_readable_options` ile açık; başka sohbet tek çağrıda canlı durumu okur:

`gbc_defter` · `gbc_hz_son` · `gbc_hz_psi_test` · `gbc_hz_is` · `gbc_kod_son` · `gbc_gv_son` · `gbc_core_kapali` · `gbc_cron_kayit` · `gbc_cron_son` · `gbc_sky_son` · `gbc_api_durum`

- Kapalı olanlar (bilerek): `gbc_api` (anahtarlar), `gbc_hz_ayar` (PageSpeed anahtarı), Travelpayouts belirteçleri.
