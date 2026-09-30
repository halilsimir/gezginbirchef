<?php
/**
 * GBC Core · Plan (iş listesi) — v1.43.0, 30 Eylül 2026
 * ------------------------------------------------------------
 * Halil'in Google tablosu (GBC_Gezi_Rehberleri_IsListesi) sitenin PLANIDIR:
 * hangi sayfa açılacak, hangisi düzeltilecek, hedef adres, şablon, video,
 * aylık arama hacmi, kış teması. Bu modül:
 *
 *   1) Tabloyu okur. Sıra: servis hesabı (Sheets API) → herkese açık CSV →
 *      elle yapıştırılan CSV. Günde bir kez kendiliğinden tazelenir.
 *   2) Her satırı sitedeki gerçek sayfayla eşleştirir (Post ID → mevcut URL →
 *      hedef URL) ve GERÇEK durumu çıkarır: yok / taslak / yayında-doğru /
 *      yayında-düzeltilecek.
 *   3) Tutarsızlıkları yazar: tablo "Açılacak" diyor ama sayfa açık, adres
 *      hedeften farklı (301 gerekir), şablon yanlış, Post ID yanlış/boş,
 *      hacim yok, skor düşük.
 *   4) Mevsim: aylık hacimden zirve ayı, "en geç şu tarihte hazır olmalı"
 *      (zirveden iki ay önce) ve alarm (acil / yaklaşıyor / zirve öncesi gözden geçir).
 *   5) Öncelik puanı: hacim + durum + skor + mevsim + video + Search Console.
 *
 * Tabloya YAZMAZ. Ağ isteği yalnız Google'a (tablo okuma) gider.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GBC_PLAN_OPT', 'gbc_plan' );
define( 'GBC_PLAN_AYAR', 'gbc_plan_ayar' );
define( 'GBC_PLAN_SHEET_VARSAYILAN', '1UPTZT6IwQKsO_Zb2A47VuQ6g8z5d_XEx0pKLFm93UGA' );

function gbc_plan_aylar() {
	return array( 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara' );
}
function gbc_plan_ay_uzun( $i ) {
	$a = array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	return isset( $a[ $i ] ) ? $a[ $i ] : '';
}

function gbc_plan_ayar() {
	$a = get_option( GBC_PLAN_AYAR, array() );
	if ( ! is_array( $a ) ) { $a = array(); }
	return wp_parse_args( $a, array( 'sheet' => GBC_PLAN_SHEET_VARSAYILAN ) );
}

/** Kayıtlı plan. */
function gbc_plan_oku() {
	$p = get_option( GBC_PLAN_OPT, array() );
	if ( is_string( $p ) ) { $p = json_decode( $p, true ); }
	/* Tablo hiç okunamadıysa pakete gömülü ilk veri (29 Eyl 2026 dışa aktarımı) kullanılır. */
	if ( ( ! is_array( $p ) || empty( $p['satir'] ) ) && file_exists( __DIR__ . '/plan-ilk.json' ) ) {
		$ilk = json_decode( (string) file_get_contents( __DIR__ . '/plan-ilk.json' ), true );
		if ( is_array( $ilk ) ) {
			$not = is_array( $p ) && ! empty( $p['not'] ) ? $p['not'] : '';
			$p = $ilk; $p['not'] = $not;
		}
	}
	if ( ! is_array( $p ) ) { $p = array(); }
	return wp_parse_args( $p, array( 't' => 0, 'kaynak' => '', 'satir' => array(), 'not' => '' ) );
}

/* ============================================================
   OKUMA — tablo satırlarını ortak biçime çevir
   ============================================================ */

function gbc_plan_durum_kod( $s ) {
	$s = (string) $s;
	if ( false !== mb_stripos( $s, 'açılacak' ) ) { return 'acilacak'; }
	if ( false !== mb_stripos( $s, 'düzeltme' ) ) { return 'duzeltme'; }
	if ( false !== mb_stripos( $s, 'açık' ) ) { return 'acik'; }
	return '';
}

/** Sayıyı "1.900" / "22.200" / "590" biçiminden okur. */
function gbc_plan_sayi( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s || ! preg_match( '/^[\d.,]+$/', $s ) ) { return null; }
	return (int) str_replace( array( '.', ',' ), '', $s );
}

