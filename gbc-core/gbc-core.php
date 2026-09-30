<?php
/**
 * Plugin Name: GBC Core
 * Description: gezginbirchef.com motorları. WPCode'dan taşınan 19 modül tek eklentide toplanır. Kontrol Merkezi her gün bütün motorları canlı sayfada test eder. Her modül, WPCode sürümü hâlâ çalışıyorsa kendini yüklemez.
 * Version: 1.48.2
 * Author: Gezginbirchef
 * Requires PHP: 8.0
 * Text Domain: gbc-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GBC_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GBC_CORE_URL', plugin_dir_url( __FILE__ ) );
/* TEK KAYNAK. Basliktaki Version ile birebir ayni olmak ZORUNDA —
   scratchpad/surum_testi.php bunu her calismada dogruluyor.
   29 Eyl 2026: GBC_CORE_SURUM hic tanimlanmamisti; inc/gunluk.php onu
   okudugu icin kural surumu surekli '0' kaliyor, eklenti guncellenince
   sorun defteri supurulmuyordu. GBC_CORE_VER de 1.14.0'da takili kalmisti. */
define( 'GBC_CORE_SURUM', '1.48.2' );
define( 'GBC_CORE_VER', GBC_CORE_SURUM );

/* KURAL SÜRÜMÜ — sorun defterinin süpürme damgası.
   GBC_CORE_SURUM'dan AYRI tutuluyor: 30 Eyl 2026'da ölçüldü ki her
   sıradan sürüm yükseltmesi açık sorunların tamamını siliyordu ve
   defter yanlışlıkla "temiz" görünüyordu. Bu sabiti YALNIZCA bir
   tespit kuralı gerçekten değiştiğinde artır; yeni özellik, hata
   düzeltmesi ya da paketleme için ASLA. */
define( 'GBC_KURAL_SURUM', '4' );

/* Sol menudeki GBC ikonu: sitenin logosundaki asci kepi, 40x40, beyaz.
   WordPress menu ikonlarini yeniden renklendirmez; bu yuzden beyaz basilip
   inc/durum.php icindeki CSS ile WP'nin kendi opaklik davranisina uyduruluyor. */
