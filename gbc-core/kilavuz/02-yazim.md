# Yazım Kuralları

> Bütün şablonlar için geçerli genel yazım, dil, SEO, fiyat ve marka kuralları; herhangi bir sayfaya metin yazmadan önce okunur.

## Temel ilke

- Amaç: hap bilgi, abartı detay değil; videoyu izlettirmek ve ortaklıktan satış.
- Okur sayfaya gelip hızlıca anlayıp gidip yapabilmeli.
- "Her yazı bir merak olmalı." İşe yaramayan bilgi çıkar; insanlar boş içerikte akıp gider.
- Gereksiz bilgi zarar verir, okur "amma yazmışlar" deyip geçer.
- **İki kapı kuralı:** bir bilginin sayfada kalması için ya ölçülmüş arama hacmi olmalı ya da okurun gerçekten ihtiyaç duyduğu bilgi olmalı. İkisi de yoksa girmez. Şablon alanı doldurmak için yazılmaz; her alanı doldurmak zorunlu değil. Ortaklık bağlantısı bir bloğu ayakta tutma gerekçesi değildir.
- **TR okur kuralı:** Türkiye'deki okur için gereksiz olan hiçbir bilgi konmaz. Genel/uluslararası bilgi ancak ileride İngilizce sayfa yapılırsa oraya girer.
- En çok aranan bilgi sayfanın üstüne konur (giriş, kısa bilgiler, bölüm başı); arama hacmi yüksek soru SSS'e gömülmez.
- Uzun rehberde ilk ekran soruyu cevaplar ("üç cümlede cevap"); rehberin ne anlattığını anlatan paragraf ikinci sıraya gider.
- Bilgi tek yerde durur; aynı bilgi iki bölümde tekrar edilmez, özet ile diğer bölümler birbirine girmez.
- **TEK YER KURALI (30 Eylül 2026):** Aynı kelime ya da aynı bilgi sayfada iki yerde geçirilmez. Tek yerde, en önemli yerde (arama hacmi için en güçlü yer) kullanılır ve biter. Yukarıda geçen bir şey Sıkça Sorulanlar'a tekrar konmaz; SSS için tekrar istisnası YOK. Soru kalıbında aranan kelimenin en güçlü yeri SSS'tir (şemaya girer); o zaman gövdeye aynı bilgi yazılmaz.
- Bilgi bulunamıyorsa ya da emin olunmuyorsa yazılmaz. Önce bakılır, bulunamazsa sayfaya girmez.

## Dil ve biçim

- "Hap hap" yazılır: kısa cümle, tek bilgi, nokta. Bu biçim girişte ve sayfanın tamamında geçerli.
- Merak açan, kontrol ettiren, satın aldırtan dil.
- Klişe kullanılmaz (ör. "Kısacası").
- Emoji kullanılmaz. Rozet (`gz-num-badge`) içindeki emoji silinmez, sıra numarasına çevrilir.
- Uzun tire (em dash) kullanılmaz, kısa tire kullanılır.
- Ok, imleç ve benzeri işaretler metne elle yazılmaz; hepsi CSS'ten ve ok motorundan gelir. Ayrıntı: bkz. Tasarım.
- Türkçe ek kontrolü: sesli harfle biten ada/şehir adlarında şablonun ürettiği başlıkta ek hatası olur ("Adası'ya" yerine "Adası'na"). Başlıklar tek tek okunur, hatalı olan elle ezilir. "Sardunya Adası'dan Sonra Nereye?" doğrudur.

## Şef ve marka ifadesi

