# GBC Gezi Rehberi Kural Defteri (v2)

Sürüm: v2.2, 30 Eylül 2026 · gezginbirchef.com · Gezi şablonu (WPCode 22607/22608)

Bu dosya her oturumda okunur. Bir gezi rehberi açılırken ya da düzeltilirken
buradaki sıra ve kararlar uygulanır. Kaynaklar: Halil'in Pillar Kural Defteri v1
(30 Eylül 2026), Sorrento (31232), Atina (22690), Taormina (30725) ve Strazburg
(31480) çalışmaları. Çelişkide öncelik: GBC İşletim Anayasası > ortaklık defteri
(30120) > bu dosya.

---

## 0. Amaç ve ilke

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

## 1. İş akışı (sırayla)

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
| 10 | Kontrol listesi | | bölüm 7 |
| 11 | Taslakta bırak | | yayın Halil'in onayıyla |
| 12 | Görseller | Halil | videodan kare, 1200x675 WebP (bölüm 5.4) |

---

## 2. Sayfa modu kararı (karar ağacı)

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
      limit_* boş bırakılır (şablon 3 uygular); özel sayı gerekiyorsa limit_* yazılır.
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

## 3. Video ve transkript

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

## 4. Kelime araştırması

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
| `rank_math_title` | En fazla 60 karakter, anahtar kelimeyle başlar, sayı içerir |
| `rank_math_description` | En fazla 160 karakter; ilk 3 kelime grubu geçer |
| `rank_math_focus_keyword` | En fazla 5 kelime, hacim sırasıyla |
| `hero_intro_text` ilk cümle | En çok aranan soruya cevap ("strazburg nerede" 2.400 ise ilk cümle nerede olduğunu söyler). İki yazım birlikte: "Strazburg (Strasbourg)" |
| İlk iki paragraf | En önemli kelimeler burada geçer |
| H2'ler (`h2_*`) | Her biri o konunun en çok aranan kalıbıyla birebir ("Strazburg Otelleri: Nerede Kalınır?", "… Uçak Bileti, Havalimanı ve Basel Treni") |
| Kart başlıkları | Soru kalıbı ("Strazburg Noel pazarı ne zaman? 2026 tarihleri") |
| SSS | Yalnız sayfada cevabı OLMAYAN aranan sorular, hacim sırasıyla (mesafe soruları, nüfus, konsolosluk, outlet) |

---

## 5. Bölüm bölüm alan standardı

### 5.0 Sayfa yerleşimi (şablon 22607'den okundu)

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

### 5.1 Giriş (`hero_intro_text`)
- Biçim: `<section class="intro-block"><p class="intro-lead"><strong>tek cümlelik cevap.</strong> …</p></section>`
- 50–60 kelime, hap hap: kısa cümle, tek bilgi, nokta.
- Girişteki sayılar sayfayla birebir tutar ("dört durak" diyorsa dört kart vardır).
- Ortaklık bildirimi girişin altına şablondan gelir, elle yazılmaz.

### 5.2 Kısa bilgiler (sağ sütun)
- `api_weather_city`, `api_timezone` her sayfada dolu.
- `info_visa_link`, `info_language_link`, `info_esim_link`: rehber varsa post id yazılır; yoksa boş kalır, sonra eklenir.
- `info_emergency`, `info_internet` doludur. Acil numara başka hiçbir yerde tekrar edilmez.

### 5.3 Bütçe
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

### 5.4 Görseller
- Kaynak videodan kare.
- İşleme: PIL `Image.LANCZOS` + `save(quality=85, method=6)`, 1200x675 WebP. ImageMagick kullanılmaz.
- Dosya adı: `NN-{şehir}-{yer}.webp` (yerler) ya da `{şehir}-{mekân}-{yemek}.webp` (yemek).
- Alt metin: yeri adıyla anan Türkçe sahne cümlesi; şehir adı geçer.
- `<div class="gz-place-img"><img class="wp-image-ID" src="…" alt="…" width="1200" height="675" loading="lazy"></div>`
- Görsel yalnız yerler, yemek ve özel bölüm kartlarında. Konaklama ve gitmeden kartlarında yok.

### 5.5 Nasıl gidilir (`trans_*_detail`)
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

### 5.6 Nerede kalınır
- `stay_summary`: bölgeleri tek cümlede sayar.
- `card_stay_list`:
  - `gz-plan-baslik` içinde şehrin tüm otelleri: `[gbc_aff id=bk_{şehir}]` (Booking tek oturum çerezi, bölümün başında olmalı).
  - Her bölge bir `gz-place-card`. İçinde `<strong>Kime uyar:</strong>`, `<strong>Göze alın:</strong>`, "Aşağıdaki bağlantı doğrudan … otelleri açıyor.", ardından `[gbc_aff id=bk_{şehir}_{bölge}]`.
- Bölge bağlantısı o bölgenin otellerini açar, genel şehir sayfası olamaz.
  Booking `landmark/ULKE/…html` ya da `district/ULKE/sehir/…html` adresi arama
  sonucundan doğrulanır.
