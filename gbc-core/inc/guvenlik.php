<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Güvenlik.
 *
 * HIZ: bu dosya ziyaretci isteginde HIC yuklenmez. gbc-core.php onu yalniz
 * is_admin() ya da DOING_CRON durumunda cagirir. On yuzde tek satiri bile
 * calismaz, tek sorgu bile atmaz. Guvenlik BASLIKLARINI basan kod ayri
 * modulde (10-guvenlik.php) ve o da yalniz hazir bir dizi basligi
 * gonderiyor — olculebilir bir maliyeti yok.
 *
 * NE YAPAR
 *   Siteyi iki taraftan yoklar:
 *     DIS  — gercek HTTP istegiyle disaridan dener (saldirgan ne goruyor).
 *     IC   — sunucuda ayarlara bakar (dosya izni, sabitler, eklentiler).
 *   Sonuclari puanlar, eksikleri onem sirasina koyar, degisince haber verir.
 *
 * GOREV PAYLASIMI (bilerek boyle)
 *   Giris denemesi sinirlama ve IP engelleme  -> Loginizer
 *   Iki adimli giris                          -> Loginizer Pro / 2FA eklentisi
 *   Sunucu ve yedek                           -> Hostinger
 *   Basliklar, XML-RPC, kullanici sayimi      -> GBC Core
 *   Olcum ve gunluk kontrol                   -> GBC Core
 *   Ayni isi iki yerde yapmiyoruz: cakisirlarsa ikisi de bozulur.
 */

define( 'GBC_GV_SON',   'gbc_gv_son' );
define( 'GBC_GV_AYAR',  'gbc_gv_ayar' );
define( 'GBC_GV_UYARI', 'gbc_gv_uyari' );

/* ============================================================
   AYARLAR
   ============================================================ */
function gbc_gv_ayar( $anahtar = null, $varsayilan = null ) {
	$a = get_option( GBC_GV_AYAR, array() );
	if ( ! is_array( $a ) ) { $a = array(); }
	$a = wp_parse_args( $a, array(
		'sıklık' => 'daily',   /* daily | twicedaily | weekly */
		'posta'  => '1',
	) );
	if ( null === $anahtar ) { return $a; }
	return isset( $a[ $anahtar ] ) ? $a[ $anahtar ] : $varsayilan;
}

/* ============================================================
   YARDIMCILAR
   ============================================================ */

/** Disaridan tek bir adres yoklar. Yalniz durum kodu ve gerekiyorsa govde. */
function gbc_gv_yokla( $yol, $govde_lazim = false, $yontem = 'GET' ) {
	$url = home_url( $yol );

	/* ONBELLEK ATLAMA — 28 Eylul 2026'da olculdu.
	   LiteSpeed sayfa onbellegi isteklere PHP'yi hic calistirmadan cevap
	   veriyor. Onbellekteki kopya duvarlar kurulmadan onceki basliklari
	   tasiyordu, tarama da onu okuyup "CSP eksik" diyordu. Gercek degil,
	   eski kopyaydi. Artik her istek benzersiz bir parametreyle gidiyor ve
	   onbellege hayir diyor: olculen sey sitenin SU ANKI hali. */
	$ayrac = ( false === strpos( $yol, '?' ) ) ? '?' : '&';
	$url  .= $ayrac . 'gbc_gv=' . time() . wp_rand( 100, 999 );

	$c = wp_remote_request( $url, array(
		'method'      => $yontem,
		'timeout'     => 12,
		'redirection' => 0,
		'sslverify'   => false,
		'user-agent'  => 'GBC-Guvenlik/1.0',
		'headers'     => array(
			'Cache-Control' => 'no-cache, no-store, max-age=0',
			'Pragma'        => 'no-cache',
			'X-LSCACHE'     => 'no-cache',
		),
	) );
	if ( is_wp_error( $c ) ) {
		return array( 'kod' => 0, 'govde' => '', 'basliklar' => array(), 'hata' => $c->get_error_message() );
	}
	return array(
		'kod'       => (int) wp_remote_retrieve_response_code( $c ),
		'govde'     => $govde_lazim ? (string) wp_remote_retrieve_body( $c ) : '',
		'basliklar' => wp_remote_retrieve_headers( $c ),
		'hata'      => '',
	);
}

function gbc_gv_baslik( $basliklar, $ad ) {
	if ( is_object( $basliklar ) && method_exists( $basliklar, 'offsetGet' ) ) {
		$v = $basliklar[ $ad ] ?? '';
	} elseif ( is_array( $basliklar ) ) {
		$v = isset( $basliklar[ $ad ] ) ? $basliklar[ $ad ] : '';
	} else {
		$v = '';
	}
	if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
	return (string) $v;
}

/**
 * Bir eklenti gercekten calisiyor mu?
 *
 * ONEMLI (28 Eylul 2026'da olculdu): bu kurulum bir COKLU SITE (multisite)
 * agi ve eklentilerin cogu AGDA etkinlestirilmis. Site bazli is_plugin_active()
 * aga etkinlestirilmis eklentiye "pasif" der — yanlis sonuc. Ikisine de bakmak
 * zorundayiz, yoksa calisan eklentiyi yok sayariz.
 */
function gbc_gv_eklenti_aktif( $yol ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( $yol ) ) {
		return true;
	}
	if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $yol ) ) {
		return true;
	}
	return false;
}


/* ============================================================
   .HTACCESS YAMASI

   NEDEN GEREKLI: CSP basligini sunucu basiyor ve PHP'nin gonderdigini
   eziyor (olculdu: statik dosya da ayni basligi tasiyor, orada PHP hic
   calismaz). Tek cozum .htaccess.

   NEDEN GUVENLI: bozuk bir .htaccess butun siteyi 500 hatasina dusurur.
   O yuzden dort katmanli koruma var:
     1) Yazmadan once dosyanin bire bir yedegi aliniyor.
     2) Yeni satir <IfModule mod_headers.c> icinde; modul yoksa Apache
        satiri hic okumuyor, hata vermiyor.
     3) Yazdiktan hemen sonra site GERCEK bir istekle yoklaniyor.
     4) Cevap 500'lerdeyse yedek ANINDA geri yaziliyor ve hicbir sey
        degismemis oluyor.
   ============================================================ */

function gbc_gv_ht_yol() {
	return ABSPATH . '.htaccess';
}

function gbc_gv_ht_blok() {
	return array(
		'<IfModule mod_headers.c>',
		'Header always set Content-Security-Policy "upgrade-insecure-requests; frame-ancestors \'self\'; object-src \'none\'; base-uri \'self\'"',
		'</IfModule>',
	);
}

/**
 * .htaccess'e CSP satirini yazar. Basarisiz olursa hicbir sey degismez.
 *
 * @return array( bool basarili, string mesaj )
 */
