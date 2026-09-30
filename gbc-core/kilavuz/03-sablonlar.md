# Şablonlar ve Silo

> Hangi içerik hangi şablonda durur, site yapısı ve silo nasıl kurulur; yeni sayfa açarken ya da şablon seçerken okunur.

## Şablon listesi

Her yazının `post_content`'i iki WPCode kısa kodundan oluşur: birincisi şablon (PHP), ikincisi şablonun CSS'i.

| Şablon | Kısa kodlar | Ne için |
|---|---|---|
| Gezi | `[wpcode id="22607"]` + `[wpcode id="22608"]` | Yerin ana rehberi: şehir, ada, bölge, ülke (pillar) |
| Liste | `[wpcode id="23108"]` + `[wpcode id="23109"]` | Alt silo: gezilecek yerler, ne yenir, nerede kalınır, vize, dil, bölge hub'ı ("Ana Hub") |
| Detay | `[wpcode id="23489"]` + `[wpcode id="23490"]` | Tek mekân, tek anıt, tek müze |
| Rota | `[wpcode id="23340"]` + `[wpcode id="23342"]` | Kaldırılacak; yeni iş yapılmaz |
| Tarif | `[wpcode id="24751"]` + `[wpcode id="24752"]` | Yemek tarifleri |
| Blog | `[wpcode id="26922"]` + `[wpcode id="26923"]` | Blog yazıları |
| Sözlük | `[wpcode id="24156"]` + `[wpcode id="24157"]` | Sözlük yazıları |

