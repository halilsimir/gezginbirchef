---
name: gbc-gezi-rehberi
description: gezginbirchef.com'da videosu çekilmiş bir şehir için Gezi şablonlu (WPCode 22607/22608) rehber açarken, doldururken ya da düzeltirken kullan. Sayfanın baştan sona kurgusunu (video, kelime, giriş, bütçe, ulaşım, konaklama, yerler, yemek, gitmeden, Noel gibi özel bölümler, SSS, sağ sütun, ortaklık, şema) if/else kararlarıyla taşır. Her işin sonunda öğrenilenleri kendine yazar.
---

# GBC Gezi Rehberi Kurgusu

Bu skill bir gezi rehberini **baştan sona, sayfadaki sırayla** kurar. Her adımda
"hangi alan, ne zaman doldurulur, ne zaman boş kalır" kararı yazılıdır.
Ayrıntılı alan standardı ve bütün kurallar `references/kural-defteri.md`'de.
Geçmiş hatalar ve dersler `references/ogrenilenler.md`'de.

**Başlamadan önce ikisini de oku.** `ogrenilenler.md`'deki bir ders bu dosyayla
çelişirse ders kazanır (daha yenidir) ve bu dosya da güncellenir.

Öncelik sırası: GBC İşletim Anayasası > ortaklık defteri (30120) > kural defteri > bu skill.

---

## 0. Değişmez kurallar (her sayfada)

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
- Her yazımdan sonra yazdığın alanları scratchpad'e JSON olarak da kaydet
  (editör tuzağına karşı geri yükleme kaydı, bkz. bölüm 9).

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
- `rank_math_title`: en fazla 60 karakter, kelimeyle başlar, sayı içerir.
- `rank_math_description`: en fazla 160 karakter, ilk 3 kelime grubu.
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
- [ ] Sayfa taslakta

---

## 9. Kendini geliştirme (her işin sonunda ZORUNLU)

Bu skill her işten sonra büyür. Kural atlanmasın diye:

1. **İş bitince** `references/ogrenilenler.md`'nin en üstüne bir kayıt ekle:
   `Tarih · Sayfa (id) · Ne oldu · Doğrusu · Kural nereye işlendi`
2. **Halil bir düzeltme istediyse** (ör. "bütçe gecelik değil günlük",
   "5 adım değil 6, ilki uçak") bu **her zaman** bir ders sayılır ve kaydedilir.
3. Ders genel bir kuralsa:
   - bu dosyada ilgili bölümü güncelle
   - `references/kural-defteri.md`'yi güncelle
   - repodaki `CLAUDE.md`'yi güncelle (ikisi aynı içerik)
   - sitedeki taslak "Gezi Rehberi Kural Defteri" sayfasını (31542) güncelle
   - sürüm numarasını artır
4. Değişikliği commit et ve gönder. Mesajda hangi dersin işlendiği yazsın.
5. Aynı hata ikinci kez görülürse kuralı daha görünür yere (bölüm 0'a) taşı.

**Sayfa "bozuldu" denirse:** önce `wp_history_list` ve sayfanın `modified`
saatine bak. Editörden eski sekmeyle kaydetme (editör tuzağı) en sık sebeptir.
Scratchpad'deki yazım kaydından ya da oturum dökümündeki
`wp_acf_update_fields` çağrılarından alanları geri yükle.