function gbc_gv_ht_yaz() {

	$yol = gbc_gv_ht_yol();

	if ( ! file_exists( $yol ) ) {
		return array( false, __( '.htaccess dosyası yok. Hostinger hPanel üzerinden eklemen gerek.', 'gbc-core' ) );
	}
	if ( ! is_writable( $yol ) ) {
		return array( false, __( '.htaccess yazılabilir değil. Dosya izni el vermiyor; hPanel dosya yöneticisinden düzenlemen gerek.', 'gbc-core' ) );
	}

	$onceki = @file_get_contents( $yol );
	if ( ! is_string( $onceki ) ) {
		return array( false, __( '.htaccess okunamadı.', 'gbc-core' ) );
	}

	/* 1) Yedek */
	$yedek = ABSPATH . '.htaccess.gbc-yedek';
	if ( false === @file_put_contents( $yedek, $onceki ) ) {
		return array( false, __( 'Yedek alınamadı, bu yüzden dosyaya dokunmadım.', 'gbc-core' ) );
	}
	update_option( 'gbc_gv_ht_yedek_zaman', time(), false );

	/* 2) Bizim blogumuzun disindaki eski CSP satirlarini devre disi birak */
	$satirlar = preg_split( "/\r\n|\n|\r/", $onceki );
	$icerde   = false;
	$kapatildi = 0;
	foreach ( $satirlar as $i => $satir ) {
		if ( false !== stripos( $satir, '# BEGIN GBC Guvenlik' ) ) { $icerde = true; continue; }
		if ( false !== stripos( $satir, '# END GBC Guvenlik' ) )   { $icerde = false; continue; }
		if ( $icerde ) { continue; }
		if ( 0 === strpos( ltrim( $satir ), '#' ) ) { continue; }
		if ( false !== stripos( $satir, 'Content-Security-Policy' ) ) {
			$satirlar[ $i ] = '# GBC devre disi (' . gmdate( 'Y-m-d' ) . '): ' . $satir;
			$kapatildi++;
		}
	}
	if ( $kapatildi > 0 ) {
		if ( false === @file_put_contents( $yol, implode( "\n", $satirlar ) ) ) {
			return array( false, __( 'Dosyaya yazılamadı; hiçbir şey değişmedi.', 'gbc-core' ) );
		}
	}

	/* 3) Kendi blogumuzu koy — WordPress'in kendi rutini, iyi denenmis */
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$ok = insert_with_markers( $yol, 'GBC Guvenlik', gbc_gv_ht_blok() );
	if ( ! $ok ) {
		@file_put_contents( $yol, $onceki );
		return array( false, __( 'Satır eklenemedi; dosya eski hâline döndürüldü.', 'gbc-core' ) );
	}

	/* 4) DOGRULAMA: site hâlâ ayakta mi? */
	$deneme = wp_remote_get(
		home_url( '/?gbc_ht=' . time() ),
		array( 'timeout' => 15, 'sslverify' => false, 'redirection' => 0,
		       'headers' => array( 'Cache-Control' => 'no-cache' ) )
	);
	$kod = is_wp_error( $deneme ) ? 0 : (int) wp_remote_retrieve_response_code( $deneme );

	if ( 0 === $kod || $kod >= 500 ) {
		@file_put_contents( $yol, $onceki );
		return array( false, sprintf(
			__( 'Yazdıktan sonra site %s cevabı verdi, bu yüzden .htaccess ANINDA eski hâline döndürüldü. Sitede hiçbir değişiklik kalmadı. Bu satırı hPanel üzerinden elle eklemen gerekiyor.', 'gbc-core' ),
			$kod ? 'HTTP ' . $kod : __( 'cevapsız', 'gbc-core' )
		) );
	}

	/* 5) Baslik gercekten degisti mi? Statik dosyadan bak. */
	$st  = gbc_gv_yokla( '/wp-includes/js/wp-embed.min.js', false, 'GET' );
	$yeni_csp = gbc_gv_baslik( $st['basliklar'], 'content-security-policy' );
	$tuttu = ( false !== stripos( $yeni_csp, 'frame-ancestors' ) );

	return array( true, $tuttu
		? sprintf( __( 'Yazıldı ve doğrulandı. Site HTTP %1$d veriyor, yeni başlık: %2$s', 'gbc-core' ), $kod, $yeni_csp )
		: sprintf( __( 'Yazıldı, site HTTP %d veriyor ve ayakta. Ama başlık hâlâ eski görünüyor — sunucu yapılandırması .htaccess\'i geçiyor olabilir. Yedek duruyor, istersen geri al.', 'gbc-core' ), $kod )
	);
}

/** Yedegi geri yazar. */
function gbc_gv_ht_geri_al() {
	$yedek = ABSPATH . '.htaccess.gbc-yedek';
	if ( ! file_exists( $yedek ) ) {
		return array( false, __( 'Geri alınacak yedek yok.', 'gbc-core' ) );
	}
	$icerik = @file_get_contents( $yedek );
	if ( ! is_string( $icerik ) || false === @file_put_contents( gbc_gv_ht_yol(), $icerik ) ) {
		return array( false, __( 'Geri yazılamadı.', 'gbc-core' ) );
	}
	return array( true, __( '.htaccess yedekten geri yüklendi.', 'gbc-core' ) );
}

/**
 * CSP'yi kim basiyor: WordPress mi, sunucu mu?
 *
 * 28 Eylul 2026'da olculdu. Statik bir PNG dosyasi da CSP basligini
 * tasiyordu. Statik dosyada PHP HIC calismaz — demek ki basligi sunucu
 * koyuyor (.htaccess ya da LiteSpeed ayari) ve "Header set" kullandigi
 * icin PHP'nin gonderdigini EZIYOR. Eklentinin CSP duvari bu yuzden
 * sayfaya ulasmiyordu; kod dogruydu, sunucu ustune yaziyordu.
 *
 * Bu fonksiyon .htaccess'te ilgili satiri bulup ekranda gosterir, boylece
 * nereyi degistirecegin belli olur.
 */
function gbc_gv_htaccess_csp() {
	$yol = ABSPATH . '.htaccess';
	if ( ! file_exists( $yol ) || ! is_readable( $yol ) ) {
		return array( 'var' => false, 'satir' => '', 'not' => __( '.htaccess okunamadı.', 'gbc-core' ) );
	}
	$icerik = @file_get_contents( $yol, false, null, 0, 200000 );
	if ( ! is_string( $icerik ) ) {
		return array( 'var' => false, 'satir' => '', 'not' => __( '.htaccess okunamadı.', 'gbc-core' ) );
	}
	foreach ( preg_split( "/\r\n|\n|\r/", $icerik ) as $no => $satir ) {
		if ( false !== stripos( $satir, 'Content-Security-Policy' ) ) {
			return array(
				'var'   => true,
				'satir' => trim( $satir ),
				'no'    => $no + 1,
				'not'   => '',
			);
		}
	}
	return array( 'var' => false, 'satir' => '', 'not' => __( '.htaccess içinde CSP satırı yok — başlık LiteSpeed ya da sunucu ayarından geliyor.', 'gbc-core' ) );
}

