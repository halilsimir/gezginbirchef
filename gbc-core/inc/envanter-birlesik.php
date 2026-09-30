<?php
/**
 * GBC Core · Birleşik Envanter — v1.43.0, 30 Eylül 2026
 * ------------------------------------------------------------
 * Envanter ve Toplama tek ekran. Sekmeler:
 *   Özet      — kartlar, alarmlar, sıradaki işler, toplama ilerlemesi, tablo kaynağı
 *   Öncelik   — plan satırları öncelik puanına göre + mevsim takvimi (hangi ay ne hazır olmalı)
 *   Plan      — Google tablosu satır satır, sitedeki gerçek durumla karşılaştırmalı; planda olmayan sayfalar
 *   Bütün içerik — sitenin bütün yazı/sayfaları, skora/trafiğe/tarihe göre sıralanır, süzülür
 *   Silo · Yetim — eski ekranlar
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
   İNDEKS — bütün içerik tek geçişte (15 dk saklanır)
   ============================================================ */
function gbc_eb_indeks() {
	$c = get_transient( 'gbc_env_indeks' );
	if ( is_array( $c ) ) { return $c; }
	global $wpdb;
	$postlar = (array) $wpdb->get_results(
		"SELECT ID, post_title, post_type, post_date, post_modified, post_content FROM {$wpdb->posts}
		  WHERE post_status = 'publish' AND post_type IN ('post','page')", ARRAY_A );
	$meta = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", '_gbc_seo' ), ARRAY_A ) as $r ) {
		$v = maybe_unserialize( $r['meta_value'] );
		if ( is_array( $v ) ) { $meta[ (int) $r['post_id'] ] = $v; }
	}
	$gsc = function_exists( 'gbc_plan_gsc_harita' ) ? gbc_plan_gsc_harita() : array();
	$bag = function_exists( 'gbc_env_bag_sayilari' ) ? gbc_env_bag_sayilari( wp_list_pluck( $postlar, 'ID' ) ) : array();
	$plan = array();
	if ( function_exists( 'gbc_plan_analiz' ) ) {
		foreach ( gbc_plan_analiz()['satir'] as $s ) { if ( $s['e_pid'] ) { $plan[ (int) $s['e_pid'] ] = $s['no'] . ' · ' . $s['durum']; } }
	}
	$o = array();
	foreach ( $postlar as $p ) {
		$id = (int) $p['ID'];
		$m  = isset( $meta[ $id ] ) ? $meta[ $id ] : null;
		$yol = function_exists( 'gbc_plan_yol' ) ? gbc_plan_yol( get_permalink( $id ) ) : (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
		$kat = array();
		foreach ( (array) get_the_category( $id ) as $k ) { $kat[] = $k->name; }
		$o[] = array(
			'id' => $id, 'ad' => $p['post_title'], 'tur' => $p['post_type'], 'yol' => $yol,
			'tarih' => strtotime( $p['post_date'] ), 'degisti' => strtotime( $p['post_modified'] ),
			'sablon' => function_exists( 'gbc_env_sablon' ) ? gbc_env_sablon( $p['post_content'] ) : '—',
			'silo' => $kat ? implode( ' › ', array_slice( $kat, 0, 2 ) ) : '—',
			'skor' => ( $m && isset( $m['skor'] ) ) ? (int) $m['skor'] : null,
			'yeni' => ( $m && ! empty( $m['skor_surum'] ) ),
			'yap' => ( $m && isset( $m['yapilacak'] ) ) ? (int) $m['yapilacak'] : null,
			'yap1' => ( $m && isset( $m['yap1'] ) ) ? (int) $m['yap1'] : null,
			'dizin' => ( $m && isset( $m['dizin'] ) ) ? (string) $m['dizin'] : '',
			'olcum' => ( $m && isset( $m['zaman'] ) ) ? (int) $m['zaman'] : 0,
			'onceki' => ( $m && ! empty( $m['gecmis'][0]['skor'] ) && ! empty( $m['gecmis'][0]['skor_surum'] ) && (int) $m['gecmis'][0]['skor_surum'] >= 2 ) ? (int) $m['gecmis'][0]['skor'] : null,
			't' => isset( $gsc[ $yol ] ) ? $gsc[ $yol ]['t'] : null,
			'g' => isset( $gsc[ $yol ] ) ? $gsc[ $yol ]['g'] : null,
			's' => isset( $gsc[ $yol ] ) ? $gsc[ $yol ]['s'] : null,
			'gelen' => isset( $bag[ $id ]['gelen'] ) ? (int) $bag[ $id ]['gelen'] : 0,
			'giden' => isset( $bag[ $id ]['giden'] ) ? (int) $bag[ $id ]['giden'] : 0,
			'plan' => isset( $plan[ $id ] ) ? $plan[ $id ] : '',
		);
	}
	set_transient( 'gbc_env_indeks', $o, 2 * HOUR_IN_SECONDS ); /* v1.43.1: saatlik toplama sonunda tazelenir */
	return $o;
}

/* ============================================================
   Küçük yardımcılar
   ============================================================ */
function gbc_eb_renk( $skor ) {
	if ( null === $skor ) { return '#9AA0A6'; }
	return $skor >= 80 ? '#1A7F37' : ( $skor >= 60 ? '#8A6100' : '#B32D2E' );
}
function gbc_eb_rozet( $metin, $renk, $zemin ) {
	return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11.5px;font-weight:600;color:' . esc_attr( $renk ) . ';background:' . esc_attr( $zemin ) . ';white-space:nowrap">' . esc_html( $metin ) . '</span>';
}
function gbc_eb_durum_rozet( $k ) {
	$r = array(
		'acik'     => array( '✅ Açık', '#1A7F37', '#E8F3EC' ),
		'duzeltme' => array( '⚠️ Düzeltme', '#8A6100', '#FCF6E8' ),
		'acilacak' => array( '🔴 Açılacak', '#B32D2E', '#FBE7E7' ),
		'yok'      => array( 'Sitede yok', '#B32D2E', '#FBE7E7' ),
		'taslak'   => array( 'Taslak', '#5C6470', '#F0F0F1' ),
	);
	return isset( $r[ $k ] ) ? gbc_eb_rozet( $r[ $k ][0], $r[ $k ][1], $r[ $k ][2] ) : '—';
}
function gbc_eb_alarm_rozet( $a ) {
	$r = array( 'acil' => array( '⏰ Acil', '#fff', '#B32D2E' ), 'yakin' => array( '⏳ Yaklaşıyor', '#8A6100', '#FCF6E8' ), 'gozden' => array( '🔁 Gözden geçir', '#1B4E9B', '#E8EEF8' ) );
	return isset( $r[ $a ] ) ? gbc_eb_rozet( $r[ $a ][0], $r[ $a ][1], $r[ $a ][2] ) : '';
}
function gbc_eb_skor_hucre( $sk ) {
	if ( null === $sk['skor'] ) { return '<span style="color:#9AA0A6">—</span>'; }
	if ( ! empty( $sk['eski'] ) ) { return '<span style="color:#9AA0A6" title="Eski ölçüm, yeni skor bekleniyor">%' . (int) $sk['skor'] . '*</span>'; }
	return '<strong style="color:' . esc_attr( gbc_eb_renk( $sk['skor'] ) ) . '">%' . (int) $sk['skor'] . '</strong>';
}
function gbc_eb_kart( $baslik, $deger, $alt, $renk, $href = '' ) {
	$ic = '<div style="font-weight:600;color:#3C4149;font-size:13px">' . esc_html( $baslik ) . '</div>'
		. '<div style="font-size:28px;font-weight:700;color:' . esc_attr( $renk ) . ';line-height:1.3">' . esc_html( $deger ) . '</div>'
		. '<div style="color:#5C6470;font-size:12.5px">' . $alt . '</div>';
	$st = 'background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:14px 18px;display:block;text-decoration:none;color:inherit;min-width:0';
	return $href ? '<a href="' . esc_url( $href ) . '" style="' . $st . '">' . $ic . '</a>' : '<div style="' . $st . '">' . $ic . '</div>';
}
function gbc_eb_url( $ek = array() ) {
	return admin_url( 'admin.php?' . http_build_query( array_merge( array( 'page' => 'gbc-seo-envanter' ), $ek ) ) );
}
function gbc_eb_hacim( $mv ) {
	if ( null === $mv['aylik'] ) { return '<span style="color:#9AA0A6">—</span>'; }
	return esc_html( number_format_i18n( $mv['aylik'] ) ) . '<span style="color:#5C6470;font-size:11px">/ay</span>'
		. ( null !== $mv['zirve'] ? '<div style="font-size:11px;color:#5C6470">zirve ' . esc_html( gbc_plan_ay_uzun( $mv['zirve'] ) ) . ( $mv['varsayim'] ? '*' : '' ) . '</div>' : '' );
}
function gbc_eb_son_tarih( $s ) {
	if ( empty( $s['mevsim']['son'] ) ) { return '<span style="color:#9AA0A6">—</span>'; }
	$gun = (int) floor( ( $s['mevsim']['son'] - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
	return esc_html( wp_date( 'j M Y', $s['mevsim']['son'] ) ) . '<div style="font-size:11px;color:' . ( $gun < 0 ? '#B32D2E' : '#5C6470' ) . '">'
		. esc_html( $gun < 0 ? abs( $gun ) . ' gün geçti' : $gun . ' gün kaldı' ) . '</div>';
}
function gbc_eb_sayfa_hucre( $s ) {
	$ic = ( '' !== $s['ust'] ) ? '<span style="color:#9AA0A6">↳ </span>' : '';
	$yol = $s['e_pid'] ? $s['e_yol'] : ( $s['h_yol'] ?: '' );
	$link = $s['e_pid'] ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $s['e_pid'] ) ) . '">denetle</a>'
		. ' · <a href="' . esc_url( get_edit_post_link( $s['e_pid'] ) ) . '">düzenle</a>' : '';
	return $ic . '<strong>' . esc_html( $s['ad'] ) . '</strong>'
		. '<div style="color:#5C6470;font-size:12px">#' . esc_html( $s['no'] ) . ' · ' . esc_html( $s['ulke'] ?: $s['bolge'] ) . ' · ' . esc_html( $yol ) . $link
		. ( '' !== $s['yt'] ? ' · <a href="' . esc_url( $s['yt'] ) . '" target="_blank" rel="noopener">▶ video</a>' : '' ) . '</div>';
}

/* ============================================================
   EKRAN
   ============================================================ */
function gbc_eb_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$sekme = isset( $_GET['sekme'] ) ? sanitize_key( $_GET['sekme'] ) : 'ozet';

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Envanter', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo-envanter' ); }
	echo '<style>.gbc-eb-kart{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:14px 0;max-width:1400px}'
		. '@media (max-width:600px){.gbc-eb-kart{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gbc-eb-kart>*{padding:12px 14px!important}}'
		. '.gbc-eb-tablo{overflow-x:auto;max-width:1400px}.gbc-eb-tablo td{vertical-align:top}'
		. '.gbc-eb-kutu{background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;margin:14px 0;max-width:1400px}'
		. '.gbc-eb-ikili{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:16px;max-width:1400px;align-items:start}@media (max-width:1100px){.gbc-eb-ikili{grid-template-columns:1fr}}</style>';

	/* Toplama turu (eski Toplama ekranındaki düğme) */
	if ( isset( $_GET['gbc_seo_tur'] ) && check_admin_referer( 'gbc_seo_tur' ) && function_exists( 'gbc_seo_tur' ) ) {
		$r = gbc_seo_tur();
		if ( ! empty( $r['atlandi'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( 'Şu an başka bir GBC işi çalışıyor (' . $r['atlandi'] . '). Siteyi yormamak için bu tur atlandı; birkaç dakika sonra kendiliğinden devam eder.' ) . '</p></div>';
		} else
		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( '%1$d sayfa denetlendi (yeni GBC skoruyla), %2$d saniye sürdü.', count( $r['sayfa'] ), (int) round( $r['ms'] / 1000 ) ) )
			. ( $r['hata'] ? ' ' . esc_html( sprintf( '%d sayfada hata.', count( $r['hata'] ) ) ) : '' ) . '</p></div>';
	}
	if ( ! empty( $_GET['pmsg'] ) ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['pmsg'] ) ) ) . '</p></div>';
	}

	$pa = function_exists( 'gbc_plan_analiz' ) ? gbc_plan_analiz() : array( 'satir' => array(), 'planda_yok' => array(), 'ozet' => array() );
	$sekmeler = array(
		'ozet'    => 'Özet',
		'oncelik' => 'Öncelik ve takvim',
		'plan'    => 'Plan (tablo)',
		'liste'   => 'Bütün içerik',
		'silo'    => 'Silo ağacı',
		'yetim'   => 'Yetim sayfalar',
	);
	echo '<p style="line-height:2.4">';
	foreach ( $sekmeler as $s => $ad ) {
		echo '<a class="button ' . ( $sekme === $s ? 'button-primary' : '' ) . '" style="margin-right:6px" href="' . esc_url( gbc_eb_url( array( 'sekme' => $s ) ) ) . '">' . esc_html( $ad ) . '</a>';
	}
	echo '</p>';

	if ( 'oncelik' === $sekme ) { gbc_eb_oncelik( $pa ); }
	elseif ( 'plan' === $sekme ) { gbc_eb_plan( $pa ); }
	elseif ( 'liste' === $sekme ) { gbc_eb_liste( $pa ); }
	elseif ( 'silo' === $sekme && function_exists( 'gbc_env_silo_ekran' ) ) { gbc_env_silo_ekran(); }
	elseif ( 'yetim' === $sekme && function_exists( 'gbc_env_yetim_ekran' ) ) { gbc_env_yetim_ekran(); }
	else { gbc_eb_ozet( $pa ); }
	echo '</div>';
}

