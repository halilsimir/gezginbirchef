<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 31366 — GBC · 50 · Ortaklık Bağlantı Motoru (30204/31358 kopyasi).
 * GBC Core'a tasindi, 28 Eylul 2026.
 *
 * NE YAPAR
 *   [gbc_aff id=...]metin[/gbc_aff] kisa kodunu cozer.
 *   Hedef adresi defterden (sayfa 30120) okur, sayfaya su bicimi basar:
 *   <a class="gbc-in" href="..." target="_blank" rel="sponsored nofollow noopener"
 *      data-aff="DEFTER_ID" data-prog="Program" data-post="POST_ID">Metin</a>
 *
 * KURALLAR (defter 30120, bolum 12)
 *   - Transient kullanilmaz. Onbellek yalniz istek icinde, statik degiskende.
 *   - Defterde olmayan ya da baglantisi bos id yazilirsa HICBIR SEY basilmaz.
 *   - Kivrik tirnak ve bosluk id'den temizlenir.
 *
 * DUZELTME (28 Eylul 2026): 31366'daki ikinci SVG mask'inda xmlns adresi
 * "2020/svg" yaziliyordu (yazim hatasi). Burada "2000/svg" olarak duzeltildi.
 */

if ( ! defined( 'GBC_AFF_DEFTER_ID' ) ) { define( 'GBC_AFF_DEFTER_ID', 30120 ); }
if ( ! defined( 'GBC_AFF_ETIKET' ) )    { define( 'GBC_AFF_ETIKET', 'iş birliği' ); }

