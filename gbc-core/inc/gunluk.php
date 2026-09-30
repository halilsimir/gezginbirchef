<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Günlük Kontrol ve Açık Sorunlar.
 *
 * NEDEN VAR: Nöbetçi, Güvenlik ve Hız ayrı ayrı çalışıyordu; biri sorun
 * bulsa bile o ekrana girmezsen haberin olmuyordu. Artık dördü her gün
 * aynı turda çalışır ve bulunan her sorun TEK bir deftere yazılır.
 *
 * KALICI UYARI: bir sorun çözülene kadar kapanmaz. Her GBC ekranının
 * tepesinde durur, kaç gündür açık olduğunu yazar. Ertesi günkü kontrol
 * sorunu bulamazsa sorun kendiliğinden kapanır — "düzelttim" demek
 * yetmez, ölçüm kapatır.
 *
 * SORUN KİMLİĞİ: her sorunun sabit bir kimliği vardır (örn. "gv-readme").
 * Aynı sorun ertesi gün yine bulunursa yeni kayıt açılmaz, ilk görülme
 * tarihi korunur. Böylece "bu 9 gündür açık" bilgisi doğru kalır.
 */

define( 'GBC_SORUN',        'gbc_sorunlar' );
define( 'GBC_GUNLUK_SON',   'gbc_gunluk_son' );

/* ============================================================
   SORUN DEFTERİ
   ============================================================ */

function gbc_sorunlar() {
	$s = get_option( GBC_SORUN, array() );
	return is_array( $s ) ? $s : array();
}

/* ============================================================
   KURAL SÜRÜMÜ — B1
   ------------------------------------------------------------
   Bir sorun HANGİ KURALLA açıldığını yanında taşır. Kural değişince
   (eklenti sürümü ya da Kontrol Merkezi kural sürümü) eski kuralla
   açılmış sorunlar artık geçerli sayılmaz: bir sonraki turda yeniden
   değerlendirilir, hâlâ varsa yeniden açılır, yoksa kendiliğinden
   kapanır ve "yanlış alarmdı" diye günlüğe yazılır.

   ÖLÇÜLEN SORUN (29 Eylül 2026): 53 sorun aylardır açıktı çünkü
   gbc_sorun_temizle() 'nobetci' alanı için HİÇ çağrılmıyordu —
   güvenlik, hız, ga4 ve kod için çağrılıyordu ama nöbetçi için değil.
   Açılan her nk-* sorunu sonsuza kadar açık kalıyordu.
   ============================================================ */

define( 'GBC_SORUN_KAPANAN', 'gbc_sorun_kapanan' );

/**
 * Bugünün kural imzası. Değişirse açık sorunlar yeniden değerlendirilir.
 *
 * 30 Eyl 2026 · ÖLÇÜLEN KUSUR — imza GBC_CORE_SURUM'a bağlıydı.
 * Yani eklentinin HER sürümü, kuralla hiç ilgisi olmasa bile, açık
 * sorunların tamamını süpürüyordu. Tek bir günde üç kez oldu
 * (1.28.0→1.28.1, 1.29.0→1.30.0, 1.30.0→1.30.1) ve her seferinde
 * gerçek tablo silindi: "298 sayfada boş alan", "6 yazının kalıcı
 * bağlantısı boş", "CCSS kapalı" hepsi kapandı sanıldı. Defter
 * "1 açık sorun" gösteriyordu ama sorunlar duruyordu.
 * Bu, "sorun GİZLENEREK sıfıra inilmeyecek" kuralının ihlaliydi.
 *
 * ARTIK: imza yalnız GBC_KURAL_SURUM'a bağlı. O sabit SADECE bir
 * tespit kuralı gerçekten değiştiğinde elle artırılır; sıradan sürüm
 * yükseltmesi defteri artık süpürmez.
 */
function gbc_sorun_kural_surumu() {
	$parca = array(
		defined( 'GBC_KURAL_SURUM' ) ? GBC_KURAL_SURUM : '1',
		defined( 'GBC_NK_KURAL_SURUM' ) ? GBC_NK_KURAL_SURUM : '0',
	);
	return implode( '/', $parca );
}

/**
 * Saklanan imza ESKİ biçimde mi? (ilk parçası eklenti sürümü: 1.30.1/5)
 *
 * Geçiş bir kereliğine sorunları süpürecekti; süpürmemesi için eski
 * biçimi tanıyıp yalnız damgayı yeniliyoruz. Açık sorunlar yerinde kalır.
 */
function gbc_sorun_eski_imza_mi( $imza ) {
	if ( ! is_string( $imza ) || '' === $imza ) { return false; }
	$ilk = explode( '/', $imza );
	/* Eklenti sürümü üç parçalı: 1.30.1. Kural sürümü tek sayı: 1. */
	return (bool) preg_match( '/^\d+\.\d+\.\d+$/', $ilk[0] );
}

/** Sorun bugünün kurallarıyla mı açılmış? */
function gbc_sorun_guncel_mi( $x ) {
	if ( ! is_array( $x ) ) { return false; }
	return isset( $x['ks'] ) && $x['ks'] === gbc_sorun_kural_surumu();
}

/**
 * Kapanan sorunun kaydı — "neden kapandı" sorusu cevapsız kalmasın.
 * Son 200 kayıt tutulur.
 */
function gbc_sorun_kapanis_yaz( $id, $x, $sebep ) {
	$g = get_option( GBC_SORUN_KAPANAN );
	if ( ! is_array( $g ) ) { $g = array(); }
	array_unshift( $g, array(
		'id'     => $id,
		'baslik' => isset( $x['baslik'] ) ? $x['baslik'] : $id,
		'alan'   => isset( $x['alan'] ) ? $x['alan'] : '',
		'acildi' => isset( $x['ilk_gorulme'] ) ? (int) $x['ilk_gorulme'] : 0,
		'kapandi'=> time(),
		'sebep'  => (string) $sebep,
		'ks'     => isset( $x['ks'] ) ? $x['ks'] : '',
	) );
	if ( count( $g ) > 200 ) { $g = array_slice( $g, 0, 200 ); }
	update_option( GBC_SORUN_KAPANAN, $g, false );
}

/** Kapanan sorunların günlüğü. */
function gbc_sorun_kapananlar() {
	$g = get_option( GBC_SORUN_KAPANAN );
	return is_array( $g ) ? $g : array();
}

/* ============================================================
   KABUL EDİLENLER — B6
   ------------------------------------------------------------
   Bilerek alınmış karar (ör. "UCSS kapalı kalsın") sorun sayılmaz ama
   KAYBOLMAZ da: ayrı listede tarihi, gerekçesi ve kararı verenle durur.
   Ölçülen değer kötüleşirse kabul kendiliğinden düşer ve sorun geri açılır.
   ============================================================ */

