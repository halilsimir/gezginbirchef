<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 29738 — Şema Motoru. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 93 · Sema Motoru v4.4 · PHP
 * ---------------------------------------------------------------------------
 * Tek merkezden, tum icerik turleri icin tek bir @graph JSON-LD uretir.
 *
 * v4.4 DEGISIKLIKLERI (27.08.2026 · canli DOM olcumuyle bulundu):
 *   1) #webpage dugumunun @type'i yoktu. about / mainEntity ve 6 silo linki
 *      (significantLink) tipsiz bir dugumde asili kaliyordu. @type + url eklendi.
 *   2) VideoObject.name artik ACF oembed HTML'indeki title="" degerinden,
 *      yani videonun GERCEK adindan geliyor. Atina pillar'inda uc video da
 *      "Atina Gezi Rehberi..." adiyla cikiyordu; gercekte 2. Pire, 3. Celestyal.
 *   3) YENI: VideoObject.hasPart -> Clip (key moments). Bolumler ACF'deki
 *      hero_video_chapters / video_N_chapters alanindan, "0:00 | Ad" satirlari
 *      olarak okunur. Alan yoksa hicbir sey basilmaz, sayfa bozulmaz.
 *   4) Semaya giden bozuk Turkce duzeltildi: "2 Gunluk Rota" -> "2 Gunluk"
 *      degil "2 Günlük Rota"; "Bagimsiz gezgin", "Konusulan dil",
 *      "Mutfak Sozlugu" da ayni sekilde.
 *
 * v4.3 DEGISIKLIKLERI (yaniltici veri temizligi):
 *   1) servesCuisine artik sabit 'Yunan Mutfagi' degil; pillar'in info_country
 *      alanindan uretiliyor (gbc_v3_mutfak). Ulke listede yoksa alan hic basilmaz.
 *      Turkce noktali I icin gbc_v3_tr_kucult() kullanilir: mb_strtolower('İ')
 *      "i"+U+0307 uretip eslesmeyi bozuyordu.
 *   2) touristType artik her destinasyonda ayni dort etiket degil; sayfanin
 *      kendi ACF verisinden uretiliyor. 'Plaj tatili' SADECE info_island dolu
 *      olan sayfalarda basilir (Prag/Viyana/Budapeste'de artik basilmiyor).
 *   3) YENI: includesAttraction — pillar'in rel_* hub'larina baglanir.
 *   4) Tekrarli @id temizligi: ayni hub birden fazla rel_* alaninda secilmisse
 *      subjectOf / significantLink / includesAttraction icinde bir kez gecer.
 *
 * KAPSAM: Gezi (pillar) · Liste (hub) · Detay (spoke) · Rota · Sozluk · Blog · duz yazi
 * KAPSAM DISI: Tarif (24751 Recipe motoru basiyor, dokunulmadi)
 *              Sayfa/arsiv/yazar/anasayfa (29274 / 23824 / 27098 / 28209)
 *
 * Kimlik dugumleri (Organization/WebSite/Person/WebPage/BreadcrumbList/
 * ImageObject) GBC·92 (29013) tarafindan wp_head(99)'da basiliyor. Bu motor
 * onlari YENIDEN TANIMLAMAZ; sadece @id ile referans verir ve WebPage'e
 * kismi bir dugumle mainEntity/about baglar (JSON-LD dugum birlestirme).
 *
 * Bilerek uretilmeyenler:
 *   FAQPage      Google FAQ zengin sonucunu 07.05.2026'da kaldirdi
 *   HowTo        Google HowTo zengin sonucunu 2023'te kaldirdi
 *   SearchAction Google sitelinks search box'i 21.11.2024'te kaldirdi
 *   aggregateRating  Gercek oy verisi olmadan uretilmez (uydurma yasak)
 *
 * SAYFA BAZLI OVERRIDE (kod degistirmeden):
 *   post meta  gbc_schema_item_type   -> Liste kartlarinin @type'i
 *   post meta  gbc_schema_place_type  -> Detay yer dugumunun @type'i
 *   post meta  gbc_schema_geo         -> "lat,lng" (harita embed'inden koordinat
 *                                        cikmayan sayfalar icin elle giris)
 *
 * WPCode: Auto Insert / Site Wide Footer. Code Type: PHP Snippet.
 * Geri alma: snippet'i pasife al + 6 snippet'te
 *   "application/gbc-v2-disabled+json" -> "application/ld+json" geri yaz.
 *
 * GBC 2026-08-11
 */

if ( defined( 'GBC_SCHEMA_V3' ) ) { return; }
define( 'GBC_SCHEMA_V3', '4.2.0' );

/* ══════════════════════ Yardimcilar ══════════════════════ */

if ( ! function_exists( 'gbc_v3_bos_degil' ) ) {
	function gbc_v3_bos_degil( $v ) { return $v !== null && $v !== '' && $v !== array() && $v !== false; }
}

if ( ! function_exists( 'gbc_v3_txt' ) ) {
	function gbc_v3_txt( $v, $limit = 0 ) {
		if ( is_array( $v ) || is_object( $v ) ) { return ''; }
		$v = wp_strip_all_tags( (string) $v );
		$v = html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$v = trim( preg_replace( '/\s+/u', ' ', $v ) );
		if ( $limit > 0 && mb_strlen( $v, 'UTF-8' ) > $limit ) {
			$v = rtrim( mb_substr( $v, 0, $limit, 'UTF-8' ) ) . '…';
		}
		return $v;
	}
}

if ( ! function_exists( 'gbc_v3_acf' ) ) {
	function gbc_v3_acf( $key, $id ) {
		if ( ! function_exists( 'get_field' ) ) { return ''; }
		$v = get_field( $key, $id );
		return ( $v === false || $v === null ) ? '' : $v;
	}
}

if ( ! function_exists( 'gbc_v3_dolu' ) ) {
	function gbc_v3_dolu( $key, $id ) { return ! empty( gbc_v3_acf( $key, $id ) ); }
}

if ( ! function_exists( 'gbc_v3_yt' ) ) {
	function gbc_v3_yt( $s ) {
		if ( ! is_string( $s ) || $s === '' ) { return ''; }
		$p = '#(?:youtube\.com/(?:embed/|v/|watch\?(?:.*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#';
		return preg_match( $p, $s, $m ) ? $m[1] : '';
	}
}

/** Harita embed HTML'i veya "lat,lng" metninden koordinat. Bulamazsa false. */
if ( ! function_exists( 'gbc_v3_geo' ) ) {
	function gbc_v3_geo( $s ) {
		if ( ! is_string( $s ) || $s === '' ) { return false; }
		if ( preg_match( '/^\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*$/', $s, $m ) ) {
			return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
		}
		if ( preg_match( '/!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)/', $s, $m ) ) {
			return array( 'lat' => (float) $m[2], 'lng' => (float) $m[1] );
		}
		if ( preg_match( '/[?&@](?:q|ll|center)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/', $s, $m ) ) {
			return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
		}
		if ( preg_match( '/!3d(-?\d+\.\d+).*?!2d(-?\d+\.\d+)/', $s, $m ) ) {
			return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
		}
		return false;
	}
}

/** Sayfa icin koordinat: once elle girilen meta, sonra harita alani. */
if ( ! function_exists( 'gbc_v3_geo_post' ) ) {
	function gbc_v3_geo_post( $id, $alan ) {
		$el = gbc_v3_geo( (string) get_post_meta( $id, 'gbc_schema_geo', true ) );
		if ( $el ) { return $el; }
		return gbc_v3_geo( gbc_v3_acf( $alan, $id ) );
	}
}

if ( ! function_exists( 'gbc_v3_geo_node' ) ) {
	function gbc_v3_geo_node( $g ) {
		return array( '@type' => 'GeoCoordinates', 'latitude' => $g['lat'], 'longitude' => $g['lng'] );
	}
}

if ( ! function_exists( 'gbc_v3_harita_url' ) ) {
	function gbc_v3_harita_url( $g ) {
		return 'https://www.google.com/maps/search/?api=1&query=' . $g['lat'] . ',' . $g['lng'];
	}
}

/** Kart aciklamasindan fiyat araligi: "€40-60", "40-60 €", "€25" -> "€40-€60". */
if ( ! function_exists( 'gbc_v3_fiyat' ) ) {
	function gbc_v3_fiyat( $s ) {
		$s = gbc_v3_txt( $s );
		if ( $s === '' ) { return ''; }
		if ( preg_match( '/€\s*(\d{1,4})\s*[-–—]\s*€?\s*(\d{1,4})/u', $s, $m ) ) { return '€' . $m[1] . '-€' . $m[2]; }
		if ( preg_match( '/(\d{1,4})\s*[-–—]\s*(\d{1,4})\s*€/u', $s, $m ) )      { return '€' . $m[1] . '-€' . $m[2]; }
		if ( preg_match( '/€\s*(\d{1,4})/u', $s, $m ) )                          { return '€' . $m[1]; }
		return '';
	}
}

/** ACF gorsel alani (dizi / ID / URL) -> ImageObject dizisi veya ''. */
if ( ! function_exists( 'gbc_v3_img' ) ) {
	function gbc_v3_img( $raw ) {
		$url = ''; $w = 0; $h = 0; $alt = '';
		if ( is_array( $raw ) && ! empty( $raw['url'] ) ) {
			$url = $raw['url'];
			$w   = isset( $raw['width'] )  ? (int) $raw['width']  : 0;
			$h   = isset( $raw['height'] ) ? (int) $raw['height'] : 0;
			$alt = isset( $raw['alt'] )    ? gbc_v3_txt( $raw['alt'] ) : '';
		} elseif ( is_numeric( $raw ) ) {
			$src = wp_get_attachment_image_src( (int) $raw, 'full' );
			if ( $src ) { $url = $src[0]; $w = (int) $src[1]; $h = (int) $src[2]; }
			$alt = gbc_v3_txt( get_post_meta( (int) $raw, '_wp_attachment_image_alt', true ) );
		} elseif ( is_string( $raw ) && filter_var( $raw, FILTER_VALIDATE_URL ) ) {
			$url = $raw;
		}
		if ( $url === '' ) { return ''; }
		return array_filter( array(
			'@type' => 'ImageObject', 'url' => $url, 'contentUrl' => $url,
			'width' => $w ?: '', 'height' => $h ?: '', 'caption' => $alt,
		), 'gbc_v3_bos_degil' );
	}
}

/** Videonun GERCEK YouTube yayin tarihi.
 *  Kaynak sirasi:
 *    1) gbc_video_tarih_haritasi filtresi  (VIDEOID => tarih)  <- snippet 94
 *    2) post meta gbc_video_date_<VIDEOID>                     <- tek tek elle
 *    3) yazinin tarihi                                          <- son care
 *  Google uploadDate'i "videonun ILK yayin tarihi" olarak tanimliyor;
 *  yazi tarihi teknik olarak yanlis, o yuzden 1 ve 2 tercih edilir. */
if ( ! function_exists( 'gbc_v3_video_kaydi' ) ) {
	function gbc_v3_video_kaydi( $vid ) {
		static $h = null;
		if ( $h === null ) { $h = (array) apply_filters( 'gbc_video_tarih_haritasi', array() ); }
		return isset( $h[ $vid ] ) ? (array) $h[ $vid ] : array();
	}
}

/** Videonun ISO 8601 suresi (snippet 94'ten). Google "recommended" diyor. */
if ( ! function_exists( 'gbc_v3_video_sure' ) ) {
	function gbc_v3_video_sure( $vid ) {
		$k = gbc_v3_video_kaydi( $vid );
		return isset( $k[1] ) ? $k[1] : '';
	}
}

if ( ! function_exists( 'gbc_v3_video_tarih' ) ) {
	function gbc_v3_video_tarih( $vid, $pid ) {
		$k = gbc_v3_video_kaydi( $vid );
		if ( ! empty( $k[0] ) ) {
			$t = strtotime( $k[0] );
			if ( $t ) { return gmdate( 'c', $t ); }
		}
		$m = trim( (string) get_post_meta( $pid, 'gbc_video_date_' . $vid, true ) );
		if ( $m !== '' ) {
			$t = strtotime( $m );
			if ( $t ) { return gmdate( 'c', $t ); }
		}
		return get_the_date( 'c', $pid );
	}
}

/** ISO 8601 sure (PT1H2M35S) -> saniye. Clip'in endOffset'i icin. */
if ( ! function_exists( 'gbc_v4_sure_sn' ) ) {
	function gbc_v4_sure_sn( $iso ) {
		if ( ! is_string( $iso ) ) { return 0; }
		if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m ) ) { return 0; }
		$s = 0;
		if ( isset( $m[1] ) && $m[1] !== '' ) { $s += (int) $m[1] * 3600; }
		if ( isset( $m[2] ) && $m[2] !== '' ) { $s += (int) $m[2] * 60; }
		if ( isset( $m[3] ) && $m[3] !== '' ) { $s += (int) $m[3]; }
		return $s;
	}
}

/**
 * Videonun GERCEK basligi.
 * ONCEDEN: her VideoObject'e sayfanin basligi basiliyordu. Atina pillar'inda
 * uc videonun ucu de "Atina Gezi Rehberi..." adiyla cikiyordu; gercekte
 * ikincisi Pire, ucuncusu Celestyal gemi turu. Google'a ayni adli uc video
 * bildiriliyordu.
 * ACF'nin oembed alani zaten YouTube'un iframe HTML'ini donduruyor ve o HTML'de
 * title="..." icinde videonun gercek adi var. Ek istek yok, API anahtari yok.
 * Elle ezmek icin:
 *   add_filter( 'gbc_video_basliklari', function( $t ) { $t['VIDEOID'] = 'Ad'; return $t; } );
 */
