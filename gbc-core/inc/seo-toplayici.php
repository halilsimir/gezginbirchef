<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — SEO Toplayıcı.
 *
 * NE YAPAR: 1.040 sayfanın denetimini bir gecede değil, saat başı birkaç
 * sayfa olarak yapar; sonucu sayfanın kendi metasına yazar. Böylece
 * ekranlar "o an ölç" yerine "kayıtlı değeri oku" ile açılır ve envanter
 * tablosu sıralanabilir hale gelir.
 *
 * SIRA: önce Search Console'da gösterimi olan sayfalar (işine yarayan veri
 * ilk günden birikir), sonra hiç denetlenmemişler, en son denetimi en eski
 * olanlar. Böylece sistem kendini sürekli tazeler.
 *
 * KAYIT: her sayfanın _gbc_seo metasında son denetim + son 5 denetimin
 * özeti durur. Eski kayıt SİLİNMEZ — "dün ne durumdaydı" sorusu sonradan
 * da yanıtlanabilsin diye.
 *
 * YÜK: her turda en fazla GBC_SEO_TUR_ADET sayfa indirilir. Dış bağlantı
 * kontrolü bu turda YAPILMAZ (sayfa başına 15-20 saniye sürer); o iş
 * haftalık ayrı turda ve daha az sayfayla yapılır.
 */

define( 'GBC_SEO_META',      '_gbc_seo' );
define( 'GBC_SEO_ILERLEME',  'gbc_seo_ilerleme' );
define( 'GBC_SEO_TUR_ADET',  6 );

/* ============================================================
   SIRA — hangi sayfa önce denetlenecek
   ============================================================ */

/** Search Console'da gösterimi olan sayfaların adresleri (çok gösterim önce). */
function gbc_seo_gosterimli_adresler( $limit = 400 ) {
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return array(); }
	global $wpdb;

	$satirlar = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT page, SUM(impressions) g
		   FROM `{$k['tablo']}`
		  WHERE created >= %s
		  GROUP BY page
		  ORDER BY g DESC
		  LIMIT %d",
		gbc_seo_sinir( 90 ), (int) $limit
	), ARRAY_A );

	$liste = array();
	foreach ( $satirlar as $s ) { $liste[] = (string) $s['page']; }
	return $liste;
}

/** Sıradaki sayfa numaralarını verir. */
function gbc_seo_sira_al( $adet ) {
	$secilen = array();

	/* 1) Gosterimi olan ama hic denetlenmemis ya da eskimis sayfalar. */
	foreach ( gbc_seo_gosterimli_adresler( 400 ) as $adres ) {
		if ( count( $secilen ) >= $adet ) { break; }
		$pid = url_to_postid( $adres );
		if ( ! $pid ) {
			/* Rank Math yalniz yolu yazmis olabilir. */
			$pid = url_to_postid( home_url( $adres ) );
		}
		if ( ! $pid || isset( $secilen[ $pid ] ) ) { continue; }
		$kayit = get_post_meta( $pid, GBC_SEO_META, true );
		if ( is_array( $kayit ) && ! empty( $kayit['zaman'] ) && ! empty( $kayit['skor_surum'] ) && ( time() - (int) $kayit['zaman'] ) < 14 * DAY_IN_SECONDS ) {
			continue;
		}
		$secilen[ $pid ] = true;
	}

	/* 1b) v1.43.0: plandaki sayfalar ve yeni skorla hiç ölçülmemiş olanlar. */
	if ( count( $secilen ) < $adet && function_exists( 'gbc_plan_oku' ) ) {
		foreach ( gbc_plan_oku()['satir'] as $ps ) {
			if ( count( $secilen ) >= $adet ) { break; }
			$pp = $ps['pid'] && get_post_status( $ps['pid'] ) ? (int) $ps['pid'] : 0;
			if ( ! $pp && '' !== $ps['mevcut'] ) { $pp = (int) url_to_postid( $ps['mevcut'] ); }
			if ( ! $pp || isset( $secilen[ $pp ] ) || 'publish' !== get_post_status( $pp ) ) { continue; }
			$kayit = get_post_meta( $pp, GBC_SEO_META, true );
			if ( is_array( $kayit ) && ! empty( $kayit['skor_surum'] ) && ( time() - (int) $kayit['zaman'] ) < 7 * DAY_IN_SECONDS ) { continue; }
			$secilen[ $pp ] = true;
		}
	}

	/* 2) Hic denetlenmemis icerikler. */
	if ( count( $secilen ) < $adet ) {
		$hic = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $adet - count( $secilen ),
			'fields'         => 'ids',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => GBC_SEO_META, 'compare' => 'NOT EXISTS' ) ),
		) );
		foreach ( (array) $hic as $pid ) { $secilen[ (int) $pid ] = true; }
	}

	/* 3) Denetimi en eski olanlar. */
	if ( count( $secilen ) < $adet ) {
		$eski = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $adet - count( $secilen ),
			'fields'         => 'ids',
			'meta_key'       => GBC_SEO_META,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		) );
		foreach ( (array) $eski as $pid ) { $secilen[ (int) $pid ] = true; }
	}

	return array_slice( array_keys( $secilen ), 0, $adet );
}

