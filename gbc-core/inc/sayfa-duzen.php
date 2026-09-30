<?php
/**
 * GBC Core · Sayfa Denetimi yeni düzeni (v1.41.0, 30 Eylül 2026)
 * ------------------------------------------------------------
 * Ekranın sırası önem sırasıdır:
 *   1) Özet kartları (GBC skoru, kelime kapsama, kanibalizasyon, Google dizini...)
 *   2) İki sütun: solda YAPILACAKLAR kutusu (bütün bölümlerden toplanan işler,
 *      önceliğe göre), sağda puanlı KONTROLLER (kapalı gruplar; eksik olan açık).
 *   3) Kelime evreni · Kanibalizasyon · Google · Bağlantı raporu · Şema ·
 *      Başlık yapısı · Meta ve yıl · Motorlar
 * Masaüstünde iki sütun, dar ekranda tek sütun.
 *
 * GBC SKORU (100): İçerik 20 · Meta 10 · URL 12 · Bağlantılar 18 · Şema 10 ·
 * Kelime 20 · Kanibalizasyon 10. Verisi olmayan bölüm (kelime taranmamış)
 * puana katılmaz, "veri yok" yazar; yüzde kalan bölümlerden hesaplanır.
 * Dizin durumu puana girmez (yeni sayfa kalite sorunu değildir) ama
 * Yapılacaklar'da görünür.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Puanlı skor, yedi bölüm. */
