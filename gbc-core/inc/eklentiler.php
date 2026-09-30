<?php
/**
 * GBC Core · Eklenti denetimi — v1.47.0, 30 Eylül 2026
 * ------------------------------------------------------------
 * Halil: "Pluginler çalışmıyorsa, aktif değilse, yavaşlatan bir şey varsa silebiliriz;
 * denetimini de denetim sayfasına koy."
 *
 * Kontrol Paneli → Eklentiler sekmesi. Her eklenti için ÖLÇÜLEN:
 *   · durum (ağda etkin / etkin / kapalı), sürüm, bekleyen güncelleme
 *   · her sayfa açılışında yüklenen ayar boyutu (autoload) — sitenin her isteğine maliyet
 *   · veritabanı tabloları ve boyutu; hiçbir eklentiye ait olmayan SAHİPSİZ tablolar
 *   · zamanlanmış işleri
 *   · ziyaretçi sayfasına yüklediği CSS/JS dosyaları (ana sayfa + bir gezi sayfası, önbellekli hâl)
 *   · WordPress'e bağladığı kanca sayısı (yönetim panelinde ölçülür)
 * ve bu site için bilinen görevi (neden var, kaldırılırsa ne bozulur).
 *
 * HİÇBİR EKLENTİYİ KAPATMAZ, SİLMEZ. Öneri yazar; karar ve işlem Halil'in.
 * Ölçüm 12 saat saklanır; ziyaretçi sayfası iki istekle, önbellekli hâliyle okunur.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bu sitede bilinen görevler. klasör => array( rol, karar, not ). karar: gerekli | kullaniliyor | incele | tasinabilir | aday */
function gbc_ek_bilgi() {
	return array(
		'gbc-core'                  => array( 'Sitenin kendi motoru', 'gerekli', 'Şablon motorları, SEO denetimi, Kontrol Paneli.' ),
		'advanced-custom-fields'    => array( 'İçerik alanları', 'gerekli', 'Sayfaların asıl içeriği ACF alanlarında; kaldırılırsa sayfalar boşalır.' ),
		'insert-headers-and-footers'=> array( 'WPCode: şablonlar', 'gerekli', 'Gezi, Liste, Detay, Tarif… şablonlarının kodu burada (50+ etkin kod parçası).' ),
		'astra-addon'               => array( 'Astra Pro', 'gerekli', 'Tema özellikleri ve Site Builder.' ),
		'litespeed-cache'           => array( 'Önbellek ve hız', 'gerekli', 'Sayfa önbelleği, CSS/JS birleştirme, görsel optimizasyonu.' ),
		'seo-by-rank-math'          => array( 'SEO', 'gerekli', 'Başlık/meta, site haritası, Search Console verisi (Envanter bunu okur).' ),
		'loginizer'                 => array( 'Giriş koruması', 'gerekli', 'Kaba kuvvet denemelerini engeller.' ),
		'hostinger'                 => array( 'Hosting aracı', 'kullaniliyor', 'Hostinger yönetimi. Otomatik güncellemeler ayrı (mu-plugin).' ),
		'integromat-connector'      => array( 'Make bağlantısı', 'kullaniliyor', 'Make senaryoları (Pinterest, YouTube, Sheets) siteye buradan yazıyor.' ),
		'easy-mcp-ai'               => array( 'Claude bağlantısı (MCP)', 'kullaniliyor', 'Claude\'un siteyi okuyup yazdığı bağlantı.' ),
		'royal-mcp'                 => array( 'İkinci Claude bağlantısı (MCP)', 'incele', 'Easy MCP ile aynı işi yapıyor. Hangisinin kullanıldığı test edilmeden kaldırılmaz.' ),
		'ai-provider-for-anthropic' => array( 'WordPress yapay zekâ sağlayıcısı', 'incele', 'WordPress\'in yerleşik yapay zekâ istemcisi için. Sitede bunu kullanan bir özellik görünmüyor.' ),
		'disable-feeds-wp'          => array( 'RSS kapatma', 'tasinabilir', 'Tek işi RSS beslemelerini kapatmak; GBC Core birkaç satırla yapabilir, eklenti sayısı düşer.' ),
		'wp-consent-api'            => array( 'Çerez izni altyapısı', 'aday', 'Onu kullanan bir çerez/izin eklentisi sitede yok; tek başına iş yapmaz.' ),
	);
}

function gbc_ek_klasor( $dosya ) { $p = explode( '/', (string) $dosya ); return $p[0]; }