if ( ! function_exists( 'gbc_aff_defter' ) ) {
/**
 * Defteri okur ve id => satir dizisine cevirir.
 */
function gbc_aff_defter() {

	static $defter = null;
	if ( $defter !== null ) {
		return $defter;
	}

	$defter = array();
	$takma  = array();

	$sayfa = get_post( GBC_AFF_DEFTER_ID );
	if ( ! $sayfa || ! isset( $sayfa->post_content ) || $sayfa->post_content === '' ) {
		return $defter;
	}

	/* Satirlar <pre> bloklarinin icinde duruyor. */
	if ( ! preg_match_all( '#<pre[^>]*>(.*?)</pre>#is', $sayfa->post_content, $bloklar ) ) {
		return $defter;
	}

	foreach ( $bloklar[1] as $blok ) {

		$blok = wp_strip_all_tags( $blok );
		$blok = html_entity_decode( $blok, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		foreach ( preg_split( "/\r\n|\n|\r/", $blok ) as $satir ) {

			$satir = trim( $satir );
			if ( $satir === '' ) {
				continue;
			}

			/* TAKMA eski_id yeni_id → eski id'yi yenisine bagla */
			if ( stripos( $satir, 'TAKMA ' ) === 0 ) {
				$p = preg_split( '/\s+/', $satir );
				if ( count( $p ) >= 3 ) {
					$takma[ $p[1] ] = $p[2];
				}
				continue;
			}

			if ( strpos( $satir, '|' ) === false ) {
				continue;
			}

			$p = array_map( 'trim', explode( '|', $satir ) );
			if ( count( $p ) < 4 ) {
				continue;
			}

			$id  = $p[0];
			$url = $p[3];

			/* Baslik satiri ve adres olmayan satirlar atlanir. */
			if ( $id === '' || $id === 'id' ) {
				continue;
			}
			if ( stripos( $url, 'http' ) !== 0 ) {
				continue;
			}

			$defter[ $id ] = array(
				'metin' => $p[1],
				'prog'  => $p[2],
				'url'   => $url,
				'ag'    => isset( $p[4] ) ? $p[4] : '',
			);
		}
	}

	/* Takma adlar gercek satira baglanir. */
	foreach ( $takma as $eski => $yeni ) {
		if ( isset( $defter[ $yeni ] ) && ! isset( $defter[ $eski ] ) ) {
			$defter[ $eski ] = $defter[ $yeni ];
		}
	}

	return $defter;
}
}

if ( ! function_exists( 'gbc_aff_id_temizle' ) ) {
/* id'yi temizler: kivrik tirnak, duz tirnak, bosluk. */
function gbc_aff_id_temizle( $ham ) {
	$ham = (string) $ham;
	$ham = str_replace( array( "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", '"', "'" ), '', $ham );
	return trim( $ham );
}
}

if ( ! function_exists( 'gbc_aff_etiket_html' ) ) {
/* 21 Eylul 2026: baglanti yanindaki "is birligi" rozeti kaldirildi.
   Bildirim artik sayfa basinda tek yerde (gbc_aff_saypa_bildirimi). */
function gbc_aff_etiket_html() {
	return '';
}
}

if ( ! function_exists( 'gbc_aff_kisa_kod' ) ) {
/* [gbc_aff id=... stil=...]metin[/gbc_aff] */
function gbc_aff_kisa_kod( $atts, $icerik = null ) {

	$a = shortcode_atts(
		array(
			'id'   => '',
			'stil' => '',
		),
		$atts,
		'gbc_aff'
	);

	$id = gbc_aff_id_temizle( $a['id'] );
	if ( $id === '' ) {
		return '';
	}

	$defter = gbc_aff_defter();
	if ( ! isset( $defter[ $id ] ) ) {
		return ''; /* Defterde yoksa hicbir sey basma — yanlis link canliya cikmasin. */
	}

	$satir = $defter[ $id ];
	if ( $satir['url'] === '' ) {
		return '';
	}

	/* Metin: kisa kodun ici; yazilmamissa defterdeki gorunen metin. */
	$metin = trim( (string) $icerik );
	if ( $metin === '' ) {
		$metin = $satir['metin'];
	}

	/* ANA SALTER: denetim panelindeki (30126) "butun ortaklik baglantilarini
	   kapat" isaretliyse baglanti basilmaz, metin duz kalir. */
	static $gbc_aff_kapali = null;
	if ( $gbc_aff_kapali === null ) {
		$gbc_aff_kapali = ( get_option( 'gbc_aff_kapali' ) === '1' );
	}
	if ( $gbc_aff_kapali ) {
		return wp_kses_post( $metin );
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		$post_id = 0;
	}

	$ortak = ' href="' . esc_url( $satir['url'] ) . '"'
		. ' target="_blank"'
		. ' rel="sponsored nofollow noopener"'
		. ' data-aff="' . esc_attr( $id ) . '"'
		. ' data-prog="' . esc_attr( $satir['prog'] ) . '"'
		. ' data-post="' . esc_attr( $post_id ) . '"';

	/* --- Yan sutun karti satiri: "Baslik | Aciklama" --- */
	if ( strtolower( trim( $a['stil'] ) ) === 'yan' ) {

		$baslik   = $metin;
		$aciklama = '';

		if ( strpos( $metin, '|' ) !== false ) {
			$parca    = array_map( 'trim', explode( '|', $metin, 2 ) );
			$baslik   = $parca[0];
			$aciklama = isset( $parca[1] ) ? $parca[1] : '';
		}

		$html  = '<a class="gz-yakin-row gbc-in gbc-yan"' . $ortak . '>';
		$html .= '<span class="gbc-yan-ad">' . wp_kses_post( $baslik ) . '</span>';
		$html .= gbc_aff_etiket_html();
		if ( $aciklama !== '' ) {
			$html .= '<span class="gbc-yan-not">' . wp_kses_post( $aciklama ) . '</span>';
		}
		$html .= '</a>';

		return $html;
	}

	/* --- Govde ici baglanti --- */
	return '<a class="gbc-in"' . $ortak . '>' . wp_kses_post( $metin ) . '</a> ' . gbc_aff_etiket_html();
}
}

if ( ! function_exists( 'gbc_aff_etiket_stili' ) ) {
/* Motorun bastigi parcalarin gorunumu. */
function gbc_aff_etiket_stili() {
	echo '<style id="gbc-in-et-stil">'
		. '.gbc-in-et{display:inline-block;background:#EFEFEF;color:#5A5A5A;'
		. 'font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:4px;'
		. 'padding:2px 7px;margin-left:8px;vertical-align:2px;line-height:1.6;'
		. 'white-space:nowrap;text-decoration:none}'
		. '.gbc-in-et::after{content:none!important}'
		. '.entry-content a.gbc-in.gbc-yan{display:block;padding:10px 14px;'
		. 'text-decoration:none!important;font-size:15px!important;line-height:1.4;'
		. 'border-bottom:1px solid #EFEDEA}'
		. '.entry-content a.gbc-in.gbc-yan:last-child{border-bottom:0}'
		. '.entry-content a.gbc-in.gbc-yan .gbc-yan-ad{display:inline!important;'
		. 'width:auto!important;'
		. 'font-weight:600;font-size:15px;color:#BF360C;'
		. 'text-decoration:underline;text-underline-offset:3px}'
		. '.entry-content a.gbc-in.gbc-yan .gbc-yan-not{display:block!important;'
		. 'width:auto!important;'
		. 'margin-top:4px;font-weight:400;font-size:13.5px;line-height:1.45;'
		. 'color:#7A7A7A;text-decoration:none}'
		. '.entry-content a.gbc-in.gbc-yan .gbc-in-et{'
		. 'display:inline-block!important;width:auto!important;'
		. 'margin:0 0 0 8px!important;vertical-align:1px}'
		. '.entry-content a.gbc-in.gbc-yan::after{display:none!important}'
		. '.entry-content a.gbc-in.gbc-yan .gbc-yan-ad::after{content:"";display:inline-block;'
		. 'width:13px;height:13px;margin-left:6px;vertical-align:-2px;background-color:#BF360C;'
		. '-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23000\' stroke-width=\'2.8\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M7 17L17 7M9 7h8v8\'/%3E%3C/svg%3E") center/contain no-repeat;'
		. 'mask:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23000\' stroke-width=\'2.8\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M7 17L17 7M9 7h8v8\'/%3E%3C/svg%3E") center/contain no-repeat}'
		. '.gbc-in-et,.gz-kart-et{display:none!important}'
		. '.entry-content .gbc-ortaklik-not{order:2!important}.entry-content .gz-main-column > .gbc-ortaklik-not{order:4!important}'
		. '.entry-content .gbc-ortaklik-not{display:block!important;margin:8px 0 18px!important;padding:0!important;'
		. 'font-size:13px!important;line-height:1.55!important;color:#6B6B6B!important;font-weight:400!important;'
		. 'font-style:normal!important;letter-spacing:0!important;text-transform:none!important;text-align:left!important;max-width:760px}'
		. '.entry-content .gbc-ortaklik-not a.gbc-ortaklik-link{color:inherit!important;font-weight:500!important;'
		. 'text-decoration:underline!important;text-underline-offset:2px}'
		. '.entry-content .gbc-ortaklik-not a.gbc-ortaklik-link::after{content:none!important;display:none!important}'
		. '</style>';
}
add_action( 'wp_head', 'gbc_aff_etiket_stili', 99 );
}

if ( ! function_exists( 'gbc_aff_saypa_bildirimi' ) ) {
function gbc_aff_saypa_bildirimi( $html ) {
	if ( ! is_string( $html ) || $html === '' ) { return $html; }
	if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $html; }
	$temiz = preg_replace(
		array(
			'#<span class="gz-kart-et">[^<]*</span>#',
			'#<span class="gbc-in-et">[^<]*</span>#',
			'#<div class="gz-section-block">\s*<p class="gz-aff-bildirim">.*?</p>\s*</div>#s',
			'#<p class="gz-aff-bildirim">.*?</p>#s',
		),
		'',
		$html
	);
	if ( is_string( $temiz ) ) { $html = $temiz; }
	static $basildi = array();
	$pid = (int) get_the_ID();
	if ( isset( $basildi[ $pid ] ) ) { return $html; }
	if ( ! preg_match( '#data-aff=|tp\.media/r|\.tpx\.li/|pxf\.io/|aff_uid=gzgbr|partner_id=#', $html ) ) { return $html; }
	$basildi[ $pid ] = true;
	$not = '<div class="gbc-ortaklik-not" role="note">Bu sayfadaki bazı bağlantılar iş ortaklığı bağlantısıdır. Bunlar üzerinden rezervasyon ya da alışveriş yaparsanız, sizin için fiyat değişmeden küçük bir komisyon alabilirim. Önerilerimi etkilemez, ama bu rehberleri hazırlamaya devam etmemi sağlar. <a class="gbc-ortaklik-link" href="' . esc_url( home_url( '/reklam-is-ortakligi-ve-sorumluluk-politikasi/' ) ) . '">Ayrıntılar</a></div>';
	$say  = 0;
	$yeni = preg_replace( '#(<article class="gz-content-box)#', $not . '$1', $html, 1, $say );
	if ( ! ( is_string( $yeni ) && $say ) ) { $gbc_hr = strpos( $html, '<div class="v1-hero-right">' ); if ( false !== $gbc_hr && false !== strpos( $html, 'v1-hero-left' ) ) { $gbc_hl = strrpos( substr( $html, 0, $gbc_hr ), '</div>' ); if ( false !== $gbc_hl ) { $yeni = substr( $html, 0, $gbc_hl ) . $not . substr( $html, $gbc_hl ); $say = 1; } } }
	if ( ! ( is_string( $yeni ) && $say ) ) { $yeni = preg_replace( '#</h1>#i', '</h1>' . $not, $html, 1, $say ); }
	if ( is_string( $yeni ) && $say ) { return $yeni; }
	return $not . $html;
}
add_filter( 'the_content', 'gbc_aff_saypa_bildirimi', 1000 );
}