function gbc_sd_skor( $d, $g, $url_m, $kw, $kn, $silo = null, $ix = null ) {
	$uz = static function ( $s ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s ) : strlen( (string) $s ); };
	$m  = static function ( $ad, $ok, $max, $not = '' ) { return array( 'ad' => $ad, 'puan' => $ok ? $max : 0, 'max' => $max, 'not' => $not ); };
	$k  = static function ( $ad, $puan, $max, $not = '' ) { return array( 'ad' => $ad, 'puan' => (int) max( 0, min( $max, round( $puan ) ) ), 'max' => $max, 'not' => $not ); };
	$gr = array();

	$h2 = 0;
	foreach ( (array) $d['basliklar'] as $b ) { if ( 'h2' === $b['tip'] ) { $h2++; } }
	$alt = (int) $d['alt_yok'];
	$gr[] = array( 'ad' => 'İçerik yapısı', 'id' => 'gbc-k-icerik', 'maddeler' => array(
		$m( 'Tek H1', 1 === (int) $d['h1_adet'], 5, (int) $d['h1_adet'] . ' H1' ),
		$m( 'Başlık sırası atlamıyor', 0 === (int) $d['atlama'], 4 ),
		$m( 'En az 3 H2', $h2 >= 3, 3, $h2 . ' H2' ),
		$k( 'Bütün görsellerde alt metni', 5 - 2 * $alt, 5, $alt ? $alt . ' görselde eksik' : (int) $d['gorsel'] . ' görselin hepsi dolu' ),
		$m( 'Eski yıl ifadesi yok', function_exists( 'gbc_seo_yil_temiz' ) ? gbc_seo_yil_temiz( $d ) : true, 3 ),
	) );

	$au = $uz( $d['aciklama'] ); $bu = $uz( $d['baslik_etiketi'] );
	$gr[] = array( 'ad' => 'Meta', 'id' => 'gbc-k-meta', 'maddeler' => array(
		$m( 'Meta açıklama var', '' !== (string) $d['aciklama'], 3 ),
		$m( 'Meta açıklama 120–165 karakter', $au >= 120 && $au <= 165, 3, $au . ' karakter' ),
		$m( 'Başlık etiketi 30–62 karakter', $bu >= 30 && $bu <= 62, 4, $bu . ' karakter' ),
	) );

	$kritik = array( 'Adres açılıyor (HTTP 200)' => 2, 'Canonical bu adresi gösteriyor' => 2, 'Google\'a kapalı değil (noindex yok)' => 2 );
	$um = array();
	foreach ( (array) $url_m as $x ) { $um[] = $m( $x['ad'], $x['ok'], isset( $kritik[ $x['ad'] ] ) ? $kritik[ $x['ad'] ] : 1, $x['not'] ); }
	$gr[] = array( 'ad' => 'URL', 'id' => 'gbc-k-url', 'maddeler' => $um,
		'ek' => '<a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener" style="font-weight:400">' . esc_html( (string) wp_parse_url( $d['url'], PHP_URL_PATH ) ) . '</a>'
			. ' <span style="font-weight:400;color:#5C6470">· HTTP ' . (int) $d['kod'] . ' · ' . esc_html( function_exists( 'size_format' ) ? size_format( (int) $d['bayt'] ) : round( (int) $d['bayt'] / 1024 ) . ' KB' ) . '</span>' );

	$o = gbc_br_ozet( $g );
	$ic_yon = 0; $rel = 0;
	foreach ( $g['ic'] as $r ) { if ( 'yon' === $r['durum'] ) { $ic_yon++; } }
	foreach ( $g['ortaklik'] as $r ) { if ( false === strpos( (string) $r['rel'], 'sponsored' ) ) { $rel++; } }
	$gr[] = array( 'ad' => 'Bağlantılar', 'id' => 'gbc-k-bag', 'maddeler' => array(
		$m( 'En az 3 iç bağlantı', count( $g['ic'] ) >= 3, 3, count( $g['ic'] ) . ' iç bağlantı' ),
		$k( 'Ölü bağlantı yok', 6 - 3 * $o['kirik'], 6, $o['kirik'] ? $o['kirik'] . ' ölü bağlantı' : '' ),
		$k( 'İç bağlantılar son adrese gidiyor', 2 - $ic_yon, 2, $ic_yon ? $ic_yon . ' yönlendiriyor' : '' ),
		$k( 'Ortaklık linklerinin yapısı doğru', 5 - 2 * $o['yapi_hata'], 5, $o['yapi_hata'] ? $o['yapi_hata'] . ' hatalı' : ( $g['ortaklik'] ? $o['yapi_ok'] . ' link kazanç yazmaya uygun' : 'ortaklık linki yok' ) ),
		$k( 'Ortaklık linklerinde rel="sponsored"', 2 - $rel, 2, $rel ? $rel . ' linkte eksik' : '' ),
	) );

	$sd = function_exists( 'gbc_seo_sema_denetim' ) ? gbc_seo_sema_denetim( $d ) : array( 'eksik' => array() );
	$ek = count( $sd['eksik'] );
	$gr[] = array( 'ad' => 'Şema', 'id' => 'gbc-k-sema', 'maddeler' => array(
		$m( 'Şema basılıyor', ! empty( $d['sema'] ), 2 ),
		$k( 'Olması gereken şemaların hepsi var', 8 - 3 * $ek, 8, $ek ? implode( ', ', array_keys( $sd['eksik'] ) ) . ' eksik' : '' ),
	) );

	/* Kelime — veri varsa */
	if ( $kw && ! empty( $kw['v']['kelimeler'] ) ) {
		$eksik_h = 0; $eksik_n = 0;
		foreach ( $kw['satir'] as $s ) {
			if ( 'evet' === $s['al'] && '' === $s['yer'] && ! in_array( $s['oneri'], array( 'atla', 'bekle' ), true ) && (int) $s['hh'] >= 20 ) { $eksik_n++; $eksik_h += (int) $s['hh']; }
		}
		$gr[] = array( 'ad' => 'Kelime', 'id' => 'gbc-k-kelime', 'maddeler' => array(
			$k( 'Uzun kuyruk kapsaması', 12 * $kw['ozet']['uk_oran'] / 100, 12, '%' . (int) $kw['ozet']['uk_oran'] ),
			$k( 'Hacimli (aylık 20+) eksik kelime yok', 8 - 2 * $eksik_n, 8, $eksik_n ? $eksik_n . ' kelime, aylık ' . $eksik_h . ' arama' : '' ),
		) );
	} else {
		$gr[] = array( 'ad' => 'Kelime', 'id' => 'gbc-k-kelime', 'yok' => true, 'maddeler' => array( array( 'ad' => 'Kelime evreni taranmamış', 'puan' => 0, 'max' => 0, 'not' => 'puana katılmadı' ) ) );
	}

	/* Kanibalizasyon */
	if ( is_array( $kn ) ) {
		$gr[] = array( 'ad' => 'Kanibalizasyon', 'id' => 'gbc-k-kanibal', 'maddeler' => array(
			$k( 'Kesin çatışma yok (Search Console)', 6 - 3 * (int) $kn['ozet']['kesin'], 6, (int) $kn['ozet']['kesin'] ? (int) $kn['ozet']['kesin'] . ' kelime' : '' ),
			$k( 'Başka sayfa aynı kelimeyi hedeflemiyor', 4 - (int) $kn['ozet']['risk'], 4, (int) $kn['ozet']['risk'] ? (int) $kn['ozet']['risk'] . ' kelime risk' : '' ),
		) );
	}

	/* v1.45.0: Silo (8. bölüm). Hesaplanmadıysa (null) hiç eklenmez. */
	if ( null !== $silo && function_exists( 'gbc_silo_skor_grubu' ) ) { $gr[] = gbc_silo_skor_grubu( $silo ); }

	/* v1.47.1: Google dizini (9. bölüm, 4 puan). Halil: "Google dizine eklenmişse yüzde yüz olur."
	   Dizin bilgisi alınamadıysa (API yok/hata) puana katılmaz. */
	if ( is_array( $ix ) && in_array( $ix['durum'], array( 'var', 'yok' ), true ) ) {
		$gr[] = array( 'ad' => 'Google dizini', 'id' => 'gbc-k-dizin', 'maddeler' => array(
			$m( 'Google dizininde', 'var' === $ix['durum'], 4, 'var' === $ix['durum'] ? '' : ( '' !== (string) $ix['kapsam'] ? (string) $ix['kapsam'] : 'dizinde değil' ) ),
		) );
	}

	$top = 0; $maxt = 0;
	foreach ( $gr as $i => $x ) {
		$p = 0; $mx = 0;
		foreach ( $x['maddeler'] as $y ) { $p += $y['puan']; $mx += $y['max']; }
		$gr[ $i ]['puan'] = $p; $gr[ $i ]['max'] = $mx;
		if ( empty( $x['yok'] ) ) { $top += $p; $maxt += $mx; }
	}
	return array( 'toplam' => $maxt ? (int) round( $top / $maxt * 100 ) : 0, 'puan' => $top, 'max' => $maxt, 'gruplar' => $gr );
}

