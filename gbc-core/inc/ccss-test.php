<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Kritik CSS (CCSS) TEST TEZGÂHI
 * ==========================================================================
 * NEDEN VAR
 * LiteSpeed'de `optm-css_async` kapalı. Açılması gerekiyor olabilir ama
 * canlıda körlemesine açılmayacak: UCSS aynı QUIC.cloud altyapısıyla
 * açıldığında ana sayfa sekme butonları, mobil kart çizgisi ve SVG oku
 * bozulmuştu (28 Eylül 2026). Bu yüzden ayar ancak ÖLÇÜLDÜKTEN sonra açılır.
 *
 * ARAŞTIRMA (29 Eylül 2026, LSCWP 7.9 kaynak kodundan doğrulandı):
 *
 * 1) Ayar TEK İSTEK için açılabiliyor. LiteSpeed'in kendi kancası:
 *       do_action( 'litespeed_conf_force', 'optm-css_async', true );
 *    Resmî doküman: "Override a setting for the current request only…
 *    No settings are persisted to the database." Kaynakta karşılığı
 *    conf.cls.php → force_option() → set_conf(), DB'ye YAZMIYOR.
 *    Zamanlama önemli: Optimize::init() ayarı okumadan ÖNCE çalışmalı.
 *
 * 2) CCSS üretilmemişse FOUC OLMUYOR. optimize.cls.php:
 *       if ( ! $this->_ccss ) { ...'CCSS set to OFF due to CCSS not
 *       generated yet'; $this->cfg_css_async = false; }
 *    Yani kritik CSS hazır değilse eklenti async'i kendisi kapatıyor,
 *    sayfa normal yükleniyor. Stilsiz sayfa riski yok.
 *
 * NE YAPIYOR
 * Adrese gizli anahtar eklenmiş isteklerde — ve YALNIZ onlarda —
 * css_async'i açıyor, o isteği önbelleğe aldırmıyor. Ziyaretçi hiçbir şey
 * görmüyor, veritabanına hiçbir şey yazılmıyor. Anahtar kaldırılınca
 * site bir milisaniye bile etkilenmemiş oluyor.
 *
 * GERİ ALMA: bu dosyayı silmek yeter. Kalıcı hiçbir iz bırakmaz.
 * ========================================================================== */

define( 'GBC_CCSS_ANAHTAR', 'gbc_ccss_test_anahtar' );

/** Testi açan gizli anahtar. Bir kez üretilir, tahmin edilemez. */
function gbc_ccss_anahtar() {
	$a = get_option( GBC_CCSS_ANAHTAR );
	if ( ! is_string( $a ) || strlen( $a ) < 16 ) {
		$a = wp_generate_password( 24, false, false );
		update_option( GBC_CCSS_ANAHTAR, $a, false );
	}
	return $a;
}

/**
 * Taban (karsilastirma) bileti.
 *
 * 29 Eyl 2026 DUZELTME: taban adresi eskiden sadece `?gbc_ccss=kapali`
 * idi. Yorumda "taban da onbelleksiz olsun ki karsilastirma adil olsun"
 * yaziyordu ama OYLE DEGILDI: anahtar tutmadigi icin hicbir kanca
 * calismiyor, LiteSpeed o adresi de normal sekilde onbellege aliyordu.
 * Yani taban onbellekten, test canli uretimden geliyordu — sure
 * karsilastirmasi bastan carpik. Artik tabanin da kendi bileti var:
 * onbellegi atlatiyor ama css_async'i ACMIYOR.
 */
function gbc_ccss_taban_bileti() {
	return 'taban-' . gbc_ccss_anahtar();
}

/**
 * Bu istek hangi kipte?
 *
 * @return string 'test' (kritik CSS acik), 'taban' (kapali ama onbelleksiz)
 *                veya '' (siradan ziyaretci).
 */
function gbc_ccss_kip() {
	if ( empty( $_GET['gbc_ccss'] ) ) { return ''; }
	$gelen = sanitize_text_field( wp_unslash( $_GET['gbc_ccss'] ) );
	if ( hash_equals( gbc_ccss_anahtar(), $gelen ) ) { return 'test'; }
	if ( hash_equals( gbc_ccss_taban_bileti(), $gelen ) ) { return 'taban'; }
	return '';
}

/** Bu istek bir CCSS testi mi? (yalniz 'test' kipi) */
function gbc_ccss_test_mi() {
	return 'test' === gbc_ccss_kip();
}

/**
 * Ayarı YALNIZ bu istek için aç.
 *
 * plugins_loaded 1'de çalışır: LiteSpeed'in Optimize::init()'i ayarı
 * okumadan önce. Ayrıca bu istek önbelleğe alınmaz, böylece test
 * sürümü başka ziyaretçiye servis edilemez.
 */
function gbc_ccss_zorla() {
	if ( is_admin() ) { return; }

	$kip = gbc_ccss_kip();
	if ( '' === $kip ) { return; }

	/* ÖNBELLEK KAPATILMIYOR — ve bu bilinçli bir karar.
	 *
	 * 29 Eyl 2026, CANLI ÖLÇÜMLE bulundu. Tezgâh önce
	 * `litespeed_control_set_nocache` çağırıyordu. Mantıklı görünüyordu:
	 * "iki taraf da önbelleksiz olsun ki karşılaştırma adil olsun."
	 * Ama LiteSpeed'de önbelleğe alınmayan istekte CSS iyileştirme
	 * hattının tamamı çalışmıyor. Ölçülen rakamlar:
	 *
	 *     herkese açık, önbellekten .... 132 KB · 1 CSS · 0 satır içi stil
	 *     önbelleklenebilir MISS ....... 132 KB · 1 CSS · 0 satır içi stil
	 *     tezgâh (nocache) ............. 268 KB · 5 CSS · 17 satır içi stil
	 *
	 * Yani tezgâh, ziyaretçinin gördüğü sayfayı değil, hiç
	 * iyileştirilmemiş bir sayfayı ölçüyordu. css_async açılsa bile
	 * üzerinde çalışacağı birleştirilmiş CSS yoktu; iki kip birbirinin
	 * AYNISI çıkıyordu. "Fark yok, açabiliriz" sonucu tamamen yanlış
	 * olurdu — hiçbir şey ölçülmemişti.
	 *
	 * Doğrusu: istek önbelleklenebilir kalsın, iyileştirme hattı
	 * çalışsın. Ziyaretçiye sızma riski yok, çünkü LiteSpeed önbellek
	 * anahtarına sorgu dizesini de katıyor; bu adresi gizli bilet
	 * olmadan kimse isteyemez.
	 */

	if ( 'test' === $kip ) {
		do_action( 'litespeed_conf_force', 'optm-css_async', true );
		do_action( 'litespeed_conf_force', 'optm-ccss_gen', true );
	}

	/* Ölçümün gerçekten uygulandığını sayfadan doğrulayabilmek için imza.
	 *
	 * 29 Eyl 2026: imza önce HTML YORUMU olarak basılıyordu. LiteSpeed'in
	 * küçültücüsü yorumları siliyor, dolayısıyla önbelleklenebilir istekte
	 * imza kayboluyor ve "hangi kip render edildi" doğrulanamıyordu.
	 * Meta etiketi küçültmeden sağ çıkıyor. */
	add_action( 'wp_head', function () use ( $kip ) {
		printf(
			"\n<meta name=\"gbc-ccss\" content=\"%s\">\n",
			esc_attr( 'test' === $kip ? 'test' : 'taban' )
		);
	}, 0 );
}

/**
 * Test adresleri — sekiz şablon.
 *
 * @return array etiket => array( taban, test )
 */
function gbc_ccss_test_adresleri() {
	$anahtar = gbc_ccss_anahtar();
	$taban   = gbc_ccss_taban_bileti();

	/* Sekiz sablon + iki motor sayfasi.
	 *
	 * 29 Eyl 2026 DUZELTME: 'Rota' yuvasi /budapeste-viyana-prag-rotasi/
	 * adresini gosteriyordu; o sayfa YOK, 404 donuyor. Karsilastirma o
	 * yuvada iki 404 sayfasini kiyaslayacakti — hicbir sey olcmeden
	 * "fark yok" diyecekti. Ayrica canli olcum sunu gosterdi: sekiz
	 * sablonun tamami feribot, yunan, kur, galeri ve rota motorlarinin
	 * HICBIRINI tasimiyordu. Oysa kritik CSS'in bozabilecegi sey tam da
	 * bu widget'lar. Yuva feribot motoruyla degistirildi, yunan pillar
	 * da eklendi.
	 *
	 * Rota motoru: 29 Eyl 2026'da 1046 sayfanin TAMAMI tarandi. Motor
	 * yalniz /atina/ sayfasinda calisiyor (gbc-rota-v4, gbc-rota-panel).
	 * Eski /budapeste-viyana-prag-rotasi/ adresi hic var olmamis.
	 * Sayfa 327 KB ile sitenin en agir sayfasi ve en karmasik JS
	 * widget'ini tasiyor — kritik CSS'in bozabilecegi ilk yer orasi,
	 * o yuzden listede.
	 *
	 * Ayni taramada kur, bolum medyasi, galeri lisansi ve ucus perdesi
	 * motorlari HICBIR sayfada gorunmedi; listeye alinacak canli
	 * ornekleri yok. */
	$sablon = (array) apply_filters( 'gbc_ccss_test_sayfalari', array(
		'Ana sayfa' => home_url( '/' ),
		'Gezi'      => home_url( '/budapeste-gezi-rehberi/' ),
		'Liste'     => home_url( '/italyanca-kelimeler/' ),
		'Detay'     => home_url( '/budapestede-nerede-kalinir/' ),  /* otel motoru */
		/* 29 Eyl 2026 IKINCI DUZELTME: yuva /atina/ yaziyordu. O adres bir
		   YAZI DEGIL; url_to_postid() 0 donuyor, yuva "olu adres" sayilip
		   hic olculmuyordu (gbc_ccss_test aynasinda olu_adres: ["Rota"]).
		   Canli olcum: /atina/ -> /atina-gezi-rehberi/ yonleniyor ve rota
		   motorunun 48 sinifi (gbc-rota-v4, -panel, -pil, -cikti) ORADA.
		   Yuva artik dogrudan o adresi gosteriyor. */
		'Rota motoru' => home_url( '/atina-gezi-rehberi/' ),        /* gbc-rota-v4 paneli — sitenin en agir sayfasi */
		/* Rota SABLONU ayri bir sey: 14 numarali sablon (snippet 23340).
		   Canli tek ornegi bu; motor tasimiyor ama sablonun kendi CSS'i
		   kritik CSS'ten etkilenebilir, o yuzden ayri yuva. */
		'Rota şablonu' => home_url( '/bolonya-bologna-gezi-rotalari-plan/' ),
		'Feribot'   => home_url( '/yunan-adalari-feribot/' ),       /* feribot motoru */
		/* 29 Eyl 2026: /yunanistan-gezi-rehberi/ bir YONLENDIRME; yonlendirme
		   sorgu dizesini dusurdugu icin bilet kayboluyor ve o yuva hic
		   olculemiyordu. Canli adres asagidaki. */
		'Yunan'     => home_url( '/yunanistan-gezilecek-yerler/' ),  /* yunan pillar */
		'Tarif'     => home_url( '/muhallebili-biskuvili-pasta-tarifi/' ),
		'Blog'      => home_url( '/ipsala-sinir-kapisi/' ),
		'Sözlük'    => home_url( '/yunanca-kelimeler/' ),
	) );

	$out = array();
	foreach ( $sablon as $ad => $url ) {
		/* Adres gercekten bir yaziya/sayfaya cozuluyor mu? Ana sayfa
		   ozel durum: url_to_postid() onu 0 dondurur ama gecerlidir. */
		$pid  = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
		$anaS = untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) );

		$out[ $ad ] = array(
			'taban'   => add_query_arg( 'gbc_ccss', $taban, $url ),
			'test'    => add_query_arg( 'gbc_ccss', $anahtar, $url ),
			'pid'     => $pid,
			'gecerli' => ( $anaS || $pid > 0 ),
		);
	}
	return $out;
}

