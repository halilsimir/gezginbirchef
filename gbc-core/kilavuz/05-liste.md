# Liste Sayfası

> Liste şablonu (23108/23109) — gezilecek yerler, ne yenir, nerede kalınır, vize, dil ve bölge hub sayfaları için alanlar ve kurallar.

## Ne için

- Gezi (yer) ile Detay (tek nokta) arasındaki alt silo: gezilecek yerler, ne yenir, nerede kalınır.
- Yatay silo sayfaları: vize rehberleri, dil (kelimeler) sayfaları.
- Plan tablosunda "… Ana Hub" satırları Liste şablonundadır.
- "Görülecek Yerler" etiketi Liste'de kullanılabilir (yalnız Gezi'de yasak).

## Kurulum şartı: etiket

- Gezi Liste alan grubu `gezilecek-yerler` etiketine bağlıdır. Etiket yoksa alanlar wp-admin'de ve REST'te görünmez, yazmalar sessizce başarısız olur. Vize ve dil sayfaları dahil her Liste sayfasında bu etiket durur, kaldırılmaz.

## Alanlar

- ACF: `hero_custom_title_listed`, `hero_badge_listed`, `hero_intro_text_listed` (giriş metni), `table_guide_listed`, `place_location_listed`, `legend_title_listed`, `hero_video_listed`, `main_card_listed_1..50`.
- `travel_guide_listed` ile yazma çalışmaz; giriş için `hero_intro_text_listed` kullanılır.
- Meta: `gz_yan_sutun_1..5`, `gz_seri_alt`, `rank_math_description`, `gbc_kart_etiketi`, `gbc_itemlist_kapali`, `pill_inceleme_listed`, `gbc_otel_dest`, `h2_ozet_listed`, `etiket_video_listed`, `etiket_konum_listed`, `rehber_faq`, `h2_sss_listed`.
- `table_guide_listed` sayfanın üstündeki "ÖZET BİLGİLER & İPUÇLARI" açılır bölümüne basılır. `place_location_listed` ana harita kutusuna.
- `detailed_main_content` Liste'de basılmaz (Detay'dan kalma ölü veri).

## Gövde: kart grupları