/** Iki adimli giris kurulu mu? Bilinen eklentileri ve kullanici verisini yoklar. */
function gbc_gv_2fa_durumu() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$adaylar = array(
		'two-factor/two-factor.php'                      => 'Two Factor',
		'wp-2fa/wp-2fa.php'                              => 'WP 2FA',
		'miniorange-2-factor-authentication/miniorange_2_factor_settings.php' => 'miniOrange 2FA',
		'google-authenticator/google-authenticator.php'  => 'Google Authenticator',
		'two-factor-authentication/two-factor-login.php' => 'Two Factor Authentication',
		'wordfence-login-security/wordfence-login-security.php' => 'Wordfence Login Security',
		'loginizer/loginizer.php'                        => 'Loginizer',
	);

	$aktif = array();
	foreach ( $adaylar as $yol => $ad ) {
		if ( gbc_gv_eklenti_aktif( $yol ) ) { $aktif[] = $ad; }
	}

	/* Yoneticiler tek tek: hangi hesapta acik?
	   Coklu sitede super yoneticiler de eklenir; asil yetki onlarda. */
	$yoneticiler = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_login', 'display_name' ) ) );

	if ( is_multisite() && function_exists( 'get_super_admins' ) ) {
		$mevcut = wp_list_pluck( $yoneticiler, 'user_login' );
		foreach ( (array) get_super_admins() as $giris ) {
			if ( in_array( $giris, (array) $mevcut, true ) ) { continue; }
			$k = get_user_by( 'login', $giris );
			if ( $k ) {
				$yoneticiler[] = (object) array(
					'ID'           => $k->ID,
					'user_login'   => $k->user_login,
					'display_name' => $k->display_name,
				);
			}
		}
	}

	$super = ( is_multisite() && function_exists( 'get_super_admins' ) ) ? (array) get_super_admins() : array();
	$rapor  = array();
	$acik   = 0;

	foreach ( $yoneticiler as $k ) {
		$var = false;
		$kim = '';

		/* Two Factor (resmi eklenti) */
		$tf = get_user_meta( $k->ID, '_two_factor_enabled_providers', true );
		if ( is_array( $tf ) && $tf ) { $var = true; $kim = 'Two Factor'; }

		/* WP 2FA */
		if ( ! $var && get_user_meta( $k->ID, 'wp_2fa_totp_key', true ) ) { $var = true; $kim = 'WP 2FA'; }

		/* Loginizer 2FA — 28 Eylul 2026'da canli veritabanindan okundu.
		   Loginizer ayri bir "secret" anahtari TUTMUYOR; her seyi tek bir
		   diziye koyuyor:
		     loginizer_user_settings = array( 'pref' => '2fa_app', 'app_enable' => 1 )
		   Onceki surumde loginizer_2fa_secret aranmisti, o anahtar hic yok;
		   bu yuzden 2FA kurulu hesaplar "yok" gorunuyordu. */
		if ( ! $var ) {
			$lz = get_user_meta( $k->ID, 'loginizer_user_settings', true );
			if ( is_string( $lz ) ) { $lz = maybe_unserialize( $lz ); }
			if ( is_array( $lz ) ) {
				$uyg  = ! empty( $lz['app_enable'] );
				$posta= ! empty( $lz['email_enable'] );
				$tercih = isset( $lz['pref'] ) ? (string) $lz['pref'] : '';
				if ( $uyg || $posta || 0 === strpos( $tercih, '2fa' ) ) {
					$var = true;
					$kim = 'Loginizer';
					if ( $uyg )        { $kim .= ' · ' . __( 'uygulama', 'gbc-core' ); }
					elseif ( $posta )  { $kim .= ' · ' . __( 'e-posta', 'gbc-core' ); }
				}
			}
		}

		/* miniOrange */
		if ( ! $var && get_user_meta( $k->ID, 'mo2f_2FA_method_to_configure', true ) ) { $var = true; $kim = 'miniOrange'; }

		/* Google Authenticator (David Nutbourne / Henrik Schack) */
		if ( ! $var && get_user_meta( $k->ID, 'googleauthenticator_enabled', true ) === 'enabled' ) { $var = true; $kim = 'Google Authenticator'; }

		if ( $var ) { $acik++; }
		$rapor[] = array(
			'ad'     => $k->display_name ? $k->display_name : $k->user_login,
			'giris'  => $k->user_login,
			'acik'   => $var,
			'yontem' => $kim,
			'super'  => in_array( $k->user_login, $super, true ),
		);
	}

	return array(
		'eklentiler' => $aktif,
		'yoneticiler'=> $rapor,
		'acik'       => $acik,
		'toplam'     => count( $rapor ),
	);
}

/* ============================================================
   TARAMA
   ============================================================ */
