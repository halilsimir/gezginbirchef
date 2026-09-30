<?php
/**
 * v1.48.2 sınaması — WordPress olmadan, taklit fonksiyonlarla.
 * Çalıştır: php testler/v1482_testi.php
 *  A) aff_stil kuralı: ortaklık kutusu olan/olmayan sayfa × stil var/yok
 *  B) günlük kontrol saati: 20:12'ye kaymış iş 05:40'a geri alınır,
 *     05:40'taki işe dokunulmaz, tek seferlik yeniden deneme işine dokunulmaz.
 *  C) hiz.php: UCSS artık "bilerek" listesinde değil.
 */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['T'] = array( 'onay' => 0, 'kirik' => 0 );
function ok( $kosul, $ad ) { if ( $kosul ) { $GLOBALS['T']['onay']++; } else { $GLOBALS['T']['kirik']++; echo "KIRIK: $ad\n"; } }

/* --- WP taklitleri --- */
function __( $s ) { return $s; } function esc_html__( $s ) { return $s; }
function add_action() {} function add_filter() {}
function apply_filters( $h, $v ) { return $v; }
$GLOBALS['SECENEK'] = array( 'page_on_front' => 1, 'gmt_offset' => 3 );
function get_option( $k, $v = false ) { return isset( $GLOBALS['SECENEK'][ $k ] ) ? $GLOBALS['SECENEK'][ $k ] : $v; }
function get_permalink( $p ) { return 'https://gezginbirchef.com/s' . $p . '/'; }
function get_post_field() { return ''; } function get_the_title() { return 'Sayfa'; }
function home_url() { return 'https://gezginbirchef.com'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function is_wp_error( $x ) { return false; }
$GLOBALS['YANIT'] = array();
function wp_remote_get( $u ) { return array( 'body' => isset( $GLOBALS['YANIT'][ $u ] ) ? $GLOBALS['YANIT'][ $u ] : '' ); }
function wp_remote_retrieve_response_code() { return 200; }
function wp_remote_retrieve_body( $c ) { return $c['body']; }
function wp_remote_retrieve_header() { return 'hit'; }

require dirname( __DIR__ ) . '/gbc-core/inc/nobetci-kural.php';

/* ---------------- A) aff_stil ---------------- */
function sayfa( $pid, $kutu, $stil ) {
	$css_url = 'https://gezginbirchef.com/min/' . $pid . '.css';
	$GLOBALS['YANIT'][ get_permalink( $pid ) ] = '<html><head><link rel="stylesheet" href="' . $css_url . '"></head><body>'
		. ( $kutu ? '<div class="gbc-yan-ad"><a data-aff="1" href="#">otel</a></div>' : '<p>metin</p>' ) . '</body></html>';
	$GLOBALS['YANIT'][ $css_url ] = $stil ? '.gbc-yan-ad{margin:0}' : '.baska{margin:0}';
	$s = gbc_nk_tara_sayfa( $pid );
	return $s['motor']['aff_stil'];
}
ok( 'ok'    === sayfa( 11, true,  true  ), 'kutu var + stil var → ok (Sorrento)' );
ok( 'eksik' === sayfa( 12, true,  false ), 'kutu var + stil yok → eksik (GERÇEK bozulma hâlâ yakalanır)' );
ok( 'yok'   === sayfa( 13, false, false ), 'kutu yok + stil yok → yok, alarm değil (Sebze Kesim, UCSS)' );
ok( 'var'   === sayfa( 14, false, true  ), 'kutu yok + stil var → var, "fazla" değil (Ibiza, UCSS yok)' );
$m = gbc_nk_motorlar();
ok( 'kosul' === $m['aff_stil']['kapsam'] && 'yan_ad_var' === $m['aff_stil']['kosul'], 'motor haritası koşullu' );
/* Öbür koşullu motorun davranışı değişmedi: ortaklık bildirimi kutusuz sayfada "gerekmez". */
$GLOBALS['YANIT'][ get_permalink( 15 ) ] = '<html><body><p>metin</p></body></html>';
$s = gbc_nk_tara_sayfa( 15 );
ok( 'gerekmez' === $s['motor']['aff_bildirim'], 'diğer koşullu motorlar eski davranışta' );

/* ---------------- B) cron saati ---------------- */
$GLOBALS['CRON'] = array();   /* [ts, hook, schedule] */
function wp_next_scheduled( $h ) { $en = false; foreach ( $GLOBALS['CRON'] as $e ) { if ( $e[1] === $h && ( false === $en || $e[0] < $en ) ) { $en = $e[0]; } } return $en; }
function wp_get_scheduled_event( $h ) { $ts = wp_next_scheduled( $h ); if ( false === $ts ) { return false; } foreach ( $GLOBALS['CRON'] as $e ) { if ( $e[0] === $ts && $e[1] === $h ) { return (object) array( 'timestamp' => $e[0], 'schedule' => $e[2] ); } } }
function wp_schedule_event( $ts, $s, $h ) { $GLOBALS['CRON'][] = array( (int) $ts, $h, $s ); }
function wp_schedule_single_event( $ts, $h ) { $GLOBALS['CRON'][] = array( (int) $ts, $h, false ); }
function wp_unschedule_event( $ts, $h ) { foreach ( $GLOBALS['CRON'] as $i => $e ) { if ( $e[0] === $ts && $e[1] === $h ) { unset( $GLOBALS['CRON'][ $i ] ); } } }
function current_time() { return time() + 3 * HOUR_IN_SECONDS; }
/* gunluk.php büyük; yalnız cron fonksiyonunu çekip değerlendir. */
$kaynak = file_get_contents( dirname( __DIR__ ) . '/gbc-core/inc/gunluk.php' );
preg_match( '/function gbc_gunluk_cron_kur\(\) \{.*?\n\}/s', $kaynak, $f );
eval( $f[0] );
function yerel_saat( $ts ) { return gmdate( 'H:i', $ts + 3 * HOUR_IN_SECONDS ); }
function gunluk_isler() { return array_values( array_filter( $GLOBALS['CRON'], function ( $e ) { return 'gbc_gunluk_kontrol' === $e[1]; } ) ); }

$GLOBALS['CRON'] = array();
gbc_gunluk_cron_kur();
ok( 1 === count( gunluk_isler() ) && '05:40' === yerel_saat( gunluk_isler()[0][0] ), 'iş yoksa 05:40 kurulur' );

$GLOBALS['CRON'] = array( array( time() + 3600, 'gbc_gunluk_kontrol', 'daily' ) );
$yarin = strtotime( 'tomorrow 17:12 UTC' );
$GLOBALS['CRON'] = array( array( $yarin, 'gbc_gunluk_kontrol', 'daily' ) );
gbc_gunluk_cron_kur();
$is = gunluk_isler();
ok( 1 === count( $is ), 'kaymış iş: tek iş kalır (çift kurulmaz)' );
ok( '05:40' === yerel_saat( $is[0][0] ) && 'daily' === $is[0][2], 'kaymış iş (20:12) 05:40 günlüğe alınır' );
ok( $is[0][0] > time() && $is[0][0] - time() <= DAY_IN_SECONDS, 'yeni saat gelecekte ve 24 saat içinde' );
$once = $is[0][0];
gbc_gunluk_cron_kur();
ok( gunluk_isler()[0][0] === $once && 1 === count( gunluk_isler() ), 'ikinci çağrıda dokunulmaz (her init’te oynamaz)' );

$GLOBALS['CRON'] = array( array( time() + 600, 'gbc_gunluk_kontrol', false ), array( $yarin, 'gbc_gunluk_kontrol', 'daily' ) );
gbc_gunluk_cron_kur();
ok( 2 === count( gunluk_isler() ), 'kilit yüzünden konan tek seferlik deneme varken hiçbir işe dokunulmaz' );

$yakin = strtotime( 'tomorrow 02:50 UTC' );   /* yerel 05:50 — 10 dk sapma, tolerans içinde */
$GLOBALS['CRON'] = array( array( $yakin, 'gbc_gunluk_kontrol', 'daily' ) );
gbc_gunluk_cron_kur();
ok( gunluk_isler()[0][0] === $yakin, '15 dk içindeki sapmaya dokunulmaz' );

/* ---------------- C) hiz.php ---------------- */
$hiz = file_get_contents( dirname( __DIR__ ) . '/gbc-core/inc/hiz.php' );
preg_match( '/function gbc_hz_bilerek\(\) \{.*?\n\}/s', $hiz, $f );
eval( $f[0] );
ok( ! isset( gbc_hz_bilerek()['optm-ucss'] ), 'UCSS artık "bilerek kapalı" sayılmıyor' );
ok( false === strpos( $hiz, "UCSS bilerek kapalı (tasarımı bozuyordu)" ), 'eski "UCSS bilerek kapalı" öneri metni kalmadı' );
preg_match( '/function gbc_hz_duzeltilebilir\(\) \{.*?\n\}/s', $hiz, $f );
eval( $f[0] );
ok( ! isset( gbc_hz_duzeltilebilir()['optm-ucss'] ), '"Düzelt" düğmesi UCSS’e dokunmuyor' );

/* ---------------- sürüm ---------------- */
$ana = file_get_contents( dirname( __DIR__ ) . '/gbc-core/gbc-core.php' );
preg_match( '/Version:\s*([\d.]+)/', $ana, $a ); preg_match( "/GBC_CORE_SURUM', '([\d.]+)'/", $ana, $b );
ok( $a[1] === $b[1] && '1.48.2' === $a[1], 'başlık sürümü = GBC_CORE_SURUM = 1.48.2' );
ok( false !== strpos( $ana, "define( 'GBC_KURAL_SURUM', '4' )" ), 'GBC_KURAL_SURUM değişmedi (defter süpürülmez)' );

printf( "%d onay, %d kırık\n", $GLOBALS['T']['onay'], $GLOBALS['T']['kirik'] );
exit( $GLOBALS['T']['kirik'] ? 1 : 0 );
