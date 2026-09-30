<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Ortak tasarım.
 *
 * Bütün GBC ekranlarına tek bir görsel dil verir: aynı kart, aynı tablo,
 * aynı başlık, aynı renkler. Her ekranı tek tek elden geçirmek yerine
 * ortak bir stil katmanı bindirilir; eski Komuta ekranları da otomatik
 * olarak yeni görünüme uyar.
 *
 * YALNIZ GBC SAYFALARINDA yüklenir — WordPress'in kendi ekranlarına ve
 * başka eklentilere hiç dokunmaz. Ziyaretçi tarafında hiç çalışmaz.
 */

add_action( 'admin_enqueue_scripts', 'gbc_tasarim_yukle' );
function gbc_tasarim_yukle( $kanca ) {
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 0 !== strpos( $sayfa, 'gbc' ) ) { return; }
	add_action( 'admin_head', 'gbc_tasarim_css' );
}

function gbc_tasarim_css() {
	?>
<style id="gbc-tasarim">
:root{
	--gbc-zemin:#FAF8F4;
	--gbc-kart:#FFFFFF;
	--gbc-cizgi:#E6E2DA;
	--gbc-murekkep:#17181A;
	--gbc-soluk:#5C6470;
	--gbc-kiremit:#BF360C;
	--gbc-yesil:#0B5B55;
	--gbc-iyi:#1A7F37;
	--gbc-uyari:#8A6100;
	--gbc-kotu:#B3261E;
}

/* Zemin ve tipografi */
body.wp-admin #wpbody-content{background:var(--gbc-zemin)}
.wrap h1{font-size:26px!important;font-weight:700!important;letter-spacing:-.2px;margin-bottom:6px!important}
.wrap h2{font-size:19px!important;font-weight:600!important;margin-top:26px!important}
.wrap h3{font-size:16px!important;font-weight:600!important}
.wrap p{font-size:14px;line-height:1.65}

/* Kartlar */
.wrap div[style*="border-radius:14px"],
.wrap div[style*="border-radius: 14px"],
.wrap div[style*="border-radius:12px"],
.wrap div[style*="border-radius: 12px"]{
	box-shadow:0 1px 2px rgba(23,24,26,.04);
}

/* Tablolar — WordPress'in kendi widefat tablosu */
.wrap .widefat{border:1px solid var(--gbc-cizgi);border-radius:12px;overflow:hidden;box-shadow:0 1px 2px rgba(23,24,26,.04)}
.wrap .widefat thead th{background:#F3F0E9;border-bottom:1px solid var(--gbc-cizgi);font-weight:600;color:#3C4149;font-size:13px;padding:11px 14px}
.wrap .widefat tbody td{padding:11px 14px;font-size:13.5px;border-bottom:1px solid #F1EDE6;vertical-align:middle}
.wrap .widefat tbody tr:last-child td{border-bottom:none}
.wrap .widefat.striped tbody tr:nth-child(odd){background:#FCFBF8}
.wrap .widefat a{color:#A8380F;text-decoration:none;font-weight:500}
.wrap .widefat a:hover{color:#7C2A0B;text-decoration:underline}

/* Butonlar */
.wrap .button{border-radius:8px!important;border-color:#DCD7CD!important;font-weight:500;padding:4px 14px!important;height:auto!important;line-height:2.1!important}
.wrap .button:hover{border-color:#BDB6A9!important;background:#FCFBF8!important}
.wrap .button-primary{background:var(--gbc-kiremit)!important;border-color:var(--gbc-kiremit)!important;color:#fff!important;font-weight:600}
.wrap .button-primary:hover{background:#A02F0A!important;border-color:#A02F0A!important}

/* Uyarı kutuları */
.wrap .notice{border-radius:10px;border-left-width:4px;box-shadow:0 1px 2px rgba(23,24,26,.04)}

/* Menü — GBC alt sayfaları arasında görsel gruplama */
#adminmenu #toplevel_page_gbc .wp-submenu a{font-size:13.5px}
#adminmenu #toplevel_page_gbc .wp-submenu li a[href*="page=gbc-seo"]{padding-left:22px}

/* Formlar */
.wrap input[type="text"],.wrap input[type="search"],.wrap input[type="number"],.wrap select,.wrap textarea{
	border-radius:8px;border:1px solid #DCD7CD;background:#FCFBF8;font-size:13.5px
}
.wrap input:focus,.wrap select:focus,.wrap textarea:focus{border-color:var(--gbc-kiremit);box-shadow:0 0 0 1px var(--gbc-kiremit)}

/* Eski Komuta ekranlarındaki satır içi kutular da yeni dile uysun */
.wrap div[style*="border:1px solid #dcdcde"]{border-color:var(--gbc-cizgi)!important;border-radius:12px!important;background:var(--gbc-kart)!important}
.wrap pre{background:#F6F4EF;border:1px solid var(--gbc-cizgi);border-radius:10px;padding:12px 14px;font-size:12.5px;line-height:1.6}
</style>
	<?php
}

/**
 * Ekranların tepesine ortak bir yol çubuğu basar.
 * Her ekran kendi başlığını yazmaya devam eder; bu yalnızca nerede
 * olduğunu ve komşu ekranları gösterir.
 */
function gbc_tasarim_yol( $aktif = '' ) {
	/* v1.44.0: tek çatı — grup düğmeleri + grubun sekmeleri (inc/merkez.php). */
	if ( function_exists( 'gbc_merkez_ust' ) ) { gbc_merkez_ust( $aktif ); return; }
	$yollar = array(
		'gbc'              => __( 'Durum', 'gbc-core' ),
		'gbc-seo'          => __( 'SEO', 'gbc-core' ),
		'gbc-seo-envanter' => __( 'Envanter', 'gbc-core' ),
		'gbc-seo-firsat'   => __( 'Fırsatlar', 'gbc-core' ),
		'gbc-seo-sayfa'    => __( 'Sayfa Denetimi', 'gbc-core' ),
		'gbc-api'          => __( 'API Merkezi', 'gbc-core' ),
		'gbc-gunluk'       => __( 'Günlük Kontrol', 'gbc-core' ),
		'gbc-nobetci-kural'=> __( 'Kontrol Merkezi', 'gbc-core' ),
		'gbc-guvenlik'     => __( 'Güvenlik', 'gbc-core' ),
		'gbc-hiz'          => __( 'Hız & Sağlık', 'gbc-core' ),
		'gbc-cron'         => __( 'Zamanlanmış İşler', 'gbc-core' ),
	);

	echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 18px">';
	foreach ( $yollar as $slug => $ad ) {
		$bu = ( $slug === $aktif );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '" style="'
			. 'text-decoration:none;font-size:13px;font-weight:' . ( $bu ? '600' : '500' ) . ';'
			. 'padding:6px 13px;border-radius:999px;'
			. ( $bu ? 'background:#17181A;color:#fff' : 'background:#fff;color:#3C4149;border:1px solid #E6E2DA' )
			. '">' . esc_html( $ad ) . '</a>';
	}
	echo '</div>';
}
