<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30210 — GBC · 90 · Komuta Merkezi. GBC Core'a tasindi, 28 Eylul 2026. */

/* GBC · 90 · Komuta Merkezi — Çekirdek + Ayarlar
   ------------------------------------------------------------------
   NE YAPAR: wp-admin içinde "GBC Komuta" menüsünü açar ve API
   anahtarlarının girildiği Ayarlar ekranını kurar. Veri toplayıcı,
   sayfa kartı ve rapor ekranları ayrı snippet'lerde olacak; hepsi
   buradaki gbc_km_ayar() üzerinden anahtarları okur.

   ÖN YÜZE ETKİSİ SIFIR: ilk satır is_admin() değilse dönüyor.
   Ziyaretçi isteğinde tek bir fonksiyon bile tanımlanmıyor.

   ANAHTARLAR: gbc_km_ayar option'ında, autoload KAPALI. Gizli alanlar
   ekranda maskeli gösterilir, boş bırakılırsa eskisi korunur.
   Hiçbir gizli değer ekrana tam olarak basılmaz.

   NOT: bu snippet eskiden "92 · Ölü Şema Meta Temizliği · TEK
   KULLANIMLIK" idi, işi bitmişti. İçeriği tamamen değişti.
   ------------------------------------------------------------------ */

/* Yonetici tarafi VE sunucu cronu. Ziyaretci isteginde hicbir sey
   yuklenmiyor; cron calisirken is_admin() false oldugu icin otomatik
   cekimin fonksiyonlari yuklensin diye DOING_CRON de kabul ediliyor. */
if ( ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) { return; }

if ( ! function_exists( 'gbc_km_alanlar' ) ) {

/* Ayar alanlarının tanımı. gizli=1 olanlar maskelenir. */
function gbc_km_alanlar() {
	return array(
		'ga4_property'  => array( 'ad' => 'GA4 Property ID',                 'tip' => 'text',     'gizli' => 0, 'ipucu' => 'Sadece rakam. Seninki: 291790321' ),
		'gsc_site'      => array( 'ad' => 'Search Console site adresi',      'tip' => 'text',     'gizli' => 0, 'ipucu' => 'sc-domain:gezginbirchef.com  ya da  https://gezginbirchef.com/' ),
		'google_sa'     => array( 'ad' => 'Google servis hesabı (JSON)',     'tip' => 'textarea', 'gizli' => 1, 'ipucu' => 'Google Cloud → Servis Hesapları → Anahtar ekle → JSON. Dosyanın tamamını yapıştır. Search Console ve GA4 verisi bununla gelir.' ),
		'ads_dev'       => array( 'ad' => 'Google Ads geliştirici anahtarı', 'tip' => 'text',     'gizli' => 1, 'ipucu' => 'Google Ads → Araçlar → API Center' ),
		'ads_musteri'   => array( 'ad' => 'Google Ads müşteri no',           'tip' => 'text',     'gizli' => 0, 'ipucu' => '123-456-7890 biçiminde' ),
		'ads_client_id' => array( 'ad' => 'Google Ads OAuth istemci no',     'tip' => 'text',     'gizli' => 0, 'ipucu' => '...apps.googleusercontent.com' ),
		'ads_secret'    => array( 'ad' => 'Google Ads OAuth gizli anahtar',  'tip' => 'text',     'gizli' => 1, 'ipucu' => '' ),
		'ads_refresh'   => array( 'ad' => 'Google Ads yenileme belirteci',   'tip' => 'text',     'gizli' => 1, 'ipucu' => 'refresh_token' ),
	);
}

/* Tek okuma noktası. Diğer snippet'ler yalnız bunu çağırır. */
function gbc_km_ayar( $anahtar = null, $varsayilan = '' ) {
	static $a = null;
	if ( $a === null ) {
		$a = get_option( 'gbc_km_ayar', array() );
		if ( ! is_array( $a ) ) { $a = array(); }
	}
	if ( $anahtar === null ) { return $a; }
	return ( isset( $a[ $anahtar ] ) && $a[ $anahtar ] !== '' ) ? $a[ $anahtar ] : $varsayilan;
}

/* Gizli değeri ekranda gösterilebilir hale getirir. Tam değer asla basılmaz. */
function gbc_km_maske( $deger ) {
	$deger = (string) $deger;
	if ( $deger === '' ) { return ''; }
	$u = strlen( $deger );
	if ( $u > 400 ) {
		$mail = '';
		$j = json_decode( $deger, true );
		if ( is_array( $j ) && ! empty( $j['client_email'] ) ) { $mail = ' · ' . $j['client_email']; }
		return 'kayıtlı (' . $u . ' karakter)' . $mail;
	}
	return 'kayıtlı ····' . substr( $deger, -4 );
}

/* Toplayıcının dışarıdan tetiklenmesi için tek seferlik üretilen anahtar. */
function gbc_km_cron_anahtari() {
	$k = get_option( 'gbc_km_cron_anahtar', '' );
	if ( ! is_string( $k ) || strlen( $k ) < 20 ) {
		$k = wp_generate_password( 32, false, false );
		update_option( 'gbc_km_cron_anahtar', $k, false );
	}
	return $k;
}

/* ---------------- MENÜ ---------------- */
add_action( 'admin_menu', function () {
	/* Ayri ust menu degil: hepsi tek GBC catisi altinda toplaniyor. */
	/* v1.9.2: yeni SEO ekrani bunun yerini aldi — menuden kaldirildi, kod duruyor.
	add_submenu_page( 'gbc', 'GBC SEO Komuta', '— Komuta (eski)', 'manage_options', 'gbc-komuta', 'gbc_km_ekran_durum' ); */
	/* v1.9.2: menü kaydı inc/durum.php içinde toplandı. */
} );

/* ---------------- KAYDETME ---------------- */
add_action( 'admin_post_gbc_km_kaydet', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_km_kaydet' );

	$eski = get_option( 'gbc_km_ayar', array() );
	if ( ! is_array( $eski ) ) { $eski = array(); }
	$yeni = $eski;

	foreach ( gbc_km_alanlar() as $k => $t ) {
		if ( ! isset( $_POST[ 'gbc_km_' . $k ] ) ) { continue; }
		$ham = wp_unslash( $_POST[ 'gbc_km_' . $k ] );
		$ham = is_string( $ham ) ? trim( $ham ) : '';

		/* Gizli alan boş gönderildiyse eskisi korunur; silmek için
		   kutuya tek başına - yazılır. */
		if ( $t['gizli'] ) {
			if ( $ham === '' ) { continue; }
			if ( $ham === '-' ) { $yeni[ $k ] = ''; continue; }
		}

		if ( $k === 'google_sa' ) {
			$j = json_decode( $ham, true );
			if ( $ham !== '' && ( ! is_array( $j ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) ) {
				set_transient( 'gbc_km_uyari', 'Servis hesabı JSON okunamadı. client_email ve private_key içeren tam dosyayı yapıştır. Diğer alanlar kaydedildi.', 60 );
				continue;
			}
			$yeni[ $k ] = $ham;
			continue;
		}

		$yeni[ $k ] = sanitize_text_field( $ham );
	}

	update_option( 'gbc_km_ayar', $yeni, false );
	wp_safe_redirect( admin_url( 'admin.php?page=gbc-komuta-ayar&gbc=ok' ) );
	exit;
} );

/* ---------------- AYAR EKRANI ---------------- */
function gbc_km_ekran_ayar() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$uyari = get_transient( 'gbc_km_uyari' );
	if ( $uyari ) { delete_transient( 'gbc_km_uyari' ); }

	echo '<div class="wrap"><h1>GBC SEO · Ayarlar</h1>';

	if ( isset( $_GET['gbc'] ) && $_GET['gbc'] === 'ok' ) {
		echo '<div class="notice notice-success is-dismissible"><p>Kaydedildi.</p></div>';
	}
	if ( $uyari ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $uyari ) . '</p></div>';
	}

	echo '<p style="max-width:760px">Buraya girdiğin anahtarlar yalnızca bu sitenin veritabanında durur ve hiçbir ekranda tam olarak gösterilmez. Gizli bir alanı değiştirmek istemiyorsan boş bırak — eskisi korunur. Bir anahtarı silmek için kutuya tek başına <code>-</code> yaz.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'gbc_km_kaydet' );
	echo '<input type="hidden" name="action" value="gbc_km_kaydet">';
	echo '<table class="form-table" role="presentation"><tbody>';

	foreach ( gbc_km_alanlar() as $k => $t ) {
		$deger = gbc_km_ayar( $k, '' );
		$id    = 'gbc_km_' . $k;

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $t['ad'] ) . '</label></th><td>';

		if ( $t['tip'] === 'textarea' ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" rows="6" class="large-text code" spellcheck="false" placeholder="' . ( $deger !== '' ? 'Değiştirmek istemiyorsan boş bırak' : '' ) . '"></textarea>';
		} else {
			$val = $t['gizli'] ? '' : esc_attr( $deger );
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . $val . '" class="regular-text" autocomplete="off" spellcheck="false"' . ( $t['gizli'] && $deger !== '' ? ' placeholder="Değiştirmek istemiyorsan boş bırak"' : '' ) . '>';
		}

		if ( $t['gizli'] ) {
			echo '<p class="description"><strong>' . ( $deger !== '' ? esc_html( gbc_km_maske( $deger ) ) : 'boş' ) . '</strong></p>';
		}
		if ( $t['ipucu'] !== '' ) {
			echo '<p class="description">' . esc_html( $t['ipucu'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';
	submit_button( 'Kaydet' );
	echo '</form></div>';
}

/* ---------------- DURUM EKRANI ---------------- */
function gbc_km_ekran_durum() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$sa   = gbc_km_ayar( 'google_sa', '' );
	$saj  = $sa !== '' ? json_decode( $sa, true ) : null;
	$mail = ( is_array( $saj ) && ! empty( $saj['client_email'] ) ) ? $saj['client_email'] : '';

	$satir = function ( $ad, $tamam, $not ) {
		echo '<tr><td style="padding:8px 14px 8px 0;white-space:nowrap">' . esc_html( $ad ) . '</td>';
		echo '<td style="padding:8px 14px 8px 0;font-weight:600;color:' . ( $tamam ? '#1a7f37' : '#9a6700' ) . '">' . ( $tamam ? 'hazır' : 'bekliyor' ) . '</td>';
		echo '<td style="padding:8px 0;color:#555">' . esc_html( $not ) . '</td></tr>';
	};

	$cron_kapali = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

	echo '<div class="wrap"><h1>GBC SEO Merkezi</h1>';
	echo '<h2 style="margin-top:22px">Bağlantı durumu</h2>';
	echo '<table style="border-collapse:collapse;font-size:14px"><tbody>';

	$satir( 'GA4 property', gbc_km_ayar( 'ga4_property' ) !== '', gbc_km_ayar( 'ga4_property', 'Ayarlar ekranından gir' ) );
	$satir( 'Search Console', gbc_km_ayar( 'gsc_site' ) !== '', gbc_km_ayar( 'gsc_site', 'Ayarlar ekranından gir' ) );
	$satir( 'Google servis hesabı', $mail !== '', $mail !== '' ? $mail : 'JSON bekleniyor' );
	$satir( 'Google Ads', gbc_km_ayar( 'ads_dev' ) !== '' && gbc_km_ayar( 'ads_musteri' ) !== '', gbc_km_ayar( 'ads_musteri', 'Geliştirici anahtarı ve müşteri no bekleniyor' ) );
	$satir( 'Gerçek cron', $cron_kapali, $cron_kapali ? 'WP-Cron kapalı, dış cron çalışıyor' : 'WP-Cron hâlâ ziyaretçi tetiklemeli' );

	echo '</tbody></table>';

	echo '<h2 style="margin-top:30px">Dış cron adresi</h2>';
	echo '<p style="max-width:760px">Hostinger hPanel → Gelişmiş → Cron İşleri bölümüne aşağıdaki komutu <strong>5 dakikada bir</strong> çalışacak şekilde ekle. Bu komut yalnız WordPress\'in kendi görev kuyruğunu çalıştırır.</p>';
	echo '<p><code style="display:inline-block;padding:10px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;user-select:all">wget -q -O - ' . esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' &gt;/dev/null 2&gt;&amp;1</code></p>';

	echo '<h2 style="margin-top:30px">Sıradaki adımlar</h2>';
	echo '<ol style="max-width:760px;line-height:1.8">';
	echo '<li>Yukarıdaki cron satırını Hostinger\'a ekle.</li>';
	echo '<li>Ayarlar ekranından Google servis hesabı JSON\'unu yapıştır.</li>';
	echo '<li>Google Ads anahtarlarını gir (arama hacmi bununla gelecek).</li>';
	echo '<li>Üçü de "hazır" göründüğünde toplayıcı devreye alınacak.</li>';
	echo '</ol>';

	echo '</div>';
}

/* ================= GOOGLE API KATMANI =================
   Servis hesabi JSON'u ile JWT imzalanir, OAuth2 belirteci alinir,
   Search Console ve GA4 Data API cagrilir. Belirtec 55 dakika
   transient'te tutulur. Hepsi admin tarafinda; on yuze hicbir sey
   eklenmiyor. */

function gbc_km_b64( $v ) {
	return rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' );
}

function gbc_km_token( $zorla = false ) {
	if ( ! $zorla ) {
		$t = get_transient( 'gbc_km_token' );
		if ( is_string( $t ) && $t !== '' ) { return $t; }
	}

	$ham = gbc_km_ayar( 'google_sa', '' );
	if ( $ham === '' ) { return new WP_Error( 'yok', 'Servis hesabi JSON girilmemis.' ); }

	$sa = json_decode( $ham, true );
	if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
		return new WP_Error( 'bozuk', 'Servis hesabi JSON okunamadi.' );
	}

	$simdi = time();
	$basl  = gbc_km_b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$iddia = gbc_km_b64( wp_json_encode( array(
		'iss'   => $sa['client_email'],
		'scope' => 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly',
		'aud'   => 'https://oauth2.googleapis.com/token',
		'iat'   => $simdi,
		'exp'   => $simdi + 3600,
	) ) );

	$girdi = $basl . '.' . $iddia;
	$imza  = '';
	if ( ! openssl_sign( $girdi, $imza, $sa['private_key'], 'sha256WithRSAEncryption' ) ) {
		return new WP_Error( 'imza', 'JWT imzalanamadi. private_key bozuk olabilir.' );
	}

	$c = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'timeout' => 25,
		'body'    => array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $girdi . '.' . gbc_km_b64( $imza ),
		),
	) );

	if ( is_wp_error( $c ) ) { return $c; }

	$kod = (int) wp_remote_retrieve_response_code( $c );
	$g   = json_decode( wp_remote_retrieve_body( $c ), true );

	if ( $kod !== 200 || empty( $g['access_token'] ) ) {
		$m = ( is_array( $g ) && ! empty( $g['error_description'] ) ) ? $g['error_description'] : wp_remote_retrieve_body( $c );
		return new WP_Error( 'token', 'Belirtec alinamadi (HTTP ' . $kod . '): ' . substr( (string) $m, 0, 300 ) );
	}

	set_transient( 'gbc_km_token', $g['access_token'], 3300 );
	return $g['access_token'];
}

function gbc_km_api( $url, $govde ) {
	$tok = gbc_km_token();
	if ( is_wp_error( $tok ) ) { return $tok; }

	$c = wp_remote_post( $url, array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $tok,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( $govde ),
	) );

	if ( is_wp_error( $c ) ) { return $c; }

	$kod = (int) wp_remote_retrieve_response_code( $c );
	$g   = json_decode( wp_remote_retrieve_body( $c ), true );

	if ( $kod !== 200 ) {
		$m = ( is_array( $g ) && ! empty( $g['error']['message'] ) ) ? $g['error']['message'] : wp_remote_retrieve_body( $c );
		return new WP_Error( 'api', 'HTTP ' . $kod . ' - ' . substr( (string) $m, 0, 400 ) );
	}
	return is_array( $g ) ? $g : array();
}

function gbc_km_gsc( $bas, $bit, $boyut = array( 'query' ), $satir = 10 ) {
	$site = gbc_km_ayar( 'gsc_site', '' );
	if ( $site === '' ) { return new WP_Error( 'yok', 'Search Console site adresi girilmemis.' ); }
	return gbc_km_api(
		'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query',
		array(
			'startDate'  => $bas,
			'endDate'    => $bit,
			'dimensions' => $boyut,
			'rowLimit'   => (int) $satir,
		)
	);
}