/* ============================================================
   DENETİM VE KAYIT
   ============================================================ */

/** Tek sayfayı denetler ve sonucu metaya yazar. Geçmiş silinmez.
 *  v1.43.0: 7 bölümlü GBC skoru (Sayfa Denetimi ile aynı hesap), yapılacak sayısı, dizin durumu, kelime fırsatları. */
function gbc_seo_kaydet( $pid ) {
	$pid = (int) $pid;
	if ( function_exists( 'gbc_sd_tam_denetim' ) ) {
		$r = gbc_sd_tam_denetim( $pid );
		return ! empty( $r['hata'] ) ? array( 'pid' => $pid, 'hata' => $r['hata'] ) : array( 'pid' => $pid, 'skor' => (int) $r['skor'] );
	}
	return array( 'pid' => $pid, 'hata' => 'Sayfa denetimi modülü yüklü değil.' );
}

/** Bir tur: birkaç sayfa denetle. Cron ve elle çalıştırma aynı yolu kullanır. */
function gbc_seo_tur( $adet = null ) {
	$adet = $adet ? (int) $adet : GBC_SEO_TUR_ADET;
	/* v1.43.1: başka ağır GBC işi sürüyorsa bu tur atlanır (bir saat sonra yeniden). */
	if ( function_exists( 'gbc_kilit_al' ) && ! gbc_kilit_al( 'toplama' ) ) {
		$k = gbc_kilit_kimde();
		return array( 'zaman' => time(), 'sayfa' => array(), 'hata' => array(), 'ms' => 0, 'atlandi' => $k ? $k['is'] : 'başka iş' );
	}
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
	ignore_user_abort( true );

	$bas = microtime( true );
	$sonuc = array( 'zaman' => time(), 'sayfa' => array(), 'hata' => array() );

	foreach ( gbc_seo_sira_al( $adet ) as $pid ) {
		$r = gbc_seo_kaydet( $pid );
		if ( ! empty( $r['hata'] ) ) { $sonuc['hata'][] = $r; }
		else { $sonuc['sayfa'][] = $r; }
	}

	/* v1.43.1: Envanter verisi burada, arka planda hazırlanır; ekran yalnız okur. */
	if ( function_exists( 'gbc_plan_analiz' ) ) { gbc_plan_analiz( true ); }
	if ( function_exists( 'gbc_eb_indeks' ) ) { delete_transient( 'gbc_env_indeks' ); gbc_eb_indeks(); }

	$sonuc['ms'] = (int) round( ( microtime( true ) - $bas ) * 1000 );

	$il = get_option( GBC_SEO_ILERLEME, array() );
	if ( ! is_array( $il ) ) { $il = array(); }
	$il['son_tur']   = $sonuc;
	$il['toplam_tur'] = isset( $il['toplam_tur'] ) ? (int) $il['toplam_tur'] + 1 : 1;
	update_option( GBC_SEO_ILERLEME, $il, false );

	return $sonuc;
}

/** Kaç sayfa denetlendi, kaçı kaldı. */
function gbc_seo_ilerleme() {
	global $wpdb;
	$denetlenen = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s", GBC_SEO_META
	) );
	$say = wp_count_posts( 'post' );
	$sayfa = wp_count_posts( 'page' );
	$toplam = ( isset( $say->publish ) ? (int) $say->publish : 0 ) + ( isset( $sayfa->publish ) ? (int) $sayfa->publish : 0 );

	$il = get_option( GBC_SEO_ILERLEME, array() );
	return array(
		'denetlenen' => $denetlenen,
		'toplam'     => $toplam,
		'yuzde'      => $toplam ? (int) round( $denetlenen / $toplam * 100 ) : 0,
		'son_tur'    => isset( $il['son_tur'] ) ? $il['son_tur'] : null,
		'tur_sayisi' => isset( $il['toplam_tur'] ) ? (int) $il['toplam_tur'] : 0,
	);
}

