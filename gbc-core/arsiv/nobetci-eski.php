<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Nöbetçi.
 *
 * NE YAPAR
 *   Sitenin örnek sayfalarını gerçekten indirir ve her motorun kendi
 *   çıktısını (parmak izini) HTML içinde arar. "Aktif görünüyor" demez;
 *   "sayfada var / yok" der. Ölçüm, tahmin değil.
 *
 * İKİ KATMAN
 *   1) Bilinen motorlar: her modülün bastığı id/sınıf adı burada kayıtlı.
 *   2) Otomatik taban: sayfadaki BÜTÜN gbc-* / gz-* id ve sınıf adları
 *      toplanır. Dün duran bir ad bugün yoksa, o motoru hiç tanımıyor
 *      olsak bile alarm verir.
 *
 * GÜNLÜK KONTROL
 *   wp-cron ile günde bir kez tarar. Çalışan bir motor durursa yönetici
 *   ekranına uyarı düşer ve e-posta gider.
 */

define( 'GBC_NB_SON',   'gbc_nb_son' );    /* son tarama sonucu   */
define( 'GBC_NB_TABAN', 'gbc_nb_taban' );  /* bilinen iyi durum   */
define( 'GBC_NB_URL',   'gbc_nb_url' );    /* ornek sayfa listesi */

/* ============================================================
   1. MOTOR KAYDI
   iz      : sayfada aranacak metin (motorun kendi bastigi id/sinif)
   kapsam  : hepsi = her sayfada olmali · bazi = yalniz ilgili sayfalarda
   ters    : true ise IZ BULUNMAMASI dogru demektir
   ============================================================ */
function gbc_nb_motorlar() {
	return array(
		array( 'ad' => 'GA4 ölçüm',          'iz' => 'id="gbc-ga4-boot"',   'kapsam' => 'hepsi', 'kaynak' => 31363 ),
		array( 'ad' => 'Tıklama motoru',     'iz' => 'id="gbc-olcum"',      'kapsam' => 'hepsi', 'kaynak' => 31363 ),
		array( 'ad' => 'AdSense',            'iz' => 'gbc-ad-ins',          'kapsam' => 'bazi',  'kaynak' => 31363 ),
		array( 'ad' => 'Ortaklık bağlantısı','iz' => 'data-aff=',           'kapsam' => 'bazi',  'kaynak' => 31366 ),
		array( 'ad' => 'Ortaklık bildirimi', 'iz' => 'gbc-ortaklik-not',    'kapsam' => 'bazi',  'kaynak' => 31366 ),
		array( 'ad' => 'Ortaklık stili',     'iz' => '.gbc-yan-ad',         'kapsam' => 'hepsi', 'kaynak' => 31366, 'css' => true ),
		array( 'ad' => 'Ortaklık tık sayacı','iz' => 'id="gbc-aff-tik"',    'kapsam' => 'hepsi', 'kaynak' => 30126 ),
		array( 'ad' => 'Şema çekirdeği',     'iz' => 'BreadcrumbList',      'kapsam' => 'bazi',  'kaynak' => 29013 ),
		array( 'ad' => 'Şema motoru',        'iz' => '"@graph"',            'kapsam' => 'hepsi', 'kaynak' => 29738 ),
		array( 'ad' => 'Canlı kur',          'iz' => '.gbc-tl',             'kapsam' => 'hepsi', 'kaynak' => 30236, 'css' => true ),
		array( 'ad' => 'YouTube facade',     'iz' => 'gbc-yt-facade',       'kapsam' => 'hepsi', 'kaynak' => 28208 ),
		array( 'ad' => 'Emoji betiği YOK',   'iz' => 'wp-emoji-release',    'kapsam' => 'hepsi', 'ters' => true, 'kaynak' => 24462 ),
		array( 'ad' => 'Otel motoru',        'iz' => 'gbc-otel',            'kapsam' => 'bazi',  'kaynak' => 30242 ),
		array( 'ad' => 'Rota motoru',        'iz' => 'gbc-rota',            'kapsam' => 'bazi',  'kaynak' => 30696 ),
		array( 'ad' => 'Feribot bulucu',     'iz' => 'gbc-fb-veri',         'kapsam' => 'bazi',  'kaynak' => 30350 ),
		array( 'ad' => 'Yunanistan haritası','iz' => 'gbc-yn-veri',         'kapsam' => 'bazi',  'kaynak' => 30361 ),
		array( 'ad' => 'Bölüm medyası',      'iz' => 'gbc-sm-css',          'kapsam' => 'bazi',  'kaynak' => 29969 ),
		array( 'ad' => 'İlgili yazılar',     'iz' => 'gbc-related',         'kapsam' => 'bazi',  'kaynak' => 27100 ),
		array( 'ad' => 'Kart video oynatıcı','iz' => 'id="gbc-vp-css"',     'kapsam' => 'bazi',  'kaynak' => 31178 ),
		array( 'ad' => 'Şefe Sor',           'iz' => 'id="gbc-sor-js"',     'kapsam' => 'bazi',  'kaynak' => 31160 ),
		array( 'ad' => 'Silo iç linkleme',   'iz' => 'gbc-silo',            'kapsam' => 'bazi',  'kaynak' => 28213 ),
		array( 'ad' => 'Şablon CSS dosyaya', 'iz' => '',                   'kapsam' => 'hepsi', 'kaynak' => 30188, 'yerel' => 'gbc_nb_kontrol_sablon_css' ),
	);
}