/** Tablo/ayar/iş adını eklenti klasörüne eşleyen işaretler. */
function gbc_ek_isaretler() {
	return array(
		'seo-by-rank-math'           => array( 'rank_math', 'rank-math', 'rankmath' ),
		'litespeed-cache'            => array( 'litespeed', 'lscwp' ),
		'loginizer'                  => array( 'loginizer', 'lz_' ),
		'insert-headers-and-footers' => array( 'wpcode', 'ihaf', 'insert_headers' ),
		'advanced-custom-fields'     => array( 'acf' ),
		'astra-addon'                => array( 'astra', 'ast_', 'bsf' ),
		'hostinger'                  => array( 'hostinger', 'hts_' ),
		'integromat-connector'       => array( 'integromat', 'iwc_' ),
		'easy-mcp-ai'                => array( 'easy_mcp', 'easymcp', 'emcp' ),
		'royal-mcp'                  => array( 'royal_mcp', 'royalmcp' ),
		'ai-provider-for-anthropic'  => array( 'anthropic' ),
		'disable-feeds-wp'           => array( 'disable_feeds' ),
		'wp-consent-api'             => array( 'consent_api', 'wp_consent' ),
		'gbc-core'                   => array( 'gbc_', 'gbc-' ),
	);
}
function gbc_ek_sahip( $ad ) {
	$ad = strtolower( (string) $ad );
	foreach ( gbc_ek_isaretler() as $k => $l ) { foreach ( $l as $i ) { if ( 0 === strpos( ltrim( $ad, '_' ), $i ) || false !== strpos( $ad, $i . '_' ) ) { return $k; } } }
	return '';
}

/** Ağır olmayan ölçümler (12 saat saklanır). */
function gbc_ek_olc( $taze = false ) {
	$c = get_transient( 'gbc_ek_olcum' );
	if ( ! $taze && is_array( $c ) ) { return $c; }
	global $wpdb;
	$o = array( 't' => time(), 'autoload' => array(), 'autoload_top' => 0, 'tablo' => array(), 'sahipsiz_tablo' => array(), 'is' => array(), 'on_yuz' => array(), 'on_yuz_sayfa' => array() );

	/* Her istekte yüklenen ayarlar */
	foreach ( (array) $wpdb->get_results( "SELECT option_name n, LENGTH(option_value) b FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')", ARRAY_A ) as $r ) {
		$o['autoload_top'] += (int) $r['b'];
		$k = gbc_ek_sahip( $r['n'] );
		if ( '' === $k ) { $k = '_wp'; }
		$o['autoload'][ $k ] = ( isset( $o['autoload'][ $k ] ) ? $o['autoload'][ $k ] : 0 ) + (int) $r['b'];
	}

	/* Tablolar (bu sitenin öneki) */
	$cekirdek = array( 'posts', 'postmeta', 'options', 'users', 'usermeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'comments', 'commentmeta', 'links',
		'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'blog_versions' );
	foreach ( (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT TABLE_NAME n, (DATA_LENGTH + INDEX_LENGTH) b, TABLE_ROWS r FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s",
		DB_NAME, $wpdb->esc_like( $wpdb->base_prefix ) . '%' ), ARRAY_A ) as $r ) {
		$kisa = substr( $r['n'], strlen( $wpdb->base_prefix ) );
		if ( in_array( $kisa, $cekirdek, true ) ) { continue; }
		$k = gbc_ek_sahip( $kisa );
		if ( '' === $k && 0 === strpos( $kisa, 'actionscheduler' ) ) { $k = 'seo-by-rank-math'; } /* Rank Math iş kuyruğu */
		$satir = array( 'ad' => $r['n'], 'b' => (int) $r['b'], 'r' => (int) $r['r'] );
		if ( '' === $k ) { $o['sahipsiz_tablo'][] = $satir; } else { $o['tablo'][ $k ][] = $satir; }
	}

	/* Zamanlanmış işler */
	foreach ( (array) _get_cron_array() as $zaman => $kancalar ) {
		foreach ( (array) $kancalar as $h => $x ) {
			$k = gbc_ek_sahip( $h );
			if ( '' === $k ) { continue; }
			$o['is'][ $k ][ $h ] = true;
		}
	}

	/* Ziyaretçi sayfası: ana sayfa + bir gezi sayfası, önbellekli hâl */
	$sayfalar = array( home_url( '/' ) );
	$gezi = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE '%[wpcode id=\"22607\"%' ORDER BY post_modified DESC LIMIT 1" );
	if ( $gezi ) { $sayfalar[] = get_permalink( $gezi ); }
	foreach ( $sayfalar as $u ) {
		$c = wp_remote_get( $u, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => 'Mozilla/5.0 GBC-Eklenti-Denetimi' ) ) );
		if ( is_wp_error( $c ) ) { continue; }
		$o['on_yuz_sayfa'][] = $u;
		if ( preg_match_all( '~(?:src|href)=["\']([^"\']*/wp-content/plugins/([a-z0-9_\-]+)/[^"\']*)~i', (string) wp_remote_retrieve_body( $c ), $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) { $o['on_yuz'][ strtolower( $x[2] ) ][ strtok( $x[1], '?' ) ] = true; }
		}
	}
	set_transient( 'gbc_ek_olcum', $o, 12 * HOUR_IN_SECONDS );
	return $o;
}