function gbc_km_ga4( $govde ) {
	$p = preg_replace( '/[^0-9]/', '', (string) gbc_km_ayar( 'ga4_property', '' ) );
	if ( $p === '' ) { return new WP_Error( 'yok', 'GA4 Property ID girilmemis.' ); }
	return gbc_km_api( 'https://analyticsdata.googleapis.com/v1beta/properties/' . $p . ':runReport', $govde );
}

/* ================= BAGLANTI TESTI EKRANI ================= */

add_action( 'admin_menu', function () {
	/* v1.9.2: menü kaydı inc/durum.php içinde toplandı. */
}, 20 );

function gbc_km_ekran_test() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	echo '<div class="wrap"><h1>GBC SEO · Bağlantı Testi</h1>';
	echo '<p style="max-width:760px">Üç şeyi sırayla dener: Google belirteci, Search Console sorgusu, GA4 raporu. Hiçbir veri kaydetmez, sadece çalışıyor mu diye bakar.</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-test&calistir=1' ), 'gbc_km_test' ) ) . '">Testi çalıştır</a></p>';

	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	$calis = isset( $_GET['calistir'] ) && $_GET['calistir'] === '1' && wp_verify_nonce( $nonce, 'gbc_km_test' );
	if ( ! $calis ) { echo '</div>'; return; }

	$kutu = function ( $ad, $ok, $metin ) {
		echo '<div style="margin:16px 0;padding:14px 16px;border:1px solid ' . ( $ok ? '#1a7f37' : '#d1242f' ) . ';border-left-width:5px;border-radius:6px;background:#fff">';
		echo '<strong style="color:' . ( $ok ? '#1a7f37' : '#d1242f' ) . '">' . ( $ok ? 'ÇALIŞTI' : 'HATA' ) . '</strong> · ' . esc_html( $ad );
		echo '<pre style="margin:10px 0 0;white-space:pre-wrap;font-size:12px;color:#333">' . esc_html( $metin ) . '</pre></div>';
	};

	$tok = gbc_km_token( true );
	if ( is_wp_error( $tok ) ) {
		$kutu( '1. Google belirteci', false, $tok->get_error_message() );
		echo '</div>';
		return;
	}
	$sa   = json_decode( gbc_km_ayar( 'google_sa', '' ), true );
	$mail = ( is_array( $sa ) && ! empty( $sa['client_email'] ) ) ? $sa['client_email'] : '?';
	$ga4_ham = (string) gbc_km_ayar( 'ga4_property', '' );
	$ga4_tem = preg_replace( '/[^0-9]/', '', $ga4_ham );
	$kutu(
		'1. Google belirteci ve ayarlar',
		true,
		'Servis hesabi : ' . $mail . "\n"
		. 'Belirtec      : alindi (' . strlen( $tok ) . ' karakter)' . "\n"
		. 'GSC site      : [' . gbc_km_ayar( 'gsc_site', '(bos)' ) . ']' . "\n"
		. 'GA4 property  : girilen [' . $ga4_ham . ']  ->  kullanilan [' . $ga4_tem . ']'
	);

	$bas = gmdate( 'Y-m-d', time() - 9 * DAY_IN_SECONDS );
	$bit = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$g   = gbc_km_gsc( $bas, $bit, array( 'query' ), 5 );
	if ( is_wp_error( $g ) ) {
		$kutu( '2. Search Console (' . $bas . ' - ' . $bit . ')', false, $g->get_error_message() );
	} else {
		$s = array();
		foreach ( (array) ( isset( $g['rows'] ) ? $g['rows'] : array() ) as $r ) {
			$s[] = substr( (string) $r['keys'][0], 0, 40 ) . '  |  tik ' . (int) $r['clicks'] . '  |  gosterim ' . (int) $r['impressions'] . '  |  poz ' . round( (float) $r['position'], 1 );
		}
		$kutu( '2. Search Console (' . $bas . ' - ' . $bit . ')', true, $s ? implode( "\n", $s ) : 'Baglanti calisti, bu araligda satir yok.' );
	}

	$a = gbc_km_ga4( array(
		'dateRanges' => array( array( 'startDate' => '7daysAgo', 'endDate' => 'yesterday' ) ),
		'dimensions' => array( array( 'name' => 'pagePath' ) ),
		'metrics'    => array( array( 'name' => 'sessions' ), array( 'name' => 'screenPageViews' ) ),
		'limit'      => 5,
	) );
	if ( is_wp_error( $a ) ) {
		$kutu( '3. GA4 (son 7 gun)', false, $a->get_error_message() );
	} else {
		$s = array();
		foreach ( (array) ( isset( $a['rows'] ) ? $a['rows'] : array() ) as $r ) {
			$s[] = substr( (string) $r['dimensionValues'][0]['value'], 0, 48 ) . '  |  oturum ' . $r['metricValues'][0]['value'] . '  |  goruntulenme ' . $r['metricValues'][1]['value'];
		}
		$kutu( '3. GA4 (son 7 gun)', true, $s ? implode( "\n", $s ) : 'Baglanti calisti, satir yok.' );
	}

	echo '</div>';
}

/* ================= VERI TABLOLARI =================
   gbc_km_sayfa : tarih x sayfa  -> tik, gosterim, pozisyon (GSC)
                                    oturum, goruntulenme, sure (GA4)
   gbc_km_sorgu : tarih x sorgu x sayfa -> kanibalizasyonun kaynagi.
                  Ayni sorguda birden fazla sayfa gosterim aliyorsa
                  buradan cikar. */

function gbc_km_tablo_kur() {
	if ( get_option( 'gbc_km_sema' ) === '3' ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE {$wpdb->prefix}gbc_km_sayfa (
		tarih date NOT NULL,
		yol varchar(180) NOT NULL,
		tik int unsigned NOT NULL DEFAULT 0,
		gosterim int unsigned NOT NULL DEFAULT 0,
		pozisyon decimal(6,2) NOT NULL DEFAULT 0,
		oturum int unsigned NOT NULL DEFAULT 0,
		goruntulenme int unsigned NOT NULL DEFAULT 0,
		sure int unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (tarih,yol)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gbc_km_sorgu (
		tarih date NOT NULL,
		sorgu varchar(150) NOT NULL,
		yol varchar(150) NOT NULL,
		tik int unsigned NOT NULL DEFAULT 0,
		gosterim int unsigned NOT NULL DEFAULT 0,
		pozisyon decimal(6,2) NOT NULL DEFAULT 0,
		PRIMARY KEY  (tarih,sorgu,yol),
		KEY sorgu (sorgu)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gbc_km_ay (
		ay char(7) NOT NULL,
		yol varchar(180) NOT NULL,
		tik int unsigned NOT NULL DEFAULT 0,
		gosterim int unsigned NOT NULL DEFAULT 0,
		pozisyon decimal(6,2) NOT NULL DEFAULT 0,
		PRIMARY KEY  (ay,yol),
		KEY yol (yol)
	) $c;" );

	update_option( 'gbc_km_sema', '3', false );
}
add_action( 'admin_init', 'gbc_km_tablo_kur' );

function gbc_km_yol( $url ) {
	$p = wp_parse_url( (string) $url );
	$y = isset( $p['path'] ) ? $p['path'] : '/';
	return substr( '/' . ltrim( $y, '/' ), 0, 180 );
}

/* GA4 gunluk istek sayaci. Tavan asilirsa cekim kendini durdurur,
   ertesi gun kaldigi yerden devam eder. Boylece kotaya hic carpmayiz. */
function gbc_km_ga4_sayac( $arttir = true ) {
	$bugun = gmdate( 'Y-m-d' );
	$s = get_option( 'gbc_km_ga4_sayac', array() );
	if ( ! is_array( $s ) || ! isset( $s['gun'] ) || $s['gun'] !== $bugun ) {
		$s = array( 'gun' => $bugun, 'adet' => 0 );
	}
	if ( $arttir ) {
		$s['adet']++;
		update_option( 'gbc_km_ga4_sayac', $s, false );
	}
	return (int) $s['adet'];
}

function gbc_km_ga4_tavan() { return 200; }

/* URL Inspection API — bir sayfa Google'da var mi, ne zaman taranmis.
   Gunluk 2000 sorgu hakki var; sonuc 12 saat onbellekte tutulur. */
function gbc_km_index( $url ) {
	$a = 'gbc_km_ix_' . md5( (string) $url );
	$c = get_transient( $a );
	if ( is_array( $c ) ) { return $c; }

	$site = gbc_km_ayar( 'gsc_site', '' );
	if ( $site === '' ) { return new WP_Error( 'yok', 'Search Console site adresi girilmemis.' ); }

	$g = gbc_km_api( 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', array(
		'inspectionUrl' => (string) $url,
		'siteUrl'       => $site,
		'languageCode'  => 'tr',
	) );
	if ( is_wp_error( $g ) ) { return $g; }

	$s = isset( $g['inspectionResult']['indexStatusResult'] ) ? $g['inspectionResult']['indexStatusResult'] : array();
	if ( ! $s ) { return new WP_Error( 'bos', 'Sonuc bos dondu.' ); }
	set_transient( $a, $s, 12 * HOUR_IN_SECONDS );
	return $s;
}

function gbc_km_cek( $tarih ) {
	global $wpdb;
	$t_sayfa = $wpdb->prefix . 'gbc_km_sayfa';
	$t_sorgu = $wpdb->prefix . 'gbc_km_sorgu';
	$site    = gbc_km_ayar( 'gsc_site', '' );
	$rapor   = array();

	if ( $site === '' ) { return new WP_Error( 'yok', 'Search Console site adresi girilmemis.' ); }

	$url_gsc = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';

	$bas = 0; $n = 0;
	do {
		$g = gbc_km_api( $url_gsc, array(
			'startDate' => $tarih, 'endDate' => $tarih,
			'dimensions' => array( 'page' ), 'rowLimit' => 5000, 'startRow' => $bas,
		) );
		if ( is_wp_error( $g ) ) { return $g; }
		$r = ( isset( $g['rows'] ) && is_array( $g['rows'] ) ) ? $g['rows'] : array();
		foreach ( $r as $x ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$t_sayfa} (tarih,yol,tik,gosterim,pozisyon) VALUES (%s,%s,%d,%d,%f)
				 ON DUPLICATE KEY UPDATE tik=VALUES(tik),gosterim=VALUES(gosterim),pozisyon=VALUES(pozisyon)",
				$tarih, gbc_km_yol( $x['keys'][0] ), (int) $x['clicks'], (int) $x['impressions'], (float) $x['position']
			) );
			$n++;
		}
		$bas += count( $r );
	} while ( count( $r ) === 5000 && $bas < 25000 );
	$rapor[] = 'GSC sayfa: ' . $n;

	$bas = 0; $n = 0;
	do {
		$g = gbc_km_api( $url_gsc, array(
			'startDate' => $tarih, 'endDate' => $tarih,
			'dimensions' => array( 'query', 'page' ), 'rowLimit' => 5000, 'startRow' => $bas,
		) );
		if ( is_wp_error( $g ) ) { return $g; }
		$r = ( isset( $g['rows'] ) && is_array( $g['rows'] ) ) ? $g['rows'] : array();
		foreach ( $r as $x ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$t_sorgu} (tarih,sorgu,yol,tik,gosterim,pozisyon) VALUES (%s,%s,%s,%d,%d,%f)
				 ON DUPLICATE KEY UPDATE tik=VALUES(tik),gosterim=VALUES(gosterim),pozisyon=VALUES(pozisyon)",
				$tarih, substr( (string) $x['keys'][0], 0, 150 ), substr( gbc_km_yol( $x['keys'][1] ), 0, 150 ),
				(int) $x['clicks'], (int) $x['impressions'], (float) $x['position']
			) );
			$n++;
		}
		$bas += count( $r );
	} while ( count( $r ) === 5000 && $bas < 25000 );
	$rapor[] = 'GSC sorgu x sayfa: ' . $n;

	if ( gbc_km_ga4_sayac( false ) >= gbc_km_ga4_tavan() ) {
		$rapor[] = 'GA4 atlandi (gunluk tavan doldu)';
		return $rapor;
	}

	$off = 0; $n = 0;
	$pid = preg_replace( '/[^0-9]/', '', (string) gbc_km_ayar( 'ga4_property', '' ) );
	do {
		gbc_km_ga4_sayac( true );
		$a = gbc_km_api( 'https://analyticsdata.googleapis.com/v1beta/properties/' . $pid . ':runReport', array(
			'dateRanges' => array( array( 'startDate' => $tarih, 'endDate' => $tarih ) ),
			'dimensions' => array( array( 'name' => 'pagePath' ) ),
			'metrics'    => array( array( 'name' => 'sessions' ), array( 'name' => 'screenPageViews' ), array( 'name' => 'userEngagementDuration' ) ),
			'limit'      => 5000,
			'offset'     => $off,
		) );
		if ( is_wp_error( $a ) ) { $rapor[] = 'GA4 HATA: ' . $a->get_error_message(); return $rapor; }
		$r = ( isset( $a['rows'] ) && is_array( $a['rows'] ) ) ? $a['rows'] : array();
		foreach ( $r as $x ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$t_sayfa} (tarih,yol,oturum,goruntulenme,sure) VALUES (%s,%s,%d,%d,%d)
				 ON DUPLICATE KEY UPDATE oturum=VALUES(oturum),goruntulenme=VALUES(goruntulenme),sure=VALUES(sure)",
				$tarih, gbc_km_yol( $x['dimensionValues'][0]['value'] ),
				(int) $x['metricValues'][0]['value'], (int) $x['metricValues'][1]['value'], (int) $x['metricValues'][2]['value']
			) );
			$n++;
		}
		$off += count( $r );
	} while ( count( $r ) === 5000 && $off < 25000 );
	$rapor[] = 'GA4 sayfa: ' . $n;

	return $rapor;
}

/* ================= VERI CEKME EKRANI ================= */

add_action( 'admin_menu', function () {
	/* v1.9.2: menü kaydı inc/durum.php içinde toplandı. */
}, 30 );