/* ============================================================
   1b. SUNUCU TARAFI KONTROLLER

   Bazi motorlarin ciktisini sayfa HTML'inde aramak yanlis sonuc verir.
   Ornek: Sablon CSS motoru <head>'e bir <link> basar, ama LiteSpeed o
   link'i birlesik CSS dosyasina katip HTML'den siler. Link kaybolunca
   calisan motor "calismiyor" gorunur. 28 Eylul 2026'da tam olarak bu
   oldu ve uc motor bosuna alarm verdi.

   Cozum: bu motorlari sayfada degil, SUNUCUDA dogruluyoruz. Dosya
   gercekten yazilmis mi, klasor duruyor mu — kesin cevap bu.
   ============================================================ */
function gbc_nb_kontrol_sablon_css() {
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) {
		return array( false, 'uploads klasörü okunamadı' );
	}
	$dizin = trailingslashit( $up['basedir'] ) . 'gbc-css';
	if ( ! is_dir( $dizin ) ) {
		return array( false, 'uploads/gbc-css klasörü yok — motor hiç çalışmamış' );
	}
	$dosyalar = glob( $dizin . '/*.css' );
	if ( ! $dosyalar ) {
		return array( false, 'klasör var ama içi boş' );
	}
	$toplam = 0;
	$yeni   = 0;
	foreach ( $dosyalar as $d ) {
		$toplam += (int) filesize( $d );
		$yeni    = max( $yeni, (int) filemtime( $d ) );
	}
	return array( true, count( $dosyalar ) . ' dosya · ' . size_format( $toplam )
		. ' · en son ' . date_i18n( 'j M H:i', $yeni ) );
}

/* ============================================================
   2. ORNEK SAYFALAR
   Her sablondan birer tane + ana sayfa. Haftada bir yenilenir;
   boylece silinen bir sayfa yuzunden nobetci yanlis alarm vermez.
   ============================================================ */
function gbc_nb_sayfalar( $zorla = false ) {

	$kayit = get_option( GBC_NB_URL, array() );
	if ( ! $zorla && is_array( $kayit ) && ! empty( $kayit['liste'] )
	     && ( time() - (int) $kayit['zaman'] ) < WEEK_IN_SECONDS ) {
		return $kayit['liste'];
	}

	$liste = array( 'Ana sayfa' => home_url( '/' ) );

	/* Her sablondan bir ornek: sablon snippet id'si -> okunur ad */
	$sablon = array(
		22607 => 'Gezi rehberi',
		23108 => 'Liste rehberi',
		23489 => 'Detay',
		23340 => 'Rota',
		24751 => 'Tarif',
		26922 => 'Blog rehberi',
		24156 => 'Sözlük detay',
	);

	global $wpdb;
	foreach ( $sablon as $sid => $ad ) {
		$pid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_status = 'publish'
				   AND post_type IN ('post','page')
				   AND post_content LIKE %s
				 ORDER BY post_modified DESC LIMIT 1",
				'%[wpcode id="' . (int) $sid . '"%'
			)
		);
		if ( $pid ) {
			$liste[ $ad ] = get_permalink( (int) $pid );
		}
	}

	update_option( GBC_NB_URL, array( 'zaman' => time(), 'liste' => $liste ), false );
	return $liste;
}

/* ============================================================
   3. TARAMA
   ============================================================ */
function gbc_nb_izleri_topla( $html ) {
	/* Sayfadaki butun gbc-* / gz-* id ve sinif adlari. Otomatik taban. */
	$bulunan = array();
	if ( preg_match_all( '/\b(?:id|class)="([^"]*)"/i', $html, $m ) ) {
		foreach ( $m[1] as $blok ) {
			foreach ( preg_split( '/\s+/', $blok ) as $ad ) {
				if ( $ad !== '' && preg_match( '/^(gbc|gz|gz2|v1|tr|fb|yn)-[a-z0-9-]+$/i', $ad ) ) {
					$bulunan[ $ad ] = true;
				}
			}
		}
	}
	return array_keys( $bulunan );
}

/**
 * Sayfanin aranabilir govdesini genisletir.
 *
 * NEDEN GEREKLI (28 Eylul 2026'da olculdu): LiteSpeed iki sey yapiyor —
 *   a) satir ici JS'i  data:text/javascript;base64,...  adresine ceviriyor,
 *   b) satir ici <style> bloklarini ve <link> dosyalarini TEK birlesik
 *      CSS dosyasinda topluyor.
 * Ikisi de aranan izi HTML'den siliyor. Boyle olunca calisan bir motor
 * "calismiyor" gorunuyordu. Bu fonksiyon ikisini de geri aciyor:
 * base64 betikleri cozuyor, ayni alan adindaki CSS dosyalarini indirip
 * govdeye ekliyor. Ayni CSS ikinci kez indirilmiyor.
 */