/* Kisa kod kaydi. init 5'te bir kez daha kaydediliyor: sablon ciktisindaki
   [gbc_aff] kodlari da cozulsun diye the_content'e ikinci gecis ekleniyor. */
if ( ! shortcode_exists( 'gbc_aff' ) ) {
	add_shortcode( 'gbc_aff', 'gbc_aff_kisa_kod' );
}
add_action( 'init', function () {
	if ( ! shortcode_exists( 'gbc_aff' ) ) {
		add_shortcode( 'gbc_aff', 'gbc_aff_kisa_kod' );
	}
	add_filter( 'the_content', 'do_shortcode', 12 );
}, 5 );

/* ============================================================
   OTOMATİK TRAVELPAYOUTS LİNKİ — API'siz

   ÖLÇÜLDÜ (28 Eylül 2026, ortaklık defteri sayfa 30120):
   Defterdeki 577 Travelpayouts bağlantısının HEPSİ aynı kalıpta:

     https://tp.media/r?campaign_id=<CID>&marker=767959&p=<P>
                       &sub_id=<SUB>&trs=565047&u=<hedef>

   marker tek (767959), trs tek (565047), program dört tane:
     Booking      campaign_id=84  p=2076   264 bağlantı
     GetYourGuide campaign_id=108 p=3965   168 bağlantı
     DiscoverCars campaign_id=117 p=3555    89 bağlantı
     Omio         campaign_id=91  p=2078    55 bağlantı

   YANİ LİNK ÜRETMEK İÇİN API'YE GEREK YOK. Travelpayouts API'si
   yalnızca kısa biçimi (booking.tpx.li/xxxx) üretiyordu; uzun biçim
   birebir aynı işi yapıyor ve zaten defterdeki bağlantıların kendisi.
   Bu yüzden ayrı "Link Üretici" ekranı kaldırıldı: hedef adresi ver,
   motor linki kendisi kuruyor — belirteç, kota, ağ isteği yok.

   Yeni program eklemek için:
     add_filter( 'gbc_aff_tp_programlar', function ( $p ) {
         $p['tiqets.com'] = array( 'cid' => 999, 'p' => 1234, 'ad' => 'Tiqets' );
         return $p;
     } );
   ============================================================ */

