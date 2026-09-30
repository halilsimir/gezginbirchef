<?php
/**
 * GBC Core · Dizin Durumu (Google Search Console) — v1.48.1, 30 Eylül 2026
 * ------------------------------------------------------------
 * NE YAPAR
 *   Sitedeki her adresin Google'da ne durumda olduğunu URL Inspection
 *   API'sinden sorar, tabloya yazar ve "neden dizinde değil, ne yapmak
 *   lazım" diye sınıflar. Ayrıca Search Console'un bildiği ama sitede
 *   artık karşılığı olmayan (ölü) adresleri bulur.
 *
 * NE YAPMAZ — ve NEDEN (bu kısım önemli, gizlemiyoruz)
 *   Google'ın "indekslemeyi iste" düğmesinin API'si YOK. Indexing API
 *   (indexing.googleapis.com) Google'ın kendi belgesinde açıkça yalnız
 *   JobPosting ve VideoObject içine gömülü BroadcastEvent sayfaları için
 *   çalışır: "The Indexing API can only be used to crawl pages with
 *   either JobPosting or BroadcastEvent embedded in a VideoObject."
 *   Tarif/gezi sayfası göndermek işe yaramaz. Bu yüzden bu modül sahte
 *   bir "otomatik gönderim" YAPMAZ. Bunun yerine günlük 10'luk kuyruğu
 *   önceliğe göre hazırlar (v1.49.0) ve gönderimi Search Console
 *   ekranından tarayıcıyla yapan zamanlanmış görev kullanır.
 *
 * KOTA
 *   URL Inspection: site başına günde 2000, dakikada 600 sorgu.
 *   Burada günlük tavan bilerek 1800'de tutuluyor; başka modüller de
 *   (inc/google-sayfa.php) aynı havuzdan içiyor.
 *
 * ÖN YÜZE ETKİSİ SIFIR: dosya yalnız yönetici tarafında ve cron'da
 * yükleniyor (gbc-core.php). Ziyaretçi isteğinde tek fonksiyon bile
 * tanımlanmıyor.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GBC_DZ_SEMA',  '1' );
define( 'GBC_DZ_TAVAN', 1800 );  /* günlük sorgu tavanı (Google'ınki 2000) */
define( 'GBC_DZ_TUR',   40 );    /* bir turun ADET tavanı — asıl sınır süre (aşağı bak) */
define( 'GBC_DZ_ELLE',  10 );    /* düğmeye basınca taranan adres */

/* SÜRE SINIRI — v1.48.1'in varlık sebebi.
   v1.48.0'da tur sınırı 120 saniyeydi ve "25 adres sorgula" düğmesi canlıda
   503 verdi: Hostinger/LiteSpeed isteği ~60 saniyede kesiyor, bizim koruma
   hiç devreye giremiyordu. Artık tur 18 saniyede kendini bitiriyor — hangi
   sunucu limiti olursa olsun altında kalır. Kalanlar zincirle devam eder. */
define( 'GBC_DZ_SURE',    18 );
define( 'GBC_DZ_ZINCIR',  120 );  /* bir gecede en fazla kaç tur zincirlenir */

/* ============================================================
   1) TABLO
   ============================================================ */

function gbc_dz_tablo() {
	global $wpdb;
	return $wpdb->prefix . 'gbc_dizin';
}

function gbc_dz_tablo_kur() {
	if ( get_option( 'gbc_dz_sema' ) === GBC_DZ_SEMA ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$t = gbc_dz_tablo();

	dbDelta( "CREATE TABLE {$t} (
		yol varchar(190) NOT NULL,
		pid bigint unsigned NOT NULL DEFAULT 0,
		sinif varchar(24) NOT NULL DEFAULT '',
		verdict varchar(24) NOT NULL DEFAULT '',
		kapsam varchar(190) NOT NULL DEFAULT '',
		robots varchar(40) NOT NULL DEFAULT '',
		indeksleme varchar(40) NOT NULL DEFAULT '',
		getirme varchar(40) NOT NULL DEFAULT '',
		g_kanonik varchar(190) NOT NULL DEFAULT '',
		k_kanonik varchar(190) NOT NULL DEFAULT '',
		son_tarama datetime DEFAULT NULL,
		bakilan datetime DEFAULT NULL,
		hata varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (yol),
		KEY sinif (sinif),
		KEY bakilan (bakilan)
	) $c;" );

	update_option( 'gbc_dz_sema', GBC_DZ_SEMA, false );
}
add_action( 'admin_init', 'gbc_dz_tablo_kur' );

/* ============================================================
   2) GÜNLÜK SORGU SAYACI
   Tavana değince tarama kendini durdurur, ertesi gün kaldığı
   yerden devam eder. Kotaya hiç çarpmıyoruz.
   ============================================================ */

function gbc_dz_sayac( $arttir = 0 ) {
	$bugun = gmdate( 'Y-m-d' );
	$s = get_option( 'gbc_dz_sayac', array() );
	if ( ! is_array( $s ) || ! isset( $s['gun'] ) || $s['gun'] !== $bugun ) {
		$s = array( 'gun' => $bugun, 'adet' => 0 );
	}
	if ( $arttir > 0 ) {
		$s['adet'] += (int) $arttir;
		update_option( 'gbc_dz_sayac', $s, false );
	}
	return (int) $s['adet'];
}

function gbc_dz_kalan_kota() {
	return max( 0, GBC_DZ_TAVAN - gbc_dz_sayac() );
}

/* ============================================================
   3) SİTEDEKİ ADRESLER
   Yayında olan her genel gönderi türü + sayı > 0 olan genel
   taksonomi terimleri + ana sayfa.
   ============================================================ */

function gbc_dz_yol( $url ) {
	$p = wp_parse_url( (string) $url );
	$y = isset( $p['path'] ) ? $p['path'] : '/';
	$y = '/' . ltrim( $y, '/' );
	if ( '/' !== $y ) { $y = rtrim( $y, '/' ) . '/'; }
	return substr( $y, 0, 190 );
}

function gbc_dz_urlleri() {
	static $bellek = null;
	if ( null !== $bellek ) { return $bellek; }

	$out = array();

	/* Ana sayfa */
	$out[ gbc_dz_yol( home_url( '/' ) ) ] = array(
		'url' => home_url( '/' ), 'pid' => 0, 'tur' => 'ana', 'baslik' => get_bloginfo( 'name' ),
	);

	$turler = get_post_types( array( 'public' => true ), 'names' );
	unset( $turler['attachment'] );
	if ( $turler ) {
		$ids = get_posts( array(
			'post_type'        => array_values( $turler ),
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		) );
		foreach ( $ids as $pid ) {
			$u = get_permalink( $pid );
			if ( ! $u ) { continue; }
			$out[ gbc_dz_yol( $u ) ] = array(
				'url' => $u, 'pid' => (int) $pid, 'tur' => get_post_type( $pid ),
				'baslik' => get_the_title( $pid ),
			);
		}
	}

	$taks = get_taxonomies( array( 'public' => true ), 'names' );
	foreach ( $taks as $tx ) {
		$terimler = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => true ) );
		if ( is_wp_error( $terimler ) ) { continue; }
		foreach ( $terimler as $t ) {
			$u = get_term_link( $t );
			if ( is_wp_error( $u ) || ! $u ) { continue; }
			$out[ gbc_dz_yol( $u ) ] = array(
				'url' => $u, 'pid' => 0, 'tur' => 'terim:' . $tx, 'baslik' => $t->name,
			);
		}
	}

	$bellek = $out;
	return $out;
}

