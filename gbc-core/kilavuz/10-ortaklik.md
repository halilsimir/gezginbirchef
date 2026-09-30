# Ortaklık

> İş ortaklığı (affiliate) sisteminin tamamı: kararlar, defter ve kısa kod, programlar, link kalıpları, kanal kuralları, tuzaklar, ödemeler; ortaklık bağlantısına dokunmadan önce okunur.

## Bozulmaz kurallar

- Ortaklık ağ adresleri ve kısa linkler asla açılmaz, tıklanmaz, sunucudan çağrılmaz (sahte tıklama). Yalnız hedef sayfa (`u=`) sınanır. Test gizli pencerede, çıkış yapmış hâlde yapılır; yönetici tıklamaları sayaca bilerek yazılmaz.
- Sayfaya elle ortaklık bağlantısı YAZILMAZ. Her bağlantı deftere (sayfa 30120) bir satır olarak girer; sayfada yalnız kısa kodu kullanılır. Elle `<a class="gbc-in">` yazmak yasak. Tek istisna: kısa kodun çözülmediği Gezi "yakın yerler" kartı (ham HTML, bkz. Gezi).
- Ortaklık bağlantılarından her zaman yüzde yüz emin olunur; hedef açılıp doğrulanmadan satır yazılmaz.
- Kısa link (tpx.li) kullanılmaz: kısaltılmış bağlantıda `sub_id` rapora düşmüyor. Her yerde ham uzun `tp.media` bağlantısı yazılır, `sub_id=` adresin içinde açık durur (20 Eylül 2026).
- Aynı sayfada aynı id (sub_id) iki kez kullanılmaz. Aynı hedef ikinci kez gerekiyorsa `<id>_yan` gibi ikinci satır üretilir.
- Aynı marka iki ağdan verilmez: marka başına tek ağ seçilir, sayfada yalnız o ağın bağlantısı durur (atıf çakışır).
- Her satırda takip kimliği olur: `sub_id` (Travelpayouts), `subId1` (Impact), `ti` (Ferryhopper), `cmp` (GYG doğrudan), `sid` (CJ). Değer defterdeki id ile birebir aynıdır.
- Her ortaklık bağlantısında `rel="sponsored"` olur; ok satır başına tam bir tanedir.

## Kararlar

- Site Travelpayouts'ta kalıyor. GetYourGuide doğrudan hesabı yalnız sosyal kanallar için (20 Eylül 2026). Gerekçe: oran ikisinde de %8, çerez ikisinde de 31 gün.
- Ağ bölüşümü (27 Eylül 2026): Travelpayouts'ta kullanılan marka orada kalır. CJ; Booking.com'un Travelpayouts'tan yapılamayan işi ve Travelpayouts'un YouTube'da izin vermediği markalar için kullanılır. Booking'in hangi ağda kalacağı açık karar (aşağıda).
- Travelpayouts'ta YouTube'a izin vermeyen programların linkleri YouTube'dan çıkarılır.
- Ortaklık hedef pazarları: Türkiye, Avrupa, Kore. Geliri artıracak her uygun programa başvurulup kenarda tutulur; İngilizce sayfalar için meşru seyahat programları da eklenir.
- eSIM ortaklık linki yalnız eSIM rehberi sayfasında kalır; başka sayfalarda eSIM satılmaz, Hızlı Plan'a girmez. Sayfada o yerin kendi değerleri satılır: uçak, otel, bilet, tur, transfer (22 Eylül 2026).
- Skyscanner widget'ı zorunlu değil: hızı düşürmüyorsa kalır, düşürüyorsa yerine klasik ortaklık linki konur (28 Eylül 2026). 29 Eylül ölçümünde widget sayfalardan kaldırılmış durumda.
- Ortaklık bildirimi tek yerde durur (motor `gbc-ortaklik-not`): giriş yazısının altında, ilk bölümden önce; mobilde de. Bağlantı yanında "iş birliği" etiketi yok, elle yazılmış etiket/bildirim olmaz. Şablonlardaki eski alt bildirim (`gz-aff-bildirim`) ve elle yazılmış `gbc-in-et` rozetleri çıktıdan silinir.
- Fiyat kuralı (ortaklıkla satılan ürüne rakam yazılmaz, band yazılır): bkz. Yazım.
- İstanbul mekân sayfalarına ortaklık konmaz.
- Bağlantı koyabilmek bir bloğu ayakta tutma gerekçesi değildir.
- Henüz e-posta bülteni yok; ileride açılabilir.

