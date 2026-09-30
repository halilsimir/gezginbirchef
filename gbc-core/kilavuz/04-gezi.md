# Gezi Rehberi

> Gezi şablonu (22607/22608) sayfası açarken, doldururken ve düzeltirken madde madde uygulanacak kontrol listesi; hiçbir alan es geçilmez.

> **TEK KAYNAK (1 Ekim 2026, Halil kararı):** Gezi rehberinin güncel formatı depodaki
> `.claude/skills/gbc-gezi-rehberi/SKILL.md` (skill) ve kökteki `CLAUDE.md`'dir.
> Bu kılavuz onların özetidir; çelişki olursa **skill kazanır**. Eski format
> (düz `<ul class="gbc-check">` yer/ipucu listeleri, "€/gece" bütçe, `gz-chef-note`
> bütçe kutusu, acil numara bölümü, tek sayfada dolu `rel_*`, `trip_links`'e ID dizisi,
> "2-3 gün" rota) hiçbir Gezi sayfasında kullanılmaz.

## Temel ilke

- Şablondaki her alanı doldurmak zorunlu değil; ölçü arama hacmi ve okurun ihtiyacı.
- Gereksiz bilgi zarar verir. Okur hızlıca anlayıp gidip yapabilmeli.
- Kararı asistan verir: Halil fikir söyler, doğru mu yanlış mı arama hacmine bakılarak karar verilir.
- Her başlık ve bölüme girerken arama hacmi araştırması yapılır; H2'ler ve İçindekiler satırları hacme göre yazılır.
- Bütün gezi rehberleri bu listeye göre taranıp düzeltilecek.
- Gezi şablonu referans şablondur; diğer bütün şablonların biçimi buna uyar.

## Açılış sırası (teknik)

1. `post_content` = `[wpcode id="22607"]` + boş satır + `[wpcode id="22608"]` (ikisi de zorunlu).
2. `layout_genis` = "1" post meta olarak yazılır (ACF değil). Bu olmadan Gezi sayfası yayına ÇIKMAZ: sidebar Astra düzeniyle çakışır, video gelince sıkışır, Gezilecek Yerler tam genişlik açılmaz.
3. `gz_yan_sutun_1..5` boş bırakılır (layout_genis=1 iken görünmez).
4. `card_stay_list` gz-place-card yapısıyla yazılır.
5. Ortaklıklar kısa kodla kart içine gömülür.
6. Yayın öncesi önizlemede YouTube videosu ve Gezilecek Yerler bölümü kontrol edilir.
7. Rank Math "Sütun İçeriği" işareti konur.
8. Etiketler konur (aşağıda).
9. Eski bir yazı taşındıysa 28829 çifte H1 listesinden çıkarılır.

## Etiket kuralı (30 Eylül 2026)

- Gezi sayfalarında "Görülecek Yerler" (ID 1188) etiketi YASAK: kullanılmaz, eklenmez, silinirse silinmiş kalır. Başka şablonlarda kullanılabilir.
- Her gezi rehberinde şehrin kendi adı etiket olarak yazılır (ör. Strazburg, Riquewihr, Obernai, Freiburg); etiket yoksa yeni oluşturulur.
- Etiket mantığı: Ülke · Gezi · Noel Pazarları (varsa) · Vizeli/Vizesiz · VLOG · ŞehirAdı.

## Giriş yazısı (hero_intro_text)

- Hook edecek, merak ettirecek cümlelerle açılır.
- En yüksek hacimli kelimeler (Ubersuggest) girişe yedirilir.
- Uzun paragraf değil, hap hap: kısa cümle, tek bilgi, nokta. Hedef 50-60 kelime.
- Girişteki sayılar sayfayla birebir tutar (girişte "16 durak" yazıp sayfada 14 durak olmaz).
- Ortaklık bildirimi girişin hook'unu kesmez: giriş yazısının altında, ilk bölümden önce durur; mobilde de tepede değil giriş altında.

## Hızlı Bilgiler kutusu boş kalmaz