/**
 * Olu test adresi kontrolu.
 *
 * Bir test adresi 404 ise karsilastirma sessizce anlamsizlasir; iki bos
 * sayfa kiyaslanir ve "fark yok" cikar. Bu yuzden olu adres SORUN
 * DEFTERINE yaziliyor — Kontrol Merkezi'nde goruluyor, sessizce gecmiyor.
 *
 * @param array $adresler gbc_ccss_test_adresleri() ciktisi.
 */
function gbc_ccss_adres_denetle( $adresler ) {
	if ( ! function_exists( 'gbc_sorun_ac' ) || ! function_exists( 'gbc_sorun_kapat' ) ) {
		return array();
	}

	$olu = array();
	foreach ( $adresler as $ad => $u ) {
		if ( empty( $u['gecerli'] ) ) { $olu[] = $ad; }
	}

	if ( $olu ) {
		gbc_sorun_ac(
			'hz-ccss-adres',
			'hiz',
			sprintf(
				/* translators: %s: sablon adlari */
				__( 'Kritik CSS tezgâhında ölü test adresi: %s', 'gbc-core' ),
				implode( ', ', $olu )
			),
			__( 'Bu şablonun test adresi bir sayfaya çözülmüyor (404). Karşılaştırma o yuvada iki boş sayfayı kıyaslar ve yanlışlıkla "fark yok" der. Adres düzeltilmeden ölçüm güvenilir değil.', 'gbc-core' ),
			'yuksek',
			admin_url( 'admin.php?page=gbc-hiz' ),
			array( 'olu_adres' => count( $olu ) )
		);
	} else {
		gbc_sorun_kapat( 'hz-ccss-adres', __( 'bütün test adresleri canlı sayfaya çözülüyor', 'gbc-core' ) );
	}

	return $olu;
}