## Defter (sayfa 30120) ve kısa kod

- Defter tek kaynaktır: taslak sayfa 30120 (`is-ortakligi-baglanti-defteri`). Motor yalnız defterin `<pre>` bloklarının içini okur; blok dışına yazılan satır sitede hiçbir şey basmaz.
- Satır biçimi: `id | etiket | program | url | ağ` (en az dört sütun, dördüncü sütun `http` ile başlar).
- Takma ad satırı: `TAKMA eski_id yeni_id`. Eski id defterde gerçek satır olarak da varsa TAKMA devreye girmez, gerçek satır kazanır. Hedefi olmayan TAKMA kırıktır.
- Satırlar iki `<pre>` bloğuna yayılmıştır; okuyucu bütün blokları tarar.
- Defter İş Ortaklığı panelinden satır satır düzenlenir (ekle / güncelle / sil). Yazma yalnız o satıra dokunur, her yazmadan önce sayfanın tamamı yedeklenir (son 10 kopya + revizyon), defter bir hamlede %40'tan fazla küçülemez. Defterin tamamını tek kutudan üzerine yazan eski yol kapalıdır ve kapalı kalır.
- Defter değişince LiteSpeed önbelleği kendiliğinden temizlenmez: her değişiklikten sonra Purge All.
- Id ön ekleri: `bk_` Booking, `gyg_` GetYourGuide, `dc_` DiscoverCars, `omio_` Omio, `fh_` Ferryhopper, `sky_` Skyscanner, `esim_` Yesim, `cj_` CJ. sub_id destinasyona göre verilir; semt/bölge ayrımı için ayrı id (`bk_roma_prati`).

### Kısa kodlar

| Kısa kod | Ne yapar |
|---|---|
| `[gbc_aff id=x]metin[/gbc_aff]` | Tek ortaklık bağlantısı |
| `[gbc_aff_kutu ids=a,b bas=Başlık not=yok]` | 2-3 hizmeti yan yana kutu |
| `[gbc_alakali etiket=x adet=6]` | Etikete göre canlı yazı listesi |
| `[gbc_otel dest=şehir]` | Otel motoru (test edilmeden sayfaya bağlanmaz) |

- Kısa kodun çalıştığı alanlar şablona göre değişir: bkz. Şablonlar. Ölü alana konan kısa kod basılmaz.

## Programlar

| Program | Ağ | Komisyon | Çerez | Onay |
|---|---|---|---|---|
| Booking.com | Travelpayouts | %5 otel / 1,5 € uçuş / %5-3 araç | tek oturum | 60-90 gün (check-out sonrası) |
| GetYourGuide | Travelpayouts (site) · doğrudan 8XLVICW (sosyal) | %8 | 31 gün | tur tarihi sonrası |
| DiscoverCars | Travelpayouts | %60 + %25 | 365 gün | araç teslimi sonrası |
| Omio | Travelpayouts | %6 | 30 gün | bulunamadı |
| Yesim (eSIM) | Travelpayouts | %18 | 90 gün | bulunamadı |
| Skyscanner | Impact · 7683448 | değişken, yayınlanmıyor | 30 gün | bulunamadı |
| Ferryhopper | Doğrudan · aff_uid=gzgbr | %3 | 30 gün | — |

- Çözülmemiş çelişkiler: DiscoverCars kendi sayfasında %70 + %30 diyor. Skyscanner oranı yalnız Impact sözleşme ekranında görünür; internetteki rakamlar resmi değil. Ferryhopper şartları kamuya açık değil (oran ve çerez hesap bilgisinden).
- Ferryhopper komisyonu bilet tutarı + ücret üzerinden; yan hizmetler dahil değil. Oran artışı sezonluk kampanya ya da performansa göre görüşülebilir.
- Strateji: Booking'in çerezi tek oturum; okur sekmeyi kapatınca takip biter. DiscoverCars'ın çerezi 365 gün. Araç kiralama eklemek otel eklemekten daha kârlıdır; yeni sayfa açarken bu gözetilir.
- Hesabı açık ama hiç kullanılmayan (Travelpayouts) programlar: Aviasales %40 / 30 gün · Hostelworld %40 gelir payı / 30 gün · Vio.com %40–64 gelir payı / 30 gün · EKTA (sigorta) %25 / 30 gün · Airalo (eSIM) %12 / 30 gün · GetRentacar %10 / 90 gün · Kiwitaxi (transfer) %9–11 / 30 gün · Welcome Pickups %8–9 / 45 gün · Viator %8 / 30 gün · Tiqets %3,5–8 / 30 gün.
- Tiqets için ayrı hesap yok; kullanılacaksa önce hesap açılır.

