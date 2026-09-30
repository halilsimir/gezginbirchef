# gezginbirchef.com: Gezi sayfası kurgusu

Bu dosya her oturumda okunur. Bir Gezi (22607/22608) sayfası açılırken ya da
düzeltilirken aşağıdaki sıra izlenir. Kurallar Halil'in (site sahibi) verdiği
yönergelerden derlendi; örnek sayfa Sorrento (31232), ikinci örnek Strazburg
(31480). Kural çelişkisinde GBC İşletim Anayasası ve ortaklık defteri (30120)
kazanır.

## 1. Video ve transkript

- Video birden fazla şehri kapsıyorsa **yalnız o şehrin bölümü** kullanılır.
  Transkriptten o bölümün başlangıç ve bitiş saniyesini bul, gerisini at.
- `hero_video_chapters` yalnız o bölümün zaman damgalarıyla yazılır:
  `MM:SS | Başlık | Kısa açıklama`. Başka şehrin sahnesi girmez.
- `hero_video_caption`: bölümün videoda nerede başladığını söyler.
- Yer kartlarına video çipi: `<a class="gz-vid-at gz-vid-cip" href="https://www.youtube.com/watch?v=ID&amp;t=SANIYEs" target="_blank" rel="noopener">YouTube'da izle · MM:SS</a>`
- Birinci ağız ("gittik, yedik") yalnız o şehrin bölümünde karşılığı varsa.
  O bölümde yemek sahnesi yoksa yemek kartı tarafsız yazılır ve bu açıkça
  söylenir: "yemediğimizi yemiş gibi anlatmıyoruz" (Sorrento emsali).

## 2. Arama hacmi ve başlıklar

- Ubersuggest `keyword_suggestions`, dil `tr`, locId `2792`.
- Futbol, maç, hava durumu gibi seyahat dışı sorguları ayıkla.
- En yüksek hacimli seyahat sorgusu: başlığın, `rank_math_title`'ın ve
  `hero_intro_text` ilk cümlesinin başına. ("strazburg nerede" 2.400 ise ilk
  cümle nerede olduğunu söyler.)
- SSS (`faq_1..10`) hacim sırasıyla dizilir; en çok aranan soru 1 numara.
- Aynı konuyu hedefleyen eski sayfa (Detay, Liste) varsa yamyamlık notu
  düşülür, eski sayfanın odak kelimesi sahibine sorulmadan değiştirilmez.

## 3. Bütçe: gecelik değil, günlük

- `budget_low/mid/high_price`: **kişi başı, bir günlük**, yemek dahil,
  konaklama ve uçak hariç. "€/gece" yazılmaz.
- Her bandın `_desc` alanı o günün kalemlerini anlatır (ulaşım bileti,
  giriş, tur, öğle ve akşam yemeği).
- `card_budget_desc`: kalem kalem fiyat + kaynak + son kontrol tarihi.
  Başlıklar: Şehir içi ulaşım · Giriş ve turlar · Yemek · Ücretsiz olanlar.
- Rakam resmî kaynaktan (işletme, belediye, ulaşım idaresi) ya da bizim
  fişimizden gelir. İkisi yoksa bant olarak ve kaynağıyla yazılır.

## 4. Kaç günde gezilir

- `gun_cevap` (meta): tek paragraf net cevap. 1 gün, 2 gün ve uzun kalış
  (çevre şehirler) aynı cevapta.
- `route_short/medium/long_desc` + `route_ozet_1/3/7` (meta). Şablon
  etiketleri 1 / 3 / 7 sabit; "2 gün" şablon değişikliği ister, onaysız
  dokunulmaz, 2 gün `gun_cevap` ve medium satırında yazılır.
- `route_onerilen` (meta): sahibin önerdiği gün (Strazburg: 1).

## 5. Nasıl gidilir

- Türkiye'den gerçek rota yazılır: direkt uçuşun olduğu havalimanı + oradan
  şehre aktarma. Direkt sefer yoksa bunu söyle ve tarihiyle yaz.
- Alternatif rotalar (Paris, Frankfurt) yalnız tek cümleyle, mantığıyla.
- Uçuş bağlantısı Skyscanner (Impact) üzerinden, kısa kodla. Defterde satır
  yoksa **boş bağlantılı** satır açılır (`sky_yer` ve `sky_yer_yan`);
  boş satır sayfada hiçbir şey basmaz. Kısa bağlantıyı sahibi Impact
  panelinden üretir, sonuna `?subId1=defter_id` eklenir.

## 6. Hızlı Plan: Gitmeden 5 Adım (yan sütun)

Meta alanları (ACF değil, royal `wp_update_post_meta` ile yazılır):

- `gz_yakin_bas` = "{Şehir} Hızlı Plan: Gitmeden 5 Adım"
- `gz_yakin_ust` = "1"
- `gz_yakin_yerler` = beş satır, her satır bir `stil=yan` kısa kodu:
  1. Uçak (`sky_…_yan`) 2. Otel (`bk_…_yan`) 3. Tur/bilet (`gyg_…_yan`)
  4. Araç (`dc_…_yan`) 5. Şehre özgü adım (yakın şehir, önemli bilet)

Metin biçimi: `N. Adım: Başlık | Kısa açıklama`.

## 7. Tekrar ve ortaklık

- Aynı defter id'si sayfada bir kez. Otel bağlantısı konaklama bölümünün
  başında (Booking tek oturum çerezi).
- eSIM `info_esim_link` ile üstte zaten veriliyor; `trip_links`'e ve
  "Gitmeden" kartına tekrar konmaz.
- `trip_links` yalnız sitede **var olan** sayfalara gider; URL
  `wp_search_posts` ile doğrulanır, tahmin edilmez.
- Kısa kod içeren alanlar yalnız `wp_acf_update_fields` ile yazılır.

## 8. Gitmeden bilmeniz gerekenler (`card_tips_list`)

Sorrento'daki kart yapısı: `gz-places-wrapper` > `gz-plan-baslik` +
numaralı `gz-place-card`. Planı en çok değiştiren konular: hangi
havalimanı, sezon/tarih, önceden ayırtılacaklar, vize ve sınır, kıyafet.

## 9. Kontrol listesi (yazmadan önce)

- Alkol taraması (`(?<![\p{L}\p{N}_])` ile), em dash, elle ok, emoji, klişe.
- Her rakamın kaynağı ve tarihi var mı.
- Her `trip_links` adresi sitede var mı.
- Defter id'leri tekil mi, kullanılan id defterde var mı.
- Taslakta kalır; yayın sahibinin onayıyla.