- Gövde `main_card_listed_N` gruplarındadır. Her grup: `card_title_listed` (bölümün H2'si), `card_desc_listed` (HTML gövde), `card_video_listed`, `card_insta_listed`, `card_image_listed`, `card_map_code_listed`.
- Kartın tek kapısı `card_title_listed`: başlık doluysa kart basılır, boşsa basılmaz (kart gizleme yolu budur).
- Kart gövdesi `<ul class="gz2-facts"><li>etiket</li>...</ul>` ile başlar, sonra satır içi stilsiz `<p>` ve `<ul>` gelir. Inline style yazılmaz; biçimi şablon CSS'i verir.
- Kart çapaları `#place-1`, `#place-2`… otomatiktir.
- Görsel yoksa medya kolonu basılmaz, metin tam genişliğe yayılır. Kart numarası H2 başındaki `v1-h2-num`'dan gelir.
- Vize rehberlerinde `gbc_kart_etiketi` "Bölüm" olur ve `gbc_itemlist_kapali=1` yazılır (kartlar durak değil bölüm; ItemList şeması kapatılır).

## Yan sütun (gz_yan_sutun_1..5)

- Beş meta `aside.v1-flow-side` içinde basılır; beşi de boşsa sütun basılmaz. Kısa kodlar burada çalışır, ortaklık konabilir. Mobilde kutular akışa dağılır.
- Kutu kalıpları:
  - Atlama kutusu: `<section class="gz2-quick"><h2 class="gz2-qh">En çok sorulan N şey</h2>` + satır `<a class="gz2-side-w" href="#place-N"><span class="t">soru</span><span class="g">kısa cevap</span><span class="r">Bölüm N →</span></a>`. Ok `.r::after` içindedir; anchor'a `::after` eklenmez (kartı bozar).
  - Bilgi kutusu: `<section class="gz2-hizli"><h2 class="gz2-hh">Başlık</h2>` + `<div class="gz2-hr"><span class="gz2-hl">etiket</span><span class="gz2-hv">değer</span></div>` + kapanışta `<p class="gz2-hnote">kaynak notu</p>`.
  - İlgili rehberler: `[gbc_alakali etiket=<etiket> adet=6]giriş cümlesi[/gbc_alakali]`.
  - Ortaklık kutusu: `[gbc_aff_kutu ids=a,b bas=Başlık]`.
- Gövde ve yan sütun aynı hedefi gösterecekse aynı sub_id iki kez kullanılmaz; `<id>_yan` diye ikinci satır üretilir.

## Kısa kod çalışmayan alanlar

- `table_guide_listed` ve `rehber_faq` kısa kod çalıştırmaz; `[gbc_aff]` yazılırsa okura ham metin görünür.

## Adım ve kontrol blokları

- `gbc-step` liste değildir; her adım ayrı div: `<div class="gbc-step"><span class="n">1</span><span class="h">başlık</span><span class="d">açıklama</span></div>`. ul/li yazılırsa metin harf harf kayar.
- `gbc-check` ul/li + small ile doğru çalışır.

## Tablolar

- Mobilde taşan tablo `<div class="gbc-tablo-kaydir" style="overflow-x:auto;-webkit-overflow-scrolling:touch">` ile sarılır; tablo kendi içinde kayar.

## İlk ekran

- Uzun rehberde ilk ekran soruyu cevaplar. Girişin ilk paragrafı "üç cümlede cevap" olur; rehberin ne anlattığını anlatan paragraf ikinci sıraya gider.

## Vize sayfaları

- Yüzde yüz doğru, tamamen resmî kaynaklı, Halil'i hukuki olarak koruyan içerik; mümkün olan yere ortaklık bağlantısı.
- "Bu sayfa ne değildir" uyarısı girişte değil, en altta durur.
- Yunanistan, İspanya, İtalya vize sayfaları aynı mantıkta düzenlenir; mobil ve masaüstünde kayma kontrol edilir.
- Resmî kurum sitelerine bağlantı verilmez; kaynak adı ve son kontrol tarihi metin olarak yazılır (bkz. Yazım).

## Dil (kelimeler) sayfaları

- Amaç trafik: "paratoner" gibi gelen okuru rehberlere akıtmak; değer iç linklemede.
- 5.000/ay üstü açılmaya değer; 6.500 yeterli görüldü.
- Bütün ilgili gezi rehberleri dil sayfasına bağlanır (Gezi'de `info_language_link`).

## İçindekiler

- `v1-lejant` taşıyan Liste sayfalarında İçindekiler kutusu bilerek basılmaz (kasıtlı kural, "gerekmez" sayılır).

## Şema

- Ne yenir listesi: ItemList + Restaurant. ItemList'e koordinatlı kartlar girer.

## Bilinen görsel hata

- Görselsiz kartta çifte numara (turuncu kare + siyah daire): Customizer Ek CSS'teki eski `gbc-kart` sayacı ile 23109'daki `v1-h2-num` aynı anda aktif. Kalıcı çözüm Ek CSS'teki eski bloğu kaldırmak ya da `.entry-content .v1-card-item:has(.v1-h2-num) .v1-card-h2::before{content:none!important}` eklemek. Geçici sayfa içi çözüm olarak eklenen `v1-card-num-overlay` span'leri kalıcı çözümden sonra temizlenir.

## Yazma yolu

- Tarayıcıdan REST ile alan ADIYLA yazılabilir: `{acf:{hero_custom_title_listed:...}}`. Kart grubuna yazarken grubun tamamı gönderilir: `{acf:{main_card_listed_7:{card_title_listed:..., card_desc_listed:...}}}`; kısa kodlar korunur.
- royal MCP yolunda alan ANAHTARI zorunludur; iç içe alt alan (`main_card_listed_N_card_desc_listed`) düz meta anahtarıyla `royal_mcp_wp_update_post_meta` ile yazılır.
- Yeni sayfada referans satırları (`_alan_adi = field_xxx`) da yazılır; anahtarlar kart başına farklıdır, dolu bir sayfadan (İtalya vize 24548) kopyalanır.
- `gz_yan_sutun_*` REST'e kayıtlı değildir, royal MCP ile yazılır.
- `main_card_listed_N` bir gruptur (nesne); dolu alan sayarken string filtresiyle bakılırsa sayfa boş sanılır.

Eksik: bu şablon için içerik bölüm sırası (hangi kart hangi sırada) henüz yazılmış kural yok; ilk çalışmada eklenecek.
