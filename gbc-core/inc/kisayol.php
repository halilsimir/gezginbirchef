<?php
/**
 * GBC Core · Sayfa Denetimi kısayolu (v1.47.3, 30 Eylül 2026)
 *
 * Halil: "Edit'e girdiğimde bir düğmeyle hızlıca sayfa denetimine geçeyim."
 * Üç yerde tek tıkla Sayfa Denetimi (admin.php?page=gbc-seo-sayfa&pid=ID):
 *   1. Düzenleme ekranının sağ sütununda en üstte kutu: GBC skoru + düğme.
 *   2. Üst siyah çubukta "GBC %97" bağlantısı (düzenleme ekranında ve sitede sayfaya bakarken).
 *   3. Yazılar/Sayfalar listesinde satırın altında "Sayfa Denetimi" bağlantısı.
 * Yalnız yöneticiye görünür. Hiçbir ölçüm yapmaz; son kayıtlı skoru (_gbc_seo) okur, sayfayı yavaşlatmaz.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gbc_ky_url( $pid ) {
	return admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $pid );
}

/** Son kayıtlı skor: array( skor, zaman ) ya da null. */
function gbc_ky_skor( $pid ) {
	$s = get_post_meta( (int) $pid, '_gbc_seo', true );
	if ( ! is_array( $s ) || ! isset( $s['skor'] ) ) { return null; }
	return array( (int) $s['skor'], isset( $s['zaman'] ) ? (int) $s['zaman'] : 0, isset( $s['yap1'] ) ? (int) $s['yap1'] : 0 );
}

function gbc_ky_renk( $skor ) {
	return $skor >= 90 ? '#1A7F37' : ( $skor >= 70 ? '#8A6100' : '#B32D2E' );
}

/* 1. Düzenleme ekranı: sağ sütunda en üstte kutu. */
add_action( 'add_meta_boxes', function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	foreach ( array( 'post', 'page' ) as $t ) {
		add_meta_box( 'gbc_ky_kutu', 'GBC Sayfa Denetimi', 'gbc_ky_kutu_ciz', $t, 'side', 'high' );
	}
} );

function gbc_ky_kutu_ciz( $post ) {
	$pid = (int) $post->ID;
	$s   = gbc_ky_skor( $pid );
	if ( $s ) {
		echo '<div style="display:flex;align-items:baseline;gap:8px;margin:2px 0 8px">'
			. '<strong style="font-size:26px;line-height:1;color:' . esc_attr( gbc_ky_renk( $s[0] ) ) . '">%' . (int) $s[0] . '</strong>'
			. '<span style="color:#5C6470;font-size:12px">' . esc_html( $s[1] ? sprintf( 'GBC skoru · %s önce', human_time_diff( $s[1] ) ) : 'GBC skoru' ) . '</span></div>';
		if ( $s[2] ) {
			echo '<p style="margin:0 0 8px;color:#B32D2E;font-size:12.5px">' . esc_html( sprintf( '%d acil iş var.', $s[2] ) ) . '</p>';
		}
	} elseif ( 'publish' === $post->post_status ) {
		echo '<p style="margin:0 0 8px;color:#5C6470;font-size:12.5px">Henüz ölçülmedi. Düğmeye basınca ölçülür.</p>';
	} else {
		echo '<p style="margin:0 0 8px;color:#5C6470;font-size:12.5px">Taslak. Yayına girince saatlik kontrolle ölçülür; şimdi de açabilirsin.</p>';
	}
	echo '<a class="button button-primary" style="width:100%;text-align:center" href="' . esc_url( gbc_ky_url( $pid ) ) . '">SEO detaylarını gör</a>';
}

/* 2. Üst çubuk: düzenleme ekranında ve sitede tek sayfaya bakarken. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$pid = 0;
	if ( is_admin() ) {
		$ekran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $ekran && 'post' === $ekran->base && isset( $_GET['post'] ) ) { $pid = (int) $_GET['post']; }
	} elseif ( is_singular( array( 'post', 'page' ) ) ) {
		$pid = (int) get_queried_object_id();
	}
	if ( ! $pid ) { return; }
	$s = gbc_ky_skor( $pid );
	$bar->add_node( array(
		'id'    => 'gbc-ky',
		'title' => $s ? 'GBC %' . (int) $s[0] : 'GBC Denetim',
		'href'  => gbc_ky_url( $pid ),
		'meta'  => array( 'title' => 'Sayfa Denetimi: SEO detaylarını gör' ),
	) );
}, 90 );

/* 3. Yazılar ve Sayfalar listesi: satır bağlantısı. */
function gbc_ky_satir( $islemler, $post ) {
	if ( current_user_can( 'manage_options' ) && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		$s = gbc_ky_skor( $post->ID );
		$islemler['gbc_ky'] = '<a href="' . esc_url( gbc_ky_url( $post->ID ) ) . '">' . esc_html( $s ? 'Sayfa Denetimi (%' . (int) $s[0] . ')' : 'Sayfa Denetimi' ) . '</a>';
	}
	return $islemler;
}
add_filter( 'post_row_actions', 'gbc_ky_satir', 10, 2 );
add_filter( 'page_row_actions', 'gbc_ky_satir', 10, 2 );
