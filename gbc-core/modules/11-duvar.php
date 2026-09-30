<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Duvarlar.
 *
 * 10-guvenlik.php'nin kapatmadigi bosluklari kapatir. Olculerek bulundu
 * (28 Eylul 2026, gezginbirchef.com): HSTS alt alan adlarini kapsamiyordu,
 * CSP yalniz upgrade-insecure-requests idi, X-Powered-By PHP surumunu her
 * istekte duyuruyordu, panelden kod duzenleme aciktı.
 *
 * HIZ: tek is birkac header() cagrisi ve bir sabit tanimi. Veritabani
 * sorgusu yok (ayarlar tek autoload option'dan gelir), dosya okuma yok,
 * dis istek yok, cikti tamponu yok. Sayfa uretim suresine olculebilir
 * etkisi yoktur — ne masaustunde ne mobilde.
 *
 * GERI ALMA: her duvarin ayri salteri var (GBC > Güvenlik > Duvarlar).
 * Bir sey bozulursa o duvari kapat, digerleri calismaya devam eder.
 */

if ( ! function_exists( 'gbc_duvar_ayar' ) ) {
	function gbc_duvar_ayar() {
		static $a = null;
		if ( null !== $a ) { return $a; }
		$varsayilan = array(
			'hsts'     => '1',   /* HSTS'i alt alan adlarini kapsayacak sekilde tamamla */
			'csp'      => '1',   /* CSP'ye frame-ancestors / object-src / base-uri ekle */
			'surum'    => '1',   /* X-Powered-By ve benzeri surum sizintilarini kaldir */
			'duzenle'  => '1',   /* Panelden tema/eklenti kodu duzenlemeyi kapat */
			'preload'  => '',    /* HSTS preload: ancak butun alt alan adlarin HTTPS ise */
		);
		$kayit = get_option( 'gbc_duvar', array() );
		if ( ! is_array( $kayit ) ) { $kayit = array(); }
		$a = array_merge( $varsayilan, array_intersect_key( $kayit, $varsayilan ) );
		return $a;
	}
}

/* ---------- 1) Panelden kod duzenlemeyi kapat ----------
   Sabit, yonetici menusu kurulmadan once tanimlanmis oluyor; WordPress
   editor sayfalarini hic acmiyor. Maliyeti sifir. */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	$gbc_duvar_ayar_ilk = gbc_duvar_ayar();
	if ( '1' === $gbc_duvar_ayar_ilk['duzenle'] ) {
		define( 'DISALLOW_FILE_EDIT', true );
	}
	unset( $gbc_duvar_ayar_ilk );
}

/* ---------- 2) Basliklar ---------- */
if ( ! function_exists( 'gbc_duvar_basliklar' ) ) {
	function gbc_duvar_basliklar() {

		if ( is_admin() ) { return; }

		$a = gbc_duvar_ayar();

		/* Surum sizintisi: PHP kendi basligini kendi basiyor, geri aliyoruz. */
		if ( '1' === $a['surum'] && ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}

		if ( ! is_ssl() ) { return; }

		/* HSTS: 10-guvenlik.php max-age basiyor ama alt alan adlarini
		   kapsamiyordu. replace=true ile tamamlanmis halini koyuyoruz. */
		if ( '1' === $a['hsts'] && ! headers_sent() ) {
			$hsts = 'max-age=31536000; includeSubDomains';
			if ( '1' === $a['preload'] ) {
				$hsts .= '; preload';
			}
			header( 'Strict-Transport-Security: ' . $hsts, true );
		}

		/* CSP: yalniz KIRMAYAN yonergeler.
		   script-src / style-src bilerek YOK — onlar sitedeki her satir ici
		   betigi ve LiteSpeed'in birlestirdigi dosyalari kirar. Buradakiler
		   hicbir icerigi engellemez, yalnizca kotuye kullanimi kapatir:
		     frame-ancestors : baskasinin seni cerceveye almasini engeller
		     object-src      : Flash/applet gibi eski gomme turlerini kapatir
		     base-uri        : enjekte edilen <base> etiketiyle butun
		                       baglantilarin kacirilmasini engeller

		   form-action BILEREK YOK: sitede disari gonderen bir form olursa
		   (bulten kaydi, dis arama kutusu) sessizce kirar. Kazanci kucuk,
		   riski gercek. */
		if ( '1' === $a['csp'] && ! headers_sent() ) {
			header(
				"Content-Security-Policy: upgrade-insecure-requests; "
				. "frame-ancestors 'self'; "
				. "object-src 'none'; "
				. "base-uri 'self'",
				true
			);
		}
	}
	add_action( 'send_headers', 'gbc_duvar_basliklar', 99 );
}
