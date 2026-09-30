<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Durum sayfası.
 * Hangi modül nereden çalışıyor: eklentiden mi, hâlâ WPCode'dan mı?
 */

add_action( 'admin_menu', 'gbc_core_menu', 9 );
function gbc_core_menu() {

	add_menu_page(
		'GBC',
		'GBC',
		'manage_options',
		'gbc',
		'gbc_core_ana_ekran',
		defined( 'GBC_CORE_IKON' ) ? GBC_CORE_IKON : 'dashicons-chart-area',
		3
	);

	/* Bütün alt sayfalar TEK yerden, mantıklı sırayla kaydedilir.
	   Modüller kendi menüsünü açmaz; böylece sıra tahmine kalmaz.
	   'kosul' => o ekranın fonksiyonu yoksa satır hiç eklenmez. */
	$sayfalar = array(
		/* v1.44.0: menüde yalnız grup başları görünür (inc/merkez.php); diğerleri sekme olarak açılır, adresleri aynı. */
		array( 'gbc',              'GBC Kontrol Paneli',            'Kontrol Paneli',       'gbc_core_ana_ekran' ),
		array( 'gbc-moduller',     'GBC Modüller',                  'Modüller',             'gbc_core_durum_ekran' ),
		array( 'gbc-eklentiler',   'GBC Eklentiler',                'Eklentiler',           'gbc_ek_ekran' ),
		array( 'gbc-seo-toplama',  'GBC SEO · Toplama',             'Toplama',              'gbc_seo_toplama_ekran' ),

		array( 'gbc-seo',          'GBC SEO',                       'SEO',                  'gbc_seo_ekran' ),
		array( 'gbc-seo-envanter', 'GBC SEO · Envanter',            '— Envanter',           'gbc_env_ekran' ),
		array( 'gbc-seo-firsat',   'GBC SEO · Fırsatlar',           '— Fırsatlar',          'gbc_seo_firsat_ekran' ),
		array( 'gbc-seo-sayfa',    'GBC SEO · Sayfa Denetimi',      '— Sayfa Denetimi',     'gbc_seo_sayfa_ekran' ),
		array( 'gbc-dizin',        'GBC SEO · Dizin Durumu',        '— Dizin Durumu',       'gbc_dz_ekran' ),
		/* v1.43.0: Toplama Envanter'in içinde (Özet sekmesi). Menüden kalktı. */
		array( 'gbc-api',          'GBC API Merkezi',               '— API Merkezi',        'gbc_api_ekran' ),
		array( 'gbc-kaynak',       'GBC SEO · Kaynak Özeti',        '— Kaynak Özeti',       'gbc_kaynak_ekran' ),
		/* v1.10.3: Google Testi'nin üç adımı da API Merkezi'ne taşındı — menüden kaldırıldı.
		array( 'gbc-komuta-test',  'GBC SEO · Google Testi',        '— Google Testi',       'gbc_km_ekran_test' ), */

		array( 'gbc-icerik',       'GBC İçerik Denetimi',           'İçerik Denetimi',      'gbc_ic_ekran' ),

		array( 'gbc-gunluk',       'GBC Günlük Kontrol',            'Günlük Kontrol',       'gbc_gunluk_ekran' ),
		array( 'gbc-nobetci-kural','GBC Kontrol Merkezi',           'Kontrol Merkezi',      'gbc_nk_ekran' ),
		array( 'gbc-guvenlik',     'GBC Güvenlik',                  'Güvenlik',             'gbc_gv_ekran' ),
		array( 'gbc-hiz',          'GBC Hız & Sağlık',              'Hız & Sağlık',         'gbc_hz_ekran' ),
		array( 'gbc-ortaklik',     'GBC İş Ortaklığı',              'İş Ortaklığı',         'gbc_ort_ekran' ),
		array( 'gbc-cron',         'GBC Zamanlanmış İşler',         'Zamanlanmış İşler',    'gbc_cron_ekran' ),
		array( 'gbc-defter',       'GBC Çalışma Dosyası',           'Çalışma Dosyası',      'gbc_defter_ekran' ),
	);

	foreach ( $sayfalar as $s ) {
		list( $slug, $baslik, $etiket, $geri ) = $s;
		if ( ! function_exists( $geri ) ) { continue; }
		add_submenu_page( 'gbc', $baslik, $etiket, 'manage_options', $slug, $geri );
	}
}

