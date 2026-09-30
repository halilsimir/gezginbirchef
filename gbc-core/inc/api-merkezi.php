<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — API Merkezi.
 *
 * TEK KAPI. Sitenin dışarıdan aldığı bütün veri kaynakları ve anahtarları
 * tek yerde durur, tek yerde test edilir. Anahtarlar bugüne kadar üç ayrı
 * yere dağılmıştı (gbc_km_ayar, gbc_hz_ayar, modüller); artık merkez
 * burasıdır.
 *
 * BOZMAMA KURALI: merkez, anahtarı ESKİ YERİNE DE yazar. Böylece eski
 * modüller kendi seçeneklerinden okumaya devam eder, hiçbir şey kırılmaz.
 * Okurken de önce merkeze, yoksa eski yere bakılır.
 *
 * TEST: her satırın kendi testi vardır ve gerçekten dışarı çıkar —
 * "kayıtlı" ile "çalışıyor" ayrı şeylerdir. Test sonucu zamanıyla saklanır.
 *
 * ANAHTARLAR EKRANDA TAM GÖSTERİLMEZ. Kayıtlı bir alanı değiştirmek
 * istemiyorsan boş bırak; silmek için tek başına "-" yaz.
 */

define( 'GBC_API_DEPO', 'gbc_api' );
define( 'GBC_API_TEST', 'gbc_api_test' );

/* ============================================================
   KAYIT — hangi kaynak var, ne işe yarar, nerede kullanılıyor
   ============================================================ */