/**
 * Satırlar (başlık satırı dahil, dizi dizisi) → plan satırları.
 * @param array $gezi  "Gezi Rehberleri" sayfası
 * @param array $kis   "Kış Temaları" sayfası (numaralar kış işareti için)
 */
function gbc_plan_ayikla( $gezi, $kis = array() ) {
	$bas = null; $ix = array();
	foreach ( $gezi as $i => $r ) {
		if ( isset( $r[0] ) && '#' === trim( (string) $r[0] ) ) { $bas = $i; break; }
	}
	if ( null === $bas ) { return array(); }
	foreach ( $gezi[ $bas ] as $j => $ad ) { $ix[ trim( (string) $ad ) ] = $j; }
	$al = static function ( $r, $ad ) use ( $ix ) {
		return ( isset( $ix[ $ad ] ) && isset( $r[ $ix[ $ad ] ] ) ) ? trim( (string) $r[ $ix[ $ad ] ] ) : '';
	};

	$kis_no = array();
	foreach ( (array) $kis as $r ) { if ( isset( $r[0] ) && ctype_digit( trim( (string) $r[0] ) ) ) { $kis_no[ trim( (string) $r[0] ) ] = true; } }

	$out = array(); $ust = ''; $alt_sira = 0;
	foreach ( $gezi as $i => $r ) {
		if ( $i <= $bas ) { continue; }
		$no = isset( $r[0] ) ? trim( (string) $r[0] ) : '';
		$ad = $al( $r, 'Sayfa Adı' );
		if ( '' === $ad ) { continue; } /* bölge ayırıcı ya da boş */
		if ( ctype_digit( $no ) ) { $ust = $no; $alt_sira = 0; $anahtar = $no; }
		else { $alt_sira++; $anahtar = $ust . '.' . $alt_sira; }
		$ay = array();
		foreach ( gbc_plan_aylar() as $a ) { $ay[] = gbc_plan_sayi( $al( $r, $a ) ); }
		$bolge = $al( $r, 'Bölge' );
		$out[] = array(
			'no'     => $anahtar,
			'ust'    => ctype_digit( $no ) ? '' : $ust,
			'bolge'  => $bolge,
			'ulke'   => $al( $r, 'Ülke' ),
			'ad'     => $ad,
			'durum'  => gbc_plan_durum_kod( $al( $r, 'Durum' ) ),
			'sablon' => str_replace( '—', '', $al( $r, 'Şablon' ) ),
			'mevcut' => str_replace( '—', '', $al( $r, 'Mevcut URL' ) ),
			'hedef'  => str_replace( '—', '', $al( $r, 'Hedef URL' ) ),
			'pid'    => (int) $al( $r, 'Post ID' ),
			'yt'     => $al( $r, 'YouTube' ),
			'not'    => $al( $r, 'Notlar' ),
			'ay'     => $ay,
			'kis'    => ( ctype_digit( $no ) && isset( $kis_no[ $no ] ) ) || ( '' !== $ust && isset( $kis_no[ $ust ] ) ) || false !== mb_stripos( $bolge, 'KIŞ' ),
		);
	}
	return $out;
}

/** CSV metni → dizi dizisi (çok satırlı hücreleri de okur). */
function gbc_plan_csv( $metin ) {
	$f = fopen( 'php://temp', 'r+' );
	fwrite( $f, (string) $metin );
	rewind( $f );
	$o = array();
	while ( false !== ( $r = fgetcsv( $f, 0, ',', '"', '' ) ) ) { $o[] = $r; }
	fclose( $f );
	return $o;
}