function gbc_km_ekran_veri() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$t_sayfa = $wpdb->prefix . 'gbc_km_sayfa';
	$t_sorgu = $wpdb->prefix . 'gbc_km_sorgu';

	echo '<div class="wrap"><h1>GBC SEO · Veri Çek</h1>';

	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	$gun   = isset( $_GET['gun'] ) ? (int) $_GET['gun'] : 0;
	$calis = $gun > 0 && wp_verify_nonce( $nonce, 'gbc_km_veri' );

	if ( $calis ) {
		@set_time_limit( 420 );
		$gun = min( 30, $gun );
		echo '<div style="padding:12px 16px;border:1px solid #dcdcde;border-left:5px solid #2271b1;border-radius:6px;background:#fff;margin:14px 0"><strong>Çekim raporu</strong><pre style="white-space:pre-wrap;font-size:12px;margin:8px 0 0">';
		for ( $i = 3; $i < 3 + $gun; $i++ ) {
			$t = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$r = gbc_km_cek( $t );
			if ( is_wp_error( $r ) ) {
				echo esc_html( $t . '  HATA: ' . $r->get_error_message() ) . "\n";
				break;
			}
			echo esc_html( $t . '  ' . implode( ' · ', $r ) ) . "\n";
			flush();
		}
		echo '</pre></div>';
	}

	if ( isset( $_GET['sezon'] ) && $_GET['sezon'] === '1' && wp_verify_nonce( $nonce, 'gbc_km_veri' ) ) {
		@set_time_limit( 420 );
		$r = gbc_km_cek_ay( 12 );
		echo '<div style="padding:12px 16px;border:1px solid #dcdcde;border-left:5px solid #6b46c1;border-radius:6px;background:#fff;margin:14px 0"><strong>12 aylık sezon çekimi</strong><pre style="white-space:pre-wrap;font-size:12px;margin:8px 0 0">';
		echo esc_html( is_wp_error( $r ) ? 'HATA: ' . $r->get_error_message() : implode( "\n", (array) $r ) );
		echo '</pre></div>';
	}

	$s1 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_sayfa}" );
	$s2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_sorgu}" );
	$ar = $wpdb->get_row( "SELECT MIN(tarih) ilk, MAX(tarih) son FROM {$t_sayfa}" );

	$sc = get_option( 'gbc_km_son_cekim', array() );
	$ss = get_option( 'gbc_km_son_sezon', array() );
	$nx = wp_next_scheduled( 'gbc_km_gunluk_cek' );
	echo '<h2>Otomatik çekim</h2><table style="border-collapse:collapse;font-size:14px"><tbody>';
	echo '<tr><td style="padding:6px 18px 6px 0">Sıradaki günlük çekim</td><td><strong>' . esc_html( $nx ? wp_date( 'j M Y H:i', $nx ) : 'kurulmadı' ) . '</strong></td></tr>';
	echo '<tr><td style="padding:6px 18px 6px 0">Son günlük çekim</td><td><strong>' . esc_html( ! empty( $sc['zaman'] ) ? wp_date( 'j M H:i', $sc['zaman'] ) . ' — ' . $sc['tarih'] : 'henüz çalışmadı' ) . '</strong>'
		. ( ! empty( $sc['sonuc'] ) ? '<br><span style="color:#646970;font-size:12px">' . esc_html( $sc['sonuc'] ) . '</span>' : '' ) . '</td></tr>';
	echo '<tr><td style="padding:6px 18px 6px 0">Son sezon tazelemesi</td><td><strong>' . esc_html( ! empty( $ss['zaman'] ) ? wp_date( 'j M H:i', $ss['zaman'] ) : 'henüz çalışmadı' ) . '</strong></td></tr>';
	echo '</tbody></table>';

	echo '<h2 style="margin-top:24px">Depo durumu</h2><table style="border-collapse:collapse;font-size:14px"><tbody>';
	echo '<tr><td style="padding:6px 18px 6px 0">Sayfa × gün satırı</td><td><strong>' . number_format_i18n( $s1 ) . '</strong></td></tr>';
	echo '<tr><td style="padding:6px 18px 6px 0">Sorgu × sayfa satırı</td><td><strong>' . number_format_i18n( $s2 ) . '</strong></td></tr>';
	echo '<tr><td style="padding:6px 18px 6px 0">Kapsanan aralık</td><td><strong>' . esc_html( $ar && $ar->ilk ? $ar->ilk . ' — ' . $ar->son : 'boş' ) . '</strong></td></tr>';
	echo '<tr><td style="padding:6px 18px 6px 0">Bugünkü GA4 isteği</td><td><strong>' . gbc_km_ga4_sayac( false ) . ' / ' . gbc_km_ga4_tavan() . '</strong></td></tr>';
	echo '</tbody></table>';

	$bag = function ( $g, $ad ) {
		return '<a class="button" style="margin-right:8px" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-veri&gun=' . $g ), 'gbc_km_veri' ) ) . '">' . esc_html( $ad ) . '</a>';
	};

	echo '<h2 style="margin-top:26px">Çek</h2>';
	echo '<p style="max-width:760px">Search Console verisi 2–3 gün gecikmeli geldiği için çekim bugünden 3 gün geriden başlar. Aynı günü tekrar çekmek zararsız — satırlar üzerine yazılır, kopya oluşmaz.</p>';
	echo '<p>' . $bag( 7, 'Son 7 günü çek' ) . $bag( 30, 'Son 30 günü çek' ) . '</p>';
	$ay_sayi = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ay) FROM {$wpdb->prefix}gbc_km_ay" );
	echo '<h2 style="margin-top:24px">Sezon ölçümü</h2>';
	echo '<p style="max-width:760px">Son 12 ayın her biri için ayrı bir sorgu atar (toplam 12 istek) ve her sayfanın hangi aylarda arandığını ölçer. Elle yazılan sezon tahmindi; bu rakam. Ayda bir çalıştırman yeter. Şu an depoda <strong>' . $ay_sayi . ' ay</strong> var.</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-veri&sezon=1' ), 'gbc_km_veri' ) ) . '">12 aylık sezon verisini çek</a></p>';
	echo '<p class="description">30 gün birkaç dakika sürebilir, sayfayı kapatma.</p>';

	echo '</div>';
}

/* ================= SAYFA KARTI =================
   Yaziyi acar acmaz o sayfanin kendi calisma alani: canli rakamlar,
   hangi aramalardan geliyor, kanibalizasyon var mi, yil eskimis mi.
   Editorde metabox olarak duruyor; on yuze hicbir sey eklemiyor. */

function gbc_km_kart_yol( $pid ) {
	$p = wp_parse_url( (string) get_permalink( $pid ) );
	$y = isset( $p['path'] ) ? $p['path'] : '/';
	return '/' . ltrim( $y, '/' );
}

function gbc_km_sablon( $pid ) {
	$harita = array(
		'24751' => 'Tarif', '22607' => 'Gezi', '23108' => 'Liste',
		'23489' => 'Detay', '23340' => 'Rota', '26922' => 'Blog', '24156' => 'Sözlük',
	);
	$c = (string) get_post_field( 'post_content', $pid );
	if ( preg_match( '/\[wpcode[^\]]*id=["\']?(\d+)/', $c, $m ) && isset( $harita[ $m[1] ] ) ) {
		return $harita[ $m[1] ];
	}
	return 'Bilinmiyor';
}

function gbc_km_eski_yil( $pid ) {
	$metin = (string) get_post_field( 'post_content', $pid );
	foreach ( array( 'hero_intro_text', 'detailed_main_content', 'gun_cevap', 'rehber_faq' ) as $alan ) {
		$v = get_post_meta( $pid, $alan, true );
		if ( is_string( $v ) ) { $metin .= ' ' . $v; }
	}
	$metin .= ' ' . get_the_title( $pid );
	$bu = (int) gmdate( 'Y' );
	$bulunan = array();
	if ( preg_match_all( '/\b(20[2-3][0-9])\b/', $metin, $m ) ) {
		foreach ( $m[1] as $y ) {
			$y = (int) $y;
			if ( $y < $bu ) { $bulunan[ $y ] = isset( $bulunan[ $y ] ) ? $bulunan[ $y ] + 1 : 1; }
		}
	}
	krsort( $bulunan );
	return $bulunan;
}

function gbc_km_kart_ciz( $post ) {
	global $wpdb;
	$pid = (int) $post->ID;
	$yol = gbc_km_kart_yol( $pid );
	$ts  = $wpdb->prefix . 'gbc_km_sayfa';
	$tq  = $wpdb->prefix . 'gbc_km_sorgu';

	$b1 = gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS );
	$s1 = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$b0 = gmdate( 'Y-m-d', time() - 59 * DAY_IN_SECONDS );
	$gun = isset( $_GET['gbcgun'] ) ? (int) $_GET['gbcgun'] : 28;
	if ( ! in_array( $gun, array( 1, 7, 14, 28, 90 ), true ) ) { $gun = 28; }
	$b1 = gmdate( 'Y-m-d', time() - ( $gun + 3 ) * DAY_IN_SECONDS );
	$s1 = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$b0 = gmdate( 'Y-m-d', time() - ( $gun * 2 + 3 ) * DAY_IN_SECONDS );
	$s0 = gmdate( 'Y-m-d', time() - ( $gun + 4 ) * DAY_IN_SECONDS );

	$sor = "SELECT SUM(tik) tik, SUM(gosterim) gos,
			SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz,
			SUM(oturum) otu, SUM(sure) sur
			FROM {$ts} WHERE yol=%s AND tarih BETWEEN %s AND %s";
	$a = $wpdb->get_row( $wpdb->prepare( $sor, $yol, $b1, $s1 ) );
	$o = $wpdb->get_row( $wpdb->prepare( $sor, $yol, $b0, $s0 ) );

	echo '<style>.gbckm table{width:100%;border-collapse:collapse;font-size:13px}.gbckm td,.gbckm th{padding:5px 8px;border-bottom:1px solid #f0f0f1;text-align:left}.gbckm th{color:#646970;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.04em}.gbckm h4{margin:20px 0 8px;font-size:13px;color:#1d2327}.gbckm .rz{display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;font-weight:600}</style>';
	echo '<div class="gbckm">';
	echo '<p style="color:#646970;margin:0 0 12px">Şablon: <b>' . esc_html( gbc_km_sablon( $pid ) ) . '</b> · Yol: <code>' . esc_html( $yol ) . '</code></p>';

	if ( ! $a || ! $a->gos ) {
		/* "Veri yok" tek basina bir sey anlatmiyor: depo mu bos, sayfa mi
		   cok yeni, yoksa gercekten hic gosterim mi almamis? Ucunu ayiriyoruz. */
		$depo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tarih) gun, MIN(tarih) ilk, MAX(tarih) son, SUM(gosterim) gos
				 FROM {$ts} WHERE tarih BETWEEN %s AND %s",
				$b1, $s1
			)
		);
		$yayin  = get_post_time( 'Y-m-d', true, $pid );
		$yas    = (int) floor( ( time() - (int) get_post_time( 'U', true, $pid ) ) / DAY_IN_SECONDS );
		$link   = esc_url( admin_url( 'admin.php?page=gbc-komuta-veri' ) );
		$notlar = array();

		if ( ! $depo || ! $depo->gun ) {
			$sebep    = '<b>Depoda bu aralık için hiç gün yok.</b> Sorun bu sayfada değil, veri deposunda.';
			$notlar[] = '<a href="' . $link . '">Veri Çek</a> ekranından "Son 30 günü çek" yap.';
		} elseif ( $yayin > $s1 ) {
			$sebep    = '<b>Sayfa henüz veri penceresine girmedi.</b> ' . esc_html( $yayin ) . ' tarihinde yayınlanmış (' . $yas . ' gün önce); Search Console 3 gün gecikmeli, pencere ' . esc_html( $s1 ) . ' tarihinde bitiyor.';
			$notlar[] = 'İlk veriler yayından birkaç gün sonra düşmeye başlar. Beklemek yeterli, yapılacak bir şey yok.';
		} elseif ( $yayin > $b1 ) {
			$sebep    = '<b>Sayfa pencereden yeni.</b> ' . esc_html( $yayin ) . ' tarihinde yayınlanmış; depodaki aralık ' . esc_html( $depo->ilk ) . ' – ' . esc_html( $depo->son ) . '. Yayın öncesi günler bu sayfa için zaten boş.';
			$notlar[] = 'Yayın tarihinden sonraki günler çekilmediyse <a href="' . $link . '">Veri Çek</a> ile o aralığı çek.';
		} else {
			$sebep    = '<b>Sayfa yeterince eski ama tek gösterim bile almamış.</b> Bu indeks sorununa işaret eder.';
			$notlar[] = 'Aşağıdaki <b>SEO Denetimi</b> kutusunda "Denetle" düğmesine bas; 9. madde indeks durumunu doğrudan Google\'a sorar.';
		}
		$notlar[] = 'Depo: ' . ( $depo && $depo->gun ? (int) $depo->gun . ' gün (' . esc_html( $depo->ilk ) . ' – ' . esc_html( $depo->son ) . '), toplam ' . number_format_i18n( (int) $depo->gos ) . ' gösterim' : 'boş' ) . '.';

		echo '<div style="padding:12px 14px;background:#fff8e5;border-left:3px solid #dba617;border-radius:4px">'
			. '<p style="margin:0 0 6px">' . $sebep . '</p>'
			. '<p style="margin:0;color:#646970;font-size:12px;line-height:1.7">' . implode( '<br>', $notlar ) . '</p>'
			. '</div></div>';
		return;
	}

	$ctr  = $a->gos ? ( $a->tik / $a->gos * 100 ) : 0;
	$sure = $a->otu ? round( $a->sur / $a->otu ) : 0;
	$fark = function ( $yeni, $eski ) {
		$yeni = (float) $yeni; $eski = (float) $eski;
		if ( $eski <= 0 ) { return ''; }
		$d = ( $yeni - $eski ) / $eski * 100;
		$r = $d >= 0 ? '#1a7f37' : '#d1242f';
		return ' <span style="color:' . $r . ';font-size:11px">' . ( $d >= 0 ? '+' : '' ) . round( $d ) . '%</span>';
	};

	$sec = '';
	foreach ( array( 1 => '1 gün', 7 => '7 gün', 14 => '14 gün', 28 => '28 gün', 90 => '90 gün' ) as $g => $et ) {
		$akt = ( $g === $gun );
		$sec .= '<a href="' . esc_url( add_query_arg( 'gbcgun', $g, get_edit_post_link( $pid, 'raw' ) ) ) . '" style="display:inline-block;padding:2px 9px;margin-right:4px;border-radius:12px;font-size:12px;text-decoration:none;'
			. ( $akt ? 'background:#2271b1;color:#fff;font-weight:600' : 'background:#f0f0f1;color:#50575e' ) . '">' . $et . '</a>';
	}
	echo '<h4 style="display:flex;align-items:center;gap:12px">Son ' . (int) $gun . ' gün <span style="font-weight:400">' . $sec . '</span></h4>';
	echo '<p style="margin:0 0 8px;color:#646970;font-size:11px">' . esc_html( $b1 ) . ' — ' . esc_html( $s1 ) . ' · tüm ülkeler · Search Console verisi 3 gün gecikmeli</p>';
	echo '<table><tr>';
	echo '<td><b>' . number_format_i18n( (int) $a->tik ) . '</b>' . $fark( $a->tik, $o ? $o->tik : 0 ) . '<br><span style="color:#646970;font-size:11px">tık</span></td>';
	echo '<td><b>' . number_format_i18n( (int) $a->gos ) . '</b>' . $fark( $a->gos, $o ? $o->gos : 0 ) . '<br><span style="color:#646970;font-size:11px">gösterim</span></td>';
	echo '<td><b>' . round( $ctr, 2 ) . '%</b><br><span style="color:#646970;font-size:11px">CTR</span></td>';
	echo '<td><b>' . round( (float) $a->poz, 1 ) . '</b><br><span style="color:#646970;font-size:11px">ort. pozisyon</span></td>';
	echo '<td><b>' . number_format_i18n( (int) $a->otu ) . '</b><br><span style="color:#646970;font-size:11px">oturum</span></td>';
	echo '<td><b>' . $sure . ' sn</b><br><span style="color:#646970;font-size:11px">ort. süre</span></td>';
	echo '</tr></table>';

	$sq = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, SUM(tik) tik, SUM(gosterim) gos,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$tq} WHERE yol=%s AND tarih BETWEEN %s AND %s
		 GROUP BY sorgu ORDER BY gos DESC LIMIT 30",
		$yol, $b1, $s1
	) );

	if ( $sq ) {
		echo '<h4>Bu sayfaya gelen aramalar</h4><table><tr><th>Sorgu</th><th>Tık</th><th>Gösterim</th><th>CTR</th><th>Poz</th><th>Not</th></tr>';
		foreach ( $sq as $r ) {
			$c   = $r->gos ? $r->tik / $r->gos * 100 : 0;
			$p   = (float) $r->poz;
			$not = '';
			if ( $p >= 11 && $p <= 20 ) {
				$not = '<span class="rz" style="background:#fff3cd;color:#8a6d00">2. sayfada — küçük dokunuş yeter</span>';
			} elseif ( $p <= 10 && $c < 2 && $r->gos >= 200 ) {
				$not = '<span class="rz" style="background:#ffe0e0;color:#a4262c">sıra iyi, CTR düşük — başlık/meta</span>';
			}
			$kel = count( preg_split( '/\s+/', trim( (string) $r->sorgu ) ) );
			$lt  = ( $kel >= 4 ) ? ' <span class="rz" style="background:#eef6ff;color:#1a5fb4">long tail</span>' : '';
			$pr  = ( $p <= 3 ) ? '#1a7f37' : ( ( $p <= 10 ) ? '#1d2327' : ( ( $p <= 20 ) ? '#8a6d00' : '#a4262c' ) );
			echo '<tr><td>' . esc_html( $r->sorgu ) . $lt . '</td><td>' . (int) $r->tik . '</td><td>' . (int) $r->gos . '</td><td>' . round( $c, 1 ) . '%</td><td style="color:' . $pr . ';font-weight:600">' . round( $p, 1 ) . '</td><td>' . $not . '</td></tr>';
		}
		echo '</table>';
	}

	$kan = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, yol, SUM(gosterim) gos, SUM(tik) tik,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$tq}
		 WHERE tarih BETWEEN %s AND %s
		 AND sorgu IN (
			SELECT sorgu FROM (
				SELECT sorgu FROM {$tq} WHERE yol=%s AND tarih BETWEEN %s AND %s
				GROUP BY sorgu HAVING SUM(gosterim) >= 30
			) x
		 )
		 GROUP BY sorgu, yol HAVING gos >= 10
		 ORDER BY sorgu, gos DESC",
		$b1, $s1, $yol, $b1, $s1
	) );

	$grup = array();
	foreach ( (array) $kan as $r ) { $grup[ $r->sorgu ][] = $r; }
	$catisan = array();
	foreach ( $grup as $s => $liste ) { if ( count( $liste ) > 1 ) { $catisan[ $s ] = $liste; } }

	echo '<h4>Kanibalizasyon</h4>';
	if ( ! $catisan ) {
		echo '<p style="padding:9px 12px;background:#edfaef;border-radius:6px;color:#1a7f37;margin:0">Temiz — bu sayfanın aldığı aramalarda başka sayfan yarışmıyor.</p>';
	} else {
		echo '<table><tr><th>Sorgu</th><th>Yarışan sayfalar</th></tr>';
		foreach ( array_slice( $catisan, 0, 8, true ) as $s => $liste ) {
			$sat = array();
			foreach ( $liste as $r ) {
				$bu = ( $r->yol === $yol );
				$sat[] = '<div style="' . ( $bu ? 'font-weight:600' : 'color:#646970' ) . '">' . esc_html( $r->yol ) . ' — poz ' . round( (float) $r->poz, 1 ) . ', ' . (int) $r->gos . ' gösterim' . ( $bu ? ' (bu sayfa)' : '' ) . '</div>';
			}
			echo '<tr><td style="vertical-align:top">' . esc_html( $s ) . '</td><td>' . implode( '', $sat ) . '</td></tr>';
		}
		echo '</table>';
		echo '<p style="color:#646970;font-size:12px;margin:8px 0 0">Aynı sorguda iki sayfan gösterim alıyorsa Google hangisini öne çıkaracağını bilemiyor. Zayıf olanı güçlüye yönlendir ya da içerikleri ayrıştır.</p>';
	}

	$yillar = gbc_km_eski_yil( $pid );
	if ( $yillar ) {
		$p = array();
		foreach ( $yillar as $y => $adet ) { $p[] = $y . ' (' . $adet . ' yerde)'; }
		echo '<h4>Yıl kontrolü</h4><p style="padding:9px 12px;background:#fff8e5;border-radius:6px;margin:0">Geçmiş yıl geçiyor: <b>' . esc_html( implode( ', ', $p ) ) . '</b>. Bu yıl ' . esc_html( gmdate( 'Y' ) ) . ' — güncellenmeli mi bak.</p>';
	}

	echo '</div>';
}