function gbc_api_kayit() {
	return array(

		/* --- Google --- */
		'psi_anahtar' => array(
			'ad'     => 'Google PageSpeed Insights',
			'grup'   => 'Google',
			'tip'    => 'gizli',
			'eski'   => array( 'secenek' => 'gbc_hz_ayar', 'alan' => 'psi_anahtar' ),
			'ne'     => __( 'Mobil ve masaüstü hız puanı, LCP, CLS', 'gbc-core' ),
			'kullanan' => __( 'Hız & Sağlık', 'gbc-core' ),
			'test'   => 'gbc_api_test_psi',
			'ipucu'  => __( 'console.cloud.google.com → API ve Hizmetler → PageSpeed Insights API → Anahtar', 'gbc-core' ),
		),
		'google_sa' => array(
			'ad'     => __( 'Google servis hesabı (JSON)', 'gbc-core' ),
			'grup'   => 'Google',
			'tip'    => 'uzun',
			'eski'   => array( 'secenek' => 'gbc_km_ayar', 'alan' => 'google_sa' ),
			'ne'     => __( 'Search Console ve GA4 raporlarını sunucudan çekmeyi sağlar', 'gbc-core' ),
			'kullanan' => __( 'SEO · GA4 Veri Çek · Google Testi', 'gbc-core' ),
			'test'   => 'gbc_api_test_sa',
			'ipucu'  => __( 'Servis hesabının e-postası GA4 ve Search Console’a okuyucu olarak eklenmeli.', 'gbc-core' ),
		),
		'gsc_site' => array(
			'ad'     => __( 'Search Console site adresi', 'gbc-core' ),
			'grup'   => 'Google',
			'tip'    => 'metin',
			'eski'   => array( 'secenek' => 'gbc_km_ayar', 'alan' => 'gsc_site' ),
			'ne'     => __( 'Hangi mülkten veri çekileceği', 'gbc-core' ),
			'kullanan' => __( 'SEO · GA4 Veri Çek', 'gbc-core' ),
			'test'   => 'gbc_api_test_gsc',
			'ipucu'  => 'sc-domain:gezginbirchef.com',
		),
		'ga4_property' => array(
			'ad'     => __( 'GA4 mülk numarası', 'gbc-core' ),
			'grup'   => 'Google',
			'tip'    => 'metin',
			'eski'   => array( 'secenek' => 'gbc_km_ayar', 'alan' => 'ga4_property' ),
			'ne'     => __( 'Oturum, sayfada kalma, dönüşüm raporları', 'gbc-core' ),
			'kullanan' => __( 'SEO · GA4 Veri Çek', 'gbc-core' ),
			'test'   => 'gbc_api_test_ga4_rapor',
			'ipucu'  => __( 'GA4 → Yönetici → Mülk ayarları → Mülk kimliği (yalnız rakam)', 'gbc-core' ),
		),
		'ga4_olcum' => array(
			'ad'     => __( 'GA4 ölçüm kimliği', 'gbc-core' ),
			'grup'   => 'Google',
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'Sayfaya basılan gtag kimliği — 5 olay, 8 parametre', 'gbc-core' ),
			'kullanan' => __( 'modules/30-olcum.php · Günlük Kontrol', 'gbc-core' ),
			'test'   => 'gbc_api_test_ga4',
			'ipucu'  => 'G-XXXXXXXXXX',
			'varsayilan' => 'G-8P6FY60W1W',
		),

		/* --- Ortaklık --- */
		'tp_token' => array(
			'bicim'  => '/^[a-f0-9]{24,40}$/i',
			'bicim_not' => __( 'Harf ve rakamdan oluşan uzun dizi.', 'gbc-core' ),
			'ad'     => 'Travelpayouts API',
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'gizli',
			'eski'   => null,
			'ne'     => __( 'Uçuş ve otel fiyatı çekme, kazanç istatistiği', 'gbc-core' ),
			'kullanan' => __( 'Sitedeki ortaklığın ana kaynağı — 580 bağlantı', 'gbc-core' ),
			'test'   => 'gbc_api_test_tp',
			'ipucu'  => __( 'travelpayouts.com → Hesap → API belirteci', 'gbc-core' ),
		),
		'tp_marker' => array(
			'ad'     => __( 'Travelpayouts ortak kodu (marker)', 'gbc-core' ),
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'Bağlantılara eklenen ortak numarası — kazancın hangi hesaba yazılacağı', 'gbc-core' ),
			'kullanan' => __( 'Ortaklık motoru · defterde 583 bağlantıda geçiyor', 'gbc-core' ),
			'varsayilan' => '767959',
			'test'   => null,
			'bicim'  => '/^\\d{4,9}$/',
			'bicim_not' => __( 'Yalnız rakam, 4-9 hane (örn. 123456).', 'gbc-core' ),
		),
		'impact_sid' => array(
			'ad'     => 'Impact.com — Account SID',
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'Impact ağındaki programların raporu', 'gbc-core' ),
			'kullanan' => __( 'Skyscanner · 13 bağlantı (skyscanner.pxf.io)', 'gbc-core' ),
			'test'   => 'gbc_api_test_impact',
			'ipucu'  => __( 'Impact → Settings → API → Account SID (IR ile başlar)', 'gbc-core' ),
			'bicim'  => '/^[A-Za-z0-9_]{8,}$/',
		),
		'impact_token' => array(
			'ad'     => 'Impact.com — Auth Token',
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'gizli',
			'eski'   => null,
			'ne'     => __( 'Impact API parolası — SID ile birlikte çalışır', 'gbc-core' ),
			'kullanan' => __( 'İş Ortaklığı (planlanıyor)', 'gbc-core' ),
			'test'   => 'gbc_api_test_impact',
			'ipucu'  => __( 'Aynı ekranda Auth Token. "Legacy Account Tokens" da buraya yazılır.', 'gbc-core' ),
		),
		'gyg_partner' => array(
			'ad'     => __( 'GetYourGuide — doğrudan partner ID', 'gbc-core' ),
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'GetYourGuide ile doğrudan ortaklığın kimliği', 'gbc-core' ),
			'kullanan' => __( 'Şu an KULLANILMIYOR', 'gbc-core' ),
			'test'   => null,
			'not'    => __( 'Ölçüldü (28 Eyl 2026): sitedeki 169 GetYourGuide bağlantısının TAMAMI Travelpayouts üzerinden gidiyor (campaign_id=108, marker=767959). Doğrudan tek bağlantı yok. Bu alanı doldurman kazancı değiştirmez; doğrudan ortaklığa geçilirse defterdeki bağlantıların yeniden yazılması gerekir.', 'gbc-core' ),
		),
		'ferryhopper_uid' => array(
			'ad'     => __( 'Ferryhopper ortak kodu (aff_uid)', 'gbc-core' ),
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'Feribot bağlantılarındaki ortak kodu — doğrudan ortaklık', 'gbc-core' ),
			'kullanan' => __( 'Ortaklık motoru · 76 bağlantı', 'gbc-core' ),
			'test'   => null,
			'varsayilan' => 'gzgbr',
			'not'    => __( 'Ferryhopper doğrudan ortaklık; bağlantılar Travelpayouts’tan geçmiyor.', 'gbc-core' ),
		),
		'cj_token' => array(
			'ad'     => __( 'CJ Affiliate — kişisel erişim belirteci', 'gbc-core' ),
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'gizli',
			'eski'   => null,
			'ne'     => __( 'CJ ağındaki programların kazanç raporu', 'gbc-core' ),
			'kullanan' => __( 'Henüz kullanılmıyor — ileride', 'gbc-core' ),
			'test'   => 'gbc_api_test_cj',
			'ipucu'  => __( 'CJ → Account → Personal Access Tokens', 'gbc-core' ),
		),
		'cj_cid' => array(
			'ad'     => __( 'CJ Affiliate — yayıncı numarası (CID)', 'gbc-core' ),
			'grup'   => __( 'Ortaklık', 'gbc-core' ),
			'tip'    => 'metin',
			'eski'   => null,
			'ne'     => __( 'CJ hesabının yayıncı kimliği', 'gbc-core' ),
			'kullanan' => __( 'Henüz kullanılmıyor — ileride', 'gbc-core' ),
			'test'   => null,
			'bicim'  => '/^\\d{5,10}$/',
			'bicim_not' => __( 'Yalnız rakam.', 'gbc-core' ),
		),

		/* --- Kelime ve içerik --- */


		/* --- Otomasyon --- */
	);
}

/* Site içi kaynaklar — anahtar istemez, yalnız çalışıyor mu diye bakılır. */
function gbc_api_ic_kaynaklar() {
	return array(
		'rm_gsc' => array(
			'ad'   => __( 'Rank Math · Search Console tablosu', 'gbc-core' ),
			'ne'   => __( 'Her sayfanın kelimeleri, sırası, tıklaması', 'gbc-core' ),
			'test' => 'gbc_api_test_rm_gsc',
			'kullanan' => __( 'SEO · Fırsatlar · Envanter · Sayfa Denetimi', 'gbc-core' ),
		),
		'rm_link' => array(
			'ad'   => __( 'Rank Math · iç bağlantı sayacı', 'gbc-core' ),
			'ne'   => __( 'Kim kime bağ veriyor, yetim sayfalar', 'gbc-core' ),
			'test' => 'gbc_api_test_rm_link',
			'kullanan' => __( 'Envanter · Silo ağacı', 'gbc-core' ),
		),
		'ubersuggest' => array(
			'ad'   => __( 'Ubersuggest (Claude üzerinden)', 'gbc-core' ),
			'ne'   => __( 'Kelime varyantları, arama hacmi, zorluk — buraya anahtar GİRİLMEZ', 'gbc-core' ),
			'test' => null,
			'kullanan' => __( 'Claude MCP ile çeker, panele yazar. Eklentiye açık API’si yok.', 'gbc-core' ),
		),
		'make' => array(
			'ad'   => __( 'Make.com (kendi eklentisi)', 'gbc-core' ),
			'ne'   => __( 'Senaryolar — Make Connector eklentisi kendi bağlantısını kurar', 'gbc-core' ),
			'test' => null,
			'kullanan' => __( 'Buraya anahtar girilmez.', 'gbc-core' ),
		),
		'aff_sayac' => array(
			'ad'   => __( 'GBC ortaklık tık sayacı', 'gbc-core' ),
			'ne'   => __( 'Hangi bağlantıya kaç kez tıklandı', 'gbc-core' ),
			'test' => 'gbc_api_test_aff',
			'kullanan' => __( 'İş Ortaklığı', 'gbc-core' ),
		),
	);
}