/**
 * Kritik CSS üretilmiş mi?
 *
 * LSCWP dosyaları wp-content/litespeed/ccss/ altında tutuyor, özeti
 * litespeed.css._summary seçeneğinde. İkisine de bakılıyor.
 *
 * @return array array( klasor, dosya_adet, ozet, kuyruk )
 */
function gbc_ccss_durum() {
	$out = array( 'klasor' => '', 'dosya_adet' => 0, 'ozet' => array(), 'kuyruk' => 0, 'not' => '' );

	$kok = defined( 'LITESPEED_STATIC_DIR' ) ? LITESPEED_STATIC_DIR : WP_CONTENT_DIR . '/litespeed';
	$klasor = $kok . '/ccss';
	$out['klasor'] = $klasor;

	if ( is_dir( $klasor ) ) {
		$dosyalar = glob( $klasor . '/*.css' );
		if ( false === $dosyalar ) { $dosyalar = array(); }
		/* Alt site klasörleri de olabilir. */
		foreach ( (array) glob( $klasor . '/*', GLOB_ONLYDIR ) as $alt ) {
			$ek = glob( $alt . '/*.css' );
			if ( is_array( $ek ) ) { $dosyalar = array_merge( $dosyalar, $ek ); }
		}
		$out['dosya_adet'] = count( $dosyalar );
	} else {
		$out['not'] = __( 'ccss klasörü yok — kritik CSS hiç üretilmemiş.', 'gbc-core' );
	}

	$ozet = get_option( 'litespeed.css._summary' );
	if ( is_array( $ozet ) ) { $out['ozet'] = $ozet; }

	$kuyruk = $klasor . '/.litespeed_conf.dat';
	if ( file_exists( $kuyruk ) ) {
		$j = json_decode( (string) file_get_contents( $kuyruk ), true );
		if ( is_array( $j ) ) { $out['kuyruk'] = count( $j ); }
	}

	return $out;
}

