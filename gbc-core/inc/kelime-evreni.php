<?php
/**
 * GBC Core · Kelime evreni ve kapsama (v1.39.0, 30 Eylül 2026)
 * ------------------------------------------------------------
 * Bir sayfanın çevresindeki bütün aramalar (kısa ve uzun kuyruk):
 *   - hangileri gerçekten alakalı (futbol, borsa, marka elenir),
 *   - her birinin aylık arama hacmi (Google Ads > Ubersuggest),
 *   - sayfada geçiyor mu, nerede geçiyor (başlık / H2 / H3 / SSS / metin),
 *   - geçmiyorsa nereye konmalı (ayrı sayfa, H2, H3, paragraf, SSS),
 *   - sayfanın arama hacminin yüzde kaçını kapsadığı.
 *
 * VERİ NEREDEN GELİR
 *   "Tara" düğmesi (sunucu): Google otomatik tamamlama + bu sayfanın
 *   Search Console kelimeleri. Hacim getirmez, yalnız kelime bulur.
 *   Hacim: Claude, Ubersuggest ve Google Ads Anahtar Kelime Planlayıcısı
 *   MCP bağlantılarıyla çeker ve post meta gbc_kw_evren'e yazar. Bu
 *   ikisinin sunucudan çağrılacak kendi API'si yok (API Merkezi'nde
 *   "Claude üzerinden" kaydı). Hacmi olmayan kelime "hacim bekliyor" görünür.
 *
 * VERİ BİÇİMİ (post meta gbc_kw_evren, JSON metin):
 *   { "tohum": "sorrento", "guncel": 1790000000,
 *     "kelimeler": [ { "k": "sorrento gezilecek yerler", "h": 50,
 *        "hk": "ubersuggest|google_ads", "ht": "2026-09-30",
 *        "kay": ["ubersuggest","google_oto","gsc"], "alaka": "evet|hayir|" } ] }
 *
 * Ziyaretçi isteğinde bu dosya yüklenmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   VERİ
   ============================================================ */

function gbc_kw_oku( $pid ) {
	$ham = get_post_meta( (int) $pid, 'gbc_kw_evren', true );
	$v   = is_array( $ham ) ? $ham : json_decode( (string) $ham, true );
	if ( ! is_array( $v ) ) { $v = array(); }
	$v += array( 'tohum' => '', 'guncel' => 0, 'kelimeler' => array() );
	if ( ! is_array( $v['kelimeler'] ) ) { $v['kelimeler'] = array(); }
	/* Kayıt filtresi metin alanlarında > işaretini &gt; yapabiliyor; ekranda düz metin olarak geri çevrilir. */
	foreach ( $v['kelimeler'] as $i => $x ) {
		foreach ( array( 'cn', 'cy' ) as $a ) {
			if ( isset( $x[ $a ] ) && is_string( $x[ $a ] ) ) { $v['kelimeler'][ $i ][ $a ] = html_entity_decode( $x[ $a ], ENT_QUOTES, 'UTF-8' ); }
		}
	}
	return $v;
}

function gbc_kw_yaz( $pid, $v ) {
	update_post_meta( (int) $pid, 'gbc_kw_evren', wp_slash( wp_json_encode( $v, JSON_UNESCAPED_UNICODE ) ) );
}

