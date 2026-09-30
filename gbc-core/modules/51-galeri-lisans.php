<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30294 — Galeri Görsel Lisansı. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 91 · Galeri Gorsel Lisansi · PHP
 * ---------------------------------------------------------------------------
 * WPCode: PHP Snippet | Auto Insert | Run Everywhere | Active
 *
 * NEDEN VAR
 * Sitedeki ONE CIKAN GORSELLER (kapaklar) AI ile uretiliyor. Umut'un kendi
 * cektigi fotograflar ICERIK icindeki kart gorselleri: gezi rehberlerindeki
 * gezilecek yer kartlari ve liste sayfalarindaki yemek/mekan kartlari.
 * Umut'un beyani (2026-09-07): "evet benim, hepsini videodan ss aldim."
 *
 * Bu yuzden lisans meta verisi (Google "Lisanslanabilir" rozeti) KAPAGA
 * DEGIL, yalnizca bu kart gorsellerine basilir. Kapak tarafi 29013'te
 * bilerek lisanssiz birakildi; sahibi olmadigimiz bir goruntude telif
 * iddia etmemek icin.
 *
 * NE YAPAR
 * the_content 999'da icerigi tarar, iki kaptaki gorselleri toplar:
 *   .gz-place-img img   -> Gezi sablonu (22607) yer/konaklama kartlari
 *   .v1-image-box img   -> Liste sablonu (23108) yemek/mekan kartlari
 *   .gz-gallery-item img -> Gezi sablonu galeri bolumu, kendi dikey fotograflarimiz
 *                           (2026-09-08 eklendi; kucuk resim degil <a href> tam boy)
 * wp_footer'da her biri icin ayri ImageObject dugumu basar.
 *
 * KURALLAR
 * - Tarifler HARIC: tarif sayfalarindaki "ne ile iyi gider" gorselleri baska
 *   tariflerin AI kapaklari, orada iddia edilecek bir sey yok. Kap siniflari
 *   orada bulunmadigi icin bu snippet kendiliginden devreye girmiyor.
 * - Sayfa basina en fazla 60 gorsel (2026-09-08: 12 idi). 12 sinirinda
 *   Budapeste pillar'inda 43 kart gorselinin ilk 12'si aliniyor, 15 yemek
 *   fotografinin tamami disarida kaliyordu. 48 dugum ~12 KB ham, gzip ~3 KB.
 * - Ayni dosya iki kez sayilmaz.
 * - Yalnizca kendi medya kutuphanemizden gelen dosyalar; disaridan gomulu
 *   gorsel atlanir.
 * - gbc_gorsel_harici = 1 isaretli postta hic calismaz (kacis kapisi).
 * - Regex yok, DOMXPath var: ters egik cizgi MCP -> REST yaziminda bozuluyor.
 *
 * TEST (yerelde, yayindan once)
 *   1) normal sayfa: 3 dugum, kart disindaki AI kapak alinmadi
 *   2) harici isaretli sayfa: cikti bos
 *   3) tarif sayfasi: cikti bos
 *   4) sinir: 60'tan fazla gorselli sayfada 60'ta duruyor
 *   5) galeri: kucuk resim degil <a href> tam boy dosya alindi, olcu yazilmadi
 *   6) kart gorselleri: width/height HTML etiketinden geldi
 *   7) baglanti: #webpage dugumunde image dizisi var, hepsi #gorsel-N'e isaret ediyor
 *   8) JSON gecerli, Turkce karakterler saglam
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_filter( 'the_content', 'gbc_gal_topla', 999 );
if ( ! function_exists( 'gbc_gal_topla' ) ) {
function gbc_gal_topla( $icerik ) {

	/* NOT (2026-09-07): in_the_loop() ve is_main_query() kosullari KALDIRILDI.
	   Ilk surumde vardilar ve snippet hicbir sayfada calismadi; olculdu:
	   icerikte gz-place-img 15 kez geciyor ve 30204 ayni kancada (the_content
	   999) calisiyordu, yani icerik goruluyordu. Sablonlar [wpcode] kisa
	   koduyla render edildigi icin dongu bayraklari guvenilir degil.
	   Cift islemeyi zaten $GLOBALS['gbc_gal_bitti'] engelliyor. */
	if ( is_admin() || is_feed() || ! is_singular( 'post' ) ) {
		return $icerik;
	}
	if ( ! empty( $GLOBALS['gbc_gal_bitti'] ) ) { return $icerik; }

	/* Ucuz on eleme: kap siniflari yoksa DOM'u hic acma. */
	$var_gz = ( strpos( $icerik, 'gz-place-img' ) !== false );
	$var_v1 = ( strpos( $icerik, 'v1-image-box' ) !== false );
	$var_gl = ( strpos( $icerik, 'gz-gallery-item' ) !== false );
	if ( ! $var_gz && ! $var_v1 && ! $var_gl ) { return $icerik; }

	$id = get_queried_object_id();
	if ( ! $id ) { return $icerik; }
	if ( get_post_meta( $id, 'gbc_gorsel_harici', true ) ) { return $icerik; }

	$GLOBALS['gbc_gal_bitti'] = 1;

	if ( ! class_exists( 'DOMDocument' ) ) { return $icerik; }

	$doc  = new DOMDocument();
	$eski = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $icerik . '</div>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $eski );

	$xp    = new DOMXPath( $doc );
	$yol   = '//*[contains(concat(" ", normalize-space(@class), " "), " gz-place-img ")]//img'
		. ' | //*[contains(concat(" ", normalize-space(@class), " "), " v1-image-box ")]//img'
		. ' | //*[contains(concat(" ", normalize-space(@class), " "), " gz-gallery-item ")]//img';
	$liste = $xp->query( $yol );
	if ( ! $liste || ! $liste->length ) { return $icerik; }

	$upload  = wp_get_upload_dir();
	$kok     = isset( $upload['baseurl'] ) ? $upload['baseurl'] : '';
	$bulunan = array();

	foreach ( $liste as $img ) {
		if ( count( $bulunan ) >= 60 ) { break; }

		/* LiteSpeed lazy load gercek adresi data-src'ye tasiyor. */
		$src = $img->getAttribute( 'data-src' );
		if ( '' === $src ) { $src = $img->getAttribute( 'src' ); }
		if ( '' === $src ) { continue; }
		/* Protokol/alt alan adi farki strpos( ..., 0 ) kontrolunu bozabiliyor;
		   yol parcasi aramak daha dayanikli. */
		/* Galeride src 768px kucuk resim; tam boy dosya sarmalayan <a href> icinde.
		   Gorsel aramasi buyuk dosyayi istiyor, varsa onu tercih et. */
		$tamboy = false;
		$ust = $img->parentNode;
		if ( $ust && 'a' === strtolower( $ust->nodeName ) ) {
			$href = (string) $ust->getAttribute( 'href' );
			if ( strpos( $href, '/wp-content/uploads/' ) !== false ) { $src = $href; $tamboy = true; }
		}
		if ( strpos( $src, '/wp-content/uploads/' ) === false ) { continue; }
		if ( isset( $bulunan[ $src ] ) ) { continue; }

		/* Olcu yalnizca etiketteki dosya kullanildiginda dogru. Tam boya
		   gectiysek HTML'deki en/boy baska bir dosyaya ait, yazilmaz. */
		$en  = $tamboy ? 0 : (int) $img->getAttribute( 'width' );
		$boy = $tamboy ? 0 : (int) $img->getAttribute( 'height' );

		$bulunan[ $src ] = array(
			'alt' => trim( (string) $img->getAttribute( 'alt' ) ),
			'w'   => $en,
			'h'   => $boy,
		);
	}

	if ( $bulunan ) { $GLOBALS['gbc_gal_gorseller'] = $bulunan; }
	return $icerik;
}
}