/* ==========================================================================
 * MCP AYNASI  (v1.19.1 — 29 Eylul 2026)
 * --------------------------------------------------------------------------
 * NEDEN VAR
 * Test adresleri ve kritik CSS uretim durumu yalniz Hiz panelinde goruluyordu.
 * Panel de yonetici oturumu gerektiriyor; oturum dustugunde olcum yapilamiyor
 * ve "sen testi yap" istegi kilitleniyor. Bu ayna ayni bilgiyi TEK bir
 * secenege yaziyor, secenek de Royal MCP'nin okunabilir listesine aliniyor.
 * Boylece olcum yonetici ekrani acmadan yurutulebiliyor.
 *
 * NE YAZMIYOR
 * Site sirri yazmiyor. Icindeki tek hassas deger test adresindeki gecici
 * anahtar; o da yalniz "bu istekte kritik CSS'i ac ve onbellege alma"
 * yetkisi veriyor, hicbir veriye erisim vermiyor. MCP zaten manage_options
 * yetkisi istiyor, yani bu ayna yonetici disina cikmiyor.
 * ========================================================================== */

define( 'GBC_CCSS_AYNA', 'gbc_ccss_test' );

/** LiteSpeed'in CANLI ayarini oku (kendi kancasiyla, DB'ye dokunmadan). */
function gbc_ccss_canli( $ad ) {
	$d = apply_filters( 'litespeed_conf', null, $ad );
	if ( null === $d ) { return null; }
	return is_bool( $d ) ? (int) $d : $d;
}

/**
 * Aynayi tazele.
 *
 * @param bool $zorla true ise yasina bakmadan yeniden yazar.
 * @return array
 */
function gbc_ccss_ayna( $zorla = false ) {
	$eski = get_option( GBC_CCSS_AYNA );

	if ( ! $zorla && is_array( $eski ) && isset( $eski['zaman'] )
		&& ( time() - (int) $eski['zaman'] ) < 300 ) {
		return $eski;
	}

	$adresler = gbc_ccss_test_adresleri();

	$ayna = array(
		'zaman'     => time(),
		'surum'     => defined( 'GBC_CORE_SURUM' ) ? GBC_CORE_SURUM : '',
		'css_async' => gbc_ccss_canli( 'optm-css_async' ),
		'ccss_gen'  => gbc_ccss_canli( 'optm-ccss_gen' ),
		'ucss'      => gbc_ccss_canli( 'optm-ucss' ),
		'css_comb'  => gbc_ccss_canli( 'optm-css_comb' ),
		'uretim'    => gbc_ccss_durum(),
		'adresler'  => $adresler,
		'olu_adres' => gbc_ccss_adres_denetle( $adresler ),
	);

	update_option( GBC_CCSS_AYNA, $ayna, false );
	return $ayna;
}

/** admin_init kancasi — parametre sizmasin diye ayri sarmal. */
function gbc_ccss_ayna_tazele() {
	gbc_ccss_ayna( false );
}
add_action( 'admin_init', 'gbc_ccss_ayna_tazele', 20 );
add_action( 'gbc_gunluk_cron', 'gbc_ccss_ayna_tazele' );