## Link kalıpları

### Travelpayouts (Booking, GetYourGuide, DiscoverCars, Omio)

```
https://tp.media/r?campaign_id=<C>&marker=767959&trs=565047&p=<P>&sub_id=<ETİKET>&u=<kodlanmış hedef>
```

| Program | campaign_id | p |
|---|---|---|
| Booking.com | 84 | 2076 |
| GetYourGuide | 108 | 3965 |
| Omio | 91 | 2078 |
| DiscoverCars | 117 | 3555 |
| Yesim | 224 | bilinmiyor |

- marker `767959` (hesap aliumutuyanik@gmail.com), site trs `565047`. `sub_id` defterdeki id ile birebir aynı.
- Link üretmek için API gerekmez; eklentideki üreteç (`gbc_aff_tp_uret`) ağ isteği atmadan bu kalıbı kurar. Sorgu dizisi elle kurulur (hedef iki kez kodlanmamalı).
- Kalıptan yeni satır üretmek serbesttir; tek şart hedefi açıp doğrulamak ve önce deftere yazmak.
- Travelpayouts API'si yalnız kısa biçim üretir; istatistik ucu (`X-Access-Token`) kazancı sub_id bazında verir.
- Kanal başına kaynak kimliği (trs): site 565047 · YouTube 566676 · Instagram 566677 · Pinterest 566678 · Facebook 566679 · TikTok 567025 · Bülten 574771. Doğru trs seçilirse hangi kanalın kazandırdığı ayrışır.
- Yesim: `p` değeri okunmadı. Ara çözüm olarak kısa bağlantıya `?sub_id=esim_yesim` eklendi (25 Eylül 2026). Kalıcı çözüm: Travelpayouts'ta SubID kutusu `esim_yesim` ile yeniden üretip uzun bağlantıyı deftere yazmak.

### GetYourGuide doğrudan (yalnız sosyal)

```
https://www.getyourguide.com/tr-tr/<hedef>/?partner_id=8XLVICW&utm_medium=online_publisher&cmp=<kampanya>
```

- `cmp` şart; yoksa veri `no_reseller_campaign` torbasına düşer.
- `/tr-tr/` kullanılır; GYG slug'ı kendisi Türkçesine çevirir, partner kimliği korunur.
- YouTube için `cmp` içinde "youtube" geçer. Video başına ayrı ad (`roma-youtube`), widget için `w_` ön eki. Rapor: panel → Analytics → Campaigns.

### Ferryhopper (doğrudan)

```
https://www.ferryhopper.com/tr/<yol>?aff_uid=gzgbr&utm_source=affiliate-link&utm_medium=in-house&utm_campaign=gzgbr&utm_content=gezginbirchef&ti=<defter_id>
```

- `ti` panelde Tracking ID sütununda görünür; her satırda satırın kendi id'sine eşit.
- Türkçe için `/tr/`; Living Greece (İngilizce) için `/en/`.
- Rota sayfası: `/tr/ferry-routes/direct/<kalkis>-<varis>`. Liman ön seçimi: `&initial=PIR,JTR` (Pire → Santorini). Tek limandan tüm hatlar: `&initial=PIR`.
- Yalnız Ferryhopper'ın verdiği ya da onayladığı bağlantılar kullanılır. Ücretli reklam doğrudan Ferryhopper'a bağlanamaz; önce kendi sayfana, oradan Ferryhopper'a.
- Giriş: affiliates.ferryhopper.com, kullanıcı adı `gezginbirchef`, şifre parola yöneticisinde. İletişim: Aristeidis Remoundos (Affiliate Specialist).

### Skyscanner (Impact)