add_action( 'wp_footer', 'gbc_gal_sema', 30 );
if ( ! function_exists( 'gbc_gal_sema' ) ) {
function gbc_gal_sema() {

	if ( empty( $GLOBALS['gbc_gal_gorseller'] ) || ! is_array( $GLOBALS['gbc_gal_gorseller'] ) ) { return; }

	$url    = get_permalink( get_queried_object_id() );
	if ( ! $url ) { return; }
	$lisans = 'https://gezginbirchef.com/gorsel-kullanim-ve-lisans/';
	$graph  = array();
	$refler = array();
	$i      = 0;

	/* ÜST SINIR — 28 Eylül 2026'da ölçüldü: /budapeste-gezi-rehberi/
	   sayfasında bu blok 33 KB tutuyordu (378 KB'lık HTML'in en büyük tek
	   parçası, ~90 görsel × ~350 bayt). Google'ın görsel lisansı özelliği
	   sayfanın ASIL görselleri için anlamlı; doksanıncı küçük görsel için
	   ödenen bedel her ziyaretçinin indirdiği 26 KB fazladan HTML.
	   Sınır süzgeçle değiştirilebilir: add_filter('gbc_gal_sema_ust_sinir', …) */
	$ust_sinir = (int) apply_filters( 'gbc_gal_sema_ust_sinir', 20 );

	foreach ( $GLOBALS['gbc_gal_gorseller'] as $src => $bilgi ) {
		if ( $ust_sinir > 0 && $i >= $ust_sinir ) { break; }
		/* Eski surumde deger duz alt metniydi; iki bicimi de kabul et. */
		$alt = is_array( $bilgi ) ? (string) $bilgi['alt'] : (string) $bilgi;
		$en  = ( is_array( $bilgi ) && ! empty( $bilgi['w'] ) ) ? (int) $bilgi['w'] : 0;
		$boy = ( is_array( $bilgi ) && ! empty( $bilgi['h'] ) ) ? (int) $bilgi['h'] : 0;
		$i++;
		$dugum = array(
			'@type'              => 'ImageObject',
			'@id'                => $url . '#gorsel-' . $i,
			/* 'url' kaldirildi (2026-09-08): contentUrl ile birebir ayniydi,
			   Google lisans ozelligi contentUrl'e bakiyor. Dugum basina ~85 bayt. */
			'contentUrl'         => $src,
			'license'            => $lisans,
			'acquireLicensePage' => $lisans . '#lisans-al',
			'creditText'         => 'Halil Şımır / Gezginbirchef',
			/* Her dugumde Person nesnesini tekrarlamak yerine 29013'un sitenin
			   her sayfasinda bastigi Person dugumune referans. ~15 bayt daha
			   uzun ama gorseli gercek yazar varligina bagliyor. */
			'creator'            => array( '@id' => 'https://gezginbirchef.com/halil-simir/#person' ),
			/* 'copyrightNotice' kaldirildi (28 Eylul 2026): creditText ve
			   creator zaten hak sahibini soyluyor, ucuncu tekrar. ~38 bayt
			   × gorsel sayisi. Google lisans ozelligi contentUrl + license
			   + acquireLicensePage istiyor; ucu de duruyor.
			   'isPartOf' kaldirildi (2026-09-08): #webpage dugumu zaten image
			   dizisiyle bu dugumlere isaret ediyor, ters yon tekrar. ~60 bayt. */
		);
		/* 'name' kaldirildi (2026-09-08): caption ile birebir ayni metindi. */
		if ( '' !== $alt ) { $dugum['caption'] = $alt; }
		if ( $en > 0 && $boy > 0 ) { $dugum['width'] = $en; $dugum['height'] = $boy; }
		$graph[] = $dugum;
		$refler[] = array( '@id' => $url . '#gorsel-' . $i );
	}

	/* Fotograflari sayfa varligina bagla. 29738'in kullandigi desenin aynisi:
	   ayni @id ile kismi dugum gonderilir, JSON-LD tuketicileri birlestirir.
	   #webpage her sayfada var (29013 ve 29738 ikisi de basiyor). #article ve
	   #destination icerik turune gore degistigi icin onlara baglanmiyor;
	   olmayan bir @id'ye baglamak tipsiz hayalet dugum uretir. */
	if ( $refler ) {
		$graph[] = array(
			'@type' => 'WebPage',
			'@id'   => $url . '#webpage',
			'image' => $refler,
		);
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		)
		. '</script>' . "\n";
}
}