function gbc_gv_tara() {

	$b = array();   /* bulgular */

	$ekle = function ( $anahtar, $ad, $durum, $deger, $not, $onem = 'orta', $grup = 'genel' ) use ( &$b ) {
		$b[] = array(
			'anahtar' => $anahtar,
			'ad'      => $ad,
			'durum'   => $durum,          /* tamam | eksik | acik | bilinmiyor */
			'deger'   => $deger,
			'not'     => $not,
			'onem'    => $onem,           /* yuksek | orta | dusuk */
			'grup'    => $grup,           /* baslik | kapi | giris | sistem */
		);
	};

	/* ---------- 1) GUVENLIK BASLIKLARI ---------- */
	$ana = gbc_gv_yokla( '/', false );
	$h   = $ana['basliklar'];

	$hsts = gbc_gv_baslik( $h, 'strict-transport-security' );
	$ekle( 'hsts', __( 'HSTS (zorunlu HTTPS)', 'gbc-core' ),
		( '' === $hsts ) ? 'acik' : ( false !== stripos( $hsts, 'includeSubDomains' ) ? 'tamam' : 'eksik' ),
		$hsts ? $hsts : '—',
		( '' === $hsts )
			? __( 'Başlık hiç gönderilmiyor.', 'gbc-core' )
			: ( false !== stripos( $hsts, 'includeSubDomains' )
				? __( 'Alt alan adları da kapsanıyor.', 'gbc-core' )
				: __( 'Alt alan adları kapsanmıyor; includeSubDomains eklenmeli.', 'gbc-core' ) ),
		'orta', 'baslik' );

	$nosniff = gbc_gv_baslik( $h, 'x-content-type-options' );
	$ekle( 'nosniff', 'X-Content-Type-Options',
		( false !== stripos( $nosniff, 'nosniff' ) ) ? 'tamam' : 'acik',
		$nosniff ? $nosniff : '—',
		__( 'Tarayıcının dosya türünü tahmin etmesini engeller.', 'gbc-core' ), 'orta', 'baslik' );

	$xfo = gbc_gv_baslik( $h, 'x-frame-options' );
	$csp = gbc_gv_baslik( $h, 'content-security-policy' );
	$cerceve = ( '' !== $xfo ) || ( false !== stripos( $csp, 'frame-ancestors' ) );
	$ekle( 'cerceve', __( 'Çerçeveleme koruması', 'gbc-core' ),
		$cerceve ? 'tamam' : 'acik',
		$xfo ? $xfo : ( $csp ? $csp : '—' ),
		__( 'Başka bir sitenin seni çerçeve içine almasını engeller (tıklama hırsızlığı).', 'gbc-core' ), 'orta', 'baslik' );

	$ref = gbc_gv_baslik( $h, 'referrer-policy' );
	$ekle( 'referrer', 'Referrer-Policy',
		( '' !== $ref ) ? 'tamam' : 'eksik',
		$ref ? $ref : '—',
		__( 'Dışarı çıkarken tam adresin sızmasını engeller.', 'gbc-core' ), 'dusuk', 'baslik' );

	$izin = gbc_gv_baslik( $h, 'permissions-policy' );
	$ekle( 'izin', 'Permissions-Policy',
		( '' !== $izin ) ? 'tamam' : 'eksik',
		$izin ? $izin : '—',
		__( 'Kamera, mikrofon, konum gibi yetkileri kapatır.', 'gbc-core' ), 'dusuk', 'baslik' );

	/* CSP'yi sunucu mu basiyor? Statik dosyada PHP calismaz; orada da
	   ayni baslik varsa kaynak sunucudur ve PHP onu degistiremez. */
	$statik = gbc_gv_yokla( '/wp-includes/js/wp-embed.min.js', false, 'GET' );
	$csp_statik = gbc_gv_baslik( $statik['basliklar'], 'content-security-policy' );
	$sunucudan  = ( '' !== $csp_statik );
	$ht = gbc_gv_htaccess_csp();

	$csp_iyi = ( false !== stripos( $csp, 'object-src' ) || false !== stripos( $csp, 'frame-ancestors' ) );

	if ( $csp_iyi ) {
		$csp_durum = 'tamam';
		$csp_not   = __( 'Çerçeveleme ve eski gömme türleri kapalı.', 'gbc-core' );
	} elseif ( $sunucudan ) {
		$csp_durum = 'eksik';
		$csp_not   = $ht['var']
			? sprintf(
				__( 'Başlığı SUNUCU basıyor (.htaccess %d. satır), PHP onu değiştiremez. O satırı düzeltmek gerek — aşağıda hazır metin var.', 'gbc-core' ),
				(int) $ht['no'] )
			: __( 'Başlığı sunucu basıyor (LiteSpeed ya da hPanel ayarı), PHP onu değiştiremez. Eklentinin CSP duvarı bu yüzden etkisiz.', 'gbc-core' );
	} elseif ( '' === $csp ) {
		$csp_durum = 'acik';
		$csp_not   = __( 'Hiç yok.', 'gbc-core' );
	} else {
		$csp_durum = 'eksik';
		$csp_not   = __( 'Var ama dar. object-src ve frame-ancestors eklenmeli.', 'gbc-core' );
	}

	$ekle( 'csp', 'Content-Security-Policy', $csp_durum, $csp ? $csp : '—', $csp_not, 'dusuk', 'baslik' );

	$sonuc_csp_sunucu = array( 'sunucudan' => $sunucudan, 'htaccess' => $ht, 'deger' => $csp );

	$php = gbc_gv_baslik( $h, 'x-powered-by' );
	$ekle( 'phpsurum', __( 'PHP sürümü gizli mi', 'gbc-core' ),
		( '' === $php ) ? 'tamam' : 'acik',
		$php ? $php : __( 'Gizli', 'gbc-core' ),
		__( 'X-Powered-By başlığı PHP sürümünü her istekte duyuruyor.', 'gbc-core' ), 'orta', 'baslik' );

	/* ---------- 2) ACIK KAPILAR ---------- */
	$x = gbc_gv_yokla( '/xmlrpc.php', false, 'POST' );
	$ekle( 'xmlrpc', 'XML-RPC',
		in_array( $x['kod'], array( 403, 404, 405, 301, 302 ), true ) ? 'tamam' : 'acik',
		'HTTP ' . $x['kod'],
		__( 'Kaba kuvvet ve DDoS saldırılarının en sık kullandığı kapı.', 'gbc-core' ), 'yuksek', 'kapi' );

	$u = gbc_gv_yokla( '/wp-json/wp/v2/users', true );
	$ekle( 'restuser', __( 'Kullanıcı listesi (REST)', 'gbc-core' ),
		( 200 !== $u['kod'] ) ? 'tamam' : 'acik',
		'HTTP ' . $u['kod'],
		__( 'Açıksa kullanıcı adların dışarıdan okunur, sonra şifre denenir.', 'gbc-core' ), 'yuksek', 'kapi' );

	$a1 = gbc_gv_yokla( '/?author=1', false );
	$ekle( 'yazarid', __( 'Yazar numarası sızıntısı', 'gbc-core' ),
		in_array( $a1['kod'], array( 301, 302, 404, 403 ), true ) ? 'tamam' : 'acik',
		'HTTP ' . $a1['kod'],
		__( 'Açıksa numaradan kullanıcı adına ulaşılır.', 'gbc-core' ), 'orta', 'kapi' );

	foreach ( array(
		'readme'    => array( '/readme.html',            __( 'readme.html dosyası', 'gbc-core' ),        __( 'WordPress sürümünü açıkça yazar.', 'gbc-core' ), 'orta' ),
		'lisans'    => array( '/license.txt',            __( 'license.txt dosyası', 'gbc-core' ),        __( 'WordPress kurulumunu ele verir.', 'gbc-core' ), 'dusuk' ),
		'debuglog'  => array( '/wp-content/debug.log',   __( 'Hata günlüğü', 'gbc-core' ),               __( 'Açıksa hata kayıtları okunur.', 'gbc-core' ), 'yuksek' ),
		'configyed' => array( '/wp-config.php.bak',      __( 'Ayar dosyası yedeği', 'gbc-core' ),        __( 'Açıksa veritabanı şifresi sızar.', 'gbc-core' ), 'yuksek' ),
		'klasor'    => array( '/wp-content/uploads/',    __( 'Klasör listeleme', 'gbc-core' ),           __( 'Açıksa bütün dosyalar listelenir.', 'gbc-core' ), 'dusuk' ),
	) as $k => $v ) {
		$r = gbc_gv_yokla( $v[0], false );
		$ekle( $k, $v[1], ( 200 === $r['kod'] ) ? 'acik' : 'tamam', 'HTTP ' . $r['kod'], $v[2], $v[3], 'kapi' );
	}

	$ana_govde = gbc_gv_yokla( '/', true );
	$gen = ( false !== strpos( $ana_govde['govde'], '<meta name="generator"' ) );
	$ekle( 'generator', __( 'Sürüm etiketi', 'gbc-core' ),
		$gen ? 'acik' : 'tamam',
		$gen ? __( 'Sayfada var', 'gbc-core' ) : __( 'Yok', 'gbc-core' ),
		__( 'WordPress sürümünün sayfa kaynağında yazması.', 'gbc-core' ), 'dusuk', 'kapi' );

	/* ---------- 3) GIRIS GUVENLIGI ---------- */
	$iki = gbc_gv_2fa_durumu();
	$ekle( '2fa', __( 'İki adımlı giriş', 'gbc-core' ),
		( $iki['toplam'] > 0 && $iki['acik'] === $iki['toplam'] ) ? 'tamam' : ( $iki['acik'] > 0 ? 'eksik' : 'acik' ),
		sprintf(
			/* translators: 1: kaç yöneticide açık, 2: toplam yönetici */
			__( '%1$d / %2$d yönetici', 'gbc-core' ), $iki['acik'], $iki['toplam'] ),
		$iki['eklentiler']
			? sprintf( __( 'Kurulu: %s', 'gbc-core' ), implode( ', ', $iki['eklentiler'] ) )
			: __( 'Tanınan bir iki adımlı giriş eklentisi bulunamadı.', 'gbc-core' ),
		'yuksek', 'giris' );

	$lg = gbc_gv_eklenti_aktif( 'loginizer/loginizer.php' )
		|| gbc_gv_eklenti_aktif( 'loginizer-security/loginizer.php' );
	$ekle( 'loginizer', __( 'Giriş denemesi sınırlama', 'gbc-core' ),
		$lg ? 'tamam' : 'acik',
		$lg ? __( 'Loginizer etkin', 'gbc-core' ) : __( 'Bulunamadı', 'gbc-core' ),
		__( 'Şifre deneme saldırılarını durduran katman. GBC Core bu işi tekrarlamaz.', 'gbc-core' ), 'yuksek', 'giris' );

	$admin_var = (bool) get_user_by( 'login', 'admin' );
	$ekle( 'adminadi', __( '"admin" kullanıcı adı', 'gbc-core' ),
		$admin_var ? 'acik' : 'tamam',
		$admin_var ? __( 'Var', 'gbc-core' ) : __( 'Yok', 'gbc-core' ),
		__( 'Saldırganın ilk denediği kullanıcı adı.', 'gbc-core' ), 'orta', 'giris' );

	$yon_sayi = count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) );
	$ekle( 'yonetici', __( 'Yönetici sayısı', 'gbc-core' ),
		( $yon_sayi <= 3 ) ? 'tamam' : 'eksik',
		(string) $yon_sayi,
		__( 'Her yönetici hesabı ayrı bir risk. Gerekmeyeni editöre indir.', 'gbc-core' ), 'dusuk', 'giris' );

	/* ---------- 4) SISTEM ---------- */
	$duzenle = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	$ekle( 'dosyaduzenle', __( 'Panelden kod düzenleme', 'gbc-core' ),
		$duzenle ? 'tamam' : 'acik',
		$duzenle ? __( 'Kapalı', 'gbc-core' ) : __( 'Açık', 'gbc-core' ),
		__( 'Kapalıysa panele giren biri tema dosyasına kod yazamaz.', 'gbc-core' ), 'yuksek', 'sistem' );

	$hata_ac = defined( 'WP_DEBUG' ) && WP_DEBUG;
	$ekle( 'debug', __( 'Hata ayıklama modu', 'gbc-core' ),
		$hata_ac ? 'acik' : 'tamam',
		$hata_ac ? __( 'Açık', 'gbc-core' ) : __( 'Kapalı', 'gbc-core' ),
		__( 'Canlı sitede açık kalırsa hata metinleri ziyaretçiye görünür.', 'gbc-core' ), 'orta', 'sistem' );

	global $wpdb;
	$onek = $wpdb->prefix;
	$ekle( 'onek', __( 'Veritabanı ön eki', 'gbc-core' ),
		( 'wp_' !== $onek ) ? 'tamam' : 'eksik',
		$onek,
		__( 'Varsayılan ön ek toplu saldırıları kolaylaştırır.', 'gbc-core' ), 'dusuk', 'sistem' );

	$ssl = ( 0 === strpos( home_url(), 'https://' ) );
	$ekle( 'ssl', __( 'HTTPS', 'gbc-core' ),
		$ssl ? 'tamam' : 'acik',
		$ssl ? __( 'Açık', 'gbc-core' ) : __( 'Kapalı', 'gbc-core' ),
		__( 'Sertifika olmadan hiçbir önlem işe yaramaz.', 'gbc-core' ), 'yuksek', 'sistem' );

	$cfg = ABSPATH . 'wp-config.php';
	if ( file_exists( $cfg ) ) {
		$izin = substr( sprintf( '%o', fileperms( $cfg ) ), -3 );
		$ekle( 'configizin', __( 'wp-config.php dosya izni', 'gbc-core' ),
			( (int) $izin <= 644 ) ? 'tamam' : 'eksik',
			$izin,
			__( '644 ya da daha sıkı olmalı; 777 asla.', 'gbc-core' ), 'orta', 'sistem' );
	}

	require_once ABSPATH . 'wp-admin/includes/update.php';
	$cekirdek = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
	$cekirdek_eski = ( is_array( $cekirdek ) && isset( $cekirdek[0]->response ) && 'upgrade' === $cekirdek[0]->response );
	$ekle( 'wpsurum', __( 'WordPress güncel mi', 'gbc-core' ),
		$cekirdek_eski ? 'eksik' : 'tamam',
		get_bloginfo( 'version' ),
		__( 'Güncelleme yamaları çoğu zaman güvenlik yamasıdır.', 'gbc-core' ), 'yuksek', 'sistem' );

	$eski_eklenti = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
	$ekle( 'eklentisurum', __( 'Eklentiler güncel mi', 'gbc-core' ),
		( count( $eski_eklenti ) === 0 ) ? 'tamam' : 'eksik',
		sprintf( __( '%d eski', 'gbc-core' ), count( $eski_eklenti ) ),
		__( 'Saldırıların çoğu güncellenmemiş eklentiden girer.', 'gbc-core' ), 'yuksek', 'sistem' );

	$hostinger = gbc_gv_eklenti_aktif( 'hostinger/hostinger.php' )
		|| gbc_gv_eklenti_aktif( 'hostinger-tools/hostinger-tools.php' )
		|| gbc_gv_eklenti_aktif( 'hostinger-easy-onboarding/hostinger-easy-onboarding.php' );
	$ekle( 'hostinger', __( 'Hostinger koruması', 'gbc-core' ),
		$hostinger ? 'tamam' : 'bilinmiyor',
		$hostinger ? __( 'Etkin', 'gbc-core' ) : __( 'Görünmüyor', 'gbc-core' ),
		__( 'Sunucu tarafı güvenlik duvarı ve yedek Hostinger tarafında.', 'gbc-core' ), 'orta', 'sistem' );

	if ( is_multisite() ) {
		$ekle( 'coklusite', __( 'Çoklu site ağı', 'gbc-core' ), 'tamam',
			sprintf( __( '%d site', 'gbc-core' ), (int) get_blog_count() ),
			__( 'Eklentiler ağda etkinleştiriliyor. Bir sitedeki açık bütün ağı ilgilendirir.', 'gbc-core' ),
			'dusuk', 'sistem' );

		/* Bu bilerek acik: kapatirsan agdan eklenti guncelleyemezsin.
		   Bilgi olarak gosteriliyor, puani dusurmuyor. */
		$dosya_degistir = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;
		$ekle( 'dosyakur', __( 'Panelden eklenti/tema kurma', 'gbc-core' ),
			'bilinmiyor',
			$dosya_degistir ? __( 'Kapalı', 'gbc-core' ) : __( 'Açık (tercih)', 'gbc-core' ),
			__( 'Bilerek açık: kapatırsan ağdan eklenti güncelleyemezsin. Puana dahil değil.', 'gbc-core' ),
			'dusuk', 'sistem' );
	}

	$sonuc = array(
		'zaman'   => time(),
		'bulgu'   => $b,
		'2fa'     => $iki,
		'csp'     => $sonuc_csp_sunucu,
	);
	$sonuc['skor'] = gbc_gv_skor( $sonuc );

	update_option( GBC_GV_SON, $sonuc, false );

	/* Yeni acilan yuksek onemli bulgu varsa haber ver. */
	$acik_yuksek = array();
	foreach ( $b as $x2 ) {
		if ( 'acik' === $x2['durum'] && 'yuksek' === $x2['onem'] ) {
			$acik_yuksek[] = $x2['ad'];
		}
	}
	update_option( GBC_GV_UYARI, $acik_yuksek, false );

	return $sonuc;
}