/** En düşük skorlu sayfalar — envanterin işe yarayan hali. */
function gbc_seo_kayitli_liste( $limit = 50, $sirala = 'skor' ) {
	global $wpdb;
	$satirlar = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 2000", GBC_SEO_META
	), ARRAY_A );

	$liste = array();
	foreach ( $satirlar as $s ) {
		$v = maybe_unserialize( $s['meta_value'] );
		if ( ! is_array( $v ) ) { continue; }
		$v['pid'] = (int) $s['post_id'];
		$liste[] = $v;
	}

	usort( $liste, static function ( $a, $b ) use ( $sirala ) {
		if ( 'zaman' === $sirala ) { return (int) $b['zaman'] - (int) $a['zaman']; }
		return (int) $a['skor'] - (int) $b['skor'];
	} );

	return array_slice( $liste, 0, $limit );
}

/* ============================================================
   CRON — saat başı bir tur
   ============================================================ */
add_action( 'gbc_seo_toplama', 'gbc_seo_tur' );

add_action( 'init', 'gbc_seo_cron_kur' );
function gbc_seo_cron_kur() {
	if ( ! wp_next_scheduled( 'gbc_seo_toplama' ) ) {
		wp_schedule_event( time() + 600, 'hourly', 'gbc_seo_toplama' );
	}
}

register_deactivation_hook( GBC_CORE_DIR . 'gbc-core.php', 'gbc_seo_cron_kaldir' );
function gbc_seo_cron_kaldir() {
	$z = wp_next_scheduled( 'gbc_seo_toplama' );
	if ( $z ) { wp_unschedule_event( $z, 'gbc_seo_toplama' ); }
}

/* ============================================================
   EKRAN — Toplama
   ============================================================ */
