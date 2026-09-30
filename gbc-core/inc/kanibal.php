<?php
/**
 * GBC Core · Kanibalizasyon (v1.40.0, 30 Eylül 2026)
 * ------------------------------------------------------------
 * Bir kelimeye sitede BİRDEN FAZLA sayfa gidiyor mu?
 *   1) KESİN: Search Console'da aynı kelime için başka sayfamız gösterim
 *      alıyor (Google iki sayfa arasında kararsız).
 *   2) RİSK: başka bir sayfanın başlığı aynı kelimeyi hedefliyor
 *      (v1.47.1: yalnız YAYINDAKİ sayfalar; taslak yayına girene kadar çatışma sayılmaz, altta not olarak görünür).
 * Her satırda kelimenin hangi sayfada kalması gerektiği önerilir:
 * konuya özel sayfa (otel, ulaşım, yemek) o konunun kelimesini alır,
 * genel ve gezi kelimeleri şehir rehberinde (hub) kalır.
 *
 * Search Console sorgusu site geneli, kelimede tohum geçenlerle süzülür,
 * 12 saat saklanır. Ziyaretçi isteğinde bu dosya yüklenmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Search Console: tohum geçen kelimeler × sayfalar (son 90 gün). */
function gbc_kn_gsc( $tohum ) {
	if ( '' === (string) $tohum || ! function_exists( 'gbc_km_api' ) || ! function_exists( 'gbc_km_ayar' ) ) { return array(); }
	$site = gbc_km_ayar( 'gsc_site', '' );
	if ( '' === $site ) { return array(); }
	$ob = 'gbc_kn_gsc_' . md5( $tohum );
	$c  = get_transient( $ob );
	if ( is_array( $c ) ) { return $c; }
	$bugun = current_time( 'timestamp' );
	$g = gbc_km_api( 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', array(
		'startDate'             => gmdate( 'Y-m-d', $bugun - 89 * DAY_IN_SECONDS ),
		'endDate'               => gmdate( 'Y-m-d', $bugun ),
		'dimensions'            => array( 'query', 'page' ),
		'dataState'             => 'all',
		'rowLimit'              => 1000,
		'dimensionFilterGroups' => array( array( 'filters' => array( array( 'dimension' => 'query', 'operator' => 'contains', 'expression' => $tohum ) ) ) ),
	) );
	if ( is_wp_error( $g ) ) { return array(); }
	$h = array();
	foreach ( ( isset( $g['rows'] ) ? (array) $g['rows'] : array() ) as $r ) {
		$q = function_exists( 'gbc_kw_normal' ) ? gbc_kw_normal( $r['keys'][0] ) : $r['keys'][0];
		$h[ $q ][] = array( 'sayfa' => $r['keys'][1], 'gos' => (int) $r['impressions'], 'tik' => (int) $r['clicks'], 'sira' => (float) $r['position'] );
	}
	set_transient( $ob, $h, 12 * HOUR_IN_SECONDS );
	return $h;
}

/** Başlığında tohum geçen diğer sayfalar. v1.47.1: $taslak=false yalnız yayındakiler (Halil: "taslak kanibalizasyona alınmaz,
 *  aktif olunca çatışma olur"); $taslak=true yalnız yayında olmayanlar (bilgi notu için). */
function gbc_kn_komsular( $tohum, $pid, $taslak = false ) {
	if ( '' === (string) $tohum ) { return array(); }
	$liste = get_posts( array(
		's' => $tohum, 'post_type' => array( 'post', 'page' ), 'posts_per_page' => 40,
		'post_status' => $taslak ? array( 'draft', 'future', 'pending' ) : array( 'publish' ), 'suppress_filters' => false,
	) );
	$out = array();
	foreach ( $liste as $p ) {
		if ( (int) $p->ID === (int) $pid ) { continue; }
		$bas = gbc_kw_normal( $p->post_title );
		if ( false === strpos( $bas, gbc_kw_normal( $tohum ) ) ) { continue; }
		$out[] = array( 'id' => (int) $p->ID, 'baslik' => $p->post_title, 'norm' => $bas, 'durum' => $p->post_status,
			'url' => get_permalink( $p->ID ), 'odak' => gbc_kw_normal( (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true ) ) );
	}
	return $out;
}

