<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Zamanlanmış İşler (cron).
 *
 * NEDEN VAR: Hostinger'da gerçek bir sunucu cron'u kuruldu, ama "kuruldu"
 * ile "çalışıyor" ayrı şeyler. Bu ekran ikisini ayırır:
 *   · Sunucu cron'u wp-cron.php'yi gerçekten çağırıyor mu (son çağrı saati)
 *   · Kuyrukta bekleyen işler neler, hangisi GECİKMİŞ (zamanı geçmiş ama
 *     hâlâ çalışmamış — takılan iş budur)
 *   · GBC'nin kendi işleri en son ne zaman çalıştı, kaç saniye sürdü
 *   · Test: bir işi elle çalıştırıp sonucunu anında görmek
 *
 * Ölçüm kaydı: her GBC işinin başlangıç/bitiş zamanı gbc_cron_kayit
 * seçeneğine yazılır. "Çalışıyor sanıyorduk" durumu böyle biter.
 */

define( 'GBC_CRON_KAYIT', 'gbc_cron_kayit' );
define( 'GBC_CRON_SON',   'gbc_cron_son' );

/** GBC'ye ait cron kancalari. */
function gbc_cron_kancalar() {
	return array(
		'gbc_gunluk_kontrol' => __( 'Günlük kontrol — Kontrol Merkezi + Güvenlik + Hız + GA4', 'gbc-core' ),
		'gbc_nk_tur'        => __( 'Kontrol Merkezi — saat başı sayfa taraması', 'gbc-core' ),
		'gbc_nb_gunluk'     => __( 'Eski Nöbetçi — kaldırıldı, kalıntı iş', 'gbc-core' ),
		'gbc_gv_tarama'     => __( 'Güvenlik taraması', 'gbc-core' ),
		'gbc_seo_toplama'   => __( 'SEO toplama — saat başı birkaç sayfa', 'gbc-core' ),
		'gbc_kur_gunluk'    => __( 'Canlı kur güncelleme', 'gbc-core' ),
		'gbc_ort_saglik_cron' => __( 'İş Ortaklığı — bağlantı sağlık kontrolü', 'gbc-core' ),
		'gbc_plan_gunluk'   => __( 'Plan tablosu — Google iş listesini okur', 'gbc-core' ),
		'gbc_tik_temizlik'  => __( 'Tıklama kayıtları temizliği', 'gbc-core' ),
		'gbc_km_gunluk_cek' => __( 'Komuta — günlük çekim', 'gbc-core' ),
		'gbc_km_sezon_cek'  => __( 'Komuta — sezon çekimi', 'gbc-core' ),
		'gbc_km_nobetci_cek' => __( 'Komuta nöbetçisi — kaldırıldı (Kontrol Merkezi yapıyor)', 'gbc-core' ),
	);
}

/* ---- Olcum: her GBC isinin basi ve sonu kaydedilir ---- */
add_action( 'init', 'gbc_cron_izle', 1 );
function gbc_cron_izle() {
	if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) { return; }

	/* Sunucu cron'u gercekten geldi mi — tek kanit budur. */
	update_option( GBC_CRON_SON, time(), false );

	foreach ( array_keys( gbc_cron_kancalar() ) as $kanca ) {
		add_action( $kanca, static function () use ( $kanca ) {
			$k = get_option( GBC_CRON_KAYIT, array() );
			if ( ! is_array( $k ) ) { $k = array(); }
			$k[ $kanca ]['bas'] = microtime( true );
			update_option( GBC_CRON_KAYIT, $k, false );
		}, 1 );

		add_action( $kanca, static function () use ( $kanca ) {
			$k = get_option( GBC_CRON_KAYIT, array() );
			if ( ! is_array( $k ) ) { $k = array(); }
			$bas = isset( $k[ $kanca ]['bas'] ) ? (float) $k[ $kanca ]['bas'] : microtime( true );
			$k[ $kanca ]['bitti'] = time();
			$k[ $kanca ]['ms']    = (int) round( ( microtime( true ) - $bas ) * 1000 );
			update_option( GBC_CRON_KAYIT, $k, false );
		}, 99999 );
	}
}