- Impact partner/hesap kimliği `7683448` (Halil'in kendi hesabı). Rapor: "Performance by SubId".
- Bağlantılar kısa (`skyscanner.pxf.io/...`) ve kalıptan üretilemez; Impact panelinden üretilir. Var olan kısa bağlantı yeni etiketle çoğaltılabilir. `subId1` şart ve satır id'sine eşit.
- İki ayrı program var: site için Affiliate Programme (ayda 5.000+ tekil ziyaretçi), sosyal kanallar için Creator Programme (1.000+ takipçi). İkisine de başvurulur.
- Widget kimliği (`data-associate-id`) `7683448`. Widget, `data-utm-term` değerini SubId2 olarak gönderir; widget tıklamaları SubId1 raporunda boş satır görünür (bozukluk değil).

### CJ Affiliate

```
https://www.jdoqocy.com/click-<PID>-<AID>?sid=<defter_id>&url=<kodlanmış hedef>
```

- PID = mecranın (promotional property) kimliği; her mecranın ayrı PID'i var. AID = bağlantının kimliği, değişmez. `sid` = kendi etiketin (defter id'si). Yayıncı kimliği 8054161.
- Aynı hedef için kanal başına ayrı bağlantı üretilir (AID ve hedef korunur, yalnız PID değişir); CJ raporunda hangi kanalın kazandırdığı ayrı görünür.
- CJ takip alan adları dönüşümlüdür: jdoqocy.com, dpbolvw.net, tkqlhce.com, anrdoezrs.net, kqzyfj.com, ftjcfx.com, awltovhc.com, lduhtrp.net, tqlkg.com, gopjn.com, emjcd.com, sjv.io.
- Defter ön eki `cj_`, ağ sütununa `CJ`. İlk gerçek takip linki gelmeden defterde CJ satırı açılmaz.
- CJ jetonu koda yazılmaz; API Merkezi'nde durur.

### Booking hedef adresleri

- Kalıplar: şehir `/city/{ulke}/{slug}.html` · bölge `/region/` · semt `/district/{ulke}/{sehir}/{semt}.html` · ülke `/country/{ulke}.html` · tesis `/hotel/{ulke}/{slug}.html`.
- Slug tahmin edilemez. Ölçülmüş sapmalar: Rodos Ixia → `city/gr/ixos.html` · Atina merkez → `district/gr/athens/athenscitycentre.html` · Rodos Eski Şehir → `district/gr/rodos/rhodesmedievalcity.html` · Sant Antoni → `city/es/san-antonio-de-potmany.html` · Budapeşte 13. bölge → `angyalfold` · Nafplio → `nafplion`.
- Semt sayfası yoksa (Roma, Cagliari) hedef şehir sayfası kalır, `sub_id` semte göre ayrılır.
- Booking `/hotel/` sayfalarını ve bazı semt sayfalarını bota kapatıyor; doğrulama arama sonucundaki Booking başlığından yapılır.

### DiscoverCars hedef adresleri

- İtalya üçe bölünmüş: `italy-mainland`, `italy-sicily`, `italy-sardinia`. İspanya'da Balear adaları ayrı: `/spain-balearic-islands/ibiza`. Yunanistan tek yolda: `/greece/<sehir>`.
- `/italy/rome` çalışıyor; havalimanı yolları her zaman yok, açılıp doğrulanır.

### GetYourGuide hedef adresleri

- Şehir slug'ları tahmin edilemez (`seoul-l303` Tunus, `rhodes-l1174` Abruzzo, `mykonos-l286` Meksika, `kos-l818` Asya çıktı). Doğrulama: sayfa çekilip `rel="canonical"` okunur (h1 boş dönebilir).
- Türkçe tarafta slug değişebilir (`athens`→`atina`, `budapest`→`budapeste`), `delphi` aynı kalır.

### Omio hedef adresleri

- Feribot sayfalarında varışın sonuna 5 karakterlik hash gelir (`omio.com/ferries/mykonos/tinos-u6zer`); hash'siz biçim 410 döner. Hash tahmin edilemez, her rota tek tek aranır.
- Tren ve otobüs sayfaları genelde hash'siz; İstanbul-Budapeşte otobüsü hash'li. İtalya/Sardinya feribot rotaları hash'siz çalışıyor. Her rota satmıyor; açılıp doğrulanır.

## Kanal kuralları

### Hangi program hangi mecrada

