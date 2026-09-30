# Detay Sayfası

> Detay şablonu (23489/23490) — tek mekân, tek anıt, tek müze sayfaları için alanlar ve kurallar.

## Ne için

- Yalnız tek mekân, tek anıt, tek müze. Şehir, ada, bölge ve ülke rehberleri Detay'da duramaz; Gezi'ye çevrilir.
- Detay sayfası bir Liste sayfasının altına girer (yemek mekânı → ne yenir listesi; gezilecek nokta → gezilecek yerler listesi) ve ana rehbere bağlanır.
- Arama hacmi yüksek tek noktalara kendi Detay sayfası açılır, görselli ve tam açıklamalı.
- Trafik alan format mekân/Detay sayfalarıdır; ortaklık önceliği yüksektir. İstanbul mekân sayfalarına ortaklık konmaz.

## Kurulum şartı: etiket

- Detay alan grubu (23478) yalnız `gorulecek-yerler` (1188), `restoranlar` veya `pazarlar` etiketi varken kayıtlıdır. Etiket yoksa `detailed_*` alanları REST'te görünmez, yazmalar sessizce başarısız olur. Taşımadan ya da açmadan önce etiket eklenir.

## Alanlar

- ACF: `detailed_hero_badge`, `detailed_hero_title`, `detailed_hero_intro`, `detailed_main_content`, `detailed_table`, `detailed_map`, `detailed_video_*`, `detay_resim_*`, `detailed_main_guide_link`.
- Meta: `detailed_h2_galeri`, `detailed_h2_harita`, `detailed_h2_sss`, `detailed_kart_hizli`, `rehber_faq`.
- Kısa kod (ortaklık dahil) yalnız `detailed_main_content` içinde çalışır.

## SSS iki yerde

- SSS metni hem `rehber_faq` metasında hem `detailed_main_content` içindeki `<details>` bloklarında durur. Birini düzeltmek diğerini düzeltmez; ikisi de ayrı yazılır.
- `rehber_faq` REST'te top-level alandır; okuma ve yazma royal MCP ile yapılır.

## Eski yazıyı Detay'a taşıma yöntemi

1. Etiket 1188 (ya da restoranlar/pazarlar) eklenir.
2. `post_content`'ten Gutenberg yorumları (`<!-- wp:... -->`) temizlenir.
3. YouTube adresi `detailed_video_1`'e, Google Maps iframe'i `detailed_map`'e ayrılır.
4. İlk `<p>` `detailed_hero_intro` olur; başlık `detailed_hero_title`'a gider.
5. Kalan gövde `<div class="gz-list-inner-content">` ile sarılıp `detailed_main_content`'e yazılır.
6. `post_content` `[wpcode id="23489"]` + `[wpcode id="23490"]` ile değiştirilir.
7. Yazının ID'si 28829 çifte H1 listesinden çıkarılır.

## Tablolar

- `table.venue-table` mobilde kendi içinde kayar (şablon CSS'inde tanımlı); taşma kontrolü 390 px'te yapılır.

## Şema

- Mekân: Restaurant. Landmark: TouristAttraction/Place. Hepsinde Article + Person + Organization + ImageObject + FAQPage + WebPage + WebSite + BreadcrumbList.

## Alkol

- Mekânın tek konsepti içki satışıysa kart gizlenir ya da mekân çıkarılır; bkz. Yazım.

Eksik: bu şablon için içerik yazımı (bölüm sırası, giriş, kart düzeni) konusunda henüz yazılmış kural yok; ilk çalışmada eklenecek.
