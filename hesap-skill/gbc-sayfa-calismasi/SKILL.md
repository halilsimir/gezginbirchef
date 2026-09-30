---
name: gbc-sayfa-calismasi
description: "gezginbirchef.com sayfalarında içerik düzeltme, ortaklık ekleme, şablon alanı doldurma veya yeni sayfa açma işi yapılırken kullan. GBC İşletim Anayasası'nın bozulmaz kurallarını, ölçülmüş teknik tuzakları ve dört şablonun alan standardını taşır."
---

# GBC sayfa çalışması

gezginbirchef.com tek bir yönetim dökümanına göre işletiliyor: **GBC İşletim Anayasası**. Kural çelişkisinde o dosya kazanır. Bu skill onun işletme özeti ve ölçülmüş tuzak listesi.

Site WordPress; işlem `GBC-mcp` araçlarıyla yapılır. Sayfalar dört WPCode şablonundan birine oturur ve içerik gövdede değil **ACF alanlarında** durur.

## Rol ayrımı, pazarlıksız

**Birinci ağızdan deneyim cümlesini site sahibi yazar.** Asistan araştırma, doğrulama, ölçüm, düzeltme, alan doldurma, biçim, iç link ve şema yapar. Transkriptte ya da fişte karşılığı olmayan hiçbir "gittik, yedik, gördük, beğendik" cümlesi kurulmaz; karşılığı yoksa tarafsız yazılır ya da hiç yazılmaz. Yazar Halil'dir, metinde "şef" denmez.

## Bozulmaz kurallar

1. **Alkol (4250 sayılı kanun).** şarap, bira, rakı, uzo, tsipouro, retsina, kokteyl, likör, limoncello, mastiha, sangria, cava, vermut, viski, votka, kadeh, içki, şerefe, yamas, "ruin bar", "tadım" geçmez. Bar ve kulüp yalnızca yer olarak yazılır. **Mekân adı da kapsam içindedir** (Szimpla Kert emsali): alkollü mekânın adı, "bohem avlu" diye anlatılsa bile geçmez. Fotoğraftaki tabela ve kadeh emojisi de aynı kurala tabi. Ortaklık ürünü seçerken de geçerli: şarap vadisi turu, bar turu, palinka müzesi listeye girmez.
2. **Em dash (—) yok.** Virgül, noktalı virgül, iki nokta. `wptexturize`'ın ürettiği en dash (–) sorun değil.
3. **Emoji ikon değildir**, yerine inline SVG. Eski sayfada tek emojiyi tek başına silme; say, sor, tek seferde temizle.
4. **Ok elle yazılmaz, motordan gelir.** Site içi `→`, site dışı `↗` + `target="_blank" rel="noopener"`. Elle yazılan ok kuralın ihlâlidir.
5. **Görsel:** WebP, `loading="lazy"` (hero hariç), width/height yazılı, alt dolu. Video karesinden kart fotoğrafı: PIL ile `Image.LANCZOS` + `save(quality=85, method=6)`, 1200×675. **ImageMagick kullanma**, `-quality` WebP'de işlemiyor.
6. **Sigorta, sağlık, finans, hukuk ortaklığına asla link verilmez.**
7. **Fiyat yazılmaz** (ortaklık bağlamında). "Fiyatlarına bak" denir.
8. **Doğrulanmamış rakam yazılmaz.** Her figürün yanında kaynak ve son kontrol tarihi olur.
9. **Tekrar yok.** Aynı cümle sayfada iki kez geçmez (SSS bilinçli istisna). Yanlış alarm kaynakları: sayfa içi JSON-LD, İçindekiler'deki kart başlıkları, harita lejandı.
10. **Astra tema dosyalarına dokunulmaz.** Düzeltme hangi snippet'te tanımlıysa oraya yazılır.
11. **Yayın onaya tabidir.** Yeni sayfa açma, yayına alma, silme, altyapı ve yerleşim değişikliği: önce sor. Kalıcı silmeyi site sahibi yapar.
12. **Ölçmeden bulgu yazma.** "Muhtemelen eksiktir" diye bir şey yok. Sayı verdiysen ölçtüğün sayıyı ver; ölçemiyorsan "ölçmedim" yaz. **Yanlış teşhis koyduysan açıkça düzelt.**

## Yapay zekâ izi bırakmama

Sayfada bulunursa silinir: üretici HTML yorumu, `data-ai`/`data-generated`/`data-model` öznitelikleri, görünmez karakterler (U+200B, U+200C, U+200D, U+FEFF, U+00AD, gereksiz U+00A0), markdown sızıntısı (`**kalın**`, `##`, `- `, ters tırnak), yer tutucu metin (`TODO`, `XXX`, `Lorem`, `[buraya`), görsel dosya adında araç adı.