define( 'GBC_KABUL', 'gbc_kabul' );

function gbc_kabuller() {
	$k = get_option( GBC_KABUL );
	return is_array( $k ) ? $k : array();
}

/**
 * Bir sorunu "kabul edildi" diye işaretler.
 *
 * @param string $id      Sorun kimliği.
 * @param string $gerekce Neden böyle bırakıldığı.
 * @param string $kim     Kararı veren.
 * @param array  $olcu    array( 'ad' => 'JS boyutu', 'deger' => 176000, 'yon' => 'artarsa' )
 */
function gbc_kabul_et( $id, $gerekce, $kim = 'Halil', $olcu = array() ) {
	$k  = gbc_kabuller();
	$id = sanitize_key( $id );
	$s  = gbc_sorunlar();

	$k[ $id ] = array(
		'gerekce' => (string) $gerekce,
		'kim'     => (string) $kim,
		'tarih'   => time(),
		'baslik'  => isset( $s[ $id ]['baslik'] ) ? $s[ $id ]['baslik'] : $id,
		'alan'    => isset( $s[ $id ]['alan'] ) ? $s[ $id ]['alan'] : '',
		'olcu'    => is_array( $olcu ) ? $olcu : array(),
	);
	update_option( GBC_KABUL, $k, false );

	if ( isset( $s[ $id ] ) ) {
		gbc_sorun_kapanis_yaz( $id, $s[ $id ], 'kabul edildi: ' . $gerekce );
		unset( $s[ $id ] );
		update_option( GBC_SORUN, $s, false );
	}
}

/** Kabulü geri alır. */
function gbc_kabul_kaldir( $id ) {
	$k  = gbc_kabuller();
	$id = sanitize_key( $id );
	if ( ! isset( $k[ $id ] ) ) { return; }
	unset( $k[ $id ] );
	update_option( GBC_KABUL, $k, false );
}

/**
 * Kabul hâlâ geçerli mi? Ölçü kötüleştiyse değil.
 *
 * @param string     $id     Sorun kimliği.
 * @param float|null $simdi  Bugünkü ölçüm; null ise ölçü denetlenmez.
 * @return bool
 */
function gbc_kabul_gecerli_mi( $id, $simdi = null ) {
	$k  = gbc_kabuller();
	$id = sanitize_key( $id );
	if ( ! isset( $k[ $id ] ) ) { return false; }
	if ( null === $simdi || empty( $k[ $id ]['olcu']['deger'] ) ) { return true; }

	$taban = (float) $k[ $id ]['olcu']['deger'];
	$yon   = isset( $k[ $id ]['olcu']['yon'] ) ? $k[ $id ]['olcu']['yon'] : 'artarsa';
	$pay   = isset( $k[ $id ]['olcu']['pay'] ) ? (float) $k[ $id ]['olcu']['pay'] : 1.15;

	$kotu = ( 'azalirsa' === $yon )
		? ( (float) $simdi < $taban / $pay )
		: ( (float) $simdi > $taban * $pay );

	if ( $kotu ) {
		/* Kabul düştü: karar bir daha sorulmalı. */
		gbc_kabul_kaldir( $id );
		return false;
	}
	return true;
}

/** Sorunu açar ya da zaten açıksa "bugün de görüldü" diye işaretler. */
function gbc_sorun_ac( $id, $alan, $baslik, $aciklama = '', $onem = 'orta', $adres = '', $olcu = null ) {
	$id = sanitize_key( $id );

	/* Bilerek alınmış karar: sorun açılmaz — ama ölçü kötüleştiyse kabul düşer
	   ve sorun normal şekilde açılır. */
	if ( gbc_kabul_gecerli_mi( $id, $olcu ) ) { return; }

	$s = gbc_sorunlar();
	if ( isset( $s[ $id ] ) ) {
		$s[ $id ]['son_gorulme'] = time();
		$s[ $id ]['baslik']      = $baslik;
		$s[ $id ]['aciklama']    = $aciklama;
		$s[ $id ]['onem']        = $onem;
		$s[ $id ]['ks']          = gbc_sorun_kural_surumu();
	} else {
		$s[ $id ] = array(
			'alan'         => $alan,
			'baslik'       => $baslik,
			'aciklama'     => $aciklama,
			'onem'         => $onem,
			'adres'        => $adres,
			'ilk_gorulme'  => time(),
			'son_gorulme'  => time(),
			'ks'           => gbc_sorun_kural_surumu(),
		);
	}
	if ( null !== $olcu ) { $s[ $id ]['olcu'] = $olcu; }
	update_option( GBC_SORUN, $s, false );
}

/** Sorun bu turda bulunmadıysa kapanır. Kapanış tarihi ayrı tutulur. */
function gbc_sorun_kapat( $id, $sebep = 'düzeldi' ) {
	$s = gbc_sorunlar();
	$id = sanitize_key( $id );
	if ( ! isset( $s[ $id ] ) ) { return; }
	gbc_sorun_kapanis_yaz( $id, $s[ $id ], $sebep );
	unset( $s[ $id ] );
	update_option( GBC_SORUN, $s, false );
}

/**
 * Bir SAYFAYA ait sorunları kapatır — B1.
 *
 * Nöbetçi her turda sayfaların yalnız bir kısmını tarar. Alan geneli
 * temizlik yapılırsa taranmamış sayfaların gerçek sorunları da kapanır.
 * Bu yüzden temizlik SAYFA BAZLI: bir sayfa yeniden tarandığında, o
 * sayfaya ait olup bu turda bulunmayan sorunlar kapanır, başka sayfalara
 * dokunulmaz.
 *
 * @param int   $pid       Sayfa kimliği.
 * @param array $bulunanlar Bu turda o sayfa için açık kalması gereken kimlikler.
 * @param string $sebep
 * @return int Kapatılan sorun sayısı.
 */
function gbc_sorun_sayfa_temizle( $pid, $bulunanlar, $sebep = 'sayfa yeniden tarandı, sorun görülmedi' ) {
	$pid = (int) $pid;
	if ( ! $pid ) { return 0; }
	$s = gbc_sorunlar();
	$n = 0;
	foreach ( $s as $id => $x ) {
		if ( ! preg_match( '/^nk-.*-' . $pid . '$/', $id ) ) { continue; }
		if ( in_array( $id, (array) $bulunanlar, true ) ) { continue; }
		gbc_sorun_kapanis_yaz( $id, $x, $sebep );
		unset( $s[ $id ] );
		$n++;
	}
	if ( $n ) { update_option( GBC_SORUN, $s, false ); }
	return $n;
}

