<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · 56 · Alt Linkler — "buradan şunlara da bakabilirsin" kutusu.
 *
 * NEDEN
 * -----
 * tarif_alt_linkler alani Tarif sablonunda (snippet 24751) tanimli ve
 * ayristiricisi da orada duruyor, ama BASIM blogu yorum satirina alinmis:
 *   "Alt linkler üstteki .tr-meta-row içine taşındı (H1 altı)"
 * Yani baslik altinda kucuk bir satira sikismis. 30 Eyl 2026 taramasi:
 * 756 tarifin 158'inde alan bos, dolu olanlarda da neredeyse gorunmuyor.
 *
 * Bu modul alani KENDI basiyor: iceriğin sonunda, Ilgili Yazilar'in hemen
 * ustunde. Snippet'e DOKUNULMUYOR — oradaki eski blok zaten yorumda.
 *
 * FORMAT — eskisiyle uyumlu, bir alan daha kabul ediyor
 *   Ad / https://...                       (eski, calismaya devam eder)
 *   Ad / https://... / Aciklama cumlesi    (yeni, ucuncu parca istege bagli)
 * Aciklama, Halil'in "acıklama yapıp buradan sunları gorebilirsin" dedigi
 * sey; eski iki parcali format bunu tasiyamiyordu.
 *
 * DUPLIKE KURALI — Halil'in sart kostugu sey
 * ------------------------------------------
 * Sayfada o adrese ZATEN link varsa (metin ici, eslikciler, silo kutusu)
 * ikincisi basilmaz. Karsilastirma normalize edilmis adres uzerinden:
 * protokol, www, sondaki egik cizgi ve sorgu dizesi atilir.
 *
 * 30 Eyl 2026 · v1.29.0
 */

define( 'GBC_ALT_TAVAN', 6 );      /* bir sayfada en fazla bu kadar alt link */
define( 'GBC_ALT_ONBELLEK', 43200 ); /* 12 saat: adres canli mi sonucu bu kadar saklanir */

/**
 * Adres GERÇEKTEN yaşıyor mu?
 *
 * Halil'in sarti: "canli url varsa baglamak lazim". Olu bir adrese link
 * basmak hem okuyucuyu 404'e gonderir hem de bugun /atina/ ornekinde
 * gordugumuz gibi fark edilmeden aylarca oyle kalir.
 *
 * MALIYET: bu kontrol HER SAYFA GORUNTULEMESINDE calisacak, o yuzden
 * once ucuz yol denenir:
 *   1) Kendi sitemizin adresi ise url_to_postid() — HTTP istegi YOK,
 *      tek veritabani sorgusu. Yazi bulunursa durumu 'publish' mi bakilir.
 *   2) Cozulemezse (etiket/kategori arsivi olabilir) HEAD istegi atilir
 *      ve sonuc 12 saat saklanir; ayni adres icin gunde en fazla iki istek.
 *   3) Dis adresler: yalniz KESIN olu (404/410) olanlar elenir. Zaman
 *      asimi ya da ag hatasi "olu" sayilmaz — dogrulayamamak, olu olmak
 *      degildir; yanlislikla saglam linki dusurmeyelim.
 *
 * @param string $url
 * @return bool
 */
function gbc_alt_canli_mi( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) { return false; }

	$anahtar = 'gbc_alt_' . md5( gbc_alt_adres_anahtar( $url ) );
	$onbellek = get_transient( $anahtar );
	if ( false !== $onbellek ) { return '1' === (string) $onbellek; }

	$ic = false !== strpos( gbc_alt_adres_anahtar( $url ), 'gezginbirchef.com' );

	/* 1) Kendi yazimiz mi? */
	if ( $ic && function_exists( 'url_to_postid' ) ) {
		$pid = (int) url_to_postid( $url );
		if ( $pid > 0 ) {
			$canli = ( 'publish' === get_post_status( $pid ) );
			set_transient( $anahtar, $canli ? '1' : '0', GBC_ALT_ONBELLEK );
			return $canli;
		}
	}

	/* 2) HEAD istegi. */
	if ( ! function_exists( 'wp_remote_head' ) ) { return true; }
	$c = wp_remote_head( $url, array( 'timeout' => 4, 'redirection' => 3 ) );

	if ( is_wp_error( $c ) ) {
		/* Dogrulanamadi — olu SAYILMAZ, ama onbelleklemeyiz ki sonra tekrar denensin. */
		return true;
	}
	$kod = (int) wp_remote_retrieve_response_code( $c );

	/* Kesin olu: 404 / 410. Diger her sey (200, 301, 403, 500...) yasiyor sayilir. */
	$canli = ! in_array( $kod, array( 404, 410 ), true );
	set_transient( $anahtar, $canli ? '1' : '0', GBC_ALT_ONBELLEK );
	return $canli;
}