**Türkçe klişe filtresi** — metinde geçmez, cümle bunlarla başlamaz ve bitmez: günümüzde · her geçen gün · şüphesiz · kuşkusuz · öte yandan · sonuç olarak · kısacası · bu bağlamda · unutulmamalıdır ki · bilindiği üzere · dikkat çekmektedir · ön plana çıkmaktadır · göze çarpmaktadır · eşsiz · büyüleyici · nefes kesen · muhteşem bir deneyim · unutulmaz anılar · adeta bir · tam anlamıyla · gerçek bir cennet · mutlaka görülmesi gereken · keşfetmeye değer · hem … hem de … sunuyor · sizi bekliyor · kaçırmayın · umarım bu rehber · bu yazıda ele aldık · özetle.

Yapısal klişe de yasak: her paragrafın aynı uzunlukta olması, her bölümün "X nedir" ile açılması, arka arkaya üç maddelik simetrik listeler. Cümle uzunluğu 8-25 kelime arasında değişken tutulur.

## Ortaklık sistemi

- **ALTIN KURAL: sayfaya HTML yazılmaz.** Elle `<a class="gbc-in">` yasak. Yalnız kısa kod: `[gbc_aff id=x]bağlantı metni[/gbc_aff]` ve `[gbc_aff_kutu ids=a,b bas=Başlık not=yok]`.
- Motor WPCode **30204**; defter sayfa **30120** tek doğruluk kaynağı. Defterde satırı olmayan id hiçbir şey basmaz.
- **Kısa bağlantı (tpx.li, pxf.io, tp.media, emrldtp.cc) asla açılmaz** — sahte tıklama üretir. Doğrulama panelden yapılır.
- tp.media kalıbı: `https://tp.media/r?campaign_id=PROGRAM&marker=767959&p=P&trs=565047&sub_id=ID&u=HEDEF`. Booking 84/2076, DiscoverCars 117/3555, Omio 91/2078, GetYourGuide 108/3965.
- Defterde **zaten var olan** id'yi başka sayfada kullanmak serbest. Yeni bağlantıyı site sahibi üretir; verdiği kalıptan link üretmek serbesttir.
- Hedef URL temiz derin link olur: sabit tarih, kişi sayısı, rota içeren URL kullanılmaz.
- **Bağlantısız uzun sayfa en büyük kayıptır.**

## Dört şablon ve alan standardı

Sayfanın şablonu `post_content` içindeki `[wpcode id="…"]` kısayolundan okunur, HTML'ine bakarak tahmin edilmez.

**Gezi (22607/22608)** — *Bu şablonun içerik formatı için TEK KAYNAK `gbc-gezi-rehberi` skill'idir; aşağıdaki liste yalnız alan adı özetidir. Eski format (düz `gbc-check` yer listesi, €/gece bütçe, `gz-chef-note` bütçe kutusu, acil numara bölümü, tek sayfada `rel_*`) kullanılmaz.* Alanlar: hero_custom_title, hero_intro_text, hero_video(+caption, duration, chapters), info_blog_title; hızlı bilgiler info_city/country/currency/language/phone_code/plug/internet/emergency/visa, api_timezone, api_weather_city; silo çapaları info_language_link, info_visa_link, info_esim_link, trip_links (satır `Ad | URL | kısa not | aff_id`; **post ID ya da ID dizisi YAZILMAZ**, textarea'dır); kartlar card_places_list/_tag, card_food_list/_note, card_stay_list, card_budget_desc, card_safe_list, card_tips_list, limit_places/food/stay; food_summary, stay_summary, author_note; bütçe budget_low/mid/high_price+desc, budget_eko/mid/lux_label, budget_note; place_*, place_location, detay_resim_*, gal_img_*; rel_places/food/stay/trans/budget/routes, related_videos, instagram_preview; faq_*. Meta: gz_seri_alt, layout_genis, route_gunler, route_onerilen, route_ozet_*, gun_cevap, git_alt_stay, rank_math_description.

**Liste (23108/23109)** — hero_badge_listed, hero_custom_title_listed, hero_intro_text_listed, hero_video_listed, legend_title_listed, travel_guide_listed, table_guide_listed, place_location_listed, main_card_listed_N. Meta: gz_yan_sutun_1..5, h2_ozet_listed, h2_sss_listed, rehber_faq, gbc_kart_etiketi, gbc_itemlist_kapali, gbc_otel_dest, pill_inceleme_listed, etiket_video_listed, etiket_konum_listed, gz_seri_alt, rank_math_description.

**Detay (23489/23490)** — detailed_hero_badge/title/intro, detailed_main_content, detailed_table, detailed_map, detailed_video_*, detay_resim_*, detailed_main_guide_link. Meta: detailed_h2_galeri/harita/sss, detailed_kart_hizli, rehber_faq.

**Rota (23340/23342)** — rota_badge/custom_title/intro_text/video_listed, rota_travel_post/link/location/list_link_listed, rota_food_post/link/location_listed.