/* --- Özet --- */
function gbc_eb_ozet( $pa ) {
	$ix = gbc_eb_indeks();
	$n = count( $ix ); $yeni = 0; $top = 0; $dusuk = 0; $dizin_yok = 0; $eski = 0;
	foreach ( $ix as $r ) {
		if ( null === $r['skor'] ) { continue; }
		if ( ! $r['yeni'] ) { $eski++; continue; }
		$yeni++; $top += $r['skor'];
		if ( $r['skor'] < 60 ) { $dusuk++; }
		if ( 'yok' === $r['dizin'] ) { $dizin_yok++; }
	}
	$ort = $yeni ? (int) round( $top / $yeni ) : null;
	$oz = $pa['ozet'];
	$al = isset( $oz['alarm'] ) ? $oz['alarm'] : array( 'acil' => 0, 'yakin' => 0, 'gozden' => 0 );
	$ger = isset( $oz['gercek'] ) ? $oz['gercek'] : array( 'acik' => 0, 'duzeltme' => 0, 'taslak' => 0, 'yok' => 0 );
	$tur = defined( 'GBC_SEO_TUR_ADET' ) ? GBC_SEO_TUR_ADET : 6;

	echo '<div class="gbc-eb-kart">';
	echo gbc_eb_kart( 'Ortalama GBC skoru', null === $ort ? '—' : '%' . $ort, esc_html( $yeni . ' sayfa yeni skorla ölçüldü' ), gbc_eb_renk( $ort ), gbc_eb_url( array( 'sekme' => 'liste', 'sirala' => 'skor' ) ) );
	echo gbc_eb_kart( 'Ölçülen', $n ? '%' . (int) round( $yeni / $n * 100 ) : '—', esc_html( sprintf( '%1$d / %2$d içerik · kalan ~%3$d saat', $yeni, $n, (int) ceil( max( 0, $n - $yeni ) / max( 1, $tur ) ) ) ), $yeni >= $n * 0.9 ? '#1A7F37' : '#8A6100', '#gbc-eb-toplama' );
	echo gbc_eb_kart( 'Düşük skor (<60)', (string) $dusuk, 'yeni skorla ölçülenler içinde', $dusuk ? '#B32D2E' : '#1A7F37', gbc_eb_url( array( 'sekme' => 'liste', 'bant' => 'dusuk' ) ) );
	echo gbc_eb_kart( 'Alarm', (string) ( $al['acil'] + $al['yakin'] ), esc_html( $al['acil'] . ' acil · ' . $al['yakin'] . ' yaklaşıyor · ' . $al['gozden'] . ' gözden geçir' ), $al['acil'] ? '#B32D2E' : ( $al['yakin'] ? '#8A6100' : '#1A7F37' ), '#gbc-eb-alarm' );
	echo gbc_eb_kart( 'Plan: yayında doğru', (string) $ger['acik'], esc_html( sprintf( 'tablodaki %d sayfadan', isset( $oz['toplam'] ) ? $oz['toplam'] : 0 ) ), '#1A7F37', gbc_eb_url( array( 'sekme' => 'plan', 'gercek' => 'acik' ) ) );
	echo gbc_eb_kart( 'Plan: düzeltilecek', (string) $ger['duzeltme'], esc_html( ( isset( $oz['url_degisecek'] ) ? $oz['url_degisecek'] : 0 ) . ' adres değişecek (301)' ), '#8A6100', gbc_eb_url( array( 'sekme' => 'plan', 'gercek' => 'duzeltme' ) ) );
	echo gbc_eb_kart( 'Plan: açılacak', (string) ( $ger['yok'] + $ger['taslak'] ), esc_html( $ger['taslak'] . ' taslakta' ), '#B32D2E', gbc_eb_url( array( 'sekme' => 'plan', 'gercek' => 'yok' ) ) );
	echo gbc_eb_kart( 'Tablo ile site uyuşmuyor', (string) ( isset( $oz['tutarsiz'] ) ? $oz['tutarsiz'] : 0 ), 'tablonun güncellenmesi gereken satır', ( ! empty( $oz['tutarsiz'] ) ) ? '#B32D2E' : '#1A7F37', gbc_eb_url( array( 'sekme' => 'plan', 'bulgu' => '1' ) ) );
	echo gbc_eb_kart( 'Planda olmayan', (string) ( isset( $oz['planda_yok'] ) ? $oz['planda_yok'] : 0 ), 'sitede var, tabloda yok (gezi/liste)', ! empty( $oz['planda_yok'] ) ? '#8A6100' : '#1A7F37', gbc_eb_url( array( 'sekme' => 'plan' ) ) . '#gbc-eb-plandayok' );
	echo gbc_eb_kart( 'Google dizininde değil', (string) $dizin_yok, 'ölçülen sayfalar içinde', $dizin_yok ? '#B32D2E' : '#1A7F37', gbc_eb_url( array( 'sekme' => 'liste', 'bant' => 'dizinyok' ) ) );
	echo gbc_eb_kart( 'Hacim eksik', (string) ( isset( $oz['hacim_yok'] ) ? $oz['hacim_yok'] : 0 ), 'plan satırı, aylık arama yok', '#5C6470', gbc_eb_url( array( 'sekme' => 'plan', 'bulgu' => 'hacim' ) ) );
	echo '</div>';

	echo '<div class="gbc-eb-ikili">';
	/* Sol: alarmlar + sıradaki işler */
	echo '<div>';
	$alarmlar = array_values( array_filter( $pa['satir'], static function ( $s ) { return '' !== $s['alarm']; } ) );
	$sira = array( 'acil' => 0, 'yakin' => 1, 'gozden' => 2 );
	usort( $alarmlar, static function ( $a, $b ) use ( $sira ) {
		return $sira[ $a['alarm'] ] === $sira[ $b['alarm'] ] ? $b['oncelik'] - $a['oncelik'] : $sira[ $a['alarm'] ] - $sira[ $b['alarm'] ];
	} );
	echo '<div class="gbc-eb-kutu" id="gbc-eb-alarm"><strong style="font-size:15px">Alarmlar: mevsim ve son tarih</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Aramaların zirve ayından iki ay önce sayfa hazır olmalı (Google\'ın taraması ve sıralaması için). Hazır = tabloyla uyumlu yayında ve GBC skoru %70+.</div>';
	if ( ! $alarmlar ) { echo '<p><em>Şu an alarm yok.</em></p>'; }
	else {
		echo '<ol style="margin:0 0 0 20px">';
		foreach ( array_slice( $alarmlar, 0, 15 ) as $s ) {
			echo '<li style="margin:0 0 7px">' . gbc_eb_alarm_rozet( $s['alarm'] ) . ' <strong>' . esc_html( $s['ad'] ) . '</strong> '
				. gbc_eb_durum_rozet( $s['gercek'] ) . '<div style="font-size:12.5px;color:#3C4149">' . esc_html( $s['alarm_not'] ) . '</div></li>';
		}
		echo '</ol>';
		if ( count( $alarmlar ) > 15 ) { echo '<a href="' . esc_url( gbc_eb_url( array( 'sekme' => 'oncelik', 'alarm' => '1' ) ) ) . '">Bütün alarmlar (' . count( $alarmlar ) . ')</a>'; }
	}
	echo '</div>';

	$ps = $pa['satir'];
	usort( $ps, static function ( $a, $b ) { return $b['oncelik'] - $a['oncelik']; } );
	echo '<div class="gbc-eb-kutu"><strong style="font-size:15px">Sıradaki 10 iş</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Öncelik puanı: arama hacmi + durum (açılacak/düzeltme/düşük skor) + mevsim alarmı + video hazır mı + Search Console gösterimi.</div><ol style="margin:0 0 0 20px">';
	foreach ( array_slice( $ps, 0, 10 ) as $s ) {
		$ilk = '';
		foreach ( $s['bulgu'] as $b ) { if ( $b[0] <= 2 ) { $ilk = $b[1]; break; } }
		echo '<li style="margin:0 0 7px"><strong>' . esc_html( $s['ad'] ) . '</strong> ' . gbc_eb_durum_rozet( $s['gercek'] ) . ' ' . gbc_eb_alarm_rozet( $s['alarm'] )
			. ' <span style="color:#5C6470;font-size:12px">puan ' . (int) $s['oncelik'] . ( null !== $s['mevsim']['aylik'] ? ' · aylık ' . esc_html( number_format_i18n( $s['mevsim']['aylik'] ) ) : '' ) . '</span>'
			. ( '' !== $ilk ? '<div style="font-size:12.5px;color:#3C4149">' . esc_html( $ilk ) . '</div>' : '' ) . '</li>';
	}
	echo '</ol><a href="' . esc_url( gbc_eb_url( array( 'sekme' => 'oncelik' ) ) ) . '">Bütün öncelik listesi ve takvim</a></div>';
	echo '</div>';

	/* Sağ: toplama + tablo kaynağı */
	echo '<div>';
	gbc_eb_toplama_kutu();
	gbc_eb_kaynak_kutu( false );
	echo '</div></div>';
}