/**
 * Kural sürümü değişmişse eski kuralla açılmış sorunları süpürür — B1.
 *
 * Bunlar silinmez, "yeniden değerlendirilecek" diye işaretlenir; ilgili
 * sayfa/alan yeniden tarandığında hâlâ varsa yeniden açılır. Sayfası
 * olan nöbetçi sorunları için sayfa da "yeniden taranacak" kuyruğuna girer.
 *
 * @return array array( islendi, kapanan )
 */
function gbc_sorun_kural_supur() {
	$simdi = gbc_sorun_kural_surumu();
	$son   = (string) get_option( 'gbc_sorun_ks', '' );
	if ( $son === $simdi ) { return array( 'islendi' => false, 'kapanan' => 0 ); }

	/* GEÇİŞ: eski imza (eklenti sürümlü) → yeni imza. Süpürme YOK;
	   açık sorunlar yeni damgayla olduğu gibi kalır. */
	if ( gbc_sorun_eski_imza_mi( $son ) ) {
		$s = gbc_sorunlar();
		foreach ( $s as $id => $x ) { $s[ $id ]['ks'] = $simdi; }
		update_option( GBC_SORUN, $s, false );
		update_option( 'gbc_sorun_ks', $simdi, false );
		return array( 'islendi' => true, 'kapanan' => 0, 'gecis' => true,
		              'damgalanan' => count( $s ), 'eski' => $son, 'yeni' => $simdi );
	}

	$s = gbc_sorunlar();
	$n = 0;
	foreach ( $s as $id => $x ) {
		if ( gbc_sorun_guncel_mi( $x ) ) { continue; }
		gbc_sorun_kapanis_yaz( $id, $x,
			'kural değişti (' . $son . ' → ' . $simdi . '); yeniden değerlendirilecek' );
		unset( $s[ $id ] );
		$n++;
	}
	update_option( GBC_SORUN, $s, false );
	update_option( 'gbc_sorun_ks', $simdi, false );

	/* Bütün sayfalar yeniden taranacak: kural değişti, eski sonuçlar geçersiz. */
	if ( defined( 'GBC_NK_ZORLA' ) ) { update_option( GBC_NK_ZORLA, time(), false ); }

	return array( 'islendi' => true, 'kapanan' => $n, 'eski' => $son, 'yeni' => $simdi );
}
add_action( 'admin_init', 'gbc_sorun_kural_supur', 5 );

/**
 * Bir alanın bu turda bulunmayan sorunlarını kapatır.
 *
 * 29 Eylül 2026 — DÜZELTİLDİ. Eskiden bir de zaman koşulu vardı:
 * "son_gorulme >= tur_zamani ise dokunma". Amaç bu turda görülen sorunu
 * korumaktı, ama $bulunanlar listesi bunu zaten söylüyor. Koşul aynı
 * saniye içinde açılıp kapanması gereken sorunları temizlenemez hâle
 * getiriyordu (tur bir saniyeden kısa sürerse HİÇBİR ŞEY kapanmıyordu).
 * Artık tek ölçüt liste: bu turda bulunmayan kapanır.
 *
 * @param string $alan        Alan adı.
 * @param array  $bulunanlar  Açık kalması gereken kimlikler.
 * @param int    $tur_zamani  Kullanılmıyor; eski çağrılar bozulmasın diye duruyor.
 * @return int Kapatılan sorun sayısı.
 */
function gbc_sorun_temizle( $alan, $bulunanlar, $tur_zamani = 0 ) {
	$s = gbc_sorunlar();
	$n = 0;
	foreach ( $s as $id => $x ) {
		if ( $x['alan'] !== $alan ) { continue; }
		if ( in_array( $id, (array) $bulunanlar, true ) ) { continue; }
		gbc_sorun_kapanis_yaz( $id, $x, 'bu turda bulunmadı' );
		unset( $s[ $id ] );
		$n++;
	}
	if ( $n ) { update_option( GBC_SORUN, $s, false ); }
	return $n;
}

function gbc_sorun_gun( $x ) {
	return max( 0, (int) floor( ( time() - (int) $x['ilk_gorulme'] ) / DAY_IN_SECONDS ) );
}

/* ============================================================
   GÜNLÜK TUR
   ============================================================ */

/**
 * Günlük tur.
 *
 * @param int $kat Sayfa taramasının kaç katı geniş olacağı. Elle
 *                 basıldığında 3, cron'da 1 (yani normalin iki katı).
 */