- Marka **Gezginbirchef**. Eski marka "Neşeli Şefler"e ait hiçbir şey (görsel, iz, yazı) sitede kalmaz; bulunursa silinip yerine yenisi yapılır.
- Gezi sayfalarında şef ifadesi kullanılmaz. "Şef Gözüyle Notlar" gibi şef ifadeleri yemek ve restoran sayfalarında kalabilir (21 Eylül 2026).
- Yazarı "Chef" diye anan etiketlerin karşılığı: `Chef Önerisi` → `Gezginbirchef Önerisi`, `Chef Notu` → `Gezginbirchef Notu`, `Chef'in Kalacağı Yer` → `Benim Kalacağım Yer`.
- "Yerel Şef" gibi yerel şefin mekânını anlatan kullanım ihlal değildir. `gz-chef-note` CSS sınıfı görünmez, kalabilir.
- "Chef" sayarken marka adı çıkarılır: `h.replace(/Gezginbirchef/gi,'').match(/Chef/g)`.
- Bio satırı aynen: "Şef · Gezgin · İçerik Üretici".
- Birlikte çalışılan markalar (Halil'in beyanı): yemek ve yaşam tarafında Banvit, Migros, Oba Makarna, Feast, Dardanel, Nescafé, English Home, Arçelik; gezi tarafında Prontotour, Tatil Sepeti. Yunanistan'a sponsorlu gezi davetlerinde kurum adı yazılmaz, "Yunanistan gezi davetleri" denir. Yeni sponsor geldikçe eklenir.

## Alkol kuralı (güvenli site)

- Site alkol içermez: "bizim safe bir site olmamız lazım".
- Mekân adları yer olarak geçebilir (Szimpla Kert emsali). Bar ve pub mekân adı olarak geçtiğinde ihlal sayılmaz.
- Yer olarak kalan mekânlar: 360 Cocktail Bar, Horizon Rooftop, Kalokerinos, Brettos Plaka (22 Eylül 2026).
- Chianti bölge adı olarak kalır, içecek olarak çıkar. Mastika reçine anlamında masum, likör anlamında değil. "Aperitivo" kalır (ikram ritüelinin adı). "Tadım" yalnız alkol bağlamında sorun; "peynir tadımı" masum.
- Konusu alkol olan kart silinmez; başlığı boşaltılarak gizlenir (Liste'de `card_title_listed`). Kart gizlenince metindeki sayılar da güncellenir.
- Otomatik cümle silme tek başına yetmez; öznesiz kalıntı kalır. Merkezde alkol olan yerler elle, tekil geçişler otomatik temizlenir.
- "Rakı/içki sofrası" yerine "meze sofrası". Tarif ikame sözlüğü: bkz. Tarif.
- "Meyhane Pilavı" adı açık karar: Halil'in.

## Fiyat ve para

- **Fiyat yazma kuralı (23 Eylül 2026, bütün sistem):** sitenin geliri ortaklıktan gelir; okur fiyatı sayfada tam bulursa tıklamaz.
  - Ortaklıkla satılmayan sabit ücretler (müze, asansör, toplu taşıma, şezlong, restoran hesabı) kaynak ve tarihle tam yazılır.
  - Ortaklıkla satılan her şey (tur, transfer, otel, araç, uçak) yalnız aralık/band olarak yazılır; ürün adının yanına rakam konmaz; bölüm ortaklık yönlendirmesiyle biter.
  - Bu kurala bakılmadan hiçbir sayfa kapanmaz.
- Rakamın kapsamı yüzde yüz net olur: kişi başı mı, günlük mü, otel ve uçak dahil mi hariç mi.
- Her fiyat resmi kaynaktan doğrulanır; emin olunmadan yazılmaz. Üçüncü parti bilet sitesi kaynak sayılmaz. Resmi tarifesi yayımlanmayan kalem varsa öyle olduğu yazılır.
- Tutar yabancı para biriminde yazılır (euro vb.); TL karşılığını Canlı Kur motoru kendisi basar. TL elle yazılmaz; TL karşılığı etiket ve lejantlarda gösterilmez.
- Canlı Kur'un tanıdığı yazımlar: `€ EUR euro`, `$ USD dolar`, `forint HUF`, `£ GBP`, `₾ GEL lari larisi Lari`, `₩ KRW won wonu Won`. "GEL" yalnız büyük harfle yazılır. Elle işaret: `{{gel:40}}`, `{{krw:12000}}`.
- Sayı yazımı: noktadan (ya da virgülden) sonra üç rakam binlik, bir-iki rakam ondalık sayılır (`2.100` = iki bin yüz, `2.10` ve `2,5` ondalık).

## Kaynak ve dış bağlantı

- Resmî kurum bağlantısı (belediye, bakanlık, park, toplu taşıma) VERİLMEZ. Kartlarda dışarıya yönlendirme olmaz; tek istisna okurun satın alma yapabileceği ortaklık bağlantısı (15 Eylül 2026).
- Bağlantı kaldırılır ama kaynak ADI ve son kontrol tarihi metin olarak kalır. Örnek: "Son kontrol: 13 Eylül 2026. Ücret ve saat bilgisinin kaynağı Cagliari Belediyesi."
- Kendi mecraları (YouTube, Instagram) dış bağlantı sayılmaz, kalır.
- Kaynak kutusunda yalnız okuru gerçekten bir bilgiye ya da işleme götüren bağlantılar kalır.

## Başlık, meta ve URL

- H2'ler o konuda en çok aranan kalıpla birebir yazılır ("X ne zaman gidilir" mi "X'in en iyi zamanı" mı, hangisi aranıyorsa). Amaç öne çıkan cevap kutusu. Sabit şablon başlığı dayatılmaz.
- İçindekiler satırları da arama hacmine göre yazılır.
- SEO başlığı ve meta açıklama en yüksek hacimli kalıba göre, hook'lu yazılır.
- Başlık etiketi 30–62 karakter; meta açıklama 120–165 karakter.
- Sayfada tek H1; başlık sırası atlamaz; en az 3 H2; en az 3 iç bağlantı; bütün görsellerde alt metni; eski yıl ifadesi olmaz.
- URL: yalnız küçük harf, rakam ve tire; Türkçe/kodlanmış karakter yok; çift ya da uçta tire yok; `-2/-3` kopya izi yok; adreste yıl yok; en çok 60 karakter ve 7 kelime; canonical kendi adresi; noindex yok.
- Gezi rehberi (yerin ana sayfası) adresi en yüksek hacimli ana kelimedir, "gezi-rehberi" eki almaz (ör. `/atina/`). Adres değişince eski adres 301 ile yönlendirilir (22 Eylül 2026).
- Tüm Bağlantılar sayfasının adresi ASLA değişmez: `/gezginbirchef-tum-baglantilar-sosyal-medya-projeler-ve-iletisim/`.

## SEO araştırması ve kelime yerleştirme

- Her başlık ve bölüme girerken arama hacmi araştırması yapılır.
- En yüksek hacimli kelimeler (Ubersuggest) giriş yazısına yedirilir.
- Hacim ölçülemiyorsa üst terime bakılır; uydurma rakam yazılmaz.
- "ne demek" gibi kalıplar da sorulur; Türkçe hacim çoğu zaman oradadır.
- Matris körü körüne uygulanmaz: her hedefte her format açılmaz, hacmi olan formatlar açılır.
- Yeni bir şey oluşturulunca (kategori, sayfa) SEO eksiksiz girilir; en yüksek hacimli kalıplar araştırılıp başlık, açıklama ve içerik ona göre doldurulur.
- Kelime alakası arama niyetine ve Google ilk sayfasına bakılarak verilir (ör. "sorrento pizza" Konya'daki pizzacı çıkıyor, alakasız).
- Geçmeyen alakalı kelime için yerleşim eşikleri:
  - aylık ≥500 ve ayrı konu (konaklama/yemek/ulaşım/gezi) → ayrı sayfa
  - ≥100 → yeni H2
  - soru kalıbı → SSS
  - ≥20 → H3 ya da paragraf
  - altında → paragrafta geçir
  - geçiyor ama yalnız metinde/SSS'de ve ≥100 → başlığa taşı
- Yakalama ihtimali: başlık/H2/ayrı sayfa yüksek · H3/SSS orta · paragraf düşük.
- Alakalı ama aylık 20'nin altında ve soru olmayan kelime "gereksiz"dir; silinmez, kapalı listede durur.

## Kanibalizasyon

- Aynı konuyu anlatan iki sayfa olmaz; teke indirilir.
- Sahip kuralı: rakip sayfanın ana konusu kelimede geçiyorsa kelime rakibindir; aynı özel konu (otel/ulaşım/yemek) rakibindir; aksi hâlde hub sahibidir, rakipten hedefleme kaldırılır ve hub'a bağlantı verilir.
- Tek kelimelik şehir adı başlıkta geçmek için kanibalizasyon sayılmaz.
- Taslak da sayılır: yayına girmeden başlığı düzeltilir.

## Görsel metinleri

- Görsel yüklenince bütün SEO alanları doldurulur: alt metin, başlık, kısa açıklama, açıklama.
- Dosya adı ve alt metin en çok aranan kelimelere göre yazılır. Alt metin ve başlıkta emoji olmaz (şemaya girer).

## Yorum alanı

- Yorum alanı "yorum bırakın" değil, satışa yönlendiren soru-cevap alanıdır ("Şefe Sor / Bize Sor"). Yalnız gerçek kişi ve anlamlı içerik kabul edilir, her şey onaya düşer, site maksimum korumalı (22 Eylül 2026).