function gbc_eb_toplama_kutu() {
	if ( ! function_exists( 'gbc_seo_ilerleme' ) ) { return; }
	$i = gbc_seo_ilerleme();
	$sonraki = wp_next_scheduled( 'gbc_seo_toplama' );
	$tur = defined( 'GBC_SEO_TUR_ADET' ) ? GBC_SEO_TUR_ADET : 6;
	echo '<div class="gbc-eb-kutu" id="gbc-eb-toplama"><strong style="font-size:15px">Toplama (otomatik denetim)</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Saat başı ' . (int) $tur . ' sayfa tam denetlenir: bölüm puanlı GBC skoru (silo dahil), yapılacaklar, Google dizini, kelime fırsatları. Sıra: Search Console\'da gösterimi olanlar → plandaki sayfalar → hiç ölçülmemişler → en eski ölçüm. Ortaklık ağ adreslerine istek atılmaz.</div>';
	echo '<p style="margin:4px 0">Sıradaki tur: <strong>' . esc_html( $sonraki ? human_time_diff( time(), $sonraki ) . ' sonra' : 'kayıtlı değil' ) . '</strong> · Tamamlanan tur: ' . (int) $i['tur_sayisi'] . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( gbc_eb_url( array( 'sekme' => 'ozet', 'gbc_seo_tur' => 1 ) ), 'gbc_seo_tur' ) ) . '">'
		. esc_html( sprintf( 'Şimdi bir tur çalıştır (%d sayfa)', $tur ) ) . '</a></p>';
	if ( ! empty( $i['son_tur'] ) ) {
		$st = $i['son_tur'];
		echo '<div style="color:#5C6470;font-size:12.5px">Son tur: ' . esc_html( wp_date( 'j F H:i', (int) $st['zaman'] ) ) . '</div><ul style="margin:4px 0 0 18px;list-style:disc">';
		foreach ( (array) $st['sayfa'] as $r ) {
			echo '<li>' . esc_html( get_the_title( $r['pid'] ) ) . ' <strong style="color:' . esc_attr( gbc_eb_renk( (int) $r['skor'] ) ) . '">%' . (int) $r['skor'] . '</strong></li>';
		}
		foreach ( (array) $st['hata'] as $r ) { echo '<li style="color:#B32D2E">' . esc_html( get_the_title( $r['pid'] ) . ' — ' . $r['hata'] ) . '</li>'; }
		echo '</ul>';
	}
	echo '</div>';
}

