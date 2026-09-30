<?php
/**
 * GBC Core · Yük koruması — v1.43.1, 30 Eylül 2026
 * ------------------------------------------------------------
 * 30 Eyl 2026 17:25'te site kısa süre "Error establishing a database connection" verdi.
 * GBC'nin ağır işleri (saatlik toplama, Kontrol Merkezi turu, günlük kontrol, ortaklık
 * sağlığı, içerik partisi) aynı anda çalışabiliyordu ve bağlantı raporu iç linkleri
 * önbelleği atlayarak topluca istiyordu. Bu dosya:
 *   1) İŞ KİLİDİ: aynı anda yalnız BİR ağır GBC işi çalışır. Kilit 15 dk sonra kendiliğinden
 *      düşer (iş yarıda ölse bile). Aynı istek içindeki iç içe çağrılar (günlük kontrol →
 *      Kontrol Merkezi turu) kilide takılmaz.
 *   2) VERİTABANI HATA SAYFASI: wp-content/db-error.php. Veritabanı koparsa ziyaretçi
 *      İngilizce hata yerine kısa bir Türkçe sayfa görür; 503 + Retry-After döner (Google
 *      geçici bakım sayar), sayfa 20 saniyede kendini yeniler. Veritabanına dokunmaz.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GBC_KILIT', 'gbc_agir_is_kilidi' );

/** @return bool true: çalışabilirsin; false: başka ağır iş sürüyor. */
function gbc_kilit_al( $is, $sure = 900 ) {
	static $bende = false;
	if ( $bende ) { return true; }
	$k = get_transient( GBC_KILIT );
	if ( is_array( $k ) && ! empty( $k['t'] ) && ( time() - (int) $k['t'] ) < $sure ) { return false; }
	set_transient( GBC_KILIT, array( 'is' => (string) $is, 't' => time() ), $sure );
	$bende = true;
	register_shutdown_function( static function () { delete_transient( GBC_KILIT ); } );
	return true;
}

/** Şu an kilidi tutan iş (ekranda göstermek için). */
function gbc_kilit_kimde() {
	$k = get_transient( GBC_KILIT );
	return is_array( $k ) ? $k : null;
}

/* ---------------- Veritabanı hata sayfası ---------------- */

function gbc_db_hata_icerik() {
	return <<<'HTML'
<?php
/* GBC-DB-HATA · GBC Core tarafından yazıldı (v1.43.1). Veritabanına dokunmaz. */
if ( ! headers_sent() ) {
	header( 'HTTP/1.1 503 Service Temporarily Unavailable' );
	header( 'Status: 503 Service Temporarily Unavailable' );
	header( 'Retry-After: 30' );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-store' );
}
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><meta http-equiv="refresh" content="20">
<title>Gezginbirchef · Birkaç saniye</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#FAF7F2;font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#17181A}
.k{max-width:420px;padding:28px 24px;text-align:center}h1{font-size:22px;margin:0 0 10px}p{color:#5C6470;line-height:1.5;margin:0 0 16px}
a{display:inline-block;background:#17181A;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:600}</style></head>
<body><div class="k"><h1>Birkaç saniye içinde buradayız</h1>
<p>Sunucumuz kısa bir yoğunluk yaşıyor. Sayfa 20 saniye içinde kendiliğinden yenilenecek.</p>
<a href="javascript:location.reload()">Şimdi yenile</a></div></body></html>
HTML;
}

/** db-error.php yoksa ya da bizimse (eski sürüm) yazar; başkasının dosyasına dokunmaz. */
function gbc_db_hata_kur() {
	if ( ! defined( 'WP_CONTENT_DIR' ) ) { return; }
	$yol = WP_CONTENT_DIR . '/db-error.php';
	$yeni = gbc_db_hata_icerik();
	if ( file_exists( $yol ) ) {
		$eski = (string) @file_get_contents( $yol );
		if ( false === strpos( $eski, 'GBC-DB-HATA' ) || $eski === $yeni ) { return; }
	}
	@file_put_contents( $yol, $yeni );
}
add_action( 'admin_init', static function () {
	if ( get_option( 'gbc_db_hata_surum' ) === '1.43.1' ) { return; }
	gbc_db_hata_kur();
	update_option( 'gbc_db_hata_surum', '1.43.1', false );
} );
