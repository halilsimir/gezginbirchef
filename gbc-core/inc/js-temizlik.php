<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — ziyaretçi tarafında gereksiz JavaScript.
 *
 * jQuery Migrate
 * --------------
 * WordPress, jQuery 3.7.1 ile birlikte jquery-migrate 3.4.1'i de yukluyor.
 * Migrate'in tek isi, jQuery'nin ESKI surumlerinden kaldirilmis API'leri
 * geri getirmek ve kullanildiklarinda konsola uyari yazmak. Hicbir eski
 * API cagrilmiyorsa tamamen olu yuktur.
 *
 * NASIL DOGRULANDI (30 Eyl 2026, canli)
 *   Migrate kendi uyarilarini jQuery.migrateWarnings dizisinde biriktirir.
 *   Iki sablonda olculdu:
 *     /atina-gezi-rehberi/  -> 0 uyari
 *     /atina-rivierasi/     -> 21 dugme + 2 akordeon tiklandiktan sonra 0 uyari
 *   Yani sayfa yuklenirken de, etkilesimde de kullanilmiyor.
 *
 * NEDEN YINE DE TEMKINLI
 *   Uyari ancak eski API GERCEKTEN cagrilinca olusur. Hic tiklanmamis bir
 *   yol kalmis olabilir. Bu yuzden:
 *     · Yalniz ZIYARETCI tarafinda kaldiriliyor; yonetici ekraninda,
 *       blok duzenleyicide ve giris yapmis kullanicida duruyor.
 *     · gbc_js_migrate_kapali secenegi ya da gbc_js_migrate_birak suzgeci
 *       ile tek adimda geri aliniyor.
 *   Kazanc kucuk (sikistirilmis ~5 KB); amac hiz degil, olu kodun gitmesi.
 *
 * 30 Eyl 2026 · v1.26.0
 */

define( 'GBC_JS_MIGRATE_KAPALI', 'gbc_js_migrate_kapali' );

/**
 * Bu istekte jQuery Migrate kaldırılsın mı?
 */
function gbc_js_migrate_kaldirilsin_mi() {
	if ( is_admin() ) { return false; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) { return false; }
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) { return false; }
	if ( get_option( GBC_JS_MIGRATE_KAPALI ) ) { return false; }

	/* Bir modul "bana lazim" diyebilsin. */
	if ( apply_filters( 'gbc_js_migrate_birak', false ) ) { return false; }

	return true;
}

add_action( 'wp_default_scripts', 'gbc_js_migrate_ayikla' );
function gbc_js_migrate_ayikla( $scripts ) {
	if ( ! gbc_js_migrate_kaldirilsin_mi() ) { return; }
	if ( empty( $scripts->registered['jquery'] ) ) { return; }

	$jq = $scripts->registered['jquery'];
	if ( empty( $jq->deps ) || ! is_array( $jq->deps ) ) { return; }

	/* jquery paketi = jquery-core + jquery-migrate. Yalniz migrate'i cikar;
	   jquery-core'a DOKUNMA — her sey ona bagli. */
	$jq->deps = array_values( array_diff( $jq->deps, array( 'jquery-migrate' ) ) );
}