- Hava (`api_weather_city`) ve yerel saat (`api_timezone`) her sayfada dolu.
- Ülkenin vize rehberi varsa vize satırına kesin link (`info_visa_link`).
- Dil rehberi varsa dil satırına kesin link (`info_language_link`, post ID yazılır).
- eSIM/internet rehberi varsa internet satırına kesin link (`info_esim_link`).
- Rehber sayfası henüz yoksa sonra hazırlanır, hazır olunca link eklenir.
- `info_emergency` ve `info_internet` doldurulur (site genelinde boş kalmıştı). `info_emergency` her sayfada kayıtlı değil; yazdıktan sonra okuyup doğrulanır.
- Çapa metni `hb_capa_vize` / `hb_capa_dil` / `hb_capa_esim` metalarıyla ezilir; ezilmezse `info_country` ve `info_language`'dan türer. Link başka ülkeye gidiyorsa (Vatikan → İtalya vize) metin ezilir. Bu metalar royal MCP ile yazılır.
- Hızlı Bilgiler alanı `<a>` etiketlerini siler; oraya bağlantı ya da ortaklık kısa kodu konmaz.

## Başlıklar

- Her H2 o konuda en çok aranan kalıpla birebir yazılır; amaç öne çıkan cevap kutusu. Sayfadan sayfaya değişebilir.
- Hacim ölçülemiyorsa üst terime bakılır, uydurma rakam yazılmaz.
- Başlıklar doğrudan aranan sorgulara karşılık gelecek şekilde düşünülür.
- H2 ezme: önce ACF alanı, boşsa aynı adlı post meta (`h2_transport`, `h2_stay`, `h2_food`, `h2_places`, `h2_tips`, `kart_mevsim`). Türkçe ek hatası çıkan başlık bu yolla düzeltilir.
- İçindekiler her sayfada olur (rich snippet için çok önemli).

## Kaç günde gezilir kutusu

- `gun_cevap`: tek cümlelik düz cevap (öne çıkan cevap kutusu için).
- `route_ozet_1 / _3 / _7` kümülatif: merkez / merkez + deniz / merkez + deniz + uzak durak.
- Alt satırda rozetteki rakamla çelişen gün sayısı yazılmaz (1/3/7 şablonda sabit).
- Ölçülmüş arama kelimeleri bu üç satıra sıkıştırılır.
- Yan sütun kartı `route_short/medium/long_desc` alanlarından beslenir, biçim "Başlık | Metin".

## Ne zaman gidilir (mevsim kartı)

- Kartı `season_*_temp` ("10-24°C") ve `season_*_durum` alanları besler; başlığı `kart_mevsim`. En az bir `season_*_temp` doluysa kart açılır.
- Uzun metin alanları `season_spring/summer/autumn/winter` basılmaz (ölü alan); mevsim bilgisi SSS'e ya da ipucu kartına yazılır.
- Sıcaklık kaynağı 1991-2020 normalleri (min-max, dönemdeki aylar birleştirilir). Ay etiketleri şablonda sabit.

## Bütçe bölümü

- Kapsam yüzde yüz net: kişi başı mı, günlük mü, otel ve uçak dahil mi hariç mi.
- Her fiyat resmi kaynaktan doğrulanır. Üçüncü parti bilet sitesi kaynak sayılmaz.
- **Standart kapsam: kişi başı, bir günlük, yemek dahil; konaklama ve uçak HARİÇ.** "€/gece" yazılmaz. `h2_budget` = "{Şehir} Pahalı mı? 2026 Günlük Bütçe". `budget_note` ilk cümlesi sabit: "Rakamlar kişi başı ve bir günlük. Yemek dahil; konaklama ve uçak bileti hariç." (Taormina ve Atina bu standarda çekilecek.)
- `card_budget_desc` kalemlidir: kaynak cümlesi · **Şehir içi ulaşım** · **Giriş ve turlar** (+ `[gbc_aff id=gyg_şehir]`) · **Yemek** · **Ücretsiz olanlar**. Eski `gz-chef-note gz-not--tasarruf` tek kutusu kullanılmaz.
- Fiyat yazma kuralı (sabit ücret tam, ortaklıkla satılan band): bkz. Yazım.

## Ulaşım bölümü

- Ulaşım bilgisi (tren, otobüs vb.) veriliyorsa ortaklık bağlantısı eklenir — ama bağlantı koyabilmek bir bloğu ayakta tutma gerekçesi değildir (iki kapı kuralı).
- Hat kodu, uluslararası okura dönük detay gibi TR okura gereksiz bilgi girmez; aynı sonuç birden çok blokta tekrar yazılmaz.
- Ulaşım alanları `trans_*_detail`; `trans_moto_detail` basılmaz.