if ( ! function_exists( 'gbc_v4_video_baslik' ) ) {
	function gbc_v4_video_baslik( $ham, $vid ) {
		$tablo = apply_filters( 'gbc_video_basliklari', array() );
		if ( is_array( $tablo ) && ! empty( $tablo[ $vid ] ) ) {
			return gbc_v3_txt( $tablo[ $vid ], 110 );
		}
		if ( is_string( $ham ) && preg_match( '/\stitle="([^"]+)"/', $ham, $m ) ) {
			$t = gbc_v3_txt( $m[1], 110 );
			/* YouTube bazen jenerik "YouTube video player" basiyor; ona guvenme. */
			if ( $t !== '' && stripos( $t, 'youtube video player' ) === false ) { return $t; }
		}
		return '';
	}
}

/**
 * "0:00 | Bolum adi" satirlarini Clip dizisine cevirir (key moments).
 * Kabul edilen bicimler:  0:00 | Ad   ·   01:23:45 - Ad   ·   1:23 Ad
 * schema.org: startOffset / endOffset SAYIDIR (saniye), "00:42" DEGIL.
 * Google en az 2 Clip istiyor; 1 tane varsa hic basilmaz.
 */
if ( ! function_exists( 'gbc_v4_bolumler' ) ) {
	function gbc_v4_bolumler( $ham, $node_id, $watch_url, $toplam_sn ) {
		if ( ! is_string( $ham ) || trim( $ham ) === '' ) { return array(); }
		$toplam = (int) $toplam_sn;
		$liste  = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $ham ) as $satir ) {
			$satir = trim( $satir );
			if ( $satir === '' ) { continue; }
			if ( ! preg_match( '/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*[|\-\x{2013}\x{2014}:]?\s*(.+)$/u', $satir, $m ) ) { continue; }
			$sn = ( (int) $m[1] * 3600 ) + ( (int) $m[2] * 60 ) + (int) $m[3];
			$ham_ad = explode( '|', $m[4] ); $ad = gbc_v3_txt( $ham_ad[0], 90 );
			if ( $ad === '' ) { continue; }
			/* Videonun suresini asan zaman damgasi = yazim hatasi. Alma. */
			if ( $toplam > 0 && $sn >= $toplam ) { continue; }
			$liste[ $sn ] = $ad;   /* ayni saniye iki kez yazildiysa sonuncusu kalir */
		}

		if ( count( $liste ) < 2 ) { return array(); }
		ksort( $liste, SORT_NUMERIC );

		$sn_dizi = array_keys( $liste );
		$n       = count( $sn_dizi );
		$ayirac  = ( strpos( $watch_url, '?' ) === false ) ? '?' : '&';
		$clipler = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$bas  = $sn_dizi[ $i ];
			$clip = array(
				'@type'       => 'Clip',
				'@id'         => $node_id . '-clip-' . ( $i + 1 ),
				'name'        => $liste[ $bas ],
				'startOffset' => $bas,
				'url'         => $watch_url . $ayirac . 't=' . $bas,
			);
			/* Son bolumun bitisi videonun sonu. Sure bilinmiyorsa endOffset
			   hic basilmaz - yanlis sayi basmaktansa eksik basmak dogru. */
			$bit = ( $i + 1 < $n ) ? $sn_dizi[ $i + 1 ] : $toplam;
			if ( $bit > $bas ) { $clip['endOffset'] = $bit; }
			$clipler[] = $clip;
		}

		return ( count( $clipler ) >= 2 ) ? $clipler : array();
	}
}

if ( ! function_exists( 'gbc_v3_video' ) ) {
	/**
	 * @param string $ham        ACF oembed alaninin HAM degeri (iframe HTML'i).
	 *                           Bos gecilirse baslik eskisi gibi $name'den gelir.
	 * @param string $bolum_ham  "0:00 | Ad" satirlari. Bos gecilirse Clip basilmaz.
	 * Son iki parametre OPSIYONEL: eski cagri yerleri aynen calisir.
	 */
	function gbc_v3_video( $vid, $node_id, $name, $desc, $pid, $ham = '', $bolum_ham = '' ) {
		$watch  = 'https://www.youtube.com/watch?v=' . $vid;
		$sure   = gbc_v3_video_sure( $vid );
		$gercek = gbc_v4_video_baslik( $ham, $vid ); /* 21 Eyl 2026: aciklama bossa Google videoyu gecersiz sayiyordu; sirayla Rank Math aciklamasi, yazi ozeti, video adi */ if ( gbc_v3_txt( $desc ) === '' ) { $desc = get_post_meta( $pid, 'rank_math_description', true ); } if ( gbc_v3_txt( $desc ) === '' ) { $desc = get_post_field( 'post_excerpt', $pid ); } if ( gbc_v3_txt( $desc ) === '' ) { $desc = ( $gercek !== '' ) ? $gercek : $name; }

		$node = array(
			'@type'        => 'VideoObject',
			'@id'          => $node_id,
			'name'         => ( $gercek !== '' ) ? $gercek : gbc_v3_txt( $name, 110 ),
			'description'  => gbc_v3_txt( $desc, 300 ),
			/* maxres bazi videolarda 404 veriyor; hqdefault her zaman var. */
			'thumbnailUrl' => array(
				'https://i.ytimg.com/vi/' . $vid . '/maxresdefault.jpg',
				'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg',
			),
			'uploadDate'   => gbc_v3_video_tarih( $vid, $pid ),
			'duration'     => $sure,
			'contentUrl'   => $watch,
			'embedUrl'     => 'https://www.youtube.com/embed/' . $vid,
			'publisher'    => array( '@id' => home_url( '/' ) . '#organization' ),
			'inLanguage'   => 'tr-TR',
		);

		$clipler = gbc_v4_bolumler( $bolum_ham, $node_id, $watch, gbc_v4_sure_sn( $sure ) );
		if ( $clipler ) { $node['hasPart'] = $clipler; }

		return array_filter( $node, 'gbc_v3_bos_degil' );
	}
}

/** Yalnizca YAYINDAKI yazilarin kalici baglantisi. Taslak/beklemede/ozel
 *  yazilarda get_permalink() "?p=123" doner; bu adres semaya girdiginde
 *  Google'a yayinda olmayan sayfalar bildirilmis olur. Onlenir. */
if ( ! function_exists( 'gbc_v3_permalink' ) ) {
	function gbc_v3_permalink( $v ) {
		$p = gbc_v3_pid( $v );
		if ( $p ) {
			if ( get_post_status( $p ) !== 'publish' ) { return ''; }
			return get_permalink( $p );
		}
		if ( is_string( $v ) && filter_var( $v, FILTER_VALIDATE_URL ) ) { return $v; }
		return '';
	}
}

if ( ! function_exists( 'gbc_v3_pid' ) ) {
	function gbc_v3_pid( $v ) {
		if ( $v instanceof WP_Post ) { return (int) $v->ID; }
		if ( is_array( $v ) && isset( $v['ID'] ) ) { return (int) $v['ID']; }
		if ( is_numeric( $v ) ) { return (int) $v; }
		return 0;
	}
}

if ( ! function_exists( 'gbc_v3_baslik' ) ) {
	function gbc_v3_baslik( $v ) {
		$p = gbc_v3_pid( $v );
		return $p ? gbc_v3_txt( get_the_title( $p ) ) : '';
	}
}

/** Kart basligindaki bastaki emoji/sus karakterlerini atar.
 *  "⭐ Jimmy's Gyros: ..." -> "Jimmy's Gyros: ..."
 *  Sayfadaki metinle ayni kalsin diye SADECE bastaki sus temizlenir. */
if ( ! function_exists( 'gbc_v3_isim_temiz' ) ) {
	function gbc_v3_isim_temiz( $v ) {
		$v = gbc_v3_txt( $v );
		if ( $v === '' ) { return ''; }
		$v = preg_replace( '/^[^\p{L}\p{N}"\x{2018}\x{201C}]+/u', '', $v );
		return trim( $v );
	}
}

/** Ulke adini ISO 3166-1 alpha-2 koduna cevirir (Google boyle istiyor).
 *  Listede yoksa metin haliyle birakilir - sema yine gecerli kalir. */
if ( ! function_exists( 'gbc_v3_ulke_kodu' ) ) {
	function gbc_v3_ulke_kodu( $v ) {
		$v = gbc_v3_txt( $v );
		if ( $v === '' ) { return ''; }
		$h = array(
			'yunanistan' => 'GR', 'turkiye' => 'TR', 'türkiye' => 'TR', 'turkey' => 'TR',
			'italya' => 'IT', 'almanya' => 'DE', 'avusturya' => 'AT', 'hollanda' => 'NL',
			'fransa' => 'FR', 'ispanya' => 'ES', 'portekiz' => 'PT', 'belcika' => 'BE',
			'belçika' => 'BE', 'bulgaristan' => 'BG', 'romanya' => 'RO', 'macaristan' => 'HU',
			'cekya' => 'CZ', 'çekya' => 'CZ', 'polonya' => 'PL', 'hirvatistan' => 'HR',
			'hırvatistan' => 'HR', 'sirbistan' => 'RS', 'sırbistan' => 'RS',
			'karadag' => 'ME', 'karadağ' => 'ME', 'arnavutluk' => 'AL', 'bosna hersek' => 'BA',
			'kuzey makedonya' => 'MK', 'makedonya' => 'MK', 'slovenya' => 'SI',
			'guney kore' => 'KR', 'güney kore' => 'KR', 'kore' => 'KR', 'japonya' => 'JP',
			'ingiltere' => 'GB', 'birlesik krallik' => 'GB', 'isvicre' => 'CH',
			'isviçre' => 'CH', 'isvec' => 'SE', 'isveç' => 'SE', 'danimarka' => 'DK',
			'norvec' => 'NO', 'norveç' => 'NO', 'finlandiya' => 'FI', 'irlanda' => 'IE',
			'kibris' => 'CY', 'kıbrıs' => 'CY', 'malta' => 'MT', 'misir' => 'EG',
			'mısır' => 'EG', 'fas' => 'MA', 'tunus' => 'TN', 'gurcistan' => 'GE',
			'gürcistan' => 'GE', 'azerbaycan' => 'AZ',
		);
		$k = mb_strtolower( $v, 'UTF-8' );
		return isset( $h[ $k ] ) ? $h[ $k ] : $v;
	}
}

/** Pillar'in ulkesinden mutfak adi (Restaurant kartlari icin).
 *  ONCEDEN: her destinasyonda sabit "Yunan Mutfagi" basiliyordu; Roma,
 *  Viyana, Prag gibi sayfalarda bu Google'a YANLIS bilgi gonderiyordu.
 *  Ulke listede yoksa bos doner ve alan hic basilmaz. */
if ( ! function_exists( 'gbc_v3_tr_kucult' ) ) {
	/**
	 * Turkce guvenli kucultme + aksan sadelestirme.
	 * mb_strtolower('İ') PHP'de "i" + U+0307 (birlesik nokta) uretir;
	 * bu yuzden once buyuk harfler elle esleniyor, sonra U+0307 temizleniyor.
	 */
	function gbc_v3_tr_kucult( $s ) {
		$s = (string) $s;
		if ( $s === '' ) { return ''; }
		$buyuk = array(
			'İ' => 'i', 'I' => 'i', 'Ş' => 's', 'Ğ' => 'g',
			'Ü' => 'u', 'Ö' => 'o', 'Ç' => 'c',
		);
		$s = strtr( $s, $buyuk );
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		$kucuk = array(
			"\xCC\x87" => '',                                  // U+0307 birlesik nokta
			'ı' => 'i', 'ş' => 's', 'ğ' => 'g',
			'ü' => 'u', 'ö' => 'o', 'ç' => 'c',
		);
		$s = strtr( $s, $kucuk );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}
}

if ( ! function_exists( 'gbc_v3_mutfak' ) ) {
	function gbc_v3_mutfak( $pillar_id ) {
		if ( ! $pillar_id ) { return ''; }
		$u = gbc_v3_tr_kucult( gbc_v3_txt( gbc_v3_acf( 'info_country', $pillar_id ) ) );
		if ( $u === '' ) { return ''; }
		// Anahtarlar tamamen ASCII: gbc_v3_tr_kucult() girdiyi ayni forma indirger.
		$h = array(
			'yunanistan'      => 'Yunan mutfağı',
			'italya'          => 'İtalyan mutfağı',
			'turkiye'         => 'Türk mutfağı',
			'avusturya'       => 'Avusturya mutfağı',
			'almanya'         => 'Alman mutfağı',
			'cekya'           => 'Çek mutfağı',
			'cek cumhuriyeti' => 'Çek mutfağı',
			'macaristan'      => 'Macar mutfağı',
			'hollanda'        => 'Hollanda mutfağı',
			'fransa'          => 'Fransız mutfağı',
			'ispanya'         => 'İspanyol mutfağı',
			'portekiz'        => 'Portekiz mutfağı',
			'danimarka'       => 'Danimarka mutfağı',
			'norvec'          => 'Norveç mutfağı',
			'isvec'           => 'İsveç mutfağı',
			'isvicre'         => 'İsviçre mutfağı',
			'finlandiya'      => 'Fin mutfağı',
			'malta'           => 'Malta mutfağı',
			'gurcistan'       => 'Gürcü mutfağı',
			'guney kore'      => 'Kore mutfağı',
			'bulgaristan'     => 'Bulgar mutfağı',
			'sirbistan'       => 'Sırp mutfağı',
			'hirvatistan'     => 'Hırvat mutfağı',
			'belcika'         => 'Belçika mutfağı',
			'irlanda'         => 'İrlanda mutfağı',
			'ingiltere'       => 'İngiliz mutfağı',
			'birlesik krallik'=> 'İngiliz mutfağı',
			'polonya'         => 'Polonya mutfağı',
			'romanya'         => 'Romen mutfağı',
			'slovenya'        => 'Sloven mutfağı',
			'slovakya'        => 'Slovak mutfağı',
			'karadag'         => 'Karadağ mutfağı',
			'arnavutluk'      => 'Arnavut mutfağı',
			'bosna hersek'    => 'Bosna mutfağı',
			'kuzey makedonya' => 'Makedon mutfağı',
			'kibris'          => 'Kıbrıs mutfağı',
			'estonya'         => 'Estonya mutfağı',
			'letonya'         => 'Letonya mutfağı',
			'litvanya'        => 'Litvanya mutfağı',
			'izlanda'         => 'İzlanda mutfağı',
			'luksemburg'      => 'Lüksemburg mutfağı',
			'japonya'         => 'Japon mutfağı',
			'tayland'         => 'Tayland mutfağı',
			'vietnam'         => 'Vietnam mutfağı',
			'hindistan'       => 'Hint mutfağı',
			'fas'             => 'Fas mutfağı',
			'misir'           => 'Mısır mutfağı',
			'lubnan'          => 'Lübnan mutfağı',
			'meksika'         => 'Meksika mutfağı',
			'peru'            => 'Peru mutfağı',
		);
		return isset( $h[ $u ] ) ? $h[ $u ] : '';
	}
}