/* ============================================================
   4) SINIFLANDIRMA
   coverageState İngilizce serbest metin; ona GÜVENMİYORUZ.
   Karar yapısal alanlardan veriliyor (verdict, robotsTxtState,
   indexingState, pageFetchState, kanonik). coverageState yalnız
   ekranda gösterilmek için saklanıyor.
   ============================================================ */

function gbc_dz_siniflar() {
	return array(
		'dizinde'   => array( 'ad' => 'Google dizininde',            'onem' => 0, 'cozum' => '' ),
		'yok404'    => array( 'ad' => 'Sayfa yok (404)',             'onem' => 3, 'cozum' => 'Adres silinmiş ya da değişmiş. Ya sayfayı geri getir ya da yeni adrese kalıcı yönlendirme (301) kur.' ),
		'soft404'   => array( 'ad' => 'Yumuşak 404 (boş görünüyor)', 'onem' => 3, 'cozum' => 'Sayfa 200 dönüyor ama Google içerik göremiyor. İçerik gerçekten ince ise doldur; değilse şablonun boş render ettiği durumu düzelt.' ),
		'sunucu'    => array( 'ad' => 'Sunucu hatası (5xx)',         'onem' => 3, 'cozum' => 'Google sayfayı alamadı. Hata kaydına bak; sürekliyse barındırma tarafında sorun var.' ),
		'yonlendirme' => array( 'ad' => 'Yönlendirme hatası',        'onem' => 3, 'cozum' => 'Yönlendirme zinciri kırık ya da döngüde. Tek adımda hedefe gidecek şekilde düzelt.' ),
		'erisim'    => array( 'ad' => 'Erişim engellendi (401/403)', 'onem' => 3, 'cozum' => 'Güvenlik katmanı Googlebot\'u engelliyor olabilir. Güvenlik duvarı ve .htaccess kurallarını kontrol et.' ),
		'robots'    => array( 'ad' => 'robots.txt engelliyor',       'onem' => 2, 'cozum' => 'Bilerek engellendiyse sorun yok. Değilse robots.txt satırını kaldır.' ),
		'noindex'   => array( 'ad' => 'noindex etiketi var',         'onem' => 1, 'cozum' => 'Bilerek noindex verildiyse doğru. Değilse Rank Math\'te o sayfanın/türün indeksleme ayarını aç.' ),
		'kanonik'   => array( 'ad' => 'Kanonik başka sayfayı gösteriyor', 'onem' => 2, 'cozum' => 'Google bu sayfayı bir başkasının kopyası sayıyor. İçerik gerçekten farklıysa kanonik etiketi düzelt; aynıysa sorun değil.' ),
		'kesfedilmis' => array( 'ad' => 'Keşfedildi, henüz taranmadı', 'onem' => 2, 'cozum' => 'Google adresi biliyor ama sıraya koymuş. İç bağlantı ver, site haritasında olduğundan emin ol, Search Console\'dan indeksleme iste.' ),
		'taranmis'  => array( 'ad' => 'Tarandı, dizine alınmadı',    'onem' => 2, 'cozum' => 'Google gördü ama değersiz buldu. İçeriği derinleştir, özgün bilgi ekle, güçlü iç bağlantı ver, sonra indeksleme iste.' ),
		'bilinmiyor' => array( 'ad' => 'Google bu adresi bilmiyor',  'onem' => 2, 'cozum' => 'Site haritasında yok ya da hiç bağlantı almamış. İç bağlantı ver ve indeksleme iste.' ),
		'hata'      => array( 'ad' => 'Sorgulanamadı',               'onem' => 1, 'cozum' => 'API sorgusu başarısız oldu. Bağlantılar ekranından Search Console bağlantısını kontrol et.' ),
		'bakilmadi' => array( 'ad' => 'Henüz bakılmadı',             'onem' => 0, 'cozum' => '' ),
	);
}