- **Booking.com sosyal medyayı yasaklıyor** ("No Social Media unless explicitly approved"). Booking bağlantısı YouTube, Instagram, TikTok, Pinterest, Facebook ve WhatsApp gruplarında paylaşılmaz. Sosyalde siteye yönlendirilir, Booking bağlantısı site üzerinden verilir.
- Yesim en serbest program: web sitesi şartı yok, sosyal medya serbest (platform kuralına uyarak).
- Marka üstüne reklam hiçbir programda izinli değil; Skyscanner ve GetYourGuide'da açıkça yasak.
- Yasak kanal için bağlantı üretilmez (panel CJ çoğaltmada "YASAK" yazar).

### GetYourGuide uygulama programı

- Kurulum linki (adres): indirilirse $2, kod gerekmez. İndirim kodu (kelime): okura %5, sana %8. Bağımsız çalışırlar.
- **Tek yasak:** GYG uygulama kurulum linki ve indirim kodu web sitesine konmaz. Blog, Reddit, Quora, açık Facebook sayfası ve Pinterest de yasak. Kural: link kalıcı yere, kod geçici yere.
- Instagram/TikTok bio ve story, DM, WhatsApp/Telegram/Discord, e-bülten: link ve kod serbest. Podcast: kod sözlü. Akış gönderisi: link yeni gönderilerde, kod sözlü. YouTube: link belirsiz, kod yalnız sözlü (videoda söylenir, açıklamaya yazılmaz).
- Linktree gibi açık bio sayfasına kod yazılmaz, link konabilir. Link Instagram bio'suna sabit (5 link sığıyor).
- Tıklama saymaz, gerçek indirme gerekir; yeniden kurulum saymaz. %5 indirim ilk rezervasyon ve yeni kullanıcı için. iOS'ta takip reddedilirse kurulum organik görünebilir.

### GetYourGuide Creator Community

- Kabul edildi; Instagram @gezginbirchef hesabına kayıtlı.
- **Sponsorlu deneyim (voucher):** nakit değil, GYG kredisi. Tutar talep edilmez; GYG başvurunun incelendiği gün son 5 Reel'e bakarak hesaplar:

| Son 5 Reel toplam izlenme | Bütçe | Viral olursa |
|---|---|---|
| 10 bin altı | 30 EUR | 50 EUR |
| 10–25 bin | 50 EUR | 100 EUR |
| 25–50 bin | 100 EUR | 150 EUR |
| 50–100 bin | 150 EUR | 200 EUR |
| 100 bin üstü | 200 EUR | 250 EUR |

  - TikTok'ta alt sınır 15 bin. Viral: Instagram'da ortalama erişimin 3–10 katı, TikTok'ta takipçinin 10–100 katı.
  - Döngü (sıra değişmez): tur seç, tarih 2 hafta–2 ay arası → Opportunities → Sponsored Experience → Apply → inceleme 4–7 iş günü → voucher gelir → rezervasyonu kendin yapıp kodu girersin → gidersin → 14 gün içinde 1 Reel veya TikTok → içerik incelemesi 7 iş günü.
  - Kendi paranla gidip sonra etiketlemek işe yaramaz.
  - Zorunlu etiketler: `@getyourguide` `@getyourguidecommunity` `#getyourguidecommunity` `#getyourguide`.
  - Sınırlar: ilk seferde 1 açık başvuru, sonra 2; bir voucher ile 2 rezervasyon; en çok 30 gün erteleme; yılda 12–20 tur.
  - Yasak turlar: hop-on hop-off · hayvanat bahçesi · tiyatro ve müzikal · gece turları · spa ve wellness · silah · zincir restoran · spor bileti. Uygun: yemek turları, pazar turları, yemek atölyeleri, şehir turları, tekne turları, müze biletleri.
  - İçerik kayıtlı hesaptan paylaşılır; ikinci hesap için Community yöneticisine yazılır.
- **Referral:** kabul edilen her yeni üye için $8 nakit, üst sınır yok, blog dahil her yere konabilir. Davet edilenin şartı: 3.000+ takipçi, Instagram veya TikTok, iki haftada bir paylaşım, içeriğin %80'i seyahat, profil açık. Kendi ikinci hesabını davet etme.
- **YouTube sponsorluğu:** dönemsel, şu an kapalı. Açılınca hemen kaydolunur (geriye dönük saymaz). Ödül (komisyonun üstüne, 2024 rakamı): 2 rez. 30 EUR · 5 → 80 · 10 → 150 · 20 → 300 · 30 → 500 · 50 → 1.000 EUR + 200 EUR voucher.
- **Storefront:** seçilen deneyimlerden vitrin, %8; site yasağı buna işlemez, bio linki için uygun. Henüz kurulmadı.
- Kanal kuralını çiğnemenin bedeli tek üyeliğe bağlı her şeydir (Community, sponsorlu deneyim, referral, uygulama programı, YouTube sponsorluğu).
- GYG API alınamıyor (Basic için aylık 100.000 ziyaret şartı).

