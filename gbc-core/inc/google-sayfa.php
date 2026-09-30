<?php
/**
 * GBC Core · Sayfa Denetimi — Google bölümü (v1.38.0, 30 Eylül 2026)
 * ------------------------------------------------------------
 * Tek sayfa için:
 *   1) Google dizininde mi? (URL Inspection API) — ✓ / ✗, son tarama tarihi
 *   2) Search Console: tıklama, gösterim, CTR, ortalama sıra, GÜNCEL sıra,
 *      hangi kelimelerden geldiği (her kelimenin güncel sırasıyla)
 *   3) Analytics (GA4): oturum, kullanıcı, görüntülenme, etkileşim,
 *      hangi kanaldan ve kaynaktan geldiği
 * Tarih: 7 / 30 / 90 gün ya da özel aralık. Varsayılan son 30 gün.
 *
 * GÜNCEL SIRA NEDİR: 30 günün ortalama sırası, 10 gün önceki 6. sırayı da
 * içine katar. Güncel sıra yalnız SON 7 GÜNÜN verisidir (Search Console'un
 * en taze, henüz kesinleşmemiş verisi dahil — dataState=all). Ayrıca
 * verisi olan en son günün sırası ayrı yazılır.
 *
 * KOTA: Search Console sorguları ve dizin sorgusu 6/12 saat saklanır.
 * GA4 isteği modules/90-komuta.php'deki günlük sayaçtan düşer (tavan 200).
 * Ziyaretçi isteğinde bu dosya yüklenmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GBC_GS_ONBELLEK', 6 * HOUR_IN_SECONDS );

/** Seçili tarih aralığı. */
function gbc_gs_aralik() {
	$bugun = current_time( 'timestamp' );
	$bas = isset( $_GET['gs_bas'] ) ? sanitize_text_field( wp_unslash( $_GET['gs_bas'] ) ) : '';
	$bit = isset( $_GET['gs_bit'] ) ? sanitize_text_field( wp_unslash( $_GET['gs_bit'] ) ) : '';
	$ok  = static function ( $t ) { return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $t ) && strtotime( $t ); };
	if ( $ok( $bas ) && $ok( $bit ) && strtotime( $bas ) <= strtotime( $bit ) ) {
		if ( strtotime( $bit ) > $bugun ) { $bit = gmdate( 'Y-m-d', $bugun ); }
		return array( 'bas' => $bas, 'bit' => $bit, 'gun' => 0, 'etiket' => $bas . ' – ' . $bit );
	}
	$gun = isset( $_GET['gs_gun'] ) ? (int) $_GET['gs_gun'] : 30;
	if ( ! in_array( $gun, array( 7, 30, 90, 180 ), true ) ) { $gun = 30; }
	return array(
		'bas'    => gmdate( 'Y-m-d', $bugun - ( $gun - 1 ) * DAY_IN_SECONDS ),
		'bit'    => gmdate( 'Y-m-d', $bugun ),
		'gun'    => $gun,
		'etiket' => sprintf( __( 'Son %d gün', 'gbc-core' ), $gun ),
	);
}