/** info_city serbest metnini tek sehir adina indirger.
 *  "Mykonos / Mikonos (Muknos) - merkez: Chora (Hora)" -> "Mykonos" */
if ( ! function_exists( 'gbc_v3_sehir_temiz' ) ) {
	function gbc_v3_sehir_temiz( $v ) {
		$v = gbc_v3_txt( $v );
		if ( $v === '' ) { return ''; }
		$v = preg_replace( '/\s*\(.*?\)\s*/u', ' ', $v );   // parantezli aciklama
		$v = preg_split( '/\s*[-\x{2013}\x{2014}:,;]\s*/u', $v )[0]; // "- merkez: ..." kuyrugu
		$v = preg_split( '#\s*/\s*#u', $v )[0];               // "Mykonos / Mikonos"
		return trim( $v );
	}
}

/** Pillar'dan (Gezi) sehir/ulke alip PostalAddress kurar. */
if ( ! function_exists( 'gbc_v3_adres' ) ) {
	function gbc_v3_adres( $pillar_id ) {
		if ( ! $pillar_id ) { return ''; }
		$sehir = gbc_v3_sehir_temiz( gbc_v3_acf( 'info_city', $pillar_id ) );
		$ulke  = gbc_v3_ulke_kodu( gbc_v3_acf( 'info_country', $pillar_id ) );
		if ( ! $sehir && ! $ulke ) { return ''; }
		return array_filter( array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $sehir,
			'addressCountry'  => $ulke,
		), 'gbc_v3_bos_degil' );
	}
}

/** Mutfak Sozlugu arsiv adresi. home_url('/mutfak-sozlugu/') 404 veriyordu;
 *  kategori hiyerarsisi nedeniyle gercek adres /mutfak-akademisi/mutfak-sozlugu/.
 *  Slug'dan cozulur, bulunamazsa ana sayfaya duser. */
if ( ! function_exists( 'gbc_v3_sozluk_url' ) ) {
	function gbc_v3_sozluk_url() {
		static $u = null;
		if ( $u !== null ) { return $u; }
		$t = get_category_by_slug( 'mutfak-sozlugu' );
		$u = ( $t && ! is_wp_error( $t ) ) ? get_category_link( $t->term_id ) : home_url( '/' );
		return $u;
	}
}

/* ══════════════════════ Tur ve @type tespiti ══════════════════════ */

if ( ! function_exists( 'gbc_v3_tur' ) ) {
	function gbc_v3_tur( $id ) {

	/* ------------------------------------------------------------------
	   GBC 2026-09-07 - SABLON ONCE (v4.5)
	   ------------------------------------------------------------------
	   NEDEN: Asagidaki zincir "ilk eslesen kazanir" mantiginda. Bir sayfa
	   silo icinde tur degistirince (Detay -> Liste, Liste -> Gezi) eski alan
	   grubunun BOS satirlari diskte kaliyor ve sayfa yanlis dala giriyor.
	   Kazanan dal veri bulamayinca hicbir dugum basmiyor; hata da vermiyor,
	   sayfa sessizce ciplak Article'a dusuyor.

	   Olculdu (1033 sayfa, canli JSON-LD):
	   - Ibiza 30205: bos main_card_listed_1 ACF GRUBU dizi oldugu icin
	     empty() testini geciyordu -> 'liste' sanildi -> TouristDestination
	     ve 3 TouristTrip hic basilmadi.
	   - Bolonya rota 24538: ayni tuzak -> TouristTrip yok.
	   - Budapeste nerede kalinir 30237: hem detailed_hero_title hem
	     hero_custom_title_listed doluydu, sira geregi 'detay' kazandi -> ItemList yok.
	   Toplam 13 sayfa ana varligini kaybediyordu.

	   COZUM: post_content'teki wpcode kisa kodu sayfanin ne oldugunun KESIN
	   kaynagi. Ama sablon TEK BASINA yetmez: /yogurt-tarifi/ tarif sablonu
	   kullaniyor, malzemesi bos (canonical /evde-yogurt-yapimi/'ye bakiyor);
	   kaba "sablon ne diyorsa o" kurali onun Article dugumunu de silerdi.
	   Bu yuzden kural SABLON + O TURUN CEKIRDEK ALANI birlikte doluysa isler.
	   Ikisi birden yoksa asagidaki eski zincir aynen yedek olarak calisir.
	   Yani bu blok yalnizca EKLER, hicbir sayfadan dugum ALMAZ.

	   NOT: gbc_v3_dolu'ya dokunulmadi (mevcut zinciri bozmamak icin).
	   Burada dizi-farkindali kendi kontrolu kullaniliyor: butun alt degerleri
	   bos olan bir ACF grubu BOS sayilir.
	   Regex yok, ters egik cizgi yok (MCP -> REST yazimini bozuyor). */

	$gbc_sb = array(
		'24751' => 'tarif',  '24752' => 'tarif',
		'24156' => 'sozluk', '24157' => 'sozluk',
		'23489' => 'detay',  '23490' => 'detay',
		'23108' => 'liste',  '23109' => 'liste',
		'22607' => 'gezi',   '22608' => 'gezi',
		'23340' => 'rota',   '23342' => 'rota',
	);
	$gbc_ck = array(
		'tarif'  => array( 'tarif_malzemeler' ),
		'sozluk' => array( 'sozluk_kisa_tanim', 'sozluk_ana_tanim' ),
		'detay'  => array( 'detailed_hero_title' ),
		'liste'  => array( 'hero_custom_title_listed', 'main_card_listed_1' ),
		'rota'   => array( 'rota_custom_title_listed', 'rota_travel_post_listed' ),
		'gezi'   => array( 'info_country', 'hero_intro_text' ),
	);

	$gbc_dolu = function ( $k, $pid ) {
		$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : null;
		if ( is_array( $v ) ) {
			$bos = true;
			array_walk_recursive(
				$v,
				function ( $x ) use ( &$bos ) {
					if ( $x !== '' && $x !== null && $x !== false ) { $bos = false; }
				}
			);
			return ! $bos;
		}
		return ! empty( $v );
	};

	$gbc_ic = get_post_field( 'post_content', $id );
	if ( is_string( $gbc_ic ) && $gbc_ic !== '' ) {
		foreach ( $gbc_sb as $gbc_sid => $gbc_t ) {
			if ( strpos( $gbc_ic, 'wpcode id="' . $gbc_sid . '"' ) === false
				&& strpos( $gbc_ic, "wpcode id='" . $gbc_sid . "'" ) === false
				&& strpos( $gbc_ic, 'wpcode id=' . $gbc_sid ) === false ) {
				continue;
			}
			foreach ( $gbc_ck[ $gbc_t ] as $gbc_alan ) {
				if ( $gbc_dolu( $gbc_alan, $id ) ) { return $gbc_t; }
			}
		}
	}
	/* --------------- SABLON ONCE blogu biter, eski zincir yedek --------------- */

		if ( gbc_v3_dolu( 'tarif_malzemeler', $id ) )        { return 'tarif'; }
		if ( gbc_v3_dolu( 'sozluk_kisa_tanim', $id )
		  || gbc_v3_dolu( 'sozluk_ana_tanim', $id ) )        { return 'sozluk'; }
		if ( gbc_v3_dolu( 'detailed_hero_title', $id ) )     { return 'detay'; }
		if ( gbc_v3_dolu( 'hero_custom_title_listed', $id )
		  || gbc_v3_dolu( 'main_card_listed_1', $id ) )      { return 'liste'; }
		if ( gbc_v3_dolu( 'rota_custom_title_listed', $id )
		  || gbc_v3_dolu( 'rota_travel_post_listed', $id ) ) { return 'rota'; }
		if ( gbc_v3_dolu( 'info_country', $id )
		  || gbc_v3_dolu( 'hero_intro_text', $id ) )         { return 'gezi'; }
		if ( gbc_v3_dolu( 'rehber_icerik', $id ) )           { return 'blog'; }
		return 'yazi';
	}
}

/** Liste kartlarinin @type'i. Meta override > kategori/etiket > varsayilan. */
if ( ! function_exists( 'gbc_v3_liste_tipi' ) ) {
	function gbc_v3_liste_tipi( $id ) {
		$ov = gbc_v3_txt( get_post_meta( $id, 'gbc_schema_item_type', true ) );
		if ( $ov ) { return $ov; }
		/* GBC 2026-08-29: sozluk etiketli Liste sayfalari (Yunanca kelimeler,
		   yemek isimleri) mekan degil KAVRAM listesidir. Bu kural sablon
		   snippet'inde (23108) vardi ama motorun temizligi sablonun JSON-LD'sini
		   zaten siliyor, yani sayfaya cikan tip motorunki oluyordu ve 200 Yunanca
		   kelime TouristAttraction olarak isaretleniyordu. Kural motora tasindi.
		   Geri almak icin bu iki satiri sil. */
		if ( has_tag( 'sozluk', $id ) )                { return 'DefinedTerm'; }
		if ( has_category( 'lezzet-duraklari', $id ) ) { return 'Restaurant'; }
		/* Konaklama sayfalarinda kartlar cogu zaman BOLGE (Old Town, Faliraki)
		   oluyor, gercek otel degil. Yanlis isaretlemektense Place guvenli.
		   Kartlar gercekten otelse: post meta gbc_schema_item_type = Hotel */
		if ( has_tag( 'konaklama', $id ) )             { return 'Place'; }
		return 'TouristAttraction';
	}
}

/** Detay yer dugumunun @type'i. Meta override > etiket. Bos = yer dugumu yok. */
if ( ! function_exists( 'gbc_v3_detay_tipi' ) ) {
	function gbc_v3_detay_tipi( $id ) {
		$ov = gbc_v3_txt( get_post_meta( $id, 'gbc_schema_place_type', true ) );
		if ( $ov ) { return $ov; }
		if ( has_tag( 'restoranlar', $id ) )       { return 'Restaurant'; }
		if ( has_tag( 'gorulecek-yerler', $id ) )  { return 'TouristAttraction'; }
		if ( has_tag( 'pazarlar', $id ) )          { return 'ShoppingCenter'; }
		/* ulasim / vize gibi kavramsal sayfalar yer degildir */
		return '';
	}
}

/* ══════════════════════ Ana varlik: Article ══════════════════════ */

if ( ! function_exists( 'gbc_v3_article' ) ) {
	function gbc_v3_article( $id, $url, $baslik, $aciklama, $ek = array() ) {
		$kat = array(); $terms = get_the_terms( $id, 'category' );
		if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { $kat[] = $t->name; } }

		$kw = array(); $tags = get_the_terms( $id, 'post_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) { foreach ( $tags as $t ) { $kw[] = $t->name; } }

		/* Gorsel: @id referansi yerine dugumun kendisi. Ayni @id'yi kullandigi
		   icin 29013'un bastigi #primaryimage ile birlesir, cakismaz. */
		$img_id = get_post_thumbnail_id( $id );
		$img    = $img_id ? gbc_v3_img( $img_id ) : '';
		if ( $img ) { $img['@id'] = $url . '#primaryimage'; }

		$node = array(
			'@type'               => 'Article',
			'@id'                 => $url . '#article',
			'isPartOf'            => array( '@id' => $url . '#webpage' ),
			'mainEntityOfPage'    => array( '@id' => $url . '#webpage' ),
			/* 110 karakter siniri Google dokumanindan kalkti; baslik artik
			   kesilmiyor (kesme ".." ekleyip basligi sakatliyordu). */
			'headline'            => gbc_v3_txt( $baslik ),
			'name'                => gbc_v3_txt( $baslik ),
			'description'         => gbc_v3_txt( $aciklama, 300 ),
			'url'                 => $url,
			'datePublished'       => get_the_date( 'c', $id ),
			'dateModified'        => get_the_modified_date( 'c', $id ),
			/* author/publisher/image: sadece @id referansi vermek yerine adi ve
			   adresi de tasiyoruz. Kimlik dugumleri AYRI bir <script> blogunda
			   (29013) basildigi icin, referansin cozulememesi ihtimaline karsi
			   Article tek basina da gecerli kaliyor. Ayni @id -> dugum birlesir. */
			'author'              => array(
				'@type' => 'Person',
				'@id'   => 'https://gezginbirchef.com/halil-simir/#person',
				'name'  => 'Halil Şımır',
				'url'   => 'https://gezginbirchef.com/halil-simir/',
			),
			'publisher'           => array(
				'@type' => 'Organization',
				'@id'   => home_url( '/' ) . '#organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'inLanguage'          => 'tr-TR',
			/* isAccessibleForFree BILEREK YOK: bu property Rich Results Test'te
			   "Paywalled Content" ozelligini tetikliyor. Sitede odeme duvari
			   olmadigi icin hic basilmiyor (varsayilan zaten "ucretsiz"). */
			'articleSection'      => $kat ? implode( ', ', $kat ) : '',
			'keywords'            => $kw ? implode( ', ', $kw ) : '',
			'image'               => $img ?: '',
			/* speakable: Google yalnizca ABD/Ingilizce haber icerigi icin
			   destekliyor, tr-TR sayfalarda yok sayiliyor. Gurultu olmasin. */
		);
		return array_filter( array_merge( $node, $ek ), 'gbc_v3_bos_degil' );
	}
}

/** faq_1..10 ACF alanlarindan FAQPage dugumu.
 *  NOT: Google FAQ zengin sonucunu 07.05.2026'da kaldirdi; bu dugum SERP'te
 *  gorunmez. ChatGPT/Perplexity/Gemini gibi sistemler ve sesli asistanlar
 *  okumaya devam ettigi icin bilerek basiliyor. */
if ( ! function_exists( 'gbc_v3_faq' ) ) {
	function gbc_v3_faq( $id, $url ) {
		$sorular = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$s = gbc_v3_txt( gbc_v3_acf( 'faq_' . $i . '_question', $id ) );
			$c = gbc_v3_acf( 'faq_' . $i . '_answer', $id );
			$c = gbc_v3_txt( $c );
			if ( $s === '' || $c === '' ) { continue; }
			$sorular[] = array(
				'@type'          => 'Question',
				'name'           => $s,
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $c ),
			);
		}
		/* GBC 2026-08-29: faq_1..10 ACF grubu her sayfa tipine bagli degil,
		   bu yuzden cogu sayfada bos kaliyor. Liste (23108) ve Detay (23489)
		   sablonlari SSS'yi rehber_faq alanindan basiyor; ekranda gorunen
		   metin o. Sema ile ekrandaki icerik ayni olmak ZORUNDA oldugu icin,
		   faq_1..10 bossa ayni alandan okunuyor.
		   Bicim: "S: soru" / "C: cevap" satirlari.
		   Geri almak icin bu blogu sil. */
		if ( ! $sorular ) {
			$ham = get_post_meta( $id, 'rehber_faq', true );
			if ( $ham ) {
				$s = null; $c = '';
				foreach ( preg_split( '/\R/u', (string) $ham ) as $ln ) {
					$t = trim( $ln );
					if ( preg_match( '/^S\s*:\s*(.+)$/u', $t, $m ) ) {
						if ( $s !== null && trim( $c ) !== '' ) {
							$sorular[] = array( '@type' => 'Question', 'name' => gbc_v3_txt( $s ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => gbc_v3_txt( $c ) ) );
						}
						$s = $m[1]; $c = '';
					} elseif ( preg_match( '/^C\s*:\s*(.*)$/u', $t, $m ) ) {
						$c = $m[1];
					} elseif ( $s !== null && $t !== '' ) {
						$c .= ' ' . $t;
					}
				}
				if ( $s !== null && trim( $c ) !== '' ) {
					$sorular[] = array( '@type' => 'Question', 'name' => gbc_v3_txt( $s ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => gbc_v3_txt( $c ) ) );
				}
			}
		}
		if ( ! $sorular ) { return array(); }
		return array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'isPartOf'   => array( '@id' => $url . '#webpage' ),
			'inLanguage' => 'tr-TR',
			'mainEntity' => $sorular,
		);
	}
}