function gbc_gunluk_calistir( $kat = 1 ) {
	/* v1.43.1: günlük kontrol en uzun iş; kilidi 30 dk tutar. Kilit doluysa 10 dk sonra yeniden denenir. */
	if ( function_exists( 'gbc_kilit_al' ) && ! gbc_kilit_al( 'gunluk', 1800 ) ) {
		if ( function_exists( 'wp_schedule_single_event' ) ) { wp_schedule_single_event( time() + 600, 'gbc_gunluk_kontrol' ); }
		return array( 'zaman' => time(), 'alan' => array(), 'atlandi' => 1 );
	}
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 900 ); }
	ignore_user_abort( true );

	$bas = microtime( true );
	$tur = time();
	$rapor = array( 'zaman' => $tur, 'alan' => array() );

	/* --- 1) KONTROL MERKEZİ — kod çalışıyor mu, motor sayfada mı ---
	   Tek tur: önce PHP tarafı (kod sağlığı), sonra canlı sayfa taraması.
	   İkisi de kendi sorunlarını deftere yazar. */
	if ( function_exists( 'gbc_kod_tara' ) ) {
		$kod = gbc_kod_tara( true );
		$rapor['alan']['kod'] = (int) $kod['sorun'];
	}

	if ( function_exists( 'gbc_nk_tur' ) ) {
		/* Günlük turda normalin iki katı sayfa taranır; elle basıldığında
		   $kat ile çarpılır, böylece "Şimdi kontrol et" gerçekten çalışır. */
		$taban = defined( 'GBC_NK_ADET' ) ? GBC_NK_ADET * 2 : 18;
		$nk = gbc_nk_tur( $taban * max( 1, (int) $kat ) );
		$rapor['alan']['nobetci']       = (int) $nk['sorun'];
		$rapor['alan']['nobetci_sayfa'] = (int) $nk['sayfa'];
		$rapor['alan']['kapanan']       = isset( $nk['kapanan'] ) ? (int) $nk['kapanan'] : 0;
	}

	/* --- 2) GÜVENLİK --- */
	if ( function_exists( 'gbc_gv_tara' ) ) {
		$g = gbc_gv_tara();
		$acik = get_option( defined( 'GBC_GV_UYARI' ) ? GBC_GV_UYARI : 'gbc_gv_uyari', array() );

		$bulunan = array();
		foreach ( (array) $acik as $ad ) {
			$id = 'gv-' . substr( md5( (string) $ad ), 0, 10 );
			$bulunan[] = $id;
			gbc_sorun_ac( $id, 'guvenlik', (string) $ad,
				sprintf( __( 'Güvenlik puanı %d/100.', 'gbc-core' ), isset( $g['skor'] ) ? (int) $g['skor'] : 0 ),
				'yuksek', admin_url( 'admin.php?page=gbc-guvenlik' ) );
		}
		gbc_sorun_temizle( 'guvenlik', $bulunan, $tur );
		$rapor['alan']['guvenlik'] = count( $bulunan );
	}

	/* --- 3) HIZ & SAĞLIK --- */
	if ( function_exists( 'gbc_hz_tara' ) ) {
		$h = gbc_hz_tara( false );
		$bulunan = array();

		if ( function_exists( 'gbc_hz_oneriler' ) && $h ) {
			foreach ( (array) gbc_hz_oneriler( $h ) as $o ) {
				if ( 'yuksek' !== $o['onem'] ) { continue; }
				$id = 'hz-' . substr( md5( (string) $o['baslik'] ), 0, 10 );
				$bulunan[] = $id;
				gbc_sorun_ac( $id, 'hiz', (string) $o['baslik'], (string) $o['neden'], 'yuksek',
					admin_url( 'admin.php?page=gbc-hiz' ) );
			}
		}
		gbc_sorun_temizle( 'hiz', $bulunan, $tur );
		$rapor['alan']['hiz'] = count( $bulunan );
	}

	/* --- 4) GA4 ÖLÇÜM BÜTÜNLÜĞÜ --- */
	$ga = gbc_ga4_denetim();
	$bulunan = array();
	foreach ( $ga['eksik'] as $id => $metin ) {
		$bulunan[] = $id;
		gbc_sorun_ac( $id, 'ga4', $metin, __( 'GA4 ölçümü eksik veri gönderiyor.', 'gbc-core' ), 'orta',
			admin_url( 'admin.php?page=gbc-gunluk' ) );
	}
	gbc_sorun_temizle( 'ga4', $bulunan, $tur );
	$rapor['alan']['ga4'] = count( $bulunan );

	/* --- 5) KONTROLÜN KENDİSİ — B8 --- */
	gbc_sistem_denetle();

	$rapor['ms'] = (int) round( ( microtime( true ) - $bas ) * 1000 );
	$rapor['acik'] = count( gbc_sorunlar() );
	update_option( GBC_GUNLUK_SON, $rapor, false );

	return $rapor;
}

/* ============================================================
   ÖZ DENETİM — B8
   ------------------------------------------------------------
   Kontrol sisteminin kendisi durursa kimse haber vermiyordu: ekran
   yeşil yanmaya devam ediyordu. Hız panelinde bu yaşandı (DEFTER 34.1):
   hiçbir şablon ölçülemezken panel "her şey eşiklerin içinde" diyordu.

   Artık üç şey denetleniyor:
     1. Günlük tur 24 saatten fazladır çalışmadı mı?
     2. Kontrol Merkezi taraması 24 saatten eski mi?
     3. Zamanlanmış iş hiç kurulu mu?
   ============================================================ */
function gbc_sistem_denetle() {
	if ( ! function_exists( 'gbc_sorun_ac' ) ) { return array(); }
	$bulunan = array();
	$sinir   = 24 * HOUR_IN_SECONDS;

	/* 1) Günlük tur */
	$son = get_option( GBC_GUNLUK_SON );
	$son_zaman = ( is_array( $son ) && ! empty( $son['zaman'] ) ) ? (int) $son['zaman'] : 0;
	if ( ! $son_zaman ) {
		$bulunan[] = 'sistem-tur-yok';
		gbc_sorun_ac( 'sistem-tur-yok', 'sistem',
			__( 'Günlük kontrol hiç çalışmamış', 'gbc-core' ),
			__( 'Hiç tur kaydı yok. Ekrandaki yeşil renk "sorun yok" demek değil, "bilgi yok" demek.', 'gbc-core' ),
			'yuksek', admin_url( 'admin.php?page=gbc-cron' ) );
	} elseif ( ( time() - $son_zaman ) > $sinir ) {
		$bulunan[] = 'sistem-tur-eski';
		gbc_sorun_ac( 'sistem-tur-eski', 'sistem',
			__( 'Günlük kontrol 24 saattir çalışmadı', 'gbc-core' ),
			sprintf( __( 'Son tur %s önce. Zamanlanmış iş çalışmıyor olabilir; ekrandaki sayılar eski.', 'gbc-core' ),
				human_time_diff( $son_zaman ) ),
			'yuksek', admin_url( 'admin.php?page=gbc-cron' ) );
	}

	/* 2) Kontrol Merkezi taraması */
	if ( function_exists( 'gbc_nk_son_tur' ) ) {
		$nk = gbc_nk_son_tur();
		if ( $nk && ( time() - (int) $nk ) > $sinir ) {
			$bulunan[] = 'sistem-nk-eski';
			gbc_sorun_ac( 'sistem-nk-eski', 'sistem',
				__( 'Sayfa taraması 24 saatten eski', 'gbc-core' ),
				sprintf( __( 'Son tarama %s önce. Motor durmuş olsa bile fark edilmez.', 'gbc-core' ),
					human_time_diff( (int) $nk ) ),
				'yuksek', admin_url( 'admin.php?page=gbc-nobetci-kural' ) );
		}
	}

	/* 3) Zamanlanmış iş kurulu mu */
	if ( ! wp_next_scheduled( 'gbc_gunluk_kontrol' ) ) {
		$bulunan[] = 'sistem-cron-yok';
		gbc_sorun_ac( 'sistem-cron-yok', 'sistem',
			__( 'Günlük kontrol zamanlanmamış', 'gbc-core' ),
			__( 'gbc_gunluk_kontrol işi kurulu değil. Kontrol kendiliğinden hiç çalışmaz.', 'gbc-core' ),
			'yuksek', admin_url( 'admin.php?page=gbc-cron' ) );
	}

	/* 4) SITE HARITASI — 29 Eylül 2026'da SESSİZCE düşmüştü.
	 *
	 * OLAN: /sitemap_index.xml, /post-sitemap.xml ve diğerleri 404
	 * dönüyordu; robots.txt'deki "Sitemap:" satırı da kaybolmuştu.
	 * Site çalışıyordu, sayfalar açılıyordu, panelde tek alarm yoktu —
	 * yalnızca Google'a giden harita yoktu. Tesadüfen fark edildi.
	 *
	 * Sebep: Rank Math'in sitemap yönlendirme kuralı WordPress'in kural
	 * tablosundan düşmüştü. Üretici sağlamdı (/?sitemap=1 çalışıyordu),
	 * yalnız güzel adres bağlı değildi. Kalıcı bağlantıların yeniden
	 * kaydedilmesi düzeltti.
	 *
	 * Eklenti kurulumu, tema güncellemesi ya da herhangi bir kural
	 * tablosu yenilenmesi bunu tekrar kırabilir. Artık her gün
	 * denetleniyor; bir daha tesadüfe kalmayacak. */
	$bulunan = array_merge( $bulunan, gbc_harita_denetle() );

	gbc_sorun_temizle( 'sistem', $bulunan, time() );
	return $bulunan;
}