/** Bir başlık hangi konuya özel? (otel sayfası, ulaşım sayfası...) */
function gbc_kn_baslik_niyet( $baslik ) {
	$b = gbc_kw_normal( $baslik );
	/* Rehber başlıkları "gezilecek yerler ... nasıl gidilir" diye birden çok konu sayar: gezi rehberi sayılır. */
	if ( preg_match( '/(gezilecek|gezi rehberi|görülecek)/u', $b ) ) { return 'gezi'; }
	return gbc_kw_niyet( $b );
}

/** Başlığın ana konusu: ilk virgül / iki nokta öncesi (örn. "amalfi sahili"). */
function gbc_kn_ana_konu( $baslik ) {
	$p = preg_split( '/[:,(\-–]/u', (string) $baslik );
	return gbc_kw_normal( $p[0] );
}

/**
 * Tam analiz.
 * @param array $satirlar gbc_kw_analiz()['satir']
 * @return array( 'satir' => kelime => bilgi, 'komsu' => [], 'ozet' => [] )
 */
function gbc_kn_analiz( $pid, $url, $tohum, $satirlar ) {
	$gsc    = gbc_kn_gsc( $tohum );
	$komsu  = gbc_kn_komsular( $tohum, $pid );
	$bu     = rtrim( (string) $url, '/' );
	$out    = array();
	$o      = array( 'kesin' => 0, 'risk' => 0 );
	foreach ( $satirlar as $s ) {
		if ( 'hayir' === $s['al'] ) { continue; }
		$k = gbc_kw_normal( $s['k'] );
		$kokler = gbc_kw_kokler( $k );
		$bilgi = array( 'baska_gsc' => array(), 'bu_gsc' => null, 'baska_baslik' => array(), 'durum' => '', 'oneri' => '' );

		/* 1) Search Console: başka sayfa gösterim alıyor mu */
		foreach ( isset( $gsc[ $k ] ) ? $gsc[ $k ] : array() as $r ) {
			if ( rtrim( $r['sayfa'], '/' ) === $bu ) { $bilgi['bu_gsc'] = $r; } else { $bilgi['baska_gsc'][] = $r; }
		}
		/* 2) Başlık hedefi: tek kelimelik ana kelime (şehir adı) hariç; odak kelime birebirse her zaman. */
		foreach ( $komsu as $p ) {
			$hedef = ( '' !== $p['odak'] && $p['odak'] === $k );
			if ( ! $hedef && count( $kokler ) >= 2 ) {
				$yer = gbc_kw_nerede( $k, array( array( 'yer' => 'baslik', 'metin' => $p['norm'] ) ) );
				$hedef = ( '' !== $yer['yer'] );
			}
			if ( $hedef ) { $bilgi['baska_baslik'][] = $p; }
		}

		if ( $bilgi['baska_gsc'] ) { $bilgi['durum'] = 'kesin'; $o['kesin']++; }
		elseif ( $bilgi['baska_baslik'] ) { $bilgi['durum'] = 'risk'; $o['risk']++; }
		else { continue; }

		/* Kim almalı: kelimenin konusu ile rakip sayfanın konusu aynı ve özelse rakip; değilse bu sayfa (hub). */
		$kn   = gbc_kw_niyet( $k );
		$rakip = $bilgi['baska_baslik'] ? $bilgi['baska_baslik'][0] : null;
		$rakip_niyet = $rakip ? gbc_kn_baslik_niyet( $rakip['baslik'] ) : '';
		/* Rakip sayfanın ana konusu (Amalfi) kelimede geçiyor, bu sayfanın başlığında geçmiyorsa kelime rakibindir. */
		$rakip_konu = $rakip ? explode( ' ', gbc_kn_ana_konu( $rakip['baslik'] ) ) : array();
		$rakip_konu_var = false;
		if ( $rakip_konu && '' !== $rakip_konu[0] && false === strpos( gbc_kw_normal( $tohum ), $rakip_konu[0] ) ) {
			foreach ( explode( ' ', $k ) as $w ) { if ( 0 === strpos( $w, $rakip_konu[0] ) ) { $rakip_konu_var = true; } }
		}
		if ( $rakip && $rakip_konu_var ) {
			$bilgi['oneri'] = sprintf( 'Bu kelime "%s" sayfasının konusu. Burada kısaca geç ve o sayfaya iç link ver; bu sayfada başlıkta hedefleme.', $rakip['baslik'] );
			$bilgi['sahip'] = 'rakip';
		} elseif ( $rakip && 'genel' !== $kn && $kn === $rakip_niyet && 'gezi' !== $kn ) {
			$bilgi['oneri'] = sprintf( 'Bu kelime "%s" sayfasının konusu. Burada yalnız kısa geç ve o sayfaya iç link ver; başlıkta hedefleme.', $rakip['baslik'] );
			$bilgi['sahip'] = 'rakip';
		} else {
			$ad = $rakip ? $rakip['baslik'] : ( $bilgi['baska_gsc'] ? $bilgi['baska_gsc'][0]['sayfa'] : '' );
			$bilgi['oneri'] = sprintf( 'Bu kelime bu sayfada kalsın. "%s" başlığından ve metninden bu kelimeyi hedeflemeyi kaldır, oradan buraya iç link ver.', $ad );
			$bilgi['sahip'] = 'bu';
		}
		$out[ $k ] = $bilgi;
	}
	$tas = array();
	foreach ( gbc_kn_komsular( $tohum, $pid, true ) as $p ) {
		foreach ( $satirlar as $sx ) {
			if ( 'hayir' === $sx['al'] ) { continue; }
			$kk = gbc_kw_normal( $sx['k'] );
			if ( count( gbc_kw_kokler( $kk ) ) < 2 && $p['odak'] !== $kk ) { continue; }
			$y = gbc_kw_nerede( $kk, array( array( 'yer' => 'baslik', 'metin' => $p['norm'] ) ) );
			if ( '' !== $y['yer'] || $p['odak'] === $kk ) { $tas[ $p['id'] ]['sayfa'] = $p; $tas[ $p['id'] ]['kelime'][] = $kk; }
		}
	}
	return array( 'satir' => $out, 'komsu' => $komsu, 'ozet' => $o, 'taslak' => $tas );
}