### Birden fazla hesap

- Ortaklık ve kurulum linki hangi hesaptan paylaşılırsa paylaşılsın sana yazılır. Sponsorlu deneyim içeriği kayıtlı hesaptan paylaşılır.

### CJ mülkleri

- CJ'de her kanal ayrı promotional property: site, YouTube Gezi, YouTube Yemek Tarifleri, Instagram, TikTok, Pinterest, Facebook, Living Greece (YouTube / Instagram / TikTok), e-posta bülteni. Reklamveren onayı mecra bazında verilir.

## Widget'lar

- GYG widget'ları Travelpayouts panelinden alınır (tek ağ, tek ödeme). Betik kalıbı: `https://tpemb.com/content?trs=565047&shmarker=767959&campaign_id=108&promo_id=XXXX`. promo_id: 8412 Auto-Update (tercih edilen) · 4040 Things to Do in a City · 7258 Tour Availability Calendar.
- Ferryhopper: Search widget panelden, API anahtarı gerekmez; Trips widget API anahtarı ister (parola yöneticisinde). Banner için Aristeidis'e yazılır.
- Skyscanner widget: yukarıdaki karar; ağırlığı düşürülemiyorsa düz link.

## Doğrulama

### Ekledikten sonra altı kontrol (tarayıcıda, `.entry-content a[data-aff]`)
1. Ok sayısı satır başına tam 1.
2. Çift ok yok.
3. `rel` içinde `sponsored`.
4. `&u=` hedefi çözülüp doğru adrese çıkıyor.
5. `sub_id` korunmuş.
6. Aynı id sayfada iki kez geçmiyor.

- Travelpayouts betiği sayfa yüklendikten ~1,5 sn sonra `tp.media` adresini `emrldtp.cc/re` yapar ve `journey_id/trace_id/page_url` ekler; sub_id ve hedef korunur, normaldir. Tıklayınca adres çubuğunda sub_id görünmez.
- Kısa bağlantılar (`pxf.io`, `tpx.li`) `u=` taşımaz; "hedefsiz" çıkmaları kusur değildir, hedefleri ağ panelinden doğrulanır.
- Ortaklık sayımı canlı sayfadan yapılır (`a.gbc-in`), ACF'den değil.

### Link yapısı kontrolü (istek atmadan)
- tp.media: marker 767959, trs 565047, campaign_id/p eşleşmesi doğru, sub_id dolu ve `data-aff` ile aynı, `u=` geçerli.
- tpx.li: HATALI (sub_id rapora düşmez). pxf.io: `subId1` şart. CJ: `sid` şart. Ağ parametresi olmayan doğrudan satıcı linki: "doğrulanamadı".

### Sağlık taraması hükümleri (hedef sayfa için)
- 2xx/3xx çalışıyor · 403/429 ağ/bot engeli (sağlam sayılır) · 404/410 ÖLÜ (acil) · 5xx ya da bağlantı hatası ulaşılamadı.

## Tuzaklar