/** Kuyrugu okunabilir bir listeye cevirir. */
function gbc_cron_kuyruk() {
	$ham = _get_cron_array();
	if ( ! is_array( $ham ) ) { return array(); }

	$simdi  = time();
	$liste  = array();
	foreach ( $ham as $zaman => $kancalar ) {
		foreach ( (array) $kancalar as $kanca => $isler ) {
			foreach ( (array) $isler as $is ) {
				$liste[] = array(
					'kanca'   => $kanca,
					'zaman'   => (int) $zaman,
					'gecikme' => max( 0, $simdi - (int) $zaman ),
					'siklik'  => ! empty( $is['schedule'] ) ? (string) $is['schedule'] : __( 'tek sefer', 'gbc-core' ),
				);
			}
		}
	}
	usort( $liste, static function ( $a, $b ) { return $a['zaman'] - $b['zaman']; } );
	return $liste;
}

/** Loopback: WordPress kendi kendini cagirabiliyor mu. */
function gbc_cron_loopback() {
	$c = wp_remote_post( site_url( 'wp-cron.php?doing_wp_cron=' . sprintf( '%.22F', microtime( true ) ) ), array(
		'timeout'   => 10,
		'blocking'  => true,
		'sslverify' => false,
		'body'      => array(),
	) );
	if ( is_wp_error( $c ) ) { return array( 'ok' => false, 'not' => $c->get_error_message() ); }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	return array( 'ok' => ( $kod >= 200 && $kod < 400 ), 'not' => 'HTTP ' . $kod );
}

