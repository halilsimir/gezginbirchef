# Tasarım Kuralları

> Renk, tipografi, ok, kart, yan sütun ve mobil düzen kuralları; CSS'e ya da kart yapısına dokunmadan önce okunur.

## Biçim birliği (21 Eylül 2026)

- Gezi rehberi referans şablondur. Bütün şablonlar (Liste, Detay, Tarif, Blog, ana sayfa) yazı boyutu, satır aralığı, boşluk, başlık ve renk bakımından yüzde yüz Gezi'yle aynı olur: "üç birbirinden ayrı olamaz".
- Ok, imleç ve benzeri işaretler metne elle yazılmaz; hepsi CSS'ten gelir, sistem otomatik işler.
- Ortaklık bağlantılarının kendi kodlama sistemi var; ok ve biçim kuralları onları bozmaz.
- CSS/tasarım "unique" olmalı.

## Tasarım sözlüğü

- Bütün ölçü ve renkler Ek CSS 23855'in en başındaki `:root` bloğundadır: `--gbc-govde-punto/satir`, `--gbc-madde-satir/alt`, `--gbc-h2-punto/alt`, `--gbc-h3-punto/alt`, `--gbc-baslik-satir`, `--gbc-not-punto/satir`, `--gbc-sss-soru-punto`, `--gbc-sss-cevap-punto`, `--gbc-dipnot-punto/satir`, `--gbc-rozet-*`, `--gbc-ok-boy/bosluk/hiza`, `--gbc-ok-sag/capraz/asagi`, `--gbc-giris-punto/satir`.
- Şablon CSS'leri (22608, 23109, 23490, 24752, 26923, 23342, 23533) ve 28213, 30350 gömülü stilleri bu değişkenlerden okur.
- Bir rol tutmuyorsa çözüm özgüllüğü şişirmek değil, şablondaki kuralı sözlüğe bağlamaktır.

## Renk

- Turuncu iki adla tanımlı: `--gbc-turuncu` #E65100 = dolgu; `--gbc-turuncu-metin` #BF360C = yazı, ok, ikon.
- Bu iki tanım yeniden yazılmaz. Toplu renk değişikliğinden (`#BF360C → var(...)`) sonra `:root` tanımları mutlaka kontrol edilir; tanım kendine referans verirse bütün oklar görünmez olur.
- Kart grupları ve harita katmanları aynı renktedir: merkez turuncu, deniz mavi, yeme içme kırmızı.

## Tipografi (Gezi referansı)

| Rol | Ölçü |
|---|---|
| Gövde | 18px / 1.8 |
| Liste maddesi | 18px / 1.72 |
| H2 | 22px / 1.3 |
| Kart başlığı ve H3 | 20px / 1.3 |
| Not kutusu | 17px / 1.7 |
| SSS sorusu | 17px |
| SSS cevabı | 16px / 1.8 |
| Kaynak dipnotu | 14px / 1.6 |
| Giriş paragrafı | 18.5px / 1.62 |
| İş birliği rozeti | 11.5px, yazı #6E6E6E, zemin #EFEFEF, köşe 4px |

## Oklar

- Standart: site içi bağlantı →, site dışı bağlantı ↗ (yukarı çapraz).
- Ok motoru (22607 `gbc_ok_bas`) sınıfsız `<a>`'ya site içi `gz-ok-ic`, site dışı `gz-ok-dis` span'i ekler. Harf olarak ok basmaz. Paylaş düğmeleri (`gz-share-a`) atlanır.
- Ok çizimi SVG maske: 13x13, vertical-align -2px, margin-left 6px, #BF360C. Aşağı/yukarı/sol oklar aynı maskenin döndürülmüşüdür.
- İçerik bağlantısına ayrıca CSS `::after` oku yazılmaz (çift ok çıkar). Ortaklık satırlarında ok `gbc-in` sınıfından ve `.gz-serie-ad::after`'dan gelir; elle span konmaz.
- `a.gz2-side-w` grid'dir; ok `.r::after` içine yazılır, anchor'a `::after` eklenmez.
- Satır başına tam bir ok olur.

## Gezi sayfa düzeni

- `layout_genis=1` her Gezi sayfasında zorunludur (yoksa sağ sütun sıkışır). Bu açıkken `gz_yan_sutun_1..5` kullanılmaz, ortaklıklar kart içine taşınır.
- Bölüm giriş yazıları sıkışık değil tam genişliktir.
- Sağ sütun kartlarının sırası: Hızlı Bilgiler en üstte, Hızlı Plan hemen altında.
- Yazar kutusu SSS'in altında, onunla bağlantılı.

## Kartlar

- `gz-place-card` yapısı: (varsa) görsel divi → rozet → başlık → etiketler → tek `<p>` gövde. Yapı: bkz. Gezi.
- Kart görseli CSS `:has(.gz-place-img img)` ile görselli düzene geçer; rozet görselin üstüne biner, görsel en üste çıkar, kart genişliği `calc(100% + 40px)` taşar (normal).
- Rozet (`gz-num-badge`) numaralı kartlarda bütün bölümlerde olur; numarasız kart tam genişlik basılır.
- İki sütunlu ızgarada eşleşen kartların metin uzunluğu dengelenir (fark 300 karakteri geçmez).
- Liste'de görsel yoksa medya kolonu basılmaz, metin tam genişlik; "Görsel Yok" kutusu basılmaz.
- Emoji yok.

## Mobil

- Ölçüm 390 px genişlikte yapılır; yatay kayma olmaz.
- Gezi şablonu mobilde `.gz-main-column` `display:contents` + CSS `order` ile dizilir. Bir öğenin yeri masaüstünde doğru görünse de mobilde başka yere çıkabilir; yer değişikliği 390 px'te ölçülür.
- Yan sütunlar (`aside.gz-sidebar-column`, Liste `v1-flow-side`) mobilde `display:contents` alır, kartlar akışa dağılır ve görünür kalır; bu hata değildir.
- Taşan tablo `gbc-tablo-kaydir` sarmalıyla kendi içinde kayar.
- Harita iframe'i `width="100%"`.

## CSS yükleme

- Ek CSS (`customizer-*.css`) şablon CSS'lerinden önce yüklenir; aynı özgüllükte şablon kuralı kazanır.
- Şablon CSS'i şablon başına ayrı dosyadır; CSS düzenlemesinden sonra LiteSpeed Purge All zorunludur. Bkz. Teknik.
