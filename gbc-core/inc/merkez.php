<?php
/**
 * GBC Core · Tek çatı: menü grupları ve Kontrol Paneli — v1.44.0, 30 Eylül 2026
 * ------------------------------------------------------------
 * Halil: "Her seferinde ona git buna git uğraşmayayım; tek ekrana bakayım."
 * Menü 16 maddeden 5'e iner:
 *   Kontrol Paneli · SEO · İş Ortaklığı · Bağlantılar · Çalışma Dosyası
 * Eski ekranlar SİLİNMEDİ: her biri kendi grubunda bir sekme. Eski adresler
 * (admin.php?page=gbc-hiz gibi) çalışmaya devam eder; menüde görünmezler ama
 * açılınca grubun sekmeleriyle açılır. Formlar ve düğmeler aynı yere gider.
 *
 * Kontrol Paneli / Özet: bütün kontrollerin son sonucu tek sayfada, tek düğme
 * ("Hepsini şimdi kontrol et": işi arka plana sıraya koyar, sunucuyu yormaz)
 * ve bütün zamanlanmış işlerin saatleri.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function gbc_merkez_gruplar() {
	return array(
		'kontrol' => array( 'ad' => 'Kontrol Paneli', 'ana' => 'gbc', 'sekme' => array(
			'gbc'               => 'Özet',
			'gbc-gunluk'        => 'Sorunlar',
			'gbc-nobetci-kural' => 'Sayfa taraması',
			'gbc-icerik'        => 'İçerik alanları',
			'gbc-guvenlik'      => 'Güvenlik',
			'gbc-hiz'           => 'Hız',
			'gbc-cron'          => 'Zamanlama',
			'gbc-eklentiler'    => 'Eklentiler',
			'gbc-moduller'      => 'Modüller',
		) ),
		'seo' => array( 'ad' => 'SEO', 'ana' => 'gbc-seo-envanter', 'sekme' => array(
			'gbc-seo-envanter' => 'Envanter',
			'gbc-seo-sayfa'    => 'Sayfa Denetimi',
			'gbc-dizin'        => 'Dizin Durumu',
			'gbc-seo-firsat'   => 'Fırsatlar',
			'gbc-seo'          => 'Search Console',
		) ),
		'ortaklik' => array( 'ad' => 'İş Ortaklığı', 'ana' => 'gbc-ortaklik', 'sekme' => array( 'gbc-ortaklik' => 'İş Ortaklığı' ) ),
		'baglanti' => array( 'ad' => 'Bağlantılar', 'ana' => 'gbc-api', 'sekme' => array(
			'gbc-api'    => 'API anahtarları',
			'gbc-kaynak' => 'Veri kaynakları',
		) ),
		'defter' => array( 'ad' => 'Çalışma Dosyası', 'ana' => 'gbc-defter', 'sekme' => array( 'gbc-defter' => 'Çalışma Dosyası' ) ),
	);
}

/** Bir sayfa hangi grupta. Toplama eski adresi SEO'ya düşer. */
function gbc_merkez_grup_bul( $slug ) {
	if ( 'gbc-seo-toplama' === $slug ) { $slug = 'gbc-seo-envanter'; }
	foreach ( gbc_merkez_gruplar() as $g => $x ) { if ( isset( $x['sekme'][ $slug ] ) ) { return array( $g, $slug ); } }
	return array( '', $slug );
}