/* v1.47.5: düzenleme ekranındaki 'gbc_km_kart' kutusu kaldırıldı (Halil: artık kullanılmıyor). Tek adres Sayfa Denetimi; kısayol inc/kisayol.php. */

/* ================= ENVANTER =================
   Karar katmani 31304 nolu "GBC Envanter Defteri" sayfasinda duruyor
   (ad|slug|ulke|hedef|durum|hacim|ust|sezon). Halil elle duzeltebilir.
   Canli katman WordPress ve veri deposundan her seferinde yeniden
   hesaplanir, boylece envanter hic bayatlamaz. */

function gbc_km_defter() {
	static $satir = null;
	if ( $satir !== null ) { return $satir; }
	$satir = array();
	$p = get_post( 31304 );
	if ( ! $p ) { return $satir; }
	if ( ! preg_match( '#<pre[^>]*id="gbc-envanter"[^>]*>(.*?)</pre>#s', (string) $p->post_content, $m ) ) { return $satir; }
	foreach ( preg_split( '/\r\n|\r|\n/', html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ) as $s ) {
		$s = trim( $s );
		if ( $s === '' ) { continue; }
		$a = explode( '|', $s );
		if ( count( $a ) < 8 ) { continue; }
		$satir[] = array(
			'ad'    => trim( $a[0] ),
			'slug'  => trim( $a[1] ),
			'ulke'  => trim( $a[2] ),
			'hedef' => trim( $a[3] ),
			'durum' => trim( $a[4] ),
			'hacim' => (int) $a[5],
			'ust'   => trim( $a[6] ),
			'sezon' => trim( $a[7] ),
		);
	}
	return $satir;
}

function gbc_km_aylar( $sezon ) {
	$ad = array( 'Oca'=>1,'Şub'=>2,'Mar'=>3,'Nis'=>4,'May'=>5,'Haz'=>6,'Tem'=>7,'Ağu'=>8,'Eyl'=>9,'Eki'=>10,'Kas'=>11,'Ara'=>12 );
	$sezon = trim( (string) $sezon );
	if ( $sezon === '' ) { return array(); }
	$bul = array();
	foreach ( $ad as $k => $v ) { if ( mb_strpos( $sezon, $k ) !== false ) { $bul[ $k ] = $v; } }
	if ( ! $bul ) { return array(); }
	$v = array_values( $bul );
	if ( count( $v ) === 1 ) { return $v; }
	$b = $v[0]; $s = $v[ count( $v ) - 1 ];
	$out = array(); $i = $b;
	for ( $k = 0; $k < 12; $k++ ) {
		$out[] = $i;
		if ( $i === $s ) { break; }
		$i = ( $i % 12 ) + 1;
	}
	return $out;
}

function gbc_km_rozet( $d ) {
	$r = array(
		'V' => array( 'var', '#edfaef', '#1a7f37' ),
		'D' => array( 'düzeltilecek', '#fff8e5', '#8a6d00' ),
		'B' => array( 'birleşecek', '#f3eefd', '#6b46c1' ),
		'Y' => array( 'yeni', '#eaf3ff', '#1a5fb4' ),
		'K' => array( 'karar', '#f6f7f7', '#646970' ),
	);
	if ( ! isset( $r[ $d ] ) ) { return ''; }
	return '<span style="display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;font-weight:600;background:' . $r[ $d ][1] . ';color:' . $r[ $d ][2] . '">' . $r[ $d ][0] . '</span>';
}

add_action( 'admin_menu', function () {
	/* v1.9.2: yerini SEO Toplama tablosu aldi.
	add_submenu_page( 'gbc', 'SEO · Envanter', '— Envanter', 'manage_options', 'gbc-komuta-envanter', 'gbc_km_ekran_envanter' ); */
}, 15 );

function gbc_km_ekran_envanter() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;

	$rows  = gbc_km_defter();
	$olcum = gbc_km_sezon_olc();
	echo '<style>@keyframes gbcyanip{0%,100%{opacity:1}50%{opacity:.25}}.gbc-yan{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;animation:gbcyanip 1.6s ease-in-out infinite;vertical-align:middle}</style>';
	echo '<div class="wrap"><h1>GBC SEO · Envanter</h1>';

	if ( ! $rows ) {
		echo '<p>Envanter defteri (sayfa 31304) okunamadı.</p></div>';
		return;
	}

	/* Canli katman: hangi slug sitede var, hangi sablonda */
	$sluglar = array();
	foreach ( $rows as $r ) { if ( $r['slug'] !== '' ) { $sluglar[] = $r['slug']; } }
	$canli = array();
	if ( $sluglar ) {
		$ph  = implode( ',', array_fill( 0, count( $sluglar ), '%s' ) );
		$sql = "SELECT ID, post_name, post_content, post_status FROM {$wpdb->posts}
				WHERE post_name IN ($ph) AND post_status IN ('publish','draft','private')";
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $sluglar ) ) as $p ) {
			$canli[ $p->post_name ] = $p;
		}
	}

	/* Performans: son 28 gun, yol bazinda */
	$ts  = $wpdb->prefix . 'gbc_km_sayfa';
	$b1  = gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS );
	$s1  = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$perf = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT yol, SUM(tik) tik, SUM(gosterim) gos,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$ts} WHERE tarih BETWEEN %s AND %s GROUP BY yol", $b1, $s1
	) ) as $p ) {
		$perf[ trim( $p->yol, '/' ) ] = $p;
	}

	/* Ozet */
	$say = array( 'V'=>0,'D'=>0,'B'=>0,'Y'=>0,'K'=>0 );
	$sitede = 0;
	foreach ( $rows as $r ) {
		if ( isset( $say[ $r['durum'] ] ) ) { $say[ $r['durum'] ]++; }
		if ( $r['slug'] !== '' && isset( $canli[ $r['slug'] ] ) ) { $sitede++; }
	}
	$toplam = count( $rows );
	$kapsama = $toplam ? round( $say['V'] / $toplam * 100 ) : 0;

	echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">';
	$kart = function ( $ust, $alt, $renk ) {
		echo '<div style="flex:1 1 130px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff">'
			. '<div style="font-size:22px;font-weight:600;color:' . $renk . '">' . $ust . '</div>'
			. '<div style="font-size:12px;color:#646970">' . $alt . '</div></div>';
	};
	$kart( $toplam, 'toplam satır', '#1d2327' );
	$kart( '%' . $kapsama, 'kapsama (var olan)', '#1a7f37' );
	$kart( $say['Y'], 'açılacak sayfa', '#1a5fb4' );
	$kart( $say['D'], 'düzeltilecek', '#8a6d00' );
	$kart( $say['B'], 'birleşecek', '#6b46c1' );
	$kart( $sitede, 'sitede bulundu', '#1d2327' );
	echo '</div>';

	/* Simdi sirada: sezonu yaklasan, henuz hazir olmayanlar */
	$ay    = (int) gmdate( 'n' );
	$son   = ( $ay % 12 ) + 1;
	$sira  = array();
	foreach ( $rows as $r ) {
		if ( $r['durum'] === 'V' ) { continue; }
		$o = isset( $olcum[ $r['slug'] ] ) ? $olcum[ $r['slug'] ] : null;
		$a = ( $o && $o['zirve'] ) ? $o['zirve'] : gbc_km_aylar( $r['sezon'] );
		if ( ! $a ) { continue; }
		if ( in_array( $ay, $a, true ) || in_array( $son, $a, true ) ) { $sira[] = $r; }
	}
	usort( $sira, function ( $x, $y ) { return $y['hacim'] - $x['hacim']; } );

	echo '<h2 style="margin-top:26px">Şimdi sırada — sezonu gelen ama hazır olmayanlar</h2>';
	if ( ! $sira ) {
		echo '<p style="color:#646970">Bu ay ve gelecek ay için sezonu gelen eksik sayfa yok.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Sayfa</th><th>Ülke</th><th>Sezon</th><th>Hacim</th><th>Durum</th><th>Hedef</th></tr></thead><tbody>';
		foreach ( array_slice( $sira, 0, 25 ) as $r ) {
			$o   = isset( $olcum[ $r['slug'] ] ) ? $olcum[ $r['slug'] ] : null;
			$zir = ( $o && $o['zirve'] ) ? $o['zirve'] : gbc_km_aylar( $r['sezon'] );
			$simdiMi = in_array( $ay, (array) $zir, true );
			$nokta = '<span class="gbc-yan" style="background:' . ( $simdiMi ? '#1a7f37' : '#f0a202' ) . '" title="' . ( $simdiMi ? 'sezonu şu an' : 'sezonu geliyor' ) . '"></span>';
			$sez = ( $o && $o['zirve'] )
				? '<strong>' . esc_html( gbc_km_ay_adi( $o['zirve'] ) ) . '</strong> <span style="color:#646970;font-size:11px">ölçüldü</span>'
				: esc_html( $r['sezon'] ) . ' <span style="color:#646970;font-size:11px">tahmin</span>';
			echo '<tr><td>' . $nokta . '<strong>' . esc_html( $r['ad'] ) . '</strong></td><td>' . esc_html( $r['ulke'] ) . '</td><td>' . $sez . '</td><td>' . ( $r['hacim'] ? number_format_i18n( $r['hacim'] ) : '–' ) . '</td><td>' . gbc_km_rozet( $r['durum'] ) . '</td><td>' . esc_html( $r['hedef'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* Ulke ulke agac */
	$ulkeler = array();
	foreach ( $rows as $r ) { $ulkeler[ $r['ulke'] ][] = $r; }
	uasort( $ulkeler, function ( $a, $b ) { return count( $b ) - count( $a ); } );

	echo '<h2 style="margin-top:30px">Site ağacı</h2>';
	foreach ( $ulkeler as $ulke => $liste ) {
		$v = 0;
		foreach ( $liste as $r ) { if ( $r['durum'] === 'V' ) { $v++; } }
		$yuzde = count( $liste ) ? round( $v / count( $liste ) * 100 ) : 0;

		echo '<details style="margin:8px 0;border:1px solid #dcdcde;border-radius:8px;background:#fff">';
		echo '<summary style="padding:10px 14px;cursor:pointer;font-weight:600">' . esc_html( $ulke )
			. ' <span style="font-weight:400;color:#646970">· ' . count( $liste ) . ' satır · %' . $yuzde . ' hazır</span></summary>';
		echo '<table class="widefat striped" style="border:0"><thead><tr><th>Sayfa</th><th>Üst</th><th>Hedef</th><th>Şimdi</th><th>Hacim</th><th>Tık</th><th>Gösterim</th><th>Poz</th><th>Sezon</th></tr></thead><tbody>';

		foreach ( $liste as $r ) {
			$p     = ( $r['slug'] !== '' && isset( $canli[ $r['slug'] ] ) ) ? $canli[ $r['slug'] ] : null;
			$simdi = '<span style="color:#a4262c">sitede yok</span>';
			if ( $p ) {
				$simdi = esc_html( gbc_km_sablon( (int) $p->ID ) );
				if ( $r['hedef'] !== '–' && $r['hedef'] !== '' && $simdi !== $r['hedef'] && $simdi !== 'Bilinmiyor' ) {
					$simdi = '<span style="color:#8a6d00">' . $simdi . ' ≠</span>';
				}
			}
			$d = isset( $perf[ $r['slug'] ] ) ? $perf[ $r['slug'] ] : null;
			$ad = $p ? '<a href="' . esc_url( get_edit_post_link( (int) $p->ID ) ) . '">' . esc_html( $r['ad'] ) . '</a>' : esc_html( $r['ad'] );

			$o   = isset( $olcum[ $r['slug'] ] ) ? $olcum[ $r['slug'] ] : null;
			$zir = ( $o && $o['zirve'] ) ? $o['zirve'] : gbc_km_aylar( $r['sezon'] );
			$acil = in_array( (int) gmdate( 'n' ), (array) $zir, true ) && $r['durum'] !== 'V';
			$nokta = $acil ? '<span class="gbc-yan" style="background:#1a7f37" title="sezonu şu an, hazır değil"></span>' : '';
			echo '<tr><td>' . $nokta . $ad . ' ' . gbc_km_rozet( $r['durum'] ) . '</td>';
			echo '<td style="color:#646970">' . esc_html( $r['ust'] ) . '</td>';
			echo '<td>' . esc_html( $r['hedef'] ) . '</td>';
			echo '<td>' . $simdi . '</td>';
			echo '<td>' . ( $r['hacim'] ? number_format_i18n( $r['hacim'] ) : '–' ) . '</td>';
			echo '<td>' . ( $d ? number_format_i18n( (int) $d->tik ) : '–' ) . '</td>';
			echo '<td>' . ( $d ? number_format_i18n( (int) $d->gos ) : '–' ) . '</td>';
			echo '<td>' . ( $d && $d->poz ? round( (float) $d->poz, 1 ) : '–' ) . '</td>';
			if ( $o && $o['zirve'] ) {
				echo '<td>' . gbc_km_kivilcim( $o['egri'] ) . '<br><strong style="font-size:11px">' . esc_html( gbc_km_ay_adi( $o['zirve'] ) ) . '</strong></td></tr>';
			} else {
				echo '<td style="color:#646970">' . esc_html( $r['sezon'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></details>';
	}

	echo '<p style="margin-top:18px;color:#646970;font-size:12px">Karar sütunları (hedef, durum, hacim, üst, sezon) <a href="' . esc_url( get_edit_post_link( 31304 ) ) . '">Envanter Defteri</a> sayfasından elle düzeltilir. Şimdi, tık, gösterim ve pozisyon her açılışta canlı hesaplanır.</p>';
	echo '</div>';
}

/* ================= OLCULEN SEZON =================
   Elle yazilan sezon tahmin; bu blok gercegi olcuyor. Son 12 ayin
   her biri icin GSC'den ay bazinda gosterim cekilir (ayda tek sorgu,
   toplam 12 istek). Bir sayfanin gosterimi kendi yillik ortalamasinin
   1.4 katini asan aylar "zirve" sayilir. Boylece "ne zaman araniyor"
   sorusu tahminle degil rakamla cevaplanir. */

function gbc_km_cek_ay( $kac = 12 ) {
	global $wpdb;
	$t    = $wpdb->prefix . 'gbc_km_ay';
	$site = gbc_km_ayar( 'gsc_site', '' );
	if ( $site === '' ) { return new WP_Error( 'yok', 'Search Console site adresi girilmemis.' ); }
	$url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';

	$rapor = array();
	$sinir = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );

	for ( $i = 1; $i <= $kac; $i++ ) {
		$ilk = gmdate( 'Y-m-01', strtotime( '-' . $i . ' month', time() ) );
		$son = gmdate( 'Y-m-t', strtotime( $ilk ) );
		if ( $son > $sinir ) { $son = $sinir; }
		if ( $son < $ilk ) { continue; }
		$anahtar = substr( $ilk, 0, 7 );

		$bas = 0; $n = 0;
		do {
			$g = gbc_km_api( $url, array(
				'startDate' => $ilk, 'endDate' => $son,
				'dimensions' => array( 'page' ), 'rowLimit' => 5000, 'startRow' => $bas,
			) );
			if ( is_wp_error( $g ) ) { $rapor[] = $anahtar . ' HATA: ' . $g->get_error_message(); return $rapor; }
			$r = ( isset( $g['rows'] ) && is_array( $g['rows'] ) ) ? $g['rows'] : array();
			foreach ( $r as $x ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$t} (ay,yol,tik,gosterim,pozisyon) VALUES (%s,%s,%d,%d,%f)
					 ON DUPLICATE KEY UPDATE tik=VALUES(tik),gosterim=VALUES(gosterim),pozisyon=VALUES(pozisyon)",
					$anahtar, gbc_km_yol( $x['keys'][0] ), (int) $x['clicks'], (int) $x['impressions'], (float) $x['position']
				) );
				$n++;
			}
			$bas += count( $r );
		} while ( count( $r ) === 5000 && $bas < 25000 );
		$rapor[] = $anahtar . ': ' . $n . ' sayfa';
	}
	return $rapor;
}

/* yol => array( zirve => [ay no], egri => [12 deger], toplam => n ) */
function gbc_km_sezon_olc() {
	static $harita = null;
	if ( $harita !== null ) { return $harita; }
	global $wpdb;
	$t = $wpdb->prefix . 'gbc_km_ay';
	$harita = array();

	$satir = $wpdb->get_results( "SELECT yol, ay, gosterim FROM {$t}" );
	if ( ! $satir ) { return $harita; }

	$ham = array();
	foreach ( $satir as $s ) {
		$ay = (int) substr( $s->ay, 5, 2 );
		if ( ! isset( $ham[ $s->yol ] ) ) { $ham[ $s->yol ] = array_fill( 1, 12, 0 ); }
		$ham[ $s->yol ][ $ay ] += (int) $s->gosterim;
	}

	foreach ( $ham as $yol => $egri ) {
		$toplam = array_sum( $egri );
		if ( $toplam < 100 ) { continue; }
		$ort   = $toplam / 12;
		$zirve = array();
		foreach ( $egri as $ay => $v ) {
			if ( $ort > 0 && $v >= $ort * 1.4 ) { $zirve[] = $ay; }
		}
		if ( count( $zirve ) >= 10 ) { $zirve = array(); }
		$harita[ trim( $yol, '/' ) ] = array( 'zirve' => $zirve, 'egri' => $egri, 'toplam' => $toplam );
	}
	return $harita;
}

function gbc_km_ay_adi( $liste ) {
	$ad = array( 1=>'Oca',2=>'Şub',3=>'Mar',4=>'Nis',5=>'May',6=>'Haz',7=>'Tem',8=>'Ağu',9=>'Eyl',10=>'Eki',11=>'Kas',12=>'Ara' );
	$out = array();
	foreach ( (array) $liste as $a ) { if ( isset( $ad[ $a ] ) ) { $out[] = $ad[ $a ]; } }
	return implode( ' ', $out );
}

/* Kucuk sutun grafigi — 12 ayin egrisi tek bakista gorunsun */
function gbc_km_kivilcim( $egri ) {
	$mak = max( $egri );
	if ( $mak <= 0 ) { return ''; }
	$bu  = (int) gmdate( 'n' );
	$out = '<span style="display:inline-flex;align-items:flex-end;gap:1px;height:18px">';
	foreach ( $egri as $ay => $v ) {
		$h = max( 2, round( $v / $mak * 18 ) );
		$r = ( $ay === $bu ) ? '#1a5fb4' : '#c3c4c7';
		$out .= '<i style="display:block;width:4px;height:' . $h . 'px;background:' . $r . ';border-radius:1px"></i>';
	}
	return $out . '</span>';
}

/* ================= SAYFA DENETIMI =================
   Sayfanin CANLI HTML'ini cekip (1 saat onbellek) su sorulara bakar:
   hedef kelime gercekte siralanan kelime mi, hangi sorgular sayfada
   hic gecmiyor, basliklar sorgulari karsiliyor mu, sema ne uretmis,
   basligin ve aciklamanin uzunlugu ve icerigi dogru mu.
   Butonla calisir, kendiliginden istek atmaz. */

function gbc_km_sade( $s ) {
	$s = mb_strtolower( (string) $s, 'UTF-8' );
	$s = strtr( $s, array( 'ı'=>'i','İ'=>'i','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c','â'=>'a','î'=>'i','û'=>'u' ) );
	/* PHP'de mb_strtolower('İ') "i" + U+0307 birlesik nokta uretir.
	   Asagidaki temizlik o noktayi bosluga cevirip kelimeyi ikiye bolerdi:
	   "İtalya" -> "i talya", "İstanbul" -> "i stanbul". Once birlesik
	   isaretleri atiyoruz, sonra temizlige giriyoruz. */
	$s = preg_replace( '/[\x{0300}-\x{036F}]/u', '', $s );
	$s = preg_replace( '/[^a-z0-9 ]+/u', ' ', $s );
	return trim( preg_replace( '/\s+/', ' ', $s ) );
}

function gbc_km_html( $pid ) {
	$a = 'gbc_km_html_' . $pid;
	$h = get_transient( $a );
	if ( is_string( $h ) && $h !== '' ) { return $h; }
	$c = wp_remote_get( get_permalink( $pid ), array( 'timeout' => 25, 'sslverify' => false ) );
	if ( is_wp_error( $c ) ) { return $c; }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( $kod !== 200 ) { return new WP_Error( 'http', 'Sayfa HTTP ' . $kod . ' dondu.' ); }
	$h = wp_remote_retrieve_body( $c );
	set_transient( $a, $h, HOUR_IN_SECONDS );
	return $h;
}

function gbc_km_etiket( $html, $etiket ) {
	$out = array();
	if ( preg_match_all( '#<' . $etiket . '[^>]*>(.*?)</' . $etiket . '>#is', $html, $m ) ) {
		foreach ( $m[1] as $x ) {
			$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $x ) ) );
			if ( $t !== '' ) { $out[] = $t; }
		}
	}
	return $out;
}