/** Kompakt kontroller: gruplar kapalı, eksik olan açık. */
function gbc_sd_kontroller( $sk ) {
	echo '<div id="gbc-kontroller" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 14px">';
	echo '<div style="display:flex;justify-content:space-between;align-items:baseline"><strong style="font-size:15px">' . esc_html__( 'Kontroller', 'gbc-core' ) . '</strong>'
		. '<span style="color:#5C6470">' . esc_html( sprintf( __( '%1$d / %2$d puan', 'gbc-core' ), $sk['puan'], $sk['max'] ) ) . '</span></div>';
	foreach ( $sk['gruplar'] as $gr ) {
		$tam = ! empty( $gr['yok'] ) || $gr['puan'] === $gr['max'];
		$renk = ! empty( $gr['yok'] ) ? '#5C6470' : ( $tam ? '#1A7F37' : '#B32D2E' );
		echo '<details id="' . esc_attr( $gr['id'] ) . '"' . ( $tam ? '' : ' open' ) . ' style="border-top:1px solid #EDEFF2;padding:6px 0;scroll-margin-top:50px">';
		echo '<summary style="cursor:pointer;display:flex;justify-content:space-between;font-weight:600">'
			. '<span>' . ( $tam ? '✓ ' : '✗ ' ) . esc_html( $gr['ad'] ) . ( ! empty( $gr['ek'] ) ? ' <span style="font-size:12px">' . $gr['ek'] . '</span>' : '' ) . '</span>'
			. '<span style="color:' . $renk . '">' . ( ! empty( $gr['yok'] ) ? esc_html__( 'veri yok', 'gbc-core' ) : (int) $gr['puan'] . ' / ' . (int) $gr['max'] ) . '</span></summary>';
		echo '<table style="width:100%;font-size:13px;margin-top:4px">';
		foreach ( $gr['maddeler'] as $y ) {
			$ok = ( $y['puan'] === $y['max'] );
			$yarim = ( ! $ok && $y['puan'] > 0 );
			echo '<tr><td style="width:18px;color:' . ( $ok ? '#1A7F37' : ( $yarim ? '#8A6100' : '#B32D2E' ) ) . ';font-weight:700">' . ( $ok ? '✓' : '✗' ) . '</td>'
				. '<td>' . esc_html( $y['ad'] ) . ( '' !== $y['not'] ? ' <span style="color:#5C6470">— ' . esc_html( $y['not'] ) . '</span>' : '' ) . '</td>'
				. '<td style="text-align:right;white-space:nowrap;color:#5C6470">' . (int) $y['puan'] . '/' . (int) $y['max'] . '</td></tr>';
		}
		echo '</table></details>';
	}
	echo '</div>';
}

/**
 * Yapılacaklar: bütün bölümlerden toplanan işler, önceliğe göre.
 * @return array liste: [ oncelik 1|2|3, metin, capa ]
 */