/* ══════════════════════ Graf uretimi ══════════════════════ */

if ( ! function_exists( 'gbc_v3_graph' ) ) {
	function gbc_v3_graph( $id ) {
		$url   = get_permalink( $id );
		$tur   = gbc_v3_tur( $id );
		$graph      = array();
		$ana        = ''; // WebPage.mainEntity icin birincil dugumun @id'si
		$webpage_ek = array(); // #webpage dugumune eklenecek property'ler

		if ( $tur === 'tarif' ) { return array(); }

		/* ---------- LISTE (hub) ---------- */
		if ( $tur === 'liste' ) {
			$baslik   = gbc_v3_acf( 'hero_custom_title_listed', $id ) ?: get_the_title( $id );
			$intro    = gbc_v3_acf( 'hero_intro_text_listed', $id );
			$oge_tipi = gbc_v3_liste_tipi( $id );

			$rehber    = gbc_v3_acf( 'travel_guide_listed', $id );
			$rehber_id = gbc_v3_pid( $rehber );
			$rehber_url = gbc_v3_permalink( $rehber );
			$adres     = gbc_v3_adres( $rehber_id );

			$ogeler = array(); $pos = 1;
			/* GBC 2026-08-29: rehber tipi Liste sayfalarinda kartlar birer
			   MEKAN degil birer BOLUM. Olculdu: vize rehberinde "Vize Reddi:
			   En Sik Nedenler" karti TouristAttraction olarak isaretleniyordu;
			   bu yanlis sinyal. Boyle sayfalarda ItemList hic basilmiyor,
			   ana varlik Article oluyor ($ana asagida #article'a dusuyor).
			   Kapatmak icin sayfa metasi:  gbc_itemlist_kapali = 1
			   Meta bos olan sayfalar (gezi listeleri) eskisi gibi calisir.
			   Geri almak icin bu blogu ve dongudeki $gbc_il_kapali kosulunu sil. */
			$gbc_il_kapali = trim( (string) get_post_meta( $id, 'gbc_itemlist_kapali', true ) ) !== '';
			for ( $i = 1; $i <= 50 && ! $gbc_il_kapali; $i++ ) {
				$k = gbc_v3_acf( 'main_card_listed_' . $i, $id );
				if ( ! is_array( $k ) || empty( $k['card_title_listed'] ) ) { continue; }

				$desc = isset( $k['card_desc_listed'] ) ? $k['card_desc_listed'] : '';
				$oge  = array(
					'@type'       => $oge_tipi,
					'@id'         => $url . '#oge-' . $i,
					'name'        => gbc_v3_isim_temiz( $k['card_title_listed'] ),
					'description' => gbc_v3_txt( $desc, 300 ),
					'url'         => $url . '#place-' . $i,
				);
				$im = gbc_v3_img( isset( $k['card_image_listed'] ) ? $k['card_image_listed'] : '' );
				if ( $im ) { $oge['image'] = $im['url']; }

				$g = gbc_v3_geo( isset( $k['card_map_code_listed'] ) ? $k['card_map_code_listed'] : '' );
				if ( $g ) {
					$oge['geo']    = gbc_v3_geo_node( $g );
					$oge['hasMap'] = gbc_v3_harita_url( $g );
				}
				/* LocalBusiness turevleri icin address ve priceRange Google'in
				   bekledigi alanlar; adres pillar'daki sehir/ulkeden geliyor. */
				if ( in_array( $oge_tipi, array( 'Restaurant', 'Hotel', 'LodgingBusiness', 'ShoppingCenter', 'CafeOrCoffeeShop', 'Store' ), true ) ) {
					if ( $adres ) { $oge['address'] = $adres; }
					$fy = gbc_v3_fiyat( $desc );
					if ( $fy ) { $oge['priceRange'] = $fy; }
					if ( $oge_tipi === 'Restaurant' ) {
						$mutfak = gbc_v3_mutfak( $rehber_id );
						if ( $mutfak ) { $oge['servesCuisine'] = $mutfak; }
					}
				}
				if ( $rehber_url ) { $oge['containedInPlace'] = array( '@id' => $rehber_url . '#destination' ); }

				$ogeler[] = array(
					'@type'    => 'ListItem',
					'position' => $pos,
					'item'     => array_filter( $oge, 'gbc_v3_bos_degil' ),
				);
				$pos++;
			}

			if ( $ogeler ) {
				$graph[] = array(
					'@type'            => 'ItemList',
					'@id'              => $url . '#itemlist',
					'name'             => gbc_v3_txt( $baslik ),
					'description'      => gbc_v3_txt( $intro, 300 ),
					'itemListOrder'    => 'https://schema.org/ItemListOrderAscending',
					'numberOfItems'    => count( $ogeler ),
					'itemListElement'  => $ogeler,
					'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				);
				$ana = $url . '#itemlist';
			}

			$ek = array();
			if ( $ogeler )     { $ek['about'] = array( '@id' => $url . '#itemlist' ); }
			if ( $rehber_url ) { $ek['isPartOf'] = array( array( '@id' => $url . '#webpage' ), array( '@id' => $rehber_url . '#article' ) ); }
			$graph[] = gbc_v3_article( $id, $url, $baslik, $intro, $ek );
			if ( ! $ana ) { $ana = $url . '#article'; }

			for ( $v = 1; $v <= 5; $v++ ) {
				$alan = ( $v === 1 ) ? 'hero_video_listed' : 'hero_video_listed_' . $v;
				$vid  = gbc_v3_yt( gbc_v3_acf( $alan, $id ) );
				if ( $vid ) { $graph[] = gbc_v3_video( $vid, $url . '#video-' . $v, $baslik, $intro, $id ); }
			}
		}

		/* ---------- DETAY (spoke) ---------- */
		elseif ( $tur === 'detay' ) {
			$baslik = gbc_v3_acf( 'detailed_hero_title', $id ) ?: get_the_title( $id );
			$intro  = gbc_v3_acf( 'detailed_hero_intro', $id );
			$ek     = array();

			$rehber     = gbc_v3_acf( 'detailed_main_guide_link', $id );
			$rehber_id  = gbc_v3_pid( $rehber );
			$rehber_url = gbc_v3_permalink( $rehber );

			$yer_tipi = gbc_v3_detay_tipi( $id );
			$g        = gbc_v3_geo_post( $id, 'detailed_map' );

			if ( $yer_tipi ) {
				$place = array(
					'@type'       => ( $yer_tipi === 'TouristAttraction' ) ? array( 'TouristAttraction', 'Place' ) : $yer_tipi,
					'@id'         => $url . '#place',
					'name'        => gbc_v3_txt( $baslik ),
					'description' => gbc_v3_txt( $intro, 300 ),
					'url'         => $url,
				);
				if ( $g ) {
					$place['geo']    = gbc_v3_geo_node( $g );
					$place['hasMap'] = gbc_v3_harita_url( $g );
				}
				$adres = gbc_v3_adres( $rehber_id );
				if ( $adres ) { $place['address'] = $adres; }
				$fy = gbc_v3_fiyat( gbc_v3_acf( 'detailed_main_content', $id ) );
				if ( $fy && in_array( $yer_tipi, array( 'Restaurant', 'Hotel', 'ShoppingCenter' ), true ) ) {
					$place['priceRange'] = $fy;
				}
				if ( $rehber_url ) { $place['containedInPlace'] = array( '@id' => $rehber_url . '#destination' ); }
				$graph[] = array_filter( $place, 'gbc_v3_bos_degil' );
				$ek['about'] = array( '@id' => $url . '#place' );
				$ana = $url . '#place';
			} elseif ( $rehber_url ) {
				/* Ulasim/vize gibi kavramsal spoke: yer degil, pillar'a bagla */
				$ek['about'] = array( '@id' => $rehber_url . '#destination' );
			}
			if ( $rehber_url ) {
				$ek['isPartOf'] = array( array( '@id' => $url . '#webpage' ), array( '@id' => $rehber_url . '#article' ) );
			}

			$graph[] = gbc_v3_article( $id, $url, $baslik, $intro, $ek );
			if ( ! $ana ) { $ana = $url . '#article'; }

			for ( $v = 1; $v <= 3; $v++ ) {
				$vid = gbc_v3_yt( gbc_v3_acf( 'detailed_video_' . $v, $id ) );
				if ( $vid ) { $graph[] = gbc_v3_video( $vid, $url . '#video-' . $v, $baslik, $intro, $id ); }
			}
		}

		/* ---------- GEZI (pillar) ---------- */
		elseif ( $tur === 'gezi' ) {
			$baslik = get_the_title( $id );
			$intro  = gbc_v3_acf( 'hero_intro_text', $id ) ?: gbc_v3_acf( 'intro_text', $id );
			$sehir  = gbc_v3_sehir_temiz( gbc_v3_acf( 'info_city', $id ) );
			$ulke   = gbc_v3_txt( gbc_v3_acf( 'info_country', $id ) );

			$dest = array(
				'@type'            => 'TouristDestination',
				'@id'              => $url . '#destination',
				'name'             => $sehir ?: gbc_v3_txt( $baslik ),
				'description'      => gbc_v3_txt( $intro, 300 ),
				'url'              => $url,
				'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			);
			$adres = gbc_v3_adres( $id );
			if ( $adres ) { $dest['address'] = $adres; }

			/* Kapak gorseli */
			$dgorsel = get_the_post_thumbnail_url( $id, 'full' );
			if ( $dgorsel ) { $dest['image'] = $dgorsel; }

			/* ACF'te dolu ama semaya hic girmeyen pratik bilgiler.
			   NOT: availableLanguage / currenciesAccepted schema.org'da Place
			   alani DEGIL (ContactPoint, LodgingBusiness vb. icin tanimli).
			   Dogru yer Thing/Place uzerindeki additionalProperty. */
			$ekbilgi = array();
			$dil = gbc_v3_txt( gbc_v3_acf( 'info_language', $id ) );
			if ( $dil ) {
				$dil = trim( preg_split( '/[.;]/u', $dil )[0] );
				if ( $dil ) { $ekbilgi[] = array( '@type' => 'PropertyValue', 'name' => 'Konuşulan dil', 'value' => $dil ); }
			}
			$prm = gbc_v3_txt( gbc_v3_acf( 'info_currency', $id ) );
			if ( $prm ) { $ekbilgi[] = array( '@type' => 'PropertyValue', 'name' => 'Para birimi', 'value' => $prm ); }
			$fis = gbc_v3_txt( gbc_v3_acf( 'info_plug', $id ) );
			if ( $fis ) { $ekbilgi[] = array( '@type' => 'PropertyValue', 'name' => 'Priz tipi', 'value' => $fis ); }
			$tel = gbc_v3_txt( gbc_v3_acf( 'info_phone_code', $id ) );
			if ( $tel ) { $ekbilgi[] = array( '@type' => 'PropertyValue', 'name' => 'Telefon kodu', 'value' => '+' . ltrim( $tel, '+' ) ); }
			if ( $ekbilgi ) { $dest['additionalProperty'] = $ekbilgi; }

			$g = gbc_v3_geo_post( $id, 'place_location' );
			if ( $g ) {
				$dest['geo']    = gbc_v3_geo_node( $g );
				$dest['hasMap'] = gbc_v3_harita_url( $g );
			}

			/* Silo: pillar'in bagli oldugu hub/spoke'lar */
			$rel = array( 'rel_places', 'rel_food', 'rel_stay', 'rel_trans', 'rel_shop',
			              'rel_routes', 'rel_night', 'rel_trip', 'rel_event', 'rel_hist',
			              'rel_budget', 'rel_photo', 'rel_kid' );
			$silo = array(); $cocuk = array(); $gorulen = array();
			foreach ( $rel as $r ) {
				$o  = gbc_v3_acf( $r, $id );
				$lu = gbc_v3_permalink( $o );
				$ln = gbc_v3_baslik( $o );
				if ( ! $lu || ! $ln ) { continue; }
				/* Ayni hub birden fazla rel_* alaninda secilmis olabilir
				   (orn. rel_places ve rel_food ayni "nerede yenir" sayfasi).
				   Tekrarli @id, semada ayni dugumu iki kez bildirmek demek. */
				if ( isset( $gorulen[ $lu ] ) ) { continue; }
				$gorulen[ $lu ] = true;
				$silo[]  = $lu;                                  // WebPage.significantLink
				$cocuk[] = array( '@id' => $lu . '#article' );
			}
			/* touristType'i sayfanin KENDI icerigine gore uret.
			   ONCEDEN: her destinasyona ayni dort etiket basiliyordu; Prag, Viyana,
			   Budapeste gibi denizi olmayan sehirlerde "Plaj tatili" yaziyordu ve
			   her sayfada ayni oldugu icin sinyal degeri sifirdi. */
			$tt = array( 'Kültür turizmi' );
			if ( gbc_v3_dolu( 'card_hist_list', $id ) || gbc_v3_dolu( 'rel_hist', $id ) ) { $tt[] = 'Tarih turizmi'; }
			if ( gbc_v3_dolu( 'card_food_list', $id ) || gbc_v3_dolu( 'rel_food', $id ) ) { $tt[] = 'Gastronomi turizmi'; }
			if ( gbc_v3_dolu( 'info_island', $id ) ) { $tt[] = 'Plaj tatili'; }
			if ( gbc_v3_dolu( 'card_night_list', $id ) || gbc_v3_dolu( 'rel_night', $id ) ) { $tt[] = 'Gece hayatı'; }
			if ( gbc_v3_dolu( 'card_shop_list', $id ) || gbc_v3_dolu( 'rel_shop', $id ) ) { $tt[] = 'Alışveriş turizmi'; }
			if ( gbc_v3_dolu( 'card_kid_list', $id ) || gbc_v3_dolu( 'rel_kid', $id ) ) { $tt[] = 'Aile tatili'; }
			$dest['touristType'] = array_values( array_unique( $tt ) );

			/* includesAttraction: TouristDestination'in en ayirt edici alani.
			   Pillar'in isaret ettigi hub'lardaki yer listelerine baglanir. */
			$cekim = array(); $cekim_gorulen = array();
			foreach ( array( 'rel_places', 'rel_food', 'rel_night', 'rel_shop' ) as $r_c ) {
				$lu_c = gbc_v3_permalink( gbc_v3_acf( $r_c, $id ) );
				if ( ! $lu_c || isset( $cekim_gorulen[ $lu_c ] ) ) { continue; }
				$cekim_gorulen[ $lu_c ] = true;
				$cekim[] = array( '@id' => $lu_c . '#itemlist' );
			}
			/* v4.5: includesAttraction kaldirildi; ItemList capasi TouristAttraction degil ve bu sayfanin grafiginde tanimli degildi. Silo baglantilari WebPage.significantLink ve subjectOf ile veriliyor. */

			if ( $cocuk ) { $dest['subjectOf'] = $cocuk; }
		/* v4.6 (21 Eyl 2026): gezi sayfasinin KENDI duraklari ve mekanlari.
		   Meta gbc_schema_yerler, satir basina: "Tur | Ad | enlem,boylam | #capa | adres".
		   Tur: TouristAttraction, Beach, Landmark, FoodEstablishment, Bakery, IceCreamShop,
		   LodgingBusiness, Museum... Koordinat Google Haritalar kaydindan (harita KML'iyle ayni). */
		/* v1.47.4: alan boşsa sayfanın kendi haritasından okunan duraklar (inc/kml-yerler.php). */
		$yer_ham = function_exists( 'gbc_kml_sema_metni' ) ? gbc_kml_sema_metni( $id ) : (string) get_post_meta( $id, 'gbc_schema_yerler', true );
		if ( $yer_ham !== '' ) {
			$yerler = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $yer_ham ) as $sat ) {
				$p = array_map( 'trim', explode( '|', $sat ) );
				if ( count( $p ) < 3 || $p[1] === '' ) { continue; }
				$tip = preg_replace( '/[^A-Za-z]/', '', $p[0] );
				$yer = array( '@type' => ( $tip !== '' ) ? $tip : 'TouristAttraction', 'name' => gbc_v3_txt( $p[1], 110 ) );
				$gg = gbc_v3_geo( $p[2] );
				if ( $gg ) { $yer['geo'] = gbc_v3_geo_node( $gg ); }
				if ( ! empty( $p[3] ) ) { $yer['url'] = $url . ( ( $p[3][0] === '#' ) ? $p[3] : '#' . $p[3] ); }
				if ( ! empty( $p[4] ) ) { $yer['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => gbc_v3_txt( $p[4], 120 ) ); }
				$yerler[] = $yer;
			}
			if ( $yerler ) { $dest['includesAttraction'] = $yerler; }
		}
			$graph[] = array_filter( $dest, 'gbc_v3_bos_degil' );
			$ana = $url . '#destination';

			/* Hub -> spoke baglantisi ItemList ile DEGIL significantLink ile
			   veriliyor. ItemList + url, Rich Results Test'te "Carousels"
			   ozelligini tetikliyordu; gezi rehberi Google'in karusel destekledigi
			   dikeylerden (Recipe/Restaurant/Movie/Course/Event) biri olmadigi
			   icin o isaret hicbir zengin sonuca donusmuyor, sadece gurultu.
			   significantLink ayni iliskiyi standarda uygun sekilde anlatir. */
			$webpage_ek = array();
			if ( $silo ) { $webpage_ek['significantLink'] = $silo; }

			$graph[] = gbc_v3_article( $id, $url, $baslik, $intro,
				array( 'about' => array( '@id' => $url . '#destination' ) ) );

			/* Rota metinleri -> TouristTrip */
			$rotalar = array(
				'route_short_desc'  => '2 Günlük Rota',
				'route_medium_desc' => '4 Günlük Rota',
				'route_long_desc'   => '7 Günlük Rota',
			);
			$ri = 1;
			foreach ( $rotalar as $alan => $ad ) {
				$d = gbc_v3_acf( $alan, $id );
				if ( ! gbc_v3_txt( $d ) ) { continue; }
				$graph[] = array_filter( array(
					'@type'       => 'TouristTrip',
					'@id'         => $url . '#trip-' . $ri,
					'name'        => gbc_v3_txt( $baslik ) . ', ' . $ad,
					'description' => gbc_v3_txt( $d, 300 ),
					'url'         => $url,
					'touristType' => 'Bağımsız gezgin',
					'provider'    => array( '@id' => home_url( '/' ) . '#organization' ),
					'itinerary'   => array( '@id' => $url . '#destination' ),
				), 'gbc_v3_bos_degil' );
				$ri++;
			}

			$hero_ham = gbc_v3_acf( 'hero_video', $id );
			$vid      = gbc_v3_yt( $hero_ham );
			if ( $vid ) {
				$graph[] = gbc_v3_video(
					$vid, $url . '#video-1', $baslik, $intro, $id,
					$hero_ham, gbc_v3_acf( 'hero_video_chapters', $id ) . "\n" . (string) get_post_meta( $id, 'gz_video_duraklar', true )
				);
			}
			for ( $v = 1; $v <= 10; $v++ ) {
				$ham = gbc_v3_acf( 'video_' . $v . '_url', $id );
				$vv  = gbc_v3_yt( $ham );
				if ( $vv && $vv !== $vid ) {
					$graph[] = gbc_v3_video(
						$vv, $url . '#video-' . ( $v + 1 ), $baslik, $intro, $id,
						$ham, gbc_v3_acf( 'video_' . $v . '_chapters', $id )
					);
				}
			}
		}

		/* ---------- ROTA ---------- */
		elseif ( $tur === 'rota' ) {
			$baslik = gbc_v3_acf( 'rota_custom_title_listed', $id ) ?: get_the_title( $id );
			$intro  = gbc_v3_acf( 'rota_intro_text_listed', $id );

			$duraklar = array(); $p = 1;
			foreach ( array( 'rota_travel_post_listed', 'rota_food_post_listed' ) as $alan ) {
				$ham = gbc_v3_acf( $alan, $id );
				if ( ! is_string( $ham ) || $ham === '' ) { continue; }
				foreach ( preg_split( '/\r\n|\r|\n/', $ham ) as $sat ) {
					$ad = trim( preg_replace( '/\s*[\[\(].*$/u', '', trim( $sat ) ) );
					if ( $ad === '' ) { continue; }
					$duraklar[] = array( '@type' => 'ListItem', 'position' => $p, 'name' => gbc_v3_txt( $ad ) );
					$p++;
				}
			}
			$ek = array();
			if ( $duraklar ) {
				$graph[] = array(
					'@type'           => 'ItemList',
					'@id'             => $url . '#itemlist',
					'name'            => gbc_v3_txt( $baslik ),
					'description'     => gbc_v3_txt( $intro, 300 ),
					'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
					'numberOfItems'   => count( $duraklar ),
					'itemListElement' => $duraklar,
				);
				$graph[] = array_filter( array(
					'@type'            => 'TouristTrip',
					'@id'              => $url . '#trip',
					'name'             => gbc_v3_txt( $baslik ),
					'description'      => gbc_v3_txt( $intro, 300 ),
					'url'              => $url,
					'itinerary'        => array( '@id' => $url . '#itemlist' ),
					'provider'         => array( '@id' => home_url( '/' ) . '#organization' ),
					'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				), 'gbc_v3_bos_degil' );
				$ek['about'] = array( '@id' => $url . '#trip' );
				$ana = $url . '#trip';
			}
			foreach ( array( 'rota_travel_link_listed', 'rota_food_link_listed', 'rota_travel_list_link_listed' ) as $r ) {
				$lu = gbc_v3_permalink( gbc_v3_acf( $r, $id ) );
				if ( $lu ) { $ek['isPartOf'] = array( array( '@id' => $url . '#webpage' ), array( '@id' => $lu . '#article' ) ); break; }
			}
			$graph[] = gbc_v3_article( $id, $url, $baslik, $intro, $ek );
			if ( ! $ana ) { $ana = $url . '#article'; }

			for ( $v = 1; $v <= 5; $v++ ) {
				$alan = ( $v === 1 ) ? 'rota_video_listed' : 'rota_video_listed_' . $v;
				$vid  = gbc_v3_yt( gbc_v3_acf( $alan, $id ) );
				if ( $vid ) { $graph[] = gbc_v3_video( $vid, $url . '#video-' . $v, $baslik, $intro, $id ); }
			}
		}

		/* ---------- SOZLUK ---------- */
		elseif ( $tur === 'sozluk' ) {
			$baslik = get_the_title( $id );
			$kisa   = gbc_v3_acf( 'sozluk_kisa_tanim', $id );
			$ana_t  = gbc_v3_acf( 'sozluk_ana_tanim', $id );

			$term = array(
				'@type'            => 'DefinedTerm',
				'@id'              => $url . '#term',
				'name'             => gbc_v3_txt( $baslik ),
				'description'      => gbc_v3_txt( $kisa ?: $ana_t, 300 ),
				'url'              => $url,
				'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				'inDefinedTermSet' => array(
					'@type' => 'DefinedTermSet',
					'@id'   => gbc_v3_sozluk_url() . '#termset',
					'name'  => 'Gezginbirchef Mutfak Sözlüğü',
					'url'   => gbc_v3_sozluk_url(),
				),
			);
			$alt = gbc_v3_txt( gbc_v3_acf( 'sozluk_diger_isimler', $id ) );
			if ( $alt ) { $term['alternateName'] = $alt; }
			$graph[] = $term;
			$ana = $url . '#term';

			$graph[] = gbc_v3_article( $id, $url, $baslik, $kisa ?: $ana_t,
				array( 'about' => array( '@id' => $url . '#term' ) ) );

			$vid = gbc_v3_yt( gbc_v3_acf( 'sozluk_video_url', $id ) );
			if ( $vid ) { $graph[] = gbc_v3_video( $vid, $url . '#video-1', $baslik, $kisa, $id ); }
		}

		/* ---------- BLOG ve duz yazi ---------- */
		else {
			$baslik  = get_the_title( $id );
			$ozet    = get_the_excerpt( $id );
			$graph[] = gbc_v3_article( $id, $url, $baslik, $ozet );
			$ana     = $url . '#article';

			/* VLOG yazilari: video ACF alaninda degil, dogrudan yazi icinde
			   gomulu. Eskiden bu sayfalarda hic VideoObject basilmiyordu -
			   video zengin sonucu sansi sifirdi. Icerikteki ilk 3 YouTube
			   videosu semaya aliniyor. Tarih/sure snippet 94'ten geliyor. */
			$icerik = (string) get_post_field( 'post_content', $id );
			$bulunan = array();
			if ( preg_match_all(
				'#(?:youtube(?:-nocookie)?\.com/(?:embed/|v/|watch\?(?:[^"\']*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#',
				$icerik, $mm ) ) {
				foreach ( $mm[1] as $vv ) {
					if ( ! in_array( $vv, $bulunan, true ) ) { $bulunan[] = $vv; }
					if ( count( $bulunan ) >= 3 ) { break; }
				}
			}
			$vi = 1;
			foreach ( $bulunan as $vv ) {
				/* name benzersiz olmali (Google: "use unique text in name") */
				$vad = ( $vi === 1 ) ? $baslik : $baslik . ', Video ' . $vi;
				$graph[] = gbc_v3_video( $vv, $url . '#video-' . $vi, $vad, $ozet, $id );
				$vi++;
			}
		}

		/* FAQ (varsa) - tum icerik turlerinde gecerli */
		$faq = gbc_v3_faq( $id, $url );
		if ( $faq ) { $graph[] = $faq; }

		/* WebPage'e (29013 basiyor) kismi dugum: birincil varligi isaretle.
		   Ayni @id ile ek property gonderiliyor, JSON-LD tuketicileri birlestirir. */
		if ( $ana ) {
			$graph[] = array_merge( array(
				'@type'      => 'WebPage',
				'@id'        => $url . '#webpage',
				'url'        => $url,
				'about'      => array( '@id' => $ana ),
				'mainEntity' => array( '@id' => $ana ),
			), $webpage_ek );
		} elseif ( $webpage_ek ) {
			$graph[] = array_merge( array(
				'@type' => 'WebPage',
				'@id'   => $url . '#webpage',
				'url'   => $url,
			), $webpage_ek );
		}

		return $graph;
	}
}

/* ══════════════════════ Cikti ══════════════════════ */

if ( ! function_exists( 'gbc_v3_yaz' ) ) {
	function gbc_v3_yaz() {
		if ( is_admin() || ! is_singular( 'post' ) ) { return; }
		if ( ! empty( $GLOBALS['gbc_v3_done'] ) ) { return; }
		$id = get_the_ID();
		if ( ! $id ) { return; }
		if ( gbc_v3_tur( $id ) === 'tarif' ) { return; }

		/* DUZELTME 21 Eylul 2026: graf ONCE uretiliyor, bayraklar SONRA set ediliyor. Onceki sirada bos graf donen bir sayfa hem kendi ana varligini basmiyor hem de gbc_main_entity yuzunden 29013'un yedek Article'i devreye girmiyordu; sayfa ana varliksiz kaliyordu. */ $graph=gbc_v3_graph($id); if ( ! $graph ) { return; } $GLOBALS['gbc_v3_done']     = true;
		$GLOBALS['gbc_main_entity'] = true; // Core 29013 yedek Article'i basmasin

		// graf yukarida uretildi (21 Eylul 2026)
		if ( ! $graph ) { return; }

		echo "\n<!-- GBC Sema Motoru v4.3 -->\n"
		   . '<script type="application/ld+json">'
		   . wp_json_encode(
				array( '@context' => 'https://schema.org', '@graph' => array_values( $graph ) ),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			 )
		   . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/* ══════════════════════ Eski bloklari sustur ══════════════════════
   Sablon snippet'lerine (22607/23108/23489/23340/26922/24156) HIC DOKUNMADAN,
   onlarin bastigi eski JSON-LD bloklarini cikti asamasinda temizler.
   Boylece kisa kodlar, kartlar, tablolar, CSS — hicbiri etkilenmez;
   sadece <script type="application/ld+json"> bloklari cikarilir.

   GUVENLIK: yalnizca motor o sayfada gercekten sema bastiysa
   ($GLOBALS['gbc_v3_done']) temizlik yapar. Motor calismadiysa
   (orn. Tarif sayfalari) hicbir sey silinmez — sayfa asla semasiz kalmaz. */

if ( ! function_exists( 'gbc_v3_eski_sema_sil' ) ) {
	function gbc_v3_eski_sema_sil( $html ) {
		if ( ! is_string( $html ) || strpos( $html, 'application/ld+json' ) === false ) { return $html; }
		$temiz = preg_replace(
			'#<script[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>.*?</script>#is',
			'',
			$html
		);
		return ( null === $temiz ) ? $html : $temiz; // regex hatasi olursa orijinali koru
	}
}

if ( ! function_exists( 'gbc_v3_temizlik_aktif' ) ) {
	function gbc_v3_temizlik_aktif() {
		return ! is_admin() && is_singular( 'post' ) && ! empty( $GLOBALS['gbc_v3_done'] );
	}
}

/* 1) the_content icinde basilanlar: Liste (23108), Detay (23489),
      Gezi (22607), Rota (23340), Sozluk (24156) */
add_filter( 'the_content', function ( $html ) {
	return gbc_v3_temizlik_aktif() ? gbc_v3_eski_sema_sil( $html ) : $html;
}, 999 );

/* 2) wp_footer(5) icinde basilanlar: Blog (26922).
      Sadece 4 ile 6 arasindaki dar pencerede tampon alinir. */
add_action( 'wp_footer', function () {
	if ( ! gbc_v3_temizlik_aktif() ) { return; }
	$GLOBALS['gbc_v3_ob'] = ob_get_level();
	ob_start();
}, 4 );

add_action( 'wp_footer', function () {
	if ( ! gbc_v3_temizlik_aktif() ) { return; }
	if ( ! isset( $GLOBALS['gbc_v3_ob'] ) ) { return; }
	if ( ob_get_level() <= (int) $GLOBALS['gbc_v3_ob'] ) { return; } // tampon baskasi tarafindan kapatilmis
	echo gbc_v3_eski_sema_sil( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput
}, 6 );

/* Kayit — WPCode ekleme moduna bagli olmayan guvenli bootstrap.
   Snippet "Run Everywhere" ile yuklenirse wp_head(98) calisir (tercih edilen).
   "Site Wide Footer" ile yuklenirse wp_head coktan atesledigi icin wp_footer(3)
   devreye girer. Ikisi de gectiyse dogrudan calisir. gbc_v3_done bayragi
   ciftlemeyi engelliyor. */
if ( ! function_exists( 'gbc_v3_baglat' ) ) {
	function gbc_v3_baglat() {
		if ( ! did_action( 'wp_head' ) ) {
			add_action( 'wp_head', 'gbc_v3_yaz', 98 );
		}
		if ( ! did_action( 'wp_footer' ) ) {
			add_action( 'wp_footer', 'gbc_v3_yaz', 3 );
		}
		if ( did_action( 'wp_head' ) && did_action( 'wp_footer' ) ) {
			gbc_v3_yaz();
		}
	}
}
gbc_v3_baglat();

/* ══ ICINDEKILER (TOC) · v6 · SUNUCUDA BASILIR · 28 Eylul 2026 ══
   ONCEKI SURUM NEDEN DEGISTI (olculdu, tahmin degil):
   Kutu JavaScript ile, sayfa boyandiktan SONRA H1'in altina ekleniyordu.
   Masaustunde kutu acik geldigi icin 213-432 piksellik bir blok sonradan
   araya giriyor, altindaki her sey asagi kayiyordu. PageSpeed'de Gezi
   rehberi masaustu CLS = 0.623 (esik 0.1). Mobilde kutu kapali (54 px)
   geldigi icin kayma zaten dusuktu.

   COZUM: kutu artik PHP tarafinda, the_content icinde uretilir; tarayici
   HTML'i ilk okudugunda kutu zaten yerindedir. Kaymanin kaynagi ortadan
   kalkar, yer ayirma yamasi gerekmez. Basliklara id'ler de sunucuda verilir.
   JavaScript artik kutuyu OLUSTURMAZ; yalniz ac/kapa, yumusak kaydirma,
   okundu isareti ve ilerleme cubugunu bagiar. Sunucu tarafi calismazsa
   (beklenmedik sablon) eski kurucu yedek olarak devrededir.

   Oncelik 15: sablon kisa kodu do_shortcode(11/12) ile acildiktan SONRA,
   "Ilgili Yazilar" (20) ve yazar kutusu (20) eklenmeden ONCE calisir —
   boylece o bolumlerin h2'leri listeye girmez (eski JS bunu closest() ile
   eliyordu, artik sirayla eleniyor).
*/

if ( ! function_exists( 'gbc_toc_slug' ) ) {
/** JS surumuyle birebir ayni slug. Eski ic bag adresleri bozulmasin diye
    adim sirasi da aynen korundu: kucult -> harf esle -> temizle -> kirp. */
function gbc_toc_slug( $metin, $eski = false ) {
	$t = trim( (string) $metin );
	/* v1.47.6: başlık metni HTML kodlu geliyordu (Tasso&#039;dan), kesme işareti id'ye "039" diye giriyordu.
	   Artık önce çözülüyor: "piazza-tasso-dan". $eski=true eski biçimi verir (takma çapa için). */
	if ( ! $eski ) { $t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
	$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $t, 'UTF-8' ) : strtolower( $t );
	$t = strtr( $t, array(
		'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u',
		'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u',
	) );
	$t = preg_replace( '/[^a-z0-9]+/', '-', $t );
	$t = trim( (string) $t, '-' );
	$t = substr( $t, 0, 60 );
	return '' !== $t ? $t : 'bolum';
}
}

if ( ! function_exists( 'gbc_toc_uygun_mu' ) ) {
/** Eski JS kosullarinin aynisi: tekil icerik, ana sayfa haric,
    duz sayfalarda yalniz GBC sablonu kullananlar. */
function gbc_toc_uygun_mu() {
	if ( is_admin() || ! is_singular() || is_front_page() ) { return false; }
	if ( is_page() ) {
		$pid = get_queried_object_id();
		$ic  = $pid ? get_post_field( 'post_content', $pid ) : '';
		if ( ! is_string( $ic ) || false === strpos( $ic, '[wpcode' ) ) { return false; }
	}
	return true;
}
}

if ( ! function_exists( 'gbc_toc_basliklara_id' ) ) {
/**
 * Verilen başlık etiketini (h2 ya da h3) sırayla gezer, id'si olmayana id
 * verir ve listeye ekler. İçindekiler kutusu bu listeden kurulur.
 *
 * @param string $html        İçerik.
 * @param string $etiket      'h2' ya da 'h3'.
 * @param array  $ogeler      (referans) bulunan başlıklar.
 * @param array  $kullanilan  (referans) sayfada zaten kullanılan id'ler.
 * @return string
 */
function gbc_toc_basliklara_id( $html, $etiket, &$ogeler, &$kullanilan ) {
	return (string) preg_replace_callback(
		'/<' . $etiket . '\b([^>]*)>(.*?)<\/' . $etiket . '>/is',
		static function ( $m ) use ( &$ogeler, &$kullanilan, $etiket ) {
			$nitelik = $m[1];
			$ic      = $m[2];

			$metin = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $ic ) ) );
			if ( '' === $metin ) { return $m[0]; }

			if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $nitelik, $v ) ) {
				$id = $v[1];
			} else {
				$taban = gbc_toc_slug( $metin );
				$id    = $taban;
				$k     = 1;
				while ( isset( $kullanilan[ $id ] ) ) { $id = $taban . '-' . ( $k++ ); }
				$nitelik .= ' id="' . esc_attr( $id ) . '"';
				/* v1.47.6: eski biçim ("-039-", "-amp-") farklıysa takma çapa: eski bağlantılar da aynı başlığa iner. */
				$eski_id = gbc_toc_slug( $metin, true );
				if ( $eski_id !== $taban && ! isset( $kullanilan[ $eski_id ] ) ) {
					$kullanilan[ $eski_id ] = true;
					$ic = '<span id="' . esc_attr( $eski_id ) . '" class="gbc-eski-capa" aria-hidden="true"></span>' . $ic;
				}
			}
			$kullanilan[ $id ] = true;
			$ogeler[] = array( 'id' => $id, 'metin' => $metin );

			return '<' . $etiket . $nitelik . '>' . $ic . '</' . $etiket . '>';
		},
		$html
	);
}
}

if ( ! function_exists( 'gbc_toc_ek_basliklar' ) ) {
/**
 * Gerçek başlık etiketi KULLANMAYAN şablonların bölüm başlıkları.
 *
 * Ölçüldü (28 Eylül 2026, /bolonya-bologna-gezi-rotalari-plan/): Rota
 * şablonu (v11) sayfaya tek bir h2 ya da h3 basmıyor; bölüm başlıkları
 * <div class="v11-block-header"> gibi. Kutu bu yüzden hiç kurulamıyordu.
 * Bu sınıflar İçindekiler için başlık sayılır — etiketleri DEĞİŞTİRİLMEZ,
 * yalnızca id verilip listeye alınır, böylece görünüm hiç bozulmaz.
 */
function gbc_toc_ek_basliklar() {
	return (array) apply_filters( 'gbc_toc_ek_basliklar', array(
		'v11-block-header',
		'v11-controls-header',
	) );
}
}

if ( ! function_exists( 'gbc_toc_sinifla_id' ) ) {
/**
 * Verilen sınıfları taşıyan div/span/p'lere id verir ve listeye ekler.
 * TEK GEÇİŞ: bütün sınıflar tek bir desende birleştirilir, böylece
 * başlıklar SAYFADAKİ SIRAYLA listelenir (sınıf sırasıyla değil).
 */
function gbc_toc_sinifla_id( $html, $siniflar, &$ogeler, &$kullanilan ) {
	$siniflar = array_values( array_filter( array_map( 'strval', (array) $siniflar ) ) );
	if ( ! $siniflar ) { return $html; }

	$kalip = array();
	foreach ( $siniflar as $sinif ) { $kalip[] = preg_quote( $sinif, '#' ); }
	$kalip = implode( '|', $kalip );

	return (string) preg_replace_callback(
		'#<(div|span|p)\b([^>]*\bclass="[^"]*\b(?:' . $kalip . ')\b[^"]*"[^>]*)>(.*?)</\1>#is',
		static function ( $m ) use ( &$ogeler, &$kullanilan ) {
			$etiket  = $m[1];
			$nitelik = $m[2];
			$ic      = $m[3];

			$metin = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $ic ) ) );
			if ( '' === $metin ) { return $m[0]; }

			if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $nitelik, $v ) ) {
				$id = $v[1];
			} else {
				$taban = gbc_toc_slug( $metin );
				$id    = $taban;
				$k     = 1;
				while ( isset( $kullanilan[ $id ] ) ) { $id = $taban . '-' . ( $k++ ); }
				$nitelik .= ' id="' . esc_attr( $id ) . '"';
				/* v1.47.6: eski biçim ("-039-", "-amp-") farklıysa takma çapa: eski bağlantılar da aynı başlığa iner. */
				$eski_id = gbc_toc_slug( $metin, true );
				if ( $eski_id !== $taban && ! isset( $kullanilan[ $eski_id ] ) ) {
					$kullanilan[ $eski_id ] = true;
					$ic = '<span id="' . esc_attr( $eski_id ) . '" class="gbc-eski-capa" aria-hidden="true"></span>' . $ic;
				}
			}
			$kullanilan[ $id ] = true;
			$ogeler[] = array( 'id' => $id, 'metin' => $metin );

			return '<' . $etiket . $nitelik . '>' . $ic . '</' . $etiket . '>';
		},
		$html
	);

}
}