function gbc_dz_sinif_ad( $s ) {
	$h = gbc_dz_siniflar();
	return isset( $h[ $s ] ) ? $h[ $s ]['ad'] : $s;
}

function gbc_dz_sinif_onem( $s ) {
	$h = gbc_dz_siniflar();
	return isset( $h[ $s ] ) ? (int) $h[ $s ]['onem'] : 0;
}

function gbc_dz_sinif_cozum( $s ) {
	$h = gbc_dz_siniflar();
	return isset( $h[ $s ] ) ? $h[ $s ]['cozum'] : '';
}

/** İki adresi karşılaştırırken protokol, www ve son eğik çizgi farkı sayılmaz. */
function gbc_dz_ayni_adres( $a, $b ) {
	$sade = static function ( $u ) {
		$u = strtolower( (string) $u );
		$u = preg_replace( '#^https?://#', '', $u );
		$u = preg_replace( '#^www\.#', '', $u );
		return rtrim( $u, '/' );
	};
	return $sade( $a ) === $sade( $b );
}

function gbc_dz_sinifla( $r, $url ) {
	$g  = static function ( $k ) use ( $r ) { return isset( $r[ $k ] ) ? (string) $r[ $k ] : ''; };
	$fs = $g( 'pageFetchState' );

	if ( 'NOT_FOUND' === $fs )      { return 'yok404'; }
	if ( 'SOFT_404' === $fs )       { return 'soft404'; }
	if ( 'SERVER_ERROR' === $fs )   { return 'sunucu'; }
	if ( 'REDIRECT_ERROR' === $fs ) { return 'yonlendirme'; }
	if ( in_array( $fs, array( 'ACCESS_DENIED', 'ACCESS_FORBIDDEN', 'BLOCKED_4XX' ), true ) ) { return 'erisim'; }
	if ( 'DISALLOWED' === $g( 'robotsTxtState' ) || 'BLOCKED_ROBOTS_TXT' === $fs ) { return 'robots'; }
	if ( in_array( $g( 'indexingState' ), array( 'BLOCKED_BY_META_TAG', 'BLOCKED_BY_HTTP_HEADER' ), true ) ) { return 'noindex'; }
	if ( 'PASS' === $g( 'verdict' ) ) { return 'dizinde'; }

	$gk = $g( 'googleCanonical' );
	if ( '' !== $gk && ! gbc_dz_ayni_adres( $gk, $url ) ) { return 'kanonik'; }

	if ( '' === $g( 'lastCrawlTime' ) ) {
		/* Hiç taranmamış: Google biliyor ama sıraya koymuş ya da hiç duymamış. */
		return ( '' === $g( 'coverageState' ) ) ? 'bilinmiyor' : 'kesfedilmis';
	}
	return 'taranmis';
}

/* ============================================================
   5) TEK ADRES SORGUSU
   gbc_km_index() 12 saat önbellek tutuyor; toplu taramada
   önbelleği atlayıp doğrudan API'ye gidiyoruz.
   ============================================================ */

function gbc_dz_hazir() {
	return function_exists( 'gbc_km_api' ) && function_exists( 'gbc_km_ayar' )
		&& '' !== (string) gbc_km_ayar( 'gsc_site', '' )
		&& '' !== (string) gbc_km_ayar( 'google_sa', '' );
}

function gbc_dz_sor( $url ) {
	if ( ! gbc_dz_hazir() ) {
		return new WP_Error( 'yok', 'Search Console bağlantısı kurulu değil (Bağlantılar ekranı).' );
	}
	$g = gbc_km_api( 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', array(
		'inspectionUrl' => (string) $url,
		'siteUrl'       => gbc_km_ayar( 'gsc_site', '' ),
		'languageCode'  => 'en',   /* coverageState metni sabit kalsın diye */
	) );
	if ( is_wp_error( $g ) ) { return $g; }
	$s = isset( $g['inspectionResult']['indexStatusResult'] ) ? $g['inspectionResult']['indexStatusResult'] : array();
	return is_array( $s ) ? $s : array();
}

/* ============================================================
   6) TOPLU TARAMA
   Sıra: hiç bakılmamışlar önce, sonra en eski bakılanlar.
   ============================================================ */

function gbc_dz_sirada( $adet ) {
	global $wpdb;
	$t   = gbc_dz_tablo();
	$hep = gbc_dz_urlleri();

	$bakilan = $wpdb->get_results( "SELECT yol, bakilan FROM {$t}", OBJECT_K );
	if ( ! is_array( $bakilan ) ) { $bakilan = array(); }

	$yeni = array();
	$eski = array();
	foreach ( $hep as $yol => $x ) {
		if ( ! isset( $bakilan[ $yol ] ) || empty( $bakilan[ $yol ]->bakilan ) ) {
			$yeni[ $yol ] = $x;
		} else {
			$x['bakilan'] = $bakilan[ $yol ]->bakilan;
			$eski[ $yol ] = $x;
		}
	}
	uasort( $eski, static function ( $a, $b ) { return strcmp( $a['bakilan'], $b['bakilan'] ); } );

	$sira = $yeni + $eski;
	return array_slice( $sira, 0, max( 0, (int) $adet ), true );
}

