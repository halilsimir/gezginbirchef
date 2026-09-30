<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30190 — GBC · 05 · Hayalet Cron Temizliği · v2.
   GBC Core'a taşındı, 28 Eylül 2026. Snippet'i WPCode'da pasife al. */

/**
 * GBC · 04 · Hayalet Zamanlanmış Görev Temizliği
 * --------------------------------------------------------------
 * 2 Eylül 2026'da WP-Cron 54 günlük bir arızadan sonra düzeltildi
 * (sunucu cron kaydı yanlış yolu gösteriyordu). Cron çalışmaya
 * başlayınca SİLİNMİŞ eklentilerden kalan zamanlanmış görevler de
 * çalışmaya başladı. Her biri olmayan tablosunu arıyor ve sunucu
 * log'una "table doesn't exist" yazıyor. Hostinger desteğinin
 * "eksik veritabanı tabloları" uyarısının kaynağı buydu.
 * Çekirdek tablolar sağlam, veri kaybı yok.
 *
 * NEDEN BAYRAK KULLANILMIYOR: WordPress tekrarlayan bir görevi
 * ÇALIŞTIRMADAN ÖNCE kendisi yeniden planlıyor. Eklenti silinmiş olsa
 * bile görev kendini sonsuza kadar yeniden kuruyor. "Bir kez çalıştır,
 * tamam bayrağı dik" yaklaşımı bu yüzden yetmedi. Her yönetici sayfası
 * yüklemesinde bakılıyor; maliyeti sıfıra yakın, çünkü
 * wp_next_scheduled bellekteki cron dizisine bakar, veritabanına gitmez.
 *
 * Listeye yalnızca sahibi KESİN OLARAK kurulu olmayan kancalar alındı.
 * Şüphelenilenler (wp_addon_database_migration_cron, puc_cron_check_*,
 * cmsk_*, content-protector-pack/*) BİLEREK DIŞARIDA bırakıldı.
 *
 * Bir eklenti geri kurulursa kendi görevini yeniden ekler; o zaman
 * listeden ilgili satırı çıkar, yoksa görevini sürekli siler.
 * Süzgeçle de değiştirilebilir: add_filter('gbc_hayalet_cron_listesi', …)
 */

/* ZİYARETÇİ TETİKLEMELİ WP-CRON KAPALI — 27 Eylül 2026
   BULGU: sunucuda gerçek cron ZATEN kurulu, 5 dakikada bir /usr/bin/php
   ile wp-cron.php çalıştırıyor. Buna rağmen DISABLE_WP_CRON tanımlı
   olmadığı için WordPress AYNI ZAMANDA ziyaretçi isteklerinde de cron
   kuyruğunu çalıştırıyordu. Ölçüldü: action_scheduler_run_queue dakikada
   bir dönüyor; o kuyruğu tetikleyen ziyaretçi bekliyordu.
   Artık kuyruk yalnız sunucu cronunda döner, ziyaretçi beklemez.
   GERİ ALMA: aşağıdaki iki satırı sil. */
if ( ! defined( 'DISABLE_WP_CRON' ) ) { define( 'DISABLE_WP_CRON', true ); }
remove_action( 'init', 'wp_cron' );

if ( ! function_exists( 'gbc_hayalet_cron_listesi' ) ) {
	function gbc_hayalet_cron_listesi() {
		return (array) apply_filters( 'gbc_hayalet_cron_listesi', array(
			'tribe_daily_cron',                         // The Events Calendar
			'tribe_common_log_cleanup',                 // The Events Calendar
			'tec_tickets_seating_tables_cron',          // Event Tickets
			'wordfence_ls_role_sync_cron',              // Wordfence Login Security
			'sucuriscan_scheduled_scan',                // Sucuri Security
			'imagify_sync_files',                       // Imagify
			'wpseo-reindex-links',                      // Yoast SEO (Rank Math kullanılıyor)
			'wpseo_onpage_fetch',                       // Yoast SEO
			'glossary_terms_counter',                   // Glossary
			'bs-booster/minify/clear-cache',            // BetterStudio Booster
			'better-framework/oculus/check-update/init', // BetterStudio Framework
			'yotuwp_weekly_scheduled_events',           // YotuWP
		) );
	}
}

if ( ! function_exists( 'gbc_hayalet_cron_bul' ) ) {
	/** Şu an zamanlanmış durumdaki hayalet kancalar. Hiçbir şey silmez. */
	function gbc_hayalet_cron_bul() {
		$bulunan = array();
		foreach ( gbc_hayalet_cron_listesi() as $kanca ) {
			$sonraki = wp_next_scheduled( $kanca );
			if ( $sonraki ) { $bulunan[ $kanca ] = (int) $sonraki; }
		}
		return $bulunan;
	}
}

if ( ! function_exists( 'gbc_hayalet_cron_temizle' ) ) {
	function gbc_hayalet_cron_temizle() {

		if ( ! current_user_can( 'manage_options' ) ) { return array(); }

		$silinen = array();
		foreach ( array_keys( gbc_hayalet_cron_bul() ) as $kanca ) {
			$adet = wp_unschedule_hook( $kanca );
			if ( $adet ) { $silinen[] = $kanca . ' (' . (int) $adet . ')'; }
		}

		if ( $silinen ) {
			set_transient( 'gbc_hayalet_cron_rapor', $silinen, 300 );
			$gecmis = get_option( 'gbc_hayalet_cron_gecmis', array() );
			if ( ! is_array( $gecmis ) ) { $gecmis = array(); }
			array_unshift( $gecmis, array( 'zaman' => time(), 'kanca' => $silinen ) );
			update_option( 'gbc_hayalet_cron_gecmis', array_slice( $gecmis, 0, 10 ), false );
		}
		return $silinen;
	}
	add_action( 'admin_init', 'gbc_hayalet_cron_temizle' );
}

if ( ! function_exists( 'gbc_hayalet_cron_notu' ) ) {
	function gbc_hayalet_cron_notu() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$rapor = get_transient( 'gbc_hayalet_cron_rapor' );
		if ( ! is_array( $rapor ) || ! $rapor ) { return; }
		delete_transient( 'gbc_hayalet_cron_rapor' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>'
			. esc_html__( 'GBC · Hayalet cron temizliği:', 'gbc-core' ) . '</strong> '
			. esc_html( sprintf(
				/* translators: %s: kanca listesi */
				__( '%1$d kanca zamanlamadan çıkarıldı: %2$s', 'gbc-core' ),
				count( $rapor ), implode( ' · ', $rapor )
			) ) . '</p></div>';
	}
	add_action( 'admin_notices', 'gbc_hayalet_cron_notu' );
}