/* ============================================================
   OKUMA / YAZMA
   ============================================================ */

/** Merkezden oku; yoksa eski yerinden. Site her yerden bunu çağırabilir. */
function gbc_api( $id, $varsayilan = '' ) {
	$depo = get_option( GBC_API_DEPO, array() );
	if ( is_array( $depo ) && isset( $depo[ $id ] ) && '' !== $depo[ $id ] ) {
		return $depo[ $id ];
	}

	$kayit = gbc_api_kayit();
	if ( isset( $kayit[ $id ]['eski']['secenek'] ) ) {
		$eski = get_option( $kayit[ $id ]['eski']['secenek'], array() );
		$alan = $kayit[ $id ]['eski']['alan'];
		if ( is_array( $eski ) && isset( $eski[ $alan ] ) && '' !== $eski[ $alan ] ) {
			return $eski[ $alan ];
		}
	}
	if ( isset( $kayit[ $id ]['varsayilan'] ) && '' === $varsayilan ) {
		return $kayit[ $id ]['varsayilan'];
	}
	return $varsayilan;
}

/** Merkeze yaz — ve eski yerine de yaz ki eski modüller kırılmasın. */
function gbc_api_yaz( $id, $deger ) {
	$depo = get_option( GBC_API_DEPO, array() );
	if ( ! is_array( $depo ) ) { $depo = array(); }

	if ( '-' === $deger ) { unset( $depo[ $id ] ); $deger = ''; }
	else { $depo[ $id ] = $deger; }
	update_option( GBC_API_DEPO, $depo, false );

	$kayit = gbc_api_kayit();
	if ( isset( $kayit[ $id ]['eski']['secenek'] ) ) {
		$ad   = $kayit[ $id ]['eski']['secenek'];
		$alan = $kayit[ $id ]['eski']['alan'];
		$eski = get_option( $ad, array() );
		if ( ! is_array( $eski ) ) { $eski = array(); }
		$eski[ $alan ] = $deger;
		update_option( $ad, $eski, false );
	}
}

function gbc_api_maske( $deger ) {
	$deger = (string) $deger;
	$u = strlen( $deger );
	if ( 0 === $u ) { return ''; }
	if ( $u <= 8 ) { return str_repeat( '•', $u ); }
	return substr( $deger, 0, 4 ) . str_repeat( '•', max( 4, $u - 8 ) ) . substr( $deger, -4 );
}

/**
 * Biçim kontrolü — "doğru girdim mi" sorusunu ekran cevaplasın.
 * Anahtarın kendisi gösterilmez; yalnız beklenen kalıba uyup uymadığı.
 */
function gbc_api_bicim( $id, $deger ) {
	$k = gbc_api_kayit();
	if ( '' === (string) $deger ) { return array( 'durum' => 'bos' ); }
	if ( empty( $k[ $id ]['bicim'] ) ) { return array( 'durum' => 'kontrol_yok' ); }
	$uyar = (bool) preg_match( $k[ $id ]['bicim'], (string) $deger );
	return array(
		'durum' => $uyar ? 'uygun' : 'uygunsuz',
		'not'   => isset( $k[ $id ]['bicim_not'] ) ? $k[ $id ]['bicim_not'] : '',
	);
}

/**
 * Gizli olmayan durum aynası. Anahtarların KENDİSİ değil, yalnız
 * "dolu mu, kaç karakter, biçimi uygun mu" bilgisi saklanır; bu ayna
 * dışarıdan okunabilir olduğu için içine hiçbir sır yazılmaz.
 */
function gbc_api_ayna_guncelle() {
	$ayna = array();
	foreach ( gbc_api_kayit() as $id => $k ) {
		$d = (string) gbc_api( $id );
		$b = gbc_api_bicim( $id, $d );
		$ayna[ $id ] = array(
			'ad'     => $k['ad'],
			'dolu'   => ( '' !== $d ),
			'uzunluk'=> strlen( $d ),
			'bicim'  => $b['durum'],
			'son4'   => ( strlen( $d ) > 6 ) ? substr( $d, -4 ) : '',
		);
	}
	update_option( 'gbc_api_durum', $ayna, false );
	return $ayna;
}

/* ============================================================
   TESTLER — hepsi gerçekten dışarı çıkar
   ============================================================ */
/** Şu an listede duran bütün kaynak kimlikleri. */
function gbc_api_gecerli_kimlikler() {
	return array_merge( array_keys( gbc_api_kayit() ), array_keys( gbc_api_ic_kaynaklar() ) );
}