function gbc_nb_govde_genislet( $html ) {

	static $css_onbellek = array();

	/* a) base64 betikleri */
	if ( preg_match_all( '#src="data:text/javascript;base64,([A-Za-z0-9+/=]+)"#', $html, $b64 ) ) {
		foreach ( $b64[1] as $parca ) {
			$acik = base64_decode( $parca, true );
			if ( is_string( $acik ) ) {
				$html .= "\n" . $acik;
			}
		}
	}

	/* b) ayni alan adindaki stil dosyalari */
	$alan = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m ) ) {
		$sayac = 0;
		foreach ( array_unique( $m[1] ) as $url ) {
			if ( $sayac >= 4 ) { break; }
			$h = wp_parse_url( $url, PHP_URL_HOST );
			if ( $h && $h !== $alan ) { continue; }   /* Google Fonts vb. atlanir */
			$sayac++;

			if ( isset( $css_onbellek[ $url ] ) ) {
				$html .= "\n" . $css_onbellek[ $url ];
				continue;
			}
			$c = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => false ) );
			if ( is_wp_error( $c ) ) { continue; }
			$govde = (string) wp_remote_retrieve_body( $c );
			$css_onbellek[ $url ] = $govde;
			$html .= "\n" . $govde;
		}
	}

	return $html;
}

function gbc_nb_tara() {

	$sayfalar = gbc_nb_sayfalar();
	$motorlar = gbc_nb_motorlar();

	$sonuc = array(
		'zaman'   => time(),
		'sayfa'   => array(),
		'motor'   => array(),
		'izler'   => array(),
		'hata'    => array(),
	);

	foreach ( $motorlar as $i => $m ) {
		$sonuc['motor'][ $i ] = array( 'bulundu' => 0, 'bakilan' => 0 );
	}

	$tum_izler = array();

	foreach ( $sayfalar as $ad => $url ) {

		$cevap = wp_remote_get(
			add_query_arg( 'gbc_nb', time(), $url ),
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'GBC-Nobetci/1.0',
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $cevap ) ) {
			$sonuc['hata'][ $ad ] = $cevap->get_error_message();
			$sonuc['sayfa'][ $ad ] = array( 'kod' => 0, 'bayt' => 0, 'url' => $url );
			continue;
		}

		$kod  = (int) wp_remote_retrieve_response_code( $cevap );
		$html = (string) wp_remote_retrieve_body( $cevap );

		$sonuc['sayfa'][ $ad ] = array( 'kod' => $kod, 'bayt' => strlen( $html ), 'url' => $url );

		if ( 200 !== $kod || '' === $html ) {
			$sonuc['hata'][ $ad ] = 'HTTP ' . $kod;
			continue;
		}

		$html = gbc_nb_govde_genislet( $html );

		foreach ( $motorlar as $i => $m ) {
			if ( ! empty( $m['yerel'] ) ) {
				continue;   /* sunucuda dogrulanacak, sayfada aranmaz */
			}
			$sonuc['motor'][ $i ]['bakilan']++;
			$var = ( false !== strpos( $html, $m['iz'] ) );
			if ( ! empty( $m['ters'] ) ) {
				$var = ! $var;
			}
			if ( $var ) {
				$sonuc['motor'][ $i ]['bulundu']++;
			}
		}

		foreach ( gbc_nb_izleri_topla( $html ) as $iz ) {
			$tum_izler[ $iz ] = true;
		}
	}

	/* Sunucu tarafi kontroller */
	$sayfa_sayisi = count( $sayfalar );
	foreach ( $motorlar as $i => $m ) {
		if ( empty( $m['yerel'] ) || ! is_callable( $m['yerel'] ) ) {
			continue;
		}
		list( $ok, $not ) = call_user_func( $m['yerel'] );
		$sonuc['motor'][ $i ] = array(
			'bakilan' => $sayfa_sayisi,
			'bulundu' => $ok ? $sayfa_sayisi : 0,
			'yerel'   => true,
			'not'     => (string) $not,
		);
	}

	$sonuc['izler'] = array_keys( $tum_izler );
	sort( $sonuc['izler'] );

	update_option( GBC_NB_SON, $sonuc, false );
	return $sonuc;
}

/* ============================================================
   4. DEGERLENDIRME
   ============================================================ */
function gbc_nb_motor_durum( $m, $olcum ) {
	if ( empty( $olcum['bakilan'] ) ) {
		return 'bilinmiyor';
	}
	if ( 'hepsi' === $m['kapsam'] ) {
		if ( $olcum['bulundu'] === $olcum['bakilan'] ) { return 'calisiyor'; }
		if ( $olcum['bulundu'] > 0 )                   { return 'kismi'; }
		return 'calismiyor';
	}
	return $olcum['bulundu'] > 0 ? 'calisiyor' : 'yok';
}

/**
 * Tabanla karsilastirir. Donen dizi = yeni bozulanlarin listesi.
 */