function gbc_sd_yapilacaklar( $d, $g, $url_m, $kw, $kn, $ix, $silo = null ) {
	$l = array();
	$ekle = static function ( $o, $metin, $capa ) use ( &$l ) { $l[] = array( $o, $metin, $capa ); };

	foreach ( array( 'ortaklik', 'youtube', 'dis', 'ic' ) as $grup ) {
		foreach ( $g[ $grup ] as $r ) {
			if ( 'kirik' === $r['durum'] ) { $ekle( 1, 'Ölü bağlantıyı düzelt: "' . ( '' !== $r['metin'] ? $r['metin'] : $r['url'] ) . '" → ' . $r['url'], '#br-' . $grup ); }
			if ( 'yon' === $r['durum'] ) { $ekle( 2, 'İç bağlantıyı son adrese çevir: ' . $r['url'], '#br-ic' ); }
			if ( ! empty( $r['yapi'] ) && 'hata' === $r['yapi'] ) { $ekle( 1, 'Ortaklık linkinin yapısı hatalı, kazanç yazılmıyor olabilir: ' . ( '' !== $r['metin'] ? $r['metin'] : $r['url'] ), '#br-ortaklik' ); }
		}
	}
	foreach ( (array) $url_m as $x ) { if ( ! $x['ok'] ) { $ekle( in_array( $x['ad'], array( 'Adres açılıyor (HTTP 200)', 'Canonical bu adresi gösteriyor', 'Google\'a kapalı değil (noindex yok)' ), true ) ? 1 : 3, 'URL: ' . $x['ad'] . ( '' !== $x['not'] ? ' (' . $x['not'] . ')' : '' ), '#gbc-k-url' ); } }
	if ( (int) $d['alt_yok'] ) { $ekle( 2, (int) $d['alt_yok'] . ' görselin alt metnini yaz.', '#gbc-k-icerik' ); }
	if ( 1 !== (int) $d['h1_adet'] ) { $ekle( 1, 'Sayfada ' . (int) $d['h1_adet'] . ' H1 var, tek olmalı.', '#gbc-basliklar' ); }
	$au = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $d['aciklama'] ) : 0;
	if ( '' === (string) $d['aciklama'] ) { $ekle( 1, 'Meta açıklama yok, yaz.', '#gbc-k-meta' ); }
	elseif ( $au < 120 || $au > 165 ) { $ekle( 3, 'Meta açıklama ' . $au . ' karakter; 120–165 arasına getir.', '#gbc-k-meta' ); }
	$sd = function_exists( 'gbc_seo_sema_denetim' ) ? gbc_seo_sema_denetim( $d ) : array( 'eksik' => array() );
	foreach ( $sd['eksik'] as $t => $n ) { $ekle( 1, 'Eksik şema: ' . $t . ' (' . $n . ')', '#gbc-sema' ); }
	if ( function_exists( 'gbc_br_sema_oneri' ) ) {
		foreach ( gbc_br_sema_oneri( (string) $d['ham'], gbc_br_sema_bilgi( (string) $d['ham'] ) ) as $t => $n ) { $ekle( 3, 'Şema eklenebilir: ' . $t, '#gbc-sema' ); }
	}

	if ( is_array( $kn ) ) {
		foreach ( $kn['satir'] as $kel => $b ) {
			$ekle( 'kesin' === $b['durum'] ? 1 : 2, 'Kanibalizasyon (' . ( 'kesin' === $b['durum'] ? 'kesin' : 'risk' ) . ') "' . $kel . '": ' . $b['oneri'], '#gbc-kanibal' );
		}
	}
	if ( $kw ) {
		foreach ( $kw['satir'] as $s ) {
			if ( 'evet' !== $s['al'] || '' !== $s['yer'] || in_array( $s['oneri'], array( 'atla', 'bekle', '' ), true ) ) {
				if ( 'evet' === $s['al'] && 'tasi' === $s['oneri'] ) { $ekle( 2, '"' . $s['k'] . '" (aylık ' . (int) $s['hh'] . '): ' . $s['oneri_not'], '#gbc-kw-gecen' ); }
				continue;
			}
			$o = (int) $s['hh'] >= 100 ? 1 : ( (int) $s['hh'] >= 20 ? 2 : 3 );
			$ad = array( 'sayfa' => 'Ayrı sayfa aç', 'h2' => 'Yeni H2', 'h3' => 'H3 ekle', 'sss' => 'SSS\'ye ekle', 'metin' => 'Paragrafta geçir' );
			$ekle( $o, ( isset( $ad[ $s['oneri'] ] ) ? $ad[ $s['oneri'] ] : 'Ekle' ) . ': "' . $s['k'] . '" (aylık ' . ( null === $s['hh'] ? '?' : (int) $s['hh'] ) . ')' . ( '' !== $s['oneri_not'] ? ' — ' . $s['oneri_not'] : '' ), '#gbc-kw-yapilacak' );
		}
		if ( empty( $kw['v']['kelimeler'] ) ) { $ekle( 2, 'Kelime evreni boş: "Tara" ile başla.', '#gbc-kelime' ); }
	}
	if ( is_array( $ix ) && 'yok' === $ix['durum'] ) { $ekle( 2, 'Google dizininde değil (' . $ix['kapsam'] . '). Search Console\'dan dizine eklenmesini iste; günlük kontrol girdiği günü yakalar.', '#gbc-google' ); }

	if ( is_array( $silo ) && ! empty( $silo['is'] ) ) { foreach ( $silo['is'] as $x ) { $ekle( $x[0], $x[1], '#gbc-silo' ); } }

	usort( $l, static function ( $a, $b ) { return $a[0] - $b[0]; } );
	return $l;
}

function gbc_sd_yapilacak_kutu( $l ) {
	$ad = array( 1 => array( 'Yüksek', '#B32D2E', '#FBE7E7' ), 2 => array( 'Orta', '#8A6100', '#FCF6E8' ), 3 => array( 'Düşük', '#5C6470', '#F1EFEA' ) );
	echo '<div id="gbc-yapilacak" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 14px">';
	echo '<div style="display:flex;justify-content:space-between;align-items:baseline"><strong style="font-size:15px">' . esc_html__( 'Yapılacaklar', 'gbc-core' ) . '</strong>'
		. '<span style="color:#5C6470">' . esc_html( sprintf( __( '%d iş · önem sırasıyla', 'gbc-core' ), count( $l ) ) ) . '</span></div>';
	if ( ! $l ) {
		echo '<p style="color:#1A7F37;font-weight:600;margin:8px 0 0">' . esc_html__( '✓ Bu sayfada yapılacak iş yok.', 'gbc-core' ) . '</p></div>';
		return;
	}
	echo '<ol style="margin:8px 0 0 18px;padding:0">';
	foreach ( $l as $i => $x ) {
		$r = $ad[ $x[0] ];
		echo '<li style="margin:0 0 6px;line-height:1.5;font-size:13.5px' . ( $i >= 12 ? ';display:none" class="gbc-sd-fazla' : '' ) . '">'
			. '<span style="display:inline-block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;color:' . $r[1] . ';background:' . $r[2] . ';margin-right:6px">' . esc_html( $r[0] ) . '</span>'
			. esc_html( $x[1] ) . ' <a href="' . esc_attr( $x[2] ) . '" style="font-size:12px">' . esc_html__( 'git', 'gbc-core' ) . '</a></li>';
	}
	echo '</ol>';
	if ( count( $l ) > 12 ) {
		echo '<a href="#" onclick="document.querySelectorAll(\'.gbc-sd-fazla\').forEach(function(e){e.style.display=\'list-item\'});this.remove();return false;" style="font-size:13px">'
			. esc_html( sprintf( __( 'Kalan %d işi göster', 'gbc-core' ), count( $l ) - 12 ) ) . '</a>';
	}
	echo '</div>';
}