/**
 * Öksüz test kayıtlarını siler.
 * Listeden çıkarılan bir kaynağın (örn. Make.com, Booking AID) eski test
 * sonucu depoda kalıyor ve sayaçta görünüyordu — ekranda satırı olmayan
 * bir şeyin sayılması yanlış rapor demektir.
 */
function gbc_api_test_temizle() {
	$t = get_option( GBC_API_TEST, array() );
	if ( ! is_array( $t ) || ! $t ) { return 0; }

	$gecerli = gbc_api_gecerli_kimlikler();
	$silinen = 0;
	foreach ( array_keys( $t ) as $id ) {
		if ( ! in_array( $id, $gecerli, true ) ) { unset( $t[ $id ] ); $silinen++; }
	}
	if ( $silinen ) { update_option( GBC_API_TEST, $t, false ); }
	return $silinen;
}

function gbc_api_test_kaydet( $id, $ok, $mesaj ) {
	$t = get_option( GBC_API_TEST, array() );
	if ( ! is_array( $t ) ) { $t = array(); }
	$t[ $id ] = array( 'zaman' => time(), 'ok' => (bool) $ok, 'mesaj' => (string) $mesaj );
	update_option( GBC_API_TEST, $t, false );
	return $t[ $id ];
}

function gbc_api_test_psi() {
	$k = gbc_api( 'psi_anahtar' );
	if ( '' === $k ) { return gbc_api_test_kaydet( 'psi_anahtar', false, __( 'Anahtar girilmemiş.', 'gbc-core' ) ); }

	$u = add_query_arg( array(
		'url' => home_url( '/' ), 'strategy' => 'mobile', 'category' => 'performance', 'key' => $k,
	), 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

	$c = wp_remote_get( $u, array( 'timeout' => 60 ) );
	if ( is_wp_error( $c ) ) { return gbc_api_test_kaydet( 'psi_anahtar', false, $c->get_error_message() ); }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( 200 !== $kod ) {
		$aciklama = 403 === $kod ? __( 'Anahtar reddedildi (IP ya da API kısıtı).', 'gbc-core' )
			: ( 429 === $kod ? __( 'Kota doldu.', 'gbc-core' ) : '' );
		return gbc_api_test_kaydet( 'psi_anahtar', false, 'HTTP ' . $kod . ( $aciklama ? ' — ' . $aciklama : '' ) );
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $c ), true );
	$puan = isset( $j['lighthouseResult']['categories']['performance']['score'] )
		? (int) round( $j['lighthouseResult']['categories']['performance']['score'] * 100 ) : 0;
	return gbc_api_test_kaydet( 'psi_anahtar', true, sprintf( __( 'Çalışıyor — ana sayfa mobil puanı %d.', 'gbc-core' ), $puan ) );
}

function gbc_api_test_sa() {
	$sa = gbc_api( 'google_sa' );
	if ( '' === $sa ) { return gbc_api_test_kaydet( 'google_sa', false, __( 'Servis hesabı girilmemiş.', 'gbc-core' ) ); }
	$j = json_decode( $sa, true );
	if ( ! is_array( $j ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
		return gbc_api_test_kaydet( 'google_sa', false, __( 'JSON okunamadı — client_email ya da private_key yok.', 'gbc-core' ) );
	}
	if ( ! function_exists( 'gbc_km_token' ) ) {
		return gbc_api_test_kaydet( 'google_sa', false, __( 'Belirteç fonksiyonu yüklü değil (modül 90 kapalı olabilir).', 'gbc-core' ) );
	}
	$tok = gbc_km_token( true );
	if ( ! $tok || ( is_array( $tok ) && ! empty( $tok['hata'] ) ) ) {
		return gbc_api_test_kaydet( 'google_sa', false, __( 'Google belirteç vermedi. Hesabın yetkisi var mı?', 'gbc-core' ) );
	}
	return gbc_api_test_kaydet( 'google_sa', true, sprintf( __( 'Belirteç alındı · %s', 'gbc-core' ), $j['client_email'] ) );
}

function gbc_api_test_ga4() {
	if ( ! function_exists( 'gbc_ga4_denetim' ) ) {
		return gbc_api_test_kaydet( 'ga4_olcum', false, __( 'Günlük Kontrol modülü yüklü değil.', 'gbc-core' ) );
	}
	$d = gbc_ga4_denetim();
	if ( ! empty( $d['hata'] ) ) { return gbc_api_test_kaydet( 'ga4_olcum', false, $d['hata'] ); }
	$eksik = count( $d['eksik'] );
	return gbc_api_test_kaydet( 'ga4_olcum', 0 === $eksik,
		0 === $eksik ? __( '5 olay ve 8 parametrenin hepsi sayfada basılıyor.', 'gbc-core' )
			: sprintf( __( '%d madde eksik — Günlük Kontrol’de listeli.', 'gbc-core' ), $eksik ) );
}

function gbc_api_test_tp() {
	$t = gbc_api( 'tp_token' );
	if ( '' === $t ) { return gbc_api_test_kaydet( 'tp_token', false, __( 'Belirteç girilmemiş.', 'gbc-core' ) ); }

	$u = add_query_arg( array( 'currency' => 'eur', 'limit' => 1, 'token' => $t ),
		'https://api.travelpayouts.com/v2/prices/latest' );
	$c = wp_remote_get( $u, array( 'timeout' => 20 ) );
	if ( is_wp_error( $c ) ) { return gbc_api_test_kaydet( 'tp_token', false, $c->get_error_message() ); }

	$kod = (int) wp_remote_retrieve_response_code( $c );
	$j   = json_decode( (string) wp_remote_retrieve_body( $c ), true );
	if ( 200 === $kod && is_array( $j ) && ! empty( $j['success'] ) ) {
		return gbc_api_test_kaydet( 'tp_token', true, __( 'Belirteç geçerli, veri geldi.', 'gbc-core' ) );
	}
	$mesaj = is_array( $j ) && ! empty( $j['message'] ) ? (string) $j['message'] : 'HTTP ' . $kod;
	return gbc_api_test_kaydet( 'tp_token', false, $mesaj );
}

function gbc_api_test_impact() {
	$sid = gbc_api( 'impact_sid' );
	$tok = gbc_api( 'impact_token' );
	if ( '' === $sid || '' === $tok ) {
		return gbc_api_test_kaydet( 'impact_sid', false, __( 'SID ya da Auth Token eksik — ikisi birlikte çalışır.', 'gbc-core' ) );
	}

	$u = 'https://api.impact.com/Mediapartners/' . rawurlencode( $sid ) . '/Campaigns?PageSize=1';
	$c = wp_remote_get( $u, array(
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $tok ),
			'Accept'        => 'application/json',
		),
	) );
	if ( is_wp_error( $c ) ) { return gbc_api_test_kaydet( 'impact_sid', false, $c->get_error_message() ); }

	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( 401 === $kod || 403 === $kod ) {
		return gbc_api_test_kaydet( 'impact_sid', false, __( 'Kimlik reddedildi — SID ya da Auth Token yanlış.', 'gbc-core' ) );
	}
	if ( 200 !== $kod ) { return gbc_api_test_kaydet( 'impact_sid', false, 'HTTP ' . $kod ); }

	$j = json_decode( (string) wp_remote_retrieve_body( $c ), true );
	$adet = is_array( $j ) && isset( $j['@total'] ) ? (int) $j['@total'] : ( is_array( $j ) && isset( $j['Campaigns'] ) ? count( (array) $j['Campaigns'] ) : 0 );
	$sonuc = gbc_api_test_kaydet( 'impact_sid', true, sprintf( __( 'Bağlandı — %d program görünüyor.', 'gbc-core' ), $adet ) );
	gbc_api_test_kaydet( 'impact_token', true, __( 'SID ile birlikte doğrulandı.', 'gbc-core' ) );
	return $sonuc;
}

