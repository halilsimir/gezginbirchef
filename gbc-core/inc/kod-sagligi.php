<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Kod sağlığı.
 *
 * Kontrol Merkezi'nin BİRİNCİ katmanı. Sayfa taraması motorun çıktısını
 * arar; bu katman motorun KODUNU denetler:
 *
 *   1) Dosya yerinde mi, kaç bayt, ne zaman değişti.
 *   2) PHP sözdizimi geçerli mi (token_get_all · TOKEN_PARSE).
 *   3) Yüklendi mi — yoksa neden yüklenmedi (WPCode kopyası açık, elle
 *      kapatılmış, dosya yok).
 *   4) İmza fonksiyonu gerçekten tanımlı mı — yani kod çalışıyor mu.
 *   5) Kısa kod tipi modüllerde kısa kod kayıtlı mı.
 *   6) WPCode'daki eski kopya hâlâ yayında mı (çift çıktı riski).
 *   7) Ekran dosyaları ve menü geri çağırmaları yerinde mi.
 *   8) Zamanlanmış işler kurulu mu.
 *
 * Bulunan her sorun Günlük Kontrol defterine yazılır ve çözülene kadar
 * kapanmaz.
 */

define( 'GBC_KOD_SON', 'gbc_kod_son' );

/** Bir PHP dosyasının sözdizimi geçerli mi. */
function gbc_kod_sozdizimi( $yol ) {
	$kaynak = @file_get_contents( $yol );
	if ( false === $kaynak ) { return array( false, __( 'dosya okunamadı', 'gbc-core' ) ); }
	if ( ! defined( 'TOKEN_PARSE' ) ) { return array( true, '' ); }
	try {
		token_get_all( $kaynak, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		return array( false, $e->getMessage() . ' (satır ' . $e->getLine() . ')' );
	} catch ( Throwable $e ) {
		return array( false, $e->getMessage() );
	}
	return array( true, '' );
}

/** Kontrol Merkezi'nin denetlediği yönetici ekranları. */
/**
 * Yalnız yönetici isteğinde yüklenen ekranlar.
 *
 * gbc-core.php bu iki dosyayı `is_admin()` kapısının arkasında yüklüyor;
 * cron'da yüklenmezler. Tarama cron'dan koştuğunda fonksiyonlar tanımsız
 * görünüyor ve panel "Ekran açılmıyor: durum.php" diye YANLIŞ alarm
 * veriyordu (28 Eylül 2026'da ölçüldü). Cron'da bunların varlığı
 * dosya ve sözdizimi düzeyinde denetlenir, fonksiyon aranmaz.
 */
/**
 * İMZA DENETİMİ — B4.
 *
 * ÖLÇÜLEN SORUN (29 Eylül 2026): gbc-core.php'de 70-ortaklik-denetim.php
 * modülünün imzası 'gbc_aff_panel_menu' idi. O fonksiyon v1.15.0'da
 * YORUM BLOĞUNUN İÇİNE alındı, yani artık tanımlı değil. Kod sağlığı
 * function_exists() ile bakıp "modül çalışmıyor" diyordu — oysa modül
 * çalışıyordu, tıklama sayacı her sayfada vardı. Yanlış alarm.
 *
 * Bu denetim dosyayı PHP belirteçlerine ayırır ve imza fonksiyonunun
 * dosyada GERÇEKTEN tanımlı olup olmadığına bakar. Yorum içindeki
 * "function x()" metni sayılmaz, çünkü token_get_all yorumu
 * T_COMMENT olarak işaretler ve fonksiyon tanımı saymaz.
 *
 * @param string $yol   Modül dosyasının tam yolu.
 * @param string $imza  Aranan fonksiyon adı.
 * @return array array( tanimli, yorumda, hata )
 */
function gbc_kod_imza_denetle( $yol, $imza ) {
	$sonuc = array( 'tanimli' => false, 'yorumda' => false, 'hata' => '' );
	if ( '' === $imza ) { return $sonuc; }
	if ( ! file_exists( $yol ) ) { $sonuc['hata'] = __( 'Dosya yok.', 'gbc-core' ); return $sonuc; }

	$kaynak = file_get_contents( $yol );
	if ( false === $kaynak ) { $sonuc['hata'] = __( 'Dosya okunamadı.', 'gbc-core' ); return $sonuc; }

	try {
		$belirtec = token_get_all( $kaynak, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		$sonuc['hata'] = $e->getMessage();
		return $sonuc;
	} catch ( Throwable $e ) {
		$sonuc['hata'] = $e->getMessage();
		return $sonuc;
	}

	$bekle = false;
	foreach ( $belirtec as $t ) {
		if ( ! is_array( $t ) ) { $bekle = false; continue; }
		if ( T_FUNCTION === $t[0] ) { $bekle = true; continue; }
		if ( T_WHITESPACE === $t[0] || T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) { continue; }
		if ( $bekle ) {
			if ( T_STRING === $t[0] && $t[1] === $imza ) { $sonuc['tanimli'] = true; break; }
			$bekle = false;
		}
	}

	/* Tanımlı değilse: yorumun içinde mi duruyor? Öyleyse mesaj net olsun. */
	if ( ! $sonuc['tanimli'] ) {
		foreach ( $belirtec as $t ) {
			if ( ! is_array( $t ) ) { continue; }
			if ( T_COMMENT !== $t[0] && T_DOC_COMMENT !== $t[0] ) { continue; }
			if ( false !== strpos( $t[1], 'function ' . $imza ) ) { $sonuc['yorumda'] = true; break; }
		}
	}

	return $sonuc;
}

/**
 * Bütün modüllerin imzaları dosyalarında gerçekten tanımlı mı?
 *
 * @return array sorunlu modüller: dosya => mesaj
 */
function gbc_kod_imza_tara() {
	$sorun = array();
	if ( ! function_exists( 'gbc_core_moduller' ) ) { return $sorun; }

	foreach ( gbc_core_moduller() as $m ) {
		if ( empty( $m['imza'] ) || empty( $m['dosya'] ) ) { continue; }
		$yol = GBC_CORE_DIR . 'modules/' . $m['dosya'];
		$d   = gbc_kod_imza_denetle( $yol, $m['imza'] );

		if ( '' !== $d['hata'] ) {
			$sorun[ $m['dosya'] ] = sprintf( __( 'İmza denetlenemedi: %s', 'gbc-core' ), $d['hata'] );
		} elseif ( ! $d['tanimli'] ) {
			$sorun[ $m['dosya'] ] = $d['yorumda']
				? sprintf( __( 'İmza %1$s() dosyada YORUM İÇİNDE — tanımlı değil. Yükleyici bu modülü hep "çalışmıyor" sayar. İmzayı dosyada gerçekten tanımlı bir fonksiyona çevir.', 'gbc-core' ), $m['imza'] )
				: sprintf( __( 'İmza %1$s() bu dosyada hiç tanımlı değil. Yanlış imza yazılmış olabilir.', 'gbc-core' ), $m['imza'] );
		}
	}
	return $sorun;
}

function gbc_kod_sadece_admin_ekranlar() {
	return array( 'durum.php', 'tasarim.php' );
}

function gbc_kod_ekranlar() {
	return array(
		'durum.php'        => 'gbc_core_durum_ekran',
		'seo.php'          => 'gbc_seo_ekran',
		'seo-envanter.php' => 'gbc_env_ekran',
		'seo-toplayici.php'=> 'gbc_seo_toplama_ekran',
		'api-merkezi.php'  => 'gbc_api_ekran',
		'gunluk.php'       => 'gbc_gunluk_ekran',
		'nobetci-kural.php'=> 'gbc_nk_ekran',
		'kod-sagligi.php'  => 'gbc_kod_tablo',
		'guvenlik.php'     => 'gbc_gv_ekran',
		'hiz.php'          => 'gbc_hz_ekran',
		'ortaklik.php'     => 'gbc_ort_ekran',
		'cron.php'         => 'gbc_cron_ekran',
		'defter.php'       => 'gbc_defter_ekran',
		'tasarim.php'      => 'gbc_tasarim_yol',
	);
}

/**
 * Bütün PHP tarafını denetler.
 *
 * @param bool $kaydet true ise bulunan sorunlar günlük deftere yazılır.
 * @return array
 */
function gbc_kod_tara( $kaydet = false ) {

	$rapor = array(
		'zaman'  => time(),
		'imza'   => array(),
		'modul'  => array(),
		'ekran'  => array(),
		'cron'   => array(),
		'sorun'  => 0,
		'uyari'  => 0,
	);

	$durum_kaydi = isset( $GLOBALS['gbc_core_durum'] ) && is_array( $GLOBALS['gbc_core_durum'] )
		? $GLOBALS['gbc_core_durum'] : array();

	/* ---- 1) MODÜLLER ---- */
	foreach ( (array) ( function_exists( 'gbc_core_moduller' ) ? gbc_core_moduller() : array() ) as $m ) {
		$dosya = $m['dosya'];
		$yol   = GBC_CORE_DIR . 'modules/' . $dosya;
		$k     = array(
			'dosya'  => $dosya,
			'ad'     => $m['ad'],
			'var'    => file_exists( $yol ),
			'bayt'   => file_exists( $yol ) ? (int) filesize( $yol ) : 0,
			'tarih'  => file_exists( $yol ) ? (int) filemtime( $yol ) : 0,
			'imza'   => isset( $m['imza'] ) ? $m['imza'] : null,
			'yuklu'  => isset( $durum_kaydi[ $dosya ]['durum'] ) ? $durum_kaydi[ $dosya ]['durum'] : 'bilinmiyor',
			'not'    => isset( $durum_kaydi[ $dosya ]['not'] ) ? $durum_kaydi[ $dosya ]['not'] : '',
			'sozdizimi' => true,
			'kisakod'   => '',
			'kopya'     => array(),
		);

		if ( ! $k['var'] ) {
			$k['seviye'] = 'sorun';
			$k['mesaj']  = __( 'Dosya yok.', 'gbc-core' );
			$rapor['modul'][] = $k; $rapor['sorun']++;
			continue;
		}

		list( $ok, $hata ) = gbc_kod_sozdizimi( $yol );
		$k['sozdizimi'] = $ok;
		if ( ! $ok ) {
			$k['seviye'] = 'sorun';
			$k['mesaj']  = __( 'PHP sözdizimi hatalı: ', 'gbc-core' ) . $hata;
			$rapor['modul'][] = $k; $rapor['sorun']++;
			continue;
		}

		/* WPCode'daki eski kopya hala yayinda mi */
		foreach ( (array) ( isset( $m['kapat'] ) ? $m['kapat'] : array() ) as $sid ) {
			if ( ! (int) $sid ) { continue; }
			if ( 'publish' === get_post_status( (int) $sid ) ) { $k['kopya'][] = (int) $sid; }
		}

		/* Kisa kod kaydi */
		if ( ! empty( $m['kisakod'] ) ) {
			$k['kisakod'] = $m['kisakod'] . ( shortcode_exists( $m['kisakod'] ) ? ' ✓' : ' ✗' );
		}

		switch ( $k['yuklu'] ) {
			case 'eklenti':
				if ( ! empty( $k['imza'] ) && ! function_exists( $k['imza'] ) ) {
					$k['seviye'] = 'sorun';
					$k['mesaj']  = sprintf( __( 'Yüklendi ama %s() tanımlı değil — dosya erken çıkıyor olabilir.', 'gbc-core' ), $k['imza'] );
					$rapor['sorun']++;
				} elseif ( ! empty( $m['kisakod'] ) && ! shortcode_exists( $m['kisakod'] ) ) {
					$k['seviye'] = 'sorun';
					$k['mesaj']  = sprintf( __( '[%s] kısa kodu kayıtlı değil.', 'gbc-core' ), $m['kisakod'] );
					$rapor['sorun']++;
				} elseif ( $k['kopya'] ) {
					$k['seviye'] = 'uyari';
					$k['mesaj']  = sprintf( __( 'Çalışıyor ama WPCode kopyası hâlâ yayında: %s — çift çıktı riski.', 'gbc-core' ), implode( ', ', $k['kopya'] ) );
					$rapor['uyari']++;
				} else {
					$k['seviye'] = 'ok';
					$k['mesaj']  = __( 'Eklentiden çalışıyor.', 'gbc-core' );
				}
				break;

			case 'kalinti':
				$k['seviye'] = 'sorun';
				$k['mesaj']  = $k['not'];
				$rapor['sorun']++;
				break;

			case 'wpcode':
			case 'wpcode-acik':
				$k['seviye'] = 'uyari';
				$k['mesaj']  = $k['not'] ? $k['not'] : __( 'WPCode sürümü çalışıyor; eklenti sürümü beklemede.', 'gbc-core' );
				$rapor['uyari']++;
				break;

			case 'kapali':
				$k['seviye'] = 'kapali';
				$k['mesaj']  = __( 'Elle kapatıldı.', 'gbc-core' );
				break;

			case 'dosya-yok':
				$k['seviye'] = 'sorun';
				$k['mesaj']  = $k['not'];
				$rapor['sorun']++;
				break;

			default:
				$k['seviye'] = 'uyari';
				$k['mesaj']  = __( 'Yükleyici bu modülü hiç işaretlememiş.', 'gbc-core' );
				$rapor['uyari']++;
		}

		$rapor['modul'][] = $k;
	}

	/* ---- 2) EKRANLAR ---- */
	/* B4 — imza denetimi: modülün imzası dosyada gerçekten tanımlı mı? */
	foreach ( gbc_kod_imza_tara() as $dosya => $mesaj ) {
		$rapor['imza'][] = array( 'dosya' => $dosya, 'mesaj' => $mesaj );
		$rapor['sorun']++;
		if ( $kaydet && function_exists( 'gbc_sorun_ac' ) ) {
			gbc_sorun_ac( 'kod-imza-' . sanitize_key( $dosya ), 'kod',
				sprintf( __( 'Modül imzası hatalı: %s', 'gbc-core' ), $dosya ),
				$mesaj, 'yuksek', admin_url( 'admin.php?page=gbc-nobetci-kural' ) );
		}
	}

	foreach ( gbc_kod_ekranlar() as $dosya => $geri ) {
		$yol = GBC_CORE_DIR . 'inc/' . $dosya;
		$e = array( 'dosya' => $dosya, 'geri' => $geri, 'var' => file_exists( $yol ) );
		if ( ! $e['var'] ) {
			$e['seviye'] = 'sorun'; $e['mesaj'] = __( 'Dosya yok.', 'gbc-core' ); $rapor['sorun']++;
		} else {
			list( $ok, $hata ) = gbc_kod_sozdizimi( $yol );
			if ( ! $ok ) {
				$e['seviye'] = 'sorun'; $e['mesaj'] = __( 'PHP sözdizimi hatalı: ', 'gbc-core' ) . $hata; $rapor['sorun']++;
			} elseif ( ! function_exists( $geri ) ) {
				if ( ! is_admin() && in_array( $dosya, gbc_kod_sadece_admin_ekranlar(), true ) ) {
					/* Cron: bu dosya bilerek yüklenmedi, sorun değil. */
					$e['seviye'] = 'ok';
					$e['mesaj']  = __( 'Yalnız yönetici ekranında yüklenir; cron taramasında denetlenmez.', 'gbc-core' );
				} else {
					$e['seviye'] = 'sorun';
					$e['mesaj']  = sprintf( __( '%s() tanımlı değil — ekran boş açılır.', 'gbc-core' ), $geri );
					$rapor['sorun']++;
				}
			} else {
				$e['seviye'] = 'ok'; $e['mesaj'] = __( 'Ekran yerinde.', 'gbc-core' );
			}
		}
		$rapor['ekran'][] = $e;
	}

	/* ---- 3) ZAMANLANMIŞ İŞLER ---- */
	$isler = array(
		'gbc_gunluk_kontrol' => __( 'Günlük kontrol (05:40)', 'gbc-core' ),
		'gbc_nk_tur'         => __( 'Kontrol Merkezi — saat başı tarama', 'gbc-core' ),
		'gbc_seo_toplama'    => __( 'SEO toplayıcı — saat başı', 'gbc-core' ),
		'gbc_kur_gunluk'     => __( 'Canlı kur — günlük', 'gbc-core' ),
		'gbc_gv_tarama'      => __( 'Güvenlik taraması — günlük', 'gbc-core' ),
	);
	foreach ( $isler as $kanca => $ad ) {
		$sonraki = wp_next_scheduled( $kanca );
		$c = array( 'kanca' => $kanca, 'ad' => $ad, 'sonraki' => (int) $sonraki );
		if ( ! $sonraki ) {
			$c['seviye'] = 'sorun'; $c['mesaj'] = __( 'Kurulu değil — kendiliğinden çalışmaz.', 'gbc-core' );
			$rapor['sorun']++;
		} elseif ( $sonraki < time() - DAY_IN_SECONDS ) {
			$c['seviye'] = 'uyari'; $c['mesaj'] = __( 'Bir günden fazla gecikmiş — cron tetiklenmiyor.', 'gbc-core' );
			$rapor['uyari']++;
		} else {
			$c['seviye'] = 'ok'; $c['mesaj'] = __( 'Sırada.', 'gbc-core' );
		}
		$rapor['cron'][] = $c;
	}

	update_option( GBC_KOD_SON, $rapor, false );

	/* ---- 4) DEFTERE YAZ ---- */
	if ( $kaydet && function_exists( 'gbc_sorun_ac' ) ) {
		$bulunan = array();
		foreach ( $rapor['modul'] as $k ) {
			if ( 'sorun' !== $k['seviye'] ) { continue; }
			$id = 'kod-m-' . sanitize_key( $k['dosya'] );
			$bulunan[] = $id;
			gbc_sorun_ac( $id, 'kod', sprintf( __( 'Modül çalışmıyor: %s', 'gbc-core' ), $k['ad'] ),
				$k['mesaj'] . ' — modules/' . $k['dosya'], 'yuksek',
				admin_url( 'admin.php?page=gbc-nobetci-kural#kod' ) );
		}
		foreach ( $rapor['ekran'] as $e ) {
			if ( 'sorun' !== $e['seviye'] ) { continue; }
			$id = 'kod-e-' . sanitize_key( $e['dosya'] );
			$bulunan[] = $id;
			gbc_sorun_ac( $id, 'kod', sprintf( __( 'Ekran açılmıyor: %s', 'gbc-core' ), $e['dosya'] ),
				$e['mesaj'], 'yuksek', admin_url( 'admin.php?page=gbc-nobetci-kural#kod' ) );
		}
		foreach ( $rapor['cron'] as $c ) {
			if ( 'sorun' !== $c['seviye'] ) { continue; }
			$id = 'kod-c-' . sanitize_key( $c['kanca'] );
			$bulunan[] = $id;
			gbc_sorun_ac( $id, 'kod', sprintf( __( 'Zamanlanmış iş kurulu değil: %s', 'gbc-core' ), $c['ad'] ),
				$c['mesaj'], 'orta', admin_url( 'admin.php?page=gbc-cron' ) );
		}
		if ( function_exists( 'gbc_sorun_temizle' ) ) {
			gbc_sorun_temizle( 'kod', $bulunan, $rapor['zaman'] );
		}
	}

	return $rapor;
}

/* ============================================================
   EKRAN PARÇASI — Kontrol Merkezi'nin içinde basılır
   ============================================================ */
function gbc_kod_rozet( $seviye ) {
	$h = array(
		'ok'     => array( '✓', '#1A7F37', __( 'Çalışıyor', 'gbc-core' ) ),
		'uyari'  => array( '!', '#8A6100', __( 'Dikkat', 'gbc-core' ) ),
		'sorun'  => array( '✗', '#B3261E', __( 'SORUN', 'gbc-core' ) ),
		'kapali' => array( '–', '#B9BDC5', __( 'Kapalı', 'gbc-core' ) ),
	);
	$x = isset( $h[ $seviye ] ) ? $h[ $seviye ] : array( '?', '#B9BDC5', '' );
	return '<span title="' . esc_attr( $x[2] ) . '" style="color:' . esc_attr( $x[1] )
		. ';font-weight:700;font-size:15px">' . esc_html( $x[0] ) . '</span>';
}

function gbc_kod_tablo( $rapor = null ) {
	if ( null === $rapor ) { $rapor = gbc_kod_tara(); }

	echo '<h2 id="kod" style="margin-top:30px">' . esc_html__( 'Kod sağlığı — PHP tarafı', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'Sayfa taraması motorun çıktısını arar; bu tablo motorun kodunu denetler: dosya yerinde mi, sözdizimi geçerli mi, yüklendi mi, imza fonksiyonu tanımlı mı, kısa kodu kayıtlı mı, WPCode’daki eski kopya hâlâ açık mı.', 'gbc-core' )
		. '</p>';

	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th style="width:38px"></th>'
		. '<th style="width:230px">' . esc_html__( 'Modül', 'gbc-core' ) . '</th>'
		. '<th style="width:210px">' . esc_html__( 'Dosya', 'gbc-core' ) . '</th>'
		. '<th style="width:96px">' . esc_html__( 'Sözdizimi', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Durum', 'gbc-core' ) . '</th></tr></thead><tbody>';
	foreach ( (array) $rapor['modul'] as $k ) {
		echo '<tr' . ( 'sorun' === $k['seviye'] ? ' style="background:#FDF6F2"' : '' ) . '>';
		echo '<td style="text-align:center">' . gbc_kod_rozet( $k['seviye'] ) . '</td>';
		echo '<td><strong>' . esc_html( $k['ad'] ) . '</strong>'
			. ( $k['imza'] ? '<div style="color:#8A919C;font-size:11px"><code>' . esc_html( $k['imza'] ) . '()</code></div>' : '' )
			. '</td>';
		echo '<td><code style="font-size:12px">' . esc_html( $k['dosya'] ) . '</code>'
			. ( $k['bayt'] ? '<div style="color:#8A919C;font-size:11px">' . esc_html( size_format( $k['bayt'] ) ) . '</div>' : '' )
			. '</td>';
		echo '<td>' . ( $k['sozdizimi']
			? '<span style="color:#1A7F37;font-weight:600">' . esc_html__( 'geçerli', 'gbc-core' ) . '</span>'
			: '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'HATALI', 'gbc-core' ) . '</span>' ) . '</td>';
		echo '<td>' . esc_html( $k['mesaj'] )
			. ( ! empty( $k['kisakod'] ) ? '<div style="color:#5C6470;font-size:12px">' . esc_html__( 'kısa kod: ', 'gbc-core' ) . esc_html( $k['kisakod'] ) . '</div>' : '' )
			. '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h3 style="margin-top:22px">' . esc_html__( 'Yönetici ekranları', 'gbc-core' ) . '</h3>';
	echo '<table class="widefat striped" style="max-width:900px"><tbody>';
	foreach ( (array) $rapor['ekran'] as $e ) {
		echo '<tr><td style="width:38px;text-align:center">' . gbc_kod_rozet( $e['seviye'] ) . '</td>'
			. '<td style="width:220px"><code style="font-size:12px">inc/' . esc_html( $e['dosya'] ) . '</code></td>'
			. '<td style="width:220px"><code style="font-size:12px">' . esc_html( $e['geri'] ) . '()</code></td>'
			. '<td>' . esc_html( $e['mesaj'] ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h3 style="margin-top:22px">' . esc_html__( 'Zamanlanmış işler', 'gbc-core' ) . '</h3>';
	echo '<table class="widefat striped" style="max-width:900px"><tbody>';
	foreach ( (array) $rapor['cron'] as $c ) {
		echo '<tr><td style="width:38px;text-align:center">' . gbc_kod_rozet( $c['seviye'] ) . '</td>'
			. '<td style="width:300px">' . esc_html( $c['ad'] ) . '</td>'
			. '<td style="width:190px">' . ( $c['sonraki']
				? esc_html( human_time_diff( $c['sonraki'] ) . ' ' . __( 'sonra', 'gbc-core' ) )
				: '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'kurulu değil', 'gbc-core' ) . '</span>' ) . '</td>'
			. '<td>' . esc_html( $c['mesaj'] ) . '</td></tr>';
	}
	echo '</tbody></table>';

	/* Hayalet kancalar — burada da görünsün, düğme Zamanlanmış İşler'de. */
	if ( function_exists( 'gbc_hayalet_cron_bul' ) ) {
		$hayalet = gbc_hayalet_cron_bul();
		if ( $hayalet ) {
			echo '<div class="notice notice-warning inline" style="margin:12px 0;max-width:900px"><p>'
				. '<strong>' . esc_html( sprintf(
					__( 'Silinmiş eklentilerden kalan %d hayalet kanca zamanlanmış:', 'gbc-core' ), count( $hayalet ) ) )
				. '</strong> ' . esc_html( implode( ' · ', array_keys( $hayalet ) ) ) . '<br>'
				. '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-cron#hayalet' ) ) . '">'
				. esc_html__( 'Zamanlanmış İşler → Hayalet kancaları şimdi sil', 'gbc-core' ) . '</a></p></div>';
		} else {
			echo '<p style="color:#1A7F37;font-size:13px;margin-top:8px"><strong>'
				. esc_html__( 'Hayalet kanca yok.', 'gbc-core' ) . '</strong> '
				. esc_html__( 'Silinmiş eklentilerden kalan zamanlanmış görev bulunmuyor; temizlik her yönetici sayfasında kendiliğinden çalışıyor.', 'gbc-core' )
				. '</p>';
		}
	}

	/* Hangi kod nereden çalışıyor — WPCode karşılaştırması. */
	if ( function_exists( 'gbc_kod_kaynak_tablo' ) ) { gbc_kod_kaynak_tablo(); }
}

/* ============================================================
   KAYNAK HARİTASI — hangi kod nereden çalışıyor

   Halil'in sorusu: "hangi kodlar burada çalışıyor, hangisi WPCode
   snippet'i, hangilerini pasife almak lazım?"

   Bu bölüm iki listeyi yan yana koyar:
     1) Eklentinin modülleri — hangisi gerçekten eklentiden çalışıyor.
     2) WPCode'da YAYINDA olan snippet'ler — hangisinin eklentide
        karşılığı var, hangisi kalmalı.
   Her snippet için tek cümlelik karar yazılır.
   ============================================================ */

if ( ! function_exists( 'gbc_kod_wpcode_listesi' ) ) {
/**
 * WPCode snippet'leri — AKTİF VE PASİF hepsi.
 *
 * Pasifler de listeleniyor, çünkü asıl tehlikeli durum şu: bir snippet
 * kapatılmış ama onu devralması gereken modül de çalışmıyor. O zaman o
 * iş sitede HİÇ yapılmıyor ve kimse fark etmiyor. Harita bunu kırmızı
 * gösteriyor.
 *
 * @return array id => array(baslik, tur, konum, aktif, hata)
 */
function gbc_kod_wpcode_listesi() {
	$liste = array();

	$snippetler = get_posts( array(
		'post_type'              => 'wpcode',
		'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page'         => 300,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'suppress_filters'       => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	) );

	foreach ( (array) $snippetler as $sn ) {
		$tur   = get_post_meta( $sn->ID, '_wpcode_code_type', true );
		$hata  = get_post_meta( $sn->ID, '_wpcode_last_error', true );
		$aktif = ( 'publish' === $sn->post_status );

		/* WPCode bazı sürümlerde ayrıca _wpcode_active meta'sı tutuyor;
		   varsa o da sayılır — ikisi de doğruysa aktif. */
		$meta_aktif = get_post_meta( $sn->ID, '_wpcode_active', true );
		if ( '' !== $meta_aktif && ! $meta_aktif ) { $aktif = false; }

		$liste[ (int) $sn->ID ] = array(
			'baslik' => (string) $sn->post_title,
			'tur'    => $tur ? (string) $tur : '—',
			'konum'  => (string) get_post_meta( $sn->ID, '_wpcode_location', true ),
			'aktif'  => $aktif,
			'hata'   => ( is_array( $hata ) && $hata ) || ( is_string( $hata ) && '' !== trim( $hata ) ),
		);
	}
	return $liste;
}
}

if ( ! function_exists( 'gbc_kod_kaynak_haritasi' ) ) {
/**
 * Snippet id => onu devralan modül(ler).
 * Hem 'kaynak' hem 'kapat' alanları taranır.
 */
function gbc_kod_kaynak_haritasi() {
	$harita = array();
	foreach ( (array) ( function_exists( 'gbc_core_moduller' ) ? gbc_core_moduller() : array() ) as $m ) {
		$idler = array();
		if ( ! empty( $m['kaynak'] ) ) { $idler[] = (int) $m['kaynak']; }
		foreach ( (array) ( isset( $m['kapat'] ) ? $m['kapat'] : array() ) as $sid ) { $idler[] = (int) $sid; }
		foreach ( array_unique( array_filter( $idler ) ) as $sid ) {
			$harita[ $sid ][] = $m;
		}
	}
	return $harita;
}
}

if ( ! function_exists( 'gbc_kod_kaynak_tablo' ) ) {
function gbc_kod_kaynak_tablo() {

	$snippetler = gbc_kod_wpcode_listesi();
	if ( ! $snippetler ) { return; }

	$harita = gbc_kod_kaynak_haritasi();
	$durum  = isset( $GLOBALS['gbc_core_durum'] ) && is_array( $GLOBALS['gbc_core_durum'] )
		? $GLOBALS['gbc_core_durum'] : array();
	$css_liste = function_exists( 'gbc_css_sablon_listesi' ) ? gbc_css_sablon_listesi() : array();

	$satirlar = array();
	$pasif_al = 0;
	$bekleyen = 0;

	foreach ( $snippetler as $sid => $sn ) {

		$aktif  = ! empty( $sn['aktif'] );
		$modul  = '';
		$karar  = $aktif
			? __( 'Kalsın — eklentide karşılığı yok, WPCode’da çalışıyor.', 'gbc-core' )
			: __( 'Pasif — eklentide karşılığı yok. Bu iş sitede hiç yapılmıyor; bilerekse sorun değil.', 'gbc-core' );
		$seviye = $aktif ? 'kalsin' : 'pasif_bos';

		if ( isset( $harita[ $sid ] ) ) {
			$m     = $harita[ $sid ][0];
			$modul = $m['dosya'];
			$d     = isset( $durum[ $m['dosya'] ]['durum'] ) ? $durum[ $m['dosya'] ]['durum'] : 'bilinmiyor';

			if ( ! $aktif ) {
				/* Snippet kapalı. Asıl soru: modül devraldı mı? */
				if ( 'eklenti' === $d ) {
					$karar  = sprintf( __( 'TAMAM — snippet kapalı, işi eklenti yapıyor (%s).', 'gbc-core' ), $m['ad'] );
					$seviye = 'devir';
				} elseif ( 'kalinti' === $d ) {
					$karar  = sprintf( __( 'ÖNBELLEK KALINTISI — snippet kapalı ama kodu hâlâ çalışıyor, “%s” devralamıyor. LiteSpeed → Purge All + nesne önbelleğini temizle.', 'gbc-core' ), $m['ad'] );
					$seviye = 'sorun';
				} elseif ( 'kapali' === $d ) {
					$karar  = sprintf( __( 'AÇIKTA — snippet kapalı, “%s” modülü de elle kapatılmış. Bu iş sitede HİÇ yapılmıyor.', 'gbc-core' ), $m['ad'] );
					$seviye = 'sorun';
				} elseif ( 'dosya-yok' === $d ) {
					$karar  = sprintf( __( 'AÇIKTA — snippet kapalı ve “%s” modül dosyası yok. Bu iş sitede HİÇ yapılmıyor.', 'gbc-core' ), $m['ad'] );
					$seviye = 'sorun';
				} else {
					$karar  = sprintf( __( 'AÇIKTA — snippet kapalı ama “%s” modülü de yüklenmemiş görünüyor. Kontrol et.', 'gbc-core' ), $m['ad'] );
					$seviye = 'sorun';
				}
			} elseif ( 'eklenti' === $d ) {
				$karar  = sprintf( __( 'PASİFE AL — eklenti sürümü çalışıyor (%s). Snippet boşuna duruyor.', 'gbc-core' ), $m['ad'] );
				$seviye = 'pasif';
				$pasif_al++;
			} elseif ( 'kalinti' === $d ) {
				$karar  = sprintf( __( 'ÖNBELLEK KALINTISI — snippet kapalı ama kodu hâlâ bellekte, bu yüzden “%s” yüklenmiyor. LiteSpeed → Purge All + nesne önbelleğini temizle.', 'gbc-core' ), $m['ad'] );
				$seviye = 'sorun';
			} elseif ( 'wpcode' === $d || 'wpcode-acik' === $d ) {
				$karar  = sprintf( __( 'PASİFE AL — modül bunu bekliyor, snippet açıkken “%s” yüklenmiyor.', 'gbc-core' ), $m['ad'] );
				$seviye = 'bekliyor';
				$bekleyen++;
			} elseif ( 'kapali' === $d ) {
				$karar  = sprintf( __( 'KALSIN — “%s” modülü elle kapatılmış, işi bu snippet yapıyor.', 'gbc-core' ), $m['ad'] );
				$seviye = 'kalsin';
			} elseif ( 'dosya-yok' === $d ) {
				$karar  = sprintf( __( 'KALSIN — “%s” modül dosyası eksik, snippet ayakta tutuyor.', 'gbc-core' ), $m['ad'] );
				$seviye = 'sorun';
			} else {
				$karar  = sprintf( __( 'KALSIN — “%s” modülü yükleyici tarafından işaretlenmemiş.', 'gbc-core' ), $m['ad'] );
				$seviye = 'sorun';
			}
		} elseif ( in_array( (int) $sid, (array) $css_liste, true ) ) {
			$karar  = __( 'KALSIN — şablon CSS’i. Eklenti bunu dosyaya çeviriyor, satır içi basılmıyor.', 'gbc-core' );
			$seviye = 'kalsin';
			$modul  = '01-sablon-css.php';
		}

		if ( ! empty( $sn['hata'] ) ) {
			$karar  = __( 'HATA — WPCode bu snippet’te hata bildiriyor. ', 'gbc-core' ) . $karar;
			$seviye = 'sorun';
		}

		$satirlar[] = array(
			'id'     => $sid,
			'baslik' => $sn['baslik'],
			'tur'    => $sn['tur'],
			'konum'  => $sn['konum'],
			'aktif'  => $aktif,
			'hata'   => ! empty( $sn['hata'] ),
			'modul'  => $modul,
			'karar'  => $karar,
			'seviye' => $seviye,
		);
	}

	/* Önce iş gerektirenler. */
	$sira = array( 'sorun' => 0, 'bekliyor' => 1, 'pasif' => 2, 'kalsin' => 3, 'devir' => 4, 'pasif_bos' => 5 );
	usort( $satirlar, static function ( $a, $b ) use ( $sira ) {
		$sa = isset( $sira[ $a['seviye'] ] ) ? $sira[ $a['seviye'] ] : 9;
		$sb = isset( $sira[ $b['seviye'] ] ) ? $sira[ $b['seviye'] ] : 9;
		return ( $sa !== $sb ) ? $sa - $sb : strcmp( $a['baslik'], $b['baslik'] );
	} );

	echo '<h2 id="kaynak" style="margin-top:30px">' . esc_html__( 'Kaynak haritası — hangi kod nereden çalışıyor', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'WPCode’daki bütün snippet’ler — açık olanlar da kapalı olanlar da. Her biri için tek karar yazılı. Eklenti bir modülü, WPCode’daki kopyası açıkken kendiliğinden yüklemez: çift çalışma riski yok, ama kopya açık kaldığı sürece eklentinin yeni sürümü de devreye girmez. Kapalı snippet’ler de listede, çünkü asıl tehlike şu: snippet kapatılmış ama devralması gereken modül de çalışmıyorsa o iş sitede HİÇ yapılmıyor demektir — o satır kırmızı çıkar.', 'gbc-core' )
		. '</p>';

	$acik   = 0; $devir = 0; $sorunlu = 0;
	foreach ( $satirlar as $r ) {
		if ( ! empty( $r['aktif'] ) ) { $acik++; }
		if ( 'devir' === $r['seviye'] ) { $devir++; }
		if ( 'sorun' === $r['seviye'] ) { $sorunlu++; }
	}

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:14px 0">';
	echo gbc_seo_kart( __( 'Açık snippet', 'gbc-core' ), number_format_i18n( $acik ),
		sprintf( __( 'toplam %d', 'gbc-core' ), count( $satirlar ) ) );
	echo gbc_seo_kart( __( 'Modülü bekletiyor', 'gbc-core' ), number_format_i18n( $bekleyen ),
		__( 'önce bunları kapat', 'gbc-core' ), $bekleyen ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Pasife alınabilir', 'gbc-core' ), number_format_i18n( $pasif_al ),
		__( 'eklenti zaten yapıyor', 'gbc-core' ), $pasif_al ? '#8A6100' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Eklentiye devredildi', 'gbc-core' ), number_format_i18n( $devir ),
		__( 'snippet kapalı, iş görülüyor', 'gbc-core' ), '#1A7F37' );
	echo gbc_seo_kart( __( 'Açıkta kalan iş', 'gbc-core' ), number_format_i18n( $sorunlu ),
		__( 'ne snippet ne modül', 'gbc-core' ), $sorunlu ? '#B3261E' : '#1A7F37' );
	echo '</div>';

	$renk = array(
		'sorun'     => array( '#B3261E', '#FDF2F2', __( 'AÇIKTA', 'gbc-core' ) ),
		'bekliyor'  => array( '#B3261E', '#FDF2F2', __( 'ÖNCE BUNU KAPAT', 'gbc-core' ) ),
		'pasif'     => array( '#8A6100', '#FCF6E8', __( 'pasife al', 'gbc-core' ) ),
		'kalsin'    => array( '#1A7F37', '#EEF7F0', __( 'kalsın', 'gbc-core' ) ),
		'devir'     => array( '#0B5B55', '#EAF4F3', __( 'devredildi', 'gbc-core' ) ),
		'pasif_bos' => array( '#8A919C', '#F4F5F6', __( 'pasif', 'gbc-core' ) ),
	);

	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th style="width:130px">' . esc_html__( 'Karar', 'gbc-core' ) . '</th>'
		. '<th style="width:70px">ID</th>'
		. '<th style="width:290px">' . esc_html__( 'Snippet', 'gbc-core' ) . '</th>'
		. '<th style="width:90px">' . esc_html__( 'WPCode', 'gbc-core' ) . '</th>'
		. '<th style="width:180px">' . esc_html__( 'Eklentideki karşılığı', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Neden', 'gbc-core' ) . '</th></tr></thead><tbody>';

	foreach ( $satirlar as $r ) {
		$rk = isset( $renk[ $r['seviye'] ] ) ? $renk[ $r['seviye'] ] : $renk['kalsin'];
		echo '<tr' . ( 'kalsin' !== $r['seviye'] ? ' style="background:' . esc_attr( $rk[1] ) . '"' : '' ) . '>';
		echo '<td><span style="display:inline-block;padding:2px 9px;border-radius:12px;font-size:11.5px;font-weight:700;color:'
			. esc_attr( $rk[0] ) . ';background:#fff;border:1px solid ' . esc_attr( $rk[0] ) . '">'
			. esc_html( $rk[2] ) . '</span></td>';
		echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=wpcode&snippet_id=' . (int) $r['id'] ) ) . '">'
			. (int) $r['id'] . '</a></td>';
		echo '<td><strong>' . esc_html( $r['baslik'] ) . '</strong>'
			. '<div style="color:#8A919C;font-size:11px">' . esc_html( $r['tur'] )
			. ( $r['konum'] ? ' · ' . esc_html( $r['konum'] ) : '' ) . '</div></td>';
		echo '<td>' . ( ! empty( $r['aktif'] )
			? '<span style="color:#1A7F37;font-weight:700;font-size:12px">' . esc_html__( 'açık', 'gbc-core' ) . '</span>'
			: '<span style="color:#8A919C;font-size:12px">' . esc_html__( 'kapalı', 'gbc-core' ) . '</span>' )
			. ( ! empty( $r['hata'] ) ? '<div style="color:#B3261E;font-size:11px;font-weight:700">' . esc_html__( 'hata', 'gbc-core' ) . '</div>' : '' )
			. '</td>';
		echo '<td>' . ( $r['modul'] ? '<code style="font-size:11.5px">' . esc_html( $r['modul'] ) . '</code>' : '<span style="color:#B9BDC5">—</span>' ) . '</td>';
		echo '<td style="font-size:13px">' . esc_html( $r['karar'] ) . '</td></tr>';
	}
	echo '</tbody></table>';

	if ( $bekleyen || $pasif_al ) {
		echo '<p style="max-width:1000px;color:#5C6470;font-size:13px;margin-top:10px">'
			. esc_html__( 'Nasıl yapılır: WPCode → Kod Parçacıkları → satırdaki anahtarı kapat. Silme; pasife al yeter. Kapattıktan sonra bu ekranı yenile — satır “kalsın”a dönerse eklenti devralmış demektir.', 'gbc-core' )
			. '</p>';
	}
}
}