if ( ! function_exists( 'gbc_toc_lejant_atla' ) ) {
/**
 * v1 "Liste rehberi" şablonunda İçindekiler basılmaz.
 *
 * Bu kural eski JS sürümünden devralındı: sayfada v1-lejant (renk
 * göstergesi) varsa kutu atlanıyordu. 28 Eylül 2026'da ölçüldü —
 * /italyanca-kelimeler/ (23 h2, 19 lejant), /bolonya-…/ (29 h2, 27),
 * /ipsala-sinir-kapisi/ (29 h2, 23), /macaristan-vize-rehberi/ (25 h2,
 * 19), /atina-nerede-kalinir/ (22 h2, 17) — hiçbirinde kutu yok ve
 * başlıkların hiçbirinde id yok. Karşılaştırma: /budapeste-gezi-rehberi/
 * (Gezi şablonu, 0 lejant) kutuyu basıyor, 18 h2'nin 16'sı id'li.
 *
 * Kural KASITLI olduğu için Kontrol Merkezi bu sayfalarda İçindekiler'i
 * "eksik" değil "gerekmez" sayar. Bu şablonlarda da kutu istenirse:
 *   add_filter( 'gbc_toc_lejant_atla', '__return_false' );
 */
function gbc_toc_lejant_atla() {
	return (bool) apply_filters( 'gbc_toc_lejant_atla', true );
}
}