/** v1.44.0: GBC ana sayfası Kontrol Paneli'dir; eski modül durumu "Modüller" sekmesinde. */
function gbc_core_ana_ekran() {
	if ( function_exists( 'gbc_kp_ekran' ) ) { gbc_kp_ekran(); return; }
	gbc_core_durum_ekran();
}

/* Logonun WordPress'in kendi menu ikonlariyla ayni opaklikta durmasi icin. */
add_action( 'admin_head', 'gbc_core_menu_stili' );
function gbc_core_menu_stili() {
	/* WordPress data:image/svg+xml ikonlarini arka plan olarak basar ve
	   .wp-menu-image.svg sinifini kendisi ekler; opakligi da kendi yonetir.
	   Burada yalniz olcuyu netlestiriyoruz. */
	echo '<style id="gbc-core-menu">'
		. '#adminmenu #toplevel_page_gbc .wp-menu-image.svg{background-size:20px auto}'
		. '</style>';
}

/* Aç/kapa bağlantısı. */
add_action( 'admin_init', 'gbc_core_salter' );
function gbc_core_salter() {
	if ( ! isset( $_GET['gbc_core_islem'], $_GET['gbc_core_modul'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$modul = sanitize_file_name( wp_unslash( $_GET['gbc_core_modul'] ) );
	$islem = sanitize_key( wp_unslash( $_GET['gbc_core_islem'] ) );
	check_admin_referer( 'gbc_core_salter_' . $modul );

	$kapali = get_option( 'gbc_core_kapali', array() );
	if ( ! is_array( $kapali ) ) {
		$kapali = array();
	}
	if ( 'kapat' === $islem ) {
		$kapali[ $modul ] = 1;
	} else {
		unset( $kapali[ $modul ] );
	}
	update_option( 'gbc_core_kapali', $kapali );

	wp_safe_redirect( admin_url( 'admin.php?page=gbc-moduller&gbc_core_ok=1' ) );
	exit;
}

function gbc_core_rozet( $durum ) {
	$harita = array(
		'eklenti'   => array( 'Eklentiden çalışıyor', '#1a7f37', '#e6f4ea' ),
		'wpcode'    => array( 'Hâlâ WPCode\'da',      '#1d4ed8', '#e6ecfb' ),
		'kapali'    => array( 'Kapalı',               '#646970', '#f0f0f1' ),
		'dosya-yok' => array( 'Dosya yok',            '#b32d2e', '#fcebea' ),
		'wpcode-acik' => array( 'WPCode kopyası açık', '#8a6100', '#fcf3e1' ),
	);
	$v = isset( $harita[ $durum ] ) ? $harita[ $durum ] : array( $durum, '#646970', '#f0f0f1' );
	return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;color:'
		. esc_attr( $v[1] ) . ';background:' . esc_attr( $v[2] ) . '">' . esc_html( $v[0] ) . '</span>';
}

function gbc_core_durum_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$durumlar = isset( $GLOBALS['gbc_core_durum'] ) && is_array( $GLOBALS['gbc_core_durum'] )
		? $GLOBALS['gbc_core_durum']
		: array();

	$sayac = array( 'eklenti' => 0, 'wpcode' => 0, 'kapali' => 0, 'dosya-yok' => 0, 'wpcode-acik' => 0 );

	echo '<div class="wrap"><h1>GBC Core — Modüller</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-moduller' ); }

	if ( isset( $_GET['gbc_core_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Kaydedildi. Değişikliğin sitede görünmesi için LiteSpeed önbelleğini temizleyin.</p></div>';
	}

	$satirlar = '';
	foreach ( gbc_core_moduller() as $m ) {
		$a  = $m['dosya'];
		$d  = isset( $durumlar[ $a ] ) ? $durumlar[ $a ]['durum'] : 'dosya-yok';
		$nt = isset( $durumlar[ $a ] ) ? $durumlar[ $a ]['not'] : 'Modül hiç işlenmedi.';
		if ( isset( $sayac[ $d ] ) ) {
			$sayac[ $d ]++;
		}

		$islem = ( 'kapali' === $d ) ? 'ac' : 'kapat';
		$etiket = ( 'kapali' === $d ) ? 'Aç' : 'Kapat';
		$url = wp_nonce_url(
			admin_url( 'admin.php?page=gbc-moduller&gbc_core_islem=' . $islem . '&gbc_core_modul=' . rawurlencode( $a ) ),
			'gbc_core_salter_' . $a
		);

		$satirlar .= '<tr>'
			. '<td><strong>' . esc_html( $m['ad'] ) . '</strong><br><code>' . esc_html( $a ) . '</code></td>'
			. '<td><code>' . (int) $m['kaynak'] . '</code></td>'
			. '<td>' . gbc_core_rozet( $d ) . '</td>'
			. '<td>' . ( $nt !== '' ? esc_html( $nt ) : '&mdash;' ) . '</td>'
			. '<td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $etiket ) . '</a></td>'
			. '</tr>';
	}

	echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:16px 0">';
	echo '<div style="background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:10px 18px;min-width:150px"><strong style="display:block;font-size:22px;line-height:1.2">' . (int) $sayac['eklenti'] . '</strong><span style="font-size:12px;color:#666">eklentiden çalışıyor</span></div>';
	echo '<div style="background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:10px 18px;min-width:150px"><strong style="display:block;font-size:22px;line-height:1.2">' . (int) $sayac['wpcode'] . '</strong><span style="font-size:12px;color:#666">hâlâ WPCode\'da</span></div>';
	echo '<div style="background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:10px 18px;min-width:150px"><strong style="display:block;font-size:22px;line-height:1.2">' . (int) $sayac['kapali'] . '</strong><span style="font-size:12px;color:#666">kapalı</span></div>';
	if ( $sayac['dosya-yok'] > 0 ) {
		echo '<div style="background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:10px 18px;min-width:150px"><strong style="display:block;font-size:22px;line-height:1.2;color:#b32d2e">' . (int) $sayac['dosya-yok'] . '</strong><span style="font-size:12px;color:#666">dosyası yok</span></div>';
	}
	echo '</div>';

	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
		. '<th>Modül</th><th>Kaynak snippet</th><th>Durum</th><th>Not</th><th></th>'
		. '</tr></thead><tbody>' . $satirlar . '</tbody></table>';

	echo '<p style="margin-top:18px;max-width:900px;color:#444"><strong>Mavi = o modül hâlâ WPCode\'da çalışıyor. WPCode\'daki snippet\'i pasife alırsan eklenti sürümü devreye girer.</strong><br>'
		. 'Sarı = modülün imzası yok (kodun tamamı isimsiz fonksiyon), o yüzden çakışma çalışırken anlaşılamıyor. '
		. 'Güvenli tarafta kalmak için WPCode kopyası yayındayken modül yüklenmiyor; kopyayı pasife alınca kendiliğinden devreye giriyor.</p>';

	/* WPCode\'da hâlâ yayında olan kopyaların listesi. */
	$kapatilacak = array();
	foreach ( gbc_core_moduller() as $m ) {
		foreach ( (array) ( isset( $m['kapat'] ) ? $m['kapat'] : array() ) as $sid ) {
			$sid = (int) $sid;
			if ( 'publish' !== get_post_status( $sid ) ) {
				continue;
			}
			$kapatilacak[ $sid ] = get_the_title( $sid );
		}
	}
	if ( $kapatilacak ) {
		echo '<h2 style="margin-top:28px">WPCode\'da pasife alınacaklar</h2>';
		echo '<p style="max-width:900px;color:#444">Bu snippet\'lerin kodu artık eklentinin içinde. WPCode\'da yayında kaldıkları sürece '
			. 'aynı iş iki yerde duruyor. Tek tek pasife al (silme — eklenti bir sorun çıkarırsa geri dönecek yer lazım).</p>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:90px">Snippet</th><th>Başlık</th></tr></thead><tbody>';
		foreach ( $kapatilacak as $sid => $baslik ) {
			echo '<tr><td><code>' . (int) $sid . '</code></td><td>' . esc_html( $baslik ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p style="margin-top:28px;color:#1a7f37"><strong>WPCode\'da pasife alınacak snippet kalmadı.</strong></p>';
	}

	echo '</div>';
}