/** Ekranların tepesi: grup düğmeleri + o grubun sekmeleri. gbc_tasarim_yol() bunu çağırır. */
function gbc_merkez_ust( $aktif = '' ) {
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( '' === $aktif ) { $aktif = $sayfa; }
	list( $grup, $aktif ) = gbc_merkez_grup_bul( $aktif );
	if ( '' === $grup ) { list( $grup, $aktif ) = gbc_merkez_grup_bul( $sayfa ); }
	$gr = gbc_merkez_gruplar();

	echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px">';
	foreach ( $gr as $g => $x ) {
		$bu = ( $g === $grup );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $x['ana'] ) ) . '" style="text-decoration:none;font-size:13.5px;font-weight:' . ( $bu ? '700' : '500' )
			. ';padding:7px 15px;border-radius:999px;' . ( $bu ? 'background:#17181A;color:#fff' : 'background:#fff;color:#3C4149;border:1px solid #E6E2DA' ) . '">' . esc_html( $x['ad'] ) . '</a>';
	}
	echo '</div>';
	if ( '' !== $grup && count( $gr[ $grup ]['sekme'] ) > 1 ) {
		echo '<div style="display:flex;flex-wrap:wrap;gap:2px 14px;border-bottom:1px solid #DCDCDE;margin:0 0 18px;max-width:1400px">';
		foreach ( $gr[ $grup ]['sekme'] as $s => $ad ) {
			$bu = ( $s === $aktif );
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $s ) ) . '" style="text-decoration:none;font-size:13.5px;padding:8px 2px;margin-bottom:-1px;'
				. ( $bu ? 'color:#17181A;font-weight:700;border-bottom:2px solid #17181A' : 'color:#50575E;border-bottom:2px solid transparent' ) . '">' . esc_html( $ad ) . '</a>';
		}
		echo '</div>';
	} else {
		echo '<div style="height:8px"></div>';
	}
}

/* ---------------- Menü: yalnız grup başları görünür ----------------
   v1.45.1: Sekmeler menüden ÇIKARILMAZ, yalnız gizlenir. Çıkarılınca WordPress sayfanın
   üst menüsünü bulamıyor, izin kaydını tanımıyor ve "Üzgünüz, bu sayfaya erişmenize izin
   verilmiyor" diyordu (1.44.0–1.45.0'da sekmeler böyle açılmadı). Şimdi kayıt yerinde duruyor;
   menü satırına "gbc-gizli" sınıfı verilip CSS ile gizleniyor. */
add_action( 'admin_menu', 'gbc_merkez_menu_sadelestir', 999 );
function gbc_merkez_menu_sadelestir() {
	$gorunen = array(); $sira = array();
	foreach ( gbc_merkez_gruplar() as $x ) { $gorunen[ $x['ana'] ] = $x['ad']; $sira[] = $x['ana']; }
	global $submenu;
	if ( empty( $submenu['gbc'] ) ) { return; }
	foreach ( $submenu['gbc'] as $i => $m ) {
		if ( isset( $gorunen[ $m[2] ] ) ) {
			$submenu['gbc'][ $i ][0] = $gorunen[ $m[2] ];
		} else {
			$submenu['gbc'][ $i ][4] = trim( ( isset( $m[4] ) ? $m[4] . ' ' : '' ) . 'gbc-gizli' );
		}
	}
	/* Sıra: görünenler grup sırasıyla önde, gizliler arkada (ilk satır 'gbc' kalır). */
	usort( $submenu['gbc'], static function ( $a, $b ) use ( $sira ) {
		$x = array_search( $a[2], $sira, true ); $y = array_search( $b[2], $sira, true );
		$x = false === $x ? 99 : $x; $y = false === $y ? 99 : $y;
		return $x - $y;
	} );
}
add_action( 'admin_head', static function () {
	echo '<style>#adminmenu .wp-submenu li.gbc-gizli{display:none!important}</style>';
} );
/* Gizli sekmedeyken menüde doğru grup parlasın. */
add_filter( 'submenu_file', static function ( $f ) {
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 0 !== strpos( $sayfa, 'gbc' ) ) { return $f; }
	list( $grup ) = gbc_merkez_grup_bul( $sayfa );
	$gr = gbc_merkez_gruplar();
	return '' !== $grup ? $gr[ $grup ]['ana'] : $f;
} );