if ( ! function_exists( 'gbc_toc_kutu_html' ) ) {
/** İçindekiler kutusunun işaretlemesi. Tek yerden üretilir. */
function gbc_toc_kutu_html( $ogeler ) {
	$liste = '';
	foreach ( $ogeler as $i => $o ) {
		$liste .= '<li><a href="#' . esc_attr( $o['id'] ) . '" data-id="' . esc_attr( $o['id'] ) . '">'
			. '<span class="n">' . ( $i + 1 ) . '</span><span>' . esc_html( $o['metin'] ) . '</span></a></li>';
	}
	$ikon_liste = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
	$ikon_ok    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';

	return '<div id="gbc-toc"' . ( count( $ogeler ) > 3 ? ' class="gbc-2"' : '' ) . '>'
		. '<div class="gbc-h" role="button" tabindex="0" aria-expanded="true">'
		. '<span class="gbc-t">' . $ikon_liste . 'İçindekiler <span class="gbc-c">' . count( $ogeler ) . ' bölüm</span></span>'
		. '<button class="gbc-x" type="button" aria-label="Aç/Kapat">' . $ikon_ok . '</button>'
		. '</div><div class="gbc-b"><ol>' . $liste . '</ol></div></div>';
}
}

if ( ! function_exists( 'gbc_toc_uygula' ) ) {
/**
 * Basliklara id verir ve icindekiler kutusunu HTML'e gomer.
 * Cikti, eski JS'in urettigi isaretlemenin birebir aynisi — ayni CSS calisir.
 */
function gbc_toc_uygula( $html ) {

	if ( ! is_string( $html ) || '' === $html ) { return $html; }
	if ( ! gbc_toc_uygun_mu() ) { return $html; }
	if ( ! in_the_loop() || ! is_main_query() ) { return $html; }
	if ( false !== strpos( $html, 'id="gbc-toc"' ) ) { return $html; } /* ciftlenme */
	if ( gbc_toc_lejant_atla() && false !== strpos( $html, 'v1-lejant' ) ) { return $html; }

	/* 1) Mevcut id'leri topla — uretilen slug'lar bunlarla carpismasin. */
	$kullanilan = array();
	if ( preg_match_all( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $m_id ) ) {
		foreach ( $m_id[1] as $i ) { $kullanilan[ $i ] = true; }
	}

	/* 2) Başlıkları sırayla gez: id yoksa ver, listeye ekle.
	      ÖNCE h2. Üç h2 yoksa h3'e düşülür — Rota şablonunda sayfada hiç
	      h2 yok, bölümler h3 ("1. Gün", "2. Gün"…). Eskiden kutu bu yüzden
	      hiç basılmıyordu: betik yükleniyor ama üç başlık bulunamıyordu
	      (28 Eylül 2026'da ölçüldü). Karışık liste üretmemek için ya hepsi
	      h2 ya hepsi h3 — ikisi birden değil. */
	$ogeler = array();
	$html   = gbc_toc_basliklara_id( $html, 'h2', $ogeler, $kullanilan );

	if ( count( $ogeler ) < 3 ) {
		$ogeler_h3 = array();
		$html      = gbc_toc_basliklara_id( $html, 'h3', $ogeler_h3, $kullanilan );
		if ( count( $ogeler_h3 ) >= 3 ) { $ogeler = $ogeler_h3; }
	}

	/* Üçüncü basamak: gerçek başlık etiketi hiç kullanmayan şablonlar
	   (Rota/v11). Etiket değişmez, yalnız id verilir. */
	if ( count( $ogeler ) < 3 ) {
		$ogeler_ek = array();
		$html      = gbc_toc_sinifla_id( $html, gbc_toc_ek_basliklar(), $ogeler_ek, $kullanilan );
		if ( count( $ogeler_ek ) >= 3 ) { $ogeler = $ogeler_ek; }
	}

	if ( count( $ogeler ) < 3 ) { return $html; }

	/* 3) Kutuyu kur. Isaretleme eski JS ciktisiyla ayni; CSS degismedi. */
	$kutu = gbc_toc_kutu_html( $ogeler );

	/* 4) Yerlesim: icerikte H1 varsa hemen altina, yoksa en basa.
	      Eski JS de H1'in altini hedefliyordu; gorunum degismesin diye ayni. */
	if ( preg_match( '/<\/h1>/i', $html, $h1, PREG_OFFSET_CAPTURE ) ) {
		$yer  = $h1[0][1] + strlen( $h1[0][0] );
		$html = substr_replace( $html, $kutu, $yer, 0 );
	} else {
		$html = $kutu . $html;
	}
	$GLOBALS['gbc_toc_basildi'] = true;

	return $html;
}
}
add_filter( 'the_content', 'gbc_toc_uygula', 15 );