function gbc_km_jsonld( $html ) {
	$tip = array();
	if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
		foreach ( $m[1] as $blok ) {
			$j = json_decode( trim( $blok ), true );
			if ( ! is_array( $j ) ) { $tip[] = 'BOZUK JSON'; continue; }
			$dugum = ( isset( $j['@graph'] ) && is_array( $j['@graph'] ) ) ? $j['@graph'] : array( $j );
			foreach ( $dugum as $d ) {
				if ( isset( $d['@type'] ) ) {
					$t = is_array( $d['@type'] ) ? implode( '/', $d['@type'] ) : $d['@type'];
					$tip[] = (string) $t;
				}
			}
		}
	}
	return $tip;
}

/* v1.47.5: düzenleme ekranındaki 'gbc_km_denetim' kutusu kaldırıldı (Halil: artık kullanılmıyor). Tek adres Sayfa Denetimi; kısayol inc/kisayol.php. */

function gbc_km_denetim_ciz( $post ) {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$pid = (int) $post->ID;
	$yol = gbc_km_kart_yol( $pid );

	$url = wp_nonce_url( admin_url( 'post.php?post=' . $pid . '&action=edit&gbcden=1' ), 'gbc_km_den' );
	echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">Bu sayfayı denetle</a> <span style="color:#646970;font-size:12px">Canlı sayfayı indirip başlıkları, şemayı ve arama kapsamasını çıkarır.</span></p>';

	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! isset( $_GET['gbcden'] ) || ! wp_verify_nonce( $nonce, 'gbc_km_den' ) ) { return; }

	$html = gbc_km_html( $pid );
	if ( is_wp_error( $html ) ) {
		echo '<p style="color:#d1242f">Sayfa indirilemedi: ' . esc_html( $html->get_error_message() ) . '</p>';
		return;
	}

	$h1 = gbc_km_etiket( $html, 'h1' );
	$h2 = gbc_km_etiket( $html, 'h2' );
	$h3 = gbc_km_etiket( $html, 'h3' );
	$basliklar = gbc_km_sade( implode( ' ', array_merge( $h1, $h2, $h3 ) ) );
	$govde     = gbc_km_sade( wp_strip_all_tags( $html ) );

	$tq = $wpdb->prefix . 'gbc_km_sorgu';
	$b1 = gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS );
	$s1 = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$sorgular = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, SUM(tik) tik, SUM(gosterim) gos,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$tq} WHERE yol=%s AND tarih BETWEEN %s AND %s
		 GROUP BY sorgu ORDER BY gos DESC", $yol, $b1, $s1
	) );

	$satirCiz = function ( $ad, $durum, $metin ) {
		$r = array( 'ok' => array( '#1a7f37', 'TAMAM' ), 'uyari' => array( '#8a6d00', 'BAKILACAK' ), 'kotu' => array( '#d1242f', 'SORUN' ) );
		$k = isset( $r[ $durum ] ) ? $r[ $durum ] : $r['uyari'];
		echo '<tr><td style="padding:7px 10px 7px 0;white-space:nowrap;vertical-align:top"><strong style="color:' . $k[0] . ';font-size:11px">' . $k[1] . '</strong></td>'
			. '<td style="padding:7px 12px 7px 0;white-space:nowrap;vertical-align:top;font-weight:600">' . esc_html( $ad ) . '</td>'
			. '<td style="padding:7px 0;vertical-align:top">' . $metin . '</td></tr>';
	};

	echo '<table style="width:100%;border-collapse:collapse;font-size:13px"><tbody>';

	/* 1 — Hedef kelime */
	$hedef = (string) get_post_meta( $pid, 'rank_math_focus_keyword', true );
	$hedef = trim( explode( ',', $hedef )[0] );
	$engos = $sorgular ? $sorgular[0]->sorgu : '';
	if ( $hedef === '' ) {
		$satirCiz( 'Hedef kelime', 'kotu', 'Rank Math hedef kelimesi boş.' . ( $engos ? ' En çok gösterim alan sorgu: <strong>' . esc_html( $engos ) . '</strong> — bunu hedef yap.' : '' ) );
	} elseif ( $engos && gbc_km_sade( $hedef ) !== gbc_km_sade( $engos ) ) {
		$satirCiz( 'Hedef kelime', 'uyari', 'Hedef: <strong>' . esc_html( $hedef ) . '</strong><br>Gerçekte en çok gösterim: <strong>' . esc_html( $engos ) . '</strong> — sayfa başka kelimede yarışıyor.' );
	} else {
		$satirCiz( 'Hedef kelime', 'ok', esc_html( $hedef ) . ' — gerçekte sıralanan kelimeyle aynı.' );
	}

	/* 2 — Kapsanan hacim */
	$tg = 0; $tt = 0; $s1s = 0; $s2s = 0; $uzak = 0;
	foreach ( $sorgular as $q ) {
		$tg += (int) $q->gos; $tt += (int) $q->tik;
		$p = (float) $q->poz;
		if ( $p <= 10 ) { $s1s++; } elseif ( $p <= 20 ) { $s2s++; } else { $uzak++; }
	}
	$satirCiz( 'Kapsanan hacim', $tg ? 'ok' : 'kotu',
		'<strong>' . number_format_i18n( count( $sorgular ) ) . '</strong> farklı sorguda görünüyor · '
		. '<strong>' . number_format_i18n( $tg ) . '</strong> gösterim · <strong>' . number_format_i18n( $tt ) . '</strong> tık<br>'
		. '1. sayfa: ' . $s1s . ' sorgu · 2. sayfa: ' . $s2s . ' · daha geride: ' . $uzak );

	/* 3 — Long tail: sayfada hic gecmeyen sorgular */
	$yok = array();
	foreach ( $sorgular as $q ) {
		if ( (int) $q->gos < 20 ) { continue; }
		if ( mb_strpos( $govde, gbc_km_sade( $q->sorgu ) ) === false ) { $yok[] = $q; }
		if ( count( $yok ) >= 12 ) { break; }
	}
	if ( $yok ) {
		$l = array();
		foreach ( $yok as $q ) { $l[] = '<li>' . esc_html( $q->sorgu ) . ' — ' . (int) $q->gos . ' gösterim, poz ' . round( (float) $q->poz, 1 ) . '</li>'; }
		$satirCiz( 'Karşılanmayan aramalar', 'uyari', 'Bu sorgular seni buluyor ama <strong>sayfada bu kelimeler hiç geçmiyor</strong>. Her biri bir başlık ya da SSS maddesi adayı:<ul style="margin:6px 0 0 18px">' . implode( '', $l ) . '</ul>' );
	} else {
		$satirCiz( 'Karşılanmayan aramalar', 'ok', 'Gösterim alan her sorgu sayfada geçiyor.' );
	}

	/* 4 — Basliklarda kapsama */
	$say = 0; $tut = 0;
	foreach ( $sorgular as $q ) {
		if ( (int) $q->gos < 20 ) { continue; }
		$say++;
		if ( mb_strpos( $basliklar, gbc_km_sade( $q->sorgu ) ) !== false ) { $tut++; }
	}
	$oran = $say ? round( $tut / $say * 100 ) : 0;
	$satirCiz( 'Başlıklarda kapsama', $oran >= 40 ? 'ok' : 'uyari',
		'H1 ' . count( $h1 ) . ' · H2 ' . count( $h2 ) . ' · H3 ' . count( $h3 ) . '<br>'
		. 'Gösterim alan ' . $say . ' sorgudan <strong>' . $tut . '</strong> tanesi bir başlıkta geçiyor (<strong>%' . $oran . '</strong>).' );

	/* 5 — H1 */
	if ( count( $h1 ) !== 1 ) {
		$satirCiz( 'H1', 'kotu', count( $h1 ) . ' adet H1 var. Tek olmalı.' );
	} else {
		$uyum = ( $hedef !== '' && mb_strpos( gbc_km_sade( $h1[0] ), gbc_km_sade( $hedef ) ) !== false );
		$satirCiz( 'H1', $uyum ? 'ok' : 'uyari', esc_html( $h1[0] ) . ( $uyum ? '' : '<br><span style="color:#8a6d00">Hedef kelimeyi taşımıyor.</span>' ) );
	}

	/* 6 — Baslik ve aciklama */
	if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
		$t = trim( wp_strip_all_tags( $m[1] ) );
		$u = mb_strlen( $t );
		$satirCiz( 'SEO başlığı', ( $u >= 30 && $u <= 62 ) ? 'ok' : 'uyari', esc_html( $t ) . '<br><span style="color:#646970">' . $u . ' karakter' . ( $u > 62 ? ' — Google keser' : ( $u < 30 ? ' — kısa' : '' ) ) . '</span>' );
	}
	if ( preg_match( '#<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']#is', $html, $m ) ) {
		$d = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		$u = mb_strlen( $d );
		$satirCiz( 'Meta açıklama', ( $u >= 110 && $u <= 165 ) ? 'ok' : 'uyari', esc_html( $d ) . '<br><span style="color:#646970">' . $u . ' karakter</span>' );
	} else {
		$satirCiz( 'Meta açıklama', 'kotu', 'Sayfada meta açıklama yok.' );
	}

	/* 7 — Sema */
	$tipler = gbc_km_jsonld( $html );
	if ( ! $tipler ) {
		$satirCiz( 'Şema (JSON-LD)', 'kotu', 'Sayfada hiç JSON-LD yok.' );
	} elseif ( in_array( 'BOZUK JSON', $tipler, true ) ) {
		$satirCiz( 'Şema (JSON-LD)', 'kotu', 'Bir JSON-LD bloğu geçersiz, Google okuyamaz.' );
	} else {
		$bs   = array_unique( $tipler );
		$fapq = in_array( 'FAQPage', $bs, true );
		$brd  = in_array( 'BreadcrumbList', $bs, true );
		$eks  = array();
		if ( ! $brd ) { $eks[] = 'BreadcrumbList'; }
		if ( ! $fapq && $h2 ) { $eks[] = 'FAQPage (SSS varsa)'; }
		$satirCiz( 'Şema (JSON-LD)', $eks ? 'uyari' : 'ok',
			'Üretilen tipler: <strong>' . esc_html( implode( ', ', $bs ) ) . '</strong>'
			. ( $eks ? '<br><span style="color:#8a6d00">Eksik olabilir: ' . esc_html( implode( ', ', $eks ) ) . '</span>' : '' ) );
	}

	/* 8 — Ortaklik */
	/* Video — sayfada YouTube gomulu mu, olcum acik mi */
	$vid = preg_match_all( '#youtube\.com/embed|youtube-nocookie\.com/embed#', $html );
	if ( ! $vid ) {
		$satirCiz( 'Video', 'uyari', 'Sayfada gömülü YouTube videosu yok. Videon varsa ekle — hem sayfada kalma süresini hem VideoObject şemasını açar.' );
	} else {
		$jsapi = ( preg_match( '#enablejsapi=1#', $html ) ? 'ölçüm açık' : 'ölçüm kapalı — izlenme verisi gelmiyor' );
		$vsema = in_array( 'VideoObject', gbc_km_jsonld( $html ), true ) ? 'VideoObject şeması var' : 'VideoObject şeması yok';
		$satirCiz( 'Video', ( $vsema === 'VideoObject şeması var' ) ? 'ok' : 'uyari', $vid . ' video · ' . esc_html( $jsapi ) . ' · ' . esc_html( $vsema ) );
	}

	$aff = preg_match_all( '#data-aff=#', $html );
	$satirCiz( 'Ortaklık bağlantısı', $aff ? 'ok' : 'uyari', $aff ? $aff . ' adet izlenen bağlantı.' : 'Sayfada izlenen ortaklık bağlantısı yok.' );

	/* 9 — Index durumu (URL Inspection API) */
	$ix = gbc_km_index( get_permalink( $pid ) );
	if ( is_wp_error( $ix ) ) {
		$satirCiz( 'Index durumu', 'uyari', 'Sorgulanamadı: ' . esc_html( $ix->get_error_message() ) );
	} else {
		$kapsam = isset( $ix['coverageState'] ) ? $ix['coverageState'] : '?';
		$karar  = isset( $ix['verdict'] ) ? $ix['verdict'] : '?';
		$tarama = isset( $ix['lastCrawlTime'] ) ? gmdate( 'j M Y', strtotime( $ix['lastCrawlTime'] ) ) : 'hiç';
		$robots = isset( $ix['robotsTxtState'] ) ? $ix['robotsTxtState'] : '';
		$indexli = ( $karar === 'PASS' );
		$satirCiz( 'Index durumu', $indexli ? 'ok' : 'kotu',
			'<strong>' . esc_html( $kapsam ) . '</strong>'
			. '<br>Son tarama: ' . esc_html( $tarama )
			. ( $robots ? ' · robots: ' . esc_html( $robots ) : '' )
			. ( $indexli ? '' : '<br><span style="color:#d1242f">Bu sayfa Google\'da yok. Search Console\'dan indexleme iste.</span>' ) );
	}

	echo '</tbody></table>';
	echo '<p style="color:#646970;font-size:12px;margin-top:10px">Canlı HTML bir saat önbellekte tutulur. Sayfayı düzeltip yeniden denetlemek istersen bir saat bekle ya da LiteSpeed önbelleğini temizle.</p>';
}

