---
name: gbc-gezi-rehberi
description: gezginbirchef.com'da videosu çekilmiş bir şehir için Gezi şablonlu (WPCode 22607/22608) rehber açarken, doldururken ya da düzeltirken kullan. Sayfanın baştan sona kurgusunu (video, kelime, giriş, bütçe, ulaşım, konaklama, yerler, yemek, gitmeden, Noel gibi özel bölümler, SSS, sağ sütun, ortaklık, şema) if/else kararlarıyla taşır. Her işin sonunda öğrenilenleri kendine yazar.
---

# GBC Gezi Rehberi Kurgusu

Okunabilir kopya: Claude Docs, "GBC Gezi Rehberi Kurgu Kitabı" (https://claude.ai/code/artifact/c52125f1-4023-4896-a654-42a57f1e8c02).

Bu skill bir gezi rehberini **baştan sona, sayfadaki sırayla** kurar. Her adımda
"hangi alan, ne zaman doldurulur, ne zaman boş kalır" kararı yazılıdır.
Her şey bu tek dosyada:
- Bölüm 0-9: adım adım kurgu (kısa, karar odaklı).
- **Ek A:** ayrıntılı kural defteri (her alanın standardı, HTML kalıpları, tablolar).
- **Ek B:** öğrenilenler (geçmiş hatalar ve doğrusu, en yeni en üstte).

**Başlamadan önce Ek B'yi oku.** Ek B'deki bir ders bu dosyanın geri kalanıyla
çelişirse ders kazanır (daha yenidir) ve ilgili bölüm de güncellenir.

Öncelik sırası: GBC İşletim Anayasası > ortaklık defteri (30120) > kural defteri > bu skill.

---

## 0. Değişmez kurallar (her sayfada)

- **ESKİ FORMAT YASAK (1 Ekim 2026, Halil):** Aşağıdakiler hiçbir Gezi sayfasına yazılmaz;
  görülürse yeni formata çevrilir:
  - yer ve ipucu kartı yerine düz `<ul class="gbc-check">` listesi
  - "€/gece" bütçe; `gz-chef-note gz-not--tasarruf` tek bütçe kutusu
  - `card_safe_list` (acil numara bölümü), `card_trans_list` (şehir içi ulaşım)
  - tek sayfa modunda dolu `rel_*` (kutular 3'e iner, geniş düzen kapanır)
  - `trip_links`'e post ID ya da ID dizisi (satır biçimi `Ad | URL | not | aff_id`)
  - "2-3 gün", "4-5 gün" gibi rozetle çelişen rota; videoda olmayan yer kartı
  - Hızlı Plan'da 5 adım ya da uçaksız plan
- Taslakta kalır; yayın Halil'in onayıyla.
- Alkol yasak (adı da geçmez, şarap turu bağlantısı konmaz).
- Em dash, elle ok (→ ↗), emoji, "şef" kelimesi, klişe listeleri yok.
- **Tek yer:** bir bilgi sayfada bir kez, en güçlü yerinde. SSS dahil.
- Rakamın yanında kaynak ve son kontrol tarihi. Doğrulanmamış rakam yazılmaz.
- Gezilecek yerler kartına **videoda olmayan yer girmez**.
- Birinci ağız ("gittik, yedik") yalnız videoda karşılığı varsa.
- Kısa kod içeren alan yalnız `wp_acf_update_fields` ile yazılır. ACF olmayan
  metalar (`gz_yakin_*`, `gun_cevap`, `route_ozet_*`, `h2_event`, şema) royal
  `wp_update_post_meta` ile yazılır.
- Kısa bağlantılar (pxf.io, tp.media, tpx.li) açılmaz, tahmin edilmez.
- Her rehbere `gz_api_kilit` = 1 metasını koy (editör tuzağına karşı teknik kilit,
  Ek A 5.14). Editörden eski sekmeyle kayıt iki kez sayfayı sıfırladı.
- Her yazımdan sonra yazdığın alanları scratchpad'e JSON olarak da kaydet
  (editör tuzağına karşı geri yükleme kaydı, bkz. bölüm 9).
- **Sayfa Denetimi hedefi %100** (en az %90): iş bitince gbc-core Sayfa Denetimi
  çalıştırılır, Yapılacaklar'daki her madde düzeltilir, denetim yeniden çalıştırılır (Ek A 5.16).

---

## 1. Hazırlık: video, kelime, mod

### 1.1 Video
```
Transkripti çek (youtube_video_transcript).
EĞER video yalnız bu şehirse
    → hero_video_chapters = videonun tamamından en önemli 6-10 an
    → hero_video_caption boş ya da "Videonun tamamı {Şehir}"
DEĞİLSE (çok şehirli video)
    → şehrin bölümünün başlangıç ve bitiş saniyesini bul
    → hero_video_chapters = YALNIZ o bölümün damgaları, başka şehir girmez
    → hero_video_caption = "Videonun {Şehir} bölümü MM:SS'de başlıyor"
Transkriptten üç liste çıkar: gezilen yerler, yenen yerler, fiyat geçen yerler.
```
Biçim: `MM:SS | Başlık | Kısa açıklama`. Alkollü sahneye damga konmaz.

### 1.2 Kelime araştırması
- Ubersuggest `keyword_suggestions`, dil `tr`, locId `2792`.
- Tohumlar: `{şehir}`, `{şehir} gezilecek yerler`, sezon konusu (`{şehir} noel pazarı`).
- İki yazım: Strazburg / Strasbourg.
- Ayıkla: futbol, maç, hava durumu, üniversite, cadde adı.
- Hacim sıralı listeyi not al. Bütün başlık kararları buna göre verilir.

### 1.3 Sayfa modu
```
EĞER bir alt konunun (yerler, yemek) sitede ayrı sayfası var
  VE kelimesi ayda 1.000+ aranıyor VE 10'dan fazla durağı var
    → MERKEZ SAYFA (Atina): rel_places / rel_food = alt sayfa id,
      limit_places = 4 (ilk 4 kart görünür, "tamamı" düğmesi alt sayfaya gider)
DEĞİLSE
    → TEK SAYFA (Sorrento, Strazburg): bütün rel_* BOŞ, limit_* boş
EĞER konu ayda 100+ aranıyor ama ana bölümlere girmiyor ve 1.000 altında
    → ÖZEL BÖLÜM (⑪b yuvası; Noel pazarı, Messina)
EĞER konu ayda 1.000+ ve sayfası yok
    → yeni sayfa ÖNER, açma (Halil onaylar)
```
**Uyarı:** `rel_*` alanlarından biri doluysa geniş düzen kapanır (bkz. 2).
Tek sayfada `rel_*` dolu kalırsa kutular 3'e iner, alt bölümler sıkışır.

---

## 2. Sayfanın haritası (şablonun bastığı sıra)

```
ÜST SATIR
  SOL: video · H1 · içindekiler · giriş · bütçe · nasıl gidilir · nerede kalınır
  SAĞ: hızlı bilgiler · ne zaman · kaç gün · videoda en iyi anlar ·
       Hızlı Plan 6 adım · sonra nereye · (alakalı YouTube videoları)
ALT SATIR (geniş, iki sütunu kaplar)
  gezilecek yerler (+ harita) · ne yenir · gitmeden bilmeniz gerekenler ·
  ⑪b özel bölümler (Noel vb.) · SSS · bu rehber nasıl hazırlandı
EN ALT
  fotoğraf galerisi (tam genişlik)
```
Geniş düzen: `layout_genis` = "1" açık, "0" kapalı; boşsa `rel_*` boşken açık.
Boş bırakılan her bölüm basılmaz. Her alanı doldurmak zorunlu değil;
okura yardım etmeyen alan boş kalır.

Aşağıdaki adımlar bu sırayla doldurulur.

---

## 3. Üst satır, sol sütun

### 3.1 Başlıklar ve SEO
- Post title = `hero_custom_title`: en yüksek hacimli seyahat kelimesiyle başlar,
  yıl içerir. Örnek: "Strazburg Gezilecek Yerler 2026: Nerede, Noel Pazarı ve Nasıl Gidilir".
- `rank_math_title`: 30-60 karakter, kelimeyle başlar, sayı içerir.
- `rank_math_description`: 120-160 karakter, ilk 3 kelime grubu.
- `rank_math_focus_keyword`: en fazla 5 kelime, hacim sırasıyla.
- Her H2 (`h2_*`) o konunun en çok aranan kalıbı. İçindekiler H2'lerden otomatik
  kurulur, ayrıca yazılmaz.

### 3.2 Giriş (`hero_intro_text`)
- `<section class="intro-block"><p class="intro-lead"><strong>İlk cümle.</strong> …</p></section>`
- **İlk cümle en çok aranan soruya cevap** ("strazburg nerede" en yüksekse ilk
  cümle nerede olduğunu söyler). İki yazım birlikte: "Strazburg (Strasbourg)".
- 50-60 kelime, kısa cümleler. En önemli kelimeler ilk iki paragrafta.
- Girişteki sayı sayfayla tutar ("dört durak" = dört kart).

### 3.3 Bütçe (`budget_*`)
- **Kişi başı, bir günlük, yemek dahil. Konaklama ve uçak HARİÇ.** "€/gece" yazılmaz.
- Üç bant: `budget_low/mid/high_price` + `_desc` (o günün kalemleri).
- `budget_note` ilk paragraf sabit: "Rakamlar kişi başı ve bir günlük. **Yemek
  dahil; konaklama ve uçak bileti hariç.**" İkinci paragraf yöntem ve tarih.
- `card_budget_desc` sırası: kaynak cümlesi · Şehir içi ulaşım · Giriş ve turlar
  (+ `gyg_{şehir}`) · Yemek · Ücretsiz olanlar.
- Kaynak: bizim fişimiz > resmî tarife > rehber bandı. Bilet satış sitesi kaynak değildir.

### 3.4 Nasıl gidilir (`trans_*_detail`)
```
EĞER İstanbul'dan şehre direkt uçuş var
    → trans_plane_detail: havalimanı + merkeze ulaşım + sky_{şehir}
DEĞİLSE
    → en yakın direkt uçuşlu havalimanı ("Strazburg'a değil, Basel'e uçun"),
      direkt seferin bittiği tarih, sky_{havalimanı}
    → trans_train_detail: aktarma treni + omio_{kalkış}_{varış}
EĞER araçla gezilecek köy, kale, bölge var
    → trans_car_detail: P+R / ZTL + dc_{şehir}
Otobüs, feribot yalnız gerçekten kullanılıyorsa (boşsa basılmaz).
```
Mesafe soruları ("Paris X arası kaç km") burada değil, SSS'te.
Şehir içi ulaşım (`card_trans_list`) varsayılan BOŞ.

### 3.5 Nerede kalınır
- `stay_summary`: bölgeleri tek cümlede sayar.
- `card_stay_list`: başlık kutusunda şehrin tüm otelleri `bk_{şehir}` (Booking tek
  oturum çerezi, bölümün başında). Sonra **bölge bölge** kart:
  Kime uyar · Göze alın · "Aşağıdaki bağlantı doğrudan … otellerini açıyor." · `bk_{şehir}_{bölge}`.
- Bölge bağlantısı o bölgenin otellerini açar (Booking `district/…` ya da `landmark/…`),
  adres arama sonucundan doğrulanır.

---

## 4. Üst satır, sağ sütun

| Kart | Alan | Kural |
|---|---|---|
| Hızlı bilgiler | `info_*`, `api_weather_city`, `api_timezone`, `info_esim_link` | Acil numara yalnız burada. eSIM yalnız burada. |
| Ne zaman | `season_*_temp`, `season_*_durum`, `kart_mevsim` | "{Şehir}'a Ne Zaman Gidilir?" |
| Kaç gün | `gun_cevap`, `route_ozet_1/3/7`, `route_onerilen`, `route_*_desc`, `kart_gun` | Rozet 1/3/7 sabit; "2 gün" yalnız `gun_cevap`'ta. `route_*_desc` "<strong>1 gün:</strong>" / "3 gün:" / "7 gün:" ile açılır. Özetler kümülatif. |
| Videoda en iyi anlar | `hero_video_chapters` | 1.1'deki karar. |
| Hızlı Plan | `gz_yakin_bas`, `gz_yakin_ust`=1, `gz_yakin_yerler` | Aşağıda. |
| Sonra nereye | `trip_links` | Aşağıda. |
| Alakalı YouTube | `related_videos` | `VideoID | Başlık | Not`; aynı şehrin başka videoları varsa. |

**Hızlı Plan: Gitmeden 6 Adım** (tam 6 satır, sıra sabit):
1. Uçak `sky_{havalimanı}_yan` (her zaman ilk)
2. Otel `bk_{şehir}_yan`
3. Aktarma treni / transfer `omio_…_yan`
4. Şehre özgü tur ya da bilet `gyg_…_yan`
5. İkinci tur/bilet ya da yedek otel
6. Araç `dc_{şehir}_yan`

Satır: `[gbc_aff id=X_yan stil=yan]N. Adım: Başlık | Kısa açıklama[/gbc_aff]`.
Defterde bağlantısı boş satır basılmaz; Skyscanner bağlantısı bekleniyorsa satır
yine yazılır, Halil'den kısa bağlantı istenir.

**Sonra nereye (`trip_links`)**, yakın destinasyonlar:
- Satır `Ad | URL | kısa not | aff_id`; `## Başlık | alt` yeni kart.
- Üç grup: "{Şehir}'dan Trenle" (Omio) · "Arabası Olmayanlar İçin Turlar" (GetYourGuide) ·
  "{Bölge} Rehberlerimiz" (iç sayfalar, `wp_search_posts` ile doğrulanmış).
- Günübirlik yerler varsayılan olarak buraya gider, gövdeye değil.

---

## 5. Alt satır (geniş)

### 5.1 Gezilecek yerler (`card_places_list`)
- Yalnız videodaki yerler, video sırasıyla.
- İskelet: `gz-places-wrapper` > `gz-plan-baslik` > numaralı `gz-place-card`.
- Kart: görsel · `gz-inner-title` + `<small>` · etiketler (önce video çipi, sonra
  2-3 `<b>`: ücret, süre, yer) · 2-4 kısa paragraf · biletliyse `gyg_{şehir}_{yer}`.
- Kart başlıkları arama kalıbında.
- İkinci grup (deniz, sahil) `gz-mavi`, numaralar devam eder.
```
EĞER durak sayısı çok VE "{şehir} gezilecek yerler" ayda 1.000+
    → alt sayfa (haritalı liste) + rel_places + limit_places = 4
DEĞİLSE → hepsi bu sayfada
EĞER Google My Maps haritası var → card_places_tag (bölüm sonunda basılır)
```

### 5.2 Ne yenir (`food_summary`, `card_food_list`, `card_food_note`)
```
food_summary HER ZAMAN en üstte: şehrin en meşhur 3-6 yemeği.
EĞER videoda mekâna gittik/yedik
    → food_summary: meşhurlar + <strong>Denediklerimiz:</strong> + <strong>Bu gezide denemediklerimiz:</strong>
      ("yemediğimizi yemiş gibi anlatmıyoruz")
    → card_food_list: mekân kartları (gz-kirmizi), "Mekân adı: öne çıkan yemek", video çipi
    → card_food_note: <strong>Hesap:</strong> mekân, kişi, toplam, kişi başı
DEĞİLSE
    → food_summary: "{Şehir}'de oturup yemek yemedik; aşağıda şehrin nesi meşhur, onu yazıyoruz."
    → card_food_list: <ul class="gbc-check"> meşhur tatlar
EĞER mekân çok VE "{şehir} ne yenir / nerede yenir" ayda 1.000+
    → alt sayfa + rel_food (+ limit_food)
```
`h2_food`: "{Şehir}'da Ne Yenir? {3 meşhur ad}". Alkollü içecek yok.

### 5.3 Gitmeden bilmeniz gerekenler (`card_tips_list`)
- 4-8 kart, soru ya da sürpriz başlığı.
- Konular: hangi havalimanı · önceden ayırtılacaklar · vize ve sınır ·
  pratik güvenlik (çanta kontrolü, cep hırsızlığı) · kıyafet ve hava · şehre özgü sürpriz.
- Sezon tarihleri burada değil (özel bölümde). Acil numara yok.

### 5.4 Özel bölümler (⑪b, mevsim ve etkinlik)
| Konu | Alan | Başlık metası |
|---|---|---|
| Noel pazarı, festival | `card_event_list` | `h2_event` |
| Gövdede kalacak komşu konu | `card_trip_list` | `h2_trip` |
| Alışveriş | `card_shop_list` | `h2_shop` |
| Tarih | `card_hist_list` | `h2_hist` |
| Çocukla | `card_kid_list` | `h2_kid` |
| Akşam | `card_night_list` | `h2_night` |
| Fotoğraf noktaları | `card_photo_list` | `h2_photo` |

Sezonluk bölüm kart sırası:
1. Bu yılın tarihi; açıklanmadıysa "henüz açıklanmadı" ve tahmin ayrı cümlede.
2. Önceki yılların doğrulanmış tarihleri.
3. Saatler.
4. Nerede kuruluyor.
5. Öne çıkan unsur, resmî ifadeyle.
6. Nasıl gidilir ve P+R.
7. Komşu şehirle birleştirme.
8. Rehberli tur (`gyg_{şehir}_{konu}`).

Resmî siteye dış bağlantı konur.

### 5.5 SSS (`faq_1..10`)
- 5-8 soru, hacim sırasıyla, **yalnız sayfada cevabı olmayanlar**:
  mesafeler, nüfus, başkonsolosluk, outlet, "hangi ülkede" (girişte yoksa).
- Cevap 1-2 cümle, kaynaklı.

### 5.6 Galeri ve görseller
- Videodan kare, 1200x675 WebP (PIL LANCZOS, quality 85, method 6).
- Görsel yalnız yerler, yemek ve özel bölüm kartlarında.

---

## 6. Ortaklık (defter 30120)

- Önce deftere satır, sonra sayfaya kısa kod. Aynı id sayfada bir kez.
- Travelpayouts: `https://tp.media/r?campaign_id=C&marker=767959&p=P&trs=565047&sub_id=ID&u=URLENCODED`
  - Booking: C=84, P=2076
  - Omio: C=91, P=2078
  - GetYourGuide: C=108, P=3965
  - DiscoverCars: C=117, P=3555
- Skyscanner (Impact): kısa bağlantıyı Halil panelden üretir, sonuna `?subId1=ID`.
  Asistan üretemez, başka şehrin bağlantısını kullanmaz.
- Hangi ihtiyaç nerede:
  - uçak ve tren: nasıl gidilir
  - otel: nerede kalınır (önce şehir, sonra bölge)
  - tur ve bilet: yer kartları ve bütçe
  - araç: nasıl gidilir
  - sağ sütun: Hızlı Plan ve sonra nereye
- Hedef URL yalnız birebir görülen adresten; slug tahmin edilmez.

## 7. Şema ve etiketler
- `gbc_schema_geo` = "enlem,boylam".
- `gbc_schema_yerler`: `Tür | Ad | enlem,boylam | h2_places slug'ı (60) | adres`.
- `gz_video_duraklar`: `M:SS | Durak`, yerler kartıyla aynı sıra.
- Etiketler: Ülke · Gezi · Noel Pazarları (varsa) · Vizeli/Vizesiz · VLOG · Şehir.
  "Görülecek Yerler" (1188) Gezi şablonunda yasak.
- `rank_math_pillar_content` = on.

## 8. Kontrol listesi (kaydetmeden önce)
- [ ] Alkol, em dash, ok, emoji, "şef" taraması
- [ ] Tek yer; SSS'te yukarıda cevaplanan soru yok
- [ ] Girişteki sayılar kartlarla tutuyor
- [ ] Her rakamın kaynağı ve tarihi var
- [ ] Yer kartlarının hepsi videoda var
- [ ] Kısa kod id'leri defterde var, sayfada tek
- [ ] Hızlı Plan 6 satır, 1. uçak
- [ ] `rel_*` yalnız merkez sayfada dolu (yoksa geniş düzen kapanır)
- [ ] `card_trans_list`, `card_safe_list` boş
- [ ] Rozetle çelişen gün yok
- [ ] Şema metaları dolu
- [ ] Sayfa Denetimi en az %90 (hedef %100); Yapılacaklar kutusunda açık madde yok
- [ ] Sayfa taslakta

---

## 9. Kendini geliştirme (her işin sonunda ZORUNLU)

Bu skill her işten sonra büyür. Kural atlanmasın diye:

1. **İş bitince** Ek B'nin en üstüne bir kayıt ekle:
   `Tarih · Sayfa (id) · Ne oldu · Doğrusu · Kural nereye işlendi`
2. **Halil bir düzeltme istediyse** (ör. "bütçe gecelik değil günlük",
   "5 adım değil 6, ilki uçak") bu **her zaman** bir ders sayılır ve kaydedilir.
3. Ders genel bir kuralsa:
   - bu dosyada ilgili bölümü ve Ek A'yı güncelle
   - repodaki `CLAUDE.md`'yi güncelle (Ek A ile aynı içerik)
   - sitedeki taslak "Gezi Rehberi Kural Defteri" sayfasını (31542) güncelle
   - Claude Docs'taki "GBC Gezi Rehberi Kurgu Kitabı" belgesinin öğrenilenler tablosuna satır ekle
   - sürüm numarasını artır
4. Değişikliği commit et ve gönder. Mesajda hangi dersin işlendiği yazsın.
5. Aynı hata ikinci kez görülürse kuralı daha görünür yere (bölüm 0'a) taşı.

**Sayfa "bozuldu" denirse:** önce `wp_history_list` ve sayfanın `modified`
saatine bak. Editörden eski sekmeyle kaydetme (editör tuzağı) en sık sebeptir.
Scratchpad'deki yazım kaydından ya da oturum dökümündeki
`wp_acf_update_fields` çağrılarından alanları geri yükle.

---

## Ek A. Ayrıntılı kural defteri (CLAUDE.md ile aynı)

### A0. Amaç ve ilke

- **Hız:** Videosu çekilmiş bir şehrin rehberi bu defterle tek oturumda,
  eksiksiz dolar. Her adımın girdisi ve çıktısı aşağıda yazılı.
- **Tekrar yok:** Bir bilgi sayfada tek yerde, en güçlü yerde geçer. SSS için de
  istisna yok.
- **Yardım + satış:** Her bölümde "okur burada neye ihtiyaç duyar?" sorulur.
  O ihtiyacı karşılayan ortaklık bağlantısı (uçak, tren, otel, tur, bilet, araç)
  tam o noktaya konur. İhtiyaç yoksa bağlantı da yok.
- **Kararı asistan verir:** Kelime alakası, bölümün açılıp açılmayacağı, alanın
  boş kalıp kalmayacağı arama hacmine bakılarak asistan tarafından verilir.
  Şablonda her alanı doldurmak zorunlu değil.
- **Doğrulanmamış bilgi yazılmaz:** Rakam resmî kaynaktan ya da bizim
  fişimizden gelir, yanında kaynak ve son kontrol tarihi durur.

---

### A1. İş akışı (sırayla)

| # | Adım | Girdi | Çıktı |
|---|---|---|---|
| 1 | Sayfayı bul, şablonu oku | post id | `post_content` içinde `[wpcode id="22607"]` var mı |
| 2 | Video ve transkript | `hero_video` | şehrin bölümünün başlangıç/bitiş saniyesi, gezilen yerler, yenen yerler, fiyat geçen yerler |
| 3 | Kelime listesi | Ubersuggest | hacim sıralı tam liste (bölüm 4) |
| 4 | Sayfa modu kararı | kelime listesi + sitedeki alt sayfalar | tek sayfa / merkez sayfa / özel bölüm (bölüm 2) |
| 5 | Başlık ve SEO | kelime listesi | post title, `hero_custom_title`, Rank Math, H2'ler |
| 6 | Bölümleri doldur | transkript + resmî kaynak | bölüm 5'teki alan standardı |
| 7 | Ortaklık | defter 30120 | eksik satırlar önce deftere, sonra kısa kod (bölüm 6) |
| 8 | Yan sütun | | Hızlı Plan 6 adım (1. adım uçak), kaç gün, trip_links |
| 9 | Şema | koordinatlar | `gbc_schema_geo`, `gbc_schema_yerler`, `gz_video_duraklar` |
| 10 | Kontrol listesi + Sayfa Denetimi | | bölüm 7; GBC skoru %90+ (hedef %100), bütün Yapılacaklar kapalı (5.16) |
| 11 | Taslakta bırak | | yayın Halil'in onayıyla |
| 12 | Görseller | Halil | videodan kare, 1200x675 WebP (bölüm 5.4) |

---

### A2. Sayfa modu kararı (karar ağacı)

Şablon üç modda çalışıyor. Hangisinin seçileceği arama hacmine ve sitede var
olan sayfalara bağlı.

```
EĞER şehrin alt konusu için sitede AYRI BİR SAYFA varsa
     (ör. "atina gezilecek yerler" → 22717, "atina ne yenir" → 23235)
  VE o alt konunun kelimesi ayda 1.000 ve üstü aranıyorsa
  VE alt sayfada 10'dan fazla kart/durak varsa
    → MERKEZ SAYFA (Atina modu)
      rel_places / rel_food / rel_stay / rel_trans = alt sayfa id
      Şablon o bölümde ilk 3 kartı gösterir, "tamamı" düğmesi alt sayfaya gider.
      limit_places = 4 (Halil kararı: merkez sayfada ilk 4 kart; boş kalırsa şablon 3 basar).
DEĞİLSE
    → TEK SAYFA (Sorrento modu)
      Bütün rel_* BOŞ. Dolu bir rel_* sayfayı merkez moduna sokar ve kartları 3'e keser.
      (Strazburg'da rel_places=16041 bu yüzden kaldırıldı.)

HER İKİ MODDA:
EĞER bir konu ayda 100 ve üstü aranıyor
  VE ana bölümlerin (bütçe, ulaşım, konaklama, yerler, yemek, gitmeden) hiçbirine
     doğal olarak girmiyor
  VE ayda 1.000 altında olduğu için yeni sayfaya değmiyorsa
    → ÖZEL BÖLÜM (Taormina/Messina modu): şablonun ⑪b ek bölümlerinden biri
      kullanılır, başlığı sorgu kalıbıyla ezilir.
EĞER konu ayda 1.000 ve üstü aranıyorsa
    → yeni sayfa önerilir (açmak Halil'in onayıyla).
```

**⑪b ek bölüm yuvaları** (şablon sırasıyla basar, alan boşsa bölüm basılmaz):

| Konu | Alan | Başlık metası | Varsayılan başlık |
|---|---|---|---|
| Şehir içi ulaşım | `card_trans_list` | `h2_trans_ici` | "{Şehir} İçinde Ulaşım" |
| Gövdede kalan günübirlik / komşu konu (Messina gibi) | `card_trip_list` | `h2_trip` | "{Şehir} Günübirlik Turları" |
| Alışveriş | `card_shop_list` (+`shop_tax_info`) | `h2_shop` | "{Şehir} Alışveriş Rehberi" |
| Tarih | `card_hist_list` | `h2_hist` | "{Şehir} Tarihi ve Kültürü" |
| Festival, Noel pazarı, etkinlik | `card_event_list` | `h2_event` | "{Şehir} Festivalleri ve Etkinlikleri" |
| Çocukla gezi | `card_kid_list` | `h2_kid` | "{Şehir} Çocuklu Ailelere Uygun mu?" |
| Akşam programı | `card_night_list` | `h2_night` | "{Şehir} Akşam Programı" |
| Fotoğraf noktaları | `card_photo_list` | `h2_photo` | "{Şehir} Fotoğraf Noktaları" |

Kurallar:
- `h2_*` metası ACF alanı değilse royal `wp_update_post_meta` ile yazılır.
- **Varsayılan boş kalanlar:**
  - `card_trans_list` (şehir içi ulaşım): okur uğraşmaz, bilgi "Nasıl gidilir"de verilir.
  - `card_safe_list` (acil numaralar): Hızlı Bilgiler'de zaten var; pratik
    güvenlik notu (çanta kontrolü, cep hırsızlığı) "Gitmeden" kartına girer.
- **Günübirlik yerler:**
  - Varsayılan olarak gövdeye değil, yan sütundaki `trip_links`'e ortaklık
    bağlantısıyla konur (bölüm 5.12).
  - Gövdeye yalnız konu ayda 100 ve üstü aranıyorsa `card_trip_list` +
    `h2_trip` ile girer (Messina).

---

### A3. Video ve transkript

- Transkript `youtube_video_transcript` ile çekilir.
- Video birden fazla şehri kapsıyorsa **yalnız o şehrin bölümü** kullanılır:
  - Başlangıç ve bitiş saniyesi bulunur, gerisi atılır.
  - Videonun açıklamasındaki bölüm listesi yardımcı olur.
- **`hero_video_chapters`:**
  - Yalnız o bölümün damgaları yazılır, biçim `MM:SS | Başlık | Kısa açıklama`.
  - Başka şehrin sahnesi girmez.
  - Alkollü sahne damgası konmaz.
- **`hero_video_caption`:** çok şehirli videoda "Videonun {Şehir} bölümü MM:SS'de başlıyor".
- **`hero_video_duration`:** videonun toplam süresi.
- **`gz_video_duraklar` (meta):** `M:SS | Durak adı`, yerler kartıyla aynı sırada.
- **Gezilecek yerler kartına videoda geçmeyen yer eklenmez.** Videoda olmayan
  ama aranan yer rota metnine (kaç gün) ya da SSS'e girebilir.
- **Birinci ağız** ("gittik, yedik, beğendik") yalnız transkriptte karşılığı
  varsa yazılır. Karşılığı yoksa tarafsız yazılır.
- **Yemek:**
  - Şehrin bölümünde yemek sahnesi varsa mekân kartları kurulur (bölüm 5.7).
  - Yoksa tek cümle: "{Şehir}'de oturup yemek yemedik; aşağıda şehrin nesi meşhur, onu yazıyoruz."
- **Video çipi:** her yer kartına şu biçimde eklenir:
  `<a class="gz-vid-at gz-vid-cip" href="https://www.youtube.com/watch?v=ID&amp;t=SANIYEs" target="_blank" rel="noopener">YouTube'da izle · MM:SS</a>`

---

### A4. Kelime araştırması

**Çekme:**
- `keyword_suggestions`, dil `tr`, locId `2792`.
- En az üç tohum kullanılır:
  - `{şehir}`
  - `{şehir} gezilecek yerler`
  - sezon konusu (`{şehir} noel pazarı`, `noel pazarı` gibi)
- İki yazım da aranır: Strazburg / Strasbourg, Barselona / Barcelona.

**Ayıklama:**
- Futbol, maç, hava durumu, üniversite, cadde adı (Ankara'daki Strazburg
  Caddesi gibi) atılır.
- Kalan liste hacme göre sıralanır ve sayfanın çalışma notuna yazılır.

**Yerleştirme (hacim sırasıyla):**

| Nereye | Kural |
|---|---|
| Post title ve `hero_custom_title` | En yüksek hacimli seyahat kelimesiyle başlar, yıl içerir ("Strazburg Gezilecek Yerler 2026: …") |
| `rank_math_title` | 30-60 karakter, anahtar kelimeyle başlar, sayı içerir |
| `rank_math_description` | 120-160 karakter (Sayfa Denetimi aralığı); ilk 3 kelime grubu geçer |
| `rank_math_focus_keyword` | En fazla 5 kelime, hacim sırasıyla |
| `hero_intro_text` ilk cümle | En çok aranan soruya cevap ("strazburg nerede" 2.400 ise ilk cümle nerede olduğunu söyler). İki yazım birlikte: "Strazburg (Strasbourg)" |
| İlk iki paragraf | En önemli kelimeler burada geçer |
| H2'ler (`h2_*`) | Her biri o konunun en çok aranan kalıbıyla birebir ("Strazburg Otelleri: Nerede Kalınır?", "… Uçak Bileti, Havalimanı ve Basel Treni") |
| Kart başlıkları | Soru kalıbı ("Strazburg Noel pazarı ne zaman? 2026 tarihleri") |
| SSS | Yalnız sayfada cevabı OLMAYAN aranan sorular, hacim sırasıyla (mesafe soruları, nüfus, konsolosluk, outlet) |

---

### A5. Bölüm bölüm alan standardı

#### A5.0 Sayfa yerleşimi (şablon 22607'den okundu)

```
┌───────────────────────────── ÜST SATIR ─────────────────────────────┐
│ SOL SÜTUN (gz-main-column)            │ SAĞ SÜTUN (gz-sidebar-column)│
│ ① video (bölüm damgaları altta)       │ Ⓐ Hızlı Bilgiler (vize, dil, │
│ ② H1 (hero_custom_title)              │    para, fiş, eSIM, hava)    │
│ ④ İçindekiler (H2'lerden otomatik)    │ Ⓑ Ne zaman gidilir (4 mevsim)│
│ ⑤ Giriş (hero_intro_text)             │ Ⓒ Kaç gün (1/3/7 rozet)      │
│ ⑥ Bütçe (yeşil bant, 3 sütun)         │ Ⓓ Videoda en iyi anlar       │
│ ⑦ Nasıl gidilir (uçak/tren/araç)      │ Hızlı Plan: Gitmeden 6 Adım  │
│ ⑦b Nerede kalınır (bölge kartları)    │ Ⓕ Sonra nereye (trip_links)  │
├───────────────────────── ALT SATIR (GENİŞ) ─────────────────────────┤
│ ⑧ Gezilecek yerler (kutu kutu kartlar)                              │
│ ⑨ Ne yenir                                                          │
│ ⑪ Gitmeden bilmeniz gerekenler                                      │
│ ⑪b Ek bölümler (Noel pazarı, alışveriş, tarih...; boşsa basılmaz)   │
│ ⑫ SSS                                                               │
│ ⑬ Bu rehber nasıl hazırlandı                                        │
├─────────────────────────────────────────────────────────────────────┤
│ Fotoğraf galerisi (gal_img_*; tam genişlik, en altta)               │
└─────────────────────────────────────────────────────────────────────┘
```

**Geniş düzen anahtarı (en sık bozulan yer):**
```
EĞER layout_genis meta = "1"  → alt satır tam genişlik (iki sütunu kaplar)
EĞER layout_genis meta = "0"  → alt satır da sol sütunda akar
BOŞSA (varsayılan):
  EĞER rel_places / rel_food / rel_stay / rel_routes'tan BİRİ doluysa
      → geniş düzen KAPANIR (Atina merkez sayfa modu)
  DEĞİLSE
      → geniş düzen AÇIK (Sorrento, Strazburg tek sayfa modu)
```
- Tek sayfa modunda `layout_genis` = "1" metası da yazılır (gbc-core kılavuzu:
  bu olmadan Gezi sayfası yayına çıkmaz); böylece `rel_*` kazara dolsa bile geniş düzen kapanmaz.
- Tek sayfa modundaki bir rehberde `rel_*` doluysa hem kartlar 3'e kesilir hem
  alt bölümlerin tam genişliği gider. "Kutular ve geniş alan kayboldu" şikâyetinde
  ilk bakılacak yer burası.
- Noel ve benzeri sezon konusu yeni alan istemez: `card_event_list` + `h2_event`
  (⑪b) doldurulur, alt satırda kendiliğinden kendi H2'si ve içindekiler satırıyla basılır.
- Ortaklık bağlantılarının yeri:
  - uçak ⑦'de
  - tren ⑦'de
  - otel ⑦b'de (önce şehir, sonra bölge bölge)
  - tur ve bilet ⑧ kartlarında ve bütçede
  - araç ⑦'de
  - sağ sütunda Hızlı Plan ve Sonra nereye

Basış sırası özet:
- **Sol sütun (üst satır):** video → H1 → içindekiler → giriş → bütçe → nasıl
  gidilir → nerede kalınır.
- **Alt satır (geniş):** gezilecek yerler → ne yenir → gitmeden → ⑪b ek bölümler →
  SSS → "bu rehber nasıl hazırlandı" → galeri.
- **Sağ sütun:** kısa bilgiler → ne zaman → kaç gün → videoda en iyi anlar →
  Hızlı Plan → sonra nereye.

#### A5.1 Giriş (`hero_intro_text`)
- Biçim: `<section class="intro-block"><p class="intro-lead"><strong>tek cümlelik cevap.</strong> …</p></section>`
- 50–60 kelime, hap hap: kısa cümle, tek bilgi, nokta.
- Girişteki sayılar sayfayla birebir tutar ("dört durak" diyorsa dört kart vardır).
- Ortaklık bildirimi girişin altına şablondan gelir, elle yazılmaz.

#### A5.2 Kısa bilgiler (sağ sütun)
- `api_weather_city`, `api_timezone` her sayfada dolu.
- `info_visa_link`, `info_language_link`, `info_esim_link`: rehber varsa post id yazılır; yoksa boş kalır, sonra eklenir.
- `info_emergency`, `info_internet` doludur. Acil numara başka hiçbir yerde tekrar edilmez.

#### A5.3 Bütçe
- **Standart:** `budget_low/mid/high_price` = **kişi başı, bir günlük, yemek
  dahil, konaklama ve uçak hariç.** "€/gece" yazılmaz.
- `budget_*_desc`: 2–3 cümle, o günün kalemleri (ulaşım bileti, giriş, tur, öğle ve akşam yemeği).
- `budget_note`: iki paragraf.
  - İlki: "Rakamlar kişi başı ve bir günlük. **Yemek dahil; konaklama ve uçak bileti hariç.**"
  - İkincisi: yöntem ve son kontrol tarihi.
- `card_budget_desc`: ilk satır kaynak cümlesi, sonra şu başlıklar:
  - `<strong>Şehir içi ulaşım:</strong>`
  - `<strong>Giriş ve turlar:</strong>`, ardından kısa kod `gyg_{şehir}`
  - `<strong>Yemek:</strong>`
  - `<strong>Ücretsiz olanlar:</strong>`
- Kaynak önceliği: bizim fişimiz > resmî tarife (belediye, ulaşım idaresi,
  işletme) > rehber sitelerinin bandı ("resmî tarifesi yok" diye belirtilir).
  Üçüncü parti bilet sitesi kaynak sayılmaz.
- `h2_budget`: "{Şehir} Pahalı mı? 2026 Günlük Bütçe"
- ⚠ Atina ("3 gece 4 gün") ve Taormina ("konaklama dahil") bu standarda uymuyor; sırası geldiğinde düzeltilecek.

#### A5.4 Görseller
- Kaynak videodan kare.
- İşleme: PIL `Image.LANCZOS` + `save(quality=85, method=6)`, 1200x675 WebP. ImageMagick kullanılmaz.
- Dosya adı: `NN-{şehir}-{yer}.webp` (yerler) ya da `{şehir}-{mekân}-{yemek}.webp` (yemek).
- Alt metin: yeri adıyla anan Türkçe sahne cümlesi; şehir adı geçer.
- `<div class="gz-place-img"><img class="wp-image-ID" src="…" alt="…" width="1200" height="675" loading="lazy"></div>`
- Görsel yalnız yerler, yemek ve özel bölüm kartlarında. Konaklama ve gitmeden kartlarında yok.

#### A5.5 Nasıl gidilir (`trans_*_detail`)
```
EĞER İstanbul'dan şehre direkt uçuş varsa
    → trans_plane_detail: havalimanı + merkeze ulaşım + [gbc_aff id=sky_{şehir}]
DEĞİLSE
    → en yakın direkt uçuşlu havalimanı yazılır ("Strazburg'a değil, Basel'e uçun"),
      direkt seferin bittiği tarih yazılır,
      trans_plane_detail: [gbc_aff id=sky_{havalimanı}]
      trans_train_detail: aktarma treni + [gbc_aff id=omio_{kalkış}_{varış}]
EĞER araçla gezilecek köy, kale ya da bölge varsa
    → trans_car_detail: P+R / ZTL notu + [gbc_aff id=dc_{şehir}]
```
- Alternatif rotalar (Paris, Frankfurt) yalnız mantıklıysa tek cümle olur.
  Mesafe soruları ("Paris Strazburg arası kaç km") SSS'e gider, burada tekrar edilmez.
- Boş kalan `trans_bus/ship` alanı basılmaz.
- `h2_transport`: "{Şehir}'a Nasıl Gidilir? Uçak Bileti, Havalimanı ve {aktarma}"

#### A5.6 Nerede kalınır
- `stay_summary`: bölgeleri tek cümlede sayar.
- `card_stay_list`:
  - `gz-plan-baslik` içinde şehrin tüm otelleri: `[gbc_aff id=bk_{şehir}]` (Booking tek oturum çerezi, bölümün başında olmalı).
  - Her bölge bir `gz-place-card`. İçinde `<strong>Kime uyar:</strong>`, `<strong>Göze alın:</strong>`, "Aşağıdaki bağlantı doğrudan … otelleri açıyor.", ardından `[gbc_aff id=bk_{şehir}_{bölge}]`.
- Bölge bağlantısı o bölgenin otellerini açar, genel şehir sayfası olamaz.
  Booking `landmark/ULKE/…html` ya da `district/ULKE/sehir/…html` adresi arama
  sonucundan doğrulanır.
- `h2_stay`: "{Şehir} Otelleri: Nerede Kalınır?" ya da hacme göre "{Şehir}'da Nerede Kalınır? …"

#### A5.7 Gezilecek yerler (`card_places_list`)
- Yalnız videodaki yerler, video sırasıyla.
- İskelet: `gz-places-wrapper` > `gz-plan-baslik` (`gz-plan-no` tek kelime: Merkez, Deniz…) > numaralı `gz-place-card`.
- Kartta sırasıyla: görsel, `gz-inner-title` + `<small>alt başlık</small>`, `gz-inner-tags` (önce video çipi, sonra 2–3 `<b>` etiket: ücret, süre, yer), 2–4 kısa paragraf.
- Biletli durak → kartın sonunda `<p class="gz-kart-aff">[gbc_aff id=gyg_{şehir}_{yer}]…[/gbc_aff]</p>`. Otel adı geçerse doğrudan o otelin sayfası.
- İkinci grup (deniz, sahil) `gz-mavi` rengiyle; numaralar gruplar boyunca devam eder.
- `card_places_tag`: Google My Maps iframe (varsa). `place_location`: basit harita iframe'i.

#### A5.8 Ne yenir
```
food_summary HER ZAMAN en üstte şehrin en meşhur 3-6 yemeğini sayar.
EĞER şehrin bölümünde yemek sahnesi varsa
    → food_summary: meşhurlar + <strong>Denediklerimiz:</strong> (yemek, kısa hüküm)
                    + <strong>Bu gezide denemediklerimiz:</strong> … "yemediğimizi yemiş gibi anlatmıyoruz."
    → card_food_list: mekân kartları (gz-kirmizi), başlık "Mekân adı: öne çıkan yemek",
      video çipi, harita linki/adres YOK (My Maps'e yönlenir)
    → card_food_note: <p><strong>Hesap:</strong> mekân, kişi sayısı, toplam, kişi başı</p>
DEĞİLSE
    → food_summary: "{Şehir}'de oturup yemek yemedik; aşağıda şehrin nesi meşhur, onu yazıyoruz."
    → card_food_list: <ul class="gbc-check"> ile meşhur tatlar
```
- Mekân çok ve "{şehir} ne yenir / nerede yenir" ayda 1.000+ ise alt sayfa + `rel_food` (+ `limit_food`).
- Alkollü içecek yazılmaz, adı da geçmez.
- `h2_food`: "{Şehir}'da Ne Yenir? {3 meşhur ad}"

#### A5.9 Gitmeden bilmeniz gerekenler (`card_tips_list`)
- Kartlı yapı, 4–8 kart. Başlıklar soru ya da sürpriz kalıbında.
- Planı en çok değiştiren konular:
  - hangi havalimanı
  - önceden ayırtılacaklar
  - vize ve sınır
  - pratik güvenlik (çanta kontrolü, cep hırsızlığı)
  - kıyafet ve hava
  - şehre özgü sürpriz
- Sezonluk tarihler burada değil, özel bölümde durur (tek yer kuralı).
- Acil numara yazılmaz.

#### A5.10 Özel bölüm (⑪b)
- Kalıp gezilecek yerlerle aynı (`gz-places-wrapper`). Kart başlıkları arama sorgusu.
- **Sezonluk bölüm** (Noel pazarı, festival):
  1. Bu yılın tarihi. Açıklanmadıysa "henüz açıklanmadı" yazılır, rehber tahmini ayrı cümlede verilir.
  2. Önceki yılların tarihleri (yalnız doğrulanmış olanlar).
  3. Saatler.
  4. Nerede kuruluyor.
  5. Öne çıkan unsur. Resmî ifade birebir kullanılır; "dünyanın en büyüğü" diyen resmî kaynak yoksa yazılmaz.
  6. Nasıl gidilir, araç ve P+R.
  7. Komşu şehirle birleştirme.
  8. Rehberli tur (`gyg_{şehir}_{konu}`).
- Resmî siteye dış bağlantı: `<a href="…" target="_blank" rel="noopener">`.

#### A5.11 SSS (`faq_1..10`)
- 5–8 soru. **Yukarıda cevabı olan hiçbir soru girmez.**
- Hacim sırasıyla. Tipik sorular:
  - komşu şehirlerle mesafe ("… arası kaç km")
  - nüfus
  - başkonsolosluk
  - outlet
  - "hangi ülkede" (girişte cevaplanmadıysa)
- Cevap 1–2 cümle, rakam kaynaklı.

#### A5.12 Yan sütun
**Kaç gün:**
- `gun_cevap` (meta): tek paragraf net cevap; 1 gün / 2 gün / uzun kalış (çevre) aynı paragrafta.
- `route_ozet_1/3/7` (meta) kümülatif yazılır: merkez / merkez + … / merkez + çevre.
- Rozetteki gün sayısıyla (1, 3, 7) çelişen gün yazılmaz. "2 gün" yalnız `gun_cevap`'ta geçer.
- `route_short/medium/long_desc` "<strong>1 gün:</strong>", "<strong>3 gün:</strong>", "<strong>7 gün:</strong>" ile açılır.
- `route_onerilen` (meta): önerilen gün.
- `kart_gun`: "{Şehir} Kaç Günde Gezilir?". `kart_mevsim`: "{Şehir}'a Ne Zaman Gidilir?"

**Hızlı Plan: Gitmeden 6 Adım** (meta, royal `wp_update_post_meta`):
- `gz_yakin_bas` = "{Şehir} Hızlı Plan: Gitmeden 6 Adım", `gz_yakin_ust` = "1".
- `gz_yakin_yerler` = **tam 6 satır**, satır biçimi `[gbc_aff id=X_yan stil=yan]N. Adım: Başlık | Kısa açıklama[/gbc_aff]`.
- Sıra sabit:
  1. **Uçak** (`sky_{havalimanı}_yan`, Skyscanner): her zaman 1. adım.
  2. Otel (`bk_{şehir}_yan`).
  3. Aktarma treni ya da transfer (`omio_{kalkış}_{varış}_yan`).
  4. Şehre özgü tur ya da bilet (`gyg_{şehir}_{konu}_yan`).
  5. İkinci tur/bilet ya da yedek otel (`gyg_…_yan`, `bk_{komşu}_yan`).
  6. Araç (`dc_{şehir}_yan`).
- Şablon satır sınırı koymuyor; kaç satır yazılırsa o kadar basılır.
- Uçak satırının Skyscanner bağlantısı defterde henüz boşsa satır **yine yazılır**
  (taslakta 1. adım görünmez, diğerleri 2-6 görünür). Halil kısa bağlantıyı
  deftere yapıştırdığında adım kendiliğinden çıkar. Yayından önce altı satırın
  altısının da bağlantısı dolu olmalı.
- Aynı `_yan` id'si birden çok sayfada kullanılabilir (Basel uçuşu dört Alsas
  sayfasında); aynı sayfada bir kez.

**Alakalı YouTube videoları (`related_videos`):** satır `VideoID | Başlık | Not`;
aynı şehrin başka videoları varsa yan sütunda kapak karesiyle basılır.

**Sonra nereye (`trip_links`):**
- Satır biçimi `Ad | URL | kısa not | aff_id`. `## Başlık | alt satır` yeni kart açar.
- Dördüncü sütun doluysa şablon URL'yi defterden (sub_id'li) okur.
- Varsayılan üç grup:
  1. "{Şehir}'dan Trenle": Omio satırları.
  2. "Arabası Olmayanlar İçin Turlar": GYG satırları.
  3. "{Bölge} Rehberlerimiz": iç sayfalar.
- eSIM burada tekrar edilmez (üstte `info_esim_link` var).
- İç link adresi `wp_search_posts` ile doğrulanır, tahmin edilmez.

#### A5.13 Şema
- `gbc_schema_geo` = "enlem,boylam" (şehir merkezi).
- `gbc_schema_yerler`: her satır `Tür | Ad | enlem,boylam | çapa | adres`.
  - Tür: TouristAttraction / Church / LandmarksOrHistoricalBuildings / Hotel.
  - Çapa: `h2_places`'in ASCII slug'ı, ilk 60 karakter.

#### A5.14 Rank Math
- Puan editörde hesaplanıyor. İçerik ACF'de olduğu için API gövdeyi boş görür;
  saklı puan editör açılıp kaydedilince yenilenir.
- 90+ için:
  - başlık anahtar kelimeyle başlar ve sayı içerir
  - açıklamada anahtar kelime geçer
  - URL'de anahtar kelime var
  - ilk %10'da anahtar kelime var
  - alt başlıklarda anahtar kelime var
  - görsel alt metninde anahtar kelime var
  - en az bir dış kaynak bağlantısı var
  - iç bağlantılar var
  - içindekiler var
  - paragraflar kısa
- `rank_math_pillar_content` = on.
- **Editör tuzağı:** API ile yazılmış sayfa, API'den ÖNCE açılmış bir editör
  sekmesinden "Güncelle" ile kaydedilirse ACF formu eski değerleri geri yazar
  (30 Eylül 2026, Strazburg 31480 bu yüzden ilk taslağa döndü). Editörde
  kaydetmeden önce sayfa yenilenir (F5). Asistan, sayfa "bozuldu" denince önce
  `wp_history_list` ve `modified` saatine bakar, sonra kendi yazım kaydından
  alanları geri yükler.
  **Kilit:** Claude'un yazdığı her rehbere `gz_api_kilit` = 1 metası konur. WPCode'daki
  "GBC · API kilidi" parçası (`wpcode/gbc-api-kilidi.php`) bu yazılarda editör
  formundan gelen ACF değerlerini kaydetmez; başlık, etiket ve Rank Math kaydedilir.
  Editörden ACF düzenlemek için meta 0 yapılır.

#### A5.15 Etiketler
- Sıra: Ülke · Gezi · Noel Pazarları (varsa) · Vizeli/Vizesiz · VLOG · {ŞehirAdı}.
- "Görülecek Yerler" (1188) Gezi şablonunda **yasak**.

---

#### A5.16 Sayfa Denetimi: hedef %100 (1 Ekim 2026, Halil)
- Sayfa bitince gbc-core **Sayfa Denetimi** (GBC skoru) çalıştırılır. Hedef **%100'e
  yakın, en az %90**. %70 yalnız "hazır" alt sınırıdır, hedef değildir.
- Denetimin **Yapılacaklar** kutusundaki ve **Kontroller** tablosundaki puan kaybettiren
  **her madde** tek tek düzeltilir, denetim yeniden çalıştırılır (test, düzelt, test).
  Madde atlanmaz, "sonra bakarız" denmez.
- Bölümler ve rehberdeki karşılığı:
  - **İçerik yapısı:** tek H1, başlık sırası atlamıyor (H2'den H4'e inilmez), en az 3 H2,
    her görselde alt metin, eski yıl yok (başlık ve metinde güncel yıl).
  - **Meta:** `rank_math_description` 120-160 karakter, `rank_math_title` 30-60 karakter.
  - **URL:** HTTP 200, canonical bu adres, noindex yok.
  - **Bağlantılar:** en az 3 iç bağlantı; ölü bağlantı yok; iç bağlantı yönlendirmesiz son
    adrese gider (`wp_search_posts` ile doğrulanmış adres); ortaklık yalnız kısa kodla
    (yapı doğru, `rel="sponsored"` şablondan gelir).
  - **Şema:** `gbc_schema_geo` ve `gbc_schema_yerler` dolu; denetimin "olması gereken"
    dediği şemaların hepsi basılıyor.
  - **Kelime:** aylık 20+ aranan eksik kelime kalmaz; eksik kelime uygun H2'ye, kart
    başlığına ya da SSS'e işlenir (tek yer kuralı bozulmadan).
  - **Kanibalizasyon:** aynı kelimeyi hedefleyen başka sayfa varsa Halil'e öneri yazılır
    (odak değişikliği ya da birleştirme); kendiliğinden sayfa silinmez.
  - **Google dizini, Silo:** yayından sonra ölçülür. Silo için üst (bölge/ülke) sayfaya,
    yan (komşu şehir) sayfalara ve alt sayfalara bağ `trip_links` ve metin içinde kurulur.
- Taslakta ölçülemeyen maddeler (HTTP 200, dizin) yayından hemen sonra ölçülür ve
  kapatılır; geri kalan her madde yayından ÖNCE yeşil olur.
- Düzeltilemeyen madde kalırsa nedeni ve ne gerektiği Halil'e tek satırla yazılır.

### A6. Ortaklık (defter 30120)

- Sayfaya HTML yazılmaz, yalnız kısa kod. Defterde satırı olmayan id hiçbir şey basmaz.
- Satır biçimi: `id | görünen metin | program | bağlantı | ağ`.
- Travelpayouts kalıbı: `https://tp.media/r?campaign_id=C&marker=767959&p=P&trs=565047&sub_id=ID&u=URLENCODED`

  | Program | C | P |
  |---|---|---|
  | Booking | 84 | 2076 |
  | Omio | 91 | 2078 |
  | GetYourGuide | 108 | 3965 |
  | DiscoverCars | 117 | 3555 |

- **Skyscanner (Impact), uçak:**
  - Kısa bağlantı (`https://skyscanner.pxf.io/XXXXXX`) yalnız Impact panelinden
    üretilir; asistan üretemez. Derin bağlantı (`/c/7683448/{AdID}/13416?u=…`)
    için panelde duran AdID gerekir. Kısa bağlantılar açılmaz, tahmin edilmez.
  - Sonuna `?subId1={defter_id}` eklenir. CampaignId 13416, partner 7683448.
  - Başka bir şehrin kısa bağlantısı (ör. `sky_atina`) yeni şehir için kullanılmaz; o bağlantı Atina'yı açar.
  - Yeni şehirde satır bağlantısız açılır (`sky_{havalimanı}` gövde, `sky_{havalimanı}_yan` yan sütun),
    Halil'e "şu rota için kısa bağlantı" diye tek satırla istenir.
  - Metin kalıbı: gövde "İstanbul - {Şehir} uçuşlarında en uygun tarihi ara", yan sütun "{Şehir} uçuşları".
- **id kalıbı:** `{program}_{yer}[_{alt}]`.
  - Alt ekler: `_yan` (yan sütun), `_butce`, `_noel`, `_tekne`, `_katedral`, `_{bölge}`.
  - Rota: `omio_{kalkış}_{varış}`.
  - Aynı hedefe farklı sub_id'li satır açılabilir (Sorrento emsali). Aynı id sayfada bir kez kullanılır.
- **Hedef adres doğrulaması:**
  - Doğrudan açılabiliyorsa h1 okunur.
  - Proxy kapalıysa yalnız arama sonucunda birebir görünen URL kullanılır; slug tahmin edilmez.
  - Omio'da bazı slug'lar kod taşır (`obernai-elr3j`), birebir kopyalanır.
- **Alkol filtresi:** şarap yolu, şarap tadımı ve benzeri tur ürünleri eklenmez.
  Konum sayfası (`…-l123/`) ya da kategori sayfası tercih edilir.
- Fiyat ortaklık cümlesinde yazılmaz ("fiyatlarına bak" serbest).
- Yeni satır → önce defter, sonra sayfa.

---

### A7. Kontrol listesi (kaydetmeden önce)

- [ ] Alkol taraması (`(?<![\p{L}\p{N}_])` kalıbı; "bırakın" gibi yanlış alarmlar elenir)
- [ ] Em dash (—) yok, elle ok (→ ↗) yok, emoji yok, "şef" yok, klişe listesi yok
- [ ] Tek yer: aynı bilgi iki bölümde yok; SSS'de yukarıdaki bir şey yok
- [ ] Girişteki sayılar kart sayılarıyla tutuyor
- [ ] Her rakamın kaynağı ve son kontrol tarihi var
- [ ] Gezilecek yerler kartlarının hepsi videoda var
- [ ] Her kısa kod id'si defterde var ve sayfada tek
- [ ] Hızlı Plan'da 6 satır, 1. satır uçak; yayından önce 6'sının da bağlantısı dolu
- [ ] `trip_links` iç adresleri sitede var
- [ ] `rel_*` yalnız merkez sayfa modundaysa dolu (doluysa geniş düzen de kapanır, 5.0)
- [ ] `card_trans_list`, `card_safe_list` boş (özel gerekçe yoksa)
- [ ] Rozetle çelişen gün yok
- [ ] Şema metaları dolu
- [ ] Sayfa Denetimi en az %90 (hedef %100); Yapılacaklar kutusunda açık madde yok
- [ ] Sayfa taslakta

---

### A8. Açık konular (Halil'in onayı gerekiyor)

1. Bölüm 2'deki eşikler (1.000/ay alt sayfa, 100/ay özel bölüm) öneri; onaylanınca kesinleşir.
2. Şablon gün etiketleri 1/3/7 sabit. "2 gün" etiketi için 22607'de küçük değişiklik gerekir.
3. Atina 22690: `kart_seri` = "28293" başlık olarak basılıyor olabilir (canlıda kontrol).
4. Atina ve Taormina bütçe tanımı standarda çekilecek.
5. Strazburg 31480 ile eski 16041 aynı kelimeleri hedefliyor; 16041'in odağı katedral ve alışverişe çekilebilir.
6. `sky_basel` Impact bağlantısı bekleniyor.

---

### A9. Değişiklik günlüğü

- **v2.4 (1 Ekim 2026):** A5.16 Sayfa Denetimi: hedef %100, en az %90; bütün Yapılacaklar
  maddeleri düzeltilir. Meta uzunlukları denetim aralığına çekildi (açıklama 120-160,
  başlık 30-60). Eski format yasak listesi bölüm 0'a eklendi.
- **v2.3 (30 Eylül 2026):** gbc-gezi-rehberi skill'i açıldı. Merkez sayfada
  `limit_places` = 4, yemekte önce meşhurlar, `related_videos` eklendi.
- **v2.2 (30 Eylül 2026):** 5.0 sayfa yerleşimi haritası ve geniş düzen anahtarı
  (`layout_genis`, `rel_*`) eklendi.
- **v2.1 (30 Eylül 2026):** Hızlı Plan 6 adım, 1. adım her zaman uçak
  (Skyscanner). Skyscanner bağlantı kuralı ayrıntılandı. Editör tuzağı eklendi.
- **v2 (30 Eylül 2026):** üç mod karar ağacı, ⑪b ek bölüm yuvaları, günlük bütçe
  standardı, Basel emsali ulaşım kuralı, Hızlı Plan tam 5 adım, trip_links
  3 grup, SSS'te tekrar yasağı, şema metaları, ortaklık URL doğrulama.
  v1 kuralları (giriş, hızlı bilgiler, başlıklar, kaç gün, bütçe, kaynak,
  tek yer, etiket) eklendi.
- **v1 (30 Eylül 2026):** Halil'in Pillar Kural Defteri.

---

## Ek B. Öğrenilenler (en yeni en üstte)

Biçim: **Tarih · Sayfa · Ne oldu · Doğrusu · İşlendiği yer**. Halil'in her düzeltmesi bir derstir.

- **1 Ekim 2026 · Genel (Sayfa Denetimi)** · Rehber "bitti" sayılıyordu ama gbc-core
  Sayfa Denetimi'ndeki Yapılacaklar maddeleri açık kalıyordu; eski kılavuzda eşik %70'ti ·
  Halil: "%100'e yaklaşması için düzeltmelerin hepsi yapılmalı". Hedef %100, en az %90;
  her madde düzeltilip denetim yeniden çalıştırılır · SKILL 0 ve 8, Ek A 5.16, A1 adım 10, CLAUDE.md.

- **1 Ekim 2026 · Genel (kaynak çatışması)** · Eski format üç yerden öğretiliyordu:
  gbc-core `kilavuz/04-gezi.md` (ek bölümler "ölü alan", bütçe tek `gz-chef-note` kutusu,
  Taormina "konaklama dahil"), hesap skill'i gbc-sayfa-calismasi ("trip_links post ID yazılır")
  ve `main`'deki eski CLAUDE.md (Hızlı Plan 5 adım) · Üçü de bu skill'e bağlandı, eski format
  yasak listesi SKILL 0'a ve CLAUDE.md'ye yazıldı; tek sayfada `layout_genis` = 1 · SKILL 0, Ek A 5.0.

- **1 Ekim 2026 · Strazburg 31480 (İKİNCİ KEZ)** · Aynı eski editör sekmesinden
  "Güncelle" (00:16) sayfayı yine ilk taslağa döndürdü · Uyarı yetmedi; teknik kilit:
  `gz_api_kilit` = 1 + WPCode "GBC · API kilidi" parçası. Her yeni rehbere kilit
  metası konur · SKILL 0, Ek A 5.14.

- **30 Eylül 2026 · Alsas taslakları (31480, 31496, 31498, 31500)** ·
  Hızlı Plan 5 adımdı, uçak adımı Skyscanner bağlantısı boş diye çıkarılmıştı ·
  6 adım, 1. adım her zaman uçak; bağlantı boşsa satır yine yazılır, Halil'den
  kısa bağlantı istenir · SKILL 4, kural defteri 5.12.

- **30 Eylül 2026 · Strazburg 31480** · Halil editörde eski bir sekmeden
  "Güncelle"ye bastı, ACF formu ilk taslağı geri yazdı (rel_places=16058,
  düz listeler, €/gece bütçe). Kutular kayboldu, geniş düzen kapandı ·
  Editörde kaydetmeden önce F5. "Bozuldu" denince önce wp_history_list ve
  modified saati; alanlar yazım kaydından geri yüklenir · SKILL 9, kural defteri 5.14.

- **30 Eylül 2026 · Strazburg 31480** · rel_places dolu olunca geniş düzen
  kapanıyor ve kartlar 3'e iniyor (şablon 22607 v75) · Tek sayfa modunda bütün
  rel_* boş; merkez sayfada limit_places = 4 · SKILL 1.3 ve 2, kural defteri 2 ve 5.0.

- **30 Eylül 2026 · Strazburg 31480** · Bütçe "€/gece" yazılmıştı · Kişi başı,
  günlük, yemek dahil, konaklama ve uçak hariç · SKILL 3.3, kural defteri 5.3.

- **30 Eylül 2026 · Strazburg 31480** · Çok şehirli videonun tamamından damga
  kullanılmıştı · Yalnız şehrin bölümü (25:37-26:48) · SKILL 1.1, kural defteri 3.

- **30 Eylül 2026 · Strazburg 31480, Freiburg 31500** · Videoda olmayan yerler
  (Galeries Lafayette, Neustadt, Schlossberg) yer kartı olmuştu · Yer kartı yalnız
  videodakiler; diğerleri rota metnine ya da SSS'e · SKILL 0, kural defteri 3.

- **30 Eylül 2026 · Strazburg, Freiburg, Obernai, Riquewihr** · SSS ve "gitmeden"
  kartları sayfanın başka yerindeki bilgiyi tekrarlıyordu (Noel saatleri, park
  et-bin, "hangi ülkede") · Tek yer kuralı SSS dahil · SKILL 0 ve 5.5.

- **30 Eylül 2026 · Obernai 31498** · Rota "2-3 gün:" yazıyordu, rozet 3 ·
  Rozetle çelişen gün yazılmaz; 2 gün yalnız gun_cevap'ta · SKILL 4.

- **30 Eylül 2026 · Strazburg 31480** · Acil numaralar ve şehir içi ulaşım ayrı
  bölüm olarak basılıyordu · card_safe_list ve card_trans_list varsayılan boş;
  çanta kontrolü "gitmeden" kartına · SKILL 3.4 ve 5.3.

- **30 Eylül 2026 · Strazburg 31480** · Günübirlik yerler gövdede listeydi ·
  Yan sütunda trip_links'e Omio ve GetYourGuide bağlantısıyla · SKILL 4.

- **30 Eylül 2026 · Genel** · Yemek sahnesi olmayan şehirde "sahne yok" gibi
  teknik ifade kullanılmıştı · "Oturup yemek yemedik; şehrin nesi meşhur, onu
  yazıyoruz." · SKILL 5.2.