/* ============================================================
   GEÇ TAMPON — başlıkları the_content dışında basan şablonlar
   ------------------------------------------------------------
   ÖLÇÜLEN SORUN (28 Eylül 2026): v1 "Liste rehberi" şablonunda
   bölüm başlıkları ACF alanlarından şablonun kendisi tarafından
   basılıyor; the_content'e hiç uğramıyorlar. Sayfada 23 tane
   <h2 class="v1-card-h2"> olmasına rağmen hiçbirinin id'si yoktu
   ve İçindekiler kutusu basılmıyordu (/italyanca-kelimeler/,
   /bolonya-bologna-gezilecek-yerler/, /macaristan-vize-rehberi/,
   /ipsala-sinir-kapisi/, /budapestede-nerede-kalinir/,
   /atina-nerede-kalinir/, /atina-havalimani-ulasim/).

   Eski JS sürümü tarayıcıda çalıştığı için bu başlıkları görüyordu;
   sunucu tarafına geçince göremez olduk. Bu tampon o farkı kapatır:
   the_content'te kutu basılamadıysa sayfanın tamamına bakar, ama
   YALNIZ ana içerik bölgesinde — başlık altı ile altbilgi arası.
   Menü, kenar çubuğu ve altbilgi başlıkları listeye girmez.
   ============================================================ */