/* ================= OTOMATIK CEKIM =================
   Artik veriyi Google'dan biz cekiyoruz. Gunluk cekim her gece,
   sezon tazelemesi haftada bir calisir. Sunucu cronuna bagli
   (DISABLE_WP_CRON acik, Hostinger 5 dakikada bir tetikliyor),
   yani ziyaretci beklemiyor. */

function gbc_km_cron_gunluk() {
	$t = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$r = gbc_km_cek( $t );
	update_option( 'gbc_km_son_cekim', array(
		'zaman' => time(),
		'tarih' => $t,
		'sonuc' => is_wp_error( $r ) ? 'HATA: ' . $r->get_error_message() : implode( ' · ', (array) $r ),
	), false );
}
add_action( 'gbc_km_gunluk_cek', 'gbc_km_cron_gunluk' );

function gbc_km_cron_sezon() {
	$r = gbc_km_cek_ay( 12 );
	update_option( 'gbc_km_son_sezon', array(
		'zaman' => time(),
		'sonuc' => is_wp_error( $r ) ? 'HATA: ' . $r->get_error_message() : count( (array) $r ) . ' ay tazelendi',
	), false );
}
add_action( 'gbc_km_sezon_cek', 'gbc_km_cron_sezon' );

/* KAPATILDI — 28 Eylül 2026.
   Bu çekim Search Console'dan günlük veri indirip wp2b_gbc_km_sayfa ve
   wp2b_gbc_km_sorgu tablolarına yazıyordu. Ölçüldü: o tabloları yalnız bu
   dosyanın kendi ekranları okuyor; yeni SEO ekranları Rank Math'in kendi
   Search Console tablosundan (34 MB) besleniyor. Yani aynı veri ikinci kez
   indiriliyor, Google kotası boşa harcanıyor ve kimse kullanmıyor.
   Tablolar SİLİNMEDİ, olduğu yerde duruyor.
   Geri açmak için: gbc_km_cekim_kapali seçeneğini sil. */
add_action( 'admin_init', function () {
	if ( get_option( 'gbc_km_cekim_kapali' ) ) { return; }
	if ( ! wp_next_scheduled( 'gbc_km_gunluk_cek' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 04:10' ), 'daily', 'gbc_km_gunluk_cek' );
	}
	if ( ! wp_next_scheduled( 'gbc_km_sezon_cek' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 04:40' ), 'weekly', 'gbc_km_sezon_cek' );
	}
}, 20 );

/* ================= FIRSATLAR =================
   Hepsi kendi sorgu tablomuzdan, ek API cagrisi yok.
   - Yeni cikan sorgular: son 7 gunde var, onceki 21 gunde yoktu
   - Yukselenler / dusenler: son 7 gun ile onceki 7 gun karsilastirmasi
   - Uzun kuyruk orani: gosterimlerin yuzde kaci 4+ kelimelik sorgulardan
   - Yakin firsat: 11-20. sirada duranlar
   NOT: bu ekranin anlamli olmasi icin depoda en az 28 gun veri olmali. */

add_action( 'admin_menu', function () {
	/* v1.9.2: yerini SEO Firsatlar aldi (Rank Math GSC tablosundan, 28 gun beklemeden).
	add_submenu_page( 'gbc', 'SEO · Fırsatlar', '— Fırsatlar', 'manage_options', 'gbc-komuta-firsat', 'gbc_km_ekran_firsat' ); */
}, 18 );

function gbc_km_ekran_firsat() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$t = $wpdb->prefix . 'gbc_km_sorgu';

	$y_son = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$y_bas = gmdate( 'Y-m-d', time() - 10 * DAY_IN_SECONDS );
	$e_son = gmdate( 'Y-m-d', time() - 11 * DAY_IN_SECONDS );
	$e_bas = gmdate( 'Y-m-d', time() - 18 * DAY_IN_SECONDS );
	$g_bas = gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS );

	echo '<div class="wrap"><h1>GBC SEO · Fırsatlar</h1>';

	$gun = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT tarih) FROM {$t}" );
	if ( $gun < 20 ) {
		echo '<div class="notice notice-warning"><p>Depoda sadece <strong>' . $gun . ' gün</strong> veri var. Bu ekranın doğru çalışması için en az 28 gün gerekiyor — <a href="' . esc_url( admin_url( 'admin.php?page=gbc-komuta-veri' ) ) . '">Veri Çek</a> ekranından "Son 30 günü çek" yap.</p></div>';
	}
	echo '<p style="color:#646970">Karşılaştırma: <strong>' . esc_html( $y_bas ) . ' — ' . esc_html( $y_son ) . '</strong> (son 7 gün) ile <strong>' . esc_html( $e_bas ) . ' — ' . esc_html( $e_son ) . '</strong> (önceki 7 gün).</p>';

	$tablo = function ( $satirlar, $sutunlar ) {
		if ( ! $satirlar ) { echo '<p style="color:#646970">Bu aralıkta kayıt yok.</p>'; return; }
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( $sutunlar as $s ) { echo '<th>' . esc_html( $s ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $satirlar as $h ) { echo '<tr>' . $h . '</tr>'; }
		echo '</tbody></table>';
	};

	/* 1 — Yeni cikan sorgular */
	$yeni = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, MIN(yol) yol, SUM(gosterim) gos, SUM(tik) tik,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$t}
		 WHERE tarih BETWEEN %s AND %s
		 AND sorgu NOT IN ( SELECT sorgu FROM ( SELECT DISTINCT sorgu FROM {$t} WHERE tarih BETWEEN %s AND %s ) x )
		 GROUP BY sorgu HAVING gos >= 10
		 ORDER BY gos DESC LIMIT 40",
		$y_bas, $y_son, $g_bas, $e_son
	) );
	echo '<h2 style="margin-top:26px">Yeni çıkan aramalar</h2>';
	echo '<p style="max-width:760px;color:#646970">Son 7 günde gösterim aldığın ama önceki 3 haftada hiç görünmeyen sorgular. Yeni bir talep doğuyor olabilir.</p>';
	$s = array();
	foreach ( (array) $yeni as $r ) {
		$s[] = '<td><strong>' . esc_html( $r->sorgu ) . '</strong></td><td>' . (int) $r->gos . '</td><td>' . (int) $r->tik . '</td><td>' . round( (float) $r->poz, 1 ) . '</td><td style="color:#646970">' . esc_html( $r->yol ) . '</td>';
	}
	$tablo( $s, array( 'Sorgu', 'Gösterim', 'Tık', 'Poz', 'Sayfa' ) );

	/* 2 — Yukselen ve dusen */
	$kiyas = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, MIN(yol) yol,
		 SUM(CASE WHEN tarih BETWEEN %s AND %s THEN gosterim ELSE 0 END) yeni,
		 SUM(CASE WHEN tarih BETWEEN %s AND %s THEN gosterim ELSE 0 END) eski
		 FROM {$t} WHERE tarih BETWEEN %s AND %s
		 GROUP BY sorgu HAVING (yeni + eski) >= 40",
		$y_bas, $y_son, $e_bas, $e_son, $e_bas, $y_son
	) );

	$yuk = array(); $dus = array();
	foreach ( (array) $kiyas as $r ) {
		$yn = (int) $r->yeni; $es = (int) $r->eski;
		if ( $es <= 0 ) { continue; }
		$d = ( $yn - $es ) / $es * 100;
		$sat = '<td><strong>' . esc_html( $r->sorgu ) . '</strong></td><td>' . $es . '</td><td>' . $yn . '</td><td style="font-weight:600;color:' . ( $d >= 0 ? '#1a7f37' : '#d1242f' ) . '">' . ( $d >= 0 ? '+' : '' ) . round( $d ) . '%</td><td style="color:#646970">' . esc_html( $r->yol ) . '</td>';
		if ( $d >= 40 ) { $yuk[ (int) ( $yn - $es ) ] = $sat; }
		if ( $d <= -40 ) { $dus[ (int) ( $es - $yn ) ] = $sat; }
	}
	krsort( $yuk ); krsort( $dus );

	echo '<h2 style="margin-top:30px">Yükselen aramalar</h2>';
	echo '<p style="max-width:760px;color:#646970">Gösterimi son haftada en az %40 artanlar. Sezonu açılıyor ya da ilgi büyüyor demektir.</p>';
	$tablo( array_slice( $yuk, 0, 25 ), array( 'Sorgu', 'Önceki hafta', 'Bu hafta', 'Değişim', 'Sayfa' ) );

	echo '<h2 style="margin-top:30px">Düşen aramalar</h2>';
	echo '<p style="max-width:760px;color:#646970">En az %40 gerileyenler. Sezon kapanıyor olabilir ya da sıra kaybettin — ikincisiyse bakmak lazım.</p>';
	$tablo( array_slice( $dus, 0, 25 ), array( 'Sorgu', 'Önceki hafta', 'Bu hafta', 'Değişim', 'Sayfa' ) );

	/* 3 — Uzun kuyruk orani */
	$hepsi = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, SUM(gosterim) gos, SUM(tik) tik FROM {$t}
		 WHERE tarih BETWEEN %s AND %s GROUP BY sorgu ORDER BY gos DESC LIMIT 3000",
		$g_bas, $y_son
	) );
	$kisa_g = 0; $uzun_g = 0; $kisa_t = 0; $uzun_t = 0; $uzun_n = 0;
	foreach ( (array) $hepsi as $r ) {
		$k = count( preg_split( '/\s+/', trim( (string) $r->sorgu ) ) );
		if ( $k >= 4 ) { $uzun_g += (int) $r->gos; $uzun_t += (int) $r->tik; $uzun_n++; }
		else { $kisa_g += (int) $r->gos; $kisa_t += (int) $r->tik; }
	}
	$top = $kisa_g + $uzun_g;
	$pay = $top ? round( $uzun_g / $top * 100 ) : 0;

	echo '<h2 style="margin-top:30px">Uzun kuyruk payı (son 28 gün)</h2>';
	echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0">';
	foreach ( array(
		array( count( (array) $hepsi ), 'farklı sorgu', '#1d2327' ),
		array( '%' . $pay, 'gösterim uzun kuyruktan', '#1a5fb4' ),
		array( number_format_i18n( $uzun_n ), '4+ kelimelik sorgu', '#6b46c1' ),
		array( $uzun_g ? round( $uzun_t / $uzun_g * 100, 2 ) . '%' : '–', 'uzun kuyruk CTR', '#1a7f37' ),
		array( $kisa_g ? round( $kisa_t / $kisa_g * 100, 2 ) . '%' : '–', 'kısa sorgu CTR', '#8a6d00' ),
	) as $k ) {
		echo '<div style="flex:1 1 150px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff">'
			. '<div style="font-size:22px;font-weight:600;color:' . $k[2] . '">' . esc_html( $k[0] ) . '</div>'
			. '<div style="font-size:12px;color:#646970">' . esc_html( $k[1] ) . '</div></div>';
	}
	echo '</div>';

	/* 4 — Yakin firsat: 11-20. sira */
	$yakin = $wpdb->get_results( $wpdb->prepare(
		"SELECT sorgu, MIN(yol) yol, SUM(gosterim) gos, SUM(tik) tik,
		 SUM(pozisyon*gosterim)/NULLIF(SUM(gosterim),0) poz
		 FROM {$t} WHERE tarih BETWEEN %s AND %s
		 GROUP BY sorgu HAVING gos >= 50 AND poz BETWEEN 11 AND 20
		 ORDER BY gos DESC LIMIT 40",
		$g_bas, $y_son
	) );
	echo '<h2 style="margin-top:30px">Yakın fırsat — 2. sayfadakiler</h2>';
	echo '<p style="max-width:760px;color:#646970">11–20. sırada duranlar. İlk sayfaya çıkmak için genelde küçük bir dokunuş yetiyor: başlığa kelimeyi koymak, bir H2 açmak, iç link vermek.</p>';
	$s = array();
	foreach ( (array) $yakin as $r ) {
		$s[] = '<td><strong>' . esc_html( $r->sorgu ) . '</strong></td><td>' . (int) $r->gos . '</td><td>' . (int) $r->tik . '</td><td style="color:#8a6d00;font-weight:600">' . round( (float) $r->poz, 1 ) . '</td><td style="color:#646970">' . esc_html( $r->yol ) . '</td>';
	}
	$tablo( $s, array( 'Sorgu', 'Gösterim', 'Tık', 'Poz', 'Sayfa' ) );

	echo '</div>';
}

/* ============================================================
   ESLESTIRME EKRANI
   Defter (31304) ile canli sitedeki yazilari karsilastirir.
   Hicbir API cagirisi yok; sadece kendi tablomuz + wp_posts.
   ============================================================ */

add_action( 'admin_menu', function () {
	/* v1.9.2: defter (31304) tabanli eski eslestirme — menuden kaldirildi.
	add_submenu_page( 'gbc', 'SEO · Eşleştirme', '— Eşleştirme', 'manage_options', 'gbc-komuta-eslesme', 'gbc_km_ekran_eslesme' ); */
}, 16 );

function gbc_km_canli_slug() {
	global $wpdb;
	$r = $wpdb->get_results(
		"SELECT ID, post_name, post_title, post_type
		 FROM {$wpdb->posts}
		 WHERE post_status='publish'
		   AND post_type IN ('post','page')
		   AND post_name <> ''"
	);
	$out = array();
	foreach ( $r as $x ) {
		$out[ $x->post_name ] = array( 'id' => (int) $x->ID, 'ad' => $x->post_title, 'tur' => $x->post_type );
	}
	return $out;
}