function gbc_gv_skor( $sonuc ) {
	$agirlik = array( 'yuksek' => 5, 'orta' => 3, 'dusuk' => 1 );
	$toplam = 0; $kazanilan = 0;
	foreach ( (array) $sonuc['bulgu'] as $x ) {
		if ( 'bilinmiyor' === $x['durum'] ) { continue; }
		$a = isset( $agirlik[ $x['onem'] ] ) ? $agirlik[ $x['onem'] ] : 2;
		$toplam += $a;
		if ( 'tamam' === $x['durum'] ) { $kazanilan += $a; }
		elseif ( 'eksik' === $x['durum'] ) { $kazanilan += $a * 0.5; }
	}
	return $toplam ? (int) round( $kazanilan / $toplam * 100 ) : 0;
}

/* ============================================================
   ZAMANLAMA
   ============================================================ */
if ( ! function_exists( 'gbc_gv_zamanla' ) ) {
	function gbc_gv_zamanla() {
		$istenen = gbc_gv_ayar( 'sıklık', 'daily' );
		$mevcut  = wp_next_scheduled( 'gbc_gv_tarama' );
		$sched   = wp_get_schedule( 'gbc_gv_tarama' );

		if ( $mevcut && $sched !== $istenen ) {
			wp_unschedule_event( $mevcut, 'gbc_gv_tarama' );
			$mevcut = false;
		}
		if ( ! $mevcut ) {
			wp_schedule_event( time() + 900, $istenen, 'gbc_gv_tarama' );
		}
	}
	add_action( 'init', 'gbc_gv_zamanla' );
}

if ( ! function_exists( 'gbc_gv_tarama_calistir' ) ) {
	function gbc_gv_tarama_calistir() {
		$s = gbc_gv_tara();
		$acik = get_option( GBC_GV_UYARI, array() );
		if ( ! $acik || '1' !== gbc_gv_ayar( 'posta', '1' ) ) { return; }
		if ( get_transient( 'gbc_gv_posta_kilit' ) ) { return; }
		set_transient( 'gbc_gv_posta_kilit', 1, DAY_IN_SECONDS );

		$govde  = sprintf( __( "GBC Güvenlik — puan %d/100.\n\n", 'gbc-core' ), (int) $s['skor'] );
		$govde .= __( "Açık ve önemli bulgular:\n", 'gbc-core' );
		foreach ( (array) $acik as $ad ) { $govde .= '• ' . $ad . "\n"; }
		$govde .= "\n" . admin_url( 'admin.php?page=gbc-guvenlik' );

		wp_mail( get_option( 'admin_email' ), __( '[GBC] Güvenlik uyarısı', 'gbc-core' ), $govde );
	}
	add_action( 'gbc_gv_tarama', 'gbc_gv_tarama_calistir' );
}