## Nerede kalınır (card_stay_list)

- Sitenin en önemli yerlerinden biri: ihtiyaca göre araştırılır, ilgili bütün bölgeler için hazırlanır, satın aldırma odaklı, Türk okurun aradığı mantıkta.
- Bölge bölge kart: her bölgede ne yapılabileceği ve o bölgeye ayrı ortaklık bağlantısı; kısa, hap gibi, iyi Türkçe.
- "Şu bölgede kalın" deniyorsa bağlantı o bölgenin otellerini açar, genel şehir sayfasını değil; hedef yüzde yüz doğrulanır. Booking'de semt sayfası yoksa şehir sayfasına gidilir ve `sub_id` semte göre ayrılır.
- Otel adı geçiyorsa bağlantı doğrudan o otelin sayfasına gider.
- Girişte şehrin "tüm otelleri gör" bağlantısı mutlaka kalır.
- Otel motoru (`[gbc_otel dest=…]`) işe yaradığı test edilmeden sayfaya bağlanmaz.
- Kart yapısı (zorunlu):

```html
<div class="gz-places-wrapper">
<div class="gz-plan-baslik"><span class="gz-plan-no">Nerede kalınır</span> Şehirde hangi bölge size uyar <em>...</em><small><b>İlk kez:</b> ... <b>Bütçe:</b> ...<br />[gbc_aff id=bk_şehir]Şehirdeki tüm otelleri gör[/gbc_aff]</small></div>
<div class="gz-place-card">
<div class="gz-num-badge">1</div>
<div class="gz-inner-title">Bölge Adı<small>kısa açıklama</small></div>
<div class="gz-inner-tags"><b>özellik</b><b>özellik</b></div>
<p><b>Kime uyar:</b> ...<br /><b>Göze alın:</b> ...<br />[gbc_aff id=bk_şehir]Bölge otellerine bak[/gbc_aff]</p>
</div>
```

## Gezilecek yerler (card_places_list)

- Kartlara videoda geçmeyen yer ya da bilgi eklenmez; ne varsa o konur ("videoda geçmeyen şeyleri niye ekliyorsun").
- Numaralı kartlar yalnız GİDİLEN yerlerdir. Gidilmeyen yerler tarafsız tonda, fotoğrafsız, numarasız kart olarak girer.
- Biletli durakların hepsine ortaklık bağlantısı konur.
- Grubun genel bağlantısı kart grubunun başlığına (`gz-plan-baslik` içindeki `<small>`) konur. Biletli durağın kendi bağlantısı (`gyg_şehir_yer`) kartın sonunda, `gz-kart-aff` sınıflı `<p>` içinde durur.
- Kart listesi her zaman `gz-places-wrapper` iskeletidir; düz `<ul class="gbc-check">` listesi yer kartı yerine kullanılmaz.
- Grup başlığı kalıbı: `<div class="gz-plan-baslik"><span class="gz-plan-no">GRUP ADI</span> üst satır <em>alt satır</em><small>paragraf + [gbc_aff id=x]metin[/gbc_aff]</small></div>`.
- Kart gruplarının renkleri haritayla aynıdır: merkez turuncu, deniz mavi, yeme içme kırmızı.
- Kartlarda "YouTube'da izle · dakika" çipi olur; videoyu sayfa içinde o dakikadan başlatır. Çipe basınca ekran kararmaz, video yeniden yüklenmeden o dakikaya gider. Bütün sayfalarda aynı standart.
- Kart gövdesi tek `<p>`; etiketler `<div class="gz-inner-tags"><b>a</b><b>b</b></div>`, aralarında satır sonu yok.

## Ne yenir (card_food_list)

- Kartlar mekân kartıdır, yemek kartı değil. Başlık: "Mekân adı: öne çıkan yemek"; içinde orada yenenler ve deneyim. Mekân bilgisini Halil Google Haritalar kaydından verir (24 Eylül 2026).
- Kartlara harita linki ve adres konmaz; konum genel haritaya (My Maps) yönlendirilir.
- Kartlar arama hacmi ve ilgiye göre sıralanır.
- Giriş yazısında "X nedir" tanımları olmaz. Yerine: bölgenin en meşhur yemekleri, bunları denedik, bunları bu gezide denemedik.
- Yemeklerin anlatımı üstteki mutfak açıklamasına (giriş) gider; bölümün asıl işi mekân vermektir.
- Tek maddelik grup numarasız, tam genişlik karttır.
- Tarif açılmaz (ör. Taormina için arancini/cannoli tarifi yok).