function gbc_km_slug_trafik( $sluglar ) {
	global $wpdb;
	if ( ! $sluglar ) { return array(); }
	$ts  = $wpdb->prefix . 'gbc_km_sayfa';
	$b   = gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS );
	$s   = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$yol = array();
	foreach ( $sluglar as $sl ) { $yol[] = '/' . $sl . '/'; }
	$yer = implode( ',', array_fill( 0, count( $yol ), '%s' ) );
	$par = array_merge( $yol, array( $b, $s ) );
	$r   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT yol, SUM(tik) tik, SUM(gosterim) gos
			 FROM {$ts} WHERE yol IN ({$yer}) AND tarih BETWEEN %s AND %s
			 GROUP BY yol",
			$par
		)
	);
	$out = array();
	foreach ( $r as $x ) { $out[ trim( $x->yol, '/' ) ] = array( 'tik' => (int) $x->tik, 'gos' => (int) $x->gos ); }
	return $out;
}

/* Gezi kategorilerindeki yazi ID'leri. Defterin kapsami bu kume;
   tarif ve mutfak sayfalari eslestirmede ayri gosterilir. */
function gbc_km_gezi_id() {
	global $wpdb;
	$kat = array( 922, 921, 936, 1212, 1211, 1213, 1175, 751 );
	$in  = implode( ',', array_map( 'intval', $kat ) );
	$r   = $wpdb->get_col(
		"SELECT DISTINCT tr.object_id
		 FROM {$wpdb->term_relationships} tr
		 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 WHERE tt.taxonomy = 'category' AND tt.term_id IN ({$in})"
	);
	$out = array();
	foreach ( $r as $x ) { $out[ (int) $x ] = true; }
	return $out;
}

function gbc_km_ekran_eslesme() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$defter = gbc_km_defter();
	$canli  = gbc_km_canli_slug();

	if ( ! $defter ) {
		echo '<div class="wrap"><h1>Eşleştirme</h1><div class="notice notice-error"><p>Defter (sayfa 31304) okunamadı.</p></div></div>';
		return;
	}

	$defter_slug = array();
	$kirik       = array();
	$sluksuz     = array();
	$acilacak    = array();
	$eslesen     = 0;

	foreach ( $defter as $d ) {
		$sl = trim( $d['slug'] );
		if ( $sl === '' ) {
			if ( $d['durum'] === 'Y' ) { $acilacak[] = $d; } else { $sluksuz[] = $d; }
			continue;
		}
		$defter_slug[ $sl ] = true;
		if ( isset( $canli[ $sl ] ) ) { $eslesen++; } else { $kirik[] = $d; }
	}

	$oksuz = array();
	foreach ( $canli as $sl => $c ) {
		if ( ! isset( $defter_slug[ $sl ] ) ) { $oksuz[ $sl ] = $c; }
	}

	$trafik = gbc_km_slug_trafik( array_keys( $oksuz ) );
	$sirali = array();
	foreach ( $oksuz as $sl => $c ) {
		$c['slug'] = $sl;
		$c['gos']  = isset( $trafik[ $sl ] ) ? $trafik[ $sl ]['gos'] : 0;
		$c['tik']  = isset( $trafik[ $sl ] ) ? $trafik[ $sl ]['tik'] : 0;
		$sirali[]  = $c;
	}
	usort( $sirali, function ( $a, $b ) { return $b['gos'] <=> $a['gos']; } );

	/* Gezi icerigini tarif yiginindan ayir: defterin kapsami gezi tarafi.
	   Tarif ve mutfak sayfalari ayri sayilir, listeyi bogmasin. */
	$gid   = gbc_km_gezi_id();
	$gezi  = array();
	$diger = array();
	foreach ( $sirali as $x ) {
		if ( isset( $gid[ $x['id'] ] ) ) { $gezi[] = $x; } else { $diger[] = $x; }
	}
	$sirali = $gezi;

	$kutu = function ( $sayi, $etiket, $renk ) {
		echo '<div style="flex:1;min-width:130px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px">'
			. '<div style="font-size:24px;font-weight:700;color:' . $renk . '">' . (int) $sayi . '</div>'
			. '<div style="font-size:12px;color:#646970;margin-top:2px">' . esc_html( $etiket ) . '</div></div>';
	};

	echo '<div class="wrap"><h1>Eşleştirme</h1>';
	echo '<p style="color:#646970;max-width:760px">Defterdeki ' . count( $defter ) . ' satır ile sitedeki ' . count( $canli ) . ' yayınlanmış yazı/sayfa karşılaştırıldı.</p>';

	echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">';
	$kutu( $eslesen, 'eşleşti', '#1a7f37' );
	$kutu( count( $sirali ), 'sitede var · defterde yok', '#b32d2e' );
	$kutu( count( $kirik ), 'defterde var · sitede yok', '#8a6d00' );
	$kutu( count( $acilacak ), 'açılacak (Y)', '#1a5fb4' );
	$kutu( count( $sluksuz ), 'slug girilmemiş', '#646970' );
	$kutu( count( $diger ), 'tarif/mutfak (defter dışı)', '#646970' );
	echo '</div>';

	echo '<h2 style="margin-top:28px">1 · Sitede var, defterde yok <span style="font-weight:400;color:#646970;font-size:13px">(' . count( $sirali ) . ' gezi sayfası — son 28 günün gösterimine göre sıralı; tarif ve mutfak sayfaları bu listenin dışında)</span></h2>';
	if ( ! $sirali ) {
		echo '<p style="color:#1a7f37">Boşluk yok.</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th>Başlık</th><th>Slug</th><th style="width:70px">Tür</th>'
			. '<th style="width:90px;text-align:right">Gösterim</th><th style="width:70px;text-align:right">Tık</th>'
			. '</tr></thead><tbody>';
		foreach ( array_slice( $sirali, 0, 200 ) as $x ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $x['id'] ) ) . '">' . esc_html( $x['ad'] ) . '</a></td>'
				. '<td><code style="font-size:11px">' . esc_html( $x['slug'] ) . '</code></td>'
				. '<td style="color:#646970">' . esc_html( $x['tur'] ) . '</td>'
				. '<td style="text-align:right' . ( $x['gos'] >= 100 ? ';font-weight:700' : '' ) . '">' . number_format_i18n( $x['gos'] ) . '</td>'
				. '<td style="text-align:right">' . number_format_i18n( $x['tik'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( count( $sirali ) > 200 ) { echo '<p style="color:#646970">İlk 200 gösteriliyor.</p>'; }
	}

	echo '<h2 style="margin-top:28px">2 · Defterde var, sitede yok <span style="font-weight:400;color:#646970;font-size:13px">(' . count( $kirik ) . ' satır — slug yanlış ya da sayfa taslakta)</span></h2>';
	if ( ! $kirik ) {
		echo '<p style="color:#1a7f37">Kırık satır yok.</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th>Ad</th><th>Slug</th><th style="width:110px">Ülke</th><th style="width:110px">Durum</th>'
			. '</tr></thead><tbody>';
		foreach ( $kirik as $d ) {
			echo '<tr><td>' . esc_html( $d['ad'] ) . '</td>'
				. '<td><code style="font-size:11px">' . esc_html( $d['slug'] ) . '</code></td>'
				. '<td>' . esc_html( $d['ulke'] ) . '</td>'
				. '<td>' . gbc_km_rozet( $d['durum'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2 style="margin-top:28px">3 · Açılacaklar <span style="font-weight:400;color:#646970;font-size:13px">(' . count( $acilacak ) . ' satır — ülkeye göre)</span></h2>';
	if ( ! $acilacak ) {
		echo '<p style="color:#646970">Yok.</p>';
	} else {
		$grup = array();
		foreach ( $acilacak as $d ) { $grup[ $d['ulke'] ][] = $d; }
		/* Ulkeyi toplam aylik arama hacmine gore sirala: en cok trafik
		   potansiyeli olan ulke en ustte. Satir icinde de hacim sirali. */
		$toplam = array();
		foreach ( $grup as $ulke => $liste ) {
			$t = 0;
			foreach ( $liste as $d ) { $t += (int) $d['hacim']; }
			$toplam[ $ulke ] = $t;
		}
		arsort( $toplam );
		$genel = array_sum( $toplam );
		echo '<p style="color:#646970">Açılacak ' . count( $acilacak ) . ' sayfanın bilinen toplam aylık arama hacmi: <b style="color:#1a5fb4">' . number_format_i18n( $genel ) . '</b>. Hacmi boş olanların rakamı henüz çekilmedi.</p>';
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th style="width:140px">Ülke</th><th style="width:60px;text-align:right">Adet</th>'
			. '<th style="width:110px;text-align:right">Aylık hacim</th><th>Başlıklar</th>'
			. '</tr></thead><tbody>';
		foreach ( $toplam as $ulke => $t ) {
			$liste = $grup[ $ulke ];
			usort( $liste, function ( $a, $b ) { return (int) $b['hacim'] <=> (int) $a['hacim']; } );
			$ad = array();
			foreach ( $liste as $d ) {
				$h = $d['hacim'] > 0 ? ' <span style="color:#1a5fb4;font-size:11px;font-weight:600">' . number_format_i18n( $d['hacim'] ) . '</span>' : '';
				$ad[] = esc_html( $d['ad'] ) . $h;
			}
			echo '<tr><td><b>' . esc_html( $ulke ) . '</b></td>'
				. '<td style="text-align:right">' . count( $liste ) . '</td>'
				. '<td style="text-align:right' . ( $t >= 10000 ? ';font-weight:700;color:#1a5fb4' : '' ) . '">' . ( $t ? number_format_i18n( $t ) : '–' ) . '</td>'
				. '<td style="font-size:12px;line-height:1.7">' . implode( ' · ', $ad ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	if ( $sluksuz ) {
		echo '<h2 style="margin-top:28px">4 · Slug girilmemiş <span style="font-weight:400;color:#646970;font-size:13px">(' . count( $sluksuz ) . ' satır)</span></h2>';
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th>Ad</th><th style="width:110px">Ülke</th><th style="width:110px">Durum</th></tr></thead><tbody>';
		foreach ( $sluksuz as $d ) {
			echo '<tr><td>' . esc_html( $d['ad'] ) . '</td><td>' . esc_html( $d['ulke'] ) . '</td><td>' . gbc_km_rozet( $d['durum'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<p style="margin-top:24px;color:#646970">Defteri düzenlemek için: <a href="' . esc_url( get_edit_post_link( 31304 ) ) . '">GBC Envanter Defteri</a></p>';
	echo '</div>';
}

/* ============================================================
   NOBETCI — ortaklik motoru canli mi?
   Saatte bir canli bir sayfayi cekip bakar: kisa kod cozulmus mu,
   link basiliyor mu, fatal var mi. Bozuksa her yonetici ekraninin
   tepesine kirmizi uyari asar. Amac: WPCode bir snippet'i calisan
   listesinden dusurdugunde bunu saatler sonra degil hemen gormek.
   ============================================================ */

function gbc_km_nobetci_url() {
	$u = get_option( 'gbc_km_nobetci_url' );
	if ( is_string( $u ) && $u !== '' ) { return $u; }
	return home_url( '/sorrento/' );
}

function gbc_km_nobetci_bak( $zorla = false ) {
	if ( ! $zorla && get_transient( 'gbc_km_nobetci_kilit' ) ) {
		return get_option( 'gbc_km_nobetci' );
	}
	set_transient( 'gbc_km_nobetci_kilit', 1, HOUR_IN_SECONDS );

	$url = add_query_arg( 'gbcnobet', time(), gbc_km_nobetci_url() );
	$c   = wp_remote_get( $url, array( 'timeout' => 25, 'sslverify' => false ) );

	$sonuc = array( 'zaman' => time(), 'url' => gbc_km_nobetci_url() );

	if ( is_wp_error( $c ) ) {
		$sonuc['durum'] = 'bilinmiyor';
		$sonuc['not']   = 'Sayfa çekilemedi: ' . $c->get_error_message();
		update_option( 'gbc_km_nobetci', $sonuc, false );
		return $sonuc;
	}

	$kod  = (int) wp_remote_retrieve_response_code( $c );
	$html = (string) wp_remote_retrieve_body( $c );

	$ham   = substr_count( $html, '[gbc_aff' );
	$link  = substr_count( $html, 'data-aff=' );
	$fatal = ( stripos( $html, 'Fatal error' ) !== false || stripos( $html, 'Parse error' ) !== false );

	$sonuc['kod']   = $kod;
	$sonuc['ham']   = $ham;
	$sonuc['link']  = $link;
	$sonuc['fatal'] = $fatal;

	if ( $kod !== 200 ) {
		$sonuc['durum'] = 'bozuk';
		$sonuc['not']   = 'Sayfa ' . $kod . ' döndü.';
	} elseif ( $fatal ) {
		$sonuc['durum'] = 'bozuk';
		$sonuc['not']   = 'Sayfada PHP hatası basılıyor.';
	} elseif ( $ham > 0 ) {
		$sonuc['durum'] = 'bozuk';
		$sonuc['not']   = $ham . ' adet [gbc_aff] kısa kodu düz metin olarak basılıyor — ortaklık motoru (snippet 30204) çalışmıyor. WPCode → 30204 → Güncelle.';
	} elseif ( $link < 1 ) {
		$sonuc['durum'] = 'bozuk';
		$sonuc['not']   = 'Sayfada hiç ortaklık bağlantısı yok. Motor çalışmıyor ya da ana şalter kapalı olabilir.';
	} else {
		$sonuc['durum'] = 'saglam';
		$sonuc['not']   = $link . ' ortaklık bağlantısı basılıyor.';
	}

	update_option( 'gbc_km_nobetci', $sonuc, false );
	return $sonuc;
}

add_action( 'gbc_km_nobetci_cek', 'gbc_km_nobetci2_bak' );

/* KAPATILDI — 28 Eylül 2026: eski Komuta nöbetçisi. İşini Kontrol Merkezi
   (inc/nobetci-kural.php) saat başı zaten yapıyor; ikisi birden çalışınca
   aynı sayfalar iki kez indiriliyordu. */
add_action( 'admin_init', function () {
	if ( get_option( 'gbc_km_cekim_kapali' ) ) { return; }
	if ( ! wp_next_scheduled( 'gbc_km_nobetci_cek' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'gbc_km_nobetci_cek' );
	}
}, 21 );

/* Yonetici ekranlarinda uyari. Sayfa yuklenirken istek atmamak icin
   sadece kayitli sonuca bakar; cekimi cron yapar. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = get_option( 'gbc_km_nobetci' );
	if ( ! is_array( $s ) || ! isset( $s['durum'] ) ) { return; }
	if ( $s['durum'] !== 'bozuk' ) { return; }
	$yas = isset( $s['zaman'] ) ? human_time_diff( (int) $s['zaman'] ) : '?';
	echo '<div class="notice notice-error"><p><strong>GBC Nöbetçi: ortaklık bağlantıları bozuk.</strong> '
		. esc_html( $s['not'] ) . ' <span style="color:#646970">(' . esc_html( $yas ) . ' önce ölçüldü · '
		. esc_html( isset( $s['url'] ) ? $s['url'] : '' ) . ')</span></p></div>';
} );

add_action( 'admin_menu', function () {
	/* Eski Nöbetçi kaldirildi (28 Eylul 2026): yerini GBC > Nöbetçi aldi.
	   Fonksiyonlari duruyor, yalniz menude gorunmuyor. */
}, 17 );

function gbc_km_ekran_nobetci() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_GET['gbcnob'] ) && check_admin_referer( 'gbc_km_nobet' ) ) {
		gbc_km_nobetci_bak( true );
		echo '<div class="notice notice-success"><p>Ölçüm yenilendi.</p></div>';
	}

	$s = get_option( 'gbc_km_nobetci' );
	echo '<div class="wrap"><h1>Nöbetçi</h1>';
	echo '<p style="color:#646970;max-width:760px">Saatte bir canlı bir sayfayı çekip ortaklık motorunun çalışıp çalışmadığına bakar. Bozuksa bütün yönetici ekranlarının tepesinde kırmızı uyarı çıkar.</p>';

	if ( ! is_array( $s ) || ! isset( $s['durum'] ) ) {
		echo '<p>Henüz ölçüm yapılmadı.</p>';
	} else {
		$renk = $s['durum'] === 'saglam' ? '#1a7f37' : ( $s['durum'] === 'bozuk' ? '#b32d2e' : '#8a6d00' );
		$ad   = $s['durum'] === 'saglam' ? 'SAĞLAM' : ( $s['durum'] === 'bozuk' ? 'BOZUK' : 'BİLİNMİYOR' );
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid ' . $renk . ';border-radius:6px;padding:14px 16px;max-width:760px">'
			. '<div style="font-size:20px;font-weight:700;color:' . $renk . '">' . $ad . '</div>'
			. '<p style="margin:6px 0 0">' . esc_html( $s['not'] ) . '</p>'
			. '<p style="margin:6px 0 0;color:#646970;font-size:12px">Ölçülen sayfa: <code>' . esc_html( $s['url'] ) . '</code>'
			. ' · ' . esc_html( human_time_diff( (int) $s['zaman'] ) ) . ' önce'
			. ( isset( $s['link'] ) ? ' · ' . (int) $s['link'] . ' bağlantı · ' . (int) $s['ham'] . ' çözülmemiş kod' : '' )
			. '</p></div>';
	}

	echo '<p style="margin-top:16px"><a class="button button-primary" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-nobetci&gbcnob=1' ), 'gbc_km_nobet' ) )
		. '">Şimdi ölç</a></p>';
	echo '</div>';
}

/* ============================================================
   NOBETCI v2 — butun motorlar
   Sablon basina birer ornek sayfayi cekip her birinde ayni
   olculere bakar. Belirli bir motorun ic isleyisini bilmesi
   gerekmez: bozulan motor kendini ciktida ele verir.
   ============================================================ */

function gbc_km_nobetci2_sayfalar() {
	$elle = get_option( 'gbc_km_nobetci_sayfa' );
	if ( is_array( $elle ) && $elle ) { return $elle; }

	$liste = array( home_url( '/' ) );
	$alindi = array();
	if ( function_exists( 'gbc_km_defter' ) ) {
		foreach ( gbc_km_defter() as $d ) {
			if ( $d['durum'] !== 'V' || $d['slug'] === '' ) { continue; }
			$h = $d['hedef'];
			if ( $h === '' || $h === '–' ) { continue; }
			if ( isset( $alindi[ $h ] ) ) { continue; }
			$alindi[ $h ] = true;
			$liste[]      = home_url( '/' . $d['slug'] . '/' );
			if ( count( $liste ) >= 7 ) { break; }
		}
	}
	$aff = home_url( '/sorrento/' );
	if ( ! in_array( $aff, $liste, true ) ) { $liste[] = $aff; }
	return $liste;
}

/* Sayfadaki kendi bilesenlerimizin izleri: gbc- / gz- ile baslayan
   sinif adlari. Hangi motorun ne bastigini bilmemize gerek yok;
   saglikli olcumde goruleni taban aliriz, sonra kaybolani ararız. */
function gbc_km_nobetci2_izler( $html ) {
	preg_match_all( '/class="([^"]*)"/i', $html, $m );
	$iz = array();
	foreach ( $m[1] as $liste ) {
		foreach ( preg_split( '/\s+/', $liste ) as $sinif ) {
			if ( $sinif === '' ) { continue; }
			if ( strpos( $sinif, 'gbc-' ) !== 0 && strpos( $sinif, 'gz-' ) !== 0 ) { continue; }
			$iz[ $sinif ] = isset( $iz[ $sinif ] ) ? $iz[ $sinif ] + 1 : 1;
		}
	}
	return $iz;
}

function gbc_km_nobetci2_olc( $url ) {
	$c = wp_remote_get( add_query_arg( 'gbcnobet', time(), $url ), array( 'timeout' => 25, 'sslverify' => false ) );
	$b = array( 'url' => $url );

	if ( is_wp_error( $c ) ) {
		$b['durum'] = 'bozuk';
		$b['not']   = 'Çekilemedi: ' . $c->get_error_message();
		return $b;
	}

	$kod  = (int) wp_remote_retrieve_response_code( $c );
	$html = (string) wp_remote_retrieve_body( $c );
	$b['kod']  = $kod;
	$b['bayt'] = strlen( $html );

	/* script ve style icerigi atilir: CSS'teki [class] gibi secicileri
	   kisa kod sanmayalim. */
	$govde = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html );
	if ( ! is_string( $govde ) ) { $govde = $html; }

	preg_match_all( '/\[(gbc_[a-z0-9_]*|gz_[a-z0-9_]*|wpcode)/i', $govde, $m );
	$b['kisakod'] = count( $m[0] );
	$b['kisakod_ornek'] = $b['kisakod'] ? array_slice( array_unique( $m[0] ), 0, 4 ) : array();

	$b['fatal'] = ( stripos( $html, 'Fatal error' ) !== false || stripos( $html, 'Parse error' ) !== false );
	$b['aff']   = substr_count( $html, 'data-aff=' );

	preg_match_all( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $j );
	$b['sema'] = count( $j[1] );
	$bozuk = 0;
	foreach ( $j[1] as $ham ) {
		$v = json_decode( trim( html_entity_decode( $ham, ENT_QUOTES, 'UTF-8' ) ), true );
		if ( $v === null ) { $bozuk++; }
	}
	$b['sema_bozuk'] = $bozuk;

	$b['ga4'] = ( strpos( $html, 'G-8P6FY60W1W' ) !== false );
	$b['h1']  = substr_count( $html, '<h1' );
	$b['iz']  = gbc_km_nobetci2_izler( $html );

	$sorun = array();
	if ( $kod !== 200 )       { $sorun[] = $kod . ' döndü'; }
	if ( $b['fatal'] )        { $sorun[] = 'PHP hatası basılıyor'; }
	if ( $b['kisakod'] )      { $sorun[] = $b['kisakod'] . ' çözülmemiş kısa kod (' . implode( ', ', $b['kisakod_ornek'] ) . ')'; }
	if ( $b['sema_bozuk'] )   { $sorun[] = $b['sema_bozuk'] . ' bozuk JSON-LD'; }
	if ( ! $b['ga4'] )        { $sorun[] = 'GA4 etiketi yok'; }
	if ( $b['h1'] < 1 )       { $sorun[] = 'H1 yok — şablon basmamış'; }

	$taban = get_option( 'gbc_km_nobetci_taban' );
	if ( ! is_array( $taban ) ) { $taban = array(); }
	$t = isset( $taban[ $url ] ) && is_array( $taban[ $url ] ) ? $taban[ $url ] : null;

	if ( $t && ! empty( $t['bayt'] ) && $t['bayt'] > 20000 && $b['bayt'] < $t['bayt'] * 0.65 ) {
		$sorun[] = 'sayfa %' . (int) ( 100 - $b['bayt'] / $t['bayt'] * 100 ) . ' küçülmüş';
	}

	$kayip = array();
	if ( $t && ! empty( $t['iz'] ) && is_array( $t['iz'] ) ) {
		foreach ( $t['iz'] as $sinif => $adet ) {
			if ( ! isset( $b['iz'][ $sinif ] ) ) { $kayip[] = $sinif; }
		}
	}
	$b['kayip'] = $kayip;
	if ( $kayip ) {
		$sorun[] = count( $kayip ) . ' bileşen kaybolmuş (' . implode( ', ', array_slice( $kayip, 0, 5 ) ) . ')';
	}

	$b['durum'] = $sorun ? 'bozuk' : 'saglam';
	$b['not']   = $sorun ? implode( ' · ', $sorun ) : 'temiz';
	if ( ! $sorun ) {
		$taban[ $url ] = array( 'bayt' => $b['bayt'], 'iz' => $b['iz'], 'zaman' => time() );
		update_option( 'gbc_km_nobetci_taban', $taban, false );
	}
	return $b;
}

function gbc_km_nobetci2_bak( $zorla = false ) {
	if ( ! $zorla && get_transient( 'gbc_km_nobetci_kilit' ) ) {
		return get_option( 'gbc_km_nobetci' );
	}
	set_transient( 'gbc_km_nobetci_kilit', 1, HOUR_IN_SECONDS );

	$bulgu = array();
	foreach ( gbc_km_nobetci2_sayfalar() as $u ) {
		$bulgu[] = gbc_km_nobetci2_olc( $u );
	}
	$bozuk = 0;
	foreach ( $bulgu as $b ) { if ( $b['durum'] === 'bozuk' ) { $bozuk++; } }

	$sonuc = array(
		'zaman' => time(),
		'bozuk' => $bozuk,
		'say'   => count( $bulgu ),
		'bulgu' => $bulgu,
	);

	/* Panelde degilken de haberin olsun: bozuldugunda tek e-posta.
	   Ayni arizayi her saat tekrar yollamamak icin 6 saat kilit. */
	$onceki = get_option( 'gbc_km_nobetci' );
	$eski_bozuk = is_array( $onceki ) && ! empty( $onceki['bozuk'] );
	if ( $bozuk && ! get_transient( 'gbc_km_nobetci_posta' ) ) {
		set_transient( 'gbc_km_nobetci_posta', 1, 6 * HOUR_IN_SECONDS );
		$satir = array();
		foreach ( $bulgu as $b ) {
			if ( $b['durum'] !== 'bozuk' ) { continue; }
			$satir[] = '• ' . str_replace( home_url(), '', $b['url'] ) . "\n  " . $b['not'];
		}
		$govde = "GBC Nöbetçi, sitede bir arıza buldu.\n\n"
			. $bozuk . '/' . count( $bulgu ) . " sayfa bozuk:\n\n"
			. implode( "\n\n", $satir )
			. "\n\nNöbetçi ekranı: " . admin_url( 'admin.php?page=gbc-komuta-nobetci' )
			. "\n\nEn sık sebep: bir WPCode snippet'i çalışan listeden düşmüştür."
			. "\nO snippet'i açıp Güncelle'ye basmak çoğu zaman yeterli olur.\n";
		wp_mail(
			get_option( 'admin_email' ),
			'[GBC] Nöbetçi alarmı — ' . $bozuk . ' sayfa bozuk',
			$govde
		);
	}
	if ( ! $bozuk && $eski_bozuk ) {
		delete_transient( 'gbc_km_nobetci_posta' );
		wp_mail(
			get_option( 'admin_email' ),
			'[GBC] Nöbetçi — arıza kapandı',
			"Sitedeki arıza giderildi, ölçülen sayfaların hepsi temiz.\n\n"
				. admin_url( 'admin.php?page=gbc-komuta-nobetci' ) . "\n"
		);
	}

	update_option( 'gbc_km_nobetci', $sonuc, false );
	return $sonuc;
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = get_option( 'gbc_km_nobetci' );
	if ( ! is_array( $s ) || empty( $s['bozuk'] ) ) { return; }
	$ilk = array();
	foreach ( $s['bulgu'] as $b ) {
		if ( $b['durum'] === 'bozuk' ) { $ilk[] = esc_html( str_replace( home_url(), '', $b['url'] ) . ' → ' . $b['not'] ); }
		if ( count( $ilk ) >= 3 ) { break; }
	}
	echo '<div class="notice notice-error"><p><strong>GBC Nöbetçi: '
		. (int) $s['bozuk'] . '/' . (int) $s['say'] . ' sayfa bozuk.</strong><br>'
		. implode( '<br>', $ilk ) . '<br>'
		. '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-komuta-nobetci' ) ) . '">Nöbetçi ekranı</a> · '
		. esc_html( human_time_diff( (int) $s['zaman'] ) ) . ' önce ölçüldü</p></div>';
} );