/** Servis hesabıyla, yalnız tablo okuma yetkisiyle belirteç. */
function gbc_plan_token() {
	$t = get_transient( 'gbc_plan_token' );
	if ( is_string( $t ) && '' !== $t ) { return $t; }
	if ( ! function_exists( 'gbc_km_ayar' ) || ! function_exists( 'gbc_km_b64' ) ) { return new WP_Error( 'yok', 'Google bağlantı modülü yüklü değil.' ); }
	$sa = json_decode( (string) gbc_km_ayar( 'google_sa', '' ), true );
	if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) { return new WP_Error( 'yok', 'Servis hesabı tanımlı değil.' ); }
	$simdi = time();
	$girdi = gbc_km_b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . gbc_km_b64( wp_json_encode( array(
		'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
		'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $simdi, 'exp' => $simdi + 3600,
	) ) );
	$imza = '';
	if ( ! openssl_sign( $girdi, $imza, $sa['private_key'], 'sha256WithRSAEncryption' ) ) { return new WP_Error( 'imza', 'JWT imzalanamadı.' ); }
	$c = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 20, 'body' => array(
		'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $girdi . '.' . gbc_km_b64( $imza ) ) ) );
	if ( is_wp_error( $c ) ) { return $c; }
	$g = json_decode( wp_remote_retrieve_body( $c ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $c ) || empty( $g['access_token'] ) ) {
		return new WP_Error( 'token', 'Belirteç alınamadı: ' . substr( (string) wp_remote_retrieve_body( $c ), 0, 200 ) );
	}
	set_transient( 'gbc_plan_token', $g['access_token'], 3300 );
	return $g['access_token'];
}

/** 1) Sheets API. */
function gbc_plan_cek_api( $id ) {
	$tok = gbc_plan_token();
	if ( is_wp_error( $tok ) ) { return $tok; }
	$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode( $id ) . '/values:batchGet?ranges='
		. rawurlencode( "'Gezi Rehberleri'!A1:Z600" ) . '&ranges=' . rawurlencode( "'Kış Temaları'!A1:L300" );
	$c = wp_remote_get( $url, array( 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $tok ) ) );
	if ( is_wp_error( $c ) ) { return $c; }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	$g   = json_decode( wp_remote_retrieve_body( $c ), true );
	if ( 200 !== $kod ) {
		$m = is_array( $g ) && ! empty( $g['error']['message'] ) ? $g['error']['message'] : 'HTTP ' . $kod;
		return new WP_Error( 'api', $m );
	}
	$vr = isset( $g['valueRanges'] ) ? $g['valueRanges'] : array();
	return array(
		isset( $vr[0]['values'] ) ? $vr[0]['values'] : array(),
		isset( $vr[1]['values'] ) ? $vr[1]['values'] : array(),
	);
}

/** 2) Herkese açık tablo (bağlantıya sahip herkes görüntüleyebilir). */
function gbc_plan_cek_acik( $id ) {
	$al = static function ( $sayfa ) use ( $id ) {
		$c = wp_remote_get( 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $id ) . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode( $sayfa ), array( 'timeout' => 25 ) );
		if ( is_wp_error( $c ) ) { return $c; }
		$b = (string) wp_remote_retrieve_body( $c );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $c ) || false !== stripos( substr( $b, 0, 300 ), '<html' ) ) {
			return new WP_Error( 'kapali', 'Tablo herkese açık değil.' );
		}
		return gbc_plan_csv( $b );
	};
	$g = $al( 'Gezi Rehberleri' );
	if ( is_wp_error( $g ) ) { return $g; }
	$k = $al( 'Kış Temaları' );
	return array( $g, is_wp_error( $k ) ? array() : $k );
}

/** Tabloyu çeker ve kaydeder. Hiçbir yol çalışmazsa eski kayıt korunur. */
function gbc_plan_cek() {
	$id = gbc_plan_ayar()['sheet'];
	$hatalar = array();
	foreach ( array( 'api' => 'gbc_plan_cek_api', 'acik' => 'gbc_plan_cek_acik' ) as $kaynak => $fn ) {
		$r = $fn( $id );
		if ( is_wp_error( $r ) ) { $hatalar[] = $kaynak . ': ' . $r->get_error_message(); continue; }
		$satir = gbc_plan_ayikla( $r[0], $r[1] );
		if ( count( $satir ) < 5 ) { $hatalar[] = $kaynak . ': tablo boş ya da başlık satırı (#, Sayfa Adı…) bulunamadı'; continue; }
		gbc_plan_kaydet( $satir, 'sheet-' . $kaynak );
		return array( 'ok' => true, 'kaynak' => $kaynak, 'adet' => count( $satir ) );
	}
	$p = gbc_plan_oku();
	$p['not'] = implode( ' · ', $hatalar );
	$p['deneme'] = time();
	update_option( GBC_PLAN_OPT, $p, false );
	return array( 'ok' => false, 'hata' => $p['not'] );
}