/**
 * Adresi karşılaştırma için sadeleştirir.
 *
 * https://www.gezginbirchef.com/Focaccia-Tarifi/?x=1#bolum
 *   -> gezginbirchef.com/focaccia-tarifi
 */
function gbc_alt_adres_anahtar( $url ) {
	$u = trim( (string) $url );
	if ( '' === $u ) { return ''; }
	$u = preg_replace( '#\#.*$#', '', $u );      /* capa */
	$u = preg_replace( '#\?.*$#', '', $u );      /* sorgu */
	$u = preg_replace( '#^https?://#i', '', $u );
	$u = preg_replace( '#^www\.#i', '', $u );
	$u = rtrim( $u, '/' );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $u, 'UTF-8' ) : strtolower( $u );
}

/**
 * tarif_alt_linkler metnini satır satır ayrıştırır.
 *
 * @param string $ham Alan içeriği.
 * @return array ( array( 'ad', 'url', 'aciklama' ), ... )
 */
function gbc_alt_ayristir( $ham ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $ham ) as $satir ) {
		$satir = trim( $satir );
		if ( '' === $satir ) { continue; }

		/* En fazla uc parca: Ad / URL / Aciklama.
		   Adresin kendi icindeki "/" bolmeyi bozmasin diye once adresi
		   yakalayip cikariyoruz. */
		if ( ! preg_match( '#^(.*?)\s*/\s*(https?://\S+)\s*(?:/\s*(.*))?$#u', $satir, $m ) ) { continue; }

		$ad  = trim( (string) $m[1] );
		$url = trim( (string) $m[2] );
		$ack = isset( $m[3] ) ? trim( (string) $m[3] ) : '';

		if ( '' === $ad || '' === $url ) { continue; }
		$out[] = array( 'ad' => $ad, 'url' => $url, 'aciklama' => $ack );
	}
	return $out;
}

/**
 * İçerikte hâlihazırda geçen bağlantı adresleri.
 *
 * @param string $html
 * @return array anahtar => true
 */
function gbc_alt_mevcut_linkler( $html ) {
	$v = array();
	if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\']#i', (string) $html, $m ) ) {
		foreach ( $m[1] as $u ) {
			$a = gbc_alt_adres_anahtar( $u );
			if ( '' !== $a ) { $v[ $a ] = true; }
		}
	}
	return $v;
}

/**
 * Basılacak listeyi süzer: duplike yok, kendine link yok, tavan var.
 *
 * @param array  $satirlar gbc_alt_ayristir çıktısı.
 * @param string $html     Sayfanın o ana kadarki içeriği.
 * @param string $bu_url   Sayfanın kendi adresi.
 * @return array
 */
function gbc_alt_suz( $satirlar, $html, $bu_url = '' ) {
	$mevcut = gbc_alt_mevcut_linkler( $html );
	$ben    = gbc_alt_adres_anahtar( $bu_url );
	$gorulen = array();
	$out    = array();

	foreach ( (array) $satirlar as $s ) {
		$a = gbc_alt_adres_anahtar( isset( $s['url'] ) ? $s['url'] : '' );
		if ( '' === $a ) { continue; }
		if ( $a === $ben ) { continue; }              /* kendine link */
		if ( isset( $mevcut[ $a ] ) ) { continue; }   /* sayfada zaten var */
		if ( isset( $gorulen[ $a ] ) ) { continue; }  /* listenin kendi icinde tekrar */
		$gorulen[ $a ] = true;

		/* CANLI MI? Olu adres basilmaz — ama sessizce yutulmaz, sayaca yazilir. */
		if ( ! gbc_alt_canli_mi( $s['url'] ) ) {
			$GLOBALS['gbc_alt_olu'][] = $s['url'];
			continue;
		}

		$out[] = $s;
		if ( count( $out ) >= GBC_ALT_TAVAN ) { break; }
	}
	return $out;
}