if ( ! function_exists( 'gbc_gv_admin_uyari' ) ) {
	function gbc_gv_admin_uyari() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$acik = get_option( GBC_GV_UYARI, array() );
		if ( ! is_array( $acik ) || ! $acik ) { return; }
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'GBC Güvenlik:', 'gbc-core' ) . '</strong> '
			. esc_html( sprintf( __( '%d önemli açık var.', 'gbc-core' ), count( $acik ) ) ) . ' '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-guvenlik' ) ) . '">' . esc_html__( 'Ayrıntıya bak', 'gbc-core' ) . '</a></p></div>';
	}
	add_action( 'admin_notices', 'gbc_gv_admin_uyari' );
}

/* ============================================================
   EKRAN
   ============================================================ */
function gbc_gv_rozet( $durum ) {
	$h = array(
		'tamam'      => array( __( 'Tamam', 'gbc-core' ),      '#1A6B31', '#E4F3E8' ),
		'eksik'      => array( __( 'Eksik', 'gbc-core' ),      '#8A6100', '#FCF3E1' ),
		'acik'       => array( __( 'AÇIK', 'gbc-core' ),       '#9C2A2B', '#FBE7E7' ),
		'bilinmiyor' => array( __( 'Bilinmiyor', 'gbc-core' ), '#5C6470', '#EEF0F3' ),
	);
	$v = isset( $h[ $durum ] ) ? $h[ $durum ] : array( $durum, '#5C6470', '#EEF0F3' );
	return '<span style="display:inline-block;padding:3px 11px;border-radius:20px;font-size:12.5px;font-weight:700;color:'
		. esc_attr( $v[1] ) . ';background:' . esc_attr( $v[2] ) . '">' . esc_html( $v[0] ) . '</span>';
}