function gbc_plan_kaydet( $satir, $kaynak ) {
	update_option( GBC_PLAN_OPT, array( 't' => time(), 'kaynak' => $kaynak, 'satir' => array_values( $satir ), 'not' => '' ), false );
	/* v1.43.1: analiz hemen burada (arka planda/işlem isteğinde) yenilenir; Envanter ekranı hesap yapmaz. */
	gbc_plan_analiz( true );
	delete_transient( 'gbc_env_indeks' );
}

/* Günlük tazeleme */
add_action( 'gbc_plan_gunluk', 'gbc_plan_cek' );
add_action( 'init', static function () {
	if ( ! wp_next_scheduled( 'gbc_plan_gunluk' ) ) { wp_schedule_event( time() + 900, 'daily', 'gbc_plan_gunluk' ); }
} );

/* Elle: tablodan güncelle / CSV yapıştır */
add_action( 'admin_post_gbc_plan', 'gbc_plan_islem' );
function gbc_plan_islem() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_plan' );
	$is = isset( $_REQUEST['is'] ) ? sanitize_key( $_REQUEST['is'] ) : '';
	$msg = '';
	if ( 'cek' === $is ) {
		$r = gbc_plan_cek();
		$msg = $r['ok'] ? sprintf( 'Tablo okundu (%s): %d satır.', 'api' === $r['kaynak'] ? 'servis hesabı' : 'herkese açık bağlantı', $r['adet'] ) : 'Tablo okunamadı: ' . $r['hata'];
	} elseif ( 'csv' === $is && ! empty( $_POST['csv'] ) ) {
		$satir = gbc_plan_ayikla( gbc_plan_csv( wp_unslash( $_POST['csv'] ) ) );
		if ( count( $satir ) >= 5 ) { gbc_plan_kaydet( $satir, 'csv' ); $msg = sprintf( 'Yapıştırılan tablodan %d satır okundu.', count( $satir ) ); }
		else { $msg = 'Yapıştırılan metinde başlık satırı (#, Bölge, Ülke, Sayfa Adı…) bulunamadı.'; }
	} elseif ( 'sheet' === $is && isset( $_POST['sheet'] ) ) {
		$v = sanitize_text_field( wp_unslash( $_POST['sheet'] ) );
		if ( preg_match( '~/d/([a-zA-Z0-9_-]{20,})~', $v, $m ) ) { $v = $m[1]; }
		$a = gbc_plan_ayar(); $a['sheet'] = preg_replace( '/[^a-zA-Z0-9_-]/', '', $v );
		update_option( GBC_PLAN_AYAR, $a, false );
		$msg = 'Tablo adresi kaydedildi.';
	}
	wp_safe_redirect( admin_url( 'admin.php?page=gbc-seo-envanter&sekme=plan&pmsg=' . rawurlencode( $msg ) ) );
	exit;
}

/* ============================================================
   EŞLEŞTİRME VE ANALİZ
   ============================================================ */

function gbc_plan_yol( $url ) {
	$y = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	return '' === $y ? '' : '/' . trim( $y, '/' ) . '/';
}

/** Search Console (Rank Math tablosu) son 90 gün, yol → tık/gösterim/sıra. */
function gbc_plan_gsc_harita() {
	static $h = null;
	if ( null !== $h ) { return $h; }
	$h = array();
	if ( ! function_exists( 'gbc_seo_kaynak' ) ) { return $h; }
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return $h; }
	global $wpdb;
	foreach ( (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT page, SUM(clicks) t, SUM(impressions) g, AVG(position) s FROM `{$k['tablo']}` WHERE created >= %s GROUP BY page",
		gbc_seo_sinir( 90 ) ), ARRAY_A ) as $r ) {
		$h[ gbc_plan_yol( $r['page'] ) ] = array( 't' => (int) $r['t'], 'g' => (int) $r['g'], 's' => (float) $r['s'] );
	}
	return $h;
}