/**
 * Site haritası ayakta mı?
 *
 * Yalnız HTTP kodu yetmez: 404 sayfası da 200 dönebilir, ya da harita
 * boş XML olabilir. Bu yüzden hem kod, hem XML olup olmadığı, hem de
 * içinde <loc> bulunup bulunmadığı denetleniyor.
 *
 * @return array Açılan sorun kimlikleri.
 */
function gbc_harita_denetle() {
	$bulunan = array();

	$indeks = home_url( '/sitemap_index.xml' );
	$c = wp_remote_get( $indeks, array( 'timeout' => 20, 'sslverify' => false ) );

	$kod   = is_wp_error( $c ) ? 0 : (int) wp_remote_retrieve_response_code( $c );
	$govde = is_wp_error( $c ) ? '' : (string) wp_remote_retrieve_body( $c );
	$xml   = ( '' !== $govde ) && ( 0 === strpos( ltrim( $govde ), '<?xml' ) );
	$loc   = substr_count( $govde, '<loc>' );

	if ( 200 !== $kod || ! $xml || $loc < 1 ) {
		$bulunan[] = 'sistem-harita';
		gbc_sorun_ac(
			'sistem-harita', 'sistem',
			__( 'Site haritası yayında değil', 'gbc-core' ),
			sprintf(
				/* translators: 1: HTTP kodu, 2: XML mi, 3: kac adres */
				__( '/sitemap_index.xml yanıtı: HTTP %1$d, XML: %2$s, adres: %3$d. Site çalışıyor olsa bile Google haritayı bulamaz. En sık sebep yönlendirme kurallarının düşmesidir; Ayarlar → Kalıcı Bağlantılar sayfasında hiçbir şeyi değiştirmeden "Değişiklikleri kaydet" demek düzeltir.', 'gbc-core' ),
				$kod, $xml ? __( 'evet', 'gbc-core' ) : __( 'hayır', 'gbc-core' ), $loc
			),
			'yuksek', admin_url( 'options-permalink.php' ),
			array( 'kod' => $kod, 'loc' => $loc )
		);
		return $bulunan;   /* indeks yoksa alt haritalara bakmaya gerek yok */
	}

	/* Alt haritalar — indeksin gösterdiği ilk üçü yeter. */
	preg_match_all( '#<loc>([^<]+)</loc>#i', $govde, $m );
	$bos = array();
	foreach ( array_slice( (array) $m[1], 0, 3 ) as $alt ) {
		$a = wp_remote_get( $alt, array( 'timeout' => 20, 'sslverify' => false ) );
		$ak = is_wp_error( $a ) ? 0 : (int) wp_remote_retrieve_response_code( $a );
		$ag = is_wp_error( $a ) ? '' : (string) wp_remote_retrieve_body( $a );
		if ( 200 !== $ak || substr_count( $ag, '<loc>' ) < 1 ) {
			$bos[] = basename( wp_parse_url( $alt, PHP_URL_PATH ) );
		}
	}
	if ( $bos ) {
		$bulunan[] = 'sistem-harita-alt';
		gbc_sorun_ac(
			'sistem-harita-alt', 'sistem',
			sprintf(
				/* translators: %s: harita dosya adlari */
				__( 'Alt site haritası boş ya da açılmıyor: %s', 'gbc-core' ),
				implode( ', ', $bos )
			),
			__( 'Ana harita çalışıyor ama bu alt harita adres döndürmüyor. O bölümdeki içerikler Google\'a bildirilmiyor.', 'gbc-core' ),
			'yuksek', admin_url( 'admin.php?page=rank-math-options-sitemap' ),
			array( 'bos' => count( $bos ) )
		);
	}

	/* robots.txt Sitemap satırı */
	$rb = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 15, 'sslverify' => false ) );
	if ( ! is_wp_error( $rb ) && 200 === (int) wp_remote_retrieve_response_code( $rb ) ) {
		$rg = (string) wp_remote_retrieve_body( $rb );
		if ( false === stripos( $rg, 'sitemap:' ) ) {
			$bulunan[] = 'sistem-robots-harita';
			gbc_sorun_ac(
				'sistem-robots-harita', 'sistem',
				__( 'robots.txt içinde Sitemap satırı yok', 'gbc-core' ),
				__( 'Harita yayında ama robots.txt onu göstermiyor. Arama motorlarının haritayı bulmasının en doğrudan yolu bu satırdır.', 'gbc-core' ),
				'orta', admin_url( 'admin.php?page=rank-math-options-general' )
			);
		}
	}

	return $bulunan;
}

/* Yönetici ekranı açıldığında da denetlenir: cron hiç çalışmıyorsa
   B8 sorunu yalnız cron içinde açılırsa asla görünmez. */
add_action( 'admin_init', 'gbc_sistem_denetle', 6 );

/* ============================================================
   GA4 DENETİMİ — sayfa gerçekten ne gönderiyor
   ============================================================ */

/** Beklenen olaylar ve parametreler. Kaynak: modül 30-olcum.php. */
function gbc_ga4_beklenen() {
	return array(
		'olay' => array(
			'affiliate_click' => __( 'Ortaklık bağlantısına tıklama', 'gbc-core' ),
			'outbound_click'  => __( 'Dışarı çıkan bağlantı', 'gbc-core' ),
			'share_click'     => __( 'Sosyal paylaşım', 'gbc-core' ),
			'video_click'     => __( 'Video bağlantısı', 'gbc-core' ),
			'scroll_depth'    => __( 'Sayfa kaydırma derinliği', 'gbc-core' ),
		),
		'parametre' => array(
			'page_type'   => __( 'Sayfa tipi (gezi / tarif / liste…)', 'gbc-core' ),
			'post_id'     => __( 'İçerik numarası', 'gbc-core' ),
			'link_url'    => __( 'Tıklanan adres', 'gbc-core' ),
			'link_text'   => __( 'Bağlantı metni', 'gbc-core' ),
			'link_domain' => __( 'Hedef alan adı', 'gbc-core' ),
			'aff_program' => __( 'Ortaklık programı', 'gbc-core' ),
			'aff_id'      => __( 'Ortaklık kimliği', 'gbc-core' ),
			'scroll_pct'  => __( 'Kaydırma yüzdesi', 'gbc-core' ),
		),
	);
}