function gbc_gv_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_POST['gbc_gv_kaydet'] ) && check_admin_referer( 'gbc_gv' ) ) {
		$sik = sanitize_key( wp_unslash( $_POST['gbc_gv_siklik'] ?? 'daily' ) );
		if ( ! in_array( $sik, array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ) { $sik = 'daily'; }
		update_option( GBC_GV_AYAR, array(
			'sıklık' => $sik,
			'posta'  => isset( $_POST['gbc_gv_posta'] ) ? '1' : '',
		), false );
		$z = wp_next_scheduled( 'gbc_gv_tarama' );
		if ( $z ) { wp_unschedule_event( $z, 'gbc_gv_tarama' ); }
		gbc_gv_zamanla();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kaydedildi.', 'gbc-core' ) . '</p></div>';
	}

	if ( isset( $_POST['gbc_duvar_kaydet'] ) && check_admin_referer( 'gbc_gv' ) ) {
		update_option( 'gbc_duvar', array(
			'hsts'    => isset( $_POST['d_hsts'] ) ? '1' : '',
			'csp'     => isset( $_POST['d_csp'] ) ? '1' : '',
			'surum'   => isset( $_POST['d_surum'] ) ? '1' : '',
			'duzenle' => isset( $_POST['d_duzenle'] ) ? '1' : '',
			'preload' => isset( $_POST['d_preload'] ) ? '1' : '',
		) );
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Duvarlar kaydedildi. Değişikliğin görünmesi için LiteSpeed önbelleğini temizleyin.', 'gbc-core' )
			. '</p></div>';
	}

	if ( isset( $_GET['gbc_gv_ht'] ) && check_admin_referer( 'gbc_gv' ) ) {
		list( $ok, $mesaj ) = gbc_gv_ht_yaz();
		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>'
			. esc_html( $mesaj ) . '</p></div>';
		gbc_gv_tara();
	}

	if ( isset( $_GET['gbc_gv_ht_geri'] ) && check_admin_referer( 'gbc_gv' ) ) {
		list( $ok, $mesaj ) = gbc_gv_ht_geri_al();
		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>'
			. esc_html( $mesaj ) . '</p></div>';
		gbc_gv_tara();
	}

	if ( isset( $_GET['gbc_gv_sil'] ) && check_admin_referer( 'gbc_gv' ) ) {
		$silinen = array();
		$kalan   = array();
		/* Yalniz bu iki dosya. Baska hicbir yol kabul edilmiyor. */
		foreach ( array( 'readme.html', 'license.txt' ) as $dosya ) {
			$yol = ABSPATH . $dosya;
			if ( ! file_exists( $yol ) ) { continue; }
			if ( @unlink( $yol ) ) { $silinen[] = $dosya; } else { $kalan[] = $dosya; }
		}
		if ( $silinen ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html( sprintf( __( 'Silindi: %s. WordPress güncellemesi bu dosyaları geri koyar; tarama yeniden uyarır.', 'gbc-core' ), implode( ', ', $silinen ) ) )
				. '</p></div>';
		}
		if ( $kalan ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html( sprintf( __( 'Silinemedi: %s — dosya izni yetmedi. Hostinger dosya yöneticisinden elle silmen gerek.', 'gbc-core' ), implode( ', ', $kalan ) ) )
				. '</p></div>';
		}
		gbc_gv_tara();
	}

	if ( isset( $_GET['gbc_gv_tara'] ) && check_admin_referer( 'gbc_gv' ) ) {
		gbc_gv_tara();
	}

	$s = get_option( GBC_GV_SON, array() );
	$ayar = gbc_gv_ayar();

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Güvenlik', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-guvenlik' ); }
	echo '<p style="max-width:900px;color:#444">'
		. esc_html__( 'Site hem dışarıdan gerçek istekle yoklanır, hem sunucuda ayarlara bakılır. Her satır ölçülmüş bir sonuçtur.', 'gbc-core' )
		. '</p>';

	echo '<p><a class="button button-primary" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-guvenlik&gbc_gv_tara=1' ), 'gbc_gv' ) )
		. '">' . esc_html__( 'Şimdi tara', 'gbc-core' ) . '</a> ';

	if ( file_exists( ABSPATH . 'readme.html' ) || file_exists( ABSPATH . 'license.txt' ) ) {
		echo '<a class="button" href="'
			. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-guvenlik&gbc_gv_sil=1' ), 'gbc_gv' ) )
			. '">' . esc_html__( 'readme.html ve license.txt dosyalarını sil', 'gbc-core' ) . '</a>';
	}
	echo '</p>';

	if ( empty( $s['zaman'] ) ) {
		echo '<p><em>' . esc_html__( 'Henüz tarama yapılmadı.', 'gbc-core' ) . '</em></p></div>';
		return;
	}

	/* Ozet */
	$sayac = array( 'tamam' => 0, 'eksik' => 0, 'acik' => 0, 'bilinmiyor' => 0 );
	foreach ( (array) $s['bulgu'] as $x ) {
		if ( isset( $sayac[ $x['durum'] ] ) ) { $sayac[ $x['durum'] ]++; }
	}
	$skor  = (int) $s['skor'];
	$renk  = ( $skor >= 85 ) ? '#1A7F37' : ( ( $skor >= 65 ) ? '#8A6100' : '#B32D2E' );
	$cevre = 327;
	$dolu  = (int) round( $cevre * $skor / 100 );

	echo '<div style="display:flex;gap:18px;flex-wrap:wrap;align-items:stretch;margin:18px 0">';

	echo '<div style="width:250px;background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:22px;text-align:center">'
		. '<svg viewBox="0 0 120 120" style="width:130px;height:130px" aria-hidden="true">'
		. '<circle cx="60" cy="60" r="52" fill="none" stroke="#E8EAEE" stroke-width="13"></circle>'
		. '<circle cx="60" cy="60" r="52" fill="none" stroke="' . esc_attr( $renk ) . '" stroke-width="13" stroke-linecap="round" stroke-dasharray="' . (int) $dolu . ' ' . (int) $cevre . '" transform="rotate(-90 60 60)"></circle>'
		. '</svg>'
		. '<div style="margin-top:-88px;font-size:40px;font-weight:700;line-height:1">' . $skor . '</div>'
		. '<div style="font-size:13px;color:#5C6470;margin-bottom:40px">' . esc_html__( '100 üzerinden', 'gbc-core' ) . '</div>'
		. '</div>';

	foreach ( array(
		array( __( 'Geçen kontrol', 'gbc-core' ), $sayac['tamam'], '#1A7F37' ),
		array( __( 'Eksik', 'gbc-core' ),          $sayac['eksik'], '#8A6100' ),
		array( __( 'Açık', 'gbc-core' ),           $sayac['acik'],  '#B32D2E' ),
	) as $kart ) {
		echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:20px 24px;min-width:150px">'
			. '<div style="font-size:12.5px;font-weight:600;color:#5C6470">' . esc_html( $kart[0] ) . '</div>'
			. '<div style="font-size:34px;font-weight:700;line-height:1.2;color:' . esc_attr( $kart[2] ) . '">' . (int) $kart[1] . '</div>'
			. '</div>';
	}
	echo '</div>';

	echo '<p style="color:#666">' . esc_html( sprintf( __( 'Son tarama: %s', 'gbc-core' ), date_i18n( 'j F Y, H:i', (int) $s['zaman'] ) ) );
	$sonraki = wp_next_scheduled( 'gbc_gv_tarama' );
	if ( $sonraki ) {
		echo ' &middot; ' . esc_html( sprintf( __( 'Sonraki otomatik tarama: %s', 'gbc-core' ), date_i18n( 'j F Y, H:i', $sonraki + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ) );
	}
	echo '</p>';

	/* Gorev paylasimi notu */
	echo '<div style="background:#FFF8EC;border:1px solid #F0DCB4;border-left:4px solid #C88A1E;border-radius:10px;padding:14px 18px;max-width:1000px;margin:14px 0">'
		. '<strong>' . esc_html__( 'Görev paylaşımı', 'gbc-core' ) . '</strong><br>'
		. esc_html__( 'Giriş sınırlama ve IP engelleme Loginizer\'da, iki adımlı giriş 2FA eklentisinde, sunucu duvarı ve yedek Hostinger\'da kalıyor. GBC Core başlıkları, açık kapıları ve form spam\'ini üstleniyor. Aynı işi iki eklenti yaparsa ikisi de bozulur.', 'gbc-core' )
		. '</div>';

	/* Bulgu tablolari */
	$gruplar = array(
		'kapi'   => __( 'Açık kapılar', 'gbc-core' ),
		'giris'  => __( 'Giriş güvenliği', 'gbc-core' ),
		'baslik' => __( 'Güvenlik başlıkları', 'gbc-core' ),
		'sistem' => __( 'Sistem', 'gbc-core' ),
	);

	foreach ( $gruplar as $g => $baslik ) {
		$satir = '';
		foreach ( (array) $s['bulgu'] as $x ) {
			if ( $x['grup'] !== $g ) { continue; }
			$vurgu = ( 'acik' === $x['durum'] ) ? ' style="background:#FFF8EC"' : '';
			$satir .= '<tr' . $vurgu . '>'
				. '<td><strong>' . esc_html( $x['ad'] ) . '</strong></td>'
				. '<td><code>' . esc_html( $x['deger'] ) . '</code></td>'
				. '<td>' . gbc_gv_rozet( $x['durum'] ) . '</td>'
				. '<td style="color:#5C6470">' . esc_html( $x['not'] ) . '</td>'
				. '</tr>';
		}
		if ( '' === $satir ) { continue; }
		echo '<h2 style="margin-top:26px">' . esc_html( $baslik ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th style="width:260px">' . esc_html__( 'Kontrol', 'gbc-core' ) . '</th>'
			. '<th style="width:230px">' . esc_html__( 'Şu anki değer', 'gbc-core' ) . '</th>'
			. '<th style="width:120px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Ne anlama geliyor', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>' . $satir . '</tbody></table>';
	}

	/* Yoneticiler ve 2FA */
	if ( ! empty( $s['2fa']['yoneticiler'] ) ) {
		echo '<h2 style="margin-top:26px">' . esc_html__( 'Yönetici hesapları ve iki adımlı giriş', 'gbc-core' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:760px"><thead><tr>'
			. '<th>' . esc_html__( 'Hesap', 'gbc-core' ) . '</th>'
			. '<th style="width:200px">' . esc_html__( 'Kullanıcı adı', 'gbc-core' ) . '</th>'
			. '<th style="width:150px">' . esc_html__( 'İki adımlı giriş', 'gbc-core' ) . '</th>'
			. '<th style="width:200px">' . esc_html__( 'Yöntem', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $s['2fa']['yoneticiler'] as $k ) {
			echo '<tr>'
				. '<td><strong>' . esc_html( $k['ad'] ) . '</strong>'
				. ( empty( $k['super'] ) ? '' : ' <span style="display:inline-block;padding:1px 8px;border-radius:12px;font-size:11px;font-weight:700;color:#7A2207;background:#F7E6DF;margin-left:6px">' . esc_html__( 'Süper yönetici', 'gbc-core' ) . '</span>' )
				. '</td>'
				. '<td><code>' . esc_html( $k['giris'] ) . '</code></td>'
				. '<td>' . ( $k['acik']
					? '<span style="display:inline-block;padding:3px 11px;border-radius:20px;font-size:12.5px;font-weight:700;color:#1A6B31;background:#E4F3E8">' . esc_html__( 'Kurulu', 'gbc-core' ) . '</span>'
					: '<span style="display:inline-block;padding:3px 11px;border-radius:20px;font-size:12.5px;font-weight:700;color:#9C2A2B;background:#FBE7E7">' . esc_html__( 'YOK', 'gbc-core' ) . '</span>' ) . '</td>'
				. '<td style="color:#5C6470">' . esc_html( $k['yontem'] ? $k['yontem'] : '—' ) . '</td>'
				. '</tr>';
		}
		echo '</tbody></table>';
	}

	/* Sunucu seviyesinde CSP varsa: elle duzeltilecek, hazir metni ver. */
	if ( ! empty( $s['csp']['sunucudan'] ) && false === stripos( (string) $s['csp']['deger'], 'frame-ancestors' ) ) {
		$onerilen = "Header always set Content-Security-Policy \"upgrade-insecure-requests; frame-ancestors 'self'; object-src 'none'; base-uri 'self'\"";
		echo '<h2 style="margin-top:26px">' . esc_html__( 'CSP başlığı sunucudan geliyor', 'gbc-core' ) . '</h2>';
		echo '<div style="background:#FFF8EC;border:1px solid #F0DCB4;border-left:4px solid #C88A1E;border-radius:10px;padding:16px 20px;max-width:1000px">';
		echo '<p style="margin:0 0 10px">' . esc_html__( 'Ölçüldü: statik bir dosya da aynı başlığı taşıyor. Statik dosyada PHP hiç çalışmaz, yani başlığı sunucu koyuyor ve eklentinin gönderdiğini eziyor. Bu satırı WordPress içinden değiştirmek mümkün değil.', 'gbc-core' ) . '</p>';
		if ( ! empty( $s['csp']['htaccess']['var'] ) ) {
			echo '<p style="margin:0 0 6px"><strong>' . esc_html( sprintf( __( 'Şu anki satır — .htaccess, %d. satır:', 'gbc-core' ), (int) $s['csp']['htaccess']['no'] ) ) . '</strong></p>';
			echo '<pre style="background:#fff;border:1px solid #E3E5E9;border-radius:6px;padding:10px 12px;overflow:auto;margin:0 0 12px"><code>' . esc_html( $s['csp']['htaccess']['satir'] ) . '</code></pre>';
			echo '<p style="margin:0 0 6px"><strong>' . esc_html__( 'Bununla değiştir:', 'gbc-core' ) . '</strong></p>';
		} else {
			echo '<p style="margin:0 0 6px"><strong>' . esc_html__( 'Satır .htaccess\'te bulunamadı; Hostinger hPanel\'deki başlık ayarından ya da LiteSpeed yapılandırmasından geliyor. Eklenecek satır:', 'gbc-core' ) . '</strong></p>';
		}
		echo '<pre style="background:#fff;border:1px solid #E3E5E9;border-radius:6px;padding:10px 12px;overflow:auto;margin:0"><code>' . esc_html( $onerilen ) . '</code></pre>';
		echo '<p style="margin:10px 0 0;color:#6B4C0A;font-size:13.5px">' . esc_html__( 'Bu satır hiçbir içeriği engellemez: yalnızca başkasının seni çerçeveye almasını, eski gömme türlerini ve enjekte edilen <base> etiketini kapatır. Betik ve stil kuralı bilerek yok — onlar sayfayı kırar.', 'gbc-core' ) . '</p>';

		$ht_yazilabilir = file_exists( ABSPATH . '.htaccess' ) && is_writable( ABSPATH . '.htaccess' );
		echo '<p style="margin:14px 0 0">';
		if ( $ht_yazilabilir ) {
			echo '<a class="button button-primary" href="'
				. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-guvenlik&gbc_gv_ht=1' ), 'gbc_gv' ) )
				. '">' . esc_html__( 'Bu satırı .htaccess\'e sen yaz', 'gbc-core' ) . '</a> ';
			if ( file_exists( ABSPATH . '.htaccess.gbc-yedek' ) ) {
				echo '<a class="button" href="'
					. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-guvenlik&gbc_gv_ht_geri=1' ), 'gbc_gv' ) )
					. '">' . esc_html__( 'Yedekten geri al', 'gbc-core' ) . '</a>';
			}
			echo '<br><span style="font-size:13px;color:#6B4C0A">'
				. esc_html__( 'Önce dosyanın yedeği alınır, sonra yazılır, sonra site gerçek bir istekle yoklanır. Site hata verirse yedek anında geri yüklenir — hiçbir değişiklik kalmaz.', 'gbc-core' )
				. '</span>';
		} else {
			echo '<span style="font-size:13.5px;color:#9C2A2B"><strong>'
				. esc_html__( '.htaccess yazılabilir değil', 'gbc-core' ) . '</strong> — '
				. esc_html__( 'eklenti dosyaya dokunamıyor. Yukarıdaki satırı Hostinger hPanel dosya yöneticisinden elle eklemen gerek.', 'gbc-core' )
				. '</span>';
		}
		echo '</p>';
		echo '</div>';
	}

	/* Duvarlar */
	$d = get_option( 'gbc_duvar', array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	$d = wp_parse_args( $d, array( 'hsts' => '1', 'csp' => '1', 'surum' => '1', 'duzenle' => '1', 'preload' => '' ) );

	$duvarlar = array(
		'duzenle' => array(
			__( 'Panelden kod düzenlemeyi kapat', 'gbc-core' ),
			__( 'Panele giren biri tema veya eklenti dosyasına kod yazamaz. Görünüm ve Eklentiler altındaki düzenleyici menüleri kaybolur.', 'gbc-core' ),
		),
		'surum' => array(
			__( 'Sürüm bilgisini gizle', 'gbc-core' ),
			__( 'X-Powered-By başlığını kaldırır; PHP sürümü her istekte duyurulmaz.', 'gbc-core' ),
		),
		'hsts' => array(
			__( 'HSTS\'i tamamla', 'gbc-core' ),
			__( 'Alt alan adlarını da zorunlu HTTPS kapsamına alır.', 'gbc-core' ),
		),
		'csp' => array(
			__( 'İçerik güvenlik kurallarını genişlet', 'gbc-core' ),
			__( 'frame-ancestors, object-src, base-uri ve form-action eklenir. Betik ve stil kuralları BİLEREK eklenmiyor — onlar sayfayı kırar.', 'gbc-core' ),
		),
		'preload' => array(
			__( 'HSTS preload (dikkat)', 'gbc-core' ),
			__( 'Yalnızca bütün alt alan adların HTTPS ise aç. Açıkken HTTP\'den erişilen bir alt alan adın tamamen erişilmez olur.', 'gbc-core' ),
		),
	);

	echo '<h2 style="margin-top:28px">' . esc_html__( 'Duvarlar', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:900px;color:#444">'
		. esc_html__( 'Her duvarın ayrı anahtarı var. Bir şey bozulursa yalnız onu kapat, diğerleri çalışmaya devam eder. Hepsinin toplam maliyeti birkaç başlık satırıdır; sayfa hızına etkisi ölçülemez.', 'gbc-core' )
		. '</p>';
	echo '<form method="post" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:6px 22px 18px;max-width:900px">';
	wp_nonce_field( 'gbc_gv' );
	foreach ( $duvarlar as $k => $v ) {
		echo '<p style="border-bottom:1px solid #EDEFF2;padding:14px 0;margin:0">'
			. '<label style="display:flex;gap:11px;align-items:flex-start;cursor:pointer">'
			. '<input type="checkbox" name="d_' . esc_attr( $k ) . '" value="1" style="margin-top:3px"' . checked( $d[ $k ], '1', false ) . '>'
			. '<span><strong>' . esc_html( $v[0] ) . '</strong><br>'
			. '<span style="color:#5C6470;font-size:13.5px">' . esc_html( $v[1] ) . '</span></span>'
			. '</label></p>';
	}
	echo '<p style="margin-top:16px"><button type="submit" name="gbc_duvar_kaydet" class="button button-primary">'
		. esc_html__( 'Duvarları kaydet', 'gbc-core' ) . '</button></p>';
	echo '</form>';

	/* Ayarlar */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Tarama ayarları', 'gbc-core' ) . '</h2>';
	echo '<form method="post" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:18px 22px;max-width:560px">';
	wp_nonce_field( 'gbc_gv' );
	echo '<p><label for="gbc_gv_siklik"><strong>' . esc_html__( 'Tarama sıklığı', 'gbc-core' ) . '</strong></label><br>';
	echo '<select name="gbc_gv_siklik" id="gbc_gv_siklik">';
	foreach ( array(
		'hourly'     => __( 'Saatte bir', 'gbc-core' ),
		'twicedaily' => __( 'Günde iki kez', 'gbc-core' ),
		'daily'      => __( 'Günde bir', 'gbc-core' ),
		'weekly'     => __( 'Haftada bir', 'gbc-core' ),
	) as $k => $v ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $ayar['sıklık'], $k, false ) . '>' . esc_html( $v ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label><input type="checkbox" name="gbc_gv_posta" value="1"' . checked( $ayar['posta'], '1', false ) . '> '
		. esc_html__( 'Önemli bir açık çıkarsa e-posta gönder', 'gbc-core' ) . '</label></p>';
	echo '<p><button type="submit" name="gbc_gv_kaydet" class="button button-primary">' . esc_html__( 'Kaydet', 'gbc-core' ) . '</button></p>';
	echo '</form>';

	echo '<p style="color:#666;margin-top:18px">'
		. esc_html__( 'Bu ekran ve taramalar yalnız yönetici tarafında ve cron\'da çalışır. Ziyaretçi isteğinde tek satırı bile yüklenmez; sitenin hızına etkisi sıfırdır.', 'gbc-core' )
		. '</p>';

	echo '</div>';
}