## Gitmeden bilmen gerekenler (card_tips_list)

- Her kart gerekli mi diye bakılır. Arama hacmi olan, en çok merak edilen, okurun "çok iyi" diyeceği ve etkileneceği bilgiler kalır; gerisi çıkar.
- "Yola Çıkmadan" kartı yalnız vize/dil sayfası varsa konur. Buradaki H2'ler aynı kalıpta değil, her biri kendi en çok aranan kalıbına göre.
- Araç kiralama ortaklığı (DiscoverCars) bu karttaki ulaşım/araç ipucuna gömülür.

## Çevre durağı bölümü (Messina gibi)

- Her yazıda olmaz. Videoda geçtiyse eklenir.
- Dwell time için ilgili görsel eklenir, gereksiz bilgi olmadan; kareyi Halil videodan çeker.

## Kart görselleri

- Halil videodan ekran görüntülerini kart adıyla verir. Kalite bozulmadan en düşük boyuta indirilir, doğru karta eşleştirilir, en çok aranan kelimelere göre SEO dosya adı ve alt metinle yerleştirilir. Görsel verilen her rehberde bu iş yapılır.
- Görsel üretimi: 16:9 kaynak kırpılmaz, 1200x675, WebP kalite 85. Yöntem: bkz. Teknik.
- Şablon kart görselini kendisi basmaz; kalıp `<div class="gz-place-card">` açılışından hemen sonra: `<div class="gz-place-img"><img src="..." alt="..." width="1200" height="675" loading="lazy" decoding="async"></div>`. Görselli kartta görsel divi rozetten önce gelir.
- Görsel yüklenince alt metin, başlık, kısa açıklama, açıklama doldurulur.

## Rozet (numara)

- `gz-num-badge` bütün bölümlere konur (konaklama, gezilecek yerler, ne yenir, ipuçları). Şablon kendisi üretmez; unutulursa kartlar numarasız kalır.
- Kalıp: `<div class="gz-place-card">` açılışından hemen sonra `<div class="gz-num-badge">N</div>`, başlık divinden önce.
- Numarasız kart (rozetsiz) güvenlidir, tam genişlik basılır.

## Kart yüksekliği dengesi

- İki sütunlu ızgarada eşleşen iki kart aynı yüksekliğe gerilir. İkişer eşlenen kartların metin uzunluğu farkı 300 karakteri geçmemeli (100'ün altı sorun değil).
- Kısa karta gerçek bilgi eklenerek ya da kart görseli eklenerek dengelenir; kod hatası değildir.

## Harita (My Maps)

- Yerler Google Maps'ten bulunup test edilir; yüzde yüz doğru ve hatasız işaretlenir.
- Renk kodları sabit (yemek kırmızı, deniz mavi, merkez turuncu).
- Her pinde YouTube zaman kodu ve web linki olur.
- Yöntem: asistan katman başına KML üretir, Halil içe aktarır. Katman adı KML'de `<Document><name>`'den gelir; Türkçe karakterli ve düzgün yazılır.
- Haritayı basan alanlar `card_places_tag` (gezilecek yerler) ve `card_food_tag` (ne yenir). `place_location` haritayı basmaz, yalnız şema koordinatı içindir.
- Gömme kalıbı: `<iframe src="https://www.google.com/maps/d/u/0/embed?mid=..." width="100%" height="480" style="border:0" loading="lazy" title="..." allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>`.
- Harita iframe'i `card_places_tag`e `royal-mcp/acf-update-field` ile yazılır (post meta yazıcısı iframe'i siler).

## Video

- Üst video ve bölüm videoları kapak resmi (facade) olarak gelir, tıklayınca oynar.
- `hero_video_chapters` biçimi "MM:SS | Başlık | Açıklama"; yan sütunda "anlar" kartını besler.

## Yan sütun kartları