/** Kutu CSS'i — bir kez basılır. */
function gbc_alt_stil() {
	static $basildi = false;
	if ( $basildi ) { return ''; }
	$basildi = true;
	return '<style id="gbc-alt-css">'
		. '.gbc-alt{margin:34px 0;padding:20px 20px 8px;border:1px solid #ECE6DF;border-radius:16px;background:#FCFAF7}'
		. '.gbc-alt-bas{display:flex;align-items:center;gap:9px;margin:0 0 14px;font-size:15px;font-weight:700;letter-spacing:.01em;color:#2B2B2B}'
		. '.gbc-alt-bas::before{content:"";width:4px;height:17px;border-radius:2px;background:var(--ast-global-color-0,#FF6210);flex:none}'
		. '.gbc-alt ul{list-style:none;margin:0;padding:0}'
		. '.gbc-alt li{margin:0 0 12px;padding:0 0 12px;border-bottom:1px solid #F0EAE3}'
		. '.gbc-alt li:last-child{border-bottom:0}'
		. '.gbc-alt a{font-weight:600;font-size:16px;line-height:1.35;color:#1F1F1F;text-decoration:none;border-bottom:2px solid transparent;transition:border-color .15s}'
		. '.gbc-alt a:hover,.gbc-alt a:focus-visible{border-bottom-color:var(--ast-global-color-0,#FF6210)}'
		. '.gbc-alt p{margin:4px 0 0;font-size:14.5px;line-height:1.55;color:#5C5C5C}'
		. '@media(max-width:600px){.gbc-alt{margin:26px 0;padding:16px 16px 6px;border-radius:14px}.gbc-alt a{font-size:15.5px}}'
		. '</style>';
}

/**
 * Kutuyu üretir.
 */
function gbc_alt_kutu( $satirlar ) {
	if ( ! $satirlar ) { return ''; }

	$h  = gbc_alt_stil();
	$h .= '<aside class="gbc-alt"><div class="gbc-alt-bas">' . esc_html__( 'Buradan devam et', 'gbc-core' ) . '</div><ul>';
	foreach ( $satirlar as $s ) {
		$dis = false !== strpos( gbc_alt_adres_anahtar( $s['url'] ), 'gezginbirchef.com' ) ? false : true;
		$h  .= '<li><a href="' . esc_url( $s['url'] ) . '"'
			. ( $dis ? ' rel="noopener nofollow" target="_blank"' : '' ) . '>'
			. esc_html( $s['ad'] ) . '</a>';
		if ( '' !== $s['aciklama'] ) {
			$h .= '<p>' . esc_html( $s['aciklama'] ) . '</p>';
		}
		$h .= '</li>';
	}
	$h .= '</ul></aside>';
	return $h;
}

/**
 * Ölü alt linkler sorun defterine yazılır.
 *
 * Bir adres basilmadi diye yok sayilmaz: hangi yazida hangi olu adres
 * oldugu deftere dusulur, yoksa kimse fark etmez.
 */
add_action( 'shutdown', 'gbc_alt_olu_bildir', 99 );
function gbc_alt_olu_bildir() {
	if ( empty( $GLOBALS['gbc_alt_olu'] ) || ! is_array( $GLOBALS['gbc_alt_olu'] ) ) { return; }
	if ( ! function_exists( 'gbc_sorun_ac' ) ) { return; }

	$kayit = get_option( 'gbc_alt_olu_linkler', array() );
	if ( ! is_array( $kayit ) ) { $kayit = array(); }

	$pid = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
	foreach ( array_unique( $GLOBALS['gbc_alt_olu'] ) as $u ) {
		$kayit[ $u ] = array( 'pid' => $pid, 'zaman' => time() );
	}
	if ( count( $kayit ) > 200 ) { $kayit = array_slice( $kayit, -200, null, true ); }
	update_option( 'gbc_alt_olu_linkler', $kayit, false );

	gbc_sorun_ac( 'alt-olu-link', 'icerik',
		sprintf( 'Alt linklerde %d ölü adres', count( $kayit ) ),
		'tarif_alt_linkler alanında 404 dönen adres var; o satır basılmadı. Adresler gbc_alt_olu_linkler seçeneğinde.',
		'orta', admin_url( 'admin.php?page=gbc-icerik' ), count( $kayit ) );
}

/* İçeriğin sonuna, İlgili Yazılar'ın (20) hemen üstüne. */
add_filter( 'the_content', 'gbc_alt_bas', 15 );
function gbc_alt_bas( $content ) {
	if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
	if ( ! function_exists( 'get_field' ) ) { return $content; }

	$pid = get_the_ID();
	if ( ! $pid ) { return $content; }

	/* Snippet kendi bassaydi iki kere gorunurdu — isaret varsa cik. */
	if ( false !== strpos( $content, 'gbc-alt-bas' ) ) { return $content; }

	$ham = get_field( 'tarif_alt_linkler', $pid );
	if ( ! is_string( $ham ) || '' === trim( $ham ) ) { return $content; }

	$liste = gbc_alt_suz( gbc_alt_ayristir( $ham ), $content, get_permalink( $pid ) );
	if ( ! $liste ) { return $content; }

	return $content . gbc_alt_kutu( $liste );
}