/** Rank Math yönlendirmeleri: kaynak yolu var mı. */
function gbc_plan_yonlendirme_var( $yol ) {
	static $liste = null;
	if ( null === $liste ) {
		$liste = array();
		global $wpdb;
		$t = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) ) {
			foreach ( (array) $wpdb->get_results( "SELECT sources, url_to FROM `{$t}` WHERE status = 'active'", ARRAY_A ) as $r ) {
				foreach ( (array) maybe_unserialize( $r['sources'] ) as $s ) {
					if ( ! empty( $s['pattern'] ) ) { $liste[ '/' . trim( (string) $s['pattern'], '/' ) . '/' ] = (string) $r['url_to']; }
				}
			}
		}
	}
	return isset( $liste[ $yol ] ) ? $liste[ $yol ] : '';
}

/** Kayıtlı SEO denetimi (yeni skor, sürüm 2). */
function gbc_plan_skor( $pid ) {
	$m = get_post_meta( (int) $pid, '_gbc_seo', true );
	if ( ! is_array( $m ) || ! isset( $m['skor'] ) ) { return array( 'skor' => null, 'eski' => false, 'zaman' => 0, 'yap' => null, 'dizin' => '' ); }
	$yeni = isset( $m['skor_surum'] ) && (int) $m['skor_surum'] >= 2;
	return array( 'skor' => (int) $m['skor'], 'eski' => ! $yeni, 'zaman' => (int) $m['zaman'],
		'yap' => isset( $m['yapilacak'] ) ? (int) $m['yapilacak'] : null, 'dizin' => isset( $m['dizin'] ) ? (string) $m['dizin'] : '' );
}

/** Hedef şablon: adı "Gezi Rehberi" olan Gezi, hub Liste, diğerleri tablodaki. */
function gbc_plan_hedef_sablon( $s ) {
	if ( preg_match( '/gezi rehberi/iu', $s['ad'] ) && '' === $s['ust'] ) { return 'Gezi'; }
	if ( preg_match( '/ana hub/iu', $s['ad'] ) ) { return 'Liste'; }
	return in_array( $s['sablon'], array( 'Gezi', 'Liste', 'Detay', 'Rota' ), true ) ? $s['sablon'] : '';
}

/** Hacim ve mevsim. */
function gbc_plan_mevsim( $s, $pid, $bugun ) {
	$var = array_filter( $s['ay'], static function ( $x ) { return null !== $x; } );
	$aylik = null; $zirve = null; $kaynak = '';
	if ( $var ) {
		$aylik = (int) round( array_sum( $var ) / count( $var ) );
		$kaynak = 'tablo';
		/* Mevsim yalnız anlamlı hacimde: aylık 10–20 aramada "zirve" gürültüdür. */
		if ( count( $var ) >= 10 && max( $var ) >= 100 ) {
			$mx = max( $var );
			if ( $mx >= 1.4 * max( 1, $aylik ) ) {
				/* Eşit zirvelerde sezonun başladığı ay: bir önceki ayı daha düşük olan (Kuzey Işıkları: Aralık = Ocak → Aralık). */
				$zirve = (int) array_search( $mx, $s['ay'], true );
				for ( $m = 0; $m < 12; $m++ ) {
					$onceki = $s['ay'][ ( $m + 11 ) % 12 ];
					if ( $s['ay'][ $m ] === $mx && ( null === $onceki || $onceki < $mx ) ) { $zirve = $m; break; }
				}
			}
		}
	}
	if ( null === $aylik && preg_match( '~([\d.]+)\s*/\s*mo~u', $s['not'], $m ) ) { $aylik = gbc_plan_sayi( $m[1] ); $kaynak = 'not'; }
	if ( null === $aylik && $pid && function_exists( 'gbc_kw_oku' ) ) {
		$v = gbc_kw_oku( $pid );
		$t = function_exists( 'gbc_kw_tohum' ) ? gbc_kw_normal( gbc_kw_tohum( $pid, $v ) ) : '';
		foreach ( (array) $v['kelimeler'] as $x ) { if ( gbc_kw_normal( $x['k'] ) === $t && null !== $x['h'] ) { $aylik = (int) $x['h']; $kaynak = 'kelime'; } }
	}
	$varsayim = false;
	/* Kış teması ana sayfası, hacmi hiç yoksa: zirve Aralık (Noel) varsayılır. Alt sayfalara (restoran, konaklama) uygulanmaz. */
	if ( null === $zirve && ! empty( $s['kis'] ) && '' === $s['ust'] && ! $var ) { $zirve = 11; $varsayim = true; }

	$son = null; $zbas = null;
	if ( null !== $zirve ) {
		$y = (int) gmdate( 'Y', $bugun );
		$zbas = gmmktime( 0, 0, 0, $zirve + 1, 1, $y );
		$zbit = gmmktime( 0, 0, 0, $zirve + 2, 1, $y ) - 1;
		if ( $bugun > $zbit ) { $zbas = gmmktime( 0, 0, 0, $zirve + 1, 1, $y + 1 ); }
		$son = gmmktime( 0, 0, 0, (int) gmdate( 'n', $zbas ) - 2, 1, (int) gmdate( 'Y', $zbas ) );
	}
	return array( 'aylik' => $aylik, 'hk' => $kaynak, 'zirve' => $zirve, 'varsayim' => $varsayim, 'zirve_t' => $zbas, 'son' => $son );
}