function gbc_dz_yaz( $yol, $x, $r, $hata = '' ) {
	global $wpdb;
	$g = static function ( $k ) use ( $r ) { return isset( $r[ $k ] ) ? substr( (string) $r[ $k ], 0, 190 ) : ''; };

	$sinif = ( '' !== $hata ) ? 'hata' : gbc_dz_sinifla( $r, $x['url'] );
	$tarama = '';
	if ( ! empty( $r['lastCrawlTime'] ) ) {
		$ts = strtotime( (string) $r['lastCrawlTime'] );
		if ( $ts ) { $tarama = gmdate( 'Y-m-d H:i:s', $ts ); }
	}

	$wpdb->replace( gbc_dz_tablo(), array(
		'yol'        => $yol,
		'pid'        => (int) $x['pid'],
		'sinif'      => $sinif,
		'verdict'    => $g( 'verdict' ),
		'kapsam'     => $g( 'coverageState' ),
		'robots'     => $g( 'robotsTxtState' ),
		'indeksleme' => $g( 'indexingState' ),
		'getirme'    => $g( 'pageFetchState' ),
		'g_kanonik'  => $g( 'googleCanonical' ),
		'k_kanonik'  => $g( 'userCanonical' ),
		'son_tarama' => $tarama ? $tarama : null,
		'bakilan'    => current_time( 'mysql', true ),
		'hata'       => substr( (string) $hata, 0, 255 ),
	) );
	return $sinif;
}

/**
 * $adet kadar adresi sorgular. Kotaya, tavana ve süreye saygı duyar.
 * Dönen: array( 'bakilan'=>n, 'atlanan'=>n, 'sebep'=>'', 'siniflar'=>array )
 */
function gbc_dz_tara( $adet ) {
	$sonuc = array( 'bakilan' => 0, 'atlanan' => 0, 'sebep' => '', 'siniflar' => array() );

	if ( ! gbc_dz_hazir() ) {
		$sonuc['sebep'] = 'Search Console bağlantısı kurulu değil.';
		return $sonuc;
	}
	$kalan = gbc_dz_kalan_kota();
	if ( $kalan < 1 ) {
		$sonuc['sebep'] = 'Bugünkü sorgu hakkı doldu (' . GBC_DZ_TAVAN . '). Yarın kaldığı yerden devam eder.';
		return $sonuc;
	}
	$adet = min( (int) $adet, $kalan );
	$sira = gbc_dz_sirada( $adet );
	if ( ! $sira ) {
		$sonuc['sebep'] = 'Sırada adres yok.';
		return $sonuc;
	}

	$basla   = microtime( true );
	$hata_us = 0;

	foreach ( $sira as $yol => $x ) {
		/* Sunucu isteği kesmeden ÖNCE biz bitiriyoruz. */
		if ( microtime( true ) - $basla > GBC_DZ_SURE ) { $sonuc['sebep'] = 'Bu turun süresi doldu; kalanlar sıradaki turda.'; break; }

		$r = gbc_dz_sor( $x['url'] );
		gbc_dz_sayac( 1 );

		if ( is_wp_error( $r ) ) {
			$hata_us++;
			$sinif = gbc_dz_yaz( $yol, $x, array(), $r->get_error_message() );
			/* Arka arkaya 5 hata: bağlantı ya da kota bozuk, boşuna zorlamayalım. */
			if ( $hata_us >= 5 ) { $sonuc['sebep'] = 'Arka arkaya 5 sorgu başarısız oldu, tarama durduruldu: ' . $r->get_error_message(); break; }
		} else {
			$hata_us = 0;
			$sinif = gbc_dz_yaz( $yol, $x, $r );
		}

		$sonuc['bakilan']++;
		if ( ! isset( $sonuc['siniflar'][ $sinif ] ) ) { $sonuc['siniflar'][ $sinif ] = 0; }
		$sonuc['siniflar'][ $sinif ]++;

		/* v1.48.1: bekleme kaldırıldı. Google'ın sınırı dakikada 600; biz
		   saniyede ~1 istek yapıyoruz, yani zaten çok altındayız. Bekleme
		   yalnız elimizdeki 18 saniyeyi yiyordu. */
	}

	gbc_dz_deftere();
	update_option( 'gbc_dz_son_tur', array( 'zaman' => time(), 'sonuc' => $sonuc ), false );
	return $sonuc;
}

/* ============================================================
   7) ÖZET
   ============================================================ */

/** Henüz hiç bakılmamış adres sayısı — zincirin devam edip etmeyeceğini belirler. */
function gbc_dz_kalan_is() {
	global $wpdb;
	$t = gbc_dz_tablo();
	$bakilan = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE bakilan IS NOT NULL" );
	return max( 0, count( gbc_dz_urlleri() ) - $bakilan );
}

function gbc_dz_ozet() {
	global $wpdb;
	$t   = gbc_dz_tablo();
	$hep = gbc_dz_urlleri();

	$satir = $wpdb->get_results( "SELECT sinif, COUNT(*) adet FROM {$t} GROUP BY sinif" );
	$say = array();
	$bakilan = 0;
	foreach ( (array) $satir as $s ) {
		$say[ $s->sinif ] = (int) $s->adet;
		$bakilan += (int) $s->adet;
	}
	$say['bakilmadi'] = max( 0, count( $hep ) - $bakilan );

	return array(
		'toplam'   => count( $hep ),
		'bakilan'  => $bakilan,
		'siniflar' => $say,
	);
}

/** Bir sınıftaki adresler. */
function gbc_dz_liste( $sinif, $limit = 500 ) {
	global $wpdb;
	$t = gbc_dz_tablo();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE sinif = %s ORDER BY yol ASC LIMIT %d", $sinif, (int) $limit
	) );
}

/* ============================================================
   8) ÖLÜ ADRESLER
   Search Console'un bildiği ama sitede artık karşılığı olmayan
   adresler. Ağ isteği YOK: WordPress'in kendi çözümleyicisiyle
   bakıyoruz, bu hem hızlı hem kesin.
   ============================================================ */

