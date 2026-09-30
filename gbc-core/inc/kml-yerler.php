<?php
/**
 * GBC Core · Haritadan şema durakları (v1.47.4, 30 Eylül 2026)
 *
 * Halil: "Hepsi varsa yazılacak, yoksa yazılmayacak; kurala göre direkt gelecek."
 * Gezi sayfasının kendi Google Haritalarım haritası (iframe'deki mid=...) okunur.
 * Haritadaki her iğne (adı + koordinatı olan) şemaya yer olarak girer:
 *   TouristDestination.includesAttraction.
 * Kurallar:
 *   - İğne açıklamasında rehberin kendi bölüm bağlantısı (…/sayfa/#bolum) varsa yer o bölüme bağlanır.
 *   - Harita yoksa ya da okunamazsa hiçbir şey yazılmaz (uydurma yok).
 *   - Adı ya da koordinatı eksik iğne atlanır.
 *   - Tür, iğnenin katman (klasör) adından ve kendi adından çıkarılır: yemek/restoran → FoodEstablishment,
 *     pastane → Bakery, dondurma → IceCreamShop, otel → LodgingBusiness, plaj/sahil → Beach,
 *     müze → Museum, katedral/kilise/manastır/kale/saray → Landmark, geri kalanı TouristAttraction.
 *   - Elle doldurulmuş gbc_schema_yerler her zaman kazanır; haritadan gelen liste yalnız o alan boşsa kullanılır.
 *   - Harita 7 günde bir yeniden okunur; "yeniden kontrol et" hemen okur.
 * Okunan liste _gbc_kml_yerler metasında durur. Değişince sayfanın önbelleği temizlenir.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Sayfada gömülü Google Haritalarım kimliği (mid). Yoksa ''. */
function gbc_kml_mid( $pid ) {
	$pid  = (int) $pid;
	$ara  = array( (string) get_post_field( 'post_content', $pid ) );
	$meta = get_post_meta( $pid );
	if ( is_array( $meta ) ) {
		foreach ( $meta as $k => $v ) {
			if ( '_' === substr( (string) $k, 0, 1 ) ) { continue; }
			foreach ( (array) $v as $x ) { if ( is_string( $x ) && false !== strpos( $x, 'maps/d/' ) ) { $ara[] = $x; } }
		}
	}
	foreach ( $ara as $s ) {
		if ( preg_match( '~google\.[a-z.]+/maps/d/[^"\'\s<>]*?[?&](?:amp;)?mid=([A-Za-z0-9_-]{15,})~', $s, $m ) ) { return $m[1]; }
	}
	return '';
}

/** Katman ve iğne adından şema türü. */
function gbc_kml_tur( $ad, $katman ) {
	$n = function_exists( 'mb_strtolower' ) ? mb_strtolower( $ad . ' | ' . $katman, 'UTF-8' ) : strtolower( $ad . ' | ' . $katman );
	$kural = array(
		'Bakery'            => array( 'pasticceria', 'pastane', 'bakery', 'fırın', 'patisserie', 'boulangerie', 'panificio' ),
		'IceCreamShop'      => array( 'gelato', 'gelateria', 'dondurma', 'ice cream' ),
		'LodgingBusiness'   => array( 'otel', 'hotel', 'konaklama', 'pansiyon', 'hostel', 'b&b', 'apart' ),
		'FoodEstablishment' => array( 'restoran', 'restaurant', 'trattoria', 'osteria', 'pizzeria', 'lokanta', 'yemek', 'yeme', 'içme', 'lezzet', 'cafe', 'kafe', 'caffè', 'bistro', 'taverna', 'meyhane', 'cantina', 'cantinaccia', 'bar ' ),
		'Beach'             => array( 'plaj', 'beach', 'spiaggia', 'kumsal', 'sahil platform', 'lido' ),
		'Museum'            => array( 'müze', 'museum', 'museo', 'musée' ),
		'Landmark'          => array( 'katedral', 'kilise', 'manastır', 'cathedral', 'duomo', 'basilica', 'kale', 'castle', 'castello', 'saray', 'palazzo', 'cami', 'tapınak', 'chiostro' ),
	);
	foreach ( $kural as $tur => $liste ) {
		foreach ( $liste as $w ) { if ( false !== strpos( $n . ' ', $w ) ) { return $tur; } }
	}
	return 'TouristAttraction';
}

/**
 * KML metnini satırlara çevirir: "Tur | Ad | enlem,boylam".
 * Adı ya da geçerli koordinatı olmayan iğne atlanır. Aynı ad iki kez girmez.
 */
