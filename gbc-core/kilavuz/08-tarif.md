# Tarif Sayfası

> Tarif şablonu (24751/24752) — sitenin en büyük içerik grubu olan yemek tarifleri için bilinen kurallar.

## Genel

- Sitede 756 tarif var; site trafiği tarif ağırlıklıdır.
- Tarif şablonu kısa kod çözmez; ortaklık kısa kodu tarif sayfasına normal yoldan konamaz.
- Şef ifadeleri ("Şef Gözüyle Notlar" gibi) yemek sayfalarında kalabilir (bkz. Yazım).
- Gezi rehberinin içinde tarif açılmaz (ör. Taormina için arancini/cannoli tarifi yok).

## Alanlar

- Bilinen alanlar: `tarif_giris`, `tarif_temel`, `tarif_faq`, `tarif_eslikciler`, `tarif_alt_linkler`, `tarif_puan`, `tarif_puan_sayisi`. Tarif şablonunun ACF grubunda 30'dan fazla alan var.
- Alanların çoğu ACF işaretçisi olmadan yazılmış; alan doluluğu `get_fields()` ile değil, grup tanımından alınan adlar + `get_post_meta` ile okunur (bkz. Teknik).
- `tarif_puan` ve `tarif_puan_sayisi` okurdan gelir; boş olması normaldir, eksik sayılmaz.
- `tarif_alt_linkler` boşsa modül kutuyu hiç basmaz; bir şey bozmaz ama doldurulması gereken içeriktir.

## Alkol ikame sözlüğü

Alkol malzeme temiz ikameyle çıkarılır; orijinali "alternatif" diye anılmaz.

| Çıkan | Yerine |
|---|---|
| Kuru beyaz şarap (deglaze) | Yarım bardak et suyu + 1 yk beyaz üzüm sirkesi |
| Votka (ganaj) | 1 yk glikoz şurubu |
| Kanyak | Elma suyu |
| Brendi | Portakal suyu |
| Sake / pirinç şarabı | Mirin (fermente pirinçten tatlı Japon soslandırması) |
| Sherry ve şarap sirkesi | Elma sirkesi |
| Mastika likörü | Toz damla sakızı |
| Bira/şarap eşlikçisi | Ayran, limonata, arpa çayı, maden suyu |
| Aperol/Campari Spritz | Aperitivo |

- "Rakı/içki sofrası" kalıbı "meze sofrası" olur.
- İkameyle birlikte ilgili başlık, alerjen satırı ve SEO başlığı da güncellenir.

## Şema

- Recipe şeması basılır.
- Rank Math'ten kalma sahte VideoObject şeması (ör. `embedUrl` sample-video) bulunursa `rank_math_schema_VideoObject` ve `_gbc_yt_ilk` metaları silinir; Recipe şeması durur.

Eksik: bu şablon için içerik yazımı (giriş, malzeme, adım, SSS düzeni) konusunda henüz yazılmış kural yok; ilk çalışmada eklenecek.