- Ölü alana kısa kod konursa basılmaz (Symi'de `card_trip_list` → `card_tips_list`'e taşınınca çalıştı).
- `royal_mcp_acf_update_field` wysiwyg alanlarda `[gbc_aff]` kısa kodunu siler; kısa kod içeren alanlar `wp_acf_update_fields` ile yazılır.
- `royal_mcp_acf_get_fields` işlenmiş değer döndürür (kısa kod HTML'e dönüşmüş); geri yazılırsa kısa kod kalıcı bozulur. Değer ham metadan okunur. Bkz. Teknik.
- Gezi Hızlı Bilgiler alanı `<a>` siler; Liste `table_guide_listed` ve `rehber_faq` kısa kod çalıştırmaz; Rota'da yalnız giriş metni çalıştırır.
- Defter değişikliği önbelleği temizlemez; Purge All yapılmazsa "değişiklik çalışmadı" sanılır.
- Kısa link ucu (snippet 30926) silindi; kısa link üretmeye çalışılmaz.
- Elle yazılmış ortaklık bağlantısı olan sayfalar var (23 sayfada 173 bağlantı, ör. Atina 22690); merkezî yönetim ve atıf için kısa koda çevrilmeleri gerekir.

## Ödemeler

| Gelir | Kanal | Eşik | Zamanlama |
|---|---|---|---|
| Travelpayouts (Booking, GYG site, DiscoverCars, Omio, Yesim) | Travelpayouts paneli | 400 EUR | Bütün markalar tek ödemede |
| Ferryhopper | Doğrudan, fatura karşılığı | 100 EUR | Aylık; eşik dolunca ay başında haber, fatura ulaşınca ödeme; altında kalan devreder |
| GYG rezervasyon komisyonu (sosyal) | Banka veya PayPal | 50 EUR | Ayın 2'sinde fatura, 5–10 arası ödeme |
| GYG uygulama kurulumu ($2) | Lumanu | — | 3 ayda bir |
| GYG referral ($8) | Lumanu | — | Q1 Mayıs · Q2 Temmuz · Q3 Ekim · Q4 Ocak |
| Skyscanner | Impact | — | Impact takvimi |

- GYG komisyonu aktivite gerçekleştikten sonra hak edilir.
- CJ ödeme durumları: New (düştü, kesinleşmedi) · Extended (bir ay uzatma) · Locked (genelde ayın 10'unda kilitlenir) · Closed (11'inde kapananlar ~16'sında, 22'sinde kapananlar ~28'inde ödenir). Türkiye için CJ ödemesi Payoneer üzerinden; PayPal ve kredi kartı yok. Hesabın para birimi bir kez seçilir, geri alınamaz; farklı para biriminden satışlarda %3 dönüşüm farkı.
- Fatura bilgileri (şahıs şirketi, vergi bilgileri) parola yöneticisinde / Halil'de; kılavuza yazılmaz.
- Açık soru (mali müşavire): yurt dışı ortaklık geliri doğrudan TL hesabına mı, EUR (Payoneer) hesabı üzerinden mi alınmalı; beyan açısından hangisi temiz.

## Açık işler (eski Kılavuz, 22 Eylül 2026 itibarıyla)

### Halil yapacak
- Yesim uzun linki: SubID `esim_yesim` ile yeniden üretip deftere yazmak.
- Skyscanner widget tıklamalarının Impact raporuna düşüp düşmediğini ilk haftadan sonra kontrol etmek.
- Ferryhopper: Aristeidis'e linklerin yayında olduğunu bildirmek.
- GYG Circle daveti: hoş geldin e-postasına "Could you please send me the Circle community invite?" yazmak.
- GYG referral / storefront / uygulama linki + indirim kodu: panelden alıp Claude'a vermek.
- Muhasebeciye TL/EUR sorusunu sormak.
- Atina: Apollo Hotel'in doğru otel olduğunu teyit etmek (`booking.com/hotel/gr/apollo-athens.html`).
- CJ: Promotional Properties'e bütün mecraları eklemek; Booking için örnek takip linki almak; Developer Portal'dan jeton oluşturup API Merkezi'ne girmek; reklamveren 5096493'ün hangi markaya ait olduğunu teyit etmek.
- Booking kararı: Travelpayouts'ta mı kalacak, CJ'ye mi geçecek (oran ve çerez ölçülmeden karar verilmez).

### Claude yapacak
- Hiç ortaklık bağlantısı olmayan sayfalar için ayrı kampanya.
- Defterde hem gerçek satır hem TAKMA kaynağı olan id'lerin temizliği.
- Elle yazılmış ortaklık bağlantılarını kısa koda çevirmek.

### GYG Community yöneticisine sorulacaklar
- %8 üstü komisyon kademeleri; YouTube sponsorluğunun açılış tarihi; kurulum linki tek başına kodsuz siteye konabilir mi; ikinci Instagram hesabı eklenebilir mi.

### Cevabı bulunamayanlar
- GYG %8 üstü kademeler · YouTube sponsorluğu 2026 rakamları · voucher son kullanma tarihi · referral'da kimlerin davet edilemeyeceği · sponsorlu deneyimde içerik teslim edilmezse ne olduğu · Ferryhopper panelinde `ti` kırılımı rapor olarak görünüyor mu.