function gbc_eb_kaynak_kutu( $tam ) {
	if ( ! function_exists( 'gbc_plan_oku' ) ) { return; }
	$p = gbc_plan_oku(); $a = gbc_plan_ayar();
	$kad = array( 'sheet-api' => 'Google tablosu (servis hesabı)', 'sheet-acik' => 'Google tablosu (bağlantı)', 'csv' => 'yapıştırılan tablo', 'ilk' => 'ilk kurulum verisi' );
	echo '<div class="gbc-eb-kutu"><strong style="font-size:15px">Plan tablosu</strong>';
	echo '<p style="margin:6px 0">' . ( $p['t'] ? esc_html( sprintf( '%1$d satır · %2$s · son okuma %3$s önce', count( $p['satir'] ), isset( $kad[ $p['kaynak'] ] ) ? $kad[ $p['kaynak'] ] : $p['kaynak'], human_time_diff( (int) $p['t'] ) ) ) : '<strong style="color:#B32D2E">Tablo henüz okunmadı.</strong>' ) . '</p>';
	if ( '' !== $p['not'] ) { echo '<p style="color:#B32D2E;font-size:12.5px;margin:4px 0">Son deneme başarısız: ' . esc_html( $p['not'] ) . '</p>'; }
	echo '<p style="margin:6px 0"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gbc_plan&is=cek' ), 'gbc_plan' ) ) . '">Tablodan şimdi güncelle</a> '
		. '<a href="https://docs.google.com/spreadsheets/d/' . esc_attr( $a['sheet'] ) . '/edit" target="_blank" rel="noopener">tabloyu aç</a></p>';
	$mail = '';
	if ( function_exists( 'gbc_km_ayar' ) ) { $sa = json_decode( (string) gbc_km_ayar( 'google_sa', '' ), true ); $mail = is_array( $sa ) && ! empty( $sa['client_email'] ) ? (string) $sa['client_email'] : ''; }
	echo '<div style="color:#5C6470;font-size:12.5px">Her gün kendiliğinden okunur. Okuyabilmesi için tablo '
		. ( '' !== $mail ? 'şu servis hesabıyla <strong style="color:#14181F;word-break:break-all">' . esc_html( $mail ) . '</strong> "Görüntüleyen" olarak paylaşılmalı' : 'servis hesabıyla "Görüntüleyen" olarak paylaşılmalı' )
		. ' ya da "bağlantıya sahip herkes görüntüleyebilir" olmalı.' . ( 'ilk' === $p['kaynak'] ? ' <strong style="color:#8A6100">Şu an pakete gömülü 29 Eylül verisi gösteriliyor.</strong>' : '' ) . '</div>';
	if ( $tam ) {
		echo '<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600">Tablo adresi ve elle yükleme</summary>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0">';
		wp_nonce_field( 'gbc_plan' );
		echo '<input type="hidden" name="action" value="gbc_plan"><input type="hidden" name="is" value="sheet">'
			. '<input type="text" name="sheet" value="' . esc_attr( 'https://docs.google.com/spreadsheets/d/' . $a['sheet'] . '/edit' ) . '" style="width:100%;max-width:560px;box-sizing:border-box"> <button class="button">Kaydet</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gbc_plan' );
		echo '<input type="hidden" name="action" value="gbc_plan"><input type="hidden" name="is" value="csv">'
			. '<textarea name="csv" rows="4" style="width:100%;max-width:560px;box-sizing:border-box" placeholder="Gezi Rehberleri sayfasını CSV olarak yapıştır (# ve Sayfa Adı başlık satırı dahil)"></textarea><br>'
			. '<button class="button">Yapıştırılanı yükle</button></form></details>';
	}
	echo '</div>';
}

