# Çalışma Kuralları

> Halil ile nasıl çalışılır, iş nasıl teslim edilir; her oturumun başında ilk okunacak dosya.

## Anlatım

- Sade ve doğrudan yaz. Katmanlı, uzun açıklama yok; net ve basit cevap.
- Her turda iş ikiye ayrılır: **"ben yaptım / sen yapacaksın / sonra ben yapacağım"**. Kimin neyi yapacağı net olur, sonra sırayla ilerlenir.
- Halil'in yapacağı iş adım adım verilir: "aç dersin açarım, bas dersin basarım".
- Çok maddeli istekte sonunda madde madde tamamlanma listesi verilir ("bunu yaptım, bunu yaptım"), hiçbir madde atlanmaz (29 Eylül 2026).
- Rapor her zaman o anki canlı veriden verilir; hafızadaki ya da eski bilgi aktarılmaz.
- Emin olunmayan şey söylenmez. Bir işaretçinin, bağlantının ya da alanın basılıp basılmadığı kodu okuyarak değil, canlı sayfada ölçülerek söylenir.

## Kod ve değişiklik

- Halil koda girmez. Değişiklik doğrudan uygulanır, sonra ne yapıldığı anlatılır. Yapıştırması için kod verilmez.
- Kod işi Claude'da; GBC Core eklenti kodu Code sohbetinde yazılır.
- Her değişiklik test edilerek yapılır. "Hiçbir şekilde negatif sonucu olmayacak"; bozmadığı ölçülmeden bitti denmez (28 Eylül 2026).
- Değişiklikler **tek tek** yayına alınır: bir düzeltme yüklenir, bozulma var mı kontrol edilir, temizse sonrakine geçilir. Toplu paket yok (30 Eylül 2026).
- Çalışan etkileşimli bir öğeye (ışık kutusu, sekme, menü, video perdesi) dokunulduysa tıklama testi yapılmadan bitti denmez.
- Tek ölçüme güvenilmez; düşük çıkan ölçüm ikinci kez alınır.
- "Düzelttim" demek yetmez; sorunu ölçüm kapatır.

## Panel ve araçlar

- Kurulan panel ve araçlarda Halil'in işi en aza iner: "Ben hiçbir şey basmıyorum, sen her şeyi yapıyorsun" (30 Eylül 2026).
- Çok sayıda düğme istemiyor. Tek sayfadan durumu görmek, gerekiyorsa tek düğmeye basmak istiyor.
- "Ona git buna git uğraşmayayım, tek ekrana bakayım": menü 5 grupta tutulur (Kontrol Paneli · SEO · İş Ortaklığı · Bağlantılar · Çalışma Dosyası).

## Onay gerektiren işler

- Yayına alma, silme ve yeni sayfa açma Halil'in onayına tabidir.
- WPCode snippet'ini silmek ya da pasife almak Halil'in işidir. Pasife alınacaklar `ZZPASIF · ` ön ekiyle işaretlenir (aramada çıksın, listede dibe insin). Pasife alma yalnız açık istek varsa yapılır.
- Eklentide eski ekran, tablo, seçenek ya da fonksiyon silinmez; menüden kaldırılır, arşive alınır, geri açılabilir kalır.
- Snippet düzenleme ya da Gutenberg "Kaydet" otomatik onay sınıflandırıcısına takılırsa ("Modify Shared Resources") iş Halil'den istenir.
- Canlı ayar değişikliği (ör. LiteSpeed Kritik CSS) Halil'in onayıyla, düşük trafikli saatte yapılır.
- Asian_World_WordPress sitesine dokunulmaz.

## Ortaklık bağlantısına dokunma kuralı

- Ortaklık ağ adresleri (tp.media, tpx.li, pxf.io, CJ alan adları) ve kısa linkler **asla açılmaz, tıklanmaz, sunucudan çağrılmaz** — sahte tıklama olur. Yalnız linkin gittiği asıl sayfa (`u=` hedefi) sınanır. Hedefi yazmayan kısa link hiç açılmaz.
- Tıklama testi gerekiyorsa gizli pencerede, çıkış yapmış hâlde yapılır; yönetici tıklamaları sayaca bilerek yazılmaz.
- Ortaklık bağlantılarından her zaman yüzde yüz emin olunur. Ayrıntı: bkz. Ortaklık.

## İçerikte iş bölümü

- Deneyim cümlelerini Halil yazar. Asistan araştırma, ölçüm, düzeltme ve biçim yapar.
- Halil fikir söyler; doğru mu yanlış mı kararını arama hacmine bakarak asistan verir.
- Sayfalar tek tek, sırayla işlenir. Her sayfa için Halil video transkripti (.sbv) ve videodan ekran görüntüleri verir.
- Altyazısı olmayan videoda bölüm (zaman damgası) kartı Halil'den istenir.
- Linksiz sayfalara önce asistan ilk tur ortaklık bağlantısı koyar ("boş kalmasın, kazanacaksak kazanalım"), sonra Halil tek tek iki tur düzeltir.
- Bizzat gidilen bir yer için hacim düşük olsa da sayfa açılabilir; bu kararı asistan verebilir.
- Sitede Halil'in arkadaşı da aynı kodlarda paralel çalışıyor.

## İş akışı (GBC Core)

### Önce defter, sonra kod
- Bir şey değiştiyse önce DEFTER (Çalışma Dosyası) güncellenir, sonra kod yazılır.
- DEFTER eklentinin içinde taşınır ve `gbc_defter` seçeneğine yazılır; başka bir sohbet MCP ile bu seçeneği okuyarak bütün kararları tek çağrıda öğrenir.
- DEFTER'e sürüm başına: ne yapıldı, neden, nasıl ölçüldü, hangi test, neyin yapılmadığı ve sebebi yazılır.

### Sürüm ve dosya adı
- Teslim edilen her dosyanın adında sürüm numarası ve tarih olur: `gbc-core-v1.8.2-28eylul2026.zip`. Amaç nerede kalındığını ve hangi sürümün yüklendiğini görmek (30 Eylül 2026).
- `GBC_KURAL_SURUM` sabiti YALNIZ bir tespit kuralı gerçekten değiştiğinde elle artırılır; yeni özellik, hata düzeltmesi veya paketleme için asla. Aksi hâlde açık sorunların tamamı süpürülür.

### Paketleme ve yükleme
- İki sohbet aynı eklentiye dokunuyorsa zip üretmeden ÖNCE canlıdaki sürüm kontrol edilir; yoksa son yükleyen diğerinin işini siler.
- Site WordPress çoklu site (network); eklentiler ağda etkinleştirilir. Kurulum ve güncelleme Network Admin üzerinden yapılır.
- Yüklemeden önce test takımı çalıştırılır; bütün kontroller geçmeden paket verilmez. Yeni özellik kendi testiyle gelir.
- Yükledikten sonra canlıda ölçülür: ziyaretçi sayfası, ilgili ekran, JS hatası, mobil (390 px) taşma.
- CSS ya da defter değişikliğinden sonra LiteSpeed → Purge All yapılır.

### Ölçüm ve kanıt
- Her ölçüm ziyaretçi gözüyle yapılır (çerezsiz, oturumsuz). Ölçüm yöntemleri ve tuzakları: bkz. Teknik.
- "0 açık sorun" SORUN YOK demek değil, veri yoksa BİLGİ YOK demektir.