/** Kanibalizasyon bölümü. */
function gbc_kn_ekran( $kn, $kw ) {
	echo '<h2 id="gbc-kanibal" style="margin-top:26px">' . esc_html__( 'Kanibalizasyon', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:1000px;color:#444;margin-top:4px">' . esc_html__( 'Aynı kelimeye sitede başka bir sayfa gidiyor mu? "Kesin": Search Console\'da aynı kelimede başka sayfamız da gösterim alıyor. "Risk": başka bir sayfanın başlığı aynı kelimeyi hedefliyor (yalnız yayındaki sayfalar; taslaklar yayına girene kadar sayılmaz).', 'gbc-core' ) . '</p>';
	if ( ! empty( $kn['taslak'] ) ) {
		echo '<div style="max-width:1000px;background:#F6F7F7;border-left:3px solid #9AA0A6;padding:8px 12px;margin:6px 0 10px;font-size:13px"><strong>Yayına girince kontrol edilecek:</strong> ';
		$tl = array();
		foreach ( $kn['taslak'] as $t ) { $tl[] = esc_html( $t['sayfa']['baslik'] ) . ' (' . esc_html( $t['sayfa']['durum'] ) . ') → ' . esc_html( implode( ', ', array_slice( array_unique( $t['kelime'] ), 0, 4 ) ) ); }
		echo implode( ' · ', $tl ) . '. Şu an puanı ve iş listesini etkilemiyor.</div>';
	}

	if ( $kn['komsu'] ) {
		echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:10px 16px;max-width:1000px;margin-bottom:10px">'
			. '<div style="font-size:12.5px;font-weight:600;color:#5C6470;margin-bottom:4px">' . esc_html( sprintf( __( 'Başlığında "%s" geçen diğer sayfalar', 'gbc-core' ), $kw['tohum'] ) ) . '</div>';
		foreach ( $kn['komsu'] as $p ) {
			echo '<div style="font-size:13.5px;line-height:1.8">· <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $p['id'] ) ) . '">' . esc_html( $p['baslik'] ) . '</a>'
				. ( 'publish' !== $p['durum'] ? ' <span style="color:#8A6100;font-size:12px">(' . esc_html( $p['durum'] ) . ')</span>' : '' )
				. ' <span style="color:#5C6470;font-size:12px">· ' . esc_html( gbc_kw_niyet_ad( gbc_kn_baslik_niyet( $p['baslik'] ) ) ) . '</span></div>';
		}
		echo '</div>';
	}

	if ( ! $kn['satir'] ) {
		echo '<p style="color:#1A7F37;font-weight:600">' . esc_html__( '✓ Kanibalizasyon yok. Bu sayfanın kelimelerine sitede başka sayfa gitmiyor.', 'gbc-core' ) . '</p>';
		return;
	}
	$hacim = array();
	foreach ( $kw['satir'] as $s ) { $hacim[ gbc_kw_normal( $s['k'] ) ] = $s['hh']; }
	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th>' . esc_html__( 'Kelime', 'gbc-core' ) . '</th><th style="width:80px">' . esc_html__( 'Aylık', 'gbc-core' ) . '</th>'
		. '<th style="width:90px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th><th>' . esc_html__( 'Çatışan sayfa', 'gbc-core' ) . '</th><th>' . esc_html__( 'Öneri', 'gbc-core' ) . '</th></tr></thead><tbody>';
	foreach ( $kn['satir'] as $k => $b ) {
		$cat = array();
		foreach ( $b['baska_gsc'] as $r ) {
			$cat[] = esc_html( wp_parse_url( $r['sayfa'], PHP_URL_PATH ) ) . ' <span style="color:#5C6470">(Google: ' . esc_html( number_format_i18n( $r['sira'], 1 ) ) . '. sıra, ' . (int) $r['gos'] . ' gösterim)</span>';
		}
		foreach ( $b['baska_baslik'] as $p ) {
			$cat[] = '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $p['id'] ) ) . '">' . esc_html( $p['baslik'] ) . '</a> <span style="color:#5C6470">(başlıkta hedefliyor' . ( 'publish' !== $p['durum'] ? ', ' . esc_html( $p['durum'] ) : '' ) . ')</span>';
		}
		$rz = 'kesin' === $b['durum'] ? gbc_kw_rozet( '✗ Kesin', '#B32D2E', '#FBE7E7' ) : gbc_kw_rozet( '⚠ Risk', '#8A6100', '#FCF6E8' );
		$h  = isset( $hacim[ $k ] ) && null !== $hacim[ $k ] ? number_format_i18n( $hacim[ $k ] ) : '—';
		echo '<tr><td><strong>' . esc_html( $k ) . '</strong>' . ( $b['bu_gsc'] ? '<div style="color:#5C6470;font-size:11px">bu sayfa: ' . esc_html( number_format_i18n( $b['bu_gsc']['sira'], 1 ) ) . '. sıra</div>' : '' ) . '</td>'
			. '<td>' . esc_html( $h ) . '</td><td>' . $rz . '</td><td>' . implode( '<br>', $cat ) . '</td><td style="font-size:12.5px">' . esc_html( $b['oneri'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}