/* --- Öncelik ve takvim --- */
function gbc_eb_oncelik( $pa ) {
	$bolge  = isset( $_GET['bolge'] ) ? sanitize_text_field( wp_unslash( $_GET['bolge'] ) ) : '';
	$alarm  = ! empty( $_GET['alarm'] );
	$ps = array_values( array_filter( $pa['satir'], static function ( $s ) use ( $bolge, $alarm ) {
		return ( '' === $bolge || $s['bolge'] === $bolge ) && ( ! $alarm || '' !== $s['alarm'] );
	} ) );
	usort( $ps, static function ( $a, $b ) { return $b['oncelik'] - $a['oncelik']; } );

	/* Takvim: önümüzdeki 12 ay, son tarihi o aya düşen sayfalar */
	$bugun = current_time( 'timestamp' );
	$ay = array();
	foreach ( $pa['satir'] as $s ) {
		if ( empty( $s['mevsim']['son'] ) ) { continue; }
		$k = gmdate( 'Y-m', max( $s['mevsim']['son'], gmmktime( 0, 0, 0, (int) gmdate( 'n', $bugun ), 1, (int) gmdate( 'Y', $bugun ) ) ) );
		$ay[ $k ][] = $s;
	}
	ksort( $ay );
	echo '<div class="gbc-eb-kutu"><strong style="font-size:15px">Takvim: hangi ay neyin hazır olması gerekiyor</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Son tarih = aramaların zirve ayından iki ay önce. Tarihi geçmişler bu aya yazılır. * işareti: aylık hacim yok, kış teması olduğu için zirve Aralık varsayıldı.</div>';
	if ( ! $ay ) { echo '<p><em>Mevsimi belli olan sayfa yok (aylık hacim girilmemiş).</em></p>'; }
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">';
	foreach ( $ay as $k => $liste ) {
		list( $y, $m ) = array_map( 'intval', explode( '-', $k ) );
		usort( $liste, static function ( $a, $b ) { return $b['oncelik'] - $a['oncelik']; } );
		echo '<div style="border:1px solid #E3E5E9;border-radius:10px;padding:8px 12px"><strong>' . esc_html( gbc_plan_ay_uzun( $m - 1 ) . ' ' . $y ) . '</strong> <span style="color:#5C6470">(' . count( $liste ) . ')</span><ul style="margin:4px 0 0 16px;list-style:disc">';
		foreach ( $liste as $s ) {
			$hazir = ( 'acik' === $s['gercek'] && null !== $s['skor']['skor'] && empty( $s['skor']['eski'] ) && $s['skor']['skor'] >= 70 );
			echo '<li style="font-size:12.5px">' . ( $hazir ? '✓ ' : '' ) . esc_html( $s['ad'] ) . ' <span style="color:#5C6470">· zirve ' . esc_html( gbc_plan_ay_uzun( $s['mevsim']['zirve'] ) ) . ( $s['mevsim']['varsayim'] ? '*' : '' ) . '</span></li>';
		}
		echo '</ul></div>';
	}
	echo '</div></div>';

	/* Süzgeç */
	$bolgeler = array_unique( wp_list_pluck( $pa['satir'], 'bolge' ) );
	echo '<form method="get" style="margin:10px 0"><input type="hidden" name="page" value="gbc-seo-envanter"><input type="hidden" name="sekme" value="oncelik">'
		. '<select name="bolge"><option value="">Bütün bölgeler</option>';
	foreach ( $bolgeler as $b ) { echo '<option' . selected( $bolge, $b, false ) . '>' . esc_html( $b ) . '</option>'; }
	echo '</select> <label><input type="checkbox" name="alarm" value="1"' . checked( $alarm, true, false ) . '> yalnız alarmlılar</label> <button class="button">Süz</button></form>';

	echo '<div class="gbc-eb-tablo"><table class="widefat striped"><thead><tr><th style="width:40px">Sıra</th><th>Sayfa</th><th style="width:110px">Durum</th><th style="width:60px">Skor</th>'
		. '<th style="width:90px">Aylık arama</th><th style="width:110px">Son tarih</th><th style="width:70px">Search C.</th><th>Ne yapılmalı</th><th style="width:50px">Puan</th></tr></thead><tbody>';
	foreach ( $ps as $i => $s ) {
		$isler = array();
		if ( $s['alarm'] ) { $isler[] = gbc_eb_alarm_rozet( $s['alarm'] ) . ' ' . esc_html( $s['alarm_not'] ); }
		foreach ( $s['bulgu'] as $b ) { if ( $b[0] <= 2 ) { $isler[] = esc_html( $b[1] ); } }
		if ( 'acilacak' === $s['durum'] && 'yok' === $s['gercek'] && '' !== $s['not'] ) { $isler[] = '<span style="color:#5C6470">' . esc_html( $s['not'] ) . '</span>'; }
		echo '<tr><td>' . ( $i + 1 ) . '</td><td>' . gbc_eb_sayfa_hucre( $s ) . '</td><td>' . gbc_eb_durum_rozet( $s['gercek'] ) . '</td><td>' . gbc_eb_skor_hucre( $s['skor'] ) . '</td>'
			. '<td>' . gbc_eb_hacim( $s['mevsim'] ) . '</td><td>' . gbc_eb_son_tarih( $s ) . '</td>'
			. '<td>' . ( $s['gsc'] ? esc_html( number_format_i18n( $s['gsc']['g'] ) ) . '<div style="font-size:11px;color:#5C6470">' . (int) $s['gsc']['t'] . ' tık</div>' : '—' ) . '</td>'
			. '<td style="font-size:12.5px">' . ( $isler ? implode( '<br>', array_slice( $isler, 0, 4 ) ) : '<span style="color:#1A7F37">Tamam</span>' ) . '</td><td><strong>' . (int) $s['oncelik'] . '</strong></td></tr>';
	}
	if ( ! $ps ) { echo '<tr><td colspan="9"><em>Plan satırı yok. Özet sekmesinden "Tablodan şimdi güncelle".</em></td></tr>'; }
	echo '</tbody></table></div>';
}