/** Başlık ağacı. */
function gbc_sd_basliklar( $d ) {
	echo '<h2 id="gbc-basliklar" style="margin-top:26px">' . esc_html__( 'Başlık yapısı', 'gbc-core' ) . '</h2>';
	echo '<details><summary style="cursor:pointer;color:#1B4E9B">' . esc_html( sprintf( __( '%1$d başlık · %2$d H1 · %3$d atlama — aç', 'gbc-core' ), count( $d['basliklar'] ), (int) $d['h1_adet'], (int) $d['atlama'] ) ) . '</summary>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;max-width:1000px;line-height:1.8;margin-top:6px">';
	$onceki = 1;
	foreach ( $d['basliklar'] as $b ) {
		$sev = (int) substr( $b['tip'], 1 );
		$atladi = ( $sev > $onceki + 1 );
		$onceki = $sev;
		echo '<div style="padding-left:' . ( ( $sev - 1 ) * 18 ) . 'px' . ( $atladi ? ';color:#B32D2E' : '' ) . '"><strong>' . esc_html( strtoupper( $b['tip'] ) ) . '</strong> ' . esc_html( $b['metin'] )
			. ( $atladi ? ' <span style="font-size:12px;font-weight:600">← sıra atlandı</span>' : '' ) . '</div>';
	}
	echo '</div></details>';
}

/** Meta ve yıl + motorlar. */
function gbc_sd_meta_motor( $pid, $d ) {
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Meta ve yıl', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:1000px"><tbody>';
	echo '<tr><td style="width:160px">' . esc_html__( 'Başlık etiketi', 'gbc-core' ) . '</td><td>' . esc_html( $d['baslik_etiketi'] ) . ' <span style="color:#5C6470">(' . (int) mb_strlen( $d['baslik_etiketi'] ) . ')</span></td></tr>';
	echo '<tr><td>' . esc_html__( 'Meta açıklama', 'gbc-core' ) . '</td><td>' . ( '' !== $d['aciklama'] ? esc_html( $d['aciklama'] ) . ' <span style="color:#5C6470">(' . (int) mb_strlen( $d['aciklama'] ) . ')</span>' : '<span style="color:#B32D2E;font-weight:600">yok</span>' ) . '</td></tr>';
	$y = array();
	foreach ( (array) $d['yillar'] as $yil => $n ) { $y[] = $yil . ' (' . $n . ')'; }
	echo '<tr><td>' . esc_html__( 'Yıl ifadeleri', 'gbc-core' ) . '</td><td>' . esc_html( $y ? implode( ' · ', $y ) : 'yok' ) . '</td></tr></tbody></table>';

	if ( ! function_exists( 'gbc_seo_motorlar' ) ) { return; }
	$mot = gbc_seo_motorlar( $pid, isset( $d['ham'] ) ? $d['ham'] : '' );
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Bu sayfada çalışan motorlar', 'gbc-core' ) . '</h2><div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">';
	foreach ( $mot['calisan'] as $m ) { echo '<span style="background:#E7F1EF;color:#0B5B55;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600">' . esc_html( $m['ad'] ) . '</span>'; }
	echo '</div>';
	foreach ( $mot['oneri'] as $o ) { echo '<div style="font-size:13px;color:#3C4149;line-height:1.7">· ' . esc_html( $o ) . '</div>'; }
}

/** Yeni düzenin tamamı. seo.php'deki eski akışın yerine basılır. */
/**
 * v1.43.0: Denetim sonucunu sayfanın _gbc_seo kaydına yazar (skor sürüm 2 = 7 bölümlü GBC skoru).
 * Sayfa Denetimi ekranı ve saat başı toplama aynı fonksiyonu kullanır; Envanter bunu okur.
 * Geçmiş silinmez: son 8 denetimin özeti tutulur.
 */