function gbc_nb_karsilastir( $sonuc ) {
	$taban = get_option( GBC_NB_TABAN, array() );
	if ( ! is_array( $taban ) || empty( $taban['motor'] ) ) {
		return array();
	}

	$motorlar = gbc_nb_motorlar();
	$alarm    = array();

	foreach ( $motorlar as $i => $m ) {
		$simdi = isset( $sonuc['motor'][ $i ] ) ? $sonuc['motor'][ $i ] : null;
		$onceki = isset( $taban['motor'][ $i ] ) ? $taban['motor'][ $i ] : null;
		if ( ! $simdi || ! $onceki ) { continue; }
		if ( (int) $onceki['bulundu'] > 0 && (int) $simdi['bulundu'] === 0 ) {
			$alarm[] = $m['ad'] . ' — önceden ' . (int) $onceki['bulundu'] . ' sayfada vardı, şimdi hiçbirinde yok';
		}
	}

	/* Otomatik taban: kaybolan sinif adlari */
	$eski = isset( $taban['izler'] ) && is_array( $taban['izler'] ) ? $taban['izler'] : array();
	$yeni = isset( $sonuc['izler'] ) && is_array( $sonuc['izler'] ) ? $sonuc['izler'] : array();
	$kayip = array_diff( $eski, $yeni );
	if ( count( $kayip ) > 2 ) {
		$alarm[] = count( $kayip ) . ' sınıf adı sayfalardan kayboldu: ' . implode( ', ', array_slice( $kayip, 0, 12 ) );
	}

	return $alarm;
}

function gbc_nb_taban_al( $sonuc ) {
	update_option( GBC_NB_TABAN, array(
		'zaman' => $sonuc['zaman'],
		'motor' => $sonuc['motor'],
		'izler' => $sonuc['izler'],
	), false );
}

/* ============================================================
   5. GUNLUK KONTROL
   ============================================================ */
if ( ! function_exists( 'gbc_nb_zamanla' ) ) {
	function gbc_nb_zamanla() {
		if ( ! wp_next_scheduled( 'gbc_nb_gunluk' ) ) {
			/* Yarin sabah 06:20, sonra her gun */
			wp_schedule_event( strtotime( 'tomorrow 06:20', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'gbc_nb_gunluk' );
		}
	}
	add_action( 'init', 'gbc_nb_zamanla' );
}

if ( ! function_exists( 'gbc_nb_gunluk_calistir' ) ) {
	function gbc_nb_gunluk_calistir() {
		$sonuc = gbc_nb_tara();
		$alarm = gbc_nb_karsilastir( $sonuc );

		update_option( 'gbc_nb_alarm', $alarm, false );

		if ( ! $alarm ) {
			/* Her sey yerindeyse bugunku olcum yeni taban olur. */
			gbc_nb_taban_al( $sonuc );
			return;
		}

		/* Ayni alarmi gunde birden fazla yollama. */
		if ( get_transient( 'gbc_nb_posta_kilit' ) ) {
			return;
		}
		set_transient( 'gbc_nb_posta_kilit', 1, 12 * HOUR_IN_SECONDS );

		$govde  = "GBC Nöbetçi — " . count( $alarm ) . " motor durdu.\n\n";
		$govde .= implode( "\n", array_map( static function ( $s ) { return '• ' . $s; }, $alarm ) );
		$govde .= "\n\nDurum ekranı: " . admin_url( 'admin.php?page=gbc-nobetci' );

		wp_mail(
			get_option( 'admin_email' ),
			'[GBC] ' . count( $alarm ) . ' motor durdu',
			$govde
		);
	}
	add_action( 'gbc_nb_gunluk', 'gbc_nb_gunluk_calistir' );
}

/* Yonetici uyarisi */
if ( ! function_exists( 'gbc_nb_uyari' ) ) {
	function gbc_nb_uyari() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$alarm = get_option( 'gbc_nb_alarm', array() );
		if ( ! is_array( $alarm ) || ! $alarm ) { return; }
		echo '<div class="notice notice-error"><p><strong>GBC Nöbetçi:</strong> ' . (int) count( $alarm ) . ' motor durdu. '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-nobetci' ) ) . '">Ayrıntıya bak</a></p></div>';
	}
	add_action( 'admin_notices', 'gbc_nb_uyari' );
}

/* ============================================================
   6. EKRAN
   ============================================================ */
function gbc_nb_rozet( $d ) {
	$h = array(
		'calisiyor'   => array( 'Çalışıyor',    '#1a7f37', '#e6f4ea' ),
		'kismi'       => array( 'Kısmen',       '#8a6100', '#fcf3e1' ),
		'calismiyor'  => array( 'ÇALIŞMIYOR',   '#b32d2e', '#fcebea' ),
		'yok'         => array( 'Bu sayfalarda yok', '#646970', '#f0f0f1' ),
		'bilinmiyor'  => array( 'Ölçülmedi',    '#646970', '#f0f0f1' ),
	);
	$v = isset( $h[ $d ] ) ? $h[ $d ] : array( $d, '#646970', '#f0f0f1' );
	return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;color:'
		. esc_attr( $v[1] ) . ';background:' . esc_attr( $v[2] ) . '">' . esc_html( $v[0] ) . '</span>';
}