function gbc_api_test_webhook() {
	$u = gbc_api( 'make_webhook' );
	if ( '' === $u ) { return gbc_api_test_kaydet( 'make_webhook', false, __( 'Adres girilmemiş.', 'gbc-core' ) ); }
	if ( ! preg_match( '#^https://#i', $u ) ) { return gbc_api_test_kaydet( 'make_webhook', false, __( 'Adres https ile başlamalı.', 'gbc-core' ) ); }

	$c = wp_remote_post( $u, array(
		'timeout' => 15,
		'body'    => array( 'kaynak' => 'gbc-core', 'olay' => 'test', 'zaman' => time() ),
	) );
	if ( is_wp_error( $c ) ) { return gbc_api_test_kaydet( 'make_webhook', false, $c->get_error_message() ); }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	return gbc_api_test_kaydet( 'make_webhook', ( $kod >= 200 && $kod < 400 ), 'HTTP ' . $kod );
}

/**
 * Search Console sorgusu — eski "Google Testi" ekranının 2. adımı.
 * Belirteç almak yetmez; gerçekten satır dönüyor mu ona bakılır.
 */
function gbc_api_test_gsc() {
	if ( ! function_exists( 'gbc_km_gsc' ) ) {
		return gbc_api_test_kaydet( 'gsc_site', false, __( 'Sorgu fonksiyonu yüklü değil (modül 90 kapalı olabilir).', 'gbc-core' ) );
	}
	if ( '' === (string) gbc_api( 'gsc_site' ) ) {
		return gbc_api_test_kaydet( 'gsc_site', false, __( 'Site adresi girilmemiş.', 'gbc-core' ) );
	}

	$bas = gmdate( 'Y-m-d', time() - 9 * DAY_IN_SECONDS );
	$bit = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
	$g   = gbc_km_gsc( $bas, $bit, array( 'query' ), 5 );

	if ( is_wp_error( $g ) ) { return gbc_api_test_kaydet( 'gsc_site', false, $g->get_error_message() ); }
	$adet = isset( $g['rows'] ) ? count( (array) $g['rows'] ) : 0;
	return gbc_api_test_kaydet( 'gsc_site', true,
		$adet ? sprintf( __( 'Çalışıyor — %1$s / %2$s aralığında %3$d satır döndü.', 'gbc-core' ), $bas, $bit, $adet )
			: __( 'Bağlantı çalıştı ama bu aralıkta satır yok.', 'gbc-core' ) );
}