- Ana sayfa ayrı bir sayfadır (23531); tipografi ve düzeni Gezi'yle aynı olur.
- Ölçülen dağılım (15 Eylül 2026): Tarif 756 · Detay 106 · Blog 100 · Liste 30 · Gezi 26 · Sözlük 19 · Rota 2. Şablonsuz yazı yoktur.
- Şablon zorunluluğu yalnız `post` türü içindir. Kurumsal sayfalar (Gizlilik #3, İletişim #3939, Food Stylist #8636, Proje & İş Birlikleri #11209) şablonsuzdur ve bu doğrudur.
- Rank Math "Sütun İçeriği" (pillar) işareti bütün gezi rehberlerinde (her yerin ana sayfası) olur.

## Kısa kodun çözüldüğü alanlar

Kısa kod (ortaklık dahil) her alanda çalışmaz. Çalışmayan alana konan kısa kod ya hiç basılmaz ya da okura ham metin görünür.

| Şablon | Kısa kod çalışan alanlar | Çalışmayan (dikkat) |
|---|---|---|
| Gezi 22607 | card_stay_list, card_places_list, card_food_list, card_tips_list, card_safe_list, stay_summary, food_summary, trans_*_detail | Hızlı Bilgiler (`<a>` etiketlerini de siler), ölü alanlar |
| Liste 23108 | kart açıklaması (`card_desc_listed`), hero girişi, 5 yan sütun metası (`gz_yan_sutun_1..5`) | `table_guide_listed`, `rehber_faq` |
| Detay 23489 | yalnız `detailed_main_content` | diğer bütün alanlar |
| Rota 23340 | yalnız giriş metni `rota_intro_text_listed` | `rota_travel_post_listed`, `rota_food_post_listed` (ayrıştırıcı köşeli parantezi siler) |
| Tarif 24751, Blog 26922 | hiçbiri | hepsi |

- Gezi sayfalarında `stay_summary` ve `food_summary` pratikte boştur; kullanılan alan `card_stay_list`.
- Ortaklık koymadan önce alanın canlıda basıldığı doğrulanır.

## Üç katlı yapı (20 Eylül 2026)

- **Gezi (yer) → Liste (alt silo) → Detay (tek nokta).** Her kat bir alttakini açar.
- Şehir, ada, bölge ve ülke rehberleri Detay şablonunda duramaz; hepsi Gezi rehberine çevrilir (Detay → Gezi çevrimi). Adım adım: önce tam envanter, sonra sırayla çevrim.
- Detay şablonu yalnız tek mekân, tek anıt, tek müze için kalır ve ana rehbere bağlanır.
- Mekân ya da anıt doğrudan şehrin altına yazılmaz; önce bir Liste sayfası gelir, Detay onun altına girer (Roma → Roma gezilecek yerler → Kolezyum; Budapeşte → ne yenir → Pomo d'Oro).
- Yemek mekânı "ne yenir" listesinin, gezilecek nokta "gezilecek yerler" listesinin altına girer; karıştırılmaz.
- Arama hacmi yüksek tek noktalara kendi Detay sayfası açılır, görselli ve tam açıklamalı.
- Rota şablonu kalkıyor; rotalar gezi rehberinin içine girer.

## Silo ve hub

- Yapı: ülke → şehir/ada → silo; artı yatayda kesen silolar (vize, dil, eSIM, feribot).
- Yatay silo çapaları (vize, dil, eSIM) ülke sayfasında bir kez durur, her şehrin altına ayrı ayrı konmaz. Gezi sayfasında Hızlı Bilgiler'deki vize/dil/eSIM satırları bu sayfalara bağlanır.
- Bir şehrin alt (spoke) sayfaları varsa ana gezi rehberinde o konudan birkaç tane (ör. 4) verilip alt sayfaya yönlendirilir. Önce şehrin bütün sayfalarının envanteri çıkarılır; kanibalizasyon olmaz (22 Eylül 2026).
- Bir video birden çok yeri anlatıyorsa aynı video o sayfaların hepsine saniye damgasıyla girer.
- Aktivite sayfaları (kuzey ışıkları gibi) birden çok yere bağlanır; iki uçlu şeyler (Bernina Express) iki ülkeden de bağlanır.
- Plan tablosunda silo: bölge hub'ı ("… Ana Hub", Liste şablonu) → şehir rehberi (ana satır, Gezi) → alt sayfalar (↳). Hub'ın ↳ satırları (vize, dil) bölge yardımcı sayfalarıdır; aynı bölgedeki diğer ana satırlar kardeştir (yatay).
- Silo bağ kuralı: her sayfa üst sayfasına bağlanır, üstten bağ alır, bütün alt sayfalarına bağlanır, en az 2 yatay (kardeş) bağ verir.
- Ana sayfa dışında her yazının dibinde en az bir iç link kutusu olur (eklenti bunu garanti eder).

## Verilmiş yapı kararları

- **Feribot hub (22 Eylül 2026):** 30343 (`yunan-adalari-feribot`) bütün Yunanistan feribot aramalarının tek sahibi; feribotla ilgili her şey buraya bağlanır.
- Atina ne yenir (23235) spoke'u ayrı kalır.
- Pire (20182) kendi başına bir gezi rehberidir, Atina'nın alt sayfası gibi ele alınmaz.
- Atina gezi rotaları Atina ana sayfasına (pillar 22690) alındı.
- Noel pazarları mevsimlik ve düşük hacimli; çok sayıda sayfa yerine tek pillar'a inmeli.
- Roma'da "part 1 / part 2" mantığı arama için çalışmıyor.

## Kategori (24 Eylül 2026)

- Gezi kategorileri ülkeye göre düzenlenir: Gezi Rehberi altında Yunanistan, İtalya, Avrupa, Balkan, Dünya, Türkiye. Türe göre değil.

## Plan tablosu

- Sitenin planı Halil'in Google tablosudur: GBC_Gezi_Rehberleri_IsListesi_v2 (`1UPTZT6IwQKsO_Zb2A47VuQ6g8z5d_XEx0pKLFm93UGA`). Eklenti okur, tabloya yazmaz.
- Satır biçimi: # (↳ alt sayfalar üst numaraya bağlanır, 7.1 gibi), bölge, ülke, ad, durum (acik/duzeltme/acilacak), şablon, mevcut/hedef URL, Post ID, YouTube, not, 12 ay hacmi; Kış Temaları "kış" işaretlidir.
- Adı "Gezi Rehberi" olan ana satırın hedef şablonu Gezi, "Ana Hub" Liste'dir; diğerleri tabloda yazan.
- Mevsimli sayfa zirve ayından 2 ay önce hazır olur (hazır = yayında ve GBC skoru ≥70).

## Eski yazıyı şablona taşırken

- Snippet 28829 ("Eksik H1 Başlığı Otomatik Ekle") sabit bir yazı ID listesi tutar. Listedeki eski yazı bir şablona taşınırsa sayfada iki H1 çıkar; taşınan yazının ID'si o listeden çıkarılır.
- Detay'a taşıma yöntemi ve etiket şartı: bkz. Detay.

## Trafik gerçeği (strateji)

- Gezi tarafında sıralanan küme Yunan Trakyası + Rodos (İskeçe karnavalı, Rodos alışveriş, Dedeağaç, Gümülcine, Drama). Ortaklık önceliği önce bu kümededir.
- Trafik alan format mekân/Detay sayfalarıdır.
- İstanbul mekân sayfalarına bilerek ortaklık konmaz (yerli okura otel/araç bağlantısı zorlama olur).