function gbc_nb_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	/* Islemler */
	if ( isset( $_GET['gbc_nb_islem'] ) && check_admin_referer( 'gbc_nb' ) ) {
		$islem = sanitize_key( wp_unslash( $_GET['gbc_nb_islem'] ) );
		if ( 'tara' === $islem ) {
			$s = gbc_nb_tara();
			update_option( 'gbc_nb_alarm', gbc_nb_karsilastir( $s ), false );
		} elseif ( 'taban' === $islem ) {
			$s = get_option( GBC_NB_SON, array() );
			if ( is_array( $s ) && ! empty( $s['motor'] ) ) {
				gbc_nb_taban_al( $s );
				update_option( 'gbc_nb_alarm', array(), false );
			}
		} elseif ( 'sayfa' === $islem ) {
			gbc_nb_sayfalar( true );
		} elseif ( 'tam_bas' === $islem ) {
			gbc_nb_tam_kur();
			wp_safe_redirect( wp_nonce_url( admin_url( 'admin.php?page=gbc-nobetci&gbc_nb_islem=tam_adim' ), 'gbc_nb' ) );
			exit;
		} elseif ( 'tam_adim' === $islem ) {
			gbc_nb_tam_adim();
		} elseif ( 'tam_devam' === $islem ) {
			$t = get_option( GBC_NB_TAM, array() );
			if ( is_array( $t ) ) {
				$t['durum'] = 'calisiyor';
				update_option( GBC_NB_TAM, $t, false );
			}
			wp_safe_redirect( wp_nonce_url( admin_url( 'admin.php?page=gbc-nobetci&gbc_nb_islem=tam_adim' ), 'gbc_nb' ) );
			exit;
		} elseif ( 'tam_dur' === $islem ) {
			$t = get_option( GBC_NB_TAM, array() );
			if ( is_array( $t ) ) {
				$t['durum'] = 'durdu';
				update_option( GBC_NB_TAM, $t, false );
			}
		}
	}

	$sonuc = get_option( GBC_NB_SON, array() );
	$taban = get_option( GBC_NB_TABAN, array() );
	$alarm = get_option( 'gbc_nb_alarm', array() );

	$u = static function ( $islem ) {
		return wp_nonce_url( admin_url( 'admin.php?page=gbc-nobetci&gbc_nb_islem=' . $islem ), 'gbc_nb' );
	};

	echo '<div class="wrap"><h1>GBC Kontrol Merkezi — eski motor testi</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-nobetci' ); }
	echo '<p style="max-width:900px;color:#444">Örnek sayfalar gerçekten indirilir ve her motorun kendi çıktısı HTML içinde aranır. '
		. '"Aktif mi" değil, <strong>"sayfada var mı"</strong> sorusunun cevabıdır.</p>';

	echo '<p><a class="button button-primary" href="' . esc_url( $u( 'tara' ) ) . '">Şimdi tara</a> '
		. '<a class="button" href="' . esc_url( $u( 'taban' ) ) . '">Bu hâli “doğru” kabul et</a> '
		. '<a class="button" href="' . esc_url( $u( 'sayfa' ) ) . '">Örnek sayfaları yenile</a></p>';

	if ( is_array( $alarm ) && $alarm ) {
		echo '<div class="notice notice-error" style="margin:14px 0"><p><strong>Alarm</strong></p><ul style="margin:0 0 8px 18px;list-style:disc">';
		foreach ( $alarm as $a ) { echo '<li>' . esc_html( $a ) . '</li>'; }
		echo '</ul></div>';
	}

	if ( empty( $sonuc['zaman'] ) ) {
		echo '<p><em>Henüz örnek tarama yapılmadı. “Şimdi tara” de.</em></p>';
		gbc_nb_tam_ekran();
		echo '</div>';
		return;
	}

	echo '<p style="color:#666">Son tarama: <strong>' . esc_html( date_i18n( 'j F Y, H:i', (int) $sonuc['zaman'] ) ) . '</strong>';
	if ( ! empty( $taban['zaman'] ) ) {
		echo ' · Karşılaştırma tabanı: ' . esc_html( date_i18n( 'j F Y, H:i', (int) $taban['zaman'] ) );
	}
	$sonraki = wp_next_scheduled( 'gbc_nb_gunluk' );
	if ( $sonraki ) {
		echo ' · Sonraki otomatik kontrol: ' . esc_html( date_i18n( 'j F Y, H:i', $sonraki + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
	}
	echo '</p>';

	/* --- Motorlar --- */
	echo '<h2>Motorlar</h2>';
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
		. '<th>Motor</th><th style="width:150px">Durum</th><th style="width:130px">Kaç sayfada</th>'
		. '<th style="width:110px">Kaynak</th><th>Aranan iz</th></tr></thead><tbody>';

	foreach ( gbc_nb_motorlar() as $i => $m ) {
		$o = isset( $sonuc['motor'][ $i ] ) ? $sonuc['motor'][ $i ] : array( 'bulundu' => 0, 'bakilan' => 0 );
		$d = gbc_nb_motor_durum( $m, $o );
		$yerel = ! empty( $m['yerel'] );
		echo '<tr>'
			. '<td><strong>' . esc_html( $m['ad'] ) . '</strong></td>'
			. '<td>' . gbc_nb_rozet( $d ) . '</td>'
			. '<td>' . ( $yerel ? '&mdash;' : ( (int) $o['bulundu'] . ' / ' . (int) $o['bakilan'] ) ) . '</td>'
			. '<td><code>' . (int) $m['kaynak'] . '</code></td>'
			. '<td>' . ( $yerel
				? '<strong style="color:#1d4ed8">Sunucuda doğrulandı</strong>'
					. ( empty( $o['not'] ) ? '' : ' &middot; ' . esc_html( (string) $o['not'] ) )
				: '<code style="font-size:11px">' . esc_html( $m['iz'] ) . '</code>'
					. ( empty( $m['ters'] ) ? '' : ' <em>(bulunmaması doğru)</em>' ) )
			. '</td>'
			. '</tr>';
	}
	echo '</tbody></table>';

	/* --- Taranan sayfalar --- */
	echo '<h2 style="margin-top:28px">Taranan sayfalar</h2>';
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
		. '<th>Şablon</th><th style="width:80px">HTTP</th><th style="width:110px">Boyut</th><th>Adres</th></tr></thead><tbody>';
	foreach ( $sonuc['sayfa'] as $ad => $s ) {
		$renk = ( 200 === (int) $s['kod'] ) ? '#1a7f37' : '#b32d2e';
		echo '<tr><td><strong>' . esc_html( $ad ) . '</strong></td>'
			. '<td style="color:' . esc_attr( $renk ) . ';font-weight:600">' . (int) $s['kod'] . '</td>'
			. '<td>' . esc_html( size_format( (int) $s['bayt'] ) ) . '</td>'
			. '<td><a href="' . esc_url( $s['url'] ) . '" target="_blank">' . esc_html( $s['url'] ) . '</a></td></tr>';
	}
	echo '</tbody></table>';

	/* --- WPCode snippet'leri --- */
	echo '<h2 style="margin-top:28px">WPCode snippet\'leri</h2>';
	$snip = get_posts( array(
		'post_type'      => 'wpcode',
		'post_status'    => array( 'publish', 'draft' ),
		'numberposts'    => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'suppress_filters' => true,
	) );
	if ( $snip ) {
		$acik = 0;
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th style="width:80px">ID</th><th>Başlık</th><th style="width:100px">Durum</th><th style="width:100px">Hata</th></tr></thead><tbody>';
		foreach ( $snip as $s ) {
			$aktif = ( 'publish' === $s->post_status );
			if ( $aktif ) { $acik++; }
			$hata = get_post_meta( $s->ID, '_wpcode_last_error', true );
			echo '<tr><td><code>' . (int) $s->ID . '</code></td>'
				. '<td>' . esc_html( $s->post_title ) . '</td>'
				. '<td>' . ( $aktif
					? '<span style="color:#1a7f37;font-weight:600">Aktif</span>'
					: '<span style="color:#646970">Pasif</span>' ) . '</td>'
				. '<td>' . ( $hata ? '<span style="color:#b32d2e;font-weight:600">VAR</span>' : '&mdash;' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="color:#666">' . (int) $acik . ' aktif / ' . (int) count( $snip ) . ' toplam.</p>';
	}

	/* --- Eklentiler --- */
	echo '<h2 style="margin-top:28px">Eklentiler</h2>';
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$eklentiler = get_plugins();
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
		. '<th>Eklenti</th><th style="width:110px">Sürüm</th><th style="width:100px">Durum</th></tr></thead><tbody>';
	foreach ( $eklentiler as $yol => $e ) {
		/* Coklu site: eklentilerin cogu AGDA etkin. Site bazli kontrol onlara
		   "pasif" der; ikisine birden bakiyoruz. */
		$agda  = is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $yol );
		$aktif = $agda || is_plugin_active( $yol );
		echo '<tr><td><strong>' . esc_html( $e['Name'] ) . '</strong></td>'
			. '<td>' . esc_html( $e['Version'] ) . '</td>'
			. '<td>' . ( $aktif
				? '<span style="color:#1a7f37;font-weight:600">' . ( $agda ? 'Ağda etkin' : 'Aktif' ) . '</span>'
				: '<span style="color:#646970">Pasif</span>' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	/* --- Otomatik taban --- */
	echo '<h2 style="margin-top:28px">Otomatik taban</h2>';
	echo '<p style="max-width:900px;color:#444">Sayfalarda görülen bütün <code>gbc-*</code> / <code>gz-*</code> adları. '
		. 'Yukarıdaki listede olmayan bir motor bile bozulsa, adı buradan düştüğü an alarm verir.</p>';
	echo '<p style="font-size:12px;color:#555;max-width:1000px;line-height:1.9">';
	foreach ( $sonuc['izler'] as $iz ) {
		echo '<code style="margin-right:6px">' . esc_html( $iz ) . '</code>';
	}
	echo '</p><p style="color:#666">' . (int) count( $sonuc['izler'] ) . ' ad bulundu.</p>';

	gbc_nb_tam_ekran();

	echo '</div>';
}

/* ============================================================
   7. TUM SITE TARAMASI
   1040 sayfa tek istekte taranamaz. Kuyruk kurulur, her adimda
   birkac sayfa islenir ve ekran kendini yeniler. Istedigin an
   durdurabilirsin; kaldigi yerden devam eder.
   ============================================================ */
define( 'GBC_NB_TAM', 'gbc_nb_tam' );
define( 'GBC_NB_ADIM', 6 );   /* her turda kac sayfa */

function gbc_nb_tam_kur() {
	global $wpdb;
	$idler = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type IN ('post','page')
		 ORDER BY post_modified DESC"
	);
	$idler = array_map( 'intval', (array) $idler );

	update_option( GBC_NB_TAM, array(
		'durum'  => 'calisiyor',
		'bas'    => time(),
		'toplam' => count( $idler ),
		'kuyruk' => $idler,
		'sonuc'  => array(),
		'sayac'  => array(),
	), false );

	return count( $idler );
}

function gbc_nb_tam_adim() {

	$t = get_option( GBC_NB_TAM, array() );
	if ( ! is_array( $t ) || empty( $t['kuyruk'] ) ) {
		if ( is_array( $t ) ) {
			$t['durum'] = 'bitti';
			$t['bitis'] = time();
			update_option( GBC_NB_TAM, $t, false );
		}
		return false;
	}

	$motorlar = gbc_nb_motorlar();
	$parca    = array_splice( $t['kuyruk'], 0, GBC_NB_ADIM );

	foreach ( $parca as $pid ) {

		$url = get_permalink( $pid );
		if ( ! $url ) { continue; }

		$cevap = wp_remote_get(
			add_query_arg( 'gbc_nb', time(), $url ),
			array( 'timeout' => 20, 'redirection' => 3, 'sslverify' => false, 'user-agent' => 'GBC-Nobetci/1.0' )
		);

		if ( is_wp_error( $cevap ) ) {
			$t['sonuc'][ $pid ] = array( 'kod' => 0, 'eksik' => array(), 'not' => $cevap->get_error_message() );
			continue;
		}

		$kod  = (int) wp_remote_retrieve_response_code( $cevap );
		$html = (string) wp_remote_retrieve_body( $cevap );

		if ( 200 !== $kod || '' === $html ) {
			$t['sonuc'][ $pid ] = array( 'kod' => $kod, 'eksik' => array(), 'not' => 'HTTP ' . $kod );
			continue;
		}

		$html  = gbc_nb_govde_genislet( $html );
		$eksik = array();

		foreach ( $motorlar as $i => $m ) {
			if ( ! empty( $m['yerel'] ) ) { continue; }
			$var = ( false !== strpos( $html, $m['iz'] ) );
			if ( ! empty( $m['ters'] ) ) { $var = ! $var; }

			if ( $var ) {
				$t['sayac'][ $i ] = ( isset( $t['sayac'][ $i ] ) ? (int) $t['sayac'][ $i ] : 0 ) + 1;
			} elseif ( 'hepsi' === $m['kapsam'] ) {
				/* Yalniz "her sayfada olmali" diyenlerin yoklugu hatadir. */
				$eksik[] = $i;
			}
		}

		$t['sonuc'][ $pid ] = array( 'kod' => 200, 'eksik' => $eksik, 'bayt' => strlen( $html ), 'not' => '' );
	}

	$t['son_adim'] = time();
	if ( empty( $t['kuyruk'] ) ) {
		$t['durum'] = 'bitti';
		$t['bitis'] = time();
	}
	update_option( GBC_NB_TAM, $t, false );

	return true;
}

function gbc_nb_tam_ekran() {

	$t = get_option( GBC_NB_TAM, array() );
	$u = static function ( $islem ) {
		return wp_nonce_url( admin_url( 'admin.php?page=gbc-nobetci&gbc_nb_islem=' . $islem ), 'gbc_nb' );
	};

	echo '<h2 style="margin-top:32px">Tüm site taraması</h2>';
	echo '<p style="max-width:900px;color:#444">Yayındaki bütün yazı ve sayfalar tek tek indirilir. '
		. 'Her turda ' . (int) GBC_NB_ADIM . ' sayfa işlenir, ekran kendini yeniler. '
		. 'Sekmeyi kapatırsan tarama durur, kaldığı yerden devam eder.</p>';

	if ( ! is_array( $t ) || empty( $t['toplam'] ) ) {
		echo '<p><a class="button button-primary" href="' . esc_url( $u( 'tam_bas' ) ) . '">Tüm siteyi tara</a></p>';
		return;
	}

	$toplam  = (int) $t['toplam'];
	$kalan   = isset( $t['kuyruk'] ) ? count( $t['kuyruk'] ) : 0;
	$bitmis  = $toplam - $kalan;
	$yuzde   = $toplam ? round( $bitmis / $toplam * 100 ) : 0;
	$suruyor = ( isset( $t['durum'] ) && 'calisiyor' === $t['durum'] && $kalan > 0 );

	echo '<div style="max-width:900px;margin:12px 0">';
	echo '<div style="background:#e8e8e8;border-radius:10px;height:22px;overflow:hidden">'
		. '<div style="background:#1a7f37;height:100%;width:' . (int) $yuzde . '%;transition:width .3s"></div></div>';
	echo '<p style="margin:6px 0 0"><strong>' . (int) $bitmis . ' / ' . (int) $toplam . '</strong> sayfa tarandı ('
		. (int) $yuzde . '%)';
	if ( ! empty( $t['bas'] ) && $bitmis > 0 ) {
		$gecen = max( 1, time() - (int) $t['bas'] );
		$hiz   = $bitmis / $gecen;
		if ( $hiz > 0 && $kalan > 0 ) {
			echo ' · tahmini kalan süre: ' . esc_html( human_time_diff( 0, (int) ( $kalan / $hiz ) ) );
		}
	}
	echo '</p></div>';

	if ( $suruyor ) {
		echo '<p><a class="button" href="' . esc_url( $u( 'tam_dur' ) ) . '">Durdur</a></p>';
		/* Bir sonraki adima kendi kendine gec. */
		echo '<meta http-equiv="refresh" content="1;url=' . esc_attr( $u( 'tam_adim' ) ) . '">';
		echo '<p style="color:#666"><em>Taranıyor… bu sayfa kendini yeniliyor.</em></p>';
	} else {
		echo '<p><a class="button button-primary" href="' . esc_url( $u( 'tam_bas' ) ) . '">Baştan tara</a> ';
		if ( $kalan > 0 ) {
			echo '<a class="button" href="' . esc_url( $u( 'tam_devam' ) ) . '">Kaldığı yerden devam et</a>';
		}
		echo '</p>';
	}

	if ( empty( $t['sonuc'] ) ) { return; }

	/* --- Motor bazinda toplam --- */
	$motorlar = gbc_nb_motorlar();
	echo '<h3 style="margin-top:24px">Motor · kaç sayfada bulundu</h3>';
	echo '<table class="widefat striped" style="max-width:820px"><thead><tr>'
		. '<th>Motor</th><th style="width:140px">Sayfa</th><th style="width:120px">Oran</th></tr></thead><tbody>';
	foreach ( $motorlar as $i => $m ) {
		$n = isset( $t['sayac'][ $i ] ) ? (int) $t['sayac'][ $i ] : 0;
		$o = $bitmis ? round( $n / $bitmis * 100 ) : 0;
		$renk = ( 'hepsi' === $m['kapsam'] && $o < 99 ) ? '#b32d2e' : '#1a7f37';
		echo '<tr><td>' . esc_html( $m['ad'] ) . ( 'hepsi' === $m['kapsam'] ? ' <em style="color:#888">(her sayfada olmalı)</em>' : '' ) . '</td>'
			. '<td><strong>' . $n . '</strong> / ' . (int) $bitmis . '</td>'
			. '<td style="color:' . esc_attr( $renk ) . ';font-weight:600">' . (int) $o . '%</td></tr>';
	}
	echo '</tbody></table>';

	/* --- Sorunlu sayfalar --- */
	$sorunlu = array();
	foreach ( $t['sonuc'] as $pid => $r ) {
		if ( 200 !== (int) $r['kod'] || ! empty( $r['eksik'] ) ) {
			$sorunlu[ $pid ] = $r;
		}
	}

	echo '<h3 style="margin-top:24px">Sorunlu sayfalar — ' . (int) count( $sorunlu ) . ' adet</h3>';
	if ( ! $sorunlu ) {
		echo '<p style="color:#1a7f37"><strong>Taranan ' . (int) $bitmis . ' sayfanın hepsi temiz.</strong></p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
		. '<th>Sayfa</th><th style="width:70px">HTTP</th><th>Eksik olan</th><th style="width:120px"></th>'
		. '</tr></thead><tbody>';
	$sayi = 0;
	foreach ( $sorunlu as $pid => $r ) {
		if ( ++$sayi > 200 ) { break; }
		$eksik_ad = array();
		foreach ( (array) $r['eksik'] as $i ) {
			if ( isset( $motorlar[ $i ] ) ) { $eksik_ad[] = $motorlar[ $i ]['ad']; }
		}
		echo '<tr>'
			. '<td><a href="' . esc_url( (string) get_permalink( $pid ) ) . '" target="_blank">' . esc_html( get_the_title( $pid ) ) . '</a></td>'
			. '<td style="color:' . ( 200 === (int) $r['kod'] ? '#1a7f37' : '#b32d2e' ) . ';font-weight:600">' . (int) $r['kod'] . '</td>'
			. '<td>' . ( $eksik_ad ? esc_html( implode( ', ', $eksik_ad ) ) : esc_html( (string) $r['not'] ) ) . '</td>'
			. '<td><a class="button button-small" href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">Düzenle</a></td>'
			. '</tr>';
	}
	echo '</tbody></table>';
	if ( count( $sorunlu ) > 200 ) {
		echo '<p style="color:#666">İlk 200 tanesi gösteriliyor.</p>';
	}
}