function gbc_kml_ayikla( $kml, $sayfa_url = '', $eski_sluglar = array() ) {
	/* v1.47.10: haritadaki bağlantı sayfanın eski adresine (ör. /sorrento/ → /sorrento-gezi-rehberi/) gidebiliyor; eski adlar da bu sayfadır. */
	$eski_sluglar = array_values( array_filter( array_map( 'strval', (array) $eski_sluglar ) ) );
	$sayfa_yolu = '';
	if ( '' !== $sayfa_url ) { $sp = wp_parse_url( $sayfa_url ); $sayfa_yolu = isset( $sp['path'] ) ? untrailingslashit( $sp['path'] ) : ''; }
	$satir = array();
	$gor   = array();
	if ( ! is_string( $kml ) || false === stripos( $kml, '<Placemark' ) ) { return $satir; }
	/* Katmanlar (Folder) sırayla; klasörsüz haritada tek katman. */
	$katmanlar = array();
	if ( preg_match_all( '~<Folder\b.*?</Folder>~is', $kml, $fm ) ) {
		foreach ( $fm[0] as $f ) {
			$fad = preg_match( '~<name>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</name>~is', preg_replace( '~<Placemark\b.*?</Placemark>~is', '', $f ), $x ) ? $x[1] : '';
			$katmanlar[] = array( trim( html_entity_decode( strip_tags( $fad ), ENT_QUOTES, 'UTF-8' ) ), $f );
		}
	} else {
		/* Klasörsüz dosya (katman başına ayrı KML): katman adı belgenin adıdır. */
		$dad = preg_match( '~<Document\b[^>]*>\s*<name>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</name>~is', $kml, $x ) ? $x[1] : '';
		$katmanlar[] = array( trim( html_entity_decode( strip_tags( $dad ), ENT_QUOTES, 'UTF-8' ) ), $kml );
	}
	foreach ( $katmanlar as $k ) {
		if ( ! preg_match_all( '~<Placemark\b.*?</Placemark>~is', $k[1], $pm ) ) { continue; }
		foreach ( $pm[0] as $p ) {
			$ad = preg_match( '~<name>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</name>~is', $p, $x ) ? $x[1] : '';
			$ad = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $ad ), ENT_QUOTES, 'UTF-8' ) ) );
			$ad = str_replace( '|', '/', $ad );
			if ( '' === $ad ) { continue; }
			/* Yalnız nokta iğneleri: çizgi/alan (rota) şemaya yer olarak girmez. */
			if ( ! preg_match( '~<Point\b.*?<coordinates>\s*([-0-9.]+)\s*,\s*([-0-9.]+)~is', $p, $c ) ) { continue; }
			$lng = (float) $c[1]; $lat = (float) $c[2];
			if ( abs( $lat ) > 90 || abs( $lng ) > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) { continue; }
			$anah = function_exists( 'mb_strtolower' ) ? mb_strtolower( $ad, 'UTF-8' ) : strtolower( $ad );
			if ( isset( $gor[ $anah ] ) ) { continue; }
			$gor[ $anah ] = 1;
			/* İğne açıklamasında rehberin bölüm bağlantısı (#çapa) varsa şemada yerin adresi o bölüm olur. */
			$capa = '';
			if ( $sayfa_yolu && preg_match_all( '~https?://[^\s"\'<>()\]]+~i', html_entity_decode( $p, ENT_QUOTES, 'UTF-8' ), $hm ) ) {
				foreach ( $hm[0] as $h ) {
					/* v1.47.8: Google bazen dış bağlantıyı yönlendirmeyle sarar (google.com/url?q=…%23bolum). */
					if ( preg_match( '~^https?://(?:www\.)?google\.[a-z.]+/url\?~i', $h ) ) {
						$qs = array(); parse_str( (string) wp_parse_url( $h, PHP_URL_QUERY ), $qs );
						if ( ! empty( $qs['q'] ) ) { $h = (string) $qs['q']; } elseif ( ! empty( $qs['url'] ) ) { $h = (string) $qs['url']; }
					}
					$h = rawurldecode( $h ); /* v1.47.7: Google'ın KML'i bağlantıyı düz metin veriyor (href yok): "Rehber: … (https://…/#bolum)" */
					$hp = wp_parse_url( $h );
					/* v1.47.9: yol birebir tutmasa da son parça (slug) aynıysa bu sayfadır; çapa ayrıca sayfadaki id'lerle doğrulanır. */
					$hyol = isset( $hp['path'] ) ? untrailingslashit( $hp['path'] ) : '';
					$ayni = ( $hyol === $sayfa_yolu ) || ( '' !== $hyol && basename( $hyol ) === basename( $sayfa_yolu ) )
						|| ( '' !== $hyol && in_array( basename( $hyol ), $eski_sluglar, true ) );
					if ( ! empty( $hp['fragment'] ) && $ayni ) { $capa = '#' . preg_replace( '/[^A-Za-z0-9_-]/', '', $hp['fragment'] ); break; }
				}
			}
			$satir[] = gbc_kml_tur( $ad, $k[0] ) . ' | ' . $ad . ' | ' . round( $lat, 6 ) . ',' . round( $lng, 6 ) . ( '#' !== $capa && '' !== $capa ? ' | ' . $capa : '' );
		}
	}
	return $satir;
}