function gbc_sd_kayit( $pid, $d, $sk, $yap, $ix = null, $kw = null, $br = null ) {
	$pid = (int) $pid;
	if ( ! $pid || ! is_array( $sk ) ) { return; }
	$bolum = array();
	foreach ( $sk['gruplar'] as $g ) { $bolum[ $g['ad'] ] = empty( $g['yok'] ) ? array( (int) $g['puan'], (int) $g['max'] ) : null; }
	$y1 = 0; foreach ( (array) $yap as $x ) { if ( 1 === (int) $x[0] ) { $y1++; } }
	$yeni = array(
		'zaman'      => time(),
		'surum'      => defined( 'GBC_CORE_SURUM' ) ? GBC_CORE_SURUM : '',
		'skor_surum' => 2,
		'skor'       => (int) $sk['toplam'],
		'bolum'      => $bolum,
		'yapilacak'  => count( (array) $yap ),
		'yap1'       => $y1,
		'ilk_isler'  => array_slice( array_map( static function ( $x ) { return array( (int) $x[0], (string) $x[1] ); }, (array) $yap ), 0, 5 ),
		'h1'         => (int) $d['h1_adet'],
		'gorsel'     => (int) $d['gorsel'],
		'alt_yok'    => (int) $d['alt_yok'],
		'aciklama'   => ( '' !== (string) $d['aciklama'] ),
		'sema'       => $d['sema'],
		'ic'         => (int) $d['ic'],
		'dis'        => (int) $d['dis'],
		'ortaklik'   => $d['ortaklik'],
		'kod'        => (int) $d['kod'],
		'bayt'       => (int) $d['bayt'],
		'dizin'      => is_array( $ix ) ? (string) $ix['durum'] : '',
		'kelime'     => ( $kw && ! empty( $kw['v']['kelimeler'] ) ) ? array( (int) $kw['ozet']['oran'], (int) $kw['ozet']['alakali'] ) : null,
	);
	$onceki = get_post_meta( $pid, '_gbc_seo', true );
	$gecmis = ( is_array( $onceki ) && ! empty( $onceki['gecmis'] ) ) ? (array) $onceki['gecmis'] : array();
	if ( is_array( $onceki ) && ! empty( $onceki['zaman'] ) ) {
		array_unshift( $gecmis, array( 'zaman' => (int) $onceki['zaman'], 'skor' => (int) $onceki['skor'],
			'skor_surum' => isset( $onceki['skor_surum'] ) ? (int) $onceki['skor_surum'] : 1,
			'dizin' => isset( $onceki['dizin'] ) ? (string) $onceki['dizin'] : '' ) );
		$gecmis = array_slice( $gecmis, 0, 8 );
	}
	$yeni['gecmis'] = $gecmis;
	/* Dizin durumu değişti mi (yok → var): günlük kontrolün yakaladığı gün. */
	if ( is_array( $onceki ) && isset( $onceki['dizin'] ) && 'yok' === $onceki['dizin'] && 'var' === $yeni['dizin'] ) { $yeni['dizine_girdi'] = time(); }
	elseif ( is_array( $onceki ) && ! empty( $onceki['dizine_girdi'] ) ) { $yeni['dizine_girdi'] = (int) $onceki['dizine_girdi']; }
	update_post_meta( $pid, '_gbc_seo', $yeni );
	/* v1.45.0: bu sayfanın verdiği iç linkler (silo "gelen" hesabı buradan). Biçim: ",12,345," */
	if ( is_array( $br ) && function_exists( 'gbc_silo_giden' ) ) {
		$gid = array_keys( gbc_silo_giden( $br ) );
		update_post_meta( $pid, '_gbc_giden', ',' . implode( ',', $gid ) . ( $gid ? ',' : '' ) );
	}
	/* v1.43.1: Envanter önbelleği silinmez (silinirse ekran her şeyi yeniden hesaplar); bu sayfanın satırı yerinde güncellenir. */
	$ix = get_transient( 'gbc_env_indeks' );
	if ( is_array( $ix ) ) {
		foreach ( $ix as $i => $r ) {
			if ( (int) $r['id'] !== $pid ) { continue; }
			$ix[ $i ] = array_merge( $r, array( 'skor' => $yeni['skor'], 'yeni' => true, 'yap' => $yeni['yapilacak'], 'yap1' => $yeni['yap1'], 'dizin' => $yeni['dizin'], 'olcum' => $yeni['zaman'],
				'onceki' => ( ! empty( $gecmis[0]['skor_surum'] ) && (int) $gecmis[0]['skor_surum'] >= 2 ) ? (int) $gecmis[0]['skor'] : null ) );
		}
		set_transient( 'gbc_env_indeks', $ix, 2 * HOUR_IN_SECONDS );
	}
	$pa = get_transient( 'gbc_plan_analiz' );
	if ( is_array( $pa ) && ! empty( $pa['satir'] ) && function_exists( 'gbc_plan_skor' ) ) {
		foreach ( $pa['satir'] as $i => $s ) { if ( (int) $s['e_pid'] === $pid ) { $pa['satir'][ $i ]['skor'] = gbc_plan_skor( $pid ); } }
		set_transient( 'gbc_plan_analiz', $pa, 6 * HOUR_IN_SECONDS );
	}
}

/**
 * v1.43.0: Tek sayfanın tam denetimi (ekransız). Toplama bunu çağırır.
 * Ortaklık ağ adreslerine istek atmaz (bağlantı raporunun kuralı).
 */