| Kart | Kaynağı |
|---|---|
| Hızlı Bilgiler `gz-k-hizli` | `info_*` alanları |
| Ne zaman gidilir `gz-k-mevsim` | `kart_mevsim` + `season_*_temp` |
| Kaç gün `gz-k-gun` | `route_short/medium/long_desc` |
| Anlar `gz-k-anlar` | `hero_video_chapters` |
| Durak / Sonra Nereye `gz-k-durak` | `trip_links` |
| Yakın yerler `gz-k-yakin` | `gz_yakin_yerler` (+ `gz_yakin_bas` başlık) |
| Hızlı Plan `gz-k-plan` | `gz_yakin_ust` doluysa yakın yerler kartı Hızlı Plan'a döner |

- **Hızlı Plan:** ortaklıklar sayfada kaybolmasın diye sağ sütunda uçak, otel ve temel yerlerin biletleri tek yerde durur; Hızlı Bilgiler'in hemen altına çıkar. Satır başlığı 37 karakteri geçmez. eSIM Hızlı Plan'a girmez.
- **Sonra Nereye?** aynı ülkedeki gezi rehberlerini sağında oklu liste olarak gösterir. `trip_links` satırı "Başlık | URL | Açıklama | " (dört parça, sonuncusu boş), satırlar `\r\n`. `## Başlık | alt satır` satırı yeni ayrı kart açar. `trip_links` textarea'dır; içine dizi (ID listesi) yazılmaz.
- **Hızlı Plan: Gitmeden 6 Adım** (`gz_yakin_bas` + `gz_yakin_ust`=1 + `gz_yakin_yerler`): kısa kod çözülür (şablon `do_shortcode` uygular). Tam 6 satır `[gbc_aff id=X_yan stil=yan]N. Adım: Başlık | Açıklama[/gbc_aff]`, sıra: 1 uçak (Skyscanner) · 2 otel · 3 aktarma treni · 4 tur/bilet · 5 ikinci tur ya da yedek otel · 6 araç. Ayrıntı: skill.
- **Yakın yerler** kartının eski ham HTML kalıbı (Hızlı Plan'a dönmemiş sayfalar için): Satır kalıbı: `<a class="gz-serie-row gz-yakin-row gbc-in" href="HEDEF" target="_blank" rel="sponsored nofollow noopener" data-aff="ID" data-prog="PROGRAM" data-post="POSTID"><span class="gz-serie-txt"><b class="gz-serie-ad">Ad</b><small>Tek cümle açıklama</small></span></a>`. Satıra elle ok ya da `gz-ok-dis` span'i konmaz; ok CSS'ten gelir. Kart rota mantığıyla kurulur (günübirlik turlar + şehirler arası rota bağlantıları). Aynı sayfadaki id tekrar kullanılmaz, yeni `_yan` id üretilir.

## SSS

- Bütün arama sonuçlarına cevap verir, sayfadaki bilgileri asla tekrar etmez (tek yer kuralı, bkz. Yazım Kuralları; SSS için istisna yok). Arama hacimlerinin hepsini ve bütün seyahat niyetlerini kapsayacak şekilde kurulur.
- Arama hacmi yüksek soru SSS'e gömülmez; üste konur.

## Yazar kutusu ve bildirim

- Yazar kutusu en altta mutlaka olur (E-E-A-T); dipte kopuk durmaz, SSS'in altında onunla bağlantılı ve şık.
- Ortaklık bildirimi tek yerde, giriş altında; bağlantı yanında elle etiket yok. Bkz. Ortaklık.

## Şema

- Gezi şeması: TouristDestination + TouristTrip + Clip (+ Article, Person, Organization, ImageObject, FAQPage, WebPage, WebSite, BreadcrumbList).
- Şema durakları kendiliğinden gelir: sayfaya gömülü Google Haritalarım haritası (iframe'de `mid=`) 7 günde bir okunur, adı ve koordinatı olan her iğne TouristDestination.includesAttraction olarak basılır. Tür, katman ve iğne adından çıkar (Yeme İçme → FoodEstablishment, pastane → Bakery, plaj → Beach, katedral/manastır → Landmark, gerisi TouristAttraction). Adı ya da koordinatı eksik iğne, çizgi/rota ve haritası olmayan sayfa için hiçbir şey yazılmaz.
- Her yeni gezi rehberinde harita katmanlı kurulur (Merkez / Deniz / Yeme İçme gibi); iğne adı sayfadaki kart adıyla aynı yazılır. İğne açıklamasındaki "Rehber" bağlantısı sayfanın gerçek bölüm çapasını göstermeli; çapa sayfada yoksa şemaya konmaz.
- `gbc_schema_yerler` metası (satır: `Tür | Ad | lat,lng | çapa | adres`) elle doldurulursa haritadan geleni ezer; yalnız haritada olmayan bir yer gerekiyorsa kullanılır. `gbc_schema_geo` şehrin koordinatını verir.
- Schema'ya turist rehberine uygun özel yapılar eklenir. Şema motoru içerikteki yeni görselleri kendisi alır.
- Video adındaki bayrak emojisi SEO'ya zarar vermiyorsa kalabilir.
- Sosyal paylaşım görseli: `rank_math_facebook_image` + `rank_math_facebook_image_id`; og:image canlıda doğrulanır.

## Ek bölümler (⑪b) ve ölü alanlar

- 15 Eylül 2026'dan beri basılır (şablon ⑪b): `card_trans_list` (`h2_trans_ici`), `card_trip_list` (`h2_trip`), `card_shop_list` + `shop_tax_info` (`h2_shop`), `card_hist_list` (`h2_hist`), `card_event_list` (`h2_event`, Noel pazarı), `card_kid_list` (`h2_kid`), `card_night_list` (`h2_night`), `card_photo_list` (`h2_photo`). Boşsa bölüm basılmaz. `card_trans_list` ve `card_safe_list` Gezi'de varsayılan BOŞ kalır.
- `season_spring/summer/autumn/winter` uzun metinleri basılmaz.
- `trans_moto_detail` basılmaz.
- Basıldığı doğrulananlar: `card_places_list`, `card_food_list`, `card_stay_list`, `card_tips_list`, `trans_*_detail`, `hero_intro_text`.

## Ortaklık yerleşimi (Gezi)

| Alan | Ne girer |
|---|---|
| `card_stay_list` | Booking `[gbc_aff id=bk_şehir]` — her bölge/tip için ayrı |
| `food_summary` sonu ya da `card_tips_list` | GetYourGuide `[gbc_aff id=gyg_şehir]` — tur ve aktivite |
| `card_tips_list` | DiscoverCars `[gbc_aff id=dc_şehir]` — araç ipucuna gömülü |
| `card_places_list` | grup başlığında; kart başına opsiyonel `gz-kart-aff` |
| `trans_*_detail` | ulaşım bağlantıları (uçak, feribot, araç) |

- Yan yana 2-3 hizmet: `[gbc_aff_kutu ids=bk_şehir,gyg_şehir bas=Başlık not=yok]`.
- eSIM ortaklığı gezi sayfasında satılmaz; sayfada o yerin kendi değerleri satılır: uçak, otel, bilet, tur, transfer.
- Diğer bütün ortaklık kuralları: bkz. Ortaklık.

## Bitiş testi

- Sayfa bitince baştan sona test edilir: hız, schema.org, H1/H2 yapısı, SEO, arama kapsamı yüzdesi. Önce analiz, sonra düzeltme.
- Test-düzelt-test robot gibi yapılır, hiçbir madde kaçmaz.
- Ortaklık doğrulama denetimi (altı kontrol), mobil 390 px taşma, İçindekiler ve yazar kutusu varlığı canlıda ölçülür.
- Sayfa Denetimi (GBC skoru) çalıştırılır. Hedef %100'e yakın, en az %90 (%70 yalnız alt sınır).
  Yapılacaklar kutusundaki her madde düzeltilir, denetim yeniden çalıştırılır; madde atlanmaz
  (1 Ekim 2026, Halil). Ayrıntı: gbc-gezi-rehberi skill'i, Ek A 5.16.

## Örnek sayfa: Sorrento (31232)

- Halil, 30 Eylül 2026: "Sorrento baştan sona örnek." Yeni gezi rehberi kurulurken ya da eskisi düzeltilirken bu sayfa kalıp alınır:
  kelime evreni (her kelimenin alaka kararı yazılı, kararsız kalmaz), tek yer kuralı (aynı bilgi bir kez), katmanlı harita (Merkez / Deniz / Yeme İçme)
  ve haritadan gelen şema durakları, yalnız alanında ve kaynağıyla yazılmış rakamlar, 5 SSS.
- Sıradaki sayfalar teker teker, en az değişiklikle bu kalıba getirilir (ilk: Strazburg).