define( 'GBC_CORE_IKON', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MCA0MCI+PGltYWdlIGhyZWY9ImRhdGE6aW1hZ2UvcG5nO2Jhc2U2NCxpVkJPUncwS0dnb0FBQUFOU1VoRVVnQUFBQ2dBQUFBb0NBWUFBQUNNL3JodEFBQUZSMGxFUVZSNEFleVhhYWhWVlJTQXo4MU1zNmRXbGxtcG1VbTlMSXdnYVo2Z3NvbU1pcVJvb0FHS2lJZ29zR2lRQWpXTUtHaW1qS0tvakxBU29qQ2FqTWlRNmtlUmxHa2h6dk04ZS8yK2cvZDR6N3RuNzN1dWl2ampQZGIzMWg3V1hudmRkZmJlNSt3RGt2MzhyelBBUFgxQW5SbnN6R0I5QnFyVmFnVUd3U2g0QmliQUl6QUMrc05lWHpLbEhUTDVrUVQ3Skh3QzE4UC84Q2xNaFZtd0JicGoxd1dPZzVGd045d0VGMEkvcUdEVGtwUUtFTWNuNHRWZ3prZmZDL2ZBdS9CVHBWTDVIZjZsdkJqMGR6LzZhVmdCVStBcm1Bbm53SzM0R2dBVnlxVkVoMUZEbkEzQzRDMzRFNjZER1FTMEFqWkRsWHE5UEVHbEg5d0gwK2hmQk10aEVYVi9vRnhKK1dUOGxnb3lHaUJPZXVKc05NeUQwVXkwR2pvR1JWY3FJL2x2cHNkZ3N3NXlkdFMzdzJwc0pzSmwwQithU2pCQWd2TVhEc1hER1RBZTU4dlJoWUp0RHpxZWhjZXcyNFFPQ3YydVZaL0lRNHpyRmpUYzJSRU1rUDd1NEtQNkR2MFh4T1JjT3Qwb3JqV0tjU0hJTlZpNHVVYWdvOUlzd0ZNWS9Ua09vMW5CNW1yNERMdmNZNlV0SnY3d204bGlMSVlrMXRrYjcxM0J6S0NLaFFsY0NtZlRPeDFLQ3o5bVBjWXVEZGM1eFdKcENKQUpCME1YekYzRTI5R3JJQ2IraUtNd1dBQ3Rpa2RSbjlpZ1hJQUU1b1pvWTRDQnJVWDd5Q3hUREVvdmVnNkNqZENxR0p6SkNJN0xBaVE0enp0MzdTelNiMkFlTGE2OVpqdnRFTHc3aVdNb2xoUG1jOHlwV0MrRG9LUUJZbXdRbDJQMUc4RzVOaWdtWm1RK2hXTWdKbWJQZGVpamp0bDE3SE0rZy9NeGQrekw2bW1BMUk0RjM3VnowVFhaUU9GRE9KMGZVTE9qMmlCbXdnRFZEWjFGRGZoekxsK0hMNUNRYU9ackV4K05vODNnK1lSS0VnWnVUWkxrZXpnYzNOR29RakdEK3BGQ2cvcEdnbk91OGJUNTd2NFlIWldhMDhPdzJrQlEyOUQxc3BUS1pPaUw0MUNHUE5ETklHWmhZZnpCNElIK09sWkQ0QUhtY3hsUkRFc3RRQ2ZwaFlQY1JEaHdCN3NPNStBaTlDZ09wVTgvYmlpS2pZTGZBMm05QWQ0RTM4ZDNvUDBDUXNWRngxcjRudlVyeElQVGVvWkJnbDh1QnB1MTF4VThtbHdPMmZLbzY2c3Y2dHVUNFZFYTUrQXo1SS91WFZJTGNBbE54NE9iQlZWT3lJemp6OEo2TmhOMlhCNDBaMkxmSDlSY3kxMnhEVDBOVFBMaUJMYTRlMWRTdUpSSld6a3VCak9tUFVtU1g5RkIyUm1RUjRxQjlRMGFGblNrQWVMQTRQeENkbTBNSThqY1dpd1lsMkRqN3IyUlBuZjVxK2htNG5ubmEzTW9ZOU41bXcyd3Y5N3dCeHEraHRkZ2VNd0pmV2JaRDRUYnNYVk05SU1DRzhYZ2ZxWndIdmc2UlRXWExFQ3k2TUg4SEVOK2dmZkJDMC9EL1lIZzNQRit0citJaldQR29OZEJWUER2a2VLNTZpZWNmby9BMTBEd050Z0g3Uk5wOEpFRmFBOU9QUGNlcC93SytLbnZHVGlPd1hlQkY1NEhhZjhBekxJNzkwN0t0WGMzeFdKaGJBL3dETHdFaXdIZ2N0SzNiNnBKMUwyclRNTEdLK3hBNnBua0FyU1ZJRjByTDFIMmp1R3Q3R0xLRThBRDlpbTBYeUJmb3YrR21kZ2JLTVZHWWNJaDRGWGdXM3JmZ1haNEhzYkN3K0FYZSswVytBWDFDMkFLWTY1Q3A5SVFvSzFNNnIzQlkyRWM5U3ZnTkJnR2ZuMFl1RXZoQk9xNVgwczlGU1pvQndPYVJvTWI2UTIwSHdlM29WMGFrNWxqT25obDlRUndIWHVadW9WK2svRTI0ejJYdzEvVURLNkNCN1RYeHJtVWZaVHowR2JZWFkrdnhJRFZLVGp0Q1M2UEdUU1llUU02aWZKRXhqbmVXK0VXeXJsRG1ycHpiVVc3a1Z6L0h1aG1NeHdnVG1QaXB0Q0p1NzBiUWJXQkdack5vR3ZBOWR2T2hGUEJIK241UjNOcDhaTXYzVFNGajdpRUczZXZXVG9UMitId0hud0UzNERIeU1zRTVpUlVXeGF2RHk2cGZ4eTVXd0V5K1NZWUN4ZkJqM0F0OUlaUjhCKzBtakZqU1dIc1FtaUQ5QksyV3dHbW52YlJ2ODRBOXpUUiszMEdkd0FBQVAvL3hYOUFMd0FBQUFaSlJFRlVBd0RMS05CZzdmcXhkZ0FBQUFCSlJVNUVya0pnZ2c9PSIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIi8+PC9zdmc+' );

/* Modül durum kaydı. Durum sayfası (inc/durum.php) bunu okur. */
$GLOBALS['gbc_core_durum'] = array();

/**
 * Bir modülün durumunu kaydeder.
 *
 * @param string $anahtar Modül dosya adı (anahtar).
 * @param string $durum   eklenti | wpcode | kapali | dosya-yok
 * @param string $not     Serbest açıklama.
 */
function gbc_core_isaret( $anahtar, $durum, $not = '' ) {
	$GLOBALS['gbc_core_durum'][ $anahtar ] = array( 'durum' => $durum, 'not' => $not );
}

/**
 * Modül tanımları.
 *
 * imza = o snippet'in WPCode sürümünde tanımlı bir fonksiyon adı.
 * Fonksiyon zaten varsa WPCode sürümü çalışıyor demektir; eklenti sürümü yüklenmez.
 * imza null ise çakışma tespiti yapılamaz (snippet tamamen closure'lardan oluşuyor).
 *
 * @return array
 */
function gbc_core_moduller() {
	return array(
		array( 'dosya' => '01-sablon-css.php',       'ad' => 'Şablon CSS\'i Dosyaya',      'imza' => 'gbc_css_sablon_listesi',   'kaynak' => 30188, 'kapat' => array( 30188 ) ),
		array( 'dosya' => '02-head-temizlik.php',   'ad' => 'Head Temizliği',             'imza' => 'gbc_head_tekille',         'kaynak' => 0, 'kapat' => array() ),
		array( 'dosya' => '03-satir-ici-js.php',    'ad' => 'Satır İçi JS\'i Dosyaya',    'imza' => 'gbc_js_bas',               'kaynak' => 0, 'kapat' => array() ),
		array( 'dosya' => '04-hayalet-cron.php',     'ad' => 'Hayalet Cron Temizliği',     'imza' => 'gbc_hayalet_cron_listesi', 'kaynak' => 30190, 'kapat' => array( 30190 ) ),
		array( 'dosya' => '10-guvenlik.php',        'ad' => 'Güvenlik + Şefe Sor',        'imza' => 'gbc_sor_ip',                'kaynak' => 31160, 'kapat' => array( 31160 ) ),
		array( 'dosya' => '11-duvar.php',            'ad' => 'Güvenlik Duvarları',         'imza' => 'gbc_duvar_basliklar',      'kaynak' => 0, 'kapat' => array() ),
		array( 'dosya' => '20-sema-cekirdek.php',   'ad' => 'Şema Çekirdek',              'imza' => 'gbc_core_identity_schema',  'kaynak' => 29013, 'kapat' => array( 29013 ) ),
		array( 'dosya' => '21-sema-motoru.php',     'ad' => 'Şema Motoru',                'imza' => 'gbc_v3_graph',              'kaynak' => 29738, 'kapat' => array( 29738 ) ),
		array( 'dosya' => '30-olcum.php',           'ad' => 'Analytics + Reklam (GA4)',   'imza' => null,                        'kaynak' => 28270, 'kapat' => array( 28270, 31363 ) ),
		array( 'dosya' => '40-otel.php',            'ad' => 'Otel Motoru',                'imza' => 'gbc_otel_kisa_kod',         'kaynak' => 30242, 'kapat' => array( 30242 ) ),
		array( 'dosya' => '41-rota.php',            'ad' => 'Rota Motoru',                'imza' => 'gbc_rota_shortcode',        'kaynak' => 30696, 'kapat' => array( 30696 ) ),
		array( 'dosya' => '42-feribot.php',         'ad' => 'Feribot Rota Bulucu',        'imza' => 'gbc_fb_render',             'kaynak' => 30350, 'tip' => 'kisakod', 'render' => 'gbc_fb_render', 'kisakod' => 'gbc_feribot', 'kapat' => array( 30350 ) ),
		array( 'dosya' => '43-yunanistan.php',      'ad' => 'Yunanistan Pillar',          'imza' => 'gbc_yn_render',             'kaynak' => 30361, 'tip' => 'kisakod', 'render' => 'gbc_yn_render', 'kisakod' => 'gbc_yunanistan', 'kapat' => array( 30361 ) ),
		array( 'dosya' => '44-kur.php',             'ad' => 'Canlı Kur',                  'imza' => 'gbc_kur_cek',               'kaynak' => 30236, 'kapat' => array( 30236 ) ),
		array( 'dosya' => '50-bolum-medyasi.php',   'ad' => 'Bölüm Medyası',              'imza' => 'gbc_bolum_medyasi',         'kaynak' => 29969, 'kapat' => array( 29969 ) ),
		array( 'dosya' => '51-galeri-lisans.php',   'ad' => 'Galeri Görsel Lisansı',      'imza' => 'gbc_gal_topla',             'kaynak' => 30294, 'kapat' => array( 30294 ) ),
		array( 'dosya' => '52-ilgili-yazilar.php',  'ad' => 'İlgili Yazılar',             'imza' => 'gbc_related_posts',         'kaynak' => 27100, 'kapat' => array( 27100 ) ),
		array( 'dosya' => '53-video-oynatici.php',  'ad' => 'Liste Kartı Video Oynatıcı', 'imza' => null,                        'kaynak' => 31178, 'kapat' => array( 31178 ) ),
		array( 'dosya' => '54-yt-facade.php',       'ad' => 'YouTube Facade',             'imza' => null,                        'kaynak' => 28208, 'kapat' => array( 28208 ) ),
		array( 'dosya' => '55-ucus-perde.php',       'ad' => 'Uçuş Widget Perdesi',        'imza' => 'gbc_sky_perde',            'kaynak' => 0,     'kapat' => array() ),
		array( 'dosya' => '56-alt-linkler.php',     'ad' => 'Alt Linkler Kutusu',         'imza' => 'gbc_alt_ayristir',          'kaynak' => 0,     'kapat' => array() ),
		array( 'dosya' => '60-font-temizlik.php',   'ad' => 'Font & Emoji Temizliği',     'imza' => null,                        'kaynak' => 24462, 'kapat' => array( 24462 ) ),
		array( 'dosya' => '70-ortaklik-denetim.php','ad' => 'Ortaklık Denetim Paneli',    'imza' => 'gbc_aff_tik_yaz',   /* 29 Eyl 2026: gbc_aff_panel_menu v1.15.0'da kaldirildi, imza tiklama yazicisina tasindi */        'kaynak' => 30126, 'kapat' => array( 30126 ) ),
		array( 'dosya' => '71-ortaklik-motoru.php', 'ad' => 'Ortaklık Bağlantı Motoru',   'imza' => 'gbc_aff_kisa_kod',         'kaynak' => 31366, 'kapat' => array( 31366, 31358, 30204, 30188 ) ),
		array( 'dosya' => '90-komuta.php',          'ad' => 'Komuta Merkezi',            'imza' => 'gbc_km_alanlar',           'kaynak' => 30210, 'kapat' => array( 30210, 31365 ) ),
	);
}

/**
 * Modülleri yükler.
 *
 * Öncelik 99: WPCode kendi snippet'lerini daha erken basar. Böylece
 * function_exists() denetimi doğru sonuç verir ve çift tanım fatal'ı olmaz.
 */
add_action( 'plugins_loaded', 'gbc_core_yukle', 99 );
function gbc_core_yukle() {

	$kapali = get_option( 'gbc_core_kapali', array() );
	if ( ! is_array( $kapali ) ) {
		$kapali = array();
	}

	foreach ( gbc_core_moduller() as $m ) {
		$a = $m['dosya'];

		/* 1) Elle kapatılmış mı? */
		if ( isset( $kapali[ $a ] ) ) {
			gbc_core_isaret( $a, 'kapali', 'Durum sayfasından kapatıldı.' );
			continue;
		}

		/* 2) WPCode sürümü hâlâ çalışıyor mu?
		      Fonksiyon tanımlıysa dosyayı YÜKLEMEYİZ — "Cannot redeclare"
		      ölümcül hatası olmasın. Ama mesajı doğru yazmak için yönetici
		      tarafında snippet'in gerçekten yayında olup olmadığına da
		      bakarız: kapalı olduğu hâlde fonksiyon tanımlıysa bu bir
		      ÖNBELLEK KALINTISIDIR (WPCode kendi snippet önbelleğini
		      Redis'te tutuyor; pasife alma bazen hemen yansımıyor).
		      Ziyaretçi isteğinde bu sorgu hiç çalışmaz. */
		if ( ! empty( $m['imza'] ) && function_exists( $m['imza'] ) ) {

			$snippet_acik = null;
			if ( is_admin() && ! empty( $m['kapat'] ) ) {
				$snippet_acik = false;
				foreach ( (array) $m['kapat'] as $sid ) {
					if ( (int) $sid && 'publish' === get_post_status( (int) $sid ) ) {
						$snippet_acik = true;
						break;
					}
				}
			}

			if ( false === $snippet_acik ) {
				gbc_core_isaret(
					$a,
					'kalinti',
					$m['imza'] . '() tanımlı ama WPCode snippet\'i KAPALI. Bu bir önbellek kalıntısı: '
						. 'LiteSpeed → Purge All ve nesne önbelleğini (Redis) temizle, sonra bu sayfayı yenile.'
				);
			} else {
				gbc_core_isaret( $a, 'wpcode', $m['imza'] . '() zaten tanımlı — WPCode sürümü çalışıyor.' );
			}
			continue;
		}

		/* 2b) İmzası olmayan modüller (tamamı closure'lardan oluşuyor, yani
		      function_exists ile denetlenemiyor): WPCode'daki kopya HÂLÂ
		      yayındaysa yükleme. Yoksa aynı çıktı iki kez basılabilir.
		      Kopyayı WPCode'da pasife alınca modül kendiliğinden devreye girer. */
		if ( empty( $m['imza'] ) && ! empty( $m['kapat'] ) ) {
			$acik = array();
			foreach ( (array) $m['kapat'] as $sid ) {
				if ( 'publish' === get_post_status( (int) $sid ) ) {
					$acik[] = (int) $sid;
				}
			}
			if ( $acik ) {
				gbc_core_isaret( $a, 'wpcode-acik', 'Önce WPCode\'da şu snippet(ler)i pasife al: ' . implode( ', ', $acik ) );
				continue;
			}
		}

		/* 3) Dosya yerinde mi? */
		$yol = GBC_CORE_DIR . 'modules/' . $a;
		if ( ! file_exists( $yol ) ) {
			gbc_core_isaret( $a, 'dosya-yok', 'modules/' . $a . ' bulunamadı.' );
			continue;
		}

		/* 4) Yükle. */
		require_once $yol;

		/* 5) Kısa kod tipi modüller: dosya kendi kendine hiçbir şey basmaz.
		      Hem kendi kısa kodlarına hem de içerikteki eski [wpcode id="..."]
		      çağrısına bağlanırlar; 1040 yazının içeriğine dokunmaya gerek yok. */
		if ( isset( $m['tip'] ) && 'kisakod' === $m['tip'] && ! empty( $m['render'] ) && function_exists( $m['render'] ) ) {
			$GLOBALS['gbc_core_kisakod'][ (int) $m['kaynak'] ] = $m['render'];
			if ( ! empty( $m['kisakod'] ) && ! shortcode_exists( $m['kisakod'] ) ) {
				add_shortcode( $m['kisakod'], $m['render'] );
			}
			gbc_core_isaret( $a, 'eklenti', 'Kısa kod: [' . $m['kisakod'] . '] · [wpcode id="' . (int) $m['kaynak'] . '"] da bu modüle bağlandı.' );
			continue;
		}

		gbc_core_isaret( $a, 'eklenti', '' );
	}
}

/**
 * Eski [wpcode id="30350"] / [wpcode id="30361"] çağrılarını modüle bağlar.
 *
 * pre_do_shortcode_tag ile WPCode'un kendi çözümünden ÖNCE araya giriyoruz.
 * Böylece WPCode o snippet'i hiç değerlendirmez — çift fonksiyon tanımı
 * (Cannot redeclare) riski de ortadan kalkar.
 */
add_filter( 'pre_do_shortcode_tag', 'gbc_core_wpcode_koprusu', 9, 3 );
function gbc_core_wpcode_koprusu( $ret, $tag, $attr ) {
	if ( 'wpcode' !== $tag ) {
		return $ret;
	}
	$sid = isset( $attr['id'] ) ? (int) $attr['id'] : 0;
	if ( ! $sid || empty( $GLOBALS['gbc_core_kisakod'][ $sid ] ) ) {
		return $ret;
	}
	$fn = $GLOBALS['gbc_core_kisakod'][ $sid ];
	if ( ! is_callable( $fn ) ) {
		return $ret;
	}
	return (string) call_user_func( $fn );
}

/* Eski Nöbetçi'den kalan zamanlanmış iş ve uyarı kaydı temizlenir.
   Modül yüklenmediği için kanca boşa düşüyordu; WP her seferinde onu
   çağırıp hiçbir şey yapmıyordu. */
add_action( 'init', 'gbc_core_eski_nobetci_temizle', 20 );
function gbc_core_eski_nobetci_temizle() {
	if ( wp_next_scheduled( 'gbc_nb_gunluk' ) ) {
		wp_clear_scheduled_hook( 'gbc_nb_gunluk' );
	}
	if ( false !== get_option( 'gbc_nb_alarm', false ) ) {
		delete_option( 'gbc_nb_alarm' );
	}

	/* ESKİ KOMUTA ÇEKİMİ — 28 Eylül 2026'da kapatıldı.
	   Search Console verisini Rank Math zaten kendi tablosunda tutuyor ve
	   yeni SEO ekranları oradan okuyor; Komuta tabloları yalnız eski Komuta
	   ekranlarınca okunuyordu. Aynı veriyi ikinci kez indirmek Google
	   kotasını boşa harcıyordu. Tablolar silinmedi.
	   Geri açmak: gbc_km_cekim_kapali seçeneğini sil. */
	if ( ! get_option( 'gbc_km_cekim_kapali' ) ) {
		update_option( 'gbc_km_cekim_kapali', 1, false );
	}
	foreach ( array( 'gbc_km_gunluk_cek', 'gbc_km_sezon_cek', 'gbc_km_nobetci_cek' ) as $eski_is ) {
		if ( wp_next_scheduled( $eski_is ) ) { wp_clear_scheduled_hook( $eski_is ); }
	}
}

/* ============================================================
   KRİTİK CSS TEST TEZGÂHI — ERKEN YÜKLENİR
   ------------------------------------------------------------
   Modül kayıtlarıyla birlikte plugins_loaded 99'da yüklenemez:
   LiteSpeed'in Optimize::init()'i ayarı ondan ÖNCE okuyor. Bu yüzden
   burada, eklenti dosyası yüklenirken require ediliyor ve kanca
   plugins_loaded 1'e takılıyor.

   Ziyaretçiye etkisi SIFIR: yalnız adresinde doğru gizli anahtar olan
   isteklerde devreye giriyor, o istek de önbelleğe alınmıyor.
   ============================================================ */
if ( file_exists( GBC_CORE_DIR . 'inc/ccss-test.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/ccss-test.php';
	add_action( 'plugins_loaded', 'gbc_ccss_zorla', 1 );
}

/* 30 Eyl 2026 · v1.24.0 — Gutenberg blok CSS'i.
   ZIYARETCI tarafinda calisir (yonetici kosulu YOK): govdesi yalniz kisa
   koddan ibaret sayfalarda WordPress'in 140 KB'lik blok kitapligini ve
   global-styles'i kuyruktan cikarir. Olcum: birlesik CSS'in %43'u bu.
   Sayfa gercekten blok kullaniyorsa dokunmaz. */
/* 30 Eyl 2026 · v1.25.0 — olu CSS kurallarini ayiklayan suzgec.
   modules/01-sablon-css.php bunu kullaniyor; ZIYARETCI tarafinda da
   gerektigi icin admin kosulunun disinda yukleniyor. */
if ( file_exists( GBC_CORE_DIR . 'inc/css-suzgec.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/css-suzgec.php';
}

/* 30 Eyl 2026 · v1.26.0 — ziyaretcide gereksiz JS (jQuery Migrate). */
if ( file_exists( GBC_CORE_DIR . 'inc/js-temizlik.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/js-temizlik.php';
}

if ( file_exists( GBC_CORE_DIR . 'inc/blok-css.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/blok-css.php';
}

/* 30 Eyl 2026 · v1.30.0 — ACF alan koruma.
   Metin bekleyen alana dizi yazilirsa PHP 8'de esc_textarea() TypeError
   firlatiyor ve DUZENLEME EKRANI o alanda kesiliyor (30 Eyl'de 4 yazida
   yasandi). Bu dosya okuma ve yazma kapilarini tutar. Yonetici ve on yuz
   ikisinde de gerekli: get_field() her yerde cagriliyor. */
if ( file_exists( GBC_CORE_DIR . 'inc/alan-koruma.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/alan-koruma.php';
}

/* Ortak tasarım — bütün GBC ekranları aynı görünsün. */
if ( is_admin() && file_exists( GBC_CORE_DIR . 'inc/tasarim.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/tasarim.php';
}

/* Durum sayfası. */
if ( is_admin() && file_exists( GBC_CORE_DIR . 'inc/durum.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/durum.php';
}

/* 30 Eyl 2026 · v1.34.0 — Alan Tanı. Denetimin dolu alani bos gormesi
   uzerine yazildi; tek yaziyi taramanin yolundan okuyup ham veriyle
   yan yana koyar. SALT OKUMA, yalniz yonetici ekraninda. */
if ( is_admin() && file_exists( GBC_CORE_DIR . 'inc/alan-tani.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/alan-tani.php';
}

/* Kontrol Merkezi, Güvenlik ve diğer paneller: yönetici ekranı VE sunucu cronu.
   Ziyaretçi isteğinde hiçbiri yüklenmez — sitenin hızına etkisi sıfır.

   28 Eylül 2026: iki ayrı Nöbetçi tek Kontrol Merkezi'nde birleşti.
   Eski inc/nobetci.php artık YÜKLENMİYOR (dosya arşivde duruyor);
   yerini inc/kod-sagligi.php (PHP tarafı) + inc/nobetci-kural.php
   (canlı sayfa taraması) aldı. */
if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
	if ( file_exists( GBC_CORE_DIR . 'inc/kod-sagligi.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/kod-sagligi.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/guvenlik.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/guvenlik.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/hiz.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/hiz.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/seo.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/seo.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/api-merkezi.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/api-merkezi.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/nobetci-kural.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/nobetci-kural.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/seo-envanter.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/seo-envanter.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/gunluk.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/gunluk.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/seo-toplayici.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/seo-toplayici.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/cron.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/cron.php';
	}
	/* 30 Eyl 2026 · v1.37.0: bağlantı raporu + ortaklık hedef çözücü.
	   ortaklik.php'den ÖNCE yüklenir; günlük sağlık taraması hedefi buradan alır. */
	if ( file_exists( GBC_CORE_DIR . 'inc/bag-raporu.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/bag-raporu.php';
	}
	/* 30 Eyl 2026 · v1.38.0: Sayfa Denetimi Google bölümü (dizin, Search Console, GA4). */
	if ( file_exists( GBC_CORE_DIR . 'inc/google-sayfa.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/google-sayfa.php';
	}
	/* 30 Eyl 2026 · v1.48.0: site geneli dizin durumu (Search Console URL Inspection).
	   google-sayfa.php TEK sayfaya bakar; bu dosya BÜTÜN siteye bakar. İkisi de
	   modules/90-komuta.php'deki gbc_km_api() üzerinden gider. */
	if ( file_exists( GBC_CORE_DIR . 'inc/dizin.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/dizin.php';
	}
	/* 30 Eyl 2026 · v1.39.0: kelime evreni ve kapsama (Sayfa Denetimi). */
	if ( file_exists( GBC_CORE_DIR . 'inc/kelime-evreni.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/kelime-evreni.php';
	}
	/* 30 Eyl 2026 · v1.40.0: kanibalizasyon (kelime evrenine bağlı). */
	if ( file_exists( GBC_CORE_DIR . 'inc/kanibal.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/kanibal.php';
	}
	/* 30 Eyl 2026 · v1.41.0: Sayfa Denetimi yeni düzeni, Yapılacaklar kutusu, 7 bölümlü GBC skoru. */
	if ( file_exists( GBC_CORE_DIR . 'inc/sayfa-duzen.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/sayfa-duzen.php';
	}
	/* 30 Eyl 2026 · v1.42.0: kelime fırsatları (Sayfa Denetimi kaydeder, SEO · Fırsatlar toplar). */
	/* 30 Eyl 2026 · v1.45.0: silo ağacı (Sayfa Denetimi + GBC skorunun 8. bölümü). */
	if ( file_exists( GBC_CORE_DIR . 'inc/silo.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/silo.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/firsat-kelime.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/firsat-kelime.php';
	}
	/* 30 Eyl 2026 · v1.43.1: iş kilidi (ağır GBC işleri üst üste binmez) + veritabanı hata sayfası. */
	if ( file_exists( GBC_CORE_DIR . 'inc/kilit.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/kilit.php';
	}
	/* 30 Eyl 2026 · v1.47.0: eklenti denetimi (Kontrol Paneli → Eklentiler). Hiçbir eklentiyi kapatmaz/silmez. */
	if ( file_exists( GBC_CORE_DIR . 'inc/eklentiler.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/eklentiler.php';
	}
	/* 30 Eyl 2026 · v1.44.0: tek çatı — menü 5 gruba iner, Kontrol Paneli özeti. */
	if ( file_exists( GBC_CORE_DIR . 'inc/merkez.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/merkez.php';
	}
	/* 30 Eyl 2026 · v1.43.0: plan (Google iş listesi) ve birleşik Envanter (Toplama dahil). */
	if ( file_exists( GBC_CORE_DIR . 'inc/plan.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/plan.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/envanter-birlesik.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/envanter-birlesik.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/ortaklik.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/ortaklik.php';
	}
	if ( file_exists( GBC_CORE_DIR . 'inc/defter.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/defter.php';
	}
	/* 29 Eyl 2026 · v1.23.0: toplu icerik denetimi. Sayfa Denetimi tek adres
	   olcer; bu modul 1064 yazinin ACF alanlarini sablon normuna gore tarar. */
	if ( file_exists( GBC_CORE_DIR . 'inc/icerik-denetimi.php' ) ) {
		require_once GBC_CORE_DIR . 'inc/icerik-denetimi.php';
	}
}
/* 30 Eyl 2026 · v1.47.3: Sayfa Denetimi kısayolu (düzenleme ekranı kutusu, üst çubuk, liste bağlantısı). Ön yüzde de yüklenir. */
if ( file_exists( GBC_CORE_DIR . 'inc/kml-yerler.php' ) ) { /* v1.47.4: haritadan şema durakları; şema motoru ön yüzde okur. */
	require_once GBC_CORE_DIR . 'inc/kml-yerler.php';
}
if ( file_exists( GBC_CORE_DIR . 'inc/kisayol.php' ) ) {
	require_once GBC_CORE_DIR . 'inc/kisayol.php';
}

/* Uzaktan okuma: GBC'nin kendi olcum sonuclarini MCP uzerinden okunabilir yap.
   Yalniz SONUC secenekleri acilir — ayar ve anahtar iceren secenekler disarida
   birakilir (gbc_hz_ayar PageSpeed anahtarini tutar, o yuzden listede yok).
   Boylece "son tarama ne dedi" sorusu ekran goruntusu istemeden yanitlanir. */
add_filter( 'royal_mcp_readable_options', 'gbc_core_okunabilir_secenekler' );
function gbc_core_okunabilir_secenekler( $liste ) {
	$ekle = array(
		'gbc_hz_son',      /* hiz olcumu   */
		'gbc_hz_psi_test', /* PageSpeed baglanti testi */
		'gbc_kod_son',     /* kod sagligi  */
		'gbc_gv_son',      /* guvenlik     */
		'gbc_core_kapali', /* elle kapatilan moduller */
		'gbc_hz_is',       /* suren olcum isi */
		'gbc_cron_kayit',  /* cron son calisma kayitlari */
		'gbc_cron_son',    /* sunucu cron'unun son gelisi */
		'gbc_sky_son',     /* ucus perdesi devreye girdi mi */
		'gbc_defter',      /* calisma dosyasi */
		'gbc_seo_son',       /* seo olcumu */
		'gbc_seo_ilerleme',  /* toplama ilerlemesi */
		'gbc_sorunlar',      /* acik sorunlar defteri */
		'gbc_gunluk_son',    /* son gunluk kontrol */
		'gbc_nk_durum',      /* nobetci tur durumu */
		'gbc_api_test',      /* api test sonuclari */
		'gbc_api_durum',     /* anahtar dolu mu — SIR ICERMEZ, anahtarin kendisi degil */
		'gbc_ccss_test',     /* kritik CSS tezgahi: test adresleri + uretim durumu */
		'gbc_icerik_durum',  /* toplu icerik denetimi: sablon dagilimi + bulgu sayisi */
		'gbc_icerik_denetim',/* tarama ilerlemesi: ofset, toplam, atlanan */
	);
	return is_array( $liste ) ? array_values( array_unique( array_merge( $liste, $ekle ) ) ) : $ekle;
}

/* Aktivasyon. */
register_activation_hook( __FILE__, 'gbc_core_kurulum' );
function gbc_core_kurulum() {
	add_option( 'gbc_core_kapali', array() );
}