/* --- Plan (tablo) --- */
function gbc_eb_plan( $pa ) {
	$bolge  = isset( $_GET['bolge'] ) ? sanitize_text_field( wp_unslash( $_GET['bolge'] ) ) : '';
	$gercek = isset( $_GET['gercek'] ) ? sanitize_key( $_GET['gercek'] ) : '';
	$bulgu  = isset( $_GET['bulgu'] ) ? sanitize_key( $_GET['bulgu'] ) : '';
	gbc_eb_kaynak_kutu( true );

	$ps = array_values( array_filter( $pa['satir'], static function ( $s ) use ( $bolge, $gercek, $bulgu ) {
		if ( '' !== $bolge && $s['bolge'] !== $bolge ) { return false; }
		if ( 'yok' === $gercek && ! in_array( $s['gercek'], array( 'yok', 'taslak' ), true ) ) { return false; }
		if ( '' !== $gercek && 'yok' !== $gercek && $s['gercek'] !== $gercek ) { return false; }
		if ( '1' === $bulgu ) { $v = false; foreach ( $s['bulgu'] as $b ) { if ( 1 === $b[0] ) { $v = true; } } if ( ! $v ) { return false; } }
		if ( 'hacim' === $bulgu && null !== $s['mevsim']['aylik'] ) { return false; }
		return true;
	} ) );

	$bolgeler = array_unique( wp_list_pluck( $pa['satir'], 'bolge' ) );
	echo '<form method="get" style="margin:10px 0"><input type="hidden" name="page" value="gbc-seo-envanter"><input type="hidden" name="sekme" value="plan">'
		. '<select name="bolge"><option value="">Bütün bölgeler</option>';
	foreach ( $bolgeler as $b ) { echo '<option' . selected( $bolge, $b, false ) . '>' . esc_html( $b ) . '</option>'; }
	echo '</select> <select name="gercek"><option value="">Her durum</option>';
	foreach ( array( 'acik' => 'Yayında, doğru', 'duzeltme' => 'Düzeltilecek', 'yok' => 'Açılacak (yok/taslak)' ) as $k => $ad ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $gercek, $k, false ) . '>' . esc_html( $ad ) . '</option>'; }
	echo '</select> <select name="bulgu"><option value="">Bütün satırlar</option><option value="1"' . selected( $bulgu, '1', false ) . '>Tablo ile site uyuşmayanlar</option><option value="hacim"' . selected( $bulgu, 'hacim', false ) . '>Hacmi eksik olanlar</option></select>'
		. ' <button class="button">Süz</button> <span style="color:#5C6470">' . count( $ps ) . ' satır</span></form>';

	echo '<div class="gbc-eb-tablo"><table class="widefat striped"><thead><tr><th>Sayfa</th><th style="width:100px">Tabloda</th><th style="width:110px">Sitede</th>'
		. '<th style="width:110px">Şablon<br><span style="font-weight:400">şu an → hedef</span></th><th style="width:60px">Skor</th><th style="width:90px">Aylık arama</th><th>Bulgular</th></tr></thead><tbody>';
	$son_bolge = null;
	foreach ( $ps as $s ) {
		if ( $s['bolge'] !== $son_bolge ) { $son_bolge = $s['bolge']; echo '<tr><td colspan="7" style="background:#F6F7F7;font-weight:700">' . esc_html( $s['bolge'] ) . '</td></tr>'; }
		$bl = array();
		foreach ( $s['bulgu'] as $b ) {
			$r = array( 1 => '#B32D2E', 2 => '#8A6100', 3 => '#5C6470' )[ $b[0] ];
			$bl[] = '<div style="color:' . $r . '">' . ( 1 === $b[0] ? '✗ ' : ( 2 === $b[0] ? '• ' : '· ' ) ) . esc_html( $b[1] ) . '</div>';
		}
		if ( '' !== $s['not'] ) { $bl[] = '<div style="color:#5C6470;font-style:italic">Not: ' . esc_html( $s['not'] ) . '</div>'; }
		echo '<tr><td>' . gbc_eb_sayfa_hucre( $s ) . '</td><td>' . gbc_eb_durum_rozet( $s['durum'] ) . '</td><td>' . gbc_eb_durum_rozet( $s['gercek'] ) . '</td>'
			. '<td style="font-size:12.5px">' . esc_html( ( $s['e_sablon'] ?: '—' ) . ' → ' . ( $s['h_sablon'] ?: '—' ) ) . '</td><td>' . gbc_eb_skor_hucre( $s['skor'] ) . '</td>'
			. '<td>' . gbc_eb_hacim( $s['mevsim'] ) . '</td><td style="font-size:12.5px">' . implode( '', $bl ) . '</td></tr>';
	}
	if ( ! $ps ) { echo '<tr><td colspan="7"><em>Satır yok.</em></td></tr>'; }
	echo '</tbody></table></div>';

	/* Planda olmayanlar */
	echo '<h2 id="gbc-eb-plandayok" style="margin-top:26px">Sitede var, tabloda yok (' . count( $pa['planda_yok'] ) . ')</h2>'
		. '<p style="color:#5C6470;max-width:1000px">Gezi veya Liste şablonunda olan ya da başlığında "Gezi Rehberi / Gezilecek Yerler" geçen yayındaki sayfalar. Tabloya eklenmeli ya da bir plan satırıyla birleştirilmeli. Search Console gösterimine göre sıralı.</p>';
	echo '<div class="gbc-eb-tablo"><table class="widefat striped"><thead><tr><th>Sayfa</th><th style="width:80px">Şablon</th><th style="width:60px">Skor</th><th style="width:110px">Gösterim (90 g)</th></tr></thead><tbody>';
	foreach ( $pa['planda_yok'] as $y ) {
		echo '<tr><td><strong>' . esc_html( $y['ad'] ) . '</strong><div style="color:#5C6470;font-size:12px">' . (int) $y['pid'] . ' · ' . esc_html( $y['yol'] )
			. ' · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $y['pid'] ) ) . '">denetle</a></div></td><td>' . esc_html( $y['sablon'] ) . '</td><td>' . gbc_eb_skor_hucre( $y['skor'] ) . '</td>'
			. '<td>' . ( $y['gsc'] ? esc_html( number_format_i18n( $y['gsc']['g'] ) ) . ' · ' . (int) $y['gsc']['t'] . ' tık' : '—' ) . '</td></tr>';
	}
	if ( ! $pa['planda_yok'] ) { echo '<tr><td colspan="4"><em>Yok: bütün gezi/liste sayfaları tabloda.</em></td></tr>'; }
	echo '</tbody></table></div>';
}

/* --- Bütün içerik --- */
function gbc_eb_liste( $pa ) {
	$ix = gbc_eb_indeks();
	$ara    = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';
	$sablon = isset( $_GET['sablon'] ) ? sanitize_text_field( wp_unslash( $_GET['sablon'] ) ) : '';
	$bant   = isset( $_GET['bant'] ) ? sanitize_key( $_GET['bant'] ) : '';
	$plan   = isset( $_GET['plan'] ) ? sanitize_key( $_GET['plan'] ) : '';
	$sirala = isset( $_GET['sirala'] ) ? sanitize_key( $_GET['sirala'] ) : 'gos';
	$sf     = isset( $_GET['sf'] ) ? max( 1, (int) $_GET['sf'] ) : 1;

	$l = array_values( array_filter( $ix, static function ( $r ) use ( $ara, $sablon, $bant, $plan ) {
		if ( '' !== $ara && false === mb_stripos( $r['ad'] . ' ' . $r['yol'], $ara ) ) { return false; }
		if ( '' !== $sablon && $r['sablon'] !== $sablon ) { return false; }
		if ( 'dusuk' === $bant && ! ( $r['yeni'] && $r['skor'] < 60 ) ) { return false; }
		if ( 'orta' === $bant && ! ( $r['yeni'] && $r['skor'] >= 60 && $r['skor'] < 80 ) ) { return false; }
		if ( 'iyi' === $bant && ! ( $r['yeni'] && $r['skor'] >= 80 ) ) { return false; }
		if ( 'olculmedi' === $bant && $r['yeni'] ) { return false; }
		if ( 'dizinyok' === $bant && 'yok' !== $r['dizin'] ) { return false; }
		if ( 'dustu' === $bant && ! ( null !== $r['onceki'] && $r['yeni'] && $r['skor'] < $r['onceki'] ) ) { return false; }
		if ( 'var' === $plan && '' === $r['plan'] ) { return false; }
		if ( 'yok' === $plan && '' !== $r['plan'] ) { return false; }
		return true;
	} ) );
	usort( $l, static function ( $a, $b ) use ( $sirala ) {
		switch ( $sirala ) {
			case 'skor':   return ( $a['yeni'] ? $a['skor'] : 999 ) - ( $b['yeni'] ? $b['skor'] : 999 );
			case 'skor_y': return ( $b['yeni'] ? $b['skor'] : -1 ) - ( $a['yeni'] ? $a['skor'] : -1 );
			case 'tik':    return (int) $b['t'] - (int) $a['t'];
			case 'yeni':   return $b['tarih'] - $a['tarih'];
			case 'eski':   return $a['degisti'] - $b['degisti'];
			case 'ad':     return strcmp( $a['ad'], $b['ad'] );
			default:       return (int) $b['g'] - (int) $a['g'];
		}
	} );

	$adet = 50; $top = count( $l ); $sayfa = max( 1, (int) ceil( $top / $adet ) );
	$l = array_slice( $l, ( $sf - 1 ) * $adet, $adet );

	$sablonlar = array_unique( wp_list_pluck( $ix, 'sablon' ) ); sort( $sablonlar );
	echo '<form method="get" class="gbc-eb-kutu" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="hidden" name="page" value="gbc-seo-envanter"><input type="hidden" name="sekme" value="liste">'
		. '<input type="search" name="ara" value="' . esc_attr( $ara ) . '" placeholder="başlık ya da adres" style="width:180px">'
		. '<select name="sablon"><option value="">Her şablon</option>';
	foreach ( $sablonlar as $s ) { echo '<option' . selected( $sablon, $s, false ) . '>' . esc_html( $s ) . '</option>'; }
	echo '</select><select name="bant">';
	foreach ( array( '' => 'Her skor', 'dusuk' => 'Düşük (<60)', 'orta' => 'Orta (60–79)', 'iyi' => 'İyi (80+)', 'dustu' => 'Skoru düşenler', 'olculmedi' => 'Yeni skorla ölçülmedi', 'dizinyok' => 'Google dizininde değil' ) as $k => $ad ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $bant, $k, false ) . '>' . esc_html( $ad ) . '</option>';
	}
	echo '</select><select name="plan"><option value="">Plan: hepsi</option><option value="var"' . selected( $plan, 'var', false ) . '>Tabloda olan</option><option value="yok"' . selected( $plan, 'yok', false ) . '>Tabloda olmayan</option></select>'
		. '<select name="sirala">';
	foreach ( array( 'gos' => 'Gösterim (çok → az)', 'tik' => 'Tıklama', 'skor' => 'Skor (düşük önce)', 'skor_y' => 'Skor (yüksek önce)', 'yeni' => 'En yeni yazı', 'eski' => 'En uzun süredir güncellenmeyen', 'ad' => 'Başlık' ) as $k => $ad ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $sirala, $k, false ) . '>' . esc_html( $ad ) . '</option>';
	}
	echo '</select><button class="button button-primary">Uygula</button><span style="color:#5C6470">' . esc_html( number_format_i18n( $top ) ) . ' içerik</span></form>';

	echo '<div class="gbc-eb-tablo"><table class="widefat striped"><thead><tr><th>İçerik</th><th style="width:150px">Silo</th><th style="width:70px">Şablon</th><th style="width:70px">Plan</th>'
		. '<th style="width:80px">Skor</th><th style="width:80px">Yapılacak</th><th style="width:90px">Gösterim / tık</th><th style="width:55px">Sıra</th><th style="width:70px">Bağ g/ç</th><th style="width:60px">Dizin</th><th style="width:90px">Ölçüm</th></tr></thead><tbody>';
	foreach ( $l as $r ) {
		$sk = $r['yeni'] ? '<strong style="color:' . esc_attr( gbc_eb_renk( $r['skor'] ) ) . '">%' . (int) $r['skor'] . '</strong>' : ( null !== $r['skor'] ? '<span style="color:#9AA0A6" title="eski ölçüm">%' . (int) $r['skor'] . '*</span>' : '<span style="color:#9AA0A6">—</span>' );
		if ( $r['yeni'] && null !== $r['onceki'] && $r['onceki'] !== $r['skor'] ) {
			$sk .= ' <span style="font-size:11px;color:' . ( $r['skor'] > $r['onceki'] ? '#1A7F37' : '#B32D2E' ) . '">' . ( $r['skor'] > $r['onceki'] ? '▲' : '▼' ) . abs( $r['skor'] - $r['onceki'] ) . '</span>';
		}
		echo '<tr><td><strong>' . esc_html( $r['ad'] ) . '</strong><div style="color:#5C6470;font-size:12px">' . esc_html( $r['yol'] )
			. ' · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $r['id'] ) ) . '">denetle</a> · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-firsat&fpid=' . $r['id'] ) ) . '">fırsatlar</a></div></td>'
			. '<td style="font-size:12px">' . esc_html( $r['silo'] ) . '</td><td>' . esc_html( $r['sablon'] ) . '</td>'
			. '<td style="font-size:12px">' . ( '' !== $r['plan'] ? '#' . esc_html( strtok( $r['plan'], ' ' ) ) : '<span style="color:#9AA0A6">—</span>' ) . '</td>'
			. '<td>' . $sk . '</td>'
			. '<td>' . ( null === $r['yap'] ? '—' : (int) $r['yap'] . ( $r['yap1'] ? ' <span style="color:#B32D2E;font-size:11px">(' . (int) $r['yap1'] . ' yüksek)</span>' : '' ) ) . '</td>'
			. '<td>' . ( null === $r['g'] ? '—' : esc_html( number_format_i18n( $r['g'] ) ) . ' / ' . esc_html( number_format_i18n( $r['t'] ) ) ) . '</td>'
			. '<td>' . ( null === $r['s'] ? '—' : esc_html( number_format_i18n( $r['s'], 1 ) ) ) . '</td>'
			. '<td>' . ( $r['gelen'] ? (int) $r['gelen'] : '<span style="color:#B32D2E;font-weight:700">0</span>' ) . ' / ' . (int) $r['giden'] . '</td>'
			. '<td>' . ( 'var' === $r['dizin'] ? '<span style="color:#1A7F37">✓</span>' : ( 'yok' === $r['dizin'] ? '<span style="color:#B32D2E">✗</span>' : '—' ) ) . '</td>'
			. '<td style="font-size:12px;color:#5C6470">' . ( $r['olcum'] ? esc_html( human_time_diff( $r['olcum'] ) ) . ' önce' : 'hiç' ) . '</td></tr>';
	}
	if ( ! $l ) { echo '<tr><td colspan="11"><em>Bu süzgeçte içerik yok.</em></td></tr>'; }
	echo '</tbody></table></div>';

	$q = array_filter( array( 'sekme' => 'liste', 'ara' => $ara, 'sablon' => $sablon, 'bant' => $bant, 'plan' => $plan, 'sirala' => $sirala ) );
	echo '<p style="margin-top:12px">';
	if ( $sf > 1 ) { echo '<a class="button" href="' . esc_url( gbc_eb_url( $q + array( 'sf' => $sf - 1 ) ) ) . '">← Önceki</a> '; }
	echo '<span style="color:#5C6470">sayfa ' . (int) $sf . ' / ' . (int) $sayfa . '</span> ';
	if ( $sf < $sayfa ) { echo '<a class="button" href="' . esc_url( gbc_eb_url( $q + array( 'sf' => $sf + 1 ) ) ) . '">Sonraki →</a>'; }
	echo '</p><p style="color:#5C6470;font-size:12.5px;max-width:1000px">Skor yanındaki * eski ölçümdür (v1.43 öncesi); toplama yeni GBC skoruyla yeniden ölçtükçe kalkar. ▲▼ bir önceki ölçüme göre değişim. Bağ g/ç: sitenin içinden aldığı ve verdiği bağlantı; 0 gelen = yetim.</p>';
}