/** Türkçe küçük harf + noktalama temizliği. */
function gbc_kw_normal( $s ) {
	$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' );
	$s = strtr( $s, array( 'I' => 'ı', 'İ' => 'i' ) );
	$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	$s = str_replace( array( "'", '’', '‘', '`', '´' ), '', $s );
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/** Varsayılan tohum: kayıtlı tohum > Rank Math odak kelimesi > adresin ilk parçası. */
function gbc_kw_tohum( $pid, $v ) {
	if ( '' !== (string) $v['tohum'] ) { return (string) $v['tohum']; }
	$odak = (string) get_post_meta( (int) $pid, 'rank_math_focus_keyword', true );
	if ( '' !== $odak ) { $p = explode( ',', $odak ); return gbc_kw_normal( $p[0] ); }
	$slug = (string) get_post_field( 'post_name', (int) $pid );
	$p = explode( '-', $slug );
	return gbc_kw_normal( $p[0] );
}

/** Listeye kelime ekler ya da kaynağını birleştirir. */
function gbc_kw_ekle( &$v, $kelime, $kaynak, $hacim = null, $hk = '' ) {
	$n = gbc_kw_normal( $kelime );
	if ( '' === $n || mb_strlen( $n ) > 80 ) { return false; }
	foreach ( $v['kelimeler'] as $i => $x ) {
		if ( gbc_kw_normal( $x['k'] ) === $n ) {
			$kay = isset( $x['kay'] ) ? (array) $x['kay'] : array();
			if ( ! in_array( $kaynak, $kay, true ) ) { $kay[] = $kaynak; }
			$v['kelimeler'][ $i ]['kay'] = $kay;
			/* Google Ads hacmi varsa başka kaynak onun üstüne yazamaz. */
			$eski_hk = isset( $x['hk'] ) ? (string) $x['hk'] : '';
			if ( null !== $hacim && ! ( 'google_ads' === $eski_hk && 'google_ads' !== $hk ) ) {
				$v['kelimeler'][ $i ]['h']  = (int) $hacim;
				$v['kelimeler'][ $i ]['hk'] = $hk;
				$v['kelimeler'][ $i ]['ht'] = gmdate( 'Y-m-d' );
			}
			return false;
		}
	}
	$v['kelimeler'][] = array( 'k' => $n, 'h' => null === $hacim ? null : (int) $hacim, 'hk' => $hk,
		'ht' => null === $hacim ? '' : gmdate( 'Y-m-d' ), 'kay' => array( $kaynak ), 'alaka' => '' );
	return true;
}

/* ============================================================
   ALAKA — otomatik eleme, elle karar her zaman kazanır
   ============================================================ */

function gbc_kw_alaka_otomatik( $k, $tohum ) {
	$n = gbc_kw_normal( $k );
	$t = gbc_kw_normal( $tohum );
	if ( '' !== $t && false === strpos( $n, $t ) ) { return array( 'hayir', 'tohum kelime geçmiyor' ); }
	$hayir = array( 'fc', 'calcio', 'maç', 'maçları', 'maçı', 'istatistik', 'istatistikleri', 'skor', 'therapeutics', 'stock', 'stocktwits',
		'hisse', 'zwilling', 'gömlek', 'bisan', 'casertana', 'live', 'canlı', 'forma', 'bıçak', 'tencere', 'kanepe', 'koltuk', 'mobilya', 'şarkı', 'lyrics', 'film' );
	$evet = array( 'gezi', 'gezilecek', 'görülecek', 'gezisi', 'rehber', 'rehberi', 'otel', 'otelleri', 'hotel', 'konaklama', 'kalınır', 'kalmalı', 'pansiyon',
		'restoran', 'restoranları', 'yemek', 'yenir', 'plaj', 'plajları', 'tur', 'turu', 'feribot', 'tekne', 'tren', 'otobüs', 'ulaşım', 'nasıl', 'nerede',
		'kaç', 'km', 'saat', 'hava', 'harita', 'italya', 'capri', 'amalfi', 'positano', 'pompei', 'napoli', 'limon', 'alışveriş', 'fiyat', 'fiyatları', 'gün', 'rota',
		'tatil', 'havalimanı', 'ne', 'demek', 'hakkında', 'vize', 'euro', 'pahalı' );
	$kel = explode( ' ', $n );
	/* Ek duyarlı: "maçları" → maç, "limonu" → limon. Kısa kökler (fc, ne, km) birebir aranır. */
	$uyar = static function ( $w, $liste ) {
		foreach ( $liste as $l ) {
			if ( $w === $l ) { return $l; }
			if ( mb_strlen( $l ) >= 4 && 0 === strpos( $w, $l ) ) { return $l; }
		}
		return '';
	};
	foreach ( $kel as $w ) { if ( '' !== $uyar( $w, $hayir ) ) { return array( 'hayir', '“' . $w . '” gezi konusu değil' ); } }
	if ( preg_match( '/\b(to|the|and|hotels|ferry|train|tickets|italy|beach|things|do|in|where|what)\b/u', $n ) ) {
		return array( '', 'İngilizce arama — Türkçe sayfa için kararsız' );
	}
	foreach ( $kel as $w ) { if ( '' !== $uyar( $w, $evet ) ) { return array( 'evet', '' ); } }
	if ( $n === $t ) { return array( 'evet', 'ana kelime' ); }
	return array( '', 'kararsız — elle işaretle' );
}

/** Kelimenin son alaka kararı: elle verilen > kontrol kararı (ck, onay beklemeden geçerli) > otomatik. */
function gbc_kw_alaka( $x, $tohum ) {
	if ( ! empty( $x['alaka'] ) ) { return array( $x['alaka'], 'elle işaretlendi' ); }
	if ( ! empty( $x['ck'] ) ) { return array( $x['ck'], isset( $x['cn'] ) ? $x['cn'] : '' ); }
	return gbc_kw_alaka_otomatik( $x['k'], $tohum );
}

/* ============================================================
   NİYET — kelime hangi konuya ait (öneri ve silo için)
   ============================================================ */

function gbc_kw_niyet( $k ) {
	$n = ' ' . gbc_kw_normal( $k ) . ' ';
	$grup = array(
		'konaklama' => array( 'otel', 'hotel', 'konaklama', 'kalınır', 'kalmalı', 'pansiyon', 'airbnb', 'nerede kal' ),
		'yemek'     => array( 'restoran', 'yemek', 'yenir', 'pizza', 'cafe', 'kafe', 'lezzet', 'limoncello', 'dondurma', 'pastane', 'pasticceria' ),
		'ulasim'    => array( 'nasıl gidilir', 'ulaşım', 'tren', 'otobüs', 'feribot', 'tekne', 'havalimanı', 'kaç km', 'kaç saat', 'arası', 'transfer', 'circumvesuviana', 'napoli sorrento' ),
		'gezi'      => array( 'gezilecek', 'görülecek', 'gezi', 'plaj', 'tur', 'rehber', 'rota', 'kaç gün', 'yapılacak' ),
	);
	foreach ( $grup as $g => $liste ) {
		foreach ( $liste as $w ) { if ( false !== strpos( $n, ' ' . $w ) ) { return $g; } }
	}
	return 'genel';
}

function gbc_kw_niyet_ad( $g ) {
	$a = array( 'konaklama' => 'Konaklama', 'yemek' => 'Yeme-içme', 'ulasim' => 'Ulaşım', 'gezi' => 'Gezi', 'genel' => 'Genel' );
	return isset( $a[ $g ] ) ? $a[ $g ] : $g;
}

/** Soru kalıbı mı? (SSS için uygun) */
function gbc_kw_soru_mu( $k ) {
	return (bool) preg_match( '/(^|\s)(nasıl|ne|neden|nerede|nereye|hangi|kaç|mı|mi|mu|mü|ne zaman|kim)(\s|$)/u', gbc_kw_normal( $k ) );
}

/* ============================================================
   KAPSAMA — kelime sayfada geçiyor mu, nerede
   ============================================================ */

/** Sayfayı yer etiketli bloklara böler: baslik, h2, h3, sss, metin. */
function gbc_kw_bloklar( $html ) {
	$b = array();
	if ( preg_match( '~<title>(.*?)</title>~is', $html, $m ) ) { $b[] = array( 'yer' => 'baslik', 'metin' => gbc_kw_normal( $m[1] ) ); }
	$alan = function_exists( 'gbc_br_icerik_alani' ) ? gbc_br_icerik_alani( $html ) : $html;
	$alan = preg_replace( '~<(script|style|noscript)\b.*?</\1>~is', ' ', $alan );

	/* SSS: <details>/<summary> blokları */
	if ( preg_match_all( '~<details\b.*?</details>~is', $alan, $md ) ) {
		foreach ( $md[0] as $d ) { $b[] = array( 'yer' => 'sss', 'metin' => gbc_kw_normal( $d ) ); }
		$alan = preg_replace( '~<details\b.*?</details>~is', ' ', $alan );
	}
	if ( preg_match_all( '~<h1\b[^>]*>(.*?)</h1>~is', $alan, $mh ) ) { foreach ( $mh[1] as $x ) { $b[] = array( 'yer' => 'baslik', 'metin' => gbc_kw_normal( $x ) ); } }
	if ( preg_match_all( '~<h2\b[^>]*>(.*?)</h2>~is', $alan, $mh ) ) { foreach ( $mh[1] as $x ) { $b[] = array( 'yer' => 'h2', 'metin' => gbc_kw_normal( $x ) ); } }
	if ( preg_match_all( '~<h[34]\b[^>]*>(.*?)</h[34]>~is', $alan, $mh ) ) { foreach ( $mh[1] as $x ) { $b[] = array( 'yer' => 'h3', 'metin' => gbc_kw_normal( $x ) ); } }
	$govde = preg_replace( '~<h[1-4]\b.*?</h[1-4]>~is', ' ', $alan );
	foreach ( preg_split( '~</?(p|li|td|th|div|section|blockquote|summary|br)\b[^>]*>~i', $govde ) as $parca ) {
		$t = gbc_kw_normal( $parca );
		if ( mb_strlen( $t ) >= 3 ) { $b[] = array( 'yer' => 'metin', 'metin' => $t ); }
	}
	return $b;
}

/** Kelime kökleri (bağlaçlar atılır). */
function gbc_kw_kokler( $k ) {
	$dur = array( 've', 'ile', 'için', 'de', 'da', 'bir', 'en', 'the' );
	return array_values( array_filter( explode( ' ', gbc_kw_normal( $k ) ), static function ( $w ) use ( $dur ) { return '' !== $w && ! in_array( $w, $dur, true ); } ) );
}

/** İngilizce aramaların Türkçe sayfadaki karşılığı. */
function gbc_kw_esanlam( $kok ) {
	$e = array( 'hotel' => 'otel', 'hotels' => 'otel', 'restaurant' => 'restoran', 'restaurants' => 'restoran', 'beach' => 'plaj',
		'train' => 'tren', 'ferry' => 'feribot', 'bus' => 'otobüs', 'italy' => 'italya', 'map' => 'harita', 'weather' => 'hava' );
	return isset( $e[ $kok ] ) ? $e[ $kok ] : '';
}

/** Bir kök bir blokta var mı? Türkçe ek için ön ek eşleşmesi. */
function gbc_kw_kok_var( $kok, $kelimeler ) {
	$es = gbc_kw_esanlam( $kok );
	if ( '' !== $es && gbc_kw_kok_var( $es, $kelimeler ) ) { return true; }
	$kl = mb_strlen( $kok );
	foreach ( $kelimeler as $w ) {
		if ( 0 === strpos( $w, $kok ) ) { return true; }                                   /* sorrento → sorrentoda, otel → otelleri */
		if ( $kl >= 5 && mb_strlen( $w ) >= 4 && 0 === strpos( $kok, $w ) ) { return true; } /* otelleri → otel (kök metinde kısa) */
	}
	return false;
}

/**
 * Kelimenin sayfadaki en iyi yeri.
 * @return array( 'yer' => baslik|h2|h3|sss|metin|'', 'birebir' => bool )
 */
function gbc_kw_nerede( $k, $bloklar ) {
	$kokler = gbc_kw_kokler( $k );
	if ( ! $kokler ) { return array( 'yer' => '', 'birebir' => false ); }
	$oncelik = array( 'baslik' => 5, 'h2' => 4, 'h3' => 3, 'sss' => 2, 'metin' => 1 );
	$en = ''; $bir = false; $n = gbc_kw_normal( $k );
	foreach ( $bloklar as $b ) {
		$kel = explode( ' ', $b['metin'] );
		$hepsi = true;
		foreach ( $kokler as $kok ) { if ( ! gbc_kw_kok_var( $kok, $kel ) ) { $hepsi = false; break; } }
		if ( ! $hepsi ) { continue; }
		if ( '' === $en || $oncelik[ $b['yer'] ] > $oncelik[ $en ] ) { $en = $b['yer']; }
		if ( false !== strpos( $b['metin'], $n ) ) { $bir = true; }
	}
	return array( 'yer' => $en, 'birebir' => $bir );
}

/** Yerleşimin arama hacmini yakalama ihtimali. */
function gbc_kw_yakalama( $yer_ya_da_eylem ) {
	$y = array( 'baslik' => 'yüksek', 'sayfa' => 'yüksek', 'h2' => 'yüksek', 'h3' => 'orta', 'sss' => 'orta', 'metin' => 'düşük', 'paragraf' => 'düşük', 'tasi' => 'orta' );
	return isset( $y[ $yer_ya_da_eylem ] ) ? $y[ $yer_ya_da_eylem ] : '';
}

/**
 * Kelime için eylem. Claude'un yazdığı eylem (ce) ve yeri (cy) varsa o kullanılır.
 * @return array( eylem, not )  eylem: sayfa|h2|h3|sss|metin|tasi|atla|bekle|''
 */
function gbc_kw_oneri( $x, $yer, $sayfa_niyet ) {
	$h  = isset( $x['h'] ) && null !== $x['h'] ? (int) $x['h'] : null;
	$ni = gbc_kw_niyet( $x['k'] );
	if ( ! empty( $x['ce'] ) && ( '' === $yer || 'tasi' === $x['ce'] ) ) {
		$ce = 'paragraf' === $x['ce'] ? 'metin' : $x['ce'];
		return array( $ce, trim( ( ! empty( $x['cy'] ) ? $x['cy'] . '. ' : '' ) . ( ! empty( $x['cn'] ) && 'atla' === $ce ? $x['cn'] : '' ) ) );
	}
	if ( '' !== $yer ) {
		if ( null !== $h && $h >= 100 && in_array( $yer, array( 'metin', 'sss' ), true ) ) {
			return array( 'tasi', 'Geçiyor ama yalnız ' . ( 'sss' === $yer ? 'SSS\'de' : 'metinde' ) . '. Hacmi yüksek: bir H2 ya da H3 başlığa taşı.' );
		}
		return array( '', '' );
	}
	if ( null === $h ) { return array( 'bekle', 'Hacim bekleniyor; hacim gelince öneri çıkar.' ); }
	if ( $h >= 500 && 'genel' !== $ni && $ni !== $sayfa_niyet ) {
		return array( 'sayfa', 'Hacmi yüksek ve ayrı bir konu (' . gbc_kw_niyet_ad( $ni ) . '). Ayrı sayfa aç, bu sayfadan ona iç link ver.' );
	}
	if ( $h >= 100 ) { return array( 'h2', 'Yeni bir H2 başlık aç; kelime başlıkta birebir geçsin.' ); }
	if ( gbc_kw_soru_mu( $x['k'] ) && $h >= 20 ) { return array( 'sss', 'Soru kalıbı: SSS\'ye soru olarak ekle.' ); }
	if ( $h >= 20 ) { return array( 'h3', 'İlgili bölüme H3 ara başlık ya da bir paragraf ekle.' ); }
	return array( 'atla', 'Aylık 20 aramanın altında: geçirmeye değmez, gereksiz.' );
}

/** Tam analiz. */
function gbc_kw_analiz( $pid, $html ) {
	$v      = gbc_kw_oku( $pid );
	$tohum  = gbc_kw_tohum( $pid, $v );
	$blok   = gbc_kw_bloklar( (string) $html );
	$sniyet = ( false !== strpos( (string) $html, 'gz-full-wrapper' ) ) ? 'gezi' : 'genel';
	$satir  = array();
	$o = array( 'alakali' => 0, 'kapsanan' => 0, 'hacim' => 0, 'hacim_kapsanan' => 0, 'bekleyen' => 0, 'alakasiz' => 0, 'kararsiz' => 0,
		'uk_hacim' => 0, 'uk_kapsanan' => 0 );
	foreach ( $v['kelimeler'] as $x ) {
		list( $al, $al_not ) = gbc_kw_alaka( $x, $tohum );
		$yer = gbc_kw_nerede( $x['k'], $blok );
		list( $on, $on_not ) = gbc_kw_oneri( $x, $yer['yer'], $sniyet );
		$h = isset( $x['h'] ) && null !== $x['h'] ? (int) $x['h'] : null;
		$yk = '' !== $yer['yer'] ? gbc_kw_yakalama( $yer['yer'] ) : gbc_kw_yakalama( $on );
		$satir[] = array_merge( $x, array( 'al' => $al, 'al_not' => $al_not, 'yer' => $yer['yer'], 'birebir' => $yer['birebir'],
			'oneri' => $on, 'oneri_not' => $on_not, 'niyet' => gbc_kw_niyet( $x['k'] ), 'hh' => $h, 'yakalama' => $yk,
			'claude_bekliyor' => false ) );
		if ( 'evet' === $al && '' === $yer['yer'] && 'atla' === $on ) { $o['gereksiz'] = isset( $o['gereksiz'] ) ? $o['gereksiz'] + 1 : 1; }
		if ( 'hayir' === $al ) { $o['alakasiz']++; continue; }
		if ( '' === $al ) { $o['kararsiz']++; continue; }
		$o['alakali']++;
		if ( null === $h ) { $o['bekleyen']++; }
		$o['hacim'] += (int) $h;
		if ( '' !== $yer['yer'] ) { $o['kapsanan']++; $o['hacim_kapsanan'] += (int) $h; }
		/* Uzun kuyruk: ana kelimenin kendisi hariç. Ana kelime tek başına oranı şişirmesin. */
		if ( gbc_kw_normal( $x['k'] ) !== gbc_kw_normal( $tohum ) ) {
			$o['uk_hacim'] += (int) $h;
			if ( '' !== $yer['yer'] ) { $o['uk_kapsanan'] += (int) $h; }
		}
	}
	usort( $satir, static function ( $a, $b ) { return (int) $b['hh'] - (int) $a['hh']; } );
	$o['uk_oran'] = $o['uk_hacim'] ? (int) round( $o['uk_kapsanan'] / $o['uk_hacim'] * 100 ) : 0;
	$o['oran'] = $o['hacim'] ? (int) round( $o['hacim_kapsanan'] / $o['hacim'] * 100 ) : ( $o['alakali'] ? (int) round( $o['kapsanan'] / $o['alakali'] * 100 ) : 0 );
	return array( 'v' => $v, 'tohum' => $tohum, 'satir' => $satir, 'ozet' => $o );
}

/* ============================================================
   TARA — Google otomatik tamamlama + Search Console
   ============================================================ */

function gbc_kw_tara( $pid ) {
	$v     = gbc_kw_oku( $pid );
	$tohum = gbc_kw_tohum( $pid, $v );
	$eklenen = 0;
	if ( '' === $tohum ) { return array( 'eklenen' => 0, 'not' => 'Tohum kelime yok.' ); }

	$ekler = array( '', ' gezilecek', ' gezi', ' otel', ' nerede', ' nasıl', ' ne', ' kaç', ' plaj', ' restoran', ' tur', ' ulaşım', ' hava', ' fiyat', ' feribot', ' tren' );
	$istek = array();
	foreach ( $ekler as $i => $e ) {
		$istek[ $i ] = array( 'url' => 'https://suggestqueries.google.com/complete/search?client=firefox&hl=tr&gl=tr&ie=UTF-8&oe=UTF-8&q=' . rawurlencode( $tohum . $e ),
			'type' => 'GET', 'headers' => array(), 'options' => array( 'timeout' => 8, 'connect_timeout' => 5 ) );
	}
	$sinif = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
	$cevap = array();
	if ( '' !== $sinif ) {
		try { $cevap = call_user_func( array( $sinif, 'request_multiple' ), $istek, array() ); } catch ( \Exception $e ) { $cevap = array(); }
	}
	$oto = 0;
	foreach ( $cevap as $c ) {
		if ( ! is_object( $c ) || empty( $c->body ) || 200 !== (int) $c->status_code ) { continue; }
		$j = json_decode( $c->body, true );
		if ( ! is_array( $j ) || empty( $j[1] ) ) { continue; }
		foreach ( (array) $j[1] as $s ) { $oto++; if ( gbc_kw_ekle( $v, $s, 'google_oto' ) ) { $eklenen++; } }
	}

	/* Bu sayfanın Search Console kelimeleri (son 90 gün). */
	$gsc = 0;
	if ( function_exists( 'gbc_gs_gsc' ) ) {
		$bugun = current_time( 'timestamp' );
		$r = gbc_gs_gsc( get_permalink( $pid ), gmdate( 'Y-m-d', $bugun - 89 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $bugun ), array( 'query' ), 200 );
		if ( ! is_wp_error( $r ) ) {
			foreach ( (array) $r as $x ) { $gsc++; if ( gbc_kw_ekle( $v, $x['keys'][0], 'gsc' ) ) { $eklenen++; } }
		}
	}
	gbc_kw_ekle( $v, $tohum, 'tohum' );
	$v['tohum']  = $tohum;
	$v['guncel'] = time();
	gbc_kw_yaz( $pid, $v );
	return array( 'eklenen' => $eklenen, 'oto' => $oto, 'gsc' => $gsc );
}

/* ============================================================
   İŞLEMLER — tara, alaka işaretle, tohum değiştir
   ============================================================ */

add_action( 'admin_post_gbc_kw', 'gbc_kw_islem' );
function gbc_kw_islem() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_kw' );
	$pid = isset( $_REQUEST['pid'] ) ? (int) $_REQUEST['pid'] : 0;
	$is  = isset( $_REQUEST['is'] ) ? sanitize_key( $_REQUEST['is'] ) : '';
	$msg = '';
	if ( $pid && 'tara' === $is ) {
		$r = gbc_kw_tara( $pid );
		$msg = 'tara-' . (int) $r['eklenen'];
	} elseif ( $pid && 'alaka' === $is ) {
		$k = isset( $_REQUEST['k'] ) ? gbc_kw_normal( wp_unslash( $_REQUEST['k'] ) ) : '';
		$d = isset( $_REQUEST['d'] ) ? sanitize_key( $_REQUEST['d'] ) : '';
		$v = gbc_kw_oku( $pid );
		foreach ( $v['kelimeler'] as $i => $x ) {
			if ( gbc_kw_normal( $x['k'] ) === $k ) { $v['kelimeler'][ $i ]['alaka'] = in_array( $d, array( 'evet', 'hayir' ), true ) ? $d : ''; }
		}
		gbc_kw_yaz( $pid, $v );
		$msg = 'alaka';
	} elseif ( $pid && 'onayla' === $is ) {
		/* Claude'un kararlarını toplu onayla: elle kararı olmayanlara ck yazılır. */
		$v = gbc_kw_oku( $pid );
		$n = 0;
		foreach ( $v['kelimeler'] as $i => $x ) {
			if ( ! empty( $x['ck'] ) && empty( $x['alaka'] ) ) { $v['kelimeler'][ $i ]['alaka'] = $x['ck']; $n++; }
		}
		gbc_kw_yaz( $pid, $v );
		$msg = 'onay-' . $n;
	} elseif ( $pid && 'tohum' === $is ) {
		$v = gbc_kw_oku( $pid );
		$v['tohum'] = gbc_kw_normal( isset( $_REQUEST['tohum'] ) ? wp_unslash( $_REQUEST['tohum'] ) : '' );
		gbc_kw_yaz( $pid, $v );
		$msg = 'tohum';
	}
	wp_safe_redirect( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid . '&kw=' . rawurlencode( $msg ) ) . '#gbc-kelime' );
	exit;
}