/** Yönetim panelinde WordPress'e bağlanan kanca sayısı (eklenti klasörüne göre). */
function gbc_ek_kancalar() {
	global $wp_filter;
	$o = array(); $kok = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
	foreach ( (array) $wp_filter as $h => $nesne ) {
		if ( ! is_object( $nesne ) || empty( $nesne->callbacks ) ) { continue; }
		foreach ( $nesne->callbacks as $oncelik => $liste ) {
			foreach ( $liste as $cb ) {
				$f = $cb['function']; $dosya = '';
				try {
					if ( is_string( $f ) && function_exists( $f ) ) { $dosya = ( new ReflectionFunction( $f ) )->getFileName(); }
					elseif ( $f instanceof Closure ) { $dosya = ( new ReflectionFunction( $f ) )->getFileName(); }
					elseif ( is_array( $f ) && isset( $f[0], $f[1] ) && method_exists( $f[0], $f[1] ) ) { $dosya = ( new ReflectionMethod( $f[0], $f[1] ) )->getFileName(); }
				} catch ( \Throwable $e ) { $dosya = ''; }
				$dosya = wp_normalize_path( (string) $dosya );
				if ( 0 !== strpos( $dosya, $kok ) ) { continue; }
				$k = strtok( substr( $dosya, strlen( $kok ) ), '/' );
				$o[ $k ] = ( isset( $o[ $k ] ) ? $o[ $k ] : 0 ) + 1;
			}
		}
	}
	return $o;
}

function gbc_ek_boyut( $b ) {
	if ( $b >= 1048576 ) { return number_format_i18n( $b / 1048576, 1 ) . ' MB'; }
	return number_format_i18n( max( 0, $b ) / 1024, 0 ) . ' KB';
}