function gbc_dz_cozulur_mu( $yol ) {
	$hep = gbc_dz_urlleri();
	if ( isset( $hep[ $yol ] ) ) { return true; }

	$url = home_url( $yol );
	if ( url_to_postid( $url ) ) { return true; }

	/* Rank Math yönlendirmesi var mı? */
	global $wpdb;
	$rm = $wpdb->prefix . 'rank_math_redirections';
	static $rm_var = null;
	if ( null === $rm_var ) {
		$rm_var = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm ) ) === $rm );
	}
	if ( $rm_var ) {
		$kaynak = ltrim( $yol, '/' );
		$v = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$rm} WHERE status = 'active' AND ( sources LIKE %s OR sources LIKE %s ) LIMIT 1",
			'%' . $wpdb->esc_like( $kaynak ) . '%',
			'%' . $wpdb->esc_like( rtrim( $kaynak, '/' ) ) . '%'
		) );
		if ( $v ) { return true; }
	}
	return false;
}

/**
 * Son 90 günde Search Console'da gösterim almış ama artık sitede
 * karşılığı olmayan adresler. Sonuç 12 saat saklanır.
 */
function gbc_dz_olu_adresler( $taze = false ) {
	$ob = 'gbc_dz_olu';
	if ( ! $taze ) {
		$c = get_transient( $ob );
		if ( is_array( $c ) ) { return $c; }
	}
	if ( ! gbc_dz_hazir() || ! function_exists( 'gbc_km_gsc' ) ) {
		return array();
	}

	$bit = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$bas = gmdate( 'Y-m-d', time() - 93 * DAY_IN_SECONDS );
	$g   = gbc_km_gsc( $bas, $bit, array( 'page' ), 1000 );
	if ( is_wp_error( $g ) ) { return array(); }

	$out = array();
	foreach ( (array) ( isset( $g['rows'] ) ? $g['rows'] : array() ) as $r ) {
		$url = isset( $r['keys'][0] ) ? (string) $r['keys'][0] : '';
		if ( '' === $url ) { continue; }
		$yol = gbc_dz_yol( $url );
		if ( gbc_dz_cozulur_mu( $yol ) ) { continue; }
		$out[] = array(
			'yol'      => $yol,
			'url'      => $url,
			'tik'      => isset( $r['clicks'] ) ? (int) $r['clicks'] : 0,
			'gosterim' => isset( $r['impressions'] ) ? (int) $r['impressions'] : 0,
		);
	}
	usort( $out, static function ( $a, $b ) { return $b['gosterim'] <=> $a['gosterim']; } );
	set_transient( $ob, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/* ============================================================
   9) DEFTER
   ============================================================ */

function gbc_dz_deftere() {
	if ( ! function_exists( 'gbc_sorun_ac' ) || ! function_exists( 'gbc_sorun_kapat' ) ) { return; }

	$o = gbc_dz_ozet();
	$s = $o['siniflar'];
	$al = static function ( $k ) use ( $s ) { return isset( $s[ $k ] ) ? (int) $s[ $k ] : 0; };

	/* Hiç tarama yapılmadıysa hüküm verme. */
	if ( $o['bakilan'] < 1 ) { return; }

	$hatali = $al( 'yok404' ) + $al( 'soft404' ) + $al( 'sunucu' ) + $al( 'yonlendirme' ) + $al( 'erisim' );
	if ( $hatali > 0 ) {
		gbc_sorun_ac(
			'dizin_hata', 'dizin',
			$hatali . ' sayfa Google tarafından alınamıyor',
			'404, yumuşak 404, sunucu hatası, kırık yönlendirme ya da erişim engeli. Bunlar dizinden düşer ve trafiği doğrudan keser. Listeyi SEO → Dizin durumu ekranında gör.',
			'yuksek', admin_url( 'admin.php?page=gbc-dizin' ), $hatali
		);
	} else {
		gbc_sorun_kapat( 'dizin_hata', 'alınamayan sayfa kalmadı' );
	}

	$bekleyen = $al( 'kesfedilmis' ) + $al( 'taranmis' ) + $al( 'bilinmiyor' );
	if ( $bekleyen > 0 ) {
		gbc_sorun_ac(
			'dizin_yok', 'dizin',
			$bekleyen . ' sayfa Google dizininde değil',
			'Teknik engel yok; Google ya henüz taramadı ya da değersiz buldu. Çözüm içerik ve iç bağlantı tarafında. SEO → Dizin durumu ekranında hangisinin hangi nedenle beklediği yazıyor.',
			'orta', admin_url( 'admin.php?page=gbc-dizin' ), $bekleyen
		);
	} else {
		gbc_sorun_kapat( 'dizin_yok', 'dizin dışı sayfa kalmadı' );
	}

	$olu = count( gbc_dz_olu_adresler() );
	if ( $olu > 0 ) {
		gbc_sorun_ac(
			'dizin_olu', 'dizin',
			$olu . ' eski adres Google\'da duruyor ama sitede yok',
			'Google bu adresleri hâlâ arama sonuçlarında gösteriyor; tıklayan 404 görüyor. Her biri için doğru sayfaya kalıcı yönlendirme (301) kurulmalı.',
			'orta', admin_url( 'admin.php?page=gbc-dizin&sekme=olu' ), $olu
		);
	} else {
		gbc_sorun_kapat( 'dizin_olu', 'ölü adres kalmadı' );
	}
}

/* ============================================================
   10) ZAMANLANMIŞ İŞ — her gün bir dilim
   1069 adres / günde 150 = yaklaşık haftada bir tam tur.
   ============================================================ */

add_action( 'gbc_dizin_gunluk', 'gbc_dz_cron' );
add_action( 'gbc_dizin_devam',  'gbc_dz_cron_devam' );

/** Gecenin ilk turu: zincir sayacını sıfırlar, ölü adresleri tazeler. */
function gbc_dz_cron() {
	if ( ! gbc_dz_hazir() ) { return; }
	gbc_dz_tablo_kur();
	update_option( 'gbc_dz_zincir', 0, false );
	gbc_dz_olu_adresler( true );
	gbc_dz_tur_calistir();
}

/** Zincirin sonraki halkaları: yalnız tarar. */
function gbc_dz_cron_devam() {
	if ( ! gbc_dz_hazir() ) { return; }
	gbc_dz_tablo_kur();
	gbc_dz_tur_calistir();
}

/**
 * Bir tur çalıştırır ve gerekiyorsa bir sonrakini sıraya koyar.
 *
 * NEDEN ZİNCİR: sunucu tek isteği ~60 saniyede kesiyor, bir turda ancak
 * 15-20 adres soruluyor. 1231 adres tek turda bitmez. Her tur, işi ve
 * kotası kaldıysa 90 saniye sonrasına bir tur daha koyuyor. Böylece gece
 * boyunca kendiliğinden ilerliyor ve hiçbir istek zaman aşımına düşmüyor.
 *
 * ÜÇ DURDURUCU (sonsuz döngü olmasın):
 *   1) bakılmamış adres kalmadıysa,
 *   2) günlük sorgu hakkı bittiyse,
 *   3) zincir GBC_DZ_ZINCIR halkaya ulaştıysa.
 */
function gbc_dz_tur_calistir() {
	$s = gbc_dz_tara( GBC_DZ_TUR );
	gbc_dz_deftere();

	$halka = (int) get_option( 'gbc_dz_zincir', 0 );
	if ( $halka >= GBC_DZ_ZINCIR ) { return; }
	if ( gbc_dz_kalan_is() < 1 ) { return; }
	if ( gbc_dz_kalan_kota() < 1 ) { return; }
	/* Tur hiç ilerleyemediyse (bağlantı bozuk, kota bitti) zinciri sürdürme. */
	if ( empty( $s['bakilan'] ) ) { return; }
	if ( wp_next_scheduled( 'gbc_dizin_devam' ) ) { return; }

	update_option( 'gbc_dz_zincir', $halka + 1, false );
	wp_schedule_single_event( time() + 90, 'gbc_dizin_devam' );
}

add_action( 'admin_init', 'gbc_dz_cron_kur', 20 );
function gbc_dz_cron_kur() {
	if ( wp_next_scheduled( 'gbc_dizin_gunluk' ) ) { return; }
	/* Gece 03:20 (site saatiyle) — günlük kontrolden sonra. */
	$hedef = strtotime( 'tomorrow 03:20', current_time( 'timestamp' ) );
	wp_schedule_event( $hedef - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'gbc_dizin_gunluk' );
}

/* ============================================================
   11) DÜĞMELER
   ============================================================ */

add_action( 'admin_post_gbc_dz_tara', 'gbc_dz_islem_tara' );
function gbc_dz_islem_tara() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_dz_tara' );
	gbc_dz_tablo_kur();
	$adet = isset( $_POST['adet'] ) ? (int) $_POST['adet'] : GBC_DZ_ELLE;
	$adet = max( 1, min( 40, $adet ) );  /* v1.48.1: süre sınırı zaten kesiyor, tavanı da düşürdük */
	$s = gbc_dz_tara( $adet );
	set_transient( 'gbc_dz_mesaj', $s, 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=gbc-dizin' ) );
	exit;
}

add_action( 'admin_post_gbc_dz_olu', 'gbc_dz_islem_olu' );
function gbc_dz_islem_olu() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_dz_olu' );
	$n = count( gbc_dz_olu_adresler( true ) );
	gbc_dz_deftere();
	set_transient( 'gbc_dz_mesaj', array( 'bakilan' => 0, 'atlanan' => 0, 'sebep' => 'Ölü adres taraması bitti: ' . $n . ' adres bulundu.', 'siniflar' => array() ), 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=gbc-dizin&sekme=olu' ) );
	exit;
}

/* ============================================================
   12) EKRAN
   ============================================================ */

function gbc_dz_rozet( $sinif, $adet ) {
	$onem = gbc_dz_sinif_onem( $sinif );
	$renk = array( 0 => array( '#1a7f37', '#e6f4ea' ), 1 => array( '#646970', '#f0f0f1' ),
		2 => array( '#8a6100', '#fcf3e1' ), 3 => array( '#b32d2e', '#fcebea' ) );
	if ( 'dizinde' === $sinif ) { $onem = 0; }
	$r = isset( $renk[ $onem ] ) ? $renk[ $onem ] : $renk[1];
	return '<div style="flex:1 1 160px;min-width:160px;padding:12px 14px;border-radius:8px;background:' . esc_attr( $r[1] )
		. ';border:1px solid ' . esc_attr( $r[0] ) . '33">'
		. '<div style="font-size:26px;font-weight:700;line-height:1.1;color:' . esc_attr( $r[0] ) . '">' . (int) $adet . '</div>'
		. '<div style="font-size:12.5px;color:#3C4149;margin-top:3px">' . esc_html( gbc_dz_sinif_ad( $sinif ) ) . '</div></div>';
}

function gbc_dz_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	gbc_dz_tablo_kur();

	$sekme = isset( $_GET['sekme'] ) ? sanitize_key( $_GET['sekme'] ) : 'ozet';

	echo '<div class="wrap"><h1>GBC SEO · Dizin Durumu</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-dizin' ); }

	$m = get_transient( 'gbc_dz_mesaj' );
	if ( is_array( $m ) ) {
		delete_transient( 'gbc_dz_mesaj' );
		$p = array();
		if ( ! empty( $m['bakilan'] ) ) { $p[] = $m['bakilan'] . ' adres sorgulandı.'; }
		if ( ! empty( $m['sebep'] ) )   { $p[] = $m['sebep']; }
		if ( ! empty( $m['siniflar'] ) ) {
			$d = array();
			foreach ( $m['siniflar'] as $s => $n ) { $d[] = gbc_dz_sinif_ad( $s ) . ': ' . $n; }
			$p[] = implode( ' · ', $d );
		}
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( implode( ' ', $p ) ) . '</p></div>';
	}

	if ( ! gbc_dz_hazir() ) {
		echo '<div class="notice notice-error"><p><strong>Search Console bağlantısı kurulu değil.</strong> '
			. 'Bağlantılar → API anahtarları ekranından Google servis hesabı JSON\'unu ve Search Console site adresini gir.</p></div></div>';
		return;
	}

	$o = gbc_dz_ozet();

	/* ---- Özet kutuları ---- */
	echo '<p style="max-width:860px;color:#3C4149">Sitedeki <strong>' . (int) $o['toplam'] . '</strong> adresin '
		. '<strong>' . (int) $o['bakilan'] . '</strong> tanesi Google\'a soruldu. '
		. 'Bugün kalan sorgu hakkı: <strong>' . (int) gbc_dz_kalan_kota() . '</strong> / ' . GBC_DZ_TAVAN . '. '
		. 'Her gece 03:20\'de kendiliğinden başlıyor ve iş bitene kadar 90 saniyede bir devam ediyor — '
		. 'sana düşen bir şey yok.</p>';

	echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:16px 0;max-width:1400px">';
	$sira = array( 'dizinde', 'taranmis', 'kesfedilmis', 'bilinmiyor', 'kanonik', 'noindex', 'robots',
		'yok404', 'soft404', 'sunucu', 'yonlendirme', 'erisim', 'hata', 'bakilmadi' );
	foreach ( $sira as $s ) {
		$n = isset( $o['siniflar'][ $s ] ) ? (int) $o['siniflar'][ $s ] : 0;
		if ( 0 === $n && ! in_array( $s, array( 'dizinde', 'bakilmadi' ), true ) ) { continue; }
		echo gbc_dz_rozet( $s, $n ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';

	/* ---- Düğmeler ---- */
	echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 20px">';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">';
	wp_nonce_field( 'gbc_dz_tara' );
	echo '<input type="hidden" name="action" value="gbc_dz_tara">';
	echo '<input type="hidden" name="adet" value="' . (int) GBC_DZ_ELLE . '">';
	echo '<button class="button button-primary">Şimdi ' . (int) GBC_DZ_ELLE . ' adres sorgula</button>';
	echo '</form>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">';
	wp_nonce_field( 'gbc_dz_olu' );
	echo '<input type="hidden" name="action" value="gbc_dz_olu">';
	echo '<button class="button">Ölü adresleri yeniden tara</button>';
	echo '</form>';
	echo '</div>';

	/* ---- İç sekmeler ---- */
	$ics = array( 'ozet' => 'Sorunlu adresler', 'olu' => 'Ölü adresler', 'hepsi' => 'Bütün adresler' );
	echo '<div style="display:flex;gap:16px;border-bottom:1px solid #DCDCDE;margin:0 0 18px;max-width:1400px">';
	foreach ( $ics as $k => $ad ) {
		$bu = ( $k === $sekme );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-dizin&sekme=' . $k ) ) . '" style="text-decoration:none;font-size:13.5px;padding:8px 2px;margin-bottom:-1px;'
			. ( $bu ? 'color:#17181A;font-weight:700;border-bottom:2px solid #17181A' : 'color:#50575E;border-bottom:2px solid transparent' ) . '">' . esc_html( $ad ) . '</a>';
	}
	echo '</div>';

	if ( 'olu' === $sekme )        { gbc_dz_ekran_olu(); }
	elseif ( 'hepsi' === $sekme )  { gbc_dz_ekran_hepsi(); }
	else                           { gbc_dz_ekran_sorunlu(); }

	echo '</div>';
}

function gbc_dz_ekran_sorunlu() {
	$gruplar = array(
		'Google alamıyor — acil'  => array( 'yok404', 'soft404', 'sunucu', 'yonlendirme', 'erisim' ),
		'Dizine girmeyi bekliyor' => array( 'taranmis', 'kesfedilmis', 'bilinmiyor' ),
		'Bilerek mi engellendi?'  => array( 'noindex', 'robots', 'kanonik' ),
		'Sorgulanamadı'           => array( 'hata' ),
	);

	$hic = true;
	foreach ( $gruplar as $baslik => $siniflar ) {
		$satirlar = array();
		foreach ( $siniflar as $s ) {
			foreach ( gbc_dz_liste( $s, 400 ) as $r ) { $satirlar[] = $r; }
		}
		if ( ! $satirlar ) { continue; }
		$hic = false;

		echo '<h2 style="margin:26px 0 8px;font-size:16px">' . esc_html( $baslik ) . ' <span style="color:#646970;font-weight:400">(' . count( $satirlar ) . ')</span></h2>';

		/* Grubun çözüm cümleleri — her sınıf için bir kere. */
		$gorulen = array();
		foreach ( $satirlar as $r ) {
			if ( isset( $gorulen[ $r->sinif ] ) ) { continue; }
			$gorulen[ $r->sinif ] = 1;
			$c = gbc_dz_sinif_cozum( $r->sinif );
			if ( '' === $c ) { continue; }
			echo '<p style="margin:4px 0;max-width:900px;font-size:13px;color:#3C4149"><strong>' . esc_html( gbc_dz_sinif_ad( $r->sinif ) ) . ':</strong> ' . esc_html( $c ) . '</p>';
		}

		echo '<table class="widefat striped" style="max-width:1400px;margin-top:8px"><thead><tr>'
			. '<th style="width:42%">Adres</th><th style="width:20%">Durum</th>'
			. '<th style="width:22%">Google\'ın kendi açıklaması</th><th style="width:16%">Son tarama</th>'
			. '</tr></thead><tbody>';
		foreach ( array_slice( $satirlar, 0, 300 ) as $r ) {
			$duzen = $r->pid ? get_edit_post_link( $r->pid ) : '';
			echo '<tr><td><a href="' . esc_url( home_url( $r->yol ) ) . '" target="_blank" rel="noopener">' . esc_html( $r->yol ) . '</a>';
			if ( $duzen ) { echo ' <a href="' . esc_url( $duzen ) . '" style="font-size:12px">düzenle</a>'; }
			echo '</td><td>' . esc_html( gbc_dz_sinif_ad( $r->sinif ) ) . '</td>'
				. '<td style="font-size:12px;color:#50575E">' . esc_html( $r->kapsam ? $r->kapsam : $r->hata ) . '</td>'
				. '<td style="font-size:12px;color:#50575E">' . esc_html( $r->son_tarama ? substr( $r->son_tarama, 0, 10 ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( count( $satirlar ) > 300 ) {
			echo '<p style="color:#646970;font-size:12px">İlk 300 satır gösteriliyor.</p>';
		}
	}

	if ( $hic ) {
		/* v1.48.1: hiç tarama yapılmadığında da "hepsi dizinde, sorun yok"
		   yazıyordu. Bilinmeyeni iyi haber diye göstermek, sorunu gizlemektir. */
		$o = gbc_dz_ozet();
		if ( $o['bakilan'] < 1 ) {
			echo '<p style="padding:18px;background:#fcf3e1;border:1px solid #8a610033;border-radius:8px;max-width:900px">'
				. '<strong>Henüz hiçbir adres sorulmadı</strong>, bu yüzden söylenecek bir şey yok. '
				. 'İlk tarama bu gece 03:20\'de kendiliğinden başlayacak; beklemek istemezsen yukarıdaki düğmeye basabilirsin.</p>';
		} else {
			echo '<p style="padding:18px;background:#e6f4ea;border:1px solid #1a7f3733;border-radius:8px;max-width:900px">'
				. 'Sorulan <strong>' . (int) $o['bakilan'] . '</strong> adresin hepsi Google dizininde, açık sorun yok. '
				. 'Kalan <strong>' . (int) ( $o['toplam'] - $o['bakilan'] ) . '</strong> adres henüz sorulmadı.</p>';
		}
	}
}

function gbc_dz_ekran_olu() {
	$liste = gbc_dz_olu_adresler();

	echo '<p style="max-width:900px;color:#3C4149">Google bu adresleri son 90 günde arama sonuçlarında gösterdi, '
		. 'ama sitede artık karşılıkları yok ve bir yönlendirme de yok. Tıklayan ziyaretçi 404 görüyor. '
		. 'Her biri için en yakın içeriğe kalıcı yönlendirme (301) kurulmalı.</p>';

	if ( ! $liste ) {
		echo '<p style="padding:18px;background:#e6f4ea;border:1px solid #1a7f3733;border-radius:8px;max-width:900px">Ölü adres bulunamadı.</p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
		. '<th style="width:60%">Adres</th><th>Gösterim (90 gün)</th><th>Tıklama</th></tr></thead><tbody>';
	foreach ( $liste as $x ) {
		echo '<tr><td><a href="' . esc_url( $x['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $x['yol'] ) . '</a></td>'
			. '<td>' . (int) $x['gosterim'] . '</td><td>' . (int) $x['tik'] . '</td></tr>';
	}
	echo '</tbody></table>';
}

function gbc_dz_ekran_hepsi() {
	global $wpdb;
	$t = gbc_dz_tablo();
	$r = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY sinif ASC, yol ASC LIMIT 1200" );
	if ( ! $r ) { echo '<p>Henüz tarama yapılmadı.</p>'; return; }

	echo '<table class="widefat striped" style="max-width:1400px"><thead><tr>'
		. '<th style="width:38%">Adres</th><th>Durum</th><th>Google açıklaması</th><th>Son tarama</th><th>Bakıldı</th>'
		. '</tr></thead><tbody>';
	foreach ( $r as $x ) {
		echo '<tr><td><a href="' . esc_url( home_url( $x->yol ) ) . '" target="_blank" rel="noopener">' . esc_html( $x->yol ) . '</a></td>'
			. '<td>' . esc_html( gbc_dz_sinif_ad( $x->sinif ) ) . '</td>'
			. '<td style="font-size:12px;color:#50575E">' . esc_html( $x->kapsam ? $x->kapsam : $x->hata ) . '</td>'
			. '<td style="font-size:12px;color:#50575E">' . esc_html( $x->son_tarama ? substr( $x->son_tarama, 0, 10 ) : '—' ) . '</td>'
			. '<td style="font-size:12px;color:#50575E">' . esc_html( $x->bakilan ? substr( $x->bakilan, 0, 10 ) : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="color:#646970;font-size:12px">En fazla 1200 satır gösteriliyor.</p>';
}