/** GA4 raporu — eski "Google Testi" ekranının 3. adımı. */
function gbc_api_test_ga4_rapor() {
	if ( ! function_exists( 'gbc_km_ga4' ) ) {
		return gbc_api_test_kaydet( 'ga4_property', false, __( 'Rapor fonksiyonu yüklü değil (modül 90 kapalı olabilir).', 'gbc-core' ) );
	}
	$ham = (string) gbc_api( 'ga4_property' );
	if ( '' === $ham ) { return gbc_api_test_kaydet( 'ga4_property', false, __( 'Mülk numarası girilmemiş.', 'gbc-core' ) ); }
	$tem = preg_replace( '/[^0-9]/', '', $ham );
	if ( '' === $tem ) { return gbc_api_test_kaydet( 'ga4_property', false, __( 'Mülk numarası rakam içermiyor.', 'gbc-core' ) ); }

	$a = gbc_km_ga4( array(
		'dateRanges' => array( array( 'startDate' => '7daysAgo', 'endDate' => 'yesterday' ) ),
		'dimensions' => array( array( 'name' => 'pagePath' ) ),
		'metrics'    => array( array( 'name' => 'sessions' ) ),
		'limit'      => 5,
	) );
	if ( is_wp_error( $a ) ) { return gbc_api_test_kaydet( 'ga4_property', false, $a->get_error_message() ); }

	$adet = isset( $a['rows'] ) ? count( (array) $a['rows'] ) : 0;
	return gbc_api_test_kaydet( 'ga4_property', true,
		$adet ? sprintf( __( 'Çalışıyor — son 7 günde %d sayfa satırı döndü.', 'gbc-core' ), $adet )
			: __( 'Bağlantı çalıştı ama satır dönmedi.', 'gbc-core' ) );
}

function gbc_api_test_cj() {
	$t = gbc_api( 'cj_token' );
	if ( '' === $t ) { return gbc_api_test_kaydet( 'cj_token', false, __( 'Belirteç girilmemiş.', 'gbc-core' ) ); }

	$c = wp_remote_post( 'https://ads.api.cj.com/query', array(
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Bearer ' . $t,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( array( 'query' => '{ __typename }' ) ),
	) );
	if ( is_wp_error( $c ) ) { return gbc_api_test_kaydet( 'cj_token', false, $c->get_error_message() ); }

	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( 401 === $kod || 403 === $kod ) {
		return gbc_api_test_kaydet( 'cj_token', false, __( 'Belirteç reddedildi.', 'gbc-core' ) );
	}
	return gbc_api_test_kaydet( 'cj_token', ( $kod >= 200 && $kod < 300 ), 'HTTP ' . $kod );
}

function gbc_api_test_rm_gsc() {
	$k = function_exists( 'gbc_seo_kaynak' ) ? gbc_seo_kaynak() : array( 'var' => false, 'not' => '' );
	return gbc_api_test_kaydet( 'rm_gsc', ! empty( $k['var'] ),
		! empty( $k['var'] ) ? __( 'Tablo okunuyor.', 'gbc-core' ) : ( isset( $k['not'] ) ? $k['not'] : '' ) );
}

function gbc_api_test_rm_link() {
	$k = function_exists( 'gbc_env_bag_kaynak' ) ? gbc_env_bag_kaynak() : array( 'var' => false, 'not' => '' );
	return gbc_api_test_kaydet( 'rm_link', ! empty( $k['var'] ),
		! empty( $k['var'] ) ? __( 'Tablo okunuyor.', 'gbc-core' ) : ( isset( $k['not'] ) ? $k['not'] : '' ) );
}

function gbc_api_test_aff() {
	$a = get_option( 'gbc_aff_tik', array() );
	$n = is_array( $a ) ? count( $a ) : 0;
	return gbc_api_test_kaydet( 'aff_sayac', $n > 0,
		$n ? sprintf( __( '%d kayıt var.', 'gbc-core' ), $n ) : __( 'Henüz tıklama kaydı yok.', 'gbc-core' ) );
}

/** Hepsini sırayla dener. */
function gbc_api_hepsini_test() {
	$sonuc = array();
	foreach ( gbc_api_kayit() as $id => $k ) {
		if ( empty( $k['test'] ) || ! function_exists( $k['test'] ) ) { continue; }
		$sonuc[ $id ] = call_user_func( $k['test'] );
	}
	foreach ( gbc_api_ic_kaynaklar() as $id => $k ) {
		if ( empty( $k['test'] ) || ! function_exists( $k['test'] ) ) { continue; }
		$sonuc[ $id ] = call_user_func( $k['test'] );
	}
	return $sonuc;
}

/* ============================================================
   EKRAN
   ============================================================ */