function gbc_ek_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	$taze = isset( $_GET['taze'] ) && check_admin_referer( 'gbc_ek_taze' );
	$olc  = gbc_ek_olc( $taze );
	$kanca = gbc_ek_kancalar();
	$bilgi = gbc_ek_bilgi();
	$gunc = get_site_transient( 'update_plugins' );
	$gunc = is_object( $gunc ) && ! empty( $gunc->response ) ? (array) $gunc->response : array();

	echo '<div class="wrap"><h1>GBC · Eklentiler</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-eklentiler' ); }
	echo '<p style="max-width:980px;color:#3C4149;margin-top:0">Her eklentinin siteye maliyeti ölçülür ve bu sitedeki görevi yazılır. <strong>Bu ekran hiçbir eklentiyi kapatmaz ya da silmez</strong>; öneri verir, karar senin. '
		. 'Ölçüm ' . esc_html( human_time_diff( (int) $olc['t'] ) ) . ' önce · <a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-eklentiler&taze=1' ), 'gbc_ek_taze' ) ) . '">yeniden ölç</a></p>';

	$ek = get_plugins();
	$rozet = array(
		'gerekli'      => array( 'Gerekli', '#1A7F37', '#E8F3EC' ),
		'kullaniliyor' => array( 'Kullanılıyor', '#1B4E9B', '#E8EEF8' ),
		'incele'       => array( 'İncele', '#8A6100', '#FCF6E8' ),
		'tasinabilir'  => array( 'GBC Core\'a taşınabilir', '#6B3FA0', '#F1EAF9' ),
		'aday'         => array( 'Kaldırma adayı', '#B32D2E', '#FBE7E7' ),
		'kapali'       => array( 'Kapalı: silinebilir', '#B32D2E', '#FBE7E7' ),
		'bilinmiyor'   => array( 'Bilinmiyor', '#5C6470', '#F0F0F1' ),
	);
	$satirlar = array(); $sayac = array();
	foreach ( $ek as $dosya => $v ) {
		$k = gbc_ek_klasor( $dosya );
		$ag = is_multisite() && is_plugin_active_for_network( $dosya );
		$etkin = $ag || is_plugin_active( $dosya );
		$b = isset( $bilgi[ $k ] ) ? $bilgi[ $k ] : array( '—', 'bilinmiyor', 'Bu sitedeki görevi kayıtlı değil.' );
		$karar = $etkin ? $b[1] : 'kapali';
		$sayac[ $karar ] = ( isset( $sayac[ $karar ] ) ? $sayac[ $karar ] : 0 ) + 1;
		$tb = 0; $tr = 0; foreach ( isset( $olc['tablo'][ $k ] ) ? $olc['tablo'][ $k ] : array() as $t ) { $tb += $t['b']; $tr++; }
		$satirlar[] = array( 'k' => $k, 'ad' => wp_strip_all_tags( $v['Name'] ), 'surum' => $v['Version'], 'etkin' => $etkin, 'ag' => $ag, 'rol' => $b[0], 'karar' => $karar, 'not' => $b[2],
			'gunc' => isset( $gunc[ $dosya ] ) ? ( is_object( $gunc[ $dosya ] ) ? $gunc[ $dosya ]->new_version : '' ) : '',
			'auto' => isset( $olc['autoload'][ $k ] ) ? $olc['autoload'][ $k ] : 0, 'tablo_b' => $tb, 'tablo_n' => $tr,
			'is' => isset( $olc['is'][ $k ] ) ? count( $olc['is'][ $k ] ) : 0,
			'on' => isset( $olc['on_yuz'][ $k ] ) ? count( $olc['on_yuz'][ $k ] ) : 0,
			'kanca' => isset( $kanca[ $k ] ) ? $kanca[ $k ] : 0 );
	}
	$sira = array( 'kapali' => 0, 'aday' => 1, 'tasinabilir' => 2, 'incele' => 3, 'bilinmiyor' => 4, 'kullaniliyor' => 5, 'gerekli' => 6 );
	usort( $satirlar, static function ( $a, $b ) use ( $sira ) { return $sira[ $a['karar'] ] - $sira[ $b['karar'] ] ?: strcmp( $a['ad'], $b['ad'] ); } );

	/* Özet kartları */
	$kart = static function ( $b, $d, $alt, $r ) { return '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:14px 18px;min-width:0"><div style="font-weight:600;color:#3C4149;font-size:13px">' . esc_html( $b ) . '</div><div style="font-size:26px;font-weight:700;color:' . esc_attr( $r ) . '">' . esc_html( $d ) . '</div><div style="color:#5C6470;font-size:12.5px">' . esc_html( $alt ) . '</div></div>'; };
	$aday = ( isset( $sayac['aday'] ) ? $sayac['aday'] : 0 ) + ( isset( $sayac['kapali'] ) ? $sayac['kapali'] : 0 ) + ( isset( $sayac['tasinabilir'] ) ? $sayac['tasinabilir'] : 0 );
	$sb = 0; foreach ( $olc['sahipsiz_tablo'] as $t ) { $sb += $t['b']; }
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:12px 0;max-width:1400px">';
	echo $kart( 'Eklenti', (string) count( $ek ), count( array_filter( $satirlar, static function ( $x ) { return $x['etkin']; } ) ) . ' etkin', '#14181F' );
	echo $kart( 'Kaldırılabilir / taşınabilir', (string) $aday, 'aşağıda kırmızı ve mor satırlar', $aday ? '#B32D2E' : '#1A7F37' );
	echo $kart( 'İncelenecek', (string) ( isset( $sayac['incele'] ) ? $sayac['incele'] : 0 ), 'görevi belirsiz', '#8A6100' );
	echo $kart( 'Güncelleme bekleyen', (string) count( array_filter( $satirlar, static function ( $x ) { return '' !== $x['gunc']; } ) ), 'Hostinger otomatik günceller', '#14181F' );
	echo $kart( 'Her açılışta yüklenen ayar', gbc_ek_boyut( $olc['autoload_top'] ), 'bütün eklentiler + WordPress', $olc['autoload_top'] > 900 * 1024 ? '#B32D2E' : '#1A7F37' );
	echo $kart( 'Sahipsiz tablo', (string) count( $olc['sahipsiz_tablo'] ), $olc['sahipsiz_tablo'] ? gbc_ek_boyut( $sb ) . ' · silinmiş eklentiden kalma olabilir' : 'yok', $olc['sahipsiz_tablo'] ? '#8A6100' : '#1A7F37' );
	echo '</div>';

	/* Tablo */
	echo '<div style="overflow-x:auto;max-width:1400px"><table class="widefat striped"><thead><tr><th>Eklenti</th><th style="width:150px">Karar</th><th style="width:95px">Her açılışta</th><th style="width:100px">Veritabanı</th><th style="width:80px">Ziyaretçi sayfası</th><th style="width:70px">Kanca</th><th style="width:60px">İş</th></tr></thead><tbody>';
	foreach ( $satirlar as $s ) {
		$r = $rozet[ $s['karar'] ];
		echo '<tr><td><strong>' . esc_html( $s['ad'] ) . '</strong> <span style="color:#5C6470;font-size:12px">' . esc_html( $s['surum'] ) . ( $s['ag'] ? ' · ağda etkin' : ( $s['etkin'] ? ' · etkin' : ' · KAPALI' ) ) . '</span>'
			. ( '' !== $s['gunc'] ? ' <span style="color:#8A6100;font-size:12px">· güncelleme ' . esc_html( $s['gunc'] ) . '</span>' : '' )
			. '<div style="font-size:12.5px;color:#3C4149"><strong>' . esc_html( $s['rol'] ) . '.</strong> ' . esc_html( $s['not'] ) . '</div></td>'
			. '<td><span style="display:inline-block;padding:2px 9px;border-radius:10px;font-size:12px;font-weight:600;color:' . esc_attr( $r[1] ) . ';background:' . esc_attr( $r[2] ) . '">' . esc_html( $r[0] ) . '</span></td>'
			. '<td>' . ( $s['auto'] ? esc_html( gbc_ek_boyut( $s['auto'] ) ) : '—' ) . '</td>'
			. '<td>' . ( $s['tablo_n'] ? esc_html( $s['tablo_n'] . ' tablo · ' . gbc_ek_boyut( $s['tablo_b'] ) ) : '—' ) . '</td>'
			. '<td>' . ( $s['on'] ? (int) $s['on'] . ' dosya' : '—' ) . '</td>'
			. '<td>' . ( $s['kanca'] ? (int) $s['kanca'] : '—' ) . '</td>'
			. '<td>' . ( $s['is'] ? (int) $s['is'] : '—' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	echo '<p style="color:#5C6470;font-size:12.5px;max-width:1100px">Her açılışta: her sayfa isteğinde veritabanından okunan ayar (autoload). Ziyaretçi sayfası: ana sayfa ve bir gezi sayfasının önbellekli hâlinde bu eklentinin klasöründen gelen CSS/JS dosyası (LiteSpeed birleştirdiği dosyaları kendi adıyla yazar, burada görünmez). Kanca: yönetim panelinde WordPress\'e bağladığı işlev sayısı; yükün kaba göstergesi. İş: zamanlanmış görev sayısı.</p>';

	if ( $olc['sahipsiz_tablo'] ) {
		usort( $olc['sahipsiz_tablo'], static function ( $a, $b ) { return $b['b'] - $a['b']; } );
		echo '<h2 style="margin-top:22px">Sahipsiz tablolar</h2><p style="color:#5C6470;max-width:1000px">Adı hiçbir kurulu eklentiyle eşleşmeyen tablolar. Çoğu zaman silinmiş bir eklentiden kalır ve yer kaplar. Silmeden önce yedek alınır; adı tanıdık değilse sor.</p>';
		echo '<div style="overflow-x:auto;max-width:900px"><table class="widefat striped"><thead><tr><th>Tablo</th><th style="width:110px">Boyut</th><th style="width:110px">Satır</th></tr></thead><tbody>';
		foreach ( array_slice( $olc['sahipsiz_tablo'], 0, 40 ) as $t ) { echo '<tr><td><code>' . esc_html( $t['ad'] ) . '</code></td><td>' . esc_html( gbc_ek_boyut( $t['b'] ) ) . '</td><td>' . esc_html( number_format_i18n( $t['r'] ) ) . '</td></tr>'; }
		echo '</tbody></table></div>';
	}
	echo '</div>';
}