function gbc_seo_toplama_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	/* v1.43.0: Toplama Envanter'in Özet sekmesine taşındı; eski adres de orayı gösterir. */
	if ( function_exists( 'gbc_eb_ekran' ) ) { $_GET['sekme'] = 'ozet'; gbc_eb_ekran(); return; }

	if ( isset( $_GET['gbc_seo_tur'] ) && check_admin_referer( 'gbc_seo_tur' ) ) {
		$r = gbc_seo_tur();
		echo '<div class="notice notice-success"><p>'
			. esc_html( sprintf( __( '%1$d sayfa denetlendi, %2$d saniye sürdü.', 'gbc-core' ), count( $r['sayfa'] ), (int) round( $r['ms'] / 1000 ) ) )
			. ( $r['hata'] ? ' ' . esc_html( sprintf( __( '%d sayfada hata.', 'gbc-core' ), count( $r['hata'] ) ) ) : '' )
			. '</p></div>';
	}

	$i = gbc_seo_ilerleme();
	$sonraki = wp_next_scheduled( 'gbc_seo_toplama' );

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Toplama', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo-toplama' ); }
	echo '<p style="max-width:960px;color:#444">'
		. esc_html__( 'Sistem bütün sayfaları bir gecede değil, saat başı birkaç sayfa denetleyerek doldurur. Sıra: önce Search Console\'da gösterimi olanlar, sonra hiç denetlenmemişler, en son denetimi eskiyenler. Ölçülen hiçbir değer silinmez; her sayfanın son beş denetimi saklanır.', 'gbc-core' )
		. '</p>';

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_seo_kart( __( 'Denetlenen', 'gbc-core' ), number_format_i18n( $i['denetlenen'] ) . ' / ' . number_format_i18n( $i['toplam'] ),
		'%' . $i['yuzde'], $i['yuzde'] >= 90 ? '#1A7F37' : '#8A6100' );
	echo gbc_seo_kart( __( 'Tamamlanan tur', 'gbc-core' ), number_format_i18n( $i['tur_sayisi'] ),
		sprintf( __( 'turda %d sayfa', 'gbc-core' ), GBC_SEO_TUR_ADET ) );
	echo gbc_seo_kart( __( 'Sıradaki tur', 'gbc-core' ),
		$sonraki ? human_time_diff( time(), $sonraki ) : __( 'kayıtlı değil', 'gbc-core' ),
		$sonraki ? __( 'sonra', 'gbc-core' ) : __( 'cron kurulmamış', 'gbc-core' ),
		$sonraki ? '#14181F' : '#B32D2E' );
	$kalan = max( 0, $i['toplam'] - $i['denetlenen'] );
	echo gbc_seo_kart( __( 'Bitiş tahmini', 'gbc-core' ),
		$kalan ? sprintf( __( '%d saat', 'gbc-core' ), (int) ceil( $kalan / GBC_SEO_TUR_ADET ) ) : __( 'bitti', 'gbc-core' ),
		sprintf( __( '%s sayfa kaldı', 'gbc-core' ), number_format_i18n( $kalan ) ) );
	echo '</div>';

	echo '<p><a class="button button-primary" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-seo-toplama&gbc_seo_tur=1' ), 'gbc_seo_tur' ) ) . '">'
		. esc_html( sprintf( __( 'Şimdi bir tur çalıştır (%d sayfa)', 'gbc-core' ), GBC_SEO_TUR_ADET ) ) . '</a></p>';

	if ( ! empty( $i['son_tur'] ) ) {
		$st = $i['son_tur'];
		echo '<h2 style="margin-top:24px">' . esc_html__( 'Son tur', 'gbc-core' ) . '</h2>';
		echo '<p style="color:#5C6470">' . esc_html( wp_date( 'j F Y H:i', (int) $st['zaman'] ) ) . ' · '
			. esc_html( sprintf( __( '%d sn', 'gbc-core' ), (int) round( $st['ms'] / 1000 ) ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:800px"><tbody>';
		foreach ( (array) $st['sayfa'] as $r ) {
			echo '<tr><td>' . esc_html( get_the_title( $r['pid'] ) ) . '</td>'
				. '<td style="width:90px"><strong>%' . (int) $r['skor'] . '</strong></td>'
				. '<td style="width:100px"><a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $r['pid'] ) ) . '">'
				. esc_html__( 'aç', 'gbc-core' ) . '</a></td></tr>';
		}
		foreach ( (array) $st['hata'] as $r ) {
			echo '<tr><td colspan="3" style="color:#B32D2E">' . esc_html( get_the_title( $r['pid'] ) . ' — ' . $r['hata'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* En dusuk skorlular */
	$liste = gbc_seo_kayitli_liste( 40, 'skor' );
	echo '<h2 style="margin-top:26px">' . esc_html__( 'En düşük skorlu sayfalar', 'gbc-core' ) . '</h2>';
	if ( ! $liste ) {
		echo '<p><em>' . esc_html__( 'Henüz kayıt yok. Bir tur çalıştır.', 'gbc-core' ) . '</em></p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
			. '<th>' . esc_html__( 'Sayfa', 'gbc-core' ) . '</th>'
			. '<th style="width:80px">' . esc_html__( 'Skor', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Şema', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Alt eksik', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Meta', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Ortaklık', 'gbc-core' ) . '</th>'
			. '<th style="width:120px">' . esc_html__( 'Denetim', 'gbc-core' ) . '</th>'
			. '<th style="width:70px"></th>'
			. '</tr></thead><tbody>';
		foreach ( $liste as $v ) {
			$renk = $v['skor'] >= 80 ? '#1A7F37' : ( $v['skor'] >= 60 ? '#8A6100' : '#B32D2E' );
			echo '<tr>';
			echo '<td>' . esc_html( get_the_title( $v['pid'] ) ) . '<div style="color:#5C6470;font-size:12px">'
				. esc_html( wp_parse_url( get_permalink( $v['pid'] ), PHP_URL_PATH ) ) . '</div></td>';
			echo '<td><strong style="color:' . esc_attr( $renk ) . '">%' . (int) $v['skor'] . '</strong></td>';
			echo '<td>' . ( ! empty( $v['sema'] ) ? esc_html( count( (array) $v['sema'] ) )
				: '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'YOK', 'gbc-core' ) . '</span>' ) . '</td>';
			echo '<td>' . ( (int) $v['alt_yok'] ? '<span style="color:#B32D2E;font-weight:600">' . (int) $v['alt_yok'] . '</span>' : '0' ) . '</td>';
			echo '<td>' . ( ! empty( $v['aciklama'] ) ? esc_html__( 'var', 'gbc-core' )
				: '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'yok', 'gbc-core' ) . '</span>' ) . '</td>';
			$ot = array_sum( (array) $v['ortaklik'] );
			echo '<td>' . ( $ot ? esc_html( $ot . ' bağ' ) : '<span style="color:#8A6100">' . esc_html__( 'yok', 'gbc-core' ) . '</span>' ) . '</td>';
			echo '<td style="color:#5C6470">' . esc_html( human_time_diff( (int) $v['zaman'] ) ) . esc_html__( ' önce', 'gbc-core' ) . '</td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $v['pid'] ) ) . '">'
				. esc_html__( 'aç', 'gbc-core' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	echo '</div>';
}