function gbc_api_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$kayit = gbc_api_kayit();

	/* Kaydet */
	if ( isset( $_POST['gbc_api_kaydet'] ) && check_admin_referer( 'gbc_api' ) ) {
		$n = 0;
		foreach ( $kayit as $id => $k ) {
			if ( ! isset( $_POST[ 'api_' . $id ] ) ) { continue; }
			$deger = trim( (string) wp_unslash( $_POST[ 'api_' . $id ] ) );
			if ( '' === $deger ) { continue; }   /* bos = degistirme */
			gbc_api_yaz( $id, 'uzun' === $k['tip'] ? $deger : sanitize_text_field( $deger ) );
			$n++;
		}
		gbc_api_ayna_guncelle();
		echo '<div class="notice notice-success"><p>'
			. esc_html( sprintf( __( '%d alan güncellendi. Eski yerlerine de yazıldı, hiçbir modül kırılmadı.', 'gbc-core' ), $n ) )
			. '</p></div>';
	}

	/* Test */
	if ( isset( $_GET['test'] ) && check_admin_referer( 'gbc_api' ) ) {
		$hedef = sanitize_key( $_GET['test'] );
		if ( 'hepsi' === $hedef ) {
			$r = gbc_api_hepsini_test();
			echo '<div class="notice notice-info"><p>'
				. esc_html( sprintf( __( '%d kaynak test edildi.', 'gbc-core' ), count( $r ) ) ) . '</p></div>';
		} else {
			$fn = isset( $kayit[ $hedef ]['test'] ) ? $kayit[ $hedef ]['test'] : '';
			if ( ! $fn ) {
				$ic = gbc_api_ic_kaynaklar();
				$fn = isset( $ic[ $hedef ]['test'] ) ? $ic[ $hedef ]['test'] : '';
			}
			if ( $fn && function_exists( $fn ) ) {
				$r = call_user_func( $fn );
				echo '<div class="notice notice-' . ( $r['ok'] ? 'success' : 'error' ) . '"><p>'
					. esc_html( $r['mesaj'] ) . '</p></div>';
			}
		}
	}

	if ( isset( $_GET['ayna'] ) && check_admin_referer( 'gbc_api' ) ) {
		gbc_api_ayna_guncelle();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Durum tazelendi.', 'gbc-core' ) . '</p></div>';
	}

	/* Listeden çıkarılmış kaynakların eski test kayıtları sayaca girmesin. */
	$oksuz = gbc_api_test_temizle();

	$test = get_option( GBC_API_TEST, array() );
	if ( ! is_array( $test ) ) { $test = array(); }

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC API Merkezi', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-api' ); }

	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'Sitenin dışarıdan aldığı bütün veri kaynakları ve anahtarları burada durur. Anahtar buraya girilir, eski yerine de yazılır — hiçbir modül kırılmaz. Her satırın kendi testi var ve gerçekten dışarı çıkar: “kayıtlı” ile “çalışıyor” ayrı şeylerdir.', 'gbc-core' )
		. '</p>';

	$u = static function ( $ek ) { return wp_nonce_url( admin_url( 'admin.php?page=gbc-api' . $ek ), 'gbc_api' ); };

	/* Ozet */
	$bagli = 0; $toplam = 0;
	foreach ( $kayit as $id => $k ) {
		$toplam++;
		if ( '' !== (string) gbc_api( $id ) ) { $bagli++; }
	}
	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_seo_kart( __( 'Kayıtlı anahtar', 'gbc-core' ), $bagli . ' / ' . $toplam, '', $bagli ? '#1A7F37' : '#8A6100' );
	/* Sayaç YALNIZ ekranda satırı olan kaynakları sayar. */
	$gecerli = gbc_api_gecerli_kimlikler();
	$calisan = 0; $bozuk = 0; $testli = 0;
	foreach ( $test as $tid => $t ) {
		if ( ! in_array( $tid, $gecerli, true ) ) { continue; }
		$testli++;
		if ( ! empty( $t['ok'] ) ) { $calisan++; } else { $bozuk++; }
	}
	$test_edilebilir = 0;
	foreach ( gbc_api_kayit() as $kk ) { if ( ! empty( $kk['test'] ) ) { $test_edilebilir++; } }
	foreach ( gbc_api_ic_kaynaklar() as $kk ) { if ( ! empty( $kk['test'] ) ) { $test_edilebilir++; } }

	echo gbc_seo_kart( __( 'Testi geçen', 'gbc-core' ), $calisan . ' / ' . $test_edilebilir,
		__( 'test edilebilen kaynak', 'gbc-core' ), $calisan ? '#1A7F37' : '#5C6470' );
	echo gbc_seo_kart( __( 'Testte kalan', 'gbc-core' ), (string) $bozuk, '', $bozuk ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Test edilemez', 'gbc-core' ), (string) ( count( gbc_api_gecerli_kimlikler() ) - $test_edilebilir ),
		__( 'numara ya da kod — denenecek uç yok', 'gbc-core' ), '#5C6470' );
	echo '</div>';

	if ( $oksuz ) {
		echo '<div class="notice notice-info inline" style="margin:12px 0"><p>'
			. esc_html( sprintf( __( 'Listeden çıkarılmış %d eski test kaydı silindi; sayaç artık yalnız ekrandaki satırları sayıyor.', 'gbc-core' ), $oksuz ) )
			. '</p></div>';
	}

	echo '<p><a class="button button-primary" href="' . esc_url( $u( '&test=hepsi' ) ) . '">'
		. esc_html__( 'Hepsini test et', 'gbc-core' ) . '</a> '
		. '<a class="button" href="' . esc_url( $u( '&ayna=1' ) ) . '">' . esc_html__( 'Durumu tazele', 'gbc-core' ) . '</a></p>';

	/* --- Dis kaynaklar, gruplu --- */
	echo '<form method="post">';
	wp_nonce_field( 'gbc_api' );

	$gruplar = array();
	foreach ( $kayit as $id => $k ) { $gruplar[ $k['grup'] ][ $id ] = $k; }

	foreach ( $gruplar as $grup => $liste ) {
		echo '<h2 style="margin-top:26px">' . esc_html( $grup ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:1400px"><thead><tr>'
			. '<th style="width:230px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th>'
			. '<th style="width:300px">' . esc_html__( 'Anahtar', 'gbc-core' ) . '</th>'
			. '<th style="width:220px">' . esc_html__( 'Test', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Ne veriyor · nerede kullanılıyor', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>';

		foreach ( $liste as $id => $k ) {
			$deger = (string) gbc_api( $id );
			$t     = isset( $test[ $id ] ) ? $test[ $id ] : null;

			echo '<tr>';
			echo '<td><strong>' . esc_html( $k['ad'] ) . '</strong>'
				. ( ! empty( $k['ipucu'] ) ? '<div style="color:#5C6470;font-size:12px">' . esc_html( $k['ipucu'] ) . '</div>' : '' )
				. '</td>';

			echo '<td>';
			if ( 'uzun' === $k['tip'] ) {
				echo '<textarea name="api_' . esc_attr( $id ) . '" rows="3" style="width:100%" placeholder="'
					. esc_attr( '' !== $deger ? __( 'kayıtlı — değiştirmek için yapıştır', 'gbc-core' ) : __( 'boş', 'gbc-core' ) ) . '"></textarea>';
			} else {
				echo '<input type="text" name="api_' . esc_attr( $id ) . '" style="width:100%" placeholder="'
					. esc_attr( '' !== $deger ? gbc_api_maske( $deger ) : __( 'boş', 'gbc-core' ) ) . '">';
			}
			$b = gbc_api_bicim( $id, $deger );
			echo '<div style="font-size:12px;color:' . ( '' !== $deger ? '#1A7F37' : '#8A6100' ) . '">'
				. esc_html( '' !== $deger ? sprintf( __( 'kayıtlı · %d karakter', 'gbc-core' ), strlen( $deger ) ) : __( 'girilmemiş', 'gbc-core' ) ) . '</div>';
			if ( 'uygunsuz' === $b['durum'] ) {
				echo '<div style="font-size:12px;color:#B3261E;font-weight:600">'
					. esc_html__( 'Biçim beklenene uymuyor.', 'gbc-core' )
					. ( ! empty( $b['not'] ) ? ' ' . esc_html( $b['not'] ) : '' ) . '</div>';
			} elseif ( 'uygun' === $b['durum'] ) {
				echo '<div style="font-size:12px;color:#1A7F37">' . esc_html__( 'Biçim doğru görünüyor.', 'gbc-core' ) . '</div>';
			}
			echo '</td>';

			echo '<td>';
			if ( ! empty( $k['test'] ) ) {
				echo '<a class="button button-small" href="' . esc_url( $u( '&test=' . $id ) ) . '">' . esc_html__( 'Test et', 'gbc-core' ) . '</a>';
				if ( $t ) {
					echo '<div style="margin-top:5px;font-size:12px;color:' . ( $t['ok'] ? '#1A7F37' : '#B3261E' ) . ';font-weight:600">'
						. esc_html( $t['ok'] ? __( 'ÇALIŞIYOR', 'gbc-core' ) : __( 'HATA', 'gbc-core' ) ) . '</div>';
					echo '<div style="font-size:12px;color:#5C6470">' . esc_html( $t['mesaj'] ) . '</div>';
					echo '<div style="font-size:11.5px;color:#8A919C">' . esc_html( human_time_diff( (int) $t['zaman'] ) . ' ' . __( 'önce', 'gbc-core' ) ) . '</div>';
				}
			} else {
				echo '<span style="color:#8A919C;font-size:12.5px">' . esc_html__( 'test edilemez', 'gbc-core' ) . '</span>';
			}
			echo '</td>';

			echo '<td style="font-size:13px">' . esc_html( $k['ne'] )
				. '<div style="color:#5C6470;font-size:12px">' . esc_html( $k['kullanan'] ) . '</div>'
				. ( ! empty( $k['not'] ) ? '<div style="color:#8A6100;font-size:12px;margin-top:3px">' . esc_html( $k['not'] ) . '</div>' : '' )
				. '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	echo '<p style="margin-top:16px"><button type="submit" name="gbc_api_kaydet" value="1" class="button button-primary">'
		. esc_html__( 'Kaydet', 'gbc-core' ) . '</button> '
		. '<span style="color:#5C6470;font-size:12.5px">'
		. esc_html__( 'Boş bıraktığın alan değişmez. Bir anahtarı silmek için kutuya tek başına “-” yaz.', 'gbc-core' )
		. '</span></p>';
	echo '</form>';

	/* --- Site içi kaynaklar --- */
	echo '<h2 style="margin-top:30px">' . esc_html__( 'Site içi kaynaklar', 'gbc-core' ) . '</h2>';
	echo '<p style="color:#5C6470;max-width:1000px">' . esc_html__( 'Anahtar istemezler; sitenin kendi tablolarından okunur. Yine de çalışıp çalışmadıkları test edilir.', 'gbc-core' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:1400px"><thead><tr>'
		. '<th style="width:280px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th>'
		. '<th style="width:220px">' . esc_html__( 'Test', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Ne veriyor · nerede kullanılıyor', 'gbc-core' ) . '</th></tr></thead><tbody>';
	foreach ( gbc_api_ic_kaynaklar() as $id => $k ) {
		$t = isset( $test[ $id ] ) ? $test[ $id ] : null;
		echo '<tr><td><strong>' . esc_html( $k['ad'] ) . '</strong></td>';
		echo '<td>';
		if ( ! empty( $k['test'] ) ) {
			echo '<a class="button button-small" href="' . esc_url( $u( '&test=' . $id ) ) . '">' . esc_html__( 'Test et', 'gbc-core' ) . '</a>';
		} else {
			echo '<span style="color:#8A919C;font-size:12.5px">' . esc_html__( 'bilgi satırı', 'gbc-core' ) . '</span>';
		}
		if ( $t ) {
			echo '<div style="margin-top:5px;font-size:12px;color:' . ( $t['ok'] ? '#1A7F37' : '#B3261E' ) . ';font-weight:600">'
				. esc_html( $t['ok'] ? __( 'ÇALIŞIYOR', 'gbc-core' ) : __( 'HATA', 'gbc-core' ) ) . '</div>';
			echo '<div style="font-size:12px;color:#5C6470">' . esc_html( $t['mesaj'] ) . '</div>';
		}
		echo '</td>';
		echo '<td style="font-size:13px">' . esc_html( $k['ne'] )
			. '<div style="color:#5C6470;font-size:12px">' . esc_html( $k['kullanan'] ) . '</div></td></tr>';
	}
	echo '</tbody></table>';

	echo '</div>';
}