/* ============================================================
   EKRAN
   ============================================================ */

function gbc_kw_islem_url( $pid, $is, $ek = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'gbc_kw', 'pid' => (int) $pid, 'is' => $is ), $ek ), admin_url( 'admin-post.php' ) ), 'gbc_kw' );
}

/** Üst kart. */
function gbc_kw_kart( $a ) {
	$o = $a['ozet'];
	if ( ! $a['v']['kelimeler'] ) {
		return gbc_br_kart( __( 'Kelime kapsama', 'gbc-core' ), '—', esc_html__( 'henüz taranmadı', 'gbc-core' ), '#5C6470', '#gbc-kelime' );
	}
	$renk = $o['uk_oran'] >= 70 ? '#1A7F37' : ( $o['uk_oran'] >= 40 ? '#8A6100' : '#B32D2E' );
	return gbc_br_kart( __( 'Kelime kapsama', 'gbc-core' ), '%' . $o['uk_oran'],
		esc_html( sprintf( __( 'uzun kuyruk hacminin kapsanan kısmı · ana kelime dahil %%%1$d · %2$d / %3$d kelime geçiyor', 'gbc-core' ),
			$o['oran'], $o['kapsanan'], $o['alakali'] ) ),
		$renk, '#gbc-kelime' );
}