function gbc_cron_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	/* --- Hayalet cron temizligi (tek tus) --- */
	if ( isset( $_GET['gbc_hayalet_sil'] ) && check_admin_referer( 'gbc_cron' )
		&& function_exists( 'gbc_hayalet_cron_temizle' ) ) {
		$silinen = gbc_hayalet_cron_temizle();
		delete_transient( 'gbc_hayalet_cron_rapor' );   /* raporu burada gosterecegiz */
		if ( $silinen ) {
			echo '<div class="notice notice-success"><p>'
				. esc_html( sprintf( __( '%d hayalet kanca silindi: ', 'gbc-core' ), count( $silinen ) ) )
				. esc_html( implode( ' · ', $silinen ) ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'Silinecek hayalet kanca yok.', 'gbc-core' ) . '</p></div>';
		}
	}

	/* --- Elle calistirma (test) --- */
	if ( isset( $_GET['gbc_cron_calistir'] ) && check_admin_referer( 'gbc_cron' ) ) {
		$kanca = sanitize_key( wp_unslash( $_GET['gbc_cron_calistir'] ) );
		if ( isset( gbc_cron_kancalar()[ $kanca ] ) ) {
			if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
			$bas = microtime( true );
			do_action( $kanca );
			$sure = (int) round( ( microtime( true ) - $bas ) * 1000 );

			$k = get_option( GBC_CRON_KAYIT, array() );
			if ( ! is_array( $k ) ) { $k = array(); }
			$k[ $kanca ]['bitti'] = time();
			$k[ $kanca ]['ms']    = $sure;
			$k[ $kanca ]['elle']  = true;
			update_option( GBC_CRON_KAYIT, $k, false );

			echo '<div class="notice notice-success"><p><strong>' . esc_html( $kanca ) . '</strong> '
				. esc_html( sprintf( __( 'elle çalıştırıldı, %d ms sürdü. Hata vermedi.', 'gbc-core' ), $sure ) )
				. '</p></div>';
		}
	}

	/* --- Takilan isi simdi calistir --- */
	if ( isset( $_GET['gbc_cron_loopback'] ) && check_admin_referer( 'gbc_cron' ) ) {
		$l = gbc_cron_loopback();
		echo '<div class="notice notice-' . ( $l['ok'] ? 'success' : 'error' ) . '"><p>'
			. esc_html( $l['ok']
				? sprintf( __( 'wp-cron.php çağrılabiliyor (%s). Kuyruk kendi kendine ilerleyebilir.', 'gbc-core' ), $l['not'] )
				: sprintf( __( 'wp-cron.php ÇAĞRILAMIYOR (%s). Kuyruk yalnız sunucu cron’u ile ilerler.', 'gbc-core' ), $l['not'] ) )
			. '</p></div>';
	}

	$kuyruk  = gbc_cron_kuyruk();
	$kayit   = get_option( GBC_CRON_KAYIT, array() );
	$son_cron = (int) get_option( GBC_CRON_SON, 0 );
	$gbc     = gbc_cron_kancalar();
	$gecikmis = array_filter( $kuyruk, static function ( $x ) { return $x['gecikme'] > 300; } );

	$u = static function ( $ek ) { return wp_nonce_url( admin_url( 'admin.php?page=gbc-cron' . $ek ), 'gbc_cron' ); };

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Zamanlanmış İşler', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-cron' ); }
	echo '<p style="max-width:920px;color:#444">'
		. esc_html__( 'Burada iki ayrı şey görünür: sunucu cron’u WordPress’i çağırıyor mu, ve kuyruktaki işler zamanında çalışıyor mu. Zamanı 5 dakikadan fazla geçmiş bir iş “takılmış” sayılır.', 'gbc-core' )
		. '</p>';

	/* --- Kartlar --- */
	$wp_cron_kapali = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$fark = $son_cron ? ( time() - $son_cron ) : 0;

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_hz_kart(
		__( 'Sunucu cron’u', 'gbc-core' ),
		$son_cron ? human_time_diff( $son_cron ) : __( 'kayıt yok', 'gbc-core' ),
		$son_cron ? __( 'önce çalıştı', 'gbc-core' ) : __( 'v1.8.2’den beri ilk çalışma bekleniyor', 'gbc-core' ),
		( $son_cron && $fark < 2 * HOUR_IN_SECONDS ) ? '#1A7F37' : '#B32D2E'
	);
	echo gbc_hz_kart( __( 'Kuyruktaki iş', 'gbc-core' ), (string) count( $kuyruk ), __( 'toplam kayıtlı', 'gbc-core' ) );
	echo gbc_hz_kart( __( 'Takılmış iş', 'gbc-core' ), (string) count( $gecikmis ),
		__( 'zamanı 5 dk’dan fazla geçmiş', 'gbc-core' ), count( $gecikmis ) ? '#B32D2E' : '#1A7F37' );
	echo gbc_hz_kart( __( 'WP kendi cron’u', 'gbc-core' ),
		$wp_cron_kapali ? __( 'Kapalı', 'gbc-core' ) : __( 'Açık', 'gbc-core' ),
		$wp_cron_kapali ? __( 'doğru: sunucu cron’u var', 'gbc-core' ) : __( 'ziyaretçi tetikler', 'gbc-core' ),
		'#14181F' );
	echo '</div>';

	echo '<p><a class="button" href="' . esc_url( $u( '&gbc_cron_loopback=1' ) ) . '">'
		. esc_html__( 'wp-cron.php’yi şimdi çağır (test)', 'gbc-core' ) . '</a></p>';

	/* --- GBC isleri --- */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'GBC işleri', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
		. '<th>' . esc_html__( 'İş', 'gbc-core' ) . '</th>'
		. '<th style="width:170px">' . esc_html__( 'Sıradaki', 'gbc-core' ) . '</th>'
		. '<th style="width:190px">' . esc_html__( 'Son çalışma', 'gbc-core' ) . '</th>'
		. '<th style="width:110px">' . esc_html__( 'Süre', 'gbc-core' ) . '</th>'
		. '<th style="width:130px">' . esc_html__( 'Test', 'gbc-core' ) . '</th></tr></thead><tbody>';

	foreach ( $gbc as $kanca => $ad ) {
		$sonraki = wp_next_scheduled( $kanca );
		$k       = isset( $kayit[ $kanca ] ) ? $kayit[ $kanca ] : array();
		$bitti   = ! empty( $k['bitti'] ) ? (int) $k['bitti'] : 0;
		$gecikme = $sonraki ? ( time() - (int) $sonraki ) : 0;

		echo '<tr><td><strong>' . esc_html( $ad ) . '</strong><br><code style="font-size:11.5px;color:#5C6470">' . esc_html( $kanca ) . '</code></td>';

		if ( ! $sonraki ) {
			echo '<td><span style="color:#B32D2E;font-weight:600">' . esc_html__( 'KAYITLI DEĞİL', 'gbc-core' ) . '</span></td>';
		} elseif ( $gecikme > 300 ) {
			echo '<td><span style="color:#B32D2E;font-weight:600">' . esc_html__( 'TAKILMIŞ', 'gbc-core' ) . '</span><br>'
				. '<span style="font-size:12px;color:#5C6470">' . esc_html( sprintf( __( '%s gecikme', 'gbc-core' ), human_time_diff( $sonraki ) ) ) . '</span></td>';
		} else {
			echo '<td>' . esc_html( wp_date( 'j M H:i', $sonraki ) ) . '</td>';
		}

		echo '<td>' . ( $bitti
			? esc_html( wp_date( 'j M H:i', $bitti ) ) . ( empty( $k['elle'] ) ? '' : ' <em style="color:#5C6470">' . esc_html__( '(elle)', 'gbc-core' ) . '</em>' )
			: '<span style="color:#8A6100">' . esc_html__( 'hiç kaydedilmedi', 'gbc-core' ) . '</span>' ) . '</td>';

		echo '<td>' . ( ! empty( $k['ms'] ) ? esc_html( (int) $k['ms'] . ' ms' ) : '—' ) . '</td>';
		echo '<td><a class="button button-small" href="' . esc_url( $u( '&gbc_cron_calistir=' . rawurlencode( $kanca ) ) ) . '">'
			. esc_html__( 'Şimdi çalıştır', 'gbc-core' ) . '</a></td></tr>';
	}
	echo '</tbody></table>';

	/* --- Butun kuyruk --- */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Bütün kuyruk', 'gbc-core' ) . '</h2>';
	if ( ! $kuyruk ) {
		echo '<p><em>' . esc_html__( 'Kuyruk boş.', 'gbc-core' ) . '</em></p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th>' . esc_html__( 'Kanca', 'gbc-core' ) . '</th>'
			. '<th style="width:170px">' . esc_html__( 'Zaman', 'gbc-core' ) . '</th>'
			. '<th style="width:140px">' . esc_html__( 'Sıklık', 'gbc-core' ) . '</th>'
			. '<th style="width:150px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $kuyruk as $x ) {
			$bizim = isset( $gbc[ $x['kanca'] ] );
			echo '<tr><td><code>' . esc_html( $x['kanca'] ) . '</code>'
				. ( $bizim ? ' <span style="color:#1A7F37;font-weight:600">GBC</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( wp_date( 'j M H:i', $x['zaman'] ) ) . '</td>';
			echo '<td>' . esc_html( $x['siklik'] ) . '</td>';
			echo '<td>' . ( $x['gecikme'] > 300
				? '<span style="color:#B32D2E;font-weight:600">' . esc_html( sprintf( __( '%s gecikmiş', 'gbc-core' ), human_time_diff( $x['zaman'] ) ) ) . '</span>'
				: '<span style="color:#1A7F37">' . esc_html__( 'sırada', 'gbc-core' ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<p style="color:#5C6470;font-size:13px;max-width:900px;margin-top:14px">'
		. esc_html__( 'Not: bir iş “takılmış” görünüyorsa sebep neredeyse her zaman cron’un hiç tetiklenmemesidir, işin kendisi bozuk olduğu için değil. Önce “wp-cron.php’yi şimdi çağır” ile tetiklemeyi, sonra “Şimdi çalıştır” ile işin kendisini dene; ikisi ayrı arızadır.', 'gbc-core' )
		. '</p>';

	gbc_cron_hayalet_bolumu();
	echo '</div>';
}

/* ============================================================
   HAYALET ZAMANLANMIŞ GÖREVLER
   Silinmiş eklentilerden kalan kancalar. Her yönetici sayfasında
   kendiliğinden temizleniyor; bu bölüm durumu gösterir ve elle
   temizleme düğmesi verir.
   ============================================================ */
function gbc_cron_hayalet_bolumu() {

	if ( ! function_exists( 'gbc_hayalet_cron_bul' ) ) { return; }

	$bulunan = gbc_hayalet_cron_bul();
	$liste   = function_exists( 'gbc_hayalet_cron_listesi' ) ? gbc_hayalet_cron_listesi() : array();
	$gecmis  = get_option( 'gbc_hayalet_cron_gecmis', array() );

	echo '<h2 id="hayalet" style="margin-top:30px">' . esc_html__( 'Hayalet zamanlanmış görevler', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'Silinmiş eklentilerden kalan kancalar. Her biri olmayan tablosunu arar ve sunucu günlüğüne “table doesn’t exist” yazar — Hostinger’ın “eksik veritabanı tabloları” uyarısının kaynağı buydu. Çekirdek tablolar sağlam, veri kaybı yok. Liste kapalı: yalnız sahibi kesin olarak kurulu OLMAYAN kancalar izleniyor.', 'gbc-core' )
		. '</p>';

	$u = wp_nonce_url( admin_url( 'admin.php?page=gbc-cron&gbc_hayalet_sil=1' ), 'gbc_cron' );

	if ( $bulunan ) {
		echo '<div class="notice notice-warning inline" style="margin:12px 0"><p><strong>'
			. esc_html( sprintf( __( '%d hayalet kanca zamanlanmış durumda.', 'gbc-core' ), count( $bulunan ) ) )
			. '</strong></p></div>';
		echo '<table class="widefat striped" style="max-width:800px"><thead><tr>'
			. '<th>' . esc_html__( 'Kanca', 'gbc-core' ) . '</th>'
			. '<th style="width:200px">' . esc_html__( 'Sıradaki çalışma', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $bulunan as $kanca => $zaman ) {
			echo '<tr><td><code>' . esc_html( $kanca ) . '</code></td>'
				. '<td>' . esc_html( human_time_diff( $zaman ) . ' ' . __( 'sonra', 'gbc-core' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="margin-top:12px"><a class="button button-primary" href="' . esc_url( $u ) . '">'
			. esc_html__( 'Hayalet kancaları şimdi sil', 'gbc-core' ) . '</a></p>';
	} else {
		echo '<p style="color:#1A7F37"><strong>' . esc_html__( 'Temiz.', 'gbc-core' ) . '</strong> '
			. esc_html( sprintf( __( 'İzlenen %d kancanın hiçbiri zamanlanmış değil.', 'gbc-core' ), count( $liste ) ) )
			. '</p>';
		echo '<p><a class="button" href="' . esc_url( $u ) . '">'
			. esc_html__( 'Yine de tara', 'gbc-core' ) . '</a></p>';
	}

	if ( is_array( $gecmis ) && $gecmis ) {
		echo '<h3 style="margin-top:18px">' . esc_html__( 'Son temizlikler', 'gbc-core' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( array_slice( $gecmis, 0, 5 ) as $g ) {
			echo '<tr><td style="width:170px">' . esc_html( human_time_diff( (int) $g['zaman'] ) . ' ' . __( 'önce', 'gbc-core' ) ) . '</td>'
				. '<td style="font-size:12px;color:#5C6470">' . esc_html( implode( ' · ', (array) $g['kanca'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<p style="color:#5C6470;font-size:13px;max-width:900px;margin-top:10px">'
		. esc_html__( 'Ziyaretçi tetiklemeli WP-Cron kapalı: sunucuda gerçek cron zaten 5 dakikada bir çalışıyor, ikisi birden açıkken kuyruğu tetikleyen ziyaretçi bekliyordu.', 'gbc-core' )
		. '</p>';
}
