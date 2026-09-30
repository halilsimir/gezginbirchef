# Rota Sayfası

> Rota şablonu (23340/23342) — kaldırılacak şablonun bilinen kuralları; mevcut sayfalara dokunurken okunur.

## Durum

- Rota şablonu kaldırılacak; Rota sayfalarına yeni iş yapılmaz. Rotalar bundan sonra gezi rehberinin içine girer.
- Sitede 2 Rota sayfası var (ör. `/bolonya-bologna-gezi-rotalari-plan/`). Atina gezi rotaları Atina ana sayfasına alındı.

## Alanlar

- Alanlar (`_listed` ekli): `rota_badge_listed`, `rota_custom_title_listed`, `rota_intro_text_listed`, `rota_video_listed`, `rota_travel_post_listed`, `rota_travel_link_listed`, `rota_travel_location_listed`, `rota_list_link_listed`, `rota_food_post_listed`, `rota_food_link_listed`, `rota_food_location_listed`.

## Kısa kod

- Kısa kodu yalnız giriş metni (`rota_intro_text_listed`) çalıştırır.
- `rota_travel_post_listed` ve `rota_food_post_listed` alanlarına kısa kod YAZILMAZ: ayrıştırıcı ilk köşeli parantezi bölge etiketi sanıp siler.

## Yapı

- Şablon sayfada h2 basmaz; bölüm başlıkları `<h3 class="gbc-rota-gun-bas">` ("1. Gün") ya da `v11-block-header` / `v11-controls-header` div'leridir. İçindekiler bunları başlık sayar, etiketleri değiştirmez.
- Şablondaki sabit emojiler (alkol ikonları dahil) silindi; geri eklenmez.
- Sayfada YouTube ve Google Maps iframe'leri var; video kapak resmiyle (facade) gelir.

Eksik: bu şablon için içerik yazım kuralı yok ve yazılmayacak (şablon kalkıyor); gerekirse ilk çalışmada eklenecek.