## Ölçülmüş teknik tuzaklar

- **ACF çift satır.** Her alanın iki meta satırı var: `alan_adi` (değer) ve `_alan_adi` (= `field_xxxxx`). Yeni sayfada yalnız değer satırını yazarsan `get_field()` boş döner, şablon hiçbir şey basmaz, hata da vermez. Alan anahtarları **kart başına farklıdır**; aynı şablondaki dolu bir sayfadan kopyalanır.
- **Otomatik kayıt tuzağı.** Taslakta autosave revizyonu varsa ACF önizlemede alanları o boş revizyondan okur: `get_field` ile okunan her şey kaybolur, `get_post_meta` ile okunanlar durur. Yayınlayınca düzelir. Taslakta kart görünmüyorsa önce revizyonlara bak.
- **Kısa kod silinmesi.** `royal_mcp_acf_update_field` wysiwyg alanlarda `[gbc_aff]` kısa kodlarını siliyor. Ortaklık kısa kodu içeren alan yalnızca `wp_acf_update_fields` ile yazılır. `wp_update_post_meta` ACF alan adlarını hiç kabul etmiyor.
- **Gezi Liste alanları** (hero_*_listed, main_card_listed_*) alan ADIYLA yazılamıyor, yalnız alan ANAHTARIYLA. ACF konum kuralı `gezilecek-yerler` etiketine bağlı; etiket yoksa alanlar görünmez ve API yazmaları sessizce başarısız olur. Etiket kaldırılmaz.
- **CSS dosyası 404.** Snippet 30188 şablon CSS'ini `<id>-<özet>.css` adıyla dosyaya yazar ve eskisini siler. CSS snippet'i düzenlenince ad değişir, önbellekteki HTML eski adı gösterir, o şablonu kullanan bütün sayfalar stilsiz kalır. **CSS snippet'i her düzenlemeden sonra LiteSpeed purge zorunludur.** Ölçüm `?gbcnc=` ile yapılır; `?nc=` düşürülüyor.
- **Kart kapısı.** Liste şablonu kartları `card_title_listed` ile açar; açıklama boş olsa da kart basılır, başlık boşsa kart hiç basılmaz.
- **"Görsel Yok".** Şablon kart görseli yoksa kutuya literal "Görsel Yok" basıyor (23108 else dalı).
- **gbc-step bir liste değil.** Doğru yapı her adım için ayrı div: `<div class="gbc-step"><span class="n">1</span><span class="h">başlık</span><span class="d">açıklama</span></div>`. ul/li yazılırsa metin harf harf kayar. `gbc-check` ise ul/li + small ile doğru çalışır.
- **Yan sütun** post meta'dan okunur: `gz_yan_sutun_1..5`, beşi de boşsa sütun hiç basılmaz.
- **Türkçe ek hatası.** 22607, H2'leri `info_blog_title`'dan türetirken sesli harfle biten adlarda bozuk ek üretiyor ("Adası'ya"). h2_transport, h2_stay, h2_food, h2_places alanlarıyla elle ezilir.
- **Ölü alanlar (ölçüldü):** `season_spring/summer/autumn/winter` ve `card_night_list` 22607'de hiç basılmıyor. Dolu olsalar da canlı sayfada görünmezler.
- **Ölçüm tuzağı 1:** JS `\b` Türkçe harflerde kırılıyor; `\brakı` "bırakıyor"a, `\bbira` "biraz"a eşleşir. Doğru kalıp `(?<![\p{L}\p{N}_])` lookbehind + `u` bayrağı.
- **Ölçüm tuzağı 2:** Gezi şablonunun sağ sütunu `.entry-content` içinde `aside.gz-sidebar-column`, ortaklık bloğu `aside.gz-aff-son`. Tarama yaparken `aside` toptan silinmez, yalnız `.gz-author-box` çıkarılır.
- **innerText ile ölçme**, `textContent` kullan.

## Sayfa işlerken sıra

1. Şablonu `post_content`'ten oku, sayfayı canlı çek, ölç.
2. Kural taraması: alkol (ek duyarlı kalıpla), emoji, em dash, "şef", klişe listesi, tekrar, görünmez karakter.
3. Şablonun alan standardındaki boş alanları çıkar.
4. Arama hacmini ölç (Ubersuggest, dil `tr`, locId 2792). Türkçe hacim çoğu zaman "ne demek" ve yerel yazım kalıbında (barselona 4.400 / barcelona 480).
5. Ortaklık: defterde uygun id var mı bak, yoksa kalıptan üret ve **önce deftere yaz**, sonra kısa kodu koy.
6. Düzeltmeyi yaz, canlı sayfadan doğrula, LiteSpeed purge hatırlat.
7. Ne ölçtüğünü sayıyla söyle; ölçemediğini "ölçmedim" diye yaz.