/**
 * Sayfanın haritasını okur ve _gbc_kml_yerler'e yazar. 7 gün önbellek; $taze=true hemen okur.
 * Döner: array( 'mid', 'zaman', 'satir' => array, 'hata' ).
 */
function gbc_kml_guncelle( $pid, $taze = false, $html = '' ) {
	$pid  = (int) $pid;
	$eski = get_post_meta( $pid, '_gbc_kml_yerler', true );
	$eski = is_array( $eski ) ? $eski : array();
	$mid  = gbc_kml_mid( $pid );
	if ( '' === $mid ) {
		if ( $eski ) { delete_post_meta( $pid, '_gbc_kml_yerler' ); }
		return array( 'mid' => '', 'zaman' => time(), 'satir' => array(), 'hata' => 'harita yok' );
	}
	if ( ! $taze && ! empty( $eski['zaman'] ) && $mid === ( isset( $eski['mid'] ) ? $eski['mid'] : '' ) && ( time() - (int) $eski['zaman'] ) < 7 * DAY_IN_SECONDS ) {
		return $eski;
	}
	$yanit = wp_remote_get( 'https://www.google.com/maps/d/kml?mid=' . rawurlencode( $mid ) . '&forcekml=1', array( 'timeout' => 15, 'redirection' => 3 ) );
	$kod   = is_wp_error( $yanit ) ? 0 : (int) wp_remote_retrieve_response_code( $yanit );
	if ( 200 !== $kod ) {
		/* Okunamadıysa eskisi durur; bir saat sonra yeniden denenir. */
		$eski['hata']  = is_wp_error( $yanit ) ? $yanit->get_error_message() : 'HTTP ' . $kod;
		$eski['mid']   = $mid;
		$eski['zaman'] = time() - 7 * DAY_IN_SECONDS + HOUR_IN_SECONDS;
		if ( ! isset( $eski['satir'] ) ) { $eski['satir'] = array(); }
		update_post_meta( $pid, '_gbc_kml_yerler', $eski );
		return $eski;
	}
	$satir = gbc_kml_ayikla( (string) wp_remote_retrieve_body( $yanit ), (string) get_permalink( $pid ), (array) get_post_meta( $pid, '_wp_old_slug' ) );
	/* Çapa sayfada gerçekten yoksa (başlık değişmiş) atılır; şemada kırık bölüm adresi olmaz. */
	$tani = array( 'capa_kml' => 0, 'capa_sayfada' => 0, 'id_sayisi' => 0, 'ornek' => '', 'yol' => (string) wp_parse_url( (string) get_permalink( $pid ), PHP_URL_PATH ) );
	foreach ( $satir as $l ) { if ( 4 === count( explode( ' | ', $l ) ) ) { $tani['capa_kml']++; } }
	if ( preg_match( '~<Placemark\b.*?<description>(.*?)</description>~is', (string) wp_remote_retrieve_body( $yanit ), $om ) ) { $tani['ornek'] = mb_substr( str_replace( array( '<![CDATA[', ']]>' ), '', $om[1] ), 0, 240 ); }
	$idler = array();
	if ( is_string( $html ) && '' !== $html && preg_match_all( '~\sid=["\']([^"\']+)["\']~i', $html, $im ) ) { $idler = array_flip( $im[1] ); }
	$tani['id_sayisi'] = count( $idler );
	foreach ( $satir as $i => $l ) {
		$p = explode( ' | ', $l );
		/* Sayfanın id'leri okunamadıysa (boş HTML) çapa atılmaz; doğrulanamayan durum ceza sayılmaz. */
		if ( ! $idler ) { continue; }
		if ( isset( $p[3] ) && ! isset( $idler[ ltrim( $p[3], '#' ) ] ) ) { $satir[ $i ] = implode( ' | ', array_slice( $p, 0, 3 ) ); }
	}
	foreach ( $satir as $l ) { if ( 4 === count( explode( ' | ', $l ) ) ) { $tani['capa_sayfada']++; } }
	$yeni = array( 'mid' => $mid, 'zaman' => time(), 'satir' => $satir, 'hata' => '', 'tani' => $tani );
	update_post_meta( $pid, '_gbc_kml_yerler', $yeni );
	$degisti = ( isset( $eski['satir'] ) ? (array) $eski['satir'] : array() ) !== $yeni['satir'];
	if ( $degisti && '' === trim( (string) get_post_meta( $pid, 'gbc_schema_yerler', true ) ) ) {
		do_action( 'litespeed_purge_post', $pid );
	}
	return $yeni;
}

/** Şema motorunun kullanacağı metin: elle yazılan varsa o, yoksa haritadan gelen. */
function gbc_kml_sema_metni( $pid ) {
	$elle = (string) get_post_meta( (int) $pid, 'gbc_schema_yerler', true );
	if ( '' !== trim( $elle ) ) { return $elle; }
	$k = get_post_meta( (int) $pid, '_gbc_kml_yerler', true );
	return ( is_array( $k ) && ! empty( $k['satir'] ) ) ? implode( "\n", (array) $k['satir'] ) : '';
}