if ( ! function_exists( 'gbc_aff_tp_programlar' ) ) {
function gbc_aff_tp_programlar() {
	return (array) apply_filters( 'gbc_aff_tp_programlar', array(
		'booking.com'      => array( 'cid' => 84,  'p' => 2076, 'ad' => 'Booking.com' ),
		'getyourguide.com' => array( 'cid' => 108, 'p' => 3965, 'ad' => 'GetYourGuide' ),
		'discovercars.com' => array( 'cid' => 117, 'p' => 3555, 'ad' => 'DiscoverCars' ),
		'omio.com'         => array( 'cid' => 91,  'p' => 2078, 'ad' => 'Omio' ),
		'omio.it'          => array( 'cid' => 91,  'p' => 2078, 'ad' => 'Omio' ),
	) );
}
}

if ( ! function_exists( 'gbc_aff_tp_marker' ) ) {
/** Ortak numarası: önce API Merkezi, sonra eski seçenek, sonra ölçülen değer. */
function gbc_aff_tp_marker() {
	if ( function_exists( 'gbc_api' ) ) {
		$m = trim( (string) gbc_api( 'tp_marker' ) );
		if ( '' !== $m ) { return $m; }
	}
	$m = trim( (string) get_option( 'gbc_tp_marker', '' ) );
	return '' !== $m ? $m : '767959';
}
}