if ( ! function_exists( 'gbc_toc_bolge_sinirlari' ) ) {
/**
 * Ana içerik bölgesinin sınırları: ilk </h1>'den altbilgiye kadar.
 *
 * @return array|null array( baslangic, bitis )
 */
function gbc_toc_bolge_sinirlari( $html ) {
	if ( ! preg_match( '/<\/h1>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) { return null; }
	$bas = $m[0][1] + strlen( $m[0][0] );

	$bit = strlen( $html );
	foreach ( array( 'gz-v16-footer', '<footer', '</main>', '</article>', 'gz-bot-bar' ) as $imza ) {
		$p = stripos( $html, $imza, $bas );
		if ( false !== $p && $p < $bit ) { $bit = $p; }
	}
	if ( $bit <= $bas ) { return null; }
	return array( $bas, $bit );
}
}

if ( ! function_exists( 'gbc_toc_sayfa_tamponu' ) ) {
function gbc_toc_sayfa_tamponu( $html ) {
	if ( ! is_string( $html ) || '' === $html ) { return $html; }
	if ( ! empty( $GLOBALS['gbc_toc_basildi'] ) ) { return $html; }
	if ( false !== strpos( $html, 'id="gbc-toc"' ) ) { return $html; }
	if ( gbc_toc_lejant_atla() && false !== strpos( $html, 'v1-lejant' ) ) { return $html; }
	if ( ! gbc_toc_uygun_mu() ) { return $html; }

	$sinir = gbc_toc_bolge_sinirlari( $html );
	if ( ! $sinir ) { return $html; }
	list( $bas, $bit ) = $sinir;
	$bolge = substr( $html, $bas, $bit - $bas );

	$kullanilan = array();
	if ( preg_match_all( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $m_id ) ) {
		foreach ( $m_id[1] as $i ) { $kullanilan[ $i ] = true; }
	}

	$ogeler = array();
	$yeni   = gbc_toc_basliklara_id( $bolge, 'h2', $ogeler, $kullanilan );

	if ( count( $ogeler ) < 3 ) {
		$ogeler_h3 = array();
		$deneme    = gbc_toc_basliklara_id( $bolge, 'h3', $ogeler_h3, $kullanilan );
		if ( count( $ogeler_h3 ) >= 3 ) { $ogeler = $ogeler_h3; $yeni = $deneme; }
	}
	if ( count( $ogeler ) < 3 ) {
		$ogeler_ek = array();
		$deneme    = gbc_toc_sinifla_id( $bolge, gbc_toc_ek_basliklar(), $ogeler_ek, $kullanilan );
		if ( count( $ogeler_ek ) >= 3 ) { $ogeler = $ogeler_ek; $yeni = $deneme; }
	}
	if ( count( $ogeler ) < 3 ) { return $html; }
	if ( ! is_string( $yeni ) || '' === $yeni ) { return $html; }

	/* Bölgeyi id'li hâliyle geri koy, kutuyu </h1>'in hemen altına bas. */
	$sonuc = substr( $html, 0, $bas ) . gbc_toc_kutu_html( $ogeler ) . $yeni . substr( $html, $bit );
	$GLOBALS['gbc_toc_basildi'] = true;
	return $sonuc;
}
}

if ( ! function_exists( 'gbc_toc_tampon_kur' ) ) {
function gbc_toc_tampon_kur() {
	if ( is_admin() || is_feed() ) { return; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }
	if ( ! gbc_toc_uygun_mu() ) { return; }

	/* LiteSpeed varsa onun son tamponuna — head temizliğinden (99) önce. */
	if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\\Core' ) ) {
		add_filter( 'litespeed_buffer_finalize', 'gbc_toc_sayfa_tamponu', 90 );
		return;
	}
	ob_start( 'gbc_toc_sayfa_tamponu' );
}
add_action( 'template_redirect', 'gbc_toc_tampon_kur', 2 );
}

/* CSS wp_head'de: kutu sunucudan geldigi icin ILK BOYAMADA bicimli olmali.
   Altbilgide kalsaydi bicimsiz kutu bir kez cizilir, ikinci bir kayma olurdu. */
if ( ! function_exists( 'gbc_toc_css' ) ) {
function gbc_toc_css() {
	if ( ! gbc_toc_uygun_mu() ) { return; }
	?>
<style id="gbc-toc-css">
#gbc-reading-progress{position:fixed;top:0;left:0;height:3px;width:0;background:#BF360C;z-index:99999;transition:width .1s linear;}
.tr-wrap h2[id],.gz-full-wrapper h2[id],.entry-content h2[id],article h2[id]{scroll-margin-top:110px;}
#gbc-toc{background:#fafafa;border:1px solid #ececec;border-radius:12px;margin:6px 0 22px;overflow:hidden;font-family:inherit;}
#gbc-toc .gbc-h{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;cursor:pointer;-webkit-user-select:none;user-select:none;background:#fff;border-bottom:1px solid #eee;}
#gbc-toc.gbc-col .gbc-h{border-bottom:none;}
#gbc-toc .gbc-t{display:flex;align-items:center;gap:8px;font-weight:800;font-size:16px;color:#1a1a1a;}
#gbc-toc .gbc-t svg{width:18px;height:18px;color:#BF360C;flex:none;}
#gbc-toc .gbc-c{font-weight:600;font-size:13px;color:#7c7268;}
#gbc-toc .gbc-x{background:none;border:none;cursor:pointer;color:#7c7268;display:flex;align-items:center;padding:4px;transition:transform .25s ease;}
#gbc-toc .gbc-x svg{width:20px;height:20px;}
#gbc-toc.gbc-col .gbc-x{transform:rotate(-90deg);}
#gbc-toc .gbc-b{padding:12px 16px;}
#gbc-toc.gbc-col .gbc-b{display:none;}
#gbc-toc ol{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr;gap:4px;}
#gbc-toc.gbc-2 ol{grid-template-columns:1fr 1fr;gap:4px 24px;}
#gbc-toc li{margin:0;}
#gbc-toc a{display:flex;align-items:flex-start;gap:9px;text-decoration:none;color:#332a20;font-size:15px;line-height:1.45;padding:6px 8px;border-radius:8px;transition:background .15s,color .15s;}
#gbc-toc a:hover{background:#fff3e0;color:#9A3412;}
#gbc-toc a.on{background:#fff3e0;color:#9A3412;font-weight:700;}
#gbc-toc a .n{flex:none;width:22px;height:22px;border-radius:50%;background:#BF360C;color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;margin-top:1px;}
#gbc-toc a:hover .n,#gbc-toc a.on .n{background:#9A3412;}
/* MOBILDE KAPALI ACILIS — JavaScript beklemeden, ilk boyamada.
   Eskiden bu karar JS'te matchMedia ile veriliyordu; kutu once acik cizilip
   sonra kapaniyordu. Artik dar ekranda govde bastan gizli. */
@media (max-width:900px){
  #gbc-toc.gbc-2 ol{grid-template-columns:1fr;}
  #gbc-toc a{font-size:14.5px;}
  #gbc-toc{margin:4px 15px 18px;}
  .tr-wrap h2[id],.gz-full-wrapper h2[id],.entry-content h2[id],article h2[id]{scroll-margin-top:90px;}
  #gbc-toc:not(.gbc-ac) .gbc-b{display:none;}
  #gbc-toc:not(.gbc-ac) .gbc-h{border-bottom:none;}
  #gbc-toc:not(.gbc-ac) .gbc-x{transform:rotate(-90deg);}
}
</style>
	<?php
}
}
add_action( 'wp_head', 'gbc_toc_css', 5 );

/* Davranis: kutuyu OLUSTURMAZ, bagilar. Sunucu kutusu yoksa eski kurucu devreye girer. */
if ( ! function_exists( 'gbc_toc_js' ) ) {
function gbc_toc_js() {
	if ( ! gbc_toc_uygun_mu() ) { return; }
	/* Betik gövdesi yakalanır, dosyadan bağlanır (modules/03). */
	ob_start();
	?>
(function(){
  if(window.__gbcTocInit) return; window.__gbcTocInit=true;
  var MINH=3, DAR='(max-width:900px)';
  function dar(){ return window.matchMedia && window.matchMedia(DAR).matches; }
  function slug(t){
    t=(t||'').trim().toLowerCase();
    var m={'ı':'i','İ':'i','ş':'s','ğ':'g','ü':'u','ö':'o','ç':'c','â':'a','î':'i','û':'u'};
    t=t.replace(/[ışğüöçâîûİ]/g,function(c){return m[c]||c;});
    return (t.replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,60))||'bolum';
  }
  function pickRoot(){
    var sels=['.gz-full-wrapper','.entry-content','.tr-wrap','.gz-elite-v16','article','main'];
    for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);
      if(el&&el.querySelectorAll('h2').length>=MINH) return el;}
    return null;
  }
  function findH1(){
    var sels=['.gz-h1','.entry-header .entry-title','.entry-title','.tr-h1','.gz-v16-title','h1.entry-title','article h1','main h1','h1'];
    for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]); if(el) return el;}
    return null;
  }
  /* --- Davranisi bagla: sunucudan gelen kutu icin de, yedek kutu icin de ayni --- */
  function bind(box){
    if(box.__gbcBound) return; box.__gbcBound=true;
    var head=box.querySelector('.gbc-h'); if(!head) return;

    function acik(){ return dar() ? box.classList.contains('gbc-ac') : !box.classList.contains('gbc-col'); }
    function toggle(){
      if(dar()) box.classList.toggle('gbc-ac'); else box.classList.toggle('gbc-col');
      head.setAttribute('aria-expanded', acik()?'true':'false');
    }
    head.setAttribute('aria-expanded', acik()?'true':'false');
    head.addEventListener('click',function(e){ if(e.target.closest('a'))return; toggle(); });
    head.addEventListener('keydown',function(e){ if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();} });

    function fix(t){ t.scrollIntoView({block:'start'}); }
    var links={}, hedefler=[];
    box.querySelectorAll('a[data-id]').forEach(function(a){
      var id=a.getAttribute('data-id');
      links[id]=a;
      var t=document.getElementById(id); if(t) hedefler.push(t);
      a.addEventListener('click',function(e){
        var t2=document.getElementById(id); if(!t2) return;
        e.preventDefault();
        t2.scrollIntoView({behavior:'smooth',block:'start'});
        setTimeout(function(){ fix(t2); }, 500);
        setTimeout(function(){ fix(t2); }, 1200);
        history.replaceState(null,'','#'+id);
      });
    });

    if('IntersectionObserver' in window && hedefler.length){
      var io=new IntersectionObserver(function(es){
        es.forEach(function(en){ if(en.isIntersecting){
          Object.keys(links).forEach(function(k){links[k].classList.remove('on');});
          if(links[en.target.id])links[en.target.id].classList.add('on');
        }});
      },{rootMargin:'-12% 0px -78% 0px',threshold:0});
      hedefler.forEach(function(t){io.observe(t);});
    }

    var root=pickRoot()||document.body;
    var bar=document.getElementById('gbc-reading-progress');
    if(!bar){ bar=document.createElement('div'); bar.id='gbc-reading-progress'; document.body.appendChild(bar); }
    var tk=false;
    function prog(){var top=root.getBoundingClientRect().top+window.pageYOffset;var h=root.offsetHeight-window.innerHeight;
      bar.style.width=Math.min(100,Math.max(0,((window.pageYOffset-top)/(h>0?h:1))*100))+'%';tk=false;}
    window.addEventListener('scroll',function(){if(!tk){tk=true;requestAnimationFrame(prog);}},{passive:true});
    prog();
  }

  /* --- YEDEK: sunucu kutusu yoksa eskisi gibi kur (kayma pahasina, hic yoktan iyi) --- */
  function build(){
    if(document.querySelector('.v1-lejant')) return;
    if(document.getElementById('gbc-toc')) return;
    var root=pickRoot(); if(!root) return;
    var hs=root.querySelectorAll('h2'), items=[], used={};
    hs.forEach(function(h){
      if(h.closest('.gbc-silo,.gbc-related,#gbc-toc')) return;
      var txt=(h.textContent||'').trim(); if(!txt) return;
      if(!h.id){var s=slug(txt),b=s,k=1;while(used[s]||document.getElementById(s)){s=b+'-'+(k++);}used[s]=1;h.id=s;}
      items.push({el:h,id:h.id,txt:txt});
    });
    if(items.length<MINH) return;
    var box=document.createElement('div'); box.id='gbc-toc';
    if(items.length>3) box.className='gbc-2';
    var ol='<ol>';
    items.forEach(function(it,i){
      ol+='<li><a href="#'+it.id+'" data-id="'+it.id+'"><span class="n">'+(i+1)+'</span><span>'+it.txt.replace(/</g,'&lt;')+'</span></a></li>';
    });
    ol+='</ol>';
    box.innerHTML='<div class="gbc-h" role="button" tabindex="0" aria-expanded="true">'+
      '<span class="gbc-t"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'+
      'İçindekiler <span class="gbc-c">'+items.length+' bölüm</span></span>'+
      '<button class="gbc-x" type="button" aria-label="Aç/Kapat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg></button>'+
      '</div><div class="gbc-b">'+ol+'</div>';
    var h1=findH1();
    if(h1&&h1.parentNode){ h1.parentNode.insertBefore(box,h1.nextSibling); }
    else { items[0].el.parentNode.insertBefore(box,items[0].el); }
    bind(box);
  }

  function init(){
    try{
      var box=document.getElementById('gbc-toc');
      if(box) bind(box); else build();
    }catch(e){ if(window.console)console.warn('[GBC TOC]',e); }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
	<?php
	$gbc_toc_betik = (string) ob_get_clean();
	if ( function_exists( 'gbc_js_bas' ) ) {
		gbc_js_bas( 'toc', $gbc_toc_betik, 'gbc-toc-js' );
	} else {
		echo '<script id="gbc-toc-js">' . $gbc_toc_betik . '</script>' . "\n";
	}
}
}
add_action( 'wp_footer', 'gbc_toc_js', 99 );

/* == YAZAR KUTUSU YEDEK YOLU · 21 Eylul 2026 ==
   WPCode 24114 (Yazar Kutusu) aktif gorunuyor ama calismiyor (27092 Icindekiler ile ayni sorun).
   Ayni kod burada, calistigi kanitlanmis Sema Motoru icinden yukleniyor. 24114 geri gelirse
   icerikte gz-author-box varsa ikinci kez basilmaz. */
if ( ! function_exists( 'gbc_yazar_kutusu_yedek' ) ) {
function gbc_yazar_kutusu_yedek( $content ) {
	if ( ! is_string( $content ) || false !== strpos( $content, 'gz-author-box' ) ) { return $content; }

    /* 21 Eyl 2026 (Halil): gezi rehberleri SAYFA (page) oldugu icin kutu cikmiyordu. Sablonlu
	   sayfalarda da (icerikte [wpcode) basilir; ana sayfa ve duz sayfalar haric. */
	if ( ( is_single() && 'post' === get_post_type() ) || ( is_page() && ! is_front_page() && in_the_loop() && false !== strpos( (string) get_post_field( 'post_content', get_the_ID() ), '[wpcode' ) ) ) {

        $author_name      = 'Halil Şımır';
        $brand_name       = 'Gezginbirchef';
        $author_url       = 'https://gezginbirchef.com/halil-simir/';
        $author_img       = 'https://gezginbirchef.com/wp-content/uploads/2025/06/halil-Simir.jpeg';
        $instagram_url    = 'https://www.instagram.com/gezginbirchef';
        $youtube_url      = 'https://www.youtube.com/@Gezginbirchef?sub_confirmation=1';

        $author_desc = 'Profesyonel mutfak deneyimini dijital içerik üreticiliğiyle birleştiren şef ve seyahat vlogger\'ı; dünya mutfakları, yerel lezzet durakları, gastronomi rotaları, restoran keşifleri ve özgün tarifler üzerine uzman içerikler üretir. Sahadaki mutfak deneyimini seyahatlerinden beslenen gözlemleriyle birleştirerek, takipçilerine hem ilham veren hem de rehber niteliği taşıyan gastronomi ve seyahat içerikleri sunar.';

        /* JSON-LD knowsAbout listesiyle birebir ayni olmali. */
        $uzmanlik = array(
            'Dünya mutfakları',
            'Gastronomi',
            'Restoran keşifleri',
            'Gezi rehberleri',
            'Seyahat rotaları',
            'Yemek tarifleri',
        );

        $cipler = '';
        foreach ( $uzmanlik as $u ) {
            $cipler .= '<b>' . esc_html( $u ) . '</b>';
        }

        $svg_ig = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor" stroke="none"/></svg>';
        $svg_yt = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M22.5 12c0-2.9-.2-4.4-.4-5.1a2.6 2.6 0 0 0-1.8-1.8C18.8 4.7 12 4.7 12 4.7s-6.8 0-8.3.4a2.6 2.6 0 0 0-1.8 1.8c-.2.7-.4 2.2-.4 5.1s.2 4.4.4 5.1a2.6 2.6 0 0 0 1.8 1.8c1.5.4 8.3.4 8.3.4s6.8 0 8.3-.4a2.6 2.6 0 0 0 1.8-1.8c.2-.7.4-2.2.4-5.1z"/><path d="M10.2 9.3l4.6 2.7-4.6 2.7z"/></svg>';

        $author_box = '
        <aside class="gz-author-box" role="complementary" aria-label="Yazar bilgisi">

            <div class="gz-author-kimlik">
                <a href="' . esc_url( $author_url ) . '" rel="author" class="gz-author-image-link" aria-label="' . esc_attr( $author_name ) . ' yazar profili">
                    <img src="' . esc_url( $author_img ) . '" alt="Profesyonel şef ve seyahat vloggerı ' . esc_attr( $author_name ) . '" class="gz-author-img" loading="lazy" width="128" height="128">
                </a>

                <span class="gz-author-title"><a href="' . esc_url( $author_url ) . '" rel="author">' . esc_html( $author_name ) . '</a></span>
                <span class="gz-author-subtitle">Profesyonel Şef &amp; Seyahat Vloggerı</span>

                <div class="gz-author-socials" aria-label="Sosyal medya profilleri">
                    <a href="' . esc_url( $youtube_url ) . '" class="gz-author-social gz-youtube" target="_blank" rel="noopener nofollow me">' . $svg_yt . '<span>YouTube</span></a>
                    <a href="' . esc_url( $instagram_url ) . '" class="gz-author-social gz-instagram" target="_blank" rel="noopener nofollow me">' . $svg_ig . '<span>Instagram</span></a>
                </div>
            </div>

            <div class="gz-author-info">
                <span class="gz-author-label">Yazarı ve içerik uzmanı &middot; ' . esc_html( $brand_name ) . '</span>

                <div class="gz-author-uzmanlik" aria-label="Uzmanlık alanları">' . $cipler . '</div>

                <p class="gz-author-desc">' . esc_html( $author_desc ) . '</p>

                <a href="' . esc_url( $author_url ) . '" class="gz-author-btn" rel="author">
                    Hakkımda daha fazla bilgi
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>
        </aside>';

        /* 21 Eyl 2026 (Halil): kutu sayfanin dibinde kopuk kaliyordu. Gezi sablonunda SSS bolumunun
		   hemen altina, ayni sutuna yerlesir; SSS yoksa eskisi gibi en sona. */
		$sss_yer = strpos( $content, 'gz-s-sss' );
		$sss_kap = ( false !== $sss_yer ) ? strpos( $content, '</article>', $sss_yer ) : false;
		if ( false !== $sss_kap ) { $content = substr_replace( $content, $author_box, $sss_kap + 10, 0 ); } else { $content .= $author_box; }
    }

    return $content;
}
add_filter( 'the_content', 'gbc_yazar_kutusu_yedek', 20 ); /* 20: sablon kisa kodu (do_shortcode 11) acildiktan sonra, SSS bulunabilsin */
}

/* v1.47.2 (30 Eyl 2026): şema yer ve koordinat alanları REST'e açıldı; yalnız yazıyı
   düzenleyebilen kullanıcı yazar. Böylece duraklar araçla doldurulabiliyor. */
add_action( 'init', function () {
	foreach ( array( 'gbc_schema_yerler', 'gbc_schema_geo' ) as $anahtar ) {
		foreach ( array( 'post', 'page' ) as $tur ) {
			register_post_meta( $tur, $anahtar, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static function ( $izin, $meta_key, $post_id ) { return current_user_can( 'edit_post', $post_id ); },
			) );
		}
	}
} );