- `h2_stay`: "{Şehir} Otelleri: Nerede Kalınır?" ya da hacme göre "{Şehir}'da Nerede Kalınır? …"

### 5.7 Gezilecek yerler (`card_places_list`)
- Yalnız videodaki yerler, video sırasıyla.
- İskelet: `gz-places-wrapper` > `gz-plan-baslik` (`gz-plan-no` tek kelime: Merkez, Deniz…) > numaralı `gz-place-card`.
- Kartta sırasıyla: görsel, `gz-inner-title` + `<small>alt başlık</small>`, `gz-inner-tags` (önce video çipi, sonra 2–3 `<b>` etiket: ücret, süre, yer), 2–4 kısa paragraf.
- Biletli durak → kartın sonunda `[gbc_aff id=gyg_{şehir}_{yer}]`. Otel adı geçerse doğrudan o otelin sayfası.
- İkinci grup (deniz, sahil) `gz-mavi` rengiyle; numaralar gruplar boyunca devam eder.
- `card_places_tag`: Google My Maps iframe (varsa). `place_location`: basit harita iframe'i.

### 5.8 Ne yenir
```
EĞER şehrin bölümünde yemek sahnesi varsa
    → food_summary: giriş + <strong>Denediklerimiz:</strong> (yemek, kısa hüküm)
                    + <strong>Bu gezide denemediklerimiz:</strong> … "yemediğimizi yemiş gibi anlatmıyoruz."
    → card_food_list: mekân kartları (gz-kirmizi), başlık "Mekân adı: öne çıkan yemek",
      video çipi, harita linki/adres YOK (My Maps'e yönlenir)
    → card_food_note: <p><strong>Hesap:</strong> mekân, kişi sayısı, toplam, kişi başı</p>
DEĞİLSE
    → food_summary: "{Şehir}'de oturup yemek yemedik; aşağıda şehrin nesi meşhur, onu yazıyoruz."
    → card_food_list: <ul class="gbc-check"> ile meşhur tatlar
```
- Alkollü içecek yazılmaz, adı da geçmez.
- `h2_food`: "{Şehir}'da Ne Yenir? {3 meşhur ad}"

### 5.9 Gitmeden bilmeniz gerekenler (`card_tips_list`)
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

### 5.10 Özel bölüm (⑪b)
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

### 5.11 SSS (`faq_1..10`)
- 5–8 soru. **Yukarıda cevabı olan hiçbir soru girmez.**
- Hacim sırasıyla. Tipik sorular:
  - komşu şehirlerle mesafe ("… arası kaç km")
  - nüfus
  - başkonsolosluk
  - outlet
  - "hangi ülkede" (girişte cevaplanmadıysa)
- Cevap 1–2 cümle, rakam kaynaklı.

### 5.12 Yan sütun
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

**Sonra nereye (`trip_links`):**
- Satır biçimi `Ad | URL | kısa not | aff_id`. `## Başlık | alt satır` yeni kart açar.
- Dördüncü sütun doluysa şablon URL'yi defterden (sub_id'li) okur.
- Varsayılan üç grup:
  1. "{Şehir}'dan Trenle": Omio satırları.
  2. "Arabası Olmayanlar İçin Turlar": GYG satırları.
  3. "{Bölge} Rehberlerimiz": iç sayfalar.
- eSIM burada tekrar edilmez (üstte `info_esim_link` var).
- İç link adresi `wp_search_posts` ile doğrulanır, tahmin edilmez.

### 5.13 Şema
- `gbc_schema_geo` = "enlem,boylam" (şehir merkezi).
- `gbc_schema_yerler`: her satır `Tür | Ad | enlem,boylam | çapa | adres`.
  - Tür: TouristAttraction / Church / LandmarksOrHistoricalBuildings / Hotel.
  - Çapa: `h2_places`'in ASCII slug'ı, ilk 60 karakter.

### 5.14 Rank Math
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

### 5.15 Etiketler
- Sıra: Ülke · Gezi · Noel Pazarları (varsa) · Vizeli/Vizesiz · VLOG · {ŞehirAdı}.
- "Görülecek Yerler" (1188) Gezi şablonunda **yasak**.

---

## 6. Ortaklık (defter 30120)

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

## 7. Kontrol listesi (kaydetmeden önce)

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
- [ ] Sayfa taslakta

---

## 8. Açık konular (Halil'in onayı gerekiyor)

1. Bölüm 2'deki eşikler (1.000/ay alt sayfa, 100/ay özel bölüm) öneri; onaylanınca kesinleşir.
2. Şablon gün etiketleri 1/3/7 sabit. "2 gün" etiketi için 22607'de küçük değişiklik gerekir.
3. Atina 22690: `kart_seri` = "28293" başlık olarak basılıyor olabilir (canlıda kontrol).
4. Atina ve Taormina bütçe tanımı standarda çekilecek.
5. Strazburg 31480 ile eski 16041 aynı kelimeleri hedefliyor; 16041'in odağı katedral ve alışverişe çekilebilir.
6. `sky_basel` Impact bağlantısı bekleniyor.

---

## 9. Değişiklik günlüğü

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