if ( ! function_exists( 'gbc_aff_tp_trs' ) ) {
function gbc_aff_tp_trs() {
	$t = (int) get_option( 'gbc_tp_trs', 0 );
	return $t ? (string) $t : '565047';
}
}

if ( ! function_exists( 'gbc_aff_tp_uret' ) ) {
/**
 * Hedef adresten Travelpayouts bağlantısı kurar. Ağ isteği yok.
 *
 * @param string $hedef  Ortaklık hedefi (booking.com/… gibi).
 * @param string $sub_id Raporda görünecek etiket.
 * @return string Bağlantı, ya da program tanınmazsa boş.
 */
function gbc_aff_tp_uret( $hedef, $sub_id = '' ) {
	$hedef = trim( (string) $hedef );
	if ( '' === $hedef ) { return ''; }

	$alan = strtolower( (string) wp_parse_url( $hedef, PHP_URL_HOST ) );
	if ( '' === $alan ) { return ''; }
	$alan = preg_replace( '/^www\./', '', $alan );

	$prog = null;
	foreach ( gbc_aff_tp_programlar() as $anahtar => $p ) {
		if ( $alan === $anahtar || substr( $alan, - ( strlen( $anahtar ) + 1 ) ) === '.' . $anahtar ) {
			$prog = $p;
			break;
		}
	}
	if ( ! $prog ) { return ''; }

	/* Sorgu dizisi ELLE kuruluyor. add_query_arg kullanılmıyor, çünkü o
	   değerleri bir kez daha kodluyor ve hedef adres çift kodlanmış
	   çıkıyor. Parametre sırası defterdeki 577 bağlantıyla birebir aynı. */
	return 'https://tp.media/r?campaign_id=' . (int) $prog['cid']
		. '&marker=' . rawurlencode( gbc_aff_tp_marker() )
		. '&p=' . (int) $prog['p']
		. '&sub_id=' . rawurlencode( sanitize_key( $sub_id ) )
		. '&trs=' . rawurlencode( gbc_aff_tp_trs() )
		. '&u=' . rawurlencode( $hedef );
}
}

if ( ! function_exists( 'gbc_aff_tp_program_adi' ) ) {
/** Hedef adresin hangi programa düştüğü — panelde göstermek için. */
function gbc_aff_tp_program_adi( $hedef ) {
	$alan = strtolower( (string) wp_parse_url( (string) $hedef, PHP_URL_HOST ) );
	$alan = preg_replace( '/^www\./', '', $alan );
	foreach ( gbc_aff_tp_programlar() as $anahtar => $p ) {
		if ( $alan === $anahtar || substr( $alan, - ( strlen( $anahtar ) + 1 ) ) === '.' . $anahtar ) {
			return $p['ad'];
		}
	}
	return '';
}
}

/* ------------------------------------------------------------
   ESKİ LİNK DEPOSU — salt okunur yedek.
   modules/40-otel.php, gbc_aff kısa kodu hiç yoksa buraya düşüyor.
   Depo (gbc_tp_links seçeneği, 57 kayıt) SİLİNMEDİ; üreteci ekran
   kaldırıldığı için okuyucu buraya taşındı.
   ------------------------------------------------------------ */
if ( ! function_exists( 'gbc_tp_get' ) ) {
function gbc_tp_get( $slot ) {
	$m = get_option( 'gbc_tp_links', array() );
	return ( isset( $m[ $slot ]['aff'] ) && $m[ $slot ]['aff'] ) ? $m[ $slot ]['aff'] : '';
}
}