/**
 * Canlı bir sayfayı indirip ölçüm kodunun gerçekten basılıp basılmadığına
 * bakar. LiteSpeed satır içi JS'i base64'e çevirdiği için çözülerek aranır.
 */
function gbc_ga4_denetim( $url = '' ) {
	if ( '' === $url ) { $url = home_url( '/' ); }

	$c = wp_remote_get( add_query_arg( 'gbc_ga', time(), $url ), array(
		'timeout'   => 25,
		'sslverify' => false,
		'headers'   => array( 'Cache-Control' => 'no-cache', 'X-LSCACHE' => 'no-cache' ),
	) );
	if ( is_wp_error( $c ) ) {
		return array( 'hata' => $c->get_error_message(), 'eksik' => array(), 'var' => array(), 'url' => $url );
	}

	$html = (string) wp_remote_retrieve_body( $c );

	/* LiteSpeed satir ici betikleri data:text/javascript;base64 yapar. */
	if ( preg_match_all( '#src=["\']data:text/javascript;base64,([^"\']+)["\']#i', $html, $m ) ) {
		foreach ( $m[1] as $b64 ) {
			$coz = base64_decode( $b64, true );
			if ( false !== $coz ) { $html .= "\n" . $coz; }
		}
	}

	$b = gbc_ga4_beklenen();
	$var = array();
	$eksik = array();

	/* Olcum kodu hic var mi */
	if ( false === strpos( $html, 'G-8P6FY60W1W' ) ) {
		$eksik['ga4-kimlik'] = __( 'GA4 ölçüm kimliği sayfada yok — hiçbir veri gitmiyor.', 'gbc-core' );
	} else {
		$var['ga4-kimlik'] = __( 'GA4 ölçüm kimliği basılıyor', 'gbc-core' );
	}

	foreach ( $b['olay'] as $olay => $ad ) {
		if ( false !== strpos( $html, $olay ) ) { $var[ 'olay-' . $olay ] = $ad; }
		else { $eksik[ 'ga4-olay-' . str_replace( '_', '-', $olay ) ] = sprintf( __( 'GA4 olayı sayfada yok: %s', 'gbc-core' ), $olay ); }
	}

	foreach ( $b['parametre'] as $p => $ad ) {
		if ( false !== strpos( $html, $p ) ) { $var[ 'par-' . $p ] = $ad; }
		else { $eksik[ 'ga4-par-' . str_replace( '_', '-', $p ) ] = sprintf( __( 'GA4 parametresi gönderilmiyor: %s', 'gbc-core' ), $p ); }
	}

	return array( 'url' => $url, 'var' => $var, 'eksik' => $eksik, 'bayt' => strlen( $html ) );
}

/* ============================================================
   CRON — her gün 05:40
   ============================================================ */
add_action( 'gbc_gunluk_kontrol', 'gbc_gunluk_calistir' );

add_action( 'init', 'gbc_gunluk_cron_kur' );
function gbc_gunluk_cron_kur() {
	if ( ! wp_next_scheduled( 'gbc_gunluk_kontrol' ) ) {
		$saat = strtotime( 'tomorrow 05:40', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		wp_schedule_event( $saat, 'daily', 'gbc_gunluk_kontrol' );
	}
}

/* ============================================================
   KALICI UYARI — her GBC ekranının tepesinde
   ============================================================ */
add_action( 'admin_notices', 'gbc_sorun_uyarisi' );
function gbc_sorun_uyarisi() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 0 !== strpos( $sayfa, 'gbc' ) ) { return; }
	/* v1.44.0: Kontrol Paneli özeti sorunları zaten en üstte gösteriyor. */
	if ( 'gbc' === $sayfa || 'gbc-gunluk' === $sayfa ) { return; }

	$s = gbc_sorunlar();
	if ( ! $s ) { return; }

	$yuksek = 0;
	foreach ( $s as $x ) { if ( 'yuksek' === $x['onem'] ) { $yuksek++; } }

	echo '<div class="notice notice-' . ( $yuksek ? 'error' : 'warning' ) . '" style="border-left-width:4px">';
	echo '<p style="font-size:14px;margin:10px 0"><strong>'
		. esc_html( sprintf( __( '%d açık sorun', 'gbc-core' ), count( $s ) ) )
		. ( $yuksek ? esc_html( sprintf( __( ' · %d tanesi yüksek öncelikli', 'gbc-core' ), $yuksek ) ) : '' )
		. '</strong> — ' . esc_html__( 'çözülene kadar burada durur.', 'gbc-core' ) . ' ';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-gunluk' ) ) . '">' . esc_html__( 'Listeyi aç', 'gbc-core' ) . '</a></p>';
	echo '</div>';
}

/* ============================================================
   EKRAN — Günlük Kontrol
   ============================================================ */