/**
 * Bütün plan analizi. 1 saat saklanır; tablo ya da toplama değişince silinir.
 * @return array( 'satir' => [...], 'planda_yok' => [...], 'ozet' => [...] )
 */
function gbc_plan_analiz( $taze = false ) {
	if ( ! $taze ) {
		$c = get_transient( 'gbc_plan_analiz' );
		if ( is_array( $c ) ) { return $c; }
	}
	$p = gbc_plan_oku();
	$bugun = current_time( 'timestamp' );
	$gsc = gbc_plan_gsc_harita();
	$out = array(); $eslesen = array();
	$oz = array( 'toplam' => 0, 'plan' => array( 'acik' => 0, 'duzeltme' => 0, 'acilacak' => 0 ), 'gercek' => array( 'acik' => 0, 'duzeltme' => 0, 'taslak' => 0, 'yok' => 0 ),
		'tutarsiz' => 0, 'alarm' => array( 'acil' => 0, 'yakin' => 0, 'gozden' => 0 ), 'hacim_yok' => 0, 'url_degisecek' => 0 );

	foreach ( $p['satir'] as $s ) {
		$oz['toplam']++;
		if ( isset( $oz['plan'][ $s['durum'] ] ) ) { $oz['plan'][ $s['durum'] ]++; }

		/* Eşleştir */
		$pid = 0; $nasil = '';
		if ( $s['pid'] && get_post_status( $s['pid'] ) ) { $pid = (int) $s['pid']; $nasil = 'id'; }
		foreach ( array( 'mevcut', 'hedef' ) as $alan ) {
			if ( $pid || '' === $s[ $alan ] ) { continue; }
			$x = (int) url_to_postid( $s[ $alan ] );
			if ( $x ) { $pid = $x; $nasil = $alan; }
		}
		$st = $pid ? get_post_status( $pid ) : '';
		$yol = $pid ? gbc_plan_yol( get_permalink( $pid ) ) : '';
		$sab = $pid ? ( function_exists( 'gbc_env_sablon' ) ? gbc_env_sablon( get_post_field( 'post_content', $pid ) ) : '' ) : '';
		$hs  = gbc_plan_hedef_sablon( $s );
		$hy  = gbc_plan_yol( $s['hedef'] );
		$sablon_ok = ( '' === $hs || $sab === $hs );
		$url_ok    = ( '' === $hy || $yol === $hy );

		if ( ! $pid ) { $g = 'yok'; }
		elseif ( 'publish' !== $st ) { $g = 'taslak'; }
		else { $g = ( $sablon_ok && $url_ok ) ? 'acik' : 'duzeltme'; }
		$oz['gercek'][ $g ]++;
		if ( $pid ) { $eslesen[ $pid ] = true; }

		$sk = $pid ? gbc_plan_skor( $pid ) : array( 'skor' => null, 'eski' => false, 'zaman' => 0, 'yap' => null, 'dizin' => '' );
		$gs = ( $pid && isset( $gsc[ $yol ] ) ) ? $gsc[ $yol ] : null;
		$mv = gbc_plan_mevsim( $s, $pid, $bugun );

		/* Bulgular: seviye 1 tutarsızlık, 2 iş, 3 bilgi */
		$b = array();
		if ( 'acilacak' === $s['durum'] && in_array( $g, array( 'acik', 'duzeltme' ), true ) ) { $b[] = array( 1, 'Tabloda "Açılacak" ama sayfa yayında (' . $yol . '). Tablodaki durumu güncelle.' ); }
		if ( in_array( $s['durum'], array( 'acik', 'duzeltme' ), true ) && 'yok' === $g ) { $b[] = array( 1, 'Tabloda "' . ( 'acik' === $s['durum'] ? 'Açık' : 'Düzeltme' ) . '" ama sitede bu sayfa bulunamadı (' . ( $s['mevcut'] ? gbc_plan_yol( $s['mevcut'] ) : 'adres yok' ) . ').' ); }
		if ( 'acik' === $s['durum'] && 'duzeltme' === $g ) { $b[] = array( 1, 'Tabloda "Açık" ama sayfa hedefle uyuşmuyor (aşağıdaki şablon/adres notuna bak).' ); }
		if ( 'taslak' === $g ) { $b[] = array( 2, 'Sayfa taslakta (' . $st . '), yayında değil.' ); }
		if ( $pid && ! $url_ok ) {
			$yon = $yol ? gbc_plan_yonlendirme_var( $yol ) : '';
			$b[] = array( 2, 'Adres hedeften farklı: ' . $yol . ' → ' . $hy . '. Slug değişecek, eski adres 301 ile yönlenmeli'
				. ( '' !== $yon ? ' (Rank Math\'te bu adresten yönlendirme zaten var → ' . $yon . ').' : '.' ) );
			$oz['url_degisecek']++;
		}
		if ( $pid && ! $sablon_ok ) { $b[] = array( 2, 'Şablon: şu an ' . ( $sab ?: 'bilinmiyor' ) . ', hedef ' . $hs . '.' ); }
		if ( $s['pid'] && $pid && (int) $s['pid'] !== $pid ) { $b[] = array( 1, 'Tablodaki Post ID ' . (int) $s['pid'] . ', eşleşen sayfa ' . $pid . '. Tabloyu düzelt.' ); }
		if ( ! $s['pid'] && $pid ) { $b[] = array( 3, 'Post ID boş; tabloya ' . $pid . ' yazılabilir.' ); }
		if ( $pid && '' !== $s['mevcut'] && gbc_plan_yol( $s['mevcut'] ) !== $yol ) { $b[] = array( 1, 'Tablodaki Mevcut URL (' . gbc_plan_yol( $s['mevcut'] ) . ') eski; sayfa şu an ' . $yol . ' adresinde. Tabloyu güncelle.' ); }
		if ( 'acilacak' !== $s['durum'] && $s['mevcut'] && $pid && 'mevcut' !== $nasil && 'id' !== $nasil ) { $b[] = array( 3, 'Mevcut URL açılmadı, sayfa hedef adresten bulundu.' ); }
		if ( null === $mv['aylik'] ) { $b[] = array( 3, 'Aylık arama hacmi yok (tabloda ya da kelime evreninde).' ); $oz['hacim_yok']++; }
		if ( $pid && 'publish' === $st ) {
			if ( null === $sk['skor'] ) { $b[] = array( 3, 'Henüz denetlenmedi; toplama sırasına alındı.' ); }
			elseif ( $sk['eski'] ) { $b[] = array( 3, 'Skor eski ölçümle (%' . $sk['skor'] . '); yeni GBC skoru bekleniyor.' ); }
			elseif ( $sk['skor'] < 60 ) { $b[] = array( 2, 'GBC skoru düşük: %' . $sk['skor'] . '.' ); }
			if ( 'yok' === $sk['dizin'] ) { $b[] = array( 2, 'Google dizininde değil.' ); }
		}
		foreach ( $b as $x ) { if ( 1 === $x[0] ) { $oz['tutarsiz']++; break; } }

		/* Hazır mı, alarm */
		$hazir = ( 'acik' === $g ) && null !== $sk['skor'] && ! $sk['eski'] && $sk['skor'] >= 70;
		$alarm = ''; $alarm_not = '';
		if ( $mv['son'] ) {
			$gun_son   = (int) floor( ( $mv['son'] - $bugun ) / DAY_IN_SECONDS );
			$gun_zirve = (int) ceil( ( $mv['zirve_t'] - $bugun ) / DAY_IN_SECONDS );
			$zad = gbc_plan_ay_uzun( $mv['zirve'] ) . ( $mv['varsayim'] ? ' (kış teması)' : '' );
			if ( ! $hazir && $gun_son <= 0 ) {
				$alarm = 'acil';
				$alarm_not = ( $gun_zirve > 0 ? 'Aramaların zirvesi ' . $zad . ', ' . $gun_zirve . ' gün kaldı.' : 'Zirve ayındasın (' . $zad . ').' )
					. ' Son tarih ' . wp_date( 'j F', $mv['son'] ) . ( $gun_son < 0 ? ' geçti' : '' ) . ', sayfa hazır değil.';
			}
			elseif ( ! $hazir && $gun_son <= 45 ) { $alarm = 'yakin'; $alarm_not = 'En geç ' . wp_date( 'j F', $mv['son'] ) . ' hazır olmalı (' . $gun_son . ' gün). Zirve ' . $zad . '.'; }
			elseif ( $hazir && $gun_zirve <= 45 && $gun_zirve >= -20 ) { $alarm = 'gozden'; $alarm_not = 'Zirve ' . $zad . ' yaklaşıyor: yıl, fiyat, etkinlik tarihlerini gözden geçir, ortaklık linklerini kontrol et.'; }
		}
		if ( $alarm ) { $oz['alarm'][ $alarm ]++; }

		/* Öncelik puanı (yaklaşık 0–100) */
		$A = (int) $mv['aylik'];
		$puan = min( 40, 13 * log10( 1 + $A ) );
		if ( 'yok' === $g || 'taslak' === $g ) { $puan += 25; }
		elseif ( 'duzeltme' === $g ) { $puan += 20; }
		else { $puan += null === $sk['skor'] || $sk['eski'] ? 10 : ( 100 - $sk['skor'] ) / 4; }
		$puan += array( 'acil' => 25, 'yakin' => 15, 'gozden' => 8, '' => 0 )[ $alarm ];
		if ( '' !== $s['yt'] ) { $puan += 5; }
		if ( $gs ) { $puan += min( 10, 3 * log10( 1 + $gs['g'] ) ); }

		$out[] = array_merge( $s, array(
			'e_pid' => $pid, 'e_nasil' => $nasil, 'e_durum' => $st, 'gercek' => $g, 'e_yol' => $yol, 'e_sablon' => $sab, 'h_sablon' => $hs, 'h_yol' => $hy,
			'skor' => $sk, 'gsc' => $gs, 'mevsim' => $mv, 'alarm' => $alarm, 'alarm_not' => $alarm_not, 'bulgu' => $b, 'oncelik' => (int) round( $puan ),
		) );
	}

	/* Sitede var, planda yok: gezi rehberi / liste şablonu ya da başlığı. */
	$yok = array();
	global $wpdb;
	$adaylar = (array) $wpdb->get_results(
		"SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page')
		   AND ( post_content LIKE '%[wpcode id=\"22607\"%' OR post_content LIKE '%[wpcode id=\"22608\"%' OR post_content LIKE '%[wpcode id=\"23108\"%'
		      OR post_title LIKE '%Gezi Rehberi%' OR post_title LIKE '%Gezilecek Yerler%' )", ARRAY_A );
	foreach ( $adaylar as $a ) {
		$id = (int) $a['ID'];
		if ( isset( $eslesen[ $id ] ) ) { continue; }
		$y = gbc_plan_yol( get_permalink( $id ) );
		$yok[] = array( 'pid' => $id, 'ad' => $a['post_title'], 'yol' => $y,
			'sablon' => function_exists( 'gbc_env_sablon' ) ? gbc_env_sablon( $a['post_content'] ) : '',
			'skor' => gbc_plan_skor( $id ), 'gsc' => isset( $gsc[ $y ] ) ? $gsc[ $y ] : null );
	}
	usort( $yok, static function ( $a, $b ) { return ( $b['gsc'] ? $b['gsc']['g'] : 0 ) - ( $a['gsc'] ? $a['gsc']['g'] : 0 ); } );
	$oz['planda_yok'] = count( $yok );

	$r = array( 'satir' => $out, 'planda_yok' => $yok, 'ozet' => $oz, 't' => time() );
	set_transient( 'gbc_plan_analiz', $r, 6 * HOUR_IN_SECONDS ); /* v1.43.1: saatlik toplama sonunda tazelenir */
	return $r;
}