/* ---------------- "Hepsini şimdi kontrol et": arka plana sıraya koy ---------------- */
add_action( 'admin_post_gbc_kp_kontrol', static function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }
	check_admin_referer( 'gbc_kp_kontrol' );
	if ( ! wp_next_scheduled( 'gbc_gunluk_kontrol', array( 'elle' ) ) ) {
		wp_schedule_single_event( time(), 'gbc_gunluk_kontrol', array( 'elle' ) );
	}
	update_option( 'gbc_kp_istek', time(), false );
	wp_safe_redirect( admin_url( 'admin.php?page=gbc&kp=sirada' ) );
	exit;
} );

/* ---------------- Kontrol Paneli · Özet ---------------- */
function gbc_kp_kart( $baslik, $deger, $alt, $renk, $href ) {
	return '<a href="' . esc_url( $href ) . '" style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:14px 18px;display:block;text-decoration:none;color:inherit;min-width:0">'
		. '<div style="font-weight:600;color:#3C4149;font-size:13px">' . esc_html( $baslik ) . '</div>'
		. '<div style="font-size:26px;font-weight:700;color:' . esc_attr( $renk ) . ';line-height:1.3">' . esc_html( $deger ) . '</div>'
		. '<div style="color:#5C6470;font-size:12.5px">' . esc_html( $alt ) . '</div></a>';
}
function gbc_kp_once( $t ) { return $t ? human_time_diff( (int) $t ) . ' önce' : 'hiç'; }