function gbc_sd_tam_denetim( $pid ) {
	$d = gbc_seo_icerik_denetim( (int) $pid );
	if ( ! empty( $d['hata'] ) ) { return array( 'hata' => $d['hata'] ); }
	$ham = isset( $d['ham'] ) ? $d['ham'] : '';
	if ( function_exists( 'gbc_kml_guncelle' ) ) { gbc_kml_guncelle( (int) $pid, false, $ham ); } /* v1.47.4: harita → şema durakları, 7 günde bir */
	$br  = function_exists( 'gbc_br_rapor' ) ? gbc_br_rapor( $ham, false ) : null;
	if ( ! is_array( $br ) ) { return array( 'hata' => 'Bağlantı raporu çıkarılamadı.' ); }
	$kw  = function_exists( 'gbc_kw_analiz' ) ? gbc_kw_analiz( $pid, $ham ) : null;
	$kn  = ( $kw && ! empty( $kw['v']['kelimeler'] ) && function_exists( 'gbc_kn_analiz' ) ) ? gbc_kn_analiz( $pid, $d['url'], $kw['tohum'], $kw['satir'] ) : null;
	$um  = function_exists( 'gbc_br_url_denetim' ) ? gbc_br_url_denetim( $d['url'], $ham, (int) $d['kod'] ) : array();
	$ix  = ( function_exists( 'gbc_gs_index_ozet' ) && function_exists( 'gbc_gs_index' ) ) ? gbc_gs_index_ozet( gbc_gs_index( $d['url'] ) ) : null;
	if ( is_array( $ix ) && 'bilinmiyor' === $ix['durum'] ) { $ix = null; }
	$si  = function_exists( 'gbc_silo_analiz' ) ? gbc_silo_analiz( $pid, $br ) : null;
	$sk  = gbc_sd_skor( $d, $br, $um, $kw, $kn, $si, $ix );
	$yap = gbc_sd_yapilacaklar( $d, $br, $um, $kw, $kn, $ix, $si );
	gbc_sd_kayit( $pid, $d, $sk, $yap, $ix, $kw, $br );
	if ( $kw && function_exists( 'gbc_fk_kaydet' ) ) { gbc_fk_kaydet( $pid, $kw, $kn ); }
	return array( 'skor' => (int) $sk['toplam'], 'yapilacak' => count( $yap ) );
}

