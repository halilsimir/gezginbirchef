<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · 55 · Uçuş Widget Perdesi (Skyscanner)
 * ---------------------------------------------------------------------------
 * NEDEN VAR (28 Eylul 2026, olculdu):
 *   Gezi rehberi sayfasinda JS agirligi 2,5 MB; diger butun sablonlarda 819 KB.
 *   Aradaki farkin neredeyse tamami TEK dosya:
 *     https://widgets.skyscanner.net/widget-server/js/loader.js  = 1,68 MB
 *   PageSpeed olcumu: mobil puan 38, FCP 13,3 sn, LCP 17,9 sn,
 *   "Reduce unused JavaScript 5,4 sn". Sayfa bu dosyayi indirmeden
 *   kendini toparlayamiyor.
 *
 * NE YAPAR:
 *   Icerikte gecen Skyscanner betik etiketini cikarir, yerine kucuk bir
 *   yukleyici koyar. Betik ancak widget ekrana YAKLASINCA (600 px kala)
 *   ya da kullanici ilk kez dokununca/kaydirinca indirilir. Widget aynen
 *   calisir, ortaklik baglantisi bozulmaz; yalniz sayfa acilisinda inmez.
 *
 * GERI ALMA: bu dosyayi modul listesinden cikarmak ya da eklentide
 *   "Kapat" isaretlemek yeter; betik etiketi yine icerikte basilir.
 *
 * OLCUM: her calistiginda gbc_sky_son secenegine kac etiket ertelendigini
 *   yazar (saatte bir). Perde gercekten devreye girdi mi, tahminle degil
 *   bu kayitla anlasilir.
 */

if ( ! defined( 'GBC_SKY_SON' ) ) { define( 'GBC_SKY_SON', 'gbc_sky_son' ); }

if ( ! function_exists( 'gbc_sky_perde' ) ) {
function gbc_sky_perde( $html ) {

	if ( ! is_string( $html ) || '' === $html ) { return $html; }
	if ( is_admin() || is_feed() ) { return $html; }
	if ( false === stripos( $html, 'skyscanner.net' ) ) { return $html; }

	$adet = 0;
	$kaynaklar = array();

	$html = preg_replace_callback(
		'#<script\b[^>]*\bsrc\s*=\s*["\']([^"\']*widgets\.skyscanner\.net[^"\']*)["\'][^>]*>\s*</script>#i',
		static function ( $m ) use ( &$adet, &$kaynaklar ) {
			$adet++;
			$kaynaklar[] = $m[1];
			return '<!-- gbc-ucus-perde -->';
		},
		$html
	);

	if ( ! $adet ) { return $html; }

	$json = wp_json_encode( array_values( array_unique( $kaynaklar ) ) );

	$html .= '<script id="gbc-ucus-perde">(function(){'
		. 'if(window.__gbcSkyPerde)return;window.__gbcSkyPerde=true;'
		. 'var S=' . $json . ',yuklendi=false;'
		. 'function yukle(){if(yuklendi)return;yuklendi=true;'
		. 'S.forEach(function(u){var s=document.createElement("script");s.src=u;s.async=true;document.body.appendChild(s);});}'
		. 'function kur(){'
		. 'var hedef=document.querySelectorAll("[data-skyscanner-widget]");'
		. 'if(hedef.length&&"IntersectionObserver" in window){'
		. 'var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){io.disconnect();yukle();}});},{rootMargin:"600px 0px"});'
		. 'hedef.forEach(function(h){io.observe(h);});'
		. '}else{'
		/* Widget kabi bulunamadiysa: ilk kullanici hareketinde yukle. Sayfa
		   acilisini yine bloklamaz, widget da kaybolmaz. */
		. 'var b=function(){yukle();["scroll","touchstart","mousemove","keydown"].forEach(function(t){window.removeEventListener(t,b);});};'
		. '["scroll","touchstart","mousemove","keydown"].forEach(function(t){window.addEventListener(t,b,{passive:true,once:true});});'
		. 'setTimeout(yukle,6000);'
		. '}}'
		. 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",kur);else kur();'
		. '})();</script>';

	/* Olcum kaydi — saatte bir, fazlasi bosuna yazma olur. */
	$son = get_option( GBC_SKY_SON, array() );
	if ( ! is_array( $son ) || empty( $son['zaman'] ) || ( time() - (int) $son['zaman'] ) > HOUR_IN_SECONDS ) {
		update_option( GBC_SKY_SON, array(
			'zaman' => time(),
			'adet'  => $adet,
			'url'   => esc_url_raw( home_url( add_query_arg( array() ) ) ),
		), false );
	}

	return $html;
}
add_filter( 'the_content', 'gbc_sky_perde', 25 );
}
