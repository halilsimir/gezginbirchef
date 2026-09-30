<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Gutenberg blok CSS'ini gereksiz yerde yükleme.
 *
 * NEDEN
 * -----
 * 29-30 Eyl 2026'da canli olculdu. Atina sayfasinin birlesik CSS'i
 * 329.703 bayt (acilmis):
 *
 *     bizim (gz-/gbc-/v1-/tr-)   128.149  %39
 *     tema + WordPress           201.555  %61
 *
 * O 201 KB'nin buyuk kismi Astra DEGIL. Kaynak dosyalar tek tek olculdu:
 *
 *     WP blok kitapligi  140.826 bayt · 490 sinif · 481'i olu (%98)
 *     Astra ana           44.905 bayt · 255 sinif · 179'u olu (%70)
 *     Astra style.css      4.571 bayt
 *
 * Yani en buyuk olu yigin WordPress'in kendi blok kitapligi. Sebebi belli:
 * sitedeki 1048 yazinin GOVDESI yalniz iki kisa koddan ibaret
 * ([wpcode id="…"]), gercek icerik ACF'te. Gutenberg blok sinifi hicbir
 * yerde basilmiyor — Atina sayfasinin HTML'inde tek bir wp-block-*,
 * has-*-color, is-style-*, align* sinifi yok.
 *
 * GUVENLIK DENETIMI — silmeden once soruldu
 * -----------------------------------------
 * global-styles'in urettigi --wp--preset-* degiskenlerine BASKA biri
 * dayaniyor olabilirdi; o zaman kaldirmak Astra'nin renklerini bozardi.
 * Birlesik CSS tarandi: degiskenin 81 kullaniminin 81'i de blok
 * kitapliginin KENDI seceleri icinde (.has-black-color, .has-huge-font-size
 * gibi). Astra'nin ve bizim hicbir kuralimiz bu degiskenleri kullanmiyor.
 * Yani blok CSS'i gittiginde onunla birlikte giden tek sey yine kendisi.
 *
 * KURAL — korkak tarafta duruyoruz
 * --------------------------------
 * Sayfa blok CSS'ine ihtiyac duyuyor sayilir; ancak asagidakilerin HICBIRI
 * yoksa kaldirilir. Suphede kalirsak BIRAKIYORUZ. Yanlis kaldirmanin
 * bedeli bozuk sayfa; yanlis birakmanin bedeli birkac KB.
 *
 * 30 Eyl 2026 · v1.24.0
 */

define( 'GBC_BLOK_KAPALI', 'gbc_blok_css_kapali' );

/** Kaldirilacak kayitlar. */
function gbc_blok_kuyruklar() {
	return array(
		'wp-block-library',
		'wp-block-library-theme',
		'global-styles',
		'classic-theme-styles',
		'wp-block-library-inline-css',
	);
}

/**
 * Bir govde blok CSS'i gerektiriyor mu?
 *
 * Iki ayri sinama; biri bile "evet" derse birakiyoruz.
 *
 * 1) Metin izi: HTML'de blok sinifi ya da klasik editor hizalama sinifi.
 * 2) Blok agaci: core/shortcode disinda ADI OLAN bir blok varsa.
 *    ([wpcode] kisa kodlari core/shortcode olarak sarili; onlar sayilmaz.)
 *
 * @param string $govde post_content.
 * @return bool
 */
function gbc_blok_gerekli_mi( $govde ) {
	$govde = (string) $govde;
	if ( '' === trim( $govde ) ) { return false; }

	/* 1) Metin izi. */
	$izler = '/\b(?:'
		. 'wp-block-[a-z0-9-]+'
		. '|has-[a-z0-9-]+-(?:color|background-color|font-size|gradient-background)'
		. '|is-style-[a-z0-9-]+'
		. '|align(?:left|center|right|wide|full)'
		. '|wp-caption'
		. '|wp-element-button'
		. '|wp-image-\d+'
		. '|is-layout-[a-z]+'
		. '|has-text-align-[a-z]+'
		. ')\b/i';
	if ( preg_match( $izler, $govde ) ) { return true; }

	/* 2) Blok agaci. */
	if ( function_exists( 'parse_blocks' ) && false !== strpos( $govde, '<!-- wp:' ) ) {
		foreach ( (array) parse_blocks( $govde ) as $b ) {
			$ad = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
			if ( '' === $ad ) { continue; }                 /* klasik parca */
			if ( 'core/shortcode' === $ad ) { continue; }   /* [wpcode …] */
			if ( 'core/html' === $ad ) { return true; }
			return true;
		}
	}

	return false;
}

/**
 * Bu istekte blok CSS'i kaldirilsin mi?
 *
 * Tek bir yazi/sayfa goruntulenmiyorsa (arsiv, arama, 404) govde yok;
 * o durumda da gerekmiyor. Ama yonetici ekraninda, blok duzenleyicide,
 * REST'te ve besleme (feed) isteginde ASLA dokunmuyoruz.
 */
function gbc_blok_kaldirilsin_mi() {
	if ( is_admin() ) { return false; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
	if ( function_exists( 'is_feed' ) && is_feed() ) { return false; }
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) { return false; }
	if ( get_option( GBC_BLOK_KAPALI ) ) { return false; }

	$gerekli = false;
	if ( function_exists( 'is_singular' ) && is_singular() ) {
		$p = get_post();
		$gerekli = $p ? gbc_blok_gerekli_mi( $p->post_content ) : true;  /* govde okunamadiysa BIRAK */
	}

	/* Suzgec: bir modul ya da sablon "bana lazim" diyebilsin. */
	$gerekli = (bool) apply_filters( 'gbc_blok_css_gerekli', $gerekli );

	return ! $gerekli;
}

add_action( 'wp_enqueue_scripts', 'gbc_blok_css_kaldir', 100 );
function gbc_blok_css_kaldir() {
	if ( ! gbc_blok_kaldirilsin_mi() ) { return; }
	foreach ( gbc_blok_kuyruklar() as $k ) {
		wp_dequeue_style( $k );
		wp_deregister_style( $k );
	}
}

/* global-styles satir ici de basilabiliyor; o ayri bir kanca. */
add_action( 'init', 'gbc_blok_satir_ici_kapat', 20 );
function gbc_blok_satir_ici_kapat() {
	if ( is_admin() ) { return; }
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	/* Not: yalniz KANCAYI kaldiriyoruz. Sayfa blok CSS'ine ihtiyac
	   duyuyorsa asagidaki geri ekleme calisir. */
	add_action( 'wp_enqueue_scripts', 'gbc_blok_satir_ici_geri', 9 );
}
function gbc_blok_satir_ici_geri() {
	if ( gbc_blok_kaldirilsin_mi() ) { return; }
	if ( function_exists( 'wp_enqueue_global_styles' ) ) { wp_enqueue_global_styles(); }
}