function gbc_sd_ekran( $pid, $d, $br, $url_m, $kw, $kn ) {
	if ( function_exists( 'gbc_kml_guncelle' ) ) { gbc_kml_guncelle( (int) $pid, function_exists( 'gbc_br_taze_mi' ) && gbc_br_taze_mi(), isset( $d['ham'] ) ? (string) $d['ham'] : '' ); }
	$si = function_exists( 'gbc_silo_analiz' ) ? gbc_silo_analiz( $pid, $br ) : null;
	$ix = ( function_exists( 'gbc_gs_index_ozet' ) && function_exists( 'gbc_gs_index' ) ) ? gbc_gs_index_ozet( gbc_gs_index( $d['url'], function_exists( 'gbc_br_taze_mi' ) && gbc_br_taze_mi() ) ) : null;
	$sk = gbc_sd_skor( $d, $br, $url_m, $kw, $kn, $si, $ix );
	$yap = gbc_sd_yapilacaklar( $d, $br, $url_m, $kw, $kn, $ix, $si );
	/* v1.43.0: ekranda görülen skor Envanter'e de yazılır. */
	gbc_sd_kayit( $pid, $d, $sk, $yap, ( is_array( $ix ) && 'bilinmiyor' !== $ix['durum'] ) ? $ix : null, $kw, $br );
	$bolum_n = 0; foreach ( $sk['gruplar'] as $g ) { if ( empty( $g['yok'] ) ) { $bolum_n++; } }

	echo '<style>.gbc-sd-grid{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:16px;align-items:start;max-width:1400px}'
		. '@media (max-width:1100px){.gbc-sd-grid{grid-template-columns:1fr}}'
		. '@media (max-width:782px){.wrap table.widefat{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}.wrap pre,.wrap code{white-space:pre-wrap;word-break:break-word}}'
		. '.gbc-sd-kart{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:14px 0}.gbc-sd-kart>*{min-width:0!important}@media (max-width:600px){.gbc-sd-kart{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gbc-sd-kart>*{padding:12px 14px!important}}</style>';

	/* 1) Kartlar: önem sırasıyla */
	$renk = $sk['toplam'] >= 80 ? '#1A7F37' : ( $sk['toplam'] >= 60 ? '#8A6100' : '#B32D2E' );
	echo '<div class="gbc-sd-kart">';
	echo gbc_br_kart( __( 'GBC skoru', 'gbc-core' ), '%' . $sk['toplam'], esc_html( sprintf( __( '%1$d / %2$d puan · %3$d bölüm', 'gbc-core' ), $sk['puan'], $sk['max'], $bolum_n ) ), $renk, '#gbc-kontroller' );
	$y1 = 0; foreach ( $yap as $x ) { if ( 1 === $x[0] ) { $y1++; } }
	echo gbc_br_kart( __( 'Yapılacak', 'gbc-core' ), (string) count( $yap ), esc_html( sprintf( __( '%d yüksek öncelikli', 'gbc-core' ), $y1 ) ), $y1 ? '#B32D2E' : ( $yap ? '#8A6100' : '#1A7F37' ), '#gbc-yapilacak' );
	if ( $kw && function_exists( 'gbc_kw_kart' ) ) { echo gbc_kw_kart( $kw ); }
	if ( is_array( $kn ) ) {
		$n = (int) $kn['ozet']['kesin'] + (int) $kn['ozet']['risk'];
		echo gbc_br_kart( __( 'Kanibalizasyon', 'gbc-core' ), $n ? '✗ ' . $n : '✓', esc_html( sprintf( __( '%1$d kesin · %2$d risk', 'gbc-core' ), $kn['ozet']['kesin'], $kn['ozet']['risk'] ) ), $n ? '#B32D2E' : '#1A7F37', '#gbc-kanibal' );
	}
	if ( is_array( $si ) ) {
		$sg = function_exists( 'gbc_silo_skor_grubu' ) ? gbc_silo_skor_grubu( $si ) : null;
		if ( $sg && empty( $sg['yok'] ) ) {
			$sp = 0; $sm = 0; foreach ( $sg['maddeler'] as $x ) { $sp += $x['puan']; $sm += $x['max']; }
			$sy = $sm ? (int) round( $sp / $sm * 100 ) : 0;
			echo gbc_br_kart( __( 'Silo', 'gbc-core' ), '%' . $sy, esc_html( sprintf( '%1$d iç sayfaya link veriyor · %2$d sayfadan alıyor', $si['giden_n'], $si['gelen_n'] ) ), $sy >= 80 ? '#1A7F37' : ( $sy >= 50 ? '#8A6100' : '#B32D2E' ), '#gbc-silo' );
		} else {
			echo gbc_br_kart( __( 'Silo', 'gbc-core' ), '—', esc_html( empty( $si['cevre'] ) ? 'plan tablosunda yok' : 'bağlanacak sayfa yok' ), '#5C6470', '#gbc-silo' );
		}
	}
	if ( function_exists( 'gbc_gs_kart' ) ) { echo gbc_gs_kart( $d['url'] ); }
	echo gbc_br_ust_kartlar( $br, $d, $url_m );
	echo gbc_br_kart( __( 'Görsel', 'gbc-core' ), (string) (int) $d['gorsel'], esc_html( sprintf( __( '%d alt metni eksik', 'gbc-core' ), (int) $d['alt_yok'] ) ), $d['alt_yok'] ? '#B32D2E' : '#1A7F37', '#gbc-k-icerik' );
	echo '</div>';

	/* Kart ya da yapılacak bağlantısına basınca kapalı bölüm (details) kendiliğinden açılsın. */
	echo '<script>document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest(\'a[href^="#"]\');if(!a)return;var t=document.querySelector(a.getAttribute("href"));if(!t)return;if(t.tagName==="DETAILS"){t.open=true;}var p=t.closest&&t.closest("details");if(p){p.open=true;}});</script>';

	/* 2) İki sütun */
	echo '<div class="gbc-sd-grid"><div>';
	gbc_sd_yapilacak_kutu( $yap );
	echo '</div><div>';
	gbc_sd_kontroller( $sk );
	/* Alt metni eksik görsellerin adı (eski ekrandan taşındı). */
	if ( ! empty( $d['alt_eksik'] ) ) {
		echo '<div style="margin-top:10px;padding:10px 14px;background:#FBE7E7;border-left:3px solid #B3261E;border-radius:3px;font-size:13px"><strong>' . esc_html__( 'Alt metni eksik görseller', 'gbc-core' ) . '</strong><ul style="margin:6px 0 0">';
		foreach ( $d['alt_eksik'] as $gz ) {
			$ad = '' !== $gz['ad'] ? $gz['ad'] : __( 'adresi okunamadı', 'gbc-core' );
			echo '<li>' . ( $gz['src'] ? '<a href="' . esc_url( $gz['src'] ) . '" target="_blank" rel="noopener">' . esc_html( $ad ) . '</a>' : esc_html( $ad ) ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '</div></div>';

	/* 3) Bölümler: önem sırasıyla */
	if ( $kw && function_exists( 'gbc_kw_ekran' ) ) { gbc_kw_ekran( $pid, $kw, $kn ); }
	if ( $kn && function_exists( 'gbc_kn_ekran' ) ) { gbc_kn_ekran( $kn, $kw ); }
	if ( is_array( $si ) && function_exists( 'gbc_silo_ekran' ) ) { gbc_silo_ekran( $pid, $si ); }
	if ( function_exists( 'gbc_gs_ekran' ) ) { gbc_gs_ekran( $pid, $d['url'] ); }
	gbc_br_ekran( $pid, isset( $d['ham'] ) ? $d['ham'] : '', $br );
	gbc_br_sema_blok( $d );
	gbc_sd_basliklar( $d );
	gbc_sd_meta_motor( $pid, $d );

	/* v1.47.1: "yeniden kontrol et" düğmesi tepeye taşındı (arama kutusunun yanı). */
}