function gbc_gunluk_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	/* B6 — kabul et / kabulü geri al */
	if ( isset( $_POST['gbc_kabul_et'] ) && check_admin_referer( 'gbc_gunluk_kabul' ) ) {
		$kid = sanitize_key( wp_unslash( $_POST['gbc_kabul_et'] ) );
		$ger = sanitize_text_field( isset( $_POST['gerekce'] ) ? wp_unslash( $_POST['gerekce'] ) : '' );
		if ( '' === $ger ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Gerekçe yazmadan kabul edilmez. Altı ay sonra "bu neden böyle?" sorusunun cevabı bu satır olacak.', 'gbc-core' )
				. '</p></div>';
		} else {
			gbc_kabul_et( $kid, $ger, wp_get_current_user()->display_name );
			echo '<div class="notice notice-success"><p>'
				. esc_html__( 'Kabul edildi. Açık sorun sayısından çıktı, aşağıdaki "Kabul edilenler" listesinde duruyor. Ölçü kötüleşirse kendiliğinden geri açılır.', 'gbc-core' )
				. '</p></div>';
		}
	}
	if ( isset( $_GET['gbc_kabul_kaldir'] ) && check_admin_referer( 'gbc_gunluk' ) ) {
		gbc_kabul_kaldir( sanitize_key( wp_unslash( $_GET['gbc_kabul_kaldir'] ) ) );
		echo '<div class="notice notice-success"><p>'
			. esc_html__( 'Kabul geri alındı. Sorun bir sonraki turda yeniden değerlendirilecek.', 'gbc-core' )
			. '</p></div>';
	}

	if ( isset( $_GET['gbc_gunluk'] ) && check_admin_referer( 'gbc_gunluk' ) ) {
		/* B2: elle basınca GERÇEKTEN tara. Açık sorunu olan sayfalar sıranın
		   başında (gbc_nk_sira_al), sayfa sayısı da normal turun üç katı. */
		$r = gbc_gunluk_calistir( 3 );
		$nk = isset( $r['alan']['nobetci_sayfa'] ) ? (int) $r['alan']['nobetci_sayfa'] : 0;
		echo '<div class="notice notice-success"><p>'
			. esc_html( sprintf(
				__( 'Kontrol bitti, %1$d saniye sürdü. %2$d sayfa yeniden indirildi, %3$d sorun kapandı. Açık sorun: %4$d.', 'gbc-core' ),
				(int) round( $r['ms'] / 1000 ), $nk,
				isset( $r['alan']['kapanan'] ) ? (int) $r['alan']['kapanan'] : 0,
				(int) $r['acik'] ) ) . '</p></div>';
		if ( function_exists( 'gbc_nk_bekleyen_sayisi' ) && gbc_nk_bekleyen_sayisi() > 0 ) {
			echo '<div class="notice notice-info"><p>'
				. esc_html( sprintf(
					__( 'Hâlâ %d sayfa yeniden taranmayı bekliyor. Hepsi bir turda taranmaz; düğmeye tekrar basabilir ya da gece turunu bekleyebilirsin.', 'gbc-core' ),
					gbc_nk_bekleyen_sayisi() ) ) . '</p></div>';
		}
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Günlük Kontrol', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-gunluk' ); }

	echo '<p style="max-width:960px;color:#444">'
		. esc_html__( 'Kod sağlığı, Kontrol Merkezi sayfa taraması, Güvenlik, Hız ve GA4 ölçümü her gün 05:40’ta aynı turda çalışır. Bulunan her sorun bu deftere yazılır ve çözülene kadar kapanmaz — ertesi günkü kontrol sorunu bulamazsa kendiliğinden kapanır.', 'gbc-core' )
		. '</p>';

	$son = get_option( GBC_GUNLUK_SON, array() );
	$sonraki = wp_next_scheduled( 'gbc_gunluk_kontrol' );
	$s = gbc_sorunlar();

	$yuksek = 0; $en_eski = 0;
	foreach ( $s as $x ) {
		if ( 'yuksek' === $x['onem'] ) { $yuksek++; }
		$g = gbc_sorun_gun( $x );
		if ( $g > $en_eski ) { $en_eski = $g; }
	}

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_seo_kart( __( 'Açık sorun', 'gbc-core' ), (string) count( $s ), '',
		count( $s ) ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Yüksek öncelikli', 'gbc-core' ), (string) $yuksek, '',
		$yuksek ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'En eski sorun', 'gbc-core' ), $en_eski ? $en_eski . ' ' . __( 'gün', 'gbc-core' ) : '—',
		__( 'açık kaldığı süre', 'gbc-core' ), $en_eski > 7 ? '#B3261E' : '#14181F' );
	echo gbc_seo_kart( __( 'Son kontrol', 'gbc-core' ),
		! empty( $son['zaman'] ) ? human_time_diff( (int) $son['zaman'] ) : __( 'hiç', 'gbc-core' ),
		! empty( $son['zaman'] ) ? __( 'önce', 'gbc-core' ) : __( 'henüz çalışmadı', 'gbc-core' ) );
	echo gbc_seo_kart( __( 'Sıradaki', 'gbc-core' ),
		$sonraki ? human_time_diff( time(), $sonraki ) : __( 'kayıtsız', 'gbc-core' ),
		$sonraki ? __( 'sonra', 'gbc-core' ) : __( 'cron kurulmamış', 'gbc-core' ),
		$sonraki ? '#14181F' : '#B3261E' );
	echo '</div>';

	echo '<p><a class="button button-primary" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-gunluk&gbc_gunluk=1' ), 'gbc_gunluk' ) ) . '">'
		. esc_html__( 'Şimdi kontrol et (2-3 dakika)', 'gbc-core' ) . '</a></p>';

	/* B8 — veri yoksa ekran yeşil yanmaz. */
	if ( empty( $son['zaman'] ) ) {
		echo '<div class="notice notice-error inline" style="margin:14px 0"><p><strong>'
			. esc_html__( 'Kontrol hiç çalışmadı.', 'gbc-core' ) . '</strong> '
			. esc_html__( 'Aşağıdaki "0 açık sorun" bilgisi SORUN YOK demek değil, BİLGİ YOK demektir.', 'gbc-core' )
			. '</p></div>';
	} elseif ( ( time() - (int) $son['zaman'] ) > 24 * HOUR_IN_SECONDS ) {
		echo '<div class="notice notice-warning inline" style="margin:14px 0"><p><strong>'
			. esc_html( sprintf( __( 'Son kontrol %s önce.', 'gbc-core' ), human_time_diff( (int) $son['zaman'] ) ) )
			. '</strong> ' . esc_html__( 'Aşağıdaki sayılar o günün fotoğrafı; bugünü göstermiyor.', 'gbc-core' )
			. '</p></div>';
	}

	/* Sorun listesi */
	echo '<h2>' . esc_html__( 'Açık sorunlar', 'gbc-core' ) . '</h2>';
	if ( ! $s ) {
		echo '<p style="color:#1A7F37;font-weight:600">' . esc_html__( 'Açık sorun yok. Dört alan da temiz.', 'gbc-core' ) . '</p>';
	} else {
		uasort( $s, static function ( $a, $b ) {
			if ( $a['onem'] === $b['onem'] ) { return (int) $a['ilk_gorulme'] - (int) $b['ilk_gorulme']; }
			return ( 'yuksek' === $a['onem'] ) ? -1 : 1;
		} );

		$alan_ad = array(
			'kod'      => __( 'Kod', 'gbc-core' ),
			'nobetci'  => __( 'Kontrol Merkezi', 'gbc-core' ),
			'guvenlik' => __( 'Güvenlik', 'gbc-core' ),
			'hiz'      => __( 'Hız', 'gbc-core' ),
			'ga4'      => __( 'GA4', 'gbc-core' ),
		);

		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
			. '<th style="width:110px">' . esc_html__( 'Alan', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Sorun', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Kaç gündür', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Önem', 'gbc-core' ) . '</th>'
			. '<th style="width:80px"></th></tr></thead><tbody>';
		foreach ( $s as $id => $x ) {
			$gun = gbc_sorun_gun( $x );
			echo '<tr>';
			echo '<td>' . esc_html( isset( $alan_ad[ $x['alan'] ] ) ? $alan_ad[ $x['alan'] ] : $x['alan'] ) . '</td>';
			echo '<td><strong>' . esc_html( $x['baslik'] ) . '</strong>'
				. ( $x['aciklama'] ? '<div style="color:#5C6470;font-size:12.5px">' . esc_html( $x['aciklama'] ) . '</div>' : '' ) . '</td>';
			echo '<td>' . ( $gun ? '<strong style="color:' . ( $gun > 7 ? '#B3261E' : '#8A6100' ) . '">'
				. esc_html( sprintf( __( '%d gün', 'gbc-core' ), $gun ) ) . '</strong>'
				: esc_html__( 'bugün', 'gbc-core' ) ) . '</td>';
			echo '<td>' . ( 'yuksek' === $x['onem']
				? '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'yüksek', 'gbc-core' ) . '</span>'
				: esc_html__( 'orta', 'gbc-core' ) ) . '</td>';
			echo '<td>' . ( ! empty( $x['adres'] ) ? '<a href="' . esc_url( $x['adres'] ) . '">' . esc_html__( 'aç', 'gbc-core' ) . '</a>' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/* GA4 bütünlüğü */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'GA4 ölçümü — ne gidiyor, ne gitmiyor', 'gbc-core' ) . '</h2>';
	$ga = gbc_ga4_denetim();
	if ( ! empty( $ga['hata'] ) ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html( $ga['hata'] ) . '</p></div>';
	} else {
		$b = gbc_ga4_beklenen();
		echo '<p style="color:#5C6470">' . esc_html( sprintf( __( 'Ölçülen sayfa: %s', 'gbc-core' ), $ga['url'] ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th style="width:180px">' . esc_html__( 'Olay / parametre', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Ne işe yarar', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $b['olay'] as $olay => $ad ) {
			$ok = isset( $ga['var'][ 'olay-' . $olay ] );
			echo '<tr><td><code>' . esc_html( $olay ) . '</code></td><td>' . esc_html( $ad ) . '</td>'
				. '<td>' . ( $ok ? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'GİDİYOR', 'gbc-core' ) . '</span>'
					: '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'YOK', 'gbc-core' ) . '</span>' ) . '</td></tr>';
		}
		foreach ( $b['parametre'] as $p => $ad ) {
			$ok = isset( $ga['var'][ 'par-' . $p ] );
			echo '<tr><td><code>' . esc_html( $p ) . '</code></td><td>' . esc_html( $ad ) . '</td>'
				. '<td>' . ( $ok ? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'GİDİYOR', 'gbc-core' ) . '</span>'
					: '<span style="color:#B3261E;font-weight:700">' . esc_html__( 'YOK', 'gbc-core' ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<div style="background:#FDF3E0;border:1px solid #F0DCD1;border-radius:10px;padding:12px 14px;max-width:1000px;margin-top:12px;font-size:13px;line-height:1.6">'
			. '<strong>' . esc_html__( 'Dikkat:', 'gbc-core' ) . '</strong> '
			. esc_html__( 'Bu tablo parametrenin sayfadan GÖNDERİLDİĞİNİ gösterir. GA4 bu parametreleri raporlarda ancak “özel boyut” olarak tanımlanırsa gösterir — o iş GA4 yönetim panelinden yapılır, sitede yapılamaz. Sekiz parametrenin hepsi için birer özel boyut açılmalı.', 'gbc-core' )
			. '</div>';
	}

	/* ============================================================
	   B6 — KABUL EDİLENLER
	   ============================================================ */
	$kabuller = gbc_kabuller();
	echo '<h2 style="margin-top:32px">' . esc_html__( 'Kabul edilenler', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:900px;color:#5C6470">'
		. esc_html__( 'Bilerek böyle bırakılmış kararlar. Açık sorun sayısına girmezler ama kaybolmazlar. Ölçülen değer kötüleşirse kabul kendiliğinden düşer ve sorun yeniden açılır.', 'gbc-core' )
		. '</p>';

	if ( ! $kabuller ) {
		echo '<p style="color:#5C6470">' . esc_html__( 'Henüz kabul edilmiş karar yok.', 'gbc-core' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th style="width:280px">' . esc_html__( 'Karar', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Gerekçe', 'gbc-core' ) . '</th>'
			. '<th style="width:130px">' . esc_html__( 'Kim', 'gbc-core' ) . '</th>'
			. '<th style="width:120px">' . esc_html__( 'Tarih', 'gbc-core' ) . '</th>'
			. '<th style="width:120px">' . esc_html__( 'Eşik', 'gbc-core' ) . '</th>'
			. '<th style="width:110px"></th></tr></thead><tbody>';
		foreach ( $kabuller as $kid => $k ) {
			echo '<tr><td><strong>' . esc_html( $k['baslik'] ) . '</strong><br><code style="font-size:11px">' . esc_html( $kid ) . '</code></td>';
			echo '<td>' . esc_html( $k['gerekce'] ) . '</td>';
			echo '<td>' . esc_html( $k['kim'] ) . '</td>';
			echo '<td>' . esc_html( wp_date( 'j M Y', (int) $k['tarih'] ) ) . '</td>';
			echo '<td>';
			if ( ! empty( $k['olcu']['deger'] ) ) {
				echo '<span style="font-size:12px">' . esc_html( $k['olcu']['ad'] ?? '' ) . ' '
					. esc_html( (string) $k['olcu']['deger'] ) . '</span>';
			} else {
				echo '<span style="color:#8A919C;font-size:12px">' . esc_html__( 'ölçüsüz', 'gbc-core' ) . '</span>';
			}
			echo '</td>';
			echo '<td><a class="button button-small" href="'
				. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-gunluk&gbc_kabul_kaldir=' . rawurlencode( $kid ) ), 'gbc_gunluk' ) )
				. '">' . esc_html__( 'Geri al', 'gbc-core' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ============================================================
	   KAPANAN SORUNLAR — "neden kapandı" cevapsız kalmasın
	   ============================================================ */
	$kapanan = gbc_sorun_kapananlar();
	if ( $kapanan ) {
		echo '<h2 style="margin-top:32px">' . esc_html__( 'Son kapanan sorunlar', 'gbc-core' ) . '</h2>';
		echo '<p style="max-width:900px;color:#5C6470">'
			. esc_html__( 'Sorunlar sessizce kaybolmaz; her kapanışın sebebi burada yazılı.', 'gbc-core' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th style="width:300px">' . esc_html__( 'Sorun', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Neden kapandı', 'gbc-core' ) . '</th>'
			. '<th style="width:130px">' . esc_html__( 'Ne zaman', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $kapanan, 0, 25 ) as $k ) {
			echo '<tr><td>' . esc_html( $k['baslik'] ) . '</td>';
			echo '<td>' . esc_html( $k['sebep'] ) . '</td>';
			echo '<td>' . esc_html( human_time_diff( (int) $k['kapandi'] ) ) . ' ' . esc_html__( 'önce', 'gbc-core' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '</div>';
}