function gbc_km_ekran_nobetci2() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_GET['gbctaban'] ) && check_admin_referer( 'gbc_km_nobet' ) ) {
		delete_option( 'gbc_km_nobetci_taban' );
		gbc_km_nobetci2_bak( true );
		echo '<div class="notice notice-success"><p>Taban sıfırlandı; şu anki hâl yeni taban olarak alındı.</p></div>';
	} elseif ( isset( $_GET['gbcnob'] ) && check_admin_referer( 'gbc_km_nobet' ) ) {
		gbc_km_nobetci2_bak( true );
		echo '<div class="notice notice-success"><p>Ölçüm yenilendi.</p></div>';
	}

	$s = get_option( 'gbc_km_nobetci' );
	echo '<div class="wrap"><h1>Nöbetçi</h1>';
	echo '<p style="color:#646970;max-width:820px">Saatte bir, şablon başına birer örnek sayfayı önbelleksiz çeker. Her sayfada altı şeye bakar: HTTP durumu, PHP hatası, çözülmemiş kısa kod, JSON-LD şemalarının geçerliliği, GA4 etiketi ve H1. Bir motor sustuğunda çıktıda kendini belli eder.</p>';
	echo '<p style="margin:0 0 16px"><a class="button button-primary" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-nobetci&gbcnob=1' ), 'gbc_km_nobet' ) )
		. '">Şimdi ölç</a> <a class="button" href="'
		. esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-komuta-nobetci&gbctaban=1' ), 'gbc_km_nobet' ) )
		. '">Tabanı sıfırla</a></p>';

	if ( ! is_array( $s ) || empty( $s['bulgu'] ) ) {
		echo '<p>Henüz ölçüm yapılmadı.</p></div>';
		return;
	}

	$renk = empty( $s['bozuk'] ) ? '#1a7f37' : '#b32d2e';
	$ad   = empty( $s['bozuk'] ) ? 'HEPSİ SAĞLAM' : (int) $s['bozuk'] . ' SAYFA BOZUK';
	echo '<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid ' . $renk . ';border-radius:6px;padding:12px 16px;max-width:1000px;margin-bottom:16px">'
		. '<span style="font-size:18px;font-weight:700;color:' . $renk . '">' . esc_html( $ad ) . '</span>'
		. ' <span style="color:#646970">· ' . (int) $s['say'] . ' sayfa · ' . esc_html( human_time_diff( (int) $s['zaman'] ) ) . ' önce</span></div>';

	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
		. '<th>Sayfa</th><th style="width:60px">HTTP</th><th style="width:80px;text-align:right">Boyut</th>'
		. '<th style="width:70px;text-align:right">Ortaklık</th><th style="width:70px;text-align:right">Şema</th>'
		. '<th style="width:60px">GA4</th><th style="width:70px;text-align:right">Bileşen</th><th>Bulgu</th></tr></thead><tbody>';
	foreach ( $s['bulgu'] as $b ) {
		$iyi = $b['durum'] === 'saglam';
		$yol = str_replace( home_url(), '', $b['url'] );
		if ( $yol === '' ) { $yol = '/'; }
		echo '<tr>'
			. '<td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:7px;background:' . ( $iyi ? '#1a7f37' : '#b32d2e' ) . '"></span><code style="font-size:11px">' . esc_html( $yol ) . '</code></td>'
			. '<td>' . ( isset( $b['kod'] ) ? (int) $b['kod'] : '–' ) . '</td>'
			. '<td style="text-align:right">' . ( isset( $b['bayt'] ) ? number_format_i18n( round( $b['bayt'] / 1024 ) ) . ' KB' : '–' ) . '</td>'
			. '<td style="text-align:right">' . ( isset( $b['aff'] ) ? (int) $b['aff'] : '–' ) . '</td>'
			. '<td style="text-align:right">' . ( isset( $b['sema'] ) ? (int) $b['sema'] . ( ! empty( $b['sema_bozuk'] ) ? ' <b style="color:#b32d2e">!</b>' : '' ) : '–' ) . '</td>'
			. '<td>' . ( ! empty( $b['ga4'] ) ? '<span style="color:#1a7f37">var</span>' : '<span style="color:#b32d2e">yok</span>' ) . '</td>'
			. '<td style="text-align:right">' . ( isset( $b['iz'] ) ? count( $b['iz'] ) : '–' ) . ( ! empty( $b['kayip'] ) ? ' <b style="color:#b32d2e">-' . count( $b['kayip'] ) . '</b>' : '' ) . '</td>'
			. '<td style="font-size:12px;color:' . ( $iyi ? '#646970' : '#b32d2e' ) . '">' . esc_html( $b['not'] ) . '</td>'
			. '</tr>';
	}
	echo '</tbody></table>';
	echo '<p style="color:#646970;font-size:12px;margin-top:10px">Nöbetçi her sağlıklı ölçümde o sayfadaki bütün <code>gbc-</code> / <code>gz-</code> bileşenlerini taban olarak saklar. Sonraki ölçümde bir bileşen hiç görünmüyorsa o motor susmuş demektir — otel, rota, feribot, canlı kur, şema, video, hangisi olursa olsun. Sayfa boyutu tabanının %35 altına düşerse de bozuk sayılır. Tasarımı bilerek değiştirdiysen <b>Tabanı sıfırla</b> ile yeni hâli taban yap.</p>';
	echo '</div>';
}

} /* function_exists */