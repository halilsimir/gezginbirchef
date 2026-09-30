<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — SEO Envanter ve Silo.
 *
 * ENVANTER: bütün içerik tek tabloda — silosu, şablonu, skoru, Search
 * Console tıklaması, iç bağlantısı, ortaklık bağı. Sıralanır, süzülür.
 *
 * SİLO: "Atina'ya neler bağlı" sorusunun cevabı tahminle değil, sitenin
 * GERÇEK iç bağlantı grafiğinden verilir. Rank Math'in link sayacı zaten
 * her yazının verdiği ve aldığı bağlantıyı tutuyor (448 KB tablo); biz de
 * onu okuyoruz. Yetim sayfa (hiç bağ almayan) böyle bulunur.
 *
 * KAYNAKLAR: hangi veri kaynağı bağlı, hangisi değil — iddia değil, canlı
 * kontrol. Bağlı olmayan bir kaynağı "kullanıyoruz" diye yazmaz.
 */

/* ============================================================
   BAĞLANTI GRAFİĞİ — Rank Math iç bağlantı tabloları
   ============================================================ */

function gbc_env_bag_kaynak() {
	static $bellek = null;
	if ( null !== $bellek ) { return $bellek; }
	global $wpdb;

	$bag  = $wpdb->prefix . 'rank_math_internal_links';
	$meta = $wpdb->prefix . 'rank_math_internal_meta';

	$v1 = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bag ) );
	$v2 = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $meta ) );

	if ( ! $v1 ) {
		$bellek = array( 'var' => false, 'not' => sprintf( __( '%s tablosu yok. Rank Math “Bağlantı Sayacı” modülü açık mı?', 'gbc-core' ), $bag ) );
		return $bellek;
	}

	$sutun = array();
	foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$bag}`" ) as $s ) {
		$sutun[ strtolower( $s->Field ) ] = true;
	}

	$bellek = array(
		'var'   => true,
		'bag'   => $bag,
		'meta'  => $v2 ? $meta : '',
		'sutun' => $sutun,
	);
	return $bellek;
}

/** Bir sayfaya bağ veren sayfalar. */
function gbc_env_gelen( $pid, $limit = 30 ) {
	$k = gbc_env_bag_kaynak();
	if ( empty( $k['var'] ) || ! isset( $k['sutun']['target_post_id'] ) ) { return array(); }
	global $wpdb;

	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT post_id FROM `{$k['bag']}` WHERE target_post_id = %d AND post_id <> %d LIMIT %d",
		(int) $pid, (int) $pid, (int) $limit
	), ARRAY_A );
}

/** Bir sayfanın bağ verdiği sayfalar. */
function gbc_env_giden( $pid, $limit = 30 ) {
	$k = gbc_env_bag_kaynak();
	if ( empty( $k['var'] ) || ! isset( $k['sutun']['target_post_id'] ) ) { return array(); }
	global $wpdb;

	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT target_post_id FROM `{$k['bag']}`
		  WHERE post_id = %d AND target_post_id > 0 AND target_post_id <> %d LIMIT %d",
		(int) $pid, (int) $pid, (int) $limit
	), ARRAY_A );
}

/** Hiç iç bağ ALMAYAN yayımdaki içerikler — yetimler. */
function gbc_env_yetimler( $limit = 100 ) {
	$k = gbc_env_bag_kaynak();
	if ( empty( $k['var'] ) ) { return null; }
	global $wpdb;

	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_title, p.post_type
		   FROM {$wpdb->posts} p
		  WHERE p.post_status = 'publish' AND p.post_type IN ('post','page')
		    AND NOT EXISTS (
		        SELECT 1 FROM `{$k['bag']}` l
		         WHERE l.target_post_id = p.ID AND l.post_id <> p.ID
		    )
		  ORDER BY p.post_modified DESC
		  LIMIT %d",
		(int) $limit
	), ARRAY_A );
}

/** Bağ sayıları: her içerik için gelen / giden. */
function gbc_env_bag_sayilari( $idler ) {
	$k = gbc_env_bag_kaynak();
	$bos = array();
	if ( empty( $k['var'] ) || ! $idler ) { return $bos; }
	global $wpdb;

	$yer = implode( ',', array_map( 'intval', $idler ) );

	$sonuc = array();
	foreach ( (array) $wpdb->get_results( "SELECT target_post_id id, COUNT(DISTINCT post_id) n FROM `{$k['bag']}` WHERE target_post_id IN ({$yer}) GROUP BY target_post_id", ARRAY_A ) as $r ) {
		$sonuc[ (int) $r['id'] ]['gelen'] = (int) $r['n'];
	}
	foreach ( (array) $wpdb->get_results( "SELECT post_id id, COUNT(DISTINCT target_post_id) n FROM `{$k['bag']}` WHERE post_id IN ({$yer}) AND target_post_id > 0 GROUP BY post_id", ARRAY_A ) as $r ) {
		$sonuc[ (int) $r['id'] ]['giden'] = (int) $r['n'];
	}
	return $sonuc;
}

/* ============================================================
   ŞABLON TESPİTİ
   ============================================================ */
function gbc_env_sablon( $icerik ) {
	$harita = array(
		22607 => __( 'Gezi', 'gbc-core' ),
		22608 => __( 'Gezi', 'gbc-core' ),
		23108 => __( 'Liste', 'gbc-core' ),
		23489 => __( 'Detay', 'gbc-core' ),
		23340 => __( 'Rota', 'gbc-core' ),
		24751 => __( 'Tarif', 'gbc-core' ),
		26922 => __( 'Blog', 'gbc-core' ),
		24156 => __( 'Sözlük', 'gbc-core' ),
	);
	foreach ( $harita as $kod => $ad ) {
		if ( false !== strpos( (string) $icerik, '[wpcode id="' . $kod . '"' ) ) { return $ad; }
	}
	return '—';
}

/* ============================================================
   ENVANTER LİSTESİ
   ============================================================ */
function gbc_env_liste( $args = array() ) {
	$v = wp_parse_args( $args, array(
		'kategori' => 0,
		'sablon'   => '',
		'sorun'    => '',
		'ara'      => '',
		'sayfa'    => 1,
		'adet'     => 40,
	) );

	$sorgu = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => (int) $v['adet'],
		'paged'          => max( 1, (int) $v['sayfa'] ),
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	if ( $v['kategori'] ) { $sorgu['cat'] = (int) $v['kategori']; }
	if ( '' !== $v['ara'] ) { $sorgu['s'] = $v['ara']; }

	$q = new WP_Query( $sorgu );

	$idler = wp_list_pluck( $q->posts, 'ID' );
	$baglar = gbc_env_bag_sayilari( $idler );

	/* Search Console tiklamalari — tek sorguda */
	$tiklar = array();
	if ( function_exists( 'gbc_seo_kaynak' ) ) {
		$k = gbc_seo_kaynak();
		if ( ! empty( $k['var'] ) ) {
			global $wpdb;
			$yollar = array();
			foreach ( $q->posts as $p ) {
				$yol = wp_parse_url( get_permalink( $p->ID ), PHP_URL_PATH );
				if ( $yol ) { $yollar[ $yol ] = (int) $p->ID; }
			}
			if ( $yollar ) {
				$ph = implode( ',', array_fill( 0, count( $yollar ), '%s' ) );
				$hazir = $wpdb->prepare(
					"SELECT page, SUM(clicks) t, SUM(impressions) g, AVG(position) s
					   FROM `{$k['tablo']}` WHERE created >= %s AND page IN ({$ph})
					  GROUP BY page",
					array_merge( array( gbc_seo_sinir( 90 ) ), array_keys( $yollar ) )
				);
				foreach ( (array) $wpdb->get_results( $hazir, ARRAY_A ) as $r ) {
					if ( isset( $yollar[ $r['page'] ] ) ) {
						$tiklar[ $yollar[ $r['page'] ] ] = $r;
					}
				}
			}
		}
	}

	$satirlar = array();
	foreach ( $q->posts as $p ) {
		$meta = get_post_meta( $p->ID, '_gbc_seo', true );
		$kat  = array();
		foreach ( (array) get_the_category( $p->ID ) as $c ) { $kat[] = $c->name; }

		$satirlar[] = array(
			'id'       => (int) $p->ID,
			'baslik'   => $p->post_title,
			'yol'      => wp_parse_url( get_permalink( $p->ID ), PHP_URL_PATH ),
			'silo'     => $kat ? implode( ' › ', array_slice( $kat, 0, 2 ) ) : '—',
			'sablon'   => gbc_env_sablon( $p->post_content ),
			'skor'     => is_array( $meta ) && isset( $meta['skor'] ) ? (int) $meta['skor'] : null,
			'sema'     => is_array( $meta ) && ! empty( $meta['sema'] ) ? count( (array) $meta['sema'] ) : 0,
			'alt_yok'  => is_array( $meta ) && isset( $meta['alt_yok'] ) ? (int) $meta['alt_yok'] : null,
			'ortaklik' => is_array( $meta ) && ! empty( $meta['ortaklik'] ) ? array_sum( (array) $meta['ortaklik'] ) : 0,
			'gelen'    => isset( $baglar[ $p->ID ]['gelen'] ) ? (int) $baglar[ $p->ID ]['gelen'] : 0,
			'giden'    => isset( $baglar[ $p->ID ]['giden'] ) ? (int) $baglar[ $p->ID ]['giden'] : 0,
			'tik'      => isset( $tiklar[ $p->ID ] ) ? (int) $tiklar[ $p->ID ]['t'] : null,
			'gos'      => isset( $tiklar[ $p->ID ] ) ? (int) $tiklar[ $p->ID ]['g'] : null,
			'sira'     => isset( $tiklar[ $p->ID ] ) ? (float) $tiklar[ $p->ID ]['s'] : null,
		);
	}

	return array( 'satir' => $satirlar, 'toplam' => (int) $q->found_posts, 'sayfa_adet' => (int) $q->max_num_pages );
}

/* ============================================================
   EKRAN — Envanter
   ============================================================ */
function gbc_env_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	/* v1.43.0: birleşik Envanter (plan, öncelik, toplama) varsa o basılır. */
	if ( function_exists( 'gbc_eb_ekran' ) ) { gbc_eb_ekran(); return; }

	$sekme = isset( $_GET['sekme'] ) ? sanitize_key( $_GET['sekme'] ) : 'liste';
	$kat   = isset( $_GET['kat'] ) ? (int) $_GET['kat'] : 0;
	$ara   = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';
	$sf    = isset( $_GET['sf'] ) ? max( 1, (int) $_GET['sf'] ) : 1;

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Envanter', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo-envanter' ); }

	$sekmeler = array(
		'liste'  => __( 'Bütün içerik', 'gbc-core' ),
		'silo'   => __( 'Silo ağacı', 'gbc-core' ),
		'yetim'  => __( 'Yetim sayfalar', 'gbc-core' ),
	);
	echo '<p>';
	foreach ( $sekmeler as $s => $ad ) {
		echo '<a class="button ' . ( $sekme === $s ? 'button-primary' : '' ) . '" style="margin-right:6px" href="'
			. esc_url( admin_url( 'admin.php?page=gbc-seo-envanter&sekme=' . $s ) ) . '">' . esc_html( $ad ) . '</a>';
	}
	echo '</p>';

	if ( 'silo' === $sekme )      { gbc_env_silo_ekran(); }
	elseif ( 'yetim' === $sekme ) { gbc_env_yetim_ekran(); }
	else                          { gbc_env_liste_ekran( $kat, $ara, $sf ); }

	echo '</div>';
}

function gbc_env_liste_ekran( $kat, $ara, $sf ) {
	/* Suzgec */
	echo '<form method="get" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
	echo '<input type="hidden" name="page" value="gbc-seo-envanter">';
	echo '<label for="gbc_ara" style="font-weight:600">' . esc_html__( 'Ara', 'gbc-core' ) . '</label>';
	echo '<input type="search" id="gbc_ara" name="ara" value="' . esc_attr( $ara ) . '" style="width:200px">';
	echo '<label for="gbc_kat" style="font-weight:600">' . esc_html__( 'Silo', 'gbc-core' ) . '</label>';
	echo '<select id="gbc_kat" name="kat"><option value="0">' . esc_html__( 'Hepsi', 'gbc-core' ) . '</option>';
	foreach ( (array) get_categories( array( 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ) ) as $c ) {
		echo '<option value="' . (int) $c->term_id . '"' . selected( $kat, $c->term_id, false ) . '>'
			. esc_html( ( $c->parent ? '— ' : '' ) . $c->name . ' (' . (int) $c->count . ')' ) . '</option>';
	}
	echo '</select>';
	echo ' <button class="button button-primary">' . esc_html__( 'Süz', 'gbc-core' ) . '</button>';
	echo '</form>';

	$d = gbc_env_liste( array( 'kategori' => $kat, 'ara' => $ara, 'sayfa' => $sf ) );

	echo '<p style="color:#5C6470">' . esc_html( sprintf( __( 'Toplam %1$s içerik · sayfa %2$d / %3$d', 'gbc-core' ),
		number_format_i18n( $d['toplam'] ), $sf, max( 1, $d['sayfa_adet'] ) ) ) . '</p>';

	echo '<table class="widefat striped" style="max-width:1400px"><thead><tr>'
		. '<th>' . esc_html__( 'İçerik', 'gbc-core' ) . '</th>'
		. '<th style="width:170px">' . esc_html__( 'Silo', 'gbc-core' ) . '</th>'
		. '<th style="width:80px">' . esc_html__( 'Şablon', 'gbc-core' ) . '</th>'
		. '<th style="width:70px">' . esc_html__( 'Skor', 'gbc-core' ) . '</th>'
		. '<th style="width:74px">' . esc_html__( 'Tıklama', 'gbc-core' ) . '</th>'
		. '<th style="width:74px">' . esc_html__( 'Sıra', 'gbc-core' ) . '</th>'
		. '<th style="width:86px">' . esc_html__( 'Bağ (g/ç)', 'gbc-core' ) . '</th>'
		. '<th style="width:70px">' . esc_html__( 'Şema', 'gbc-core' ) . '</th>'
		. '<th style="width:80px">' . esc_html__( 'Ortaklık', 'gbc-core' ) . '</th>'
		. '<th style="width:70px"></th></tr></thead><tbody>';

	foreach ( $d['satir'] as $r ) {
		$renk = ( null === $r['skor'] ) ? '#5C6470' : ( $r['skor'] >= 80 ? '#1A7F37' : ( $r['skor'] >= 60 ? '#8A6100' : '#B3261E' ) );
		echo '<tr>';
		echo '<td><strong>' . esc_html( $r['baslik'] ) . '</strong><div style="color:#5C6470;font-size:12px">' . esc_html( $r['yol'] ) . '</div></td>';
		echo '<td style="font-size:12.5px">' . esc_html( $r['silo'] ) . '</td>';
		echo '<td>' . esc_html( $r['sablon'] ) . '</td>';
		echo '<td>' . ( null === $r['skor'] ? '<span style="color:#5C6470">—</span>'
			: '<strong style="color:' . esc_attr( $renk ) . '">%' . (int) $r['skor'] . '</strong>' ) . '</td>';
		echo '<td>' . ( null === $r['tik'] ? '<span style="color:#5C6470">—</span>' : esc_html( number_format_i18n( $r['tik'] ) ) ) . '</td>';
		echo '<td>' . ( null === $r['sira'] ? '<span style="color:#5C6470">—</span>' : esc_html( number_format_i18n( $r['sira'], 1 ) ) ) . '</td>';
		echo '<td>' . ( $r['gelen'] ? (int) $r['gelen'] : '<span style="color:#B3261E;font-weight:700">0</span>' )
			. ' / ' . (int) $r['giden'] . '</td>';
		echo '<td>' . ( $r['sema'] ? (int) $r['sema'] : '<span style="color:#B3261E;font-weight:600">' . esc_html__( 'yok', 'gbc-core' ) . '</span>' ) . '</td>';
		echo '<td>' . ( $r['ortaklik'] ? (int) $r['ortaklik'] : '<span style="color:#8A6100">0</span>' ) . '</td>';
		echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $r['id'] ) ) . '">' . esc_html__( 'denetle', 'gbc-core' ) . '</a></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	/* Sayfalama */
	$temel = admin_url( 'admin.php?page=gbc-seo-envanter&kat=' . (int) $kat . '&ara=' . rawurlencode( $ara ) );
	echo '<p style="margin-top:14px">';
	if ( $sf > 1 ) {
		echo '<a class="button" href="' . esc_url( $temel . '&sf=' . ( $sf - 1 ) ) . '">' . esc_html__( '← Önceki', 'gbc-core' ) . '</a> ';
	}
	if ( $sf < $d['sayfa_adet'] ) {
		echo '<a class="button" href="' . esc_url( $temel . '&sf=' . ( $sf + 1 ) ) . '">' . esc_html__( 'Sonraki →', 'gbc-core' ) . '</a>';
	}
	echo '</p>';

	echo '<p style="color:#5C6470;font-size:12.5px;max-width:1000px">'
		. esc_html__( 'Skor kolonu boşsa o sayfa henüz denetlenmemiştir — SEO Toplama saat başı ilerliyor. Bağ (g/ç): sitenin kendi içinden aldığı ve verdiği bağlantı sayısı; 0 alan bir sayfa yetimdir.', 'gbc-core' )
		. '</p>';
}

/* --- Silo ağacı --- */
function gbc_env_silo_ekran() {
	$k = gbc_env_bag_kaynak();

	echo '<h2>' . esc_html__( 'Kategori silosu', 'gbc-core' ) . '</h2>';
	echo '<p style="color:#5C6470;max-width:1000px">'
		. esc_html__( 'Dikey yapı: ülke hub’ı → şehir rehberi → konu sayfaları. Bir konu sayfası doğrudan ülkeye değil, önce şehrine bağlanmalı.', 'gbc-core' ) . '</p>';

	$kats = (array) get_categories( array( 'hide_empty' => false ) );
	$agac = array();
	foreach ( $kats as $c ) { $agac[ (int) $c->parent ][] = $c; }

	$yaz = function ( $ust, $derinlik ) use ( &$yaz, $agac ) {
		if ( empty( $agac[ $ust ] ) ) { return; }
		foreach ( $agac[ $ust ] as $c ) {
			$renk = 0 === $derinlik ? '#17181A' : ( 1 === $derinlik ? '#3C4149' : '#5C6470' );
			echo '<div style="padding:7px 0 7px ' . ( $derinlik * 26 ) . 'px;border-bottom:1px solid #F1EDE6;display:flex;align-items:center;gap:10px">';
			if ( $derinlik ) { echo '<span style="color:#C9CBD0">└</span>'; }
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-envanter&kat=' . (int) $c->term_id ) ) . '" '
				. 'style="font-weight:' . ( $derinlik ? '500' : '700' ) . ';color:' . esc_attr( $renk ) . ';text-decoration:none;font-size:' . ( 0 === $derinlik ? '15px' : '14px' ) . '">'
				. esc_html( $c->name ) . '</a>';
			echo '<span style="background:#F3F0E9;border-radius:999px;padding:2px 9px;font-size:12px;color:#3C4149">'
				. esc_html( number_format_i18n( (int) $c->count ) ) . '</span>';
			echo '</div>';
			$yaz( (int) $c->term_id, $derinlik + 1 );
		}
	};

	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:10px 18px;max-width:900px">';
	$yaz( 0, 0 );
	echo '</div>';

	/* Bir hub'a gercekte neler bagli */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Bir sayfaya gerçekte neler bağlı', 'gbc-core' ) . '</h2>';
	if ( empty( $k['var'] ) ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $k['not'] ) . '</p></div>';
		return;
	}

	$pid = isset( $_GET['pid'] ) ? (int) $_GET['pid'] : 0;
	echo '<form method="get" style="margin:12px 0">';
	echo '<input type="hidden" name="page" value="gbc-seo-envanter"><input type="hidden" name="sekme" value="silo">';
	echo '<label for="gbc_silo_pid" style="font-weight:600;margin-right:6px">' . esc_html__( 'Sayfa numarası', 'gbc-core' ) . '</label>';
	echo '<input type="number" id="gbc_silo_pid" name="pid" value="' . esc_attr( $pid ? $pid : '' ) . '" style="width:120px"> ';
	echo '<button class="button button-primary">' . esc_html__( 'Bağlantılarını göster', 'gbc-core' ) . '</button>';
	echo '</form>';

	if ( ! $pid ) { return; }

	$gelen = gbc_env_gelen( $pid );
	$giden = gbc_env_giden( $pid );

	echo '<h3>' . esc_html( get_the_title( $pid ) ) . '</h3>';
	echo '<div style="display:flex;gap:16px;flex-wrap:wrap">';

	echo '<div style="flex:1;min-width:380px;background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px">';
	echo '<div style="font-weight:600;margin-bottom:8px;color:#0B5B55">' . esc_html( sprintf( __( 'Bu sayfaya bağ verenler (%d)', 'gbc-core' ), count( $gelen ) ) ) . '</div>';
	if ( ! $gelen ) {
		echo '<div style="color:#B3261E;font-weight:600">' . esc_html__( 'Hiç iç bağ almıyor — yetim sayfa.', 'gbc-core' ) . '</div>';
	}
	foreach ( $gelen as $g ) {
		echo '<div style="padding:4px 0;font-size:13.5px">· <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $g['post_id'] ) ) . '">'
			. esc_html( get_the_title( (int) $g['post_id'] ) ) . '</a></div>';
	}
	echo '</div>';

	echo '<div style="flex:1;min-width:380px;background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px">';
	echo '<div style="font-weight:600;margin-bottom:8px;color:#A8380F">' . esc_html( sprintf( __( 'Bu sayfanın bağ verdikleri (%d)', 'gbc-core' ), count( $giden ) ) ) . '</div>';
	foreach ( $giden as $g ) {
		echo '<div style="padding:4px 0;font-size:13.5px">· <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $g['target_post_id'] ) ) . '">'
			. esc_html( get_the_title( (int) $g['target_post_id'] ) ) . '</a></div>';
	}
	if ( ! $giden ) { echo '<div style="color:#8A6100">' . esc_html__( 'Hiçbir sayfaya bağ vermiyor.', 'gbc-core' ) . '</div>'; }
	echo '</div>';

	echo '</div>';
}

/* --- Yetimler --- */
function gbc_env_yetim_ekran() {
	$y = gbc_env_yetimler( 100 );
	echo '<h2>' . esc_html__( 'Yetim sayfalar', 'gbc-core' ) . '</h2>';
	echo '<p style="color:#5C6470;max-width:1000px">'
		. esc_html__( 'Siteden hiç iç bağ almayan içerikler. Google bunları zor bulur, bulduğunda da önemsiz sayar. Her biri silosundaki bir üst sayfadan bağ almalı.', 'gbc-core' ) . '</p>';

	if ( null === $y ) {
		$k = gbc_env_bag_kaynak();
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $k['not'] ) . '</p></div>';
		return;
	}
	if ( ! $y ) {
		echo '<p style="color:#1A7F37;font-weight:600">' . esc_html__( 'Yetim sayfa yok.', 'gbc-core' ) . '</p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
		. '<th>' . esc_html__( 'İçerik', 'gbc-core' ) . '</th>'
		. '<th style="width:110px">' . esc_html__( 'Tür', 'gbc-core' ) . '</th>'
		. '<th style="width:90px"></th></tr></thead><tbody>';
	foreach ( $y as $p ) {
		echo '<tr><td><strong>' . esc_html( $p['post_title'] ) . '</strong>'
			. '<div style="color:#5C6470;font-size:12px">' . esc_html( wp_parse_url( get_permalink( (int) $p['ID'] ), PHP_URL_PATH ) ) . '</div></td>';
		echo '<td>' . esc_html( $p['post_type'] ) . '</td>';
		echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $p['ID'] ) ) . '">' . esc_html__( 'denetle', 'gbc-core' ) . '</a></td></tr>';
	}
	echo '</tbody></table>';
}

/* ============================================================
   EKRAN — Kaynaklar (API'ler)
   ============================================================ */
function gbc_kaynak_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Kaynaklar', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-kaynak' ); }
	echo '<p style="max-width:960px;color:#444">'
		. esc_html__( 'Hangi veri kaynağı gerçekten bağlı, hangisi değil. Burada yazan her satır canlı kontrol sonucudur — bağlı olmayan bir kaynak “kullanılıyor” diye gösterilmez.', 'gbc-core' )
		. '</p>';

	global $wpdb;
	$satir = array();

	/* Search Console — Rank Math tablosu */
	$k = function_exists( 'gbc_seo_kaynak' ) ? gbc_seo_kaynak() : array( 'var' => false, 'not' => '' );
	$satir[] = array(
		'ad'      => 'Google Search Console',
		'nasil'   => __( 'Rank Math’in Google bağlantısı', 'gbc-core' ),
		'ne'      => __( 'Her sayfanın kelimeleri, sırası, tıklaması, gösterimi', 'gbc-core' ),
		'durum'   => ! empty( $k['var'] ),
		'not'     => ! empty( $k['var'] ) ? __( 'Tablo okunuyor', 'gbc-core' ) : ( isset( $k['not'] ) ? $k['not'] : '' ),
		'kullanan'=> 'SEO · Fırsatlar · Sayfa Denetimi · Envanter',
	);

	/* Ic baglanti sayaci */
	$b = gbc_env_bag_kaynak();
	$satir[] = array(
		'ad'      => __( 'Rank Math bağlantı sayacı', 'gbc-core' ),
		'nasil'   => __( 'Site içi tablo', 'gbc-core' ),
		'ne'      => __( 'İç bağlantı grafiği — kim kime bağ veriyor, yetimler', 'gbc-core' ),
		'durum'   => ! empty( $b['var'] ),
		'not'     => ! empty( $b['var'] ) ? __( 'Tablo okunuyor', 'gbc-core' ) : ( isset( $b['not'] ) ? $b['not'] : '' ),
		'kullanan'=> 'Envanter · Silo ağacı',
	);

	/* PageSpeed */
	$psi = function_exists( 'gbc_hz_ayar' ) ? gbc_hz_ayar( 'psi_anahtar' ) : '';
	$psi_test = get_option( 'gbc_hz_psi_test', array() );
	$satir[] = array(
		'ad'      => 'Google PageSpeed Insights',
		'nasil'   => __( 'API anahtarı (Hız ekranında)', 'gbc-core' ),
		'ne'      => __( 'Mobil ve masaüstü hız puanı, LCP, CLS', 'gbc-core' ),
		'durum'   => ( '' !== $psi ),
		'not'     => ( '' === $psi ) ? __( 'Anahtar girilmemiş', 'gbc-core' )
			: ( ( is_array( $psi_test ) && empty( $psi_test['hata'] ) && ! empty( $psi_test['zaman'] ) )
				? __( 'Anahtar çalışıyor', 'gbc-core' )
				: ( ( is_array( $psi_test ) && ! empty( $psi_test['hata'] ) ) ? sprintf( __( 'Son test: %s', 'gbc-core' ), $psi_test['hata'] ) : __( 'Anahtar kayıtlı, henüz test edilmedi', 'gbc-core' ) ) ),
		'kullanan'=> 'Hız & Sağlık',
	);

	/* GA4 — olcum kodu */
	$ga = function_exists( 'gbc_ga4_denetim' ) ? gbc_ga4_denetim() : array( 'eksik' => array( 'x' => 1 ), 'var' => array() );
	$ga_ok = empty( $ga['eksik'] );
	$satir[] = array(
		'ad'      => 'Google Analytics 4 (ölçüm kodu)',
		'nasil'   => __( 'Sitedeki gtag betiği — modül 30', 'gbc-core' ),
		'ne'      => __( '5 olay, 8 parametre: ortaklık tıklaması, kaydırma, dış bağlantı', 'gbc-core' ),
		'durum'   => $ga_ok,
		'not'     => $ga_ok ? __( 'Hepsi sayfada basılıyor', 'gbc-core' )
			: sprintf( __( '%d eksik — Günlük Kontrol ekranında listeli', 'gbc-core' ), count( $ga['eksik'] ) ),
		'kullanan'=> 'Günlük Kontrol',
	);

	/* GA4 raporlama — Komuta servis hesabi */
	$sa = function_exists( 'gbc_km_ayar' ) ? gbc_km_ayar( 'google_sa', '' ) : '';
	$satir[] = array(
		'ad'      => 'GA4 raporlama (veri çekme)',
		'nasil'   => __( 'Google servis hesabı — SEO · Google Ayarları', 'gbc-core' ),
		'ne'      => __( 'Oturum, sayfada kalma, dönüşüm — sayfa bazında', 'gbc-core' ),
		'durum'   => ( '' !== (string) $sa ),
		'not'     => ( '' !== (string) $sa ) ? __( 'Servis hesabı kayıtlı — “Google Testi” ile doğrula', 'gbc-core' )
			: __( 'Servis hesabı girilmemiş; GA4 raporu çekilemiyor', 'gbc-core' ),
		'kullanan'=> 'SEO · GA4 Veri Çek',
	);

	/* Ortaklik tiklama sayaci */
	$aff = get_option( 'gbc_aff_tik', array() );
	$satir[] = array(
		'ad'      => __( 'GBC ortaklık tıklama sayacı', 'gbc-core' ),
		'nasil'   => __( 'Sitenin kendi sayacı — modül 70', 'gbc-core' ),
		'ne'      => __( 'Hangi ortaklık bağlantısına kaç kez tıklandı', 'gbc-core' ),
		'durum'   => ! empty( $aff ),
		'not'     => ! empty( $aff ) ? sprintf( __( '%d kayıt', 'gbc-core' ), is_array( $aff ) ? count( $aff ) : 0 )
			: __( 'Henüz kayıt yok', 'gbc-core' ),
		'kullanan'=> __( 'İş Ortaklığı ekranı', 'gbc-core' ),
	);

	/* Ubersuggest */
	$satir[] = array(
		'ad'      => 'Ubersuggest',
		'nasil'   => __( 'Eklentiden bağlanmıyor', 'gbc-core' ),
		'ne'      => __( 'Kelime varyantları, arama hacmi, zorluk, tıklama değeri', 'gbc-core' ),
		'durum'   => false,
		'not'     => __( 'Ayrı API satılıyor. Şimdilik veriyi Claude çekip panele yazıyor.', 'gbc-core' ),
		'kullanan'=> __( 'Kelime havuzu (elle)', 'gbc-core' ),
	);

	echo '<table class="widefat striped" style="max-width:1300px"><thead><tr>'
		. '<th style="width:220px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th>'
		. '<th style="width:110px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Ne veriyor', 'gbc-core' ) . '</th>'
		. '<th style="width:230px">' . esc_html__( 'Nasıl bağlı', 'gbc-core' ) . '</th>'
		. '<th style="width:220px">' . esc_html__( 'Kullanan ekran', 'gbc-core' ) . '</th>'
		. '</tr></thead><tbody>';
	foreach ( $satir as $s ) {
		echo '<tr>';
		echo '<td><strong>' . esc_html( $s['ad'] ) . '</strong></td>';
		echo '<td>' . ( $s['durum']
			? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'BAĞLI', 'gbc-core' ) . '</span>'
			: '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'YOK', 'gbc-core' ) . '</span>' )
			. '<div style="color:#5C6470;font-size:12px">' . esc_html( $s['not'] ) . '</div></td>';
		echo '<td>' . esc_html( $s['ne'] ) . '</td>';
		echo '<td style="font-size:12.5px;color:#3C4149">' . esc_html( $s['nasil'] ) . '</td>';
		echo '<td style="font-size:12.5px;color:#3C4149">' . esc_html( $s['kullanan'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '</div>';
}