function gbc_kw_rozet( $metin, $renk, $zemin ) {
	return '<span style="display:inline-block;white-space:nowrap;font-weight:700;font-size:12px;padding:2px 8px;border-radius:999px;color:' . $renk . ';background:' . $zemin . '">' . esc_html( $metin ) . '</span>';
}

function gbc_kw_ekran( $pid, $a, $kn = null ) {
	$pid = (int) $pid;
	$o   = $a['ozet'];
	$yer_ad = array( 'baslik' => 'Başlık / H1', 'h2' => 'H2', 'h3' => 'H3', 'sss' => 'SSS', 'metin' => 'Metin' );
	$on_ad  = array( 'sayfa' => array( 'Ayrı sayfa aç', '#8A1C7C', '#F6E8F4' ), 'h2' => array( 'Yeni H2', '#B32D2E', '#FBE7E7' ),
		'h3' => array( 'H3 ara başlık', '#8A6100', '#FCF6E8' ), 'sss' => array( 'SSS\'ye ekle', '#1B4E9B', '#EEF3FB' ),
		'metin' => array( 'Paragrafta geçir', '#5C6470', '#F1EFEA' ), 'tasi' => array( 'Başlığa taşı', '#8A6100', '#FCF6E8' ),
		'bekle' => array( 'Hacim bekliyor', '#5C6470', '#F1EFEA' ), 'atla' => array( 'Gereksiz', '#5C6470', '#F1EFEA' ) );
	$yk_renk = array( 'yüksek' => array( '#1A7F37', '#E8F3EC' ), 'orta' => array( '#8A6100', '#FCF6E8' ), 'düşük' => array( '#5C6470', '#F1EFEA' ) );
	$knr = ( is_array( $kn ) && isset( $kn['satir'] ) ) ? $kn['satir'] : array();

	echo '<h2 id="gbc-kelime" style="margin-top:26px">' . esc_html__( 'Kelime evreni ve kapsama', 'gbc-core' ) . '</h2>';
	if ( isset( $_GET['kw'] ) ) {
		$m = sanitize_text_field( wp_unslash( $_GET['kw'] ) );
		if ( 0 === strpos( $m, 'tara-' ) ) { $yazi = sprintf( __( 'Tarama bitti: %d yeni kelime eklendi.', 'gbc-core' ), (int) substr( $m, 5 ) ); }
		elseif ( 0 === strpos( $m, 'onay-' ) ) { $yazi = sprintf( __( '%d Claude kararı onaylandı.', 'gbc-core' ), (int) substr( $m, 5 ) ); }
		else { $yazi = __( 'Kaydedildi.', 'gbc-core' ); }
		echo '<div class="notice notice-success inline" style="margin:8px 0"><p>' . esc_html( $yazi ) . '</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:6px 0 10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
	wp_nonce_field( 'gbc_kw' );
	echo '<input type="hidden" name="action" value="gbc_kw"><input type="hidden" name="pid" value="' . (int) $pid . '"><input type="hidden" name="is" value="tohum">'
		. '<label style="font-weight:600">' . esc_html__( 'Ana kelime (tohum)', 'gbc-core' ) . '</label>'
		. '<input type="text" name="tohum" value="' . esc_attr( $a['tohum'] ) . '" style="width:200px"> '
		. '<button class="button">' . esc_html__( 'Kaydet', 'gbc-core' ) . '</button> '
		. '<a class="button" href="' . esc_url( gbc_kw_islem_url( $pid, 'tara' ) ) . '">' . esc_html__( 'Tara: yeni kelime bul', 'gbc-core' ) . '</a>';
	echo '</form>';
	echo '<div style="color:#5C6470;font-size:12.5px;margin:0 0 10px;max-width:1100px;line-height:1.6">'
		. esc_html__( 'Nasıl çalışır: "Tara" Google önerileri ve Search Console\'dan yeni kelime ekler (istediğin zaman basarsın, eskileri silmez). Hacim, arama niyeti ve Google ilk sayfası (SERP) kontrol edilir; her kelimenin alaka kararı ve yeri doğrudan yazılır, onay beklemez. Yanlış bulursan satırdan değiştirirsin; elle verdiğin karar her zaman kazanır.', 'gbc-core' )
		. ( $a['v']['guncel'] ? ' ' . esc_html( sprintf( __( 'Son güncelleme: %s önce.', 'gbc-core' ), human_time_diff( (int) $a['v']['guncel'] ) ) ) : '' ) . '</div>';

	if ( ! $a['satir'] ) {
		echo '<p><em>' . esc_html__( 'Bu sayfa için henüz kelime yok. "Tara" ile başla.', 'gbc-core' ) . '</em></p>';
		return;
	}

	$yap = 0; $yap_h = 0;
	foreach ( $a['satir'] as $s ) { if ( 'evet' === $s['al'] && '' === $s['yer'] && ! in_array( $s['oneri'], array( 'atla', 'bekle' ), true ) ) { $yap++; $yap_h += (int) $s['hh']; } }
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0">';
	echo gbc_kw_kart( $a );
	echo gbc_br_kart( __( 'Yapılacak', 'gbc-core' ), (string) $yap, esc_html( sprintf( __( 'geçirilince aylık %s arama daha', 'gbc-core' ), number_format_i18n( $yap_h ) ) ), $yap ? '#B32D2E' : '#1A7F37', '#gbc-kw-yapilacak' );
	$knsay = is_array( $kn ) ? (int) $kn['ozet']['kesin'] + (int) $kn['ozet']['risk'] : 0;
	echo gbc_br_kart( __( 'Kanibalizasyon', 'gbc-core' ), $knsay ? '✗ ' . $knsay : '✓',
		esc_html( is_array( $kn ) ? sprintf( __( '%1$d kesin · %2$d risk', 'gbc-core' ), $kn['ozet']['kesin'], $kn['ozet']['risk'] ) : '' ), $knsay ? '#B32D2E' : '#1A7F37', '#gbc-kanibal' );
	echo gbc_br_kart( __( 'Alakasız / gereksiz', 'gbc-core' ), $o['alakasiz'] . ' / ' . (int) ( isset( $o['gereksiz'] ) ? $o['gereksiz'] : 0 ), esc_html__( 'elendi, aşağıda kapalı listede', 'gbc-core' ), '#5C6470' );
	echo '</div>';

	$gruplar = array(
		'yapilacak' => array( __( 'Yapılacaklar: alakalı, sayfada yok, geçirmeye değer', 'gbc-core' ), false,
			static function ( $s ) { return 'evet' === $s['al'] && '' === $s['yer'] && ! in_array( $s['oneri'], array( 'atla', 'bekle' ), true ); } ),
		'gecen'     => array( __( 'Sayfada geçenler', 'gbc-core' ), false, static function ( $s ) { return 'evet' === $s['al'] && '' !== $s['yer']; } ),
		'bekle'     => array( __( 'Hacim bekleyen', 'gbc-core' ), true, static function ( $s ) { return 'evet' === $s['al'] && '' === $s['yer'] && 'bekle' === $s['oneri']; } ),
		'kararsiz'  => array( __( 'Kararsız: sonraki kelime kontrolünde karara bağlanır', 'gbc-core' ), false, static function ( $s ) { return '' === $s['al']; } ),
		'gereksiz'  => array( __( 'Gereksiz: alakalı ama hacmi yok ya da kazanılamaz', 'gbc-core' ), true, static function ( $s ) { return 'evet' === $s['al'] && '' === $s['yer'] && 'atla' === $s['oneri']; } ),
		'alakasiz'  => array( __( 'Alakasız: elendi', 'gbc-core' ), true, static function ( $s ) { return 'hayir' === $s['al']; } ),
	);
	foreach ( $gruplar as $gid => $g ) {
		$liste = array_values( array_filter( $a['satir'], $g[2] ) );
		echo '<h3 id="gbc-kw-' . esc_attr( $gid ) . '" style="margin:18px 0 6px;scroll-margin-top:50px">' . esc_html( $g[0] ) . ' <span style="color:#5C6470;font-weight:400">(' . count( $liste ) . ')</span></h3>';
		if ( ! $liste ) { echo '<p style="margin:0"><em>' . esc_html__( 'Yok.', 'gbc-core' ) . '</em></p>'; continue; }
		if ( $g[1] ) { echo '<details><summary style="cursor:pointer;color:#1B4E9B">' . esc_html__( 'Listeyi aç', 'gbc-core' ) . '</summary>'; }
		echo '<table class="widefat striped" style="max-width:1300px"><thead><tr>'
			. '<th>' . esc_html__( 'Kelime', 'gbc-core' ) . '</th>'
			. '<th style="width:80px">' . esc_html__( 'Aylık', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Sayfada', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Karar ve nereye', 'gbc-core' ) . '</th>'
			. '<th style="width:80px">' . esc_html__( 'Yakalama', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Kanibal.', 'gbc-core' ) . '</th>'
			. '<th style="width:150px">' . esc_html__( 'Alaka', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $liste as $s ) {
			$kay = array();
			foreach ( (array) $s['kay'] as $k ) {
				$ad = array( 'ubersuggest' => 'Ubersuggest', 'google_oto' => 'Google öneri', 'gsc' => 'Search Console', 'google_ads' => 'Google Ads', 'tohum' => 'ana kelime' );
				$kay[] = isset( $ad[ $k ] ) ? $ad[ $k ] : $k;
			}
			$hacim = null === $s['hh'] ? '<span style="color:#5C6470">—</span>' : '<strong>' . esc_html( number_format_i18n( $s['hh'] ) ) . '</strong>'
				. '<div style="color:#5C6470;font-size:11px">' . esc_html( 'google_ads' === $s['hk'] ? 'Google Ads' : ( 'ubersuggest' === $s['hk'] ? 'Ubersuggest' : '' ) ) . '</div>';
			$sayfa = '' !== $s['yer'] ? gbc_kw_rozet( '✓ ' . $yer_ad[ $s['yer'] ], '#1A7F37', '#E8F3EC' ) . ( $s['birebir'] ? '' : '<div style="color:#5C6470;font-size:11px">kelimeler ayrı ayrı</div>' )
				: gbc_kw_rozet( '✗ yok', '#B32D2E', '#FBE7E7' );
			$karar = '';
			if ( '' !== $s['oneri'] && isset( $on_ad[ $s['oneri'] ] ) ) {
				$karar = gbc_kw_rozet( $on_ad[ $s['oneri'] ][0], $on_ad[ $s['oneri'] ][1], $on_ad[ $s['oneri'] ][2] ) . ' <span style="color:#3C4149;font-size:12.5px">' . esc_html( $s['oneri_not'] ) . '</span>';
			}
			if ( ! empty( $s['cn'] ) ) {
				$karar .= '<div style="color:#5C6470;font-size:12px;margin-top:3px">' . esc_html( $s['cn'] ) . '</div>';
			}
			if ( '' === $karar ) { $karar = '<span style="color:#5C6470">—</span>'; }
			$yk = '' !== $s['yakalama'] ? gbc_kw_rozet( $s['yakalama'], $yk_renk[ $s['yakalama'] ][0], $yk_renk[ $s['yakalama'] ][1] ) : '<span style="color:#5C6470">—</span>';
			$kk = gbc_kw_normal( $s['k'] );
			$kan = isset( $knr[ $kk ] ) ? ( 'kesin' === $knr[ $kk ]['durum'] ? gbc_kw_rozet( '✗ kesin', '#B32D2E', '#FBE7E7' ) : gbc_kw_rozet( '⚠ risk', '#8A6100', '#FCF6E8' ) ) . '<div style="font-size:11px"><a href="#gbc-kanibal">ayrıntı</a></div>'
				: ( 'hayir' === $s['al'] ? '' : '<span style="color:#1A7F37">✓ yok</span>' );
			/* v1.47.6: düğme yok; karar yazılı durur, yanlışsa tek küçük "değiştir" bağlantısı tersine çevirir. */
			if ( 'evet' === $s['al'] ) { $btn = gbc_kw_rozet( '✓ alakalı', '#1A7F37', '#E8F3EC' ); $ters = 'hayir'; }
			elseif ( 'hayir' === $s['al'] ) { $btn = gbc_kw_rozet( '✗ alakasız', '#5C6470', '#F0F0F1' ); $ters = 'evet'; }
			else { $btn = gbc_kw_rozet( 'kararsız', '#8A6100', '#FCF6E8' ); $ters = 'evet'; }
			$btn .= ' <a style="font-size:11px;color:#5C6470" href="' . esc_url( gbc_kw_islem_url( $pid, 'alaka', array( 'k' => $s['k'], 'd' => $ters ) ) ) . '">değiştir</a>';
			echo '<tr><td><strong>' . esc_html( $s['k'] ) . '</strong><div style="color:#5C6470;font-size:11px">' . esc_html( implode( ' · ', $kay ) . ' · ' . gbc_kw_niyet_ad( $s['niyet'] ) ) . '</div></td>'
				. '<td>' . $hacim . '</td><td>' . $sayfa . '</td><td>' . $karar . '</td><td>' . $yk . '</td><td>' . $kan . '</td>'
				. '<td>' . $btn . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $g[1] ) { echo '</details>'; }
	}
}