/** Search Console sorgusu — yalnız bu sayfa. */
function gbc_gs_gsc( $url, $bas, $bit, $boyut = array(), $satir = 50 ) {
	if ( ! function_exists( 'gbc_km_api' ) || ! function_exists( 'gbc_km_ayar' ) ) {
		return new WP_Error( 'yok', 'Search Console bağlantı modülü yüklü değil.' );
	}
	$site = gbc_km_ayar( 'gsc_site', '' );
	if ( '' === $site ) { return new WP_Error( 'yok', 'Search Console site adresi girilmemiş (API Merkezi).' ); }

	$ob = 'gbc_gs_gsc_' . md5( $url . '|' . $bas . '|' . $bit . '|' . implode( ',', $boyut ) . '|' . $satir );
	$c  = get_transient( $ob );
	if ( is_array( $c ) ) { return $c; }

	$govde = array(
		'startDate'             => $bas,
		'endDate'               => $bit,
		'dataState'             => 'all',
		'rowLimit'              => (int) $satir,
		'dimensionFilterGroups' => array( array( 'filters' => array( array(
			'dimension' => 'page', 'operator' => 'equals', 'expression' => $url,
		) ) ) ),
	);
	if ( $boyut ) { $govde['dimensions'] = $boyut; }
	$g = gbc_km_api( 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', $govde );
	if ( is_wp_error( $g ) ) { return $g; }
	$satirlar = isset( $g['rows'] ) && is_array( $g['rows'] ) ? $g['rows'] : array();
	set_transient( $ob, $satirlar, GBC_GS_ONBELLEK );
	return $satirlar;
}

/** GA4 raporu — yalnız bu sayfanın yolu. Günlük sayaca uyar. */
function gbc_gs_ga4( $yol, $bas, $bit, $boyut, $metrik, $satir = 10 ) {
	if ( ! function_exists( 'gbc_km_ga4' ) ) { return new WP_Error( 'yok', 'Analytics bağlantı modülü yüklü değil.' ); }
	$ob = 'gbc_gs_ga4_' . md5( $yol . '|' . $bas . '|' . $bit . '|' . implode( ',', $boyut ) . '|' . implode( ',', $metrik ) );
	$c  = get_transient( $ob );
	if ( is_array( $c ) ) { return $c; }

	if ( function_exists( 'gbc_km_ga4_sayac' ) && function_exists( 'gbc_km_ga4_tavan' ) ) {
		if ( gbc_km_ga4_sayac( false ) >= gbc_km_ga4_tavan() ) {
			return new WP_Error( 'kota', 'Bugünkü Analytics istek sınırı doldu, yarın yeniden denenecek.' );
		}
		gbc_km_ga4_sayac( true );
	}
	$govde = array(
		'dateRanges'      => array( array( 'startDate' => $bas, 'endDate' => $bit ) ),
		'metrics'         => array_map( static function ( $m ) { return array( 'name' => $m ); }, $metrik ),
		'dimensionFilter' => array( 'filter' => array( 'fieldName' => 'pagePath',
			'stringFilter' => array( 'matchType' => 'EXACT', 'value' => $yol ) ) ),
		'limit'           => (int) $satir,
	);
	if ( $boyut ) {
		$govde['dimensions'] = array_map( static function ( $b ) { return array( 'name' => $b ); }, $boyut );
		$govde['orderBys']   = array( array( 'metric' => array( 'metricName' => $metrik[0] ), 'desc' => true ) );
	}
	$g = gbc_km_ga4( $govde );
	if ( is_wp_error( $g ) ) { return $g; }
	$out = array();
	foreach ( ( isset( $g['rows'] ) ? (array) $g['rows'] : array() ) as $r ) {
		$sat = array();
		foreach ( $boyut as $i => $b ) { $sat[ $b ] = isset( $r['dimensionValues'][ $i ]['value'] ) ? $r['dimensionValues'][ $i ]['value'] : ''; }
		foreach ( $metrik as $i => $m ) { $sat[ $m ] = isset( $r['metricValues'][ $i ]['value'] ) ? (float) $r['metricValues'][ $i ]['value'] : 0; }
		$out[] = $sat;
	}
	set_transient( $ob, $out, GBC_GS_ONBELLEK );
	return $out;
}

/** Dizin durumu. $taze ise saklanan sonuç silinip yeniden sorulur. */
function gbc_gs_index( $url, $taze = false ) {
	if ( ! function_exists( 'gbc_km_index' ) ) { return new WP_Error( 'yok', 'Dizin sorgu modülü yüklü değil.' ); }
	if ( $taze ) { delete_transient( 'gbc_km_ix_' . md5( (string) $url ) ); }
	return gbc_km_index( $url );
}

/** Dizin sonucunu sadeleştirir. */
function gbc_gs_index_ozet( $ix ) {
	if ( is_wp_error( $ix ) ) {
		return array( 'durum' => 'bilinmiyor', 'yazi' => $ix->get_error_message(), 'tarama' => '', 'kapsam' => '' );
	}
	$karar  = isset( $ix['verdict'] ) ? $ix['verdict'] : '';
	$kapsam = isset( $ix['coverageState'] ) ? $ix['coverageState'] : '';
	$tarama = ! empty( $ix['lastCrawlTime'] ) ? date_i18n( 'j F Y', strtotime( $ix['lastCrawlTime'] ) ) : '';
	return array(
		'durum'  => ( 'PASS' === $karar ) ? 'var' : 'yok',
		'yazi'   => ( 'PASS' === $karar ) ? __( 'Google dizininde', 'gbc-core' ) : __( 'Google dizininde değil', 'gbc-core' ),
		'tarama' => $tarama,
		'kapsam' => $kapsam,
		'gcanon' => isset( $ix['googleCanonical'] ) ? $ix['googleCanonical'] : '',
	);
}

/** Üst kart: Google dizini. */
function gbc_gs_kart( $url ) {
	$o = gbc_gs_index_ozet( gbc_gs_index( $url ) );
	$deger = 'var' === $o['durum'] ? '✓' : ( 'yok' === $o['durum'] ? '✗' : '?' );
	$alt   = esc_html( $o['yazi'] ) . ( '' !== $o['tarama'] ? '<br>' . esc_html( sprintf( __( 'son tarama %s', 'gbc-core' ), $o['tarama'] ) ) : ( 'yok' === $o['durum'] ? '<br>' . esc_html__( 'Google henüz taramadı', 'gbc-core' ) : '' ) );
	$renk  = 'var' === $o['durum'] ? '#1A7F37' : ( 'yok' === $o['durum'] ? '#B32D2E' : '#8A6100' );
	return function_exists( 'gbc_br_kart' ) ? gbc_br_kart( __( 'Google dizini', 'gbc-core' ), $deger, $alt, $renk, '#gbc-google' ) : '';
}

/** Sayı biçimi. */
function gbc_gs_sayi( $n, $ondalik = 0 ) {
	return function_exists( 'number_format_i18n' ) ? number_format_i18n( (float) $n, $ondalik ) : number_format( (float) $n, $ondalik, ',', '.' );
}

/** Ağırlıklı ortalama sıra (gösterime göre). */
function gbc_gs_sira( $satirlar ) {
	$g = 0; $p = 0;
	foreach ( (array) $satirlar as $r ) { $g += (float) $r['impressions']; $p += (float) $r['position'] * (float) $r['impressions']; }
	return $g > 0 ? $p / $g : 0;
}

/** Google bölümü. */
function gbc_gs_ekran( $pid, $url ) {
	$pid  = (int) $pid;
	$ar   = gbc_gs_aralik();
	$taze = isset( $_GET['gs_ix'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gbc_gs_ix' );
	$ix   = gbc_gs_index_ozet( gbc_gs_index( $url, $taze ) );
	$ana  = admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid );

	echo '<h2 id="gbc-google" style="margin-top:26px">' . esc_html__( 'Google: dizin, arama ve trafik', 'gbc-core' ) . '</h2>';

	/* 1) Dizin */
	$renk = 'var' === $ix['durum'] ? array( '#1A7F37', '#E8F3EC' ) : ( 'yok' === $ix['durum'] ? array( '#B32D2E', '#FBE7E7' ) : array( '#8A6100', '#FCF6E8' ) );
	echo '<div style="background:' . esc_attr( $renk[1] ) . ';border-radius:12px;padding:12px 18px;max-width:1000px;margin:8px 0 14px">';
	echo '<strong style="color:' . esc_attr( $renk[0] ) . ';font-size:15px">' . ( 'var' === $ix['durum'] ? '✓ ' : ( 'yok' === $ix['durum'] ? '✗ ' : '? ' ) ) . esc_html( $ix['yazi'] ) . '</strong>';
	if ( '' !== $ix['kapsam'] ) { echo ' <span style="color:#3C4149">· ' . esc_html( $ix['kapsam'] ) . '</span>'; }
	echo '<div style="font-size:13px;color:#3C4149;margin-top:4px">'
		. esc_html( '' !== $ix['tarama'] ? sprintf( __( 'Google son tarama: %s', 'gbc-core' ), $ix['tarama'] ) : __( 'Google bu adresi henüz hiç taramamış.', 'gbc-core' ) );
	if ( ! empty( $ix['gcanon'] ) && rtrim( $ix['gcanon'], '/' ) !== rtrim( $url, '/' ) ) {
		echo '<br><span style="color:#B32D2E">' . esc_html( sprintf( __( 'Google bu sayfanın asıl adresi olarak başka bir adres seçmiş: %s', 'gbc-core' ), $ix['gcanon'] ) ) . '</span>';
	}
	if ( 'yok' === $ix['durum'] ) {
		echo '<br>' . esc_html__( 'Yeni sayfalarda normaldir. Search Console\'dan dizine ekleme istendiyse günlük kontrol, sayfa dizine girdiği gün bunu yakalar.', 'gbc-core' );
	}
	echo '</div><div style="margin-top:8px"><a class="button button-small" href="' . esc_url( wp_nonce_url( $ana . '&gs_ix=1', 'gbc_gs_ix' ) ) . '#gbc-google">'
		. esc_html__( 'Dizin durumunu şimdi yeniden sor', 'gbc-core' ) . '</a> '
		. '<a class="button button-small" target="_blank" rel="noopener" href="' . esc_url( 'https://search.google.com/search-console/inspect?resource_id=' . rawurlencode( function_exists( 'gbc_km_ayar' ) ? gbc_km_ayar( 'gsc_site', '' ) : '' ) . '&id=' . rawurlencode( $url ) ) . '">'
		. esc_html__( 'Search Console\'da aç ↗', 'gbc-core' ) . '</a></div>';
	echo '</div>';

	/* 2) Tarih seçimi */
	echo '<form method="get" style="margin:6px 0 12px"><input type="hidden" name="page" value="gbc-seo-sayfa"><input type="hidden" name="pid" value="' . (int) $pid . '">';
	foreach ( array( 7, 30, 90, 180 ) as $g ) {
		$aktif = ( $ar['gun'] === $g );
		echo '<a class="button' . ( $aktif ? ' button-primary' : '' ) . '" style="margin-right:6px" href="' . esc_url( $ana . '&gs_gun=' . $g ) . '#gbc-google">'
			. esc_html( sprintf( __( 'Son %d gün', 'gbc-core' ), $g ) ) . '</a>';
	}
	echo ' <span style="margin:0 6px;color:#5C6470">' . esc_html__( 'ya da', 'gbc-core' ) . '</span>'
		. '<input type="date" name="gs_bas" value="' . esc_attr( $ar['gun'] ? '' : $ar['bas'] ) . '"> – '
		. '<input type="date" name="gs_bit" value="' . esc_attr( $ar['gun'] ? '' : $ar['bit'] ) . '"> '
		. '<button class="button' . ( $ar['gun'] ? '' : ' button-primary' ) . '">' . esc_html__( 'Özel aralık', 'gbc-core' ) . '</button>';
	echo '<div style="color:#5C6470;font-size:12.5px;margin-top:6px">' . esc_html( sprintf( __( 'Gösterilen: %1$s (%2$s → %3$s). Search Console verisi 1–2 gün geriden gelir; en son günler kesinleşmemiş veridir.', 'gbc-core' ), $ar['etiket'], $ar['bas'], $ar['bit'] ) ) . '</div>';
	echo '</form>';

	/* 3) Search Console */
	echo '<h3 style="margin:14px 0 8px">' . esc_html__( 'Search Console — aramada görünme', 'gbc-core' ) . '</h3>';
	$top = gbc_gs_gsc( $url, $ar['bas'], $ar['bit'], array(), 1 );
	$gunluk = gbc_gs_gsc( $url, gmdate( 'Y-m-d', current_time( 'timestamp' ) - 13 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', current_time( 'timestamp' ) ), array( 'date' ), 20 );
	if ( is_wp_error( $top ) ) {
		echo '<p style="color:#B32D2E">' . esc_html( $top->get_error_message() ) . '</p>';
	} else {
		$t = $top ? $top[0] : array( 'clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0 );

		/* Güncel sıra: son 7 günün gösterime göre ağırlıklı sırası + verisi olan son gün. */
		$son7 = array(); $sonGun = null;
		if ( ! is_wp_error( $gunluk ) && $gunluk ) {
			/* v1.39.0: gösterimi olmayan gün sıra taşımaz ("0,0. sıra" hatası). */
			$gunluk = array_values( array_filter( $gunluk, static function ( $r ) { return (float) $r['impressions'] > 0; } ) );
		}
		if ( ! is_wp_error( $gunluk ) && $gunluk ) {
			usort( $gunluk, static function ( $a, $b ) { return strcmp( $b['keys'][0], $a['keys'][0] ); } );
			$sonGun = $gunluk[0];
			$sinir  = gmdate( 'Y-m-d', strtotime( $gunluk[0]['keys'][0] ) - 6 * DAY_IN_SECONDS );
			foreach ( $gunluk as $r ) { if ( $r['keys'][0] >= $sinir ) { $son7[] = $r; } }
		}
		$guncel = gbc_gs_sira( $son7 );

		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0">';
		echo gbc_br_kart( __( 'Tıklama', 'gbc-core' ), gbc_gs_sayi( $t['clicks'] ), esc_html( $ar['etiket'] ) );
		echo gbc_br_kart( __( 'Gösterim', 'gbc-core' ), gbc_gs_sayi( $t['impressions'] ), esc_html( $ar['etiket'] ) );
		echo gbc_br_kart( __( 'Tıklanma oranı', 'gbc-core' ), '%' . gbc_gs_sayi( (float) $t['ctr'] * 100, 1 ), esc_html( $ar['etiket'] ) );
		echo gbc_br_kart( __( 'Ortalama sıra', 'gbc-core' ), $t['impressions'] ? gbc_gs_sayi( $t['position'], 1 ) : '—',
			esc_html( sprintf( __( '%s ortalaması', 'gbc-core' ), $ar['etiket'] ) ) );
		echo gbc_br_kart( __( 'Güncel sıra', 'gbc-core' ), $guncel ? gbc_gs_sayi( $guncel, 1 ) : '—',
			esc_html( $sonGun
				? sprintf( __( 'son 7 gün · en son gün %1$s: %2$s. sıra', 'gbc-core' ), date_i18n( 'j M', strtotime( $sonGun['keys'][0] ) ), gbc_gs_sayi( $sonGun['position'], 1 ) )
				: __( 'son 7 günde gösterim yok', 'gbc-core' ) ),
			$guncel && $guncel <= 3 ? '#1A7F37' : ( $guncel && $guncel <= 10 ? '#8A6100' : '#14181F' ) );
		echo '</div>';

		/* Kelimeler: aralık + son 7 günün güncel sırası yan yana */
		$kel = gbc_gs_gsc( $url, $ar['bas'], $ar['bit'], array( 'query' ), 50 );
		$kel7 = $sonGun ? gbc_gs_gsc( $url, gmdate( 'Y-m-d', strtotime( $sonGun['keys'][0] ) - 6 * DAY_IN_SECONDS ), $sonGun['keys'][0], array( 'query' ), 100 ) : array();
		$g7 = array();
		if ( ! is_wp_error( $kel7 ) ) { foreach ( (array) $kel7 as $r ) { $g7[ $r['keys'][0] ] = (float) $r['position']; } }

		if ( is_wp_error( $kel ) ) {
			echo '<p style="color:#B32D2E">' . esc_html( $kel->get_error_message() ) . '</p>';
		} elseif ( ! $kel ) {
			echo '<p><em>' . esc_html__( 'Bu aralıkta bu sayfa hiçbir aramada görünmemiş.', 'gbc-core' ) . '</em></p>';
		} else {
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
				. '<th>' . esc_html__( 'Kelime', 'gbc-core' ) . '</th>'
				. '<th style="width:80px">' . esc_html__( 'Tıklama', 'gbc-core' ) . '</th>'
				. '<th style="width:90px">' . esc_html__( 'Gösterim', 'gbc-core' ) . '</th>'
				. '<th style="width:70px">' . esc_html__( 'CTR', 'gbc-core' ) . '</th>'
				. '<th style="width:110px">' . esc_html__( 'Ort. sıra', 'gbc-core' ) . '</th>'
				. '<th style="width:130px">' . esc_html__( 'Güncel sıra (7 gün)', 'gbc-core' ) . '</th>'
				. '</tr></thead><tbody>';
			foreach ( $kel as $r ) {
				$q = $r['keys'][0];
				$gs = isset( $g7[ $q ] ) ? $g7[ $q ] : null;
				$ok = '';
				if ( null !== $gs ) {
					$fark = (float) $r['position'] - $gs;
					if ( $fark >= 0.5 ) { $ok = ' <span style="color:#1A7F37">▲ ' . gbc_gs_sayi( $fark, 1 ) . '</span>'; }
					elseif ( $fark <= -0.5 ) { $ok = ' <span style="color:#B32D2E">▼ ' . gbc_gs_sayi( -$fark, 1 ) . '</span>'; }
				}
				$rk = null !== $gs ? ( $gs <= 3 ? '#1A7F37' : ( $gs <= 10 ? '#8A6100' : '#1B4E9B' ) ) : '#5C6470';
				echo '<tr><td><strong>' . esc_html( $q ) . '</strong></td>'
					. '<td>' . gbc_gs_sayi( $r['clicks'] ) . '</td>'
					. '<td>' . gbc_gs_sayi( $r['impressions'] ) . '</td>'
					. '<td>%' . gbc_gs_sayi( (float) $r['ctr'] * 100, 1 ) . '</td>'
					. '<td>' . gbc_gs_sayi( $r['position'], 1 ) . '</td>'
					. '<td><span style="font-weight:700;color:' . $rk . '">' . ( null !== $gs ? gbc_gs_sayi( $gs, 1 ) : '—' ) . '</span>' . $ok . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p style="color:#5C6470;font-size:12.5px;margin:6px 0 0">' . esc_html__( '▲ yükseldi / ▼ düştü: aralık ortalamasına göre son 7 günün farkı. "—" o kelimede son 7 günde gösterim yok demek.', 'gbc-core' ) . '</p>';
		}
	}

	/* 4) Analytics */
	echo '<h3 style="margin:20px 0 8px">' . esc_html__( 'Analytics — sayfaya gelen ziyaretçi', 'gbc-core' ) . '</h3>';
	$yol = (string) wp_parse_url( $url, PHP_URL_PATH );
	$ga  = gbc_gs_ga4( $yol, $ar['bas'], $ar['bit'], array(), array( 'sessions', 'totalUsers', 'screenPageViews', 'engagementRate' ), 1 );
	if ( is_wp_error( $ga ) ) {
		echo '<p style="color:#B32D2E">' . esc_html( $ga->get_error_message() ) . '</p>';
		return;
	}
	$a = $ga ? $ga[0] : array( 'sessions' => 0, 'totalUsers' => 0, 'screenPageViews' => 0, 'engagementRate' => 0 );
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0">';
	echo gbc_br_kart( __( 'Görüntülenme', 'gbc-core' ), gbc_gs_sayi( $a['screenPageViews'] ), esc_html( $ar['etiket'] ) );
	echo gbc_br_kart( __( 'Oturum', 'gbc-core' ), gbc_gs_sayi( $a['sessions'] ), esc_html( $ar['etiket'] ) );
	echo gbc_br_kart( __( 'Kullanıcı', 'gbc-core' ), gbc_gs_sayi( $a['totalUsers'] ), esc_html( $ar['etiket'] ) );
	echo gbc_br_kart( __( 'Etkileşim oranı', 'gbc-core' ), '%' . gbc_gs_sayi( (float) $a['engagementRate'] * 100, 0 ), esc_html__( 'sayfada kalıp gezenler', 'gbc-core' ) );
	echo '</div>';

	if ( ! (float) $a['sessions'] ) {
		echo '<p><em>' . esc_html__( 'Bu aralıkta bu sayfaya Analytics\'e düşen ziyaret yok.', 'gbc-core' ) . '</em></p>';
		return;
	}
	$kanal = gbc_gs_ga4( $yol, $ar['bas'], $ar['bit'], array( 'sessionDefaultChannelGroup' ), array( 'sessions', 'totalUsers' ), 10 );
	$kay   = gbc_gs_ga4( $yol, $ar['bas'], $ar['bit'], array( 'sessionSource', 'sessionMedium' ), array( 'sessions', 'totalUsers' ), 10 );
	$tr    = array( 'Organic Search' => 'Google / arama', 'Direct' => 'Doğrudan', 'Organic Social' => 'Sosyal medya', 'Referral' => 'Başka siteden',
		'Paid Search' => 'Ücretli arama', 'Email' => 'E-posta', 'Unassigned' => 'Belirsiz', 'Organic Video' => 'Video (YouTube)' );
	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;max-width:1000px">';
	foreach ( array( array( __( 'Hangi kanaldan geldi', 'gbc-core' ), $kanal, 'kanal' ), array( __( 'Hangi kaynaktan geldi', 'gbc-core' ), $kay, 'kaynak' ) ) as $b ) {
		echo '<div style="flex:1;min-width:300px"><table class="widefat striped"><thead><tr><th>' . esc_html( $b[0] ) . '</th><th style="width:80px">' . esc_html__( 'Oturum', 'gbc-core' ) . '</th><th style="width:80px">' . esc_html__( 'Kullanıcı', 'gbc-core' ) . '</th></tr></thead><tbody>';
		if ( is_wp_error( $b[1] ) ) {
			echo '<tr><td colspan="3" style="color:#B32D2E">' . esc_html( $b[1]->get_error_message() ) . '</td></tr>';
		} else {
			foreach ( (array) $b[1] as $r ) {
				$ad = 'kanal' === $b[2] ? ( isset( $tr[ $r['sessionDefaultChannelGroup'] ] ) ? $tr[ $r['sessionDefaultChannelGroup'] ] : $r['sessionDefaultChannelGroup'] )
					: $r['sessionSource'] . ' / ' . $r['sessionMedium'];
				echo '<tr><td>' . esc_html( $ad ) . '</td><td>' . gbc_gs_sayi( $r['sessions'] ) . '</td><td>' . gbc_gs_sayi( $r['totalUsers'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
	}
	echo '</div>';
}