function gbc_kp_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$ye = '#1A7F37'; $sa = '#8A6100'; $ki = '#B32D2E'; $gr = '#5C6470';

	echo '<div class="wrap"><h1>GBC · Kontrol Paneli</h1>';
	gbc_merkez_ust( 'gbc' );
	echo '<style>.gbc-kp-kart{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:14px 0;max-width:1400px}'
		. '@media (max-width:600px){.gbc-kp-kart{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gbc-kp-kart>a{padding:12px 14px!important}}'
		. '.gbc-kp-kutu{background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;margin:14px 0;max-width:1400px}'
		. '.gbc-kp-ikili{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:16px;max-width:1400px;align-items:start}@media (max-width:1100px){.gbc-kp-ikili{grid-template-columns:1fr}}'
		. '.gbc-kp-tablo{overflow-x:auto}</style>';

	/* Veriler — hepsi kayıtlı sonuçtan okunur, bu ekran ölçüm yapmaz. */
	$sorun = function_exists( 'gbc_sorunlar' ) ? gbc_sorunlar() : array();
	$yuksek = 0; foreach ( $sorun as $x ) { if ( 'yuksek' === $x['onem'] ) { $yuksek++; } }
	$gun = get_option( 'gbc_gunluk_son', array() );
	$istek = (int) get_option( 'gbc_kp_istek', 0 );
	$sirada = wp_next_scheduled( 'gbc_gunluk_kontrol', array( 'elle' ) );
	$kilit = function_exists( 'gbc_kilit_kimde' ) ? gbc_kilit_kimde() : null;

	/* Genel durum şeridi */
	$genel = $yuksek ? array( 'Sorun var', $ki, '#FBE7E7' ) : ( $sorun ? array( 'Dikkat', $sa, '#FCF6E8' ) : array( 'Sağlıklı', $ye, '#E8F3EC' ) );
	echo '<div class="gbc-kp-kutu" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;background:' . esc_attr( $genel[2] ) . ';border-color:' . esc_attr( $genel[2] ) . '">'
		. '<div style="font-size:24px;font-weight:800;color:' . esc_attr( $genel[1] ) . '">' . esc_html( $genel[0] ) . '</div>'
		. '<div style="flex:1;min-width:220px;color:#3C4149">' . esc_html( count( $sorun ) . ' açık sorun' . ( $yuksek ? ', ' . $yuksek . ' tanesi yüksek öncelikli' : '' ) . ' · son tam kontrol ' . gbc_kp_once( isset( $gun['zaman'] ) ? $gun['zaman'] : 0 ) ) . '</div>';
	if ( $sirada || isset( $_GET['kp'] ) ) {
		echo '<div style="color:#3C4149;font-weight:600">⏳ Kontrol sırada. Arka planda birkaç dakika içinde başlar; bu sayfayı sonra yenile.</div>';
	} elseif ( $kilit ) {
		echo '<div style="color:#3C4149;font-weight:600">⚙️ Şu an çalışan iş: ' . esc_html( $kilit['is'] ) . '</div>';
	} else {
		echo '<a class="button button-primary button-hero" style="font-size:15px" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gbc_kp_kontrol' ), 'gbc_kp_kontrol' ) ) . '">Hepsini şimdi kontrol et</a>';
	}
	echo '</div>';
	unset( $istek );

	/* Kartlar */
	echo '<div class="gbc-kp-kart">';
	echo gbc_kp_kart( 'Açık sorun', (string) count( $sorun ), $yuksek . ' yüksek öncelikli', $yuksek ? $ki : ( $sorun ? $sa : $ye ), admin_url( 'admin.php?page=gbc-gunluk' ) );

	$nk_t = function_exists( 'gbc_nk_son_tur' ) ? gbc_nk_son_tur() : 0;
	echo gbc_kp_kart( 'Sayfa taraması', $nk_t ? gbc_kp_once( $nk_t ) : '—', 'motorlar sayfada mı · saat başı', $nk_t && ( time() - $nk_t ) < 3 * HOUR_IN_SECONDS ? $ye : $sa, admin_url( 'admin.php?page=gbc-nobetci-kural' ) );

	$gv = get_option( 'gbc_gv_son', array() );
	$gvs = isset( $gv['skor'] ) ? (int) $gv['skor'] : null;
	echo gbc_kp_kart( 'Güvenlik', null === $gvs ? '—' : $gvs . '/100', 'son tarama ' . gbc_kp_once( isset( $gv['zaman'] ) ? $gv['zaman'] : 0 ), null === $gvs ? $gr : ( $gvs >= 85 ? $ye : ( $gvs >= 60 ? $sa : $ki ) ), admin_url( 'admin.php?page=gbc-guvenlik' ) );

	$hz = get_option( 'gbc_hz_son', array() );
	$hz_sorun = 0; foreach ( $sorun as $x ) { if ( 'hiz' === $x['alan'] ) { $hz_sorun++; } }
	echo gbc_kp_kart( 'Hız', $hz_sorun ? $hz_sorun . ' sorun' : ( $hz ? 'Tamam' : '—' ), 'son ölçüm ' . gbc_kp_once( isset( $hz['zaman'] ) ? $hz['zaman'] : 0 ), $hz_sorun ? $ki : ( $hz ? $ye : $gr ), admin_url( 'admin.php?page=gbc-hiz' ) );

	$ic_sorun = 0; foreach ( $sorun as $x ) { if ( 0 === strpos( (string) $x['alan'], 'icerik' ) ) { $ic_sorun++; } }
	$icd = function_exists( 'gbc_ic_durum' ) ? gbc_ic_durum() : array();
	echo gbc_kp_kart( 'İçerik alanları', $ic_sorun ? $ic_sorun . ' sorun' : 'Tamam', 'şablon alanları dolu mu · ' . gbc_kp_once( isset( $icd['bitis'] ) ? $icd['bitis'] : 0 ), $ic_sorun ? $sa : $ye, admin_url( 'admin.php?page=gbc-icerik' ) );

	$pa = get_transient( 'gbc_plan_analiz' );
	$al = is_array( $pa ) && isset( $pa['ozet']['alarm'] ) ? $pa['ozet']['alarm'] : null;
	$ix = get_transient( 'gbc_env_indeks' ); $n = 0; $t = 0;
	if ( is_array( $ix ) ) { foreach ( $ix as $r ) { if ( ! empty( $r['yeni'] ) ) { $n++; $t += (int) $r['skor']; } } }
	echo gbc_kp_kart( 'SEO', $n ? '%' . (int) round( $t / $n ) : '—', $al ? ( $al['acil'] . ' acil mevsim alarmı · ' . $n . ' sayfa ölçüldü' ) : 'ortalama GBC skoru', $al && $al['acil'] ? $ki : ( $n ? $ye : $gr ), admin_url( 'admin.php?page=gbc-seo-envanter' ) );

	if ( function_exists( 'gbc_ort_saglik_ozet' ) ) {
		$os = gbc_ort_saglik_ozet();
		echo gbc_kp_kart( 'Ortaklık linkleri', $os['kirik'] ? $os['kirik'] . ' ölü' : 'Tamam', $os['toplam'] . ' link · ' . $os['iyi'] . ' çalışıyor', $os['kirik'] ? $ki : $ye, admin_url( 'admin.php?page=gbc-ortaklik' ) );
	}

	$cs = (int) get_option( 'gbc_cron_son', 0 );
	$cron_ok = $cs && ( time() - $cs ) < 20 * MINUTE_IN_SECONDS;
	echo gbc_kp_kart( 'Zamanlayıcı', $cron_ok ? 'Çalışıyor' : 'Durmuş olabilir', 'sunucu en son ' . gbc_kp_once( $cs ) . ' uğradı', $cron_ok ? $ye : $ki, admin_url( 'admin.php?page=gbc-cron' ) );
	echo '</div>';

	echo '<div class="gbc-kp-ikili"><div>';
	/* Açık sorunlar */
	echo '<div class="gbc-kp-kutu"><strong style="font-size:15px">Açık sorunlar</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Çözülene kadar burada durur; ertesi kontrol sorunu bulamazsa kendiliğinden kapanır.</div>';
	if ( ! $sorun ) { echo '<p style="color:' . $ye . '">Açık sorun yok.</p>'; }
	else {
		uasort( $sorun, static function ( $a, $b ) {
			$o = array( 'yuksek' => 0, 'orta' => 1, 'dusuk' => 2 );
			$x = ( isset( $o[ $a['onem'] ] ) ? $o[ $a['onem'] ] : 3 ) - ( isset( $o[ $b['onem'] ] ) ? $o[ $b['onem'] ] : 3 );
			return $x ? $x : (int) $a['ilk_gorulme'] - (int) $b['ilk_gorulme'];
		} );
		$alan_ad = array( 'guvenlik' => 'Güvenlik', 'hiz' => 'Hız', 'nobetci' => 'Sayfa taraması', 'kod' => 'Kod', 'ga4' => 'GA4', 'sistem' => 'Sistem', 'icerik' => 'İçerik alanları' );
		echo '<ol style="margin:0 0 0 20px">';
		foreach ( array_slice( $sorun, 0, 12, true ) as $x ) {
			$g = function_exists( 'gbc_sorun_gun' ) ? gbc_sorun_gun( $x ) : 0;
			$renk = 'yuksek' === $x['onem'] ? $ki : ( 'orta' === $x['onem'] ? $sa : $gr );
			echo '<li style="margin:0 0 6px"><span style="color:' . esc_attr( $renk ) . ';font-weight:700">' . esc_html( isset( $alan_ad[ $x['alan'] ] ) ? $alan_ad[ $x['alan'] ] : ucfirst( (string) $x['alan'] ) ) . '</span> · '
				. ( ! empty( $x['adres'] ) ? '<a href="' . esc_url( $x['adres'] ) . '">' . esc_html( $x['baslik'] ) . '</a>' : esc_html( $x['baslik'] ) )
				. ' <span style="color:#5C6470;font-size:12px">' . ( $g ? (int) $g . ' gündür açık' : 'bugün' ) . '</span></li>';
		}
		echo '</ol>';
		if ( count( $sorun ) > 12 ) { echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-gunluk' ) ) . '">Hepsi (' . count( $sorun ) . ')</a>'; }
	}
	echo '</div></div><div>';

	/* Zamanlanmış işler: ne, ne zaman */
	$isler = array(
		'gbc_nk_tur'          => array( 'Sayfa taraması', 'Motorlar sayfada mı (9 sayfa)' ),
		'gbc_seo_toplama'     => array( 'SEO toplama', 'GBC skoru, dizin, kelime fırsatları (6 sayfa) + Envanter hazırlığı' ),
		'gbc_gunluk_kontrol'  => array( 'Günlük tam kontrol', 'Kod, sayfa taraması, güvenlik, hız, GA4' ),
		'gbc_ort_saglik_cron' => array( 'Ortaklık linkleri', 'Hedef sayfalar açılıyor mu (tıklama göndermez)' ),
		'gbc_gv_tarama'       => array( 'Güvenlik taraması', 'Başlıklar, dosyalar, ayarlar' ),
		'gbc_plan_gunluk'     => array( 'Plan tablosu', 'Google tablosunu okur' ),
		'gbc_kur_gunluk'      => array( 'Döviz kuru', 'Canlı kur güncellemesi' ),
		'gbc_tik_temizlik'    => array( 'Tıklama kayıtları', 'Eski kayıt temizliği' ),
	);
	$kayit = get_option( 'gbc_cron_kayit', array() );
	echo '<div class="gbc-kp-kutu"><strong style="font-size:15px">Otomatik işler</strong>'
		. '<div style="color:#5C6470;font-size:12.5px;margin:2px 0 8px">Hepsi kendiliğinden çalışır; aynı anda yalnız biri çalışır ki site yorulmasın.</div><div class="gbc-kp-tablo"><table class="widefat striped"><thead><tr><th>İş</th><th style="width:90px">Sıklık</th><th style="width:100px">Sıradaki</th><th style="width:100px">Son çalışma</th></tr></thead><tbody>';
	$cr = function_exists( '_get_cron_array' ) ? (array) _get_cron_array() : array();
	foreach ( $isler as $h => $ad ) {
		$ne = wp_next_scheduled( $h );
		$sik = '';
		foreach ( $cr as $zaman => $kancalar ) {
			if ( isset( $kancalar[ $h ] ) ) { $ilk = reset( $kancalar[ $h ] ); $sik = isset( $ilk['schedule'] ) ? (string) $ilk['schedule'] : ''; break; }
		}
		$sik_ad = array( 'hourly' => 'saatte bir', 'daily' => 'günde bir', 'twicedaily' => 'günde iki', 'weekly' => 'haftada bir' );
		$son = isset( $kayit[ $h ]['bitti'] ) ? (int) $kayit[ $h ]['bitti'] : 0;
		echo '<tr><td><strong>' . esc_html( $ad[0] ) . '</strong><div style="color:#5C6470;font-size:12px">' . esc_html( $ad[1] ) . '</div></td>'
			. '<td style="font-size:12.5px">' . esc_html( isset( $sik_ad[ $sik ] ) ? $sik_ad[ $sik ] : ( $sik ?: '—' ) ) . '</td>'
			. '<td style="font-size:12.5px">' . ( $ne ? esc_html( wp_date( 'H:i', $ne ) ) . '<div style="color:#5C6470;font-size:11px">' . esc_html( human_time_diff( time(), $ne ) ) . ' sonra</div>' : '<span style="color:' . $ki . '">kayıtsız</span>' ) . '</td>'
			. '<td style="font-size:12.5px">' . esc_html( gbc_kp_once( $son ) ) . ( isset( $kayit[ $h ]['ms'] ) && $son ? '<div style="color:#5C6470;font-size:11px">' . esc_html( round( (int) $kayit[ $h ]['ms'] / 1000 ) ) . ' sn sürdü</div>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table></div></div>';
	echo '</div></div></div>';
}
