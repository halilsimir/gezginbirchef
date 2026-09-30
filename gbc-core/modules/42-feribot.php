<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30350 — Feribot Rota Bulucu. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 81 · FERİBOT ROTA BULUCU · PHP
 * 2026-09-09 · v1 · kisa kod: [wpcode id="30350"]
 *
 * NE YAPAR
 * Yunan adalari feribot hub sayfasinin ustune gelen etkilesimli blok:
 * gercek koordinatlardan cizilen Ege haritasi + liman secici + sonuc kartlari.
 *
 * VERI NEREDEN GELIYOR
 * 1) HAT ve LIMAN satirlari: "Feribot Hat Defteri" sayfasi (30349).
 *    Umut oradan satir ekler, blok kendini yeniden cizer. Kod degismez.
 * 2) Baglantilar: "Is Ortakligi Baglanti Defteri" (30120). Bu snippet
 *    30204'teki gbc_aff_defter() fonksiyonunu cagirir; anahtar orada
 *    yoksa ya da url bossa o dugme HIC basilmaz (anayasa: bos kutu kalmaz).
 * 3) Ada siluetleri: asagidaki gbc_fb_cografya() dizisi. Yeni ada defterden
 *    eklenince haritaya NOKTA olarak duser; silueti buraya elle eklenir.
 *
 * NEDEN ISTEMCI TARAFINDA CIZILIYOR
 * Kartlar JS ile uretiliyor, dolayisiyla 30204'un the_content filtresi
 * onlara ulasmaz. O yuzden href'ler ve "is birligi" etiketi BURADA,
 * PHP tarafinda cozuluyor; rel ve target da elle yaziliyor. Ana salter
 * (gbc_aff_kapali) burada da gecerli.
 *
 * FIYAT YAZILMAZ. Fiyat gemi tipine, sezona ve firmaya gore degisiyor;
 * her donem sefer de olmuyor. Okur guncel fiyat icin baglantiya gonderilir.
 *
 * GERI ALMAK ICIN: bu snippet'i pasife al ve 30343'un icerik alanindaki
 * kisa kodu sil. Baska hicbir yere dokunulmadi, Astra'ya hic girilmedi.
 */

if ( ! function_exists( 'gbc_fb_satirlar' ) ) {
	function gbc_fb_satirlar() {
		static $bellek = null;
		if ( $bellek !== null ) { return $bellek; }
		$bellek = array( 'liman' => array(), 'hat' => array() );

		$sayfa = get_page_by_path( 'feribot-hat-defteri', OBJECT, 'page' );
		if ( ! $sayfa || empty( $sayfa->post_content ) ) { $sayfa = get_post( 30349 ); }
		if ( ! $sayfa || empty( $sayfa->post_content ) ) { return $bellek; }

		$ham = html_entity_decode( wp_strip_all_tags( $sayfa->post_content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$ham = str_replace( chr(13), chr(10), $ham );

		foreach ( explode( chr(10), $ham ) as $satir ) {
			$satir = trim( $satir );
			if ( $satir === '' || strpos( $satir, '|' ) === false ) { continue; }
			$p = array_map( 'trim', explode( '|', $satir ) );
			$tip = strtoupper( $p[0] );

			if ( $tip === 'LIMAN' && count( $p ) >= 7 ) {
				$id = sanitize_key( $p[1] );
				if ( $id === '' || $id === 'id' ) { continue; }
				$bellek['liman'][ $id ] = array(
					'n'  => $p[2],
					'lo' => (float) $p[3],
					'la' => (float) $p[4],
					's'  => $p[5],
					'lp' => $p[6],
					/* GBC 2026-09-10: Ferryhopper liman kodu, LIMAN satirinin 8. sutunu.
					   Bos birakilirsa o limandan gecen hatlarda Ferryhopper dugmesi
					   basilmaz (Ferryhopper o limani satmiyor demektir). Kodlar
					   Ferryhopper'in kendi "Deep Links" tablosundan geliyor. */
					'fh' => isset( $p[7] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $p[7] ) ) : '',
				);
			}

			if ( $tip === 'HAT' && count( $p ) >= 16 ) {
				$f = sanitize_key( $p[1] );
				$t = sanitize_key( $p[2] );
				if ( $f === '' || $f === 'nereden' || $t === '' ) { continue; }
				$det = array();
				if ( isset( $p[16] ) && trim( $p[16] ) !== '' ) {
					foreach ( explode( ';;', $p[16] ) as $ikili ) {
						$ikili = trim( $ikili );
						if ( $ikili === '' ) { continue; }
						$es = strpos( $ikili, '=' );
						if ( $es === false ) { continue; }
						$det[ trim( substr( $ikili, 0, $es ) ) ] = trim( substr( $ikili, $es + 1 ) );
					}
				}
				$tipi = strtoupper( $p[3] );
				$bellek['hat'][] = array(
					'f'      => $f,
					't'      => $t,
					'k'      => ( $tipi === 'YOK' ) ? 'TR' : $tipi,
					'dead'   => ( $tipi === 'YOK' ) ? 1 : 0,
					'd'      => $p[4],
					'season' => $p[5],
					'firms'  => $p[6],
					'yb'     => ( $p[7] === '1' ) ? 1 : 0,
					'car'    => ( $p[8] === '1' ) ? 1 : 0,
					'lo'     => (float) $p[9],
					'hi'     => (float) $p[10],
					'omio'   => sanitize_key( $p[11] ),
					'fh'     => sanitize_key( $p[12] ),
					'ters'   => sanitize_key( $p[13] ),
					'chain'  => $p[14],
					'note'   => $p[15],
					'det'    => $det,
				);
			}
		}
		return $bellek;
	}
}

if ( ! function_exists( 'gbc_fb_link' ) ) {
	function gbc_fb_link( $anahtar ) {
		if ( $anahtar === '' ) { return ''; }
		if ( get_option( 'gbc_aff_kapali' ) === '1' ) { return ''; }
		if ( ! function_exists( 'gbc_aff_defter' ) ) { return ''; }
		$defter = gbc_aff_defter();
		if ( ! isset( $defter[ $anahtar ] ) ) { return ''; }
		$url = $defter[ $anahtar ]['url'];
		if ( $url === '' || stripos( $url, 'http' ) !== 0 ) { return ''; }
		return $url;
	}
}

/* Kiyi cizgileri ve ada siluetleri. Bicim: [boylam, enlem].
   Yeni ada defterden eklenince haritada NOKTA olur; siluet istiyorsan
   asagiya id ile bir satir ekle. id bos ise sadece baglam adasidir,
   tiklanmaz. */
if ( ! function_exists( 'gbc_fb_cografya' ) ) {
	function gbc_fb_cografya() {
		return array(
			'tr' => array(
				array(26.15,40.90),array(26.65,40.35),array(26.32,40.08),array(26.80,39.92),
				array(27.02,39.58),array(26.62,39.32),array(26.92,39.02),array(27.06,38.62),
				array(26.24,38.30),array(27.02,38.36),array(27.20,38.44),array(27.38,37.92),
				array(27.16,37.70),array(27.62,37.56),array(27.20,37.34),array(27.72,37.20),
				array(27.20,36.97),array(27.62,36.94),array(27.78,36.66),array(28.38,36.82),
				array(28.66,36.62),array(29.20,36.52),array(29.72,36.12),array(30.90,36.30),
				array(30.90,40.90),
			),
			'gr' => array(
				array(22.10,41.60),array(26.30,41.60),array(26.10,40.90),array(25.85,40.86),
				array(25.45,40.96),array(25.10,40.99),array(24.80,40.86),array(24.42,40.94),
				array(24.05,40.80),array(23.88,40.72),array(23.78,40.52),array(23.90,40.40),
				array(24.15,40.28),array(24.42,40.13),array(24.20,40.22),array(23.98,40.36),
				array(23.80,40.26),array(23.90,40.08),array(23.96,39.90),array(23.80,40.06),
				array(23.66,40.24),array(23.56,40.22),array(23.68,40.02),array(23.72,39.90),
				array(23.50,40.06),array(23.32,40.20),array(23.22,40.30),array(23.05,40.42),
				array(22.94,40.62),
				array(22.72,40.48),array(22.60,40.35),array(22.56,40.05),array(22.62,39.82),
				array(22.75,39.63),array(22.92,39.45),array(23.10,39.32),array(23.34,39.17),
				array(23.12,39.10),array(22.95,39.22),array(22.88,39.04),
				array(22.40,39.00),array(23.00,38.76),array(23.36,38.54),array(23.70,38.30),
				array(23.76,38.10),array(23.58,38.00),array(23.70,37.88),array(23.92,37.80),
				array(24.04,37.64),array(23.86,37.70),array(23.74,37.86),array(23.54,37.86),
				array(23.38,37.70),array(23.14,37.55),array(22.84,37.60),array(23.05,36.90),
				array(23.22,36.50),array(23.00,36.70),array(22.88,36.82),array(22.72,36.80),
				array(22.58,36.42),array(22.45,36.70),array(22.30,36.95),array(22.10,37.40),
			),
			'ada' => array(
				array('limni',array(array(25.05,39.90),array(25.13,40.00),array(25.30,40.03),array(25.45,39.99),array(25.58,39.92),array(25.50,39.85),array(25.38,39.88),array(25.30,39.80),array(25.22,39.83),array(25.12,39.80))),
				array('midilli',array(array(25.95,39.22),array(26.05,39.35),array(26.25,39.40),array(26.45,39.38),array(26.60,39.28),array(26.57,39.12),array(26.45,39.02),array(26.38,39.10),array(26.30,38.98),array(26.20,39.05),array(26.13,38.97),array(26.05,39.08),array(25.98,39.10))),
				array('sakiz',array(array(25.92,38.60),array(26.05,38.62),array(26.13,38.52),array(26.17,38.38),array(26.15,38.25),array(26.05,38.16),array(25.97,38.22),array(25.94,38.35),array(25.87,38.45))),
				array('sisam',array(array(26.56,37.72),array(26.62,37.80),array(26.80,37.82),array(26.95,37.80),array(27.09,37.73),array(27.00,37.68),array(26.85,37.70),array(26.70,37.66))),
				array('kos',array(array(26.90,36.79),array(26.98,36.86),array(27.15,36.90),array(27.30,36.91),array(27.36,36.85),array(27.25,36.80),array(27.10,36.76),array(26.96,36.72))),
				array('symi',array(array(27.79,36.62),array(27.85,36.66),array(27.91,36.62),array(27.90,36.56),array(27.84,36.53),array(27.79,36.57))),
				array('rodos',array(array(28.22,36.46),array(28.25,36.38),array(28.20,36.25),array(28.10,36.12),array(27.95,36.00),array(27.80,35.88),array(27.72,35.93),array(27.86,36.12),array(27.98,36.28),array(28.10,36.42))),
				array('meis',array(array(29.55,36.15),array(29.59,36.17),array(29.63,36.15),array(29.62,36.12),array(29.57,36.11))),
				array('andros',array(array(24.72,38.02),array(24.83,38.03),array(24.95,37.90),array(25.02,37.78),array(24.98,37.67),array(24.90,37.70),array(24.85,37.83),array(24.73,37.94))),
				array('tinos',array(array(25.01,37.66),array(25.12,37.68),array(25.24,37.60),array(25.30,37.53),array(25.22,37.49),array(25.12,37.55),array(25.03,37.58))),
				array('mykonos',array(array(25.29,37.47),array(25.37,37.50),array(25.48,37.47),array(25.50,37.41),array(25.40,37.38),array(25.31,37.41))),
				array('syros',array(array(24.90,37.52),array(24.97,37.50),array(25.00,37.42),array(24.96,37.35),array(24.89,37.38),array(24.86,37.46))),
				array('paros',array(array(25.06,37.12),array(25.13,37.18),array(25.24,37.15),array(25.28,37.06),array(25.20,36.98),array(25.10,37.01),array(25.04,37.06))),
				array('',array(array(23.05,38.68),array(23.30,38.92),array(23.60,38.98),array(23.90,38.75),array(24.20,38.55),array(24.58,38.15),array(24.45,38.03),array(24.15,38.35),array(23.85,38.55),array(23.55,38.75),array(23.25,38.60),array(23.10,38.55))),
				array('',array(array(25.36,37.12),array(25.45,37.15),array(25.58,37.11),array(25.62,37.00),array(25.52,36.95),array(25.40,37.00))),
				array('',array(array(25.95,37.55),array(26.05,37.62),array(26.25,37.65),array(26.32,37.60),array(26.15,37.55),array(26.00,37.51))),
				array('',array(array(27.00,36.55),array(27.10,36.58),array(27.18,36.53),array(27.10,36.48),array(27.01,36.50))),
				array('',array(array(26.28,36.90),array(26.38,36.95),array(26.44,36.88),array(26.36,36.82),array(26.28,36.85))),
				array('',array(array(23.52,35.42),array(23.58,35.54),array(23.78,35.53),array(24.02,35.51),array(24.30,35.41),array(24.55,35.37),array(24.85,35.41),array(25.14,35.34),array(25.45,35.35),array(25.72,35.19),array(26.02,35.20),array(26.30,35.28),array(26.25,35.00),array(25.95,34.96),array(25.70,34.90),array(25.35,34.92),array(25.05,34.90),array(24.75,34.90),array(24.45,35.04),array(24.10,35.14),array(23.80,35.20),array(23.58,35.28))),
				array('',array(array(27.03,35.82),array(27.10,35.86),array(27.16,35.72),array(27.24,35.58),array(27.26,35.44),array(27.18,35.42),array(27.12,35.56),array(27.06,35.70))),
				array('',array(array(26.88,35.42),array(26.98,35.45),array(27.02,35.38),array(26.94,35.34))),
			),
		);
	}
}

if ( ! function_exists( 'gbc_fb_render' ) ) {
	function gbc_fb_render() {
		$d = gbc_fb_satirlar();
		if ( empty( $d['hat'] ) || empty( $d['liman'] ) ) {
			return current_user_can( 'edit_posts' )
				? '<div style="border:1px solid #B71C1C;background:#FBECEA;color:#8a2a20;padding:14px 16px;border-radius:10px;font-size:15px">Feribot Hat Defteri okunamadi. Sayfa 30349 duruyor mu ve icindeki satirlar bozulmus mu, kontrol et.</div>'
				: '';
		}

		$hatlar = array();
		foreach ( $d['hat'] as $h ) {
			if ( ! isset( $d['liman'][ $h['f'] ] ) || ! isset( $d['liman'][ $h['t'] ] ) ) { continue; }
			$h['omioUrl'] = gbc_fb_link( $h['omio'] );
			$h['fhUrl']   = gbc_fb_link( $h['fh'] );
			$h['tersUrl'] = gbc_fb_link( $h['ters'] );
			/* GBC 21 Eylul 2026: dugmelerde data-aff icin defter id'leri JS verisine tasinir (yalniz URL basiliyorsa). Sayfa id'si 'pid' olarak verinin icinde. */ $h['omioId'] = $h['omioUrl'] ? $h['omio'] : ''; $h['tersId'] = $h['tersUrl'] ? $h['ters'] : ''; $h['fhId'] = $h['fhUrl'] ? $h['fh'] : ''; unset( $h['omio'], $h['fh'], $h['ters'] );
			$hatlar[] = $h;
		}

		$veri = array(
			'liman' => $d['liman'],
			'hat'   => array_values( $hatlar ),
			'geo'   => gbc_fb_cografya(), 'pid' => (int) get_the_ID(),
			/* ═══ GBC 2026-09-10 · FERRYHOPPER DERIN BAGLANTI ═══
			   Ferryhopper'in KENDI ortaklik programi; Travelpayouts degil,
			   kurallari da farkli. Kalip sabit, degisen tek sey iki liman kodu:
			     ...&initial=<KALKIS_KODU>,<VARIS_KODU>
			   Kodlar Hat Defteri'ndeki LIMAN satirinin 8. sutunundan geliyor,
			   yon de kartin gosterdigi yone gore JS tarafinda cevriliyor.
			   BOYLECE: yeni bir hat eklendiginde Ferryhopper icin defterde
			   satir acmaya gerek yok, link kendiliginden dogru cikiyor.
			   Ana salter (gbc_aff_kapali) kapaliysa bos string doner ve
			   dugme hic basilmaz; gbc_fb_link() ile ayni davranis. */
			'fhbase' => ( get_option( 'gbc_aff_kapali' ) === '1' ) ? '' : 'https://www.ferryhopper.com/tr/?aff_uid=gzgbr&utm_source=affiliate-link&utm_medium=in-house&utm_campaign=gzgbr&utm_content=gezginbirchef&initial=',
		);
		$json = wp_json_encode( $veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( ! $json ) { return ''; }

		ob_start();
		echo '<script type="application/json" id="gbc-fb-veri">' . $json . '</script>';
		echo gbc_fb_kabuk();
		echo gbc_fb_js();
		return ob_get_clean();
	}
}

if ( ! function_exists( 'gbc_fb_kabuk' ) ) {
	function gbc_fb_kabuk() {
		$s = <<<'GBCFBHTML'
<style>
.gbc-fb{--o:#D1440D;--os:#FDF0E7;--b:#0F5C6B;--bs:#EAF2F9;--t:#00796B;--ts:#E6F2F0;
 --ink:#1a1a1a;--ink2:#4a4a4a;--mut:#7a7a7a;--ln:#e8e8e8;--ln2:#f2efec;--card:#fff;
 --sea:#DCE9F1;--land:#E6E0D6;--landln:#C9BFB0;--komsu:#E4E2DE;--yun:#DCE7C8;--yunln:#B5C596;
 --yunyazi:#6E8050;--denizyazi:#5A7C93;--gd:#047857;--gds:#E9F5EE;
 --wr:#8A5A00;--wrs:#FBF1DF;--dd:#98342A;--dds:#FBECEA;
 font-family:inherit;color:var(--ink);margin:0 0 34px;line-height:1.55}
.gbc-fb *{box-sizing:border-box}
/* GBC 2026-09-10: Ispanya Vizesi (30285) sayfasindaki not/uyari kutusunun
   rengi yalnizca Liste sablonunun CSS'inde tanimliymis; Detay sablonunda
   .gz2-note zemini ve sol serigi kayboluyordu (olculdu). Ayni gorunumu
   burada tanimliyoruz ki iki sayfa ayni dili konussun. Geri almak icin
   bu uc satiri sil. */
.entry-content .gz2-note{background:#FBF3EF;border-left:3px solid #BF360C;border-radius:0 10px 10px 0;padding:13px 16px;margin:18px 0}
.entry-content .gz2-note.gz2-warn{background:#FDF3F3;border-left-color:#B91C1C}
.entry-content .gz2-note.gz2-ok{background:#EDF7F0;border-left-color:#047857}
/* .gbc-src Detay sablonunda flex ama .l / .v genislikleri tanimsizdi; etiket
   kucucuk kaliyor, deger saga yasliyordu (olculdu). Kaynak kutusu duzeni. */
.entry-content .gbc-src{display:flex;flex-wrap:wrap;gap:4px 16px;align-items:baseline;
 padding:12px 16px;background:#fafbfb;border:1px solid #ececec;border-radius:10px;margin:18px 0}
.entry-content .gbc-src .l{flex:0 0 auto;min-width:150px;font-size:13px !important;font-weight:700;
 color:#7a7a7a;text-align:left;line-height:1.5 !important}
.entry-content .gbc-src .v{flex:1 1 260px;font-size:15px !important;color:#1a1a1a;text-align:left;
 line-height:1.6 !important;font-weight:400}
.gbc-fb button,.gbc-fb .fb-chip,.gbc-fb .fb-tog,.gbc-fb .fb-tabs button,
.gbc-fb .fb-clear,.gbc-fb .fb-reset{text-transform:none !important;letter-spacing:normal !important;
 min-height:auto;text-shadow:none}
.gbc-fb .fb-card{background:var(--card);border:1px solid var(--ln);border-radius:16px;
 box-shadow:0 4px 16px rgba(0,0,0,.05);overflow:hidden}
.gbc-fb .fb-mapcard{margin:0 0 16px}
.gbc-fb .fb-maphead{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;
 padding:14px 18px;border-bottom:1px solid var(--ln2)}
.gbc-fb .fb-mt{font-size:16px !important;font-weight:800 !important;margin:0 !important;
 flex:1 1 auto;line-height:1.3 !important;color:var(--ink) !important}
.gbc-fb .fb-mt span{display:block;font-size:13.5px;font-weight:400;color:var(--mut)}
.gbc-fb .fb-leg{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:var(--ink2)}
.gbc-fb .fb-leg i{display:inline-block;width:18px;height:3px;border-radius:2px;vertical-align:middle;margin-right:5px}
.gbc-fb .fb-picker{padding:13px 18px 14px;border-bottom:1px solid var(--ln2);background:#fdfcfb}
.gbc-fb .fb-pgroup{display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;margin:0 0 8px}
.gbc-fb .fb-pgroup:last-child{margin-bottom:0}
.gbc-fb .fb-prow{display:flex;flex-wrap:wrap;gap:6px;flex:1 1 0;min-width:0}
.gbc-fb .fb-plabel{padding-top:5px}
.gbc-fb .fb-plabel .sm{display:none}
.gbc-fb .fb-plabel{font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
 color:var(--mut);flex:0 0 auto;min-width:104px}
.gbc-fb .fb-pick{border:1px solid var(--ln);background:#fff;color:var(--ink2);font-family:inherit;
 font-size:13.5px;font-weight:600;padding:5px 11px;border-radius:999px;cursor:pointer;line-height:1.35;
 text-transform:none !important;letter-spacing:normal !important;min-height:auto}
.gbc-fb .fb-pick:hover{border-color:var(--o);color:var(--o);background:var(--os)}
.gbc-fb .fb-pick[aria-pressed="true"]{background:var(--o);border-color:var(--o);color:#fff}
.gbc-fb .fb-node:hover .fb-dot{stroke-width:4}
.gbc-fb .fb-node:focus-visible{outline:none}
.gbc-fb .fb-node:focus-visible .fb-dot{stroke-width:4}
.gbc-fb .fb-swipe{display:none;text-align:center;font-size:12.5px;color:var(--mut);padding:7px 0 0}
/* GBC 2026-09-21: oklar metinden cikarildi, buradan ciziliyor. Kural 4:
   ok elle yazilmaz. Tek maske kullaniliyor, soldaki ayna cevriliyor. */
.gbc-fb .fb-swipe::before,.gbc-fb .fb-swipe::after{content:"";display:inline-block;width:11px;height:11px;vertical-align:-1px;background-color:currentColor;-webkit-mask:var(--gbc-ok-sag) center/contain no-repeat;mask:var(--gbc-ok-sag) center/contain no-repeat}
.gbc-fb .fb-swipe::before{margin-right:7px;transform:scaleX(-1)}
.gbc-fb .fb-swipe::after{margin-left:7px}
.gbc-fb .fb-scroll{overflow-x:auto;background:var(--sea);-webkit-overflow-scrolling:touch}
.gbc-fb svg.fb-map{display:block;width:100%;min-width:640px;height:auto}
.gbc-fb .fb-node{cursor:pointer}
.gbc-fb .fb-dot{transition:stroke-width .15s}
.gbc-fb .fb-lbl{font-size:13px;font-weight:700;fill:var(--ink);cursor:pointer;
 paint-order:stroke;stroke:var(--sea);stroke-width:3.5px}
.gbc-fb .fb-lbl.sm{font-size:11.5px;font-weight:500;fill:var(--mut)}
.gbc-fb .fb-dur{font-size:12px;font-weight:700;cursor:pointer;paint-order:stroke;
 stroke:var(--sea);stroke-width:4px}
.gbc-fb .fb-dur:hover{text-decoration:underline}
/* GBC 2026-09-10: harita boyamasi Yunanistan pillar haritasiyla ayni dile
   getirildi. Yunan topragi ve adalar yesil, komsu kara gri, deniz ve ulke
   adlari haritanin icinde. Geri almak icin bu blogu eski tek renk
   .fb-land kuralina dondur. */
.gbc-fb .fb-ulke{font-size:12px;font-weight:700;letter-spacing:.14em;fill:var(--mut);opacity:.85;pointer-events:none}
.gbc-fb .fb-ulke.yn{font-size:11.5px;letter-spacing:.1em;fill:var(--yunyazi);opacity:.95}
.gbc-fb .fb-ulke.ada{font-size:10.5px;letter-spacing:.12em;fill:var(--yunyazi);opacity:.8}
.gbc-fb .fb-deniz{font-size:12.5px;font-style:italic;letter-spacing:.08em;fill:var(--denizyazi);
 opacity:.95;pointer-events:none;paint-order:stroke;stroke:var(--sea);stroke-width:3.5px}
.gbc-fb .fb-land{fill:var(--land);stroke:var(--landln);stroke-width:1.5}
.gbc-fb .fb-land.komsu{fill:var(--komsu);stroke:var(--landln);stroke-width:1.2}
.gbc-fb .fb-land.yun{fill:var(--yun);stroke:var(--yunln);stroke-width:1.4}
.gbc-fb .fb-isle{transition:fill .18s,stroke .18s}
.gbc-fb .fb-isle.ctx{opacity:1;fill:#E4EDD4;stroke:#C3D0A8}
.gbc-fb .fb-isle.on{fill:#CFE3D6;stroke:var(--t);stroke-width:2.2}
.gbc-fb .fb-lnk{fill:none;stroke-linecap:round;transition:opacity .18s,stroke-width .18s}
.gbc-fb .fb-hint{padding:10px 18px;border-top:1px solid var(--ln2);font-size:13.5px;
 color:var(--mut);display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.gbc-fb .fb-hint b{color:var(--ink);font-weight:700}
.gbc-fb .fb-clear{margin-left:auto;background:none;border:0;color:var(--o);font:inherit;
 font-size:13.5px;font-weight:700;cursor:pointer;padding:4px 0;min-height:auto}
.gbc-fb .fb-clear:hover{text-decoration:underline}
.gbc-fb .fb-finder{padding:16px 18px 14px}
.gbc-fb .fb-q{font-size:14.5px !important;font-weight:700 !important;margin:0 0 11px !important;
 color:var(--ink2) !important;line-height:1.4 !important}
.gbc-fb .fb-tabs{display:flex;flex-wrap:wrap;gap:6px;background:var(--ln2);padding:5px;border-radius:12px;margin:0 0 13px}
.gbc-fb .fb-tabs button{flex:1 1 auto;min-width:110px;border:0;background:transparent;color:var(--ink2);
 font-family:inherit;font-size:13.5px;font-weight:600;padding:10px 12px;border-radius:9px;cursor:pointer}
.gbc-fb .fb-tabs button[aria-selected="true"]{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.09)}
.gbc-fb .fb-chips{display:flex;flex-wrap:wrap;gap:7px}
.gbc-fb .fb-chip{border:1px solid var(--ln);background:#fff;color:var(--ink2);font-family:inherit;
 font-size:14px;font-weight:600;padding:7px 13px;border-radius:999px;cursor:pointer;line-height:1.3}
.gbc-fb .fb-chip:hover{border-color:var(--o);color:var(--ink)}
.gbc-fb .fb-chip[aria-pressed="true"]{background:var(--o);border-color:var(--o);color:#fff}
.gbc-fb .fb-tools{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:16px 0 0;
 padding-top:14px;border-top:1px solid var(--ln2)}
.gbc-fb .fb-search{position:relative;flex:1 1 240px;min-width:200px}
.gbc-fb .fb-search input{width:100%;border:1px solid var(--ln);background:#fafbfb;color:var(--ink);
 border-radius:10px;padding:10px 12px 10px 34px;font-family:inherit;font-size:14.5px}
.gbc-fb .fb-search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);opacity:.45}
.gbc-fb .fb-togs{display:flex;flex-wrap:wrap;gap:6px}
.gbc-fb .fb-tog{border:1px solid var(--ln);background:#fff;color:var(--mut);font-family:inherit;
 font-size:13px;font-weight:700;padding:8px 11px;border-radius:8px;cursor:pointer}
.gbc-fb .fb-tog[aria-pressed="true"]{background:var(--os);border-color:var(--o);color:var(--o)}
.gbc-fb .fb-meta{display:flex;flex-wrap:wrap;gap:12px;align-items:baseline;margin:24px 0 14px}
.gbc-fb .fb-cnt{font-size:18px;font-weight:800}
.gbc-fb .fb-sub{font-size:14px;color:var(--mut)}
.gbc-fb .fb-reset{margin-left:auto;background:none;border:0;color:var(--o);font:inherit;
 font-size:14px;font-weight:700;cursor:pointer;padding:0}
.gbc-fb .fb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;align-items:start}
.gbc-fb .fb-rc{background:#fff;border:1px solid var(--ln);border-radius:14px;overflow:hidden}
.gbc-fb .fb-rc:hover{border-color:#f0b48a;box-shadow:0 4px 14px rgba(0,0,0,.06)}
.gbc-fb .fb-top{width:100%;background:none;border:0;text-align:left;padding:15px 17px;
 cursor:pointer;font-family:inherit;color:inherit;display:block}
.gbc-fb .fb-route{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 9px}
.gbc-fb .fb-p{font-size:17px;font-weight:800;line-height:1.25}
.gbc-fb .fb-arw{font-size:15px;flex:none;color:var(--o)}
.gbc-fb .fb-rc[data-k="AD"] .fb-arw{color:var(--b)}
.gbc-fb .fb-rc[data-k="II"] .fb-arw{color:var(--t)}
.gbc-fb .fb-rc.dead .fb-arw{color:var(--dd)}
.gbc-fb .fb-facts{display:flex;flex-wrap:wrap;gap:6px}
.gbc-fb .fb-f{font-size:12.5px;font-weight:700;padding:3px 9px;border-radius:6px;
 background:#f5f3f1;color:var(--ink2);white-space:nowrap}
.gbc-fb .fb-f.time{background:var(--os);color:var(--o)}
.gbc-fb .fb-f.ok{background:var(--gds);color:var(--gd)}
.gbc-fb .fb-f.seas{background:var(--wrs);color:var(--wr)}
.gbc-fb .fb-f.no{background:var(--dds);color:var(--dd)}
.gbc-fb .fb-f.ath{background:var(--bs);color:var(--b)}
.gbc-fb .fb-body{border-top:1px solid var(--ln2);padding:14px 17px 16px;font-size:15px;color:var(--ink2)}
.gbc-fb .fb-body[hidden]{display:none !important}
.gbc-fb .fb-note{margin:0 0 11px;line-height:1.6}
.gbc-fb .fb-dl{display:grid;grid-template-columns:auto 1fr;gap:5px 12px;margin:0 0 11px;font-size:14px}
.gbc-fb .fb-dt{color:var(--mut);font-weight:700}
.gbc-fb .fb-dd{color:var(--ink)}
.gbc-fb .fb-chain{background:var(--bs);border-radius:9px;padding:10px 12px;margin:0 0 12px;
 font-size:13.5px;color:var(--ink2);line-height:1.5}
.gbc-fb .fb-chain b{color:var(--b);font-weight:800}
.gbc-fb .fb-ctas{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 9px}
.gbc-fb a.fb-cta{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--o);
 border-radius:9px;padding:8px 13px;background:var(--os);color:var(--o) !important;
 font-weight:800;text-decoration:none !important;font-size:14.5px}
.gbc-fb a.fb-cta:hover{background:var(--o);color:#fff !important}
.gbc-fb a.fb-cta.alt{border-color:var(--b);color:var(--b) !important;background:var(--bs)}
.gbc-fb a.fb-cta.alt:hover{background:var(--b);color:#fff !important}
.gbc-fb a.fb-cta::after{content:none !important}
/* GBC 2026-09-10: is ortakligi etiketi artik site geneliyle ayni sinifi
   (.gbc-in-et) kullaniyor; boylece Ispanya sayfasindaki gorunumle birebir.
   Burada yalnizca kart icindeki hizasi ayarlaniyor. */
.gbc-fb .fb-ctas .gbc-in-et{align-self:center;flex:0 0 auto}
.gbc-fb .fb-price{font-size:13px;color:var(--mut);margin:0;line-height:1.45}
.gbc-fb .fb-nolink{font-size:14px;color:var(--mut);font-style:italic}
.gbc-fb .fb-caret{float:right;color:var(--mut);font-size:12px;display:inline-block;transition:transform .18s}
.gbc-fb .fb-top[aria-expanded="true"] .fb-caret{transform:rotate(180deg)}
.gbc-fb .fb-empty{border:1px dashed var(--ln);border-radius:14px;padding:32px 22px;text-align:center;color:var(--mut)}
.gbc-fb .fb-empty b{display:block;color:var(--ink);margin-bottom:6px;font-size:16px}
@media (max-width:700px){
 .gbc-fb .fb-picker{padding:11px 13px 12px}
 .gbc-fb .fb-pgroup{display:flex;flex-wrap:nowrap;align-items:flex-start;gap:0 7px;margin:0 0 8px}
 .gbc-fb .fb-plabel{min-width:0;flex:0 0 auto;padding-top:6px;font-size:11px}
 .gbc-fb .fb-plabel .lg{display:none}
 .gbc-fb .fb-plabel .sm{display:inline;font-weight:800}
 .gbc-fb .fb-prow{flex:1 1 0;min-width:0;flex-wrap:wrap;gap:5px;padding:0}
 .gbc-fb .fb-pick{font-size:12.5px;padding:4px 9px;line-height:1.3}
 .gbc-fb svg.fb-map{min-width:560px}
 .gbc-fb .fb-swipe{display:block}
 .gbc-fb .fb-grid{grid-template-columns:1fr}
 .gbc-fb .fb-finder{padding:14px 13px 12px}
 .gbc-fb .fb-maphead{padding:12px 14px}
 .gbc-fb .fb-hint{padding:10px 14px}
}
@media (prefers-reduced-motion:reduce){.gbc-fb *{transition:none !important}}
</style>

<div class="gbc-fb" id="gbcFb">
 <div class="fb-card fb-mapcard">
  <div class="fb-maphead">
   <div class="fb-mt" role="heading" aria-level="2">Ege feribot ağı <span>Bir limana ya da adaya tıkla, hatları gör</span></div>
   <div class="fb-leg">
    <span><i style="background:#D1440D"></i>Türkiye &#8596; ada</span>
    <span><i style="background:#0F5C6B"></i>Ada &#8596; Atina</span>
    <span><i style="background:#00796B"></i>Adalar arası</span>
   </div>
  </div>
  <div class="fb-picker" id="fbPicker"></div>
  <div class="fb-swipe">haritayı yana kaydır</div>
  <div class="fb-scroll"><svg class="fb-map" id="fbMap" role="img" aria-label="Ege Denizi haritasi: Turkiye limanlari, Yunan adalari ve Pire arasindaki feribot hatlari"></svg></div>
  <div class="fb-hint"><span id="fbHint"></span><button class="fb-clear" id="fbClear" type="button" hidden>Seçimi kaldır</button></div>
 </div>

 <div class="fb-card fb-finder">
  <h3 class="fb-q">Hat tipine göre daralt</h3>
  <div class="fb-tabs" role="tablist" id="fbTabs"></div>
  <div class="fb-tools">
   <div class="fb-search">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
    <input id="fbQ" type="search" placeholder="Ya da yaz: bodrum, rodos, pire..." aria-label="Rota ara">
   </div>
   <div class="fb-togs" id="fbTogs"></div>
  </div>
 </div>

 <div class="fb-meta"><span class="fb-cnt" id="fbCnt"></span><span class="fb-sub" id="fbSub"></span><button class="fb-reset" id="fbReset" type="button">Sıfırla</button></div>
 <div class="fb-grid" id="fbGrid"></div>
</div>
GBCFBHTML;
		return $s;
	}
}

if ( ! function_exists( 'gbc_fb_js' ) ) {
	function gbc_fb_js() {
		return <<<'GBCFBJS'
<script>
(function(){
 var el=document.getElementById("gbc-fb-veri"); if(!el) return;
 var D; try{ D=JSON.parse(el.textContent); }catch(e){ return; }
 var N=D.liman, R=D.hat, GEO=D.geo;
 var root=document.getElementById("gbcFb"), svg=document.getElementById("fbMap");
 if(!root||!svg) return;

 var W=1000,PL=34,PR=34,PT=44,PB=30;
 var LO0=22.75,LO1=30.05,LA0=35.20,LA1=40.45;
 var SX=(W-PL-PR)/(LO1-LO0), SY=SX*1.27;
 var H=Math.round(PT+PB+(LA1-LA0)*SY);
 function X(lo){return PL+(lo-LO0)*SX;}
 function Y(la){return PT+(LA1-la)*SY;}
 var KC={TR:"#D1440D",AD:"#0F5C6B",II:"#00796B"};
 function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}

 function poly(pts,smooth){
  var P=pts.map(function(p){return [X(p[0]),Y(p[1])];});
  if(!smooth) return P.map(function(p,i){return (i?"L":"M")+p[0].toFixed(1)+" "+p[1].toFixed(1);}).join(" ")+" Z";
  var n=P.length,t=0.15,d="M "+P[0][0].toFixed(1)+" "+P[0][1].toFixed(1);
  for(var i=0;i<n;i++){
   var p0=P[(i-1+n)%n],p1=P[i],p2=P[(i+1)%n],p3=P[(i+2)%n];
   var c1=[p1[0]+(p2[0]-p0[0])*t,p1[1]+(p2[1]-p0[1])*t];
   var c2=[p2[0]-(p3[0]-p1[0])*t,p2[1]-(p3[1]-p1[1])*t];
   d+=" C "+c1[0].toFixed(1)+" "+c1[1].toFixed(1)+", "+c2[0].toFixed(1)+" "+c2[1].toFixed(1)+", "+p2[0].toFixed(1)+" "+p2[1].toFixed(1);
  }
  return d+" Z";
 }
 function arc(a,b,bend){
  var x1=X(N[a].lo),y1=Y(N[a].la),x2=X(N[b].lo),y2=Y(N[b].la);
  var mx=(x1+x2)/2,my=(y1+y2)/2,dx=x2-x1,dy=y2-y1,L=Math.hypot(dx,dy)||1;
  var cx=mx-dy/L*L*bend, cy=my+dx/L*L*bend;
  return {d:"M "+x1.toFixed(1)+" "+y1.toFixed(1)+" Q "+cx.toFixed(1)+" "+cy.toFixed(1)+" "+x2.toFixed(1)+" "+y2.toFixed(1),
          mid:[0.25*x1+0.5*cx+0.25*x2, 0.25*y1+0.5*cy+0.25*y2]};
 }
 var deg={};
 R.forEach(function(r,i){ r.i=i; r.arc=arc(r.f,r.t, r.k==="AD"?0.10:0.07);
   if(!r.dead){deg[r.f]=(deg[r.f]||0)+1; deg[r.t]=(deg[r.t]||0)+1;} });

 svg.setAttribute("viewBox","0 0 "+W+" "+H);
 var s="";
 s+='<path class="fb-land komsu" d="'+poly(GEO.tr,1)+'"></path>';
 s+='<path class="fb-land yun" d="'+poly(GEO.gr,1)+'"></path>';
 GEO.ada.forEach(function(a){
  s+='<path class="fb-land yun fb-isle'+(a[0]?"":" ctx")+'"'+(a[0]?' data-isle="'+a[0]+'"':"")+' d="'+poly(a[1],1)+'"></path>';
 });
 function ulke(t,lo,la,c){return '<text class="fb-ulke'+(c||"")+'" x="'+X(lo).toFixed(1)+'" y="'+Y(la).toFixed(1)+'" text-anchor="middle">'+t+'</text>';}
 function deniz(t,lo,la){return '<text class="fb-deniz" x="'+X(lo).toFixed(1)+'" y="'+Y(la).toFixed(1)+'" text-anchor="middle">'+t+'</text>';}
 var LBL=ulke("TÜRKİYE",29.10,39.05)+ulke("YUNANİSTAN",23.50,40.35," yn")
  +ulke("GİRİT",24.95,35.31," ada")
  +deniz("EGE DENİZİ",25.55,38.62)+deniz("AKDENİZ",28.62,36.02);
 s+='<g id="fbLinks">';
 R.forEach(function(r){
  s+='<path class="fb-lnk" data-i="'+r.i+'" d="'+r.arc.d+'" stroke="'+(r.dead?"#9a9a9a":KC[r.k])+'" stroke-width="1.6" opacity=".3"'+(r.dead?' stroke-dasharray="5 5"':"")+'></path>';
 });
 s+='</g>'+LBL+'<g id="fbDurs"></g><g id="fbNodes">';
 Object.keys(N).forEach(function(id){
  var p=N[id], x=X(p.lo), y=Y(p.la), tr=(p.s==="tr"), ath=(p.s==="ath");
  var r=ath?8:Math.min(6,2.8+(deg[id]||0)*0.55);
  var fill=ath?"#0F5C6B":tr?"#D1440D":"#ffffff";
  var st=ath?"#0F5C6B":tr?"#D1440D":"#00796B";
  var lp=p.lp||(ath?"r":tr?"r":"t"), off=r+7;
  var LX=(lp==="l")?x-off:(lp==="r")?x+off:x;
  var LY=(lp==="t")?y-off-4:(lp==="b")?y+off+11:y+4.5;
  var AN=(lp==="l")?"end":(lp==="r")?"start":"middle";
  s+='<g class="fb-node" data-id="'+id+'" tabindex="0" role="button" aria-label="'+esc(p.n)+'">';
  s+='<circle cx="'+x.toFixed(1)+'" cy="'+y.toFixed(1)+'" r="16" fill="transparent"></circle>';
  s+='<circle class="fb-dot" cx="'+x.toFixed(1)+'" cy="'+y.toFixed(1)+'" r="'+r.toFixed(1)+'" fill="'+fill+'" stroke="'+st+'" stroke-width="2"></circle>';
  if(ath){
   s+='<text class="fb-lbl" x="'+LX.toFixed(1)+'" y="'+(y-2).toFixed(1)+'" text-anchor="'+AN+'">'+esc(p.n)+'</text>';
   s+='<text class="fb-lbl sm" x="'+LX.toFixed(1)+'" y="'+(y+13).toFixed(1)+'" text-anchor="'+AN+'">Atina&#8217;nın limanı</text>';
  }else{
   s+='<text class="fb-lbl" x="'+LX.toFixed(1)+'" y="'+LY.toFixed(1)+'" text-anchor="'+AN+'">'+esc(p.n)+'</text>';
  }
  s+='</g>';
 });
 s+='</g>';
 svg.innerHTML=s;

 var TABS=[{k:"all",l:"Hepsi"},{k:"TR",l:"Türkiye &#8596; ada"},{k:"AD",l:"Ada &#8596; Atina"},{k:"II",l:"Adalar arası"}];
 var TOGS=[{k:"yb",l:"Yıl boyu açık"},{k:"car",l:"Araç taşıyor"},{k:"fast",l:"1 saatten kısa"},{k:"buy",l:"Online bilet"}];
 var tab="all", port=null, q="", on={};
 var $=function(i){return document.getElementById(i);};

 $("fbTabs").innerHTML=TABS.map(function(t){return '<button role="tab" data-k="'+t.k+'" aria-selected="'+(t.k===tab)+'">'+t.l+'</button>';}).join("");
 $("fbTogs").innerHTML=TOGS.map(function(t){return '<button class="fb-tog" type="button" data-k="'+t.k+'" aria-pressed="false">'+t.l+'</button>';}).join("");

 function portList(){
  var set={};
  R.forEach(function(r){ if(tab==="all"||r.k===tab){set[r.f]=1;set[r.t]=1;} });
  return Object.keys(set).sort(function(a,b){return N[a].n.localeCompare(N[b].n,"tr");});
 }
 var GRUP=[["tr","Türkiye limanları","Türkiye"],["gr","Yunan adaları","Adalar"],["ath","Atina","Atina"]];
 function drawChips(){
  var h="";
  GRUP.forEach(function(g){
   var ids=Object.keys(N).filter(function(id){return N[id].s===g[0];})
     .sort(function(a,b){return N[a].n.localeCompare(N[b].n,"tr");});
   if(!ids.length) return;
   h+='<div class="fb-pgroup"><span class="fb-plabel"><b class="lg">'+g[1]+'</b><b class="sm">'+g[2]+'</b></span><div class="fb-prow">'+ids.map(function(p){
    return '<button class="fb-pick" type="button" data-p="'+p+'" aria-pressed="'+(p===port)+'">'+esc(N[p].n)+'</button>';
   }).join("")+'</div></div>';
  });
  $("fbPicker").innerHTML=h;
 }
 function pass(r){
  if(tab!=="all" && r.k!==tab) return false;
  if(port && r.f!==port && r.t!==port) return false;
  if(q){
   var hay=(N[r.f].n+" "+N[r.t].n+" "+r.firms+" "+r.note).toLocaleLowerCase("tr");
   var ok=true; q.split(" ").forEach(function(w){ if(w && hay.indexOf(w)<0) ok=false; });
   if(!ok) return false;
  }
  if(on.yb&&(!r.yb||r.dead)) return false;
  if(on.car&&(!r.car||r.dead)) return false;
  if(on.fast&&(r.lo>1||r.dead)) return false;
  if(on.buy&&!r.omioUrl&&!r.fhUrl) return false;
  return true;
 }
 function paint(){
  var act = port ? R.filter(function(r){return r.f===port||r.t===port;}) : [];
  var set={}; act.forEach(function(r){set[r.i]=1;});
  var touched={}; act.forEach(function(r){touched[r.f]=1;touched[r.t]=1;});
  Array.prototype.forEach.call(svg.querySelectorAll(".fb-lnk"),function(p){
   var i=+p.getAttribute("data-i");
   if(!port){p.setAttribute("opacity",".3");p.setAttribute("stroke-width","1.6");}
   else if(set[i]){p.setAttribute("opacity","1");p.setAttribute("stroke-width","3");}
   else{p.setAttribute("opacity",".08");p.setAttribute("stroke-width","1.4");}
  });
  Array.prototype.forEach.call(svg.querySelectorAll(".fb-node"),function(g){
   var id=g.getAttribute("data-id");
   g.style.opacity=(!port||touched[id])?"1":".28";
   g.querySelector(".fb-dot").setAttribute("stroke-width", id===port?"4":"2");
  });
  Array.prototype.forEach.call(svg.querySelectorAll(".fb-isle"),function(p){
   var id=p.getAttribute("data-isle");
   if(id){ p.classList.toggle("on", id===port||!!touched[id]);
           p.style.opacity=(!port||touched[id])?"1":".45"; }
  });
  var placed=[];
  $("fbDurs").innerHTML=act.map(function(r){
   var mx=r.arc.mid[0], my=r.arc.mid[1];
   for(var g=0;g<8;g++){
    var hit=null;
    for(var j=0;j<placed.length;j++){ if(Math.abs(placed[j][0]-mx)<64 && Math.abs(placed[j][1]-my)<15){hit=placed[j];break;} }
    if(!hit) break;
    my=(my>hit[1])?hit[1]+16:hit[1]-16;
   }
   placed.push([mx,my]);
   return '<text class="fb-dur" data-go="'+r.i+'" x="'+mx.toFixed(1)+'" y="'+my.toFixed(1)+'" text-anchor="middle" fill="'+(r.dead?"#98342A":KC[r.k])+'">'+esc(r.d)+' &#8250;</text>';
  }).join("");
  $("fbClear").hidden=!port;
  $("fbHint").innerHTML = port
   ? "<b>"+esc(N[port].n)+"</b> · "+act.length+" hat · geliş ve gidiş birlikte"
   : "<b>"+R.length+" hat</b> · bir limana tıkla, süreleri göster";
 }
 function card(r){
  var dep = port ? (r.f===port) : true;
  var A=dep?r.f:r.t, B=dep?r.t:r.f;
  var f=['<span class="fb-f '+(r.dead?"no":"time")+'">'+esc(r.d)+'</span>'];
  if(!r.dead){
   f.push('<span class="fb-f '+(r.yb?"ok":"seas")+'">'+esc(r.season)+'</span>');
   if(r.car) f.push('<span class="fb-f">Araç taşınıyor</span>');
   if(r.chain) f.push('<span class="fb-f ath">Atina&#8217;ya '+esc(r.chain)+'</span>');
  }
  var dl="";
  Object.keys(r.det||{}).forEach(function(k){ dl+='<span class="fb-dt">'+esc(k)+'</span><span class="fb-dd">'+esc(r.det[k])+'</span>'; });
  var ch=[];
  var uOm = dep ? r.omioUrl : (r.tersUrl||r.omioUrl);
  /* GBC 21 Eylul 2026: Omio ve Ferryhopper dugmelerine data-aff + data-post eklendi, tiklama sayaci (30126) bunlari da saysin. URL ve davranis ayni. */ var idOm = dep ? (r.omioId||"") : (r.tersUrl ? (r.tersId||"") : (r.omioId||"")); /* takma ad yerine sub_id'deki gercek defter id'si */ var sOm=/[?&]sub_id=([A-Za-z0-9_-]+)/.exec(uOm||""); if(sOm) idOm=sOm[1]; var aOm = idOm ? ' data-aff="'+esc(idOm)+'" data-post="'+(+D.pid||0)+'"' : ""; if(uOm) ch.push('<a class="fb-cta" href="'+esc(uOm)+'" target="_blank" rel="sponsored nofollow noopener" data-prog="Omio"'+aOm+'>Omio&#8217;da saat ve fiyata bak</a>');
  /* GBC 2026-09-10: Ferryhopper baglantisi artik iki liman kodundan
     uretiliyor. A ve B yukarida "dep" ile cevrilmis kalkis/varis, yani
     kart hangi yonu gosteriyorsa link de o yonu aciyor. Uretilemezse
     (liman kodu yoksa) defterdeki anahtar yedek olarak kullaniliyor. */
  var uFh = "";
  if(D.fhbase && !r.dead && N[A] && N[B] && N[A].fh && N[B].fh){ uFh = D.fhbase + N[A].fh + "," + N[B].fh; }
  if(!uFh) uFh = r.fhUrl || "";
  if(uFh) ch.push('<a class="fb-cta alt" href="'+esc(uFh)+'" target="_blank" rel="sponsored nofollow noopener" data-prog="Ferryhopper"'+(r.fhId ? ' data-aff="'+esc(r.fhId)+'" data-post="'+(+D.pid||0)+'"' : "")+'>Ferryhopper&#8217;da karşılaştır</a>');
  var cta = ch.length
   ? '<div class="fb-ctas">'+ch.join("")+'<span class="gbc-in-et">iş birliği</span></div><div class="fb-price">Fiyat gemi tipine, sezona ve firmaya göre değişiyor; her dönem sefer de olmuyor. Gideceğin tarihi bu bağlantılardan kontrol et.</div>'
   : (r.dead?"":'<span class="fb-nolink">Bilet sitelerinde yok, doğrudan işletmeciden alınıyor. Saatleri işletmeciden teyit et.</span>');
  var chain = r.chain ? '<div class="fb-chain"><b>Atina&#8217;ya devam:</b> '+esc(N[r.f].n)+' &#8594; '+esc(N[r.t].n)+' &#8594; Pire, toplam <b>'+esc(r.chain)+'</b>. Aktarma beklemesi hariç.</div>' : "";
  return '<article class="fb-rc'+(r.dead?" dead":"")+'" data-k="'+r.k+'">'
   +'<button class="fb-top" type="button" aria-expanded="false" aria-controls="fbB'+r.i+'">'
   +'<span class="fb-caret" aria-hidden="true">&#9662;</span>'
   +'<span class="fb-route"><span class="fb-p">'+esc(N[A].n)+'</span><span class="fb-arw" aria-hidden="true">'+(r.dead?"&#10005;":"&#8594;")+'</span><span class="fb-p">'+esc(N[B].n)+'</span></span>'
   +'<span class="fb-facts">'+f.join("")+'</span></button>'
   +'<div class="fb-body" id="fbB'+r.i+'" hidden>'
   +'<div class="fb-note">'+esc(r.note)+'</div>'
   +(dl?'<div class="fb-dl">'+dl+'</div>':"")
   +(r.firms&&r.firms!=="yok"?'<div class="fb-dl"><span class="fb-dt">İşletmeci</span><span class="fb-dd">'+esc(r.firms)+'</span></div>':"")
   +chain+cta+'</div></article>';
 }
 function draw(){
  var res=R.filter(pass).sort(function(a,b){
   if(port){var ad=a.f===port?0:1, bd=b.f===port?0:1; if(ad!==bd) return ad-bd;}
   return a.lo-b.lo;
  });
  $("fbGrid").innerHTML = res.length ? res.map(card).join("")
   : '<div class="fb-empty" style="grid-column:1/-1"><b>Bu seçimle sefer çıkmadı.</b>Filtreleri gevşetmeyi ya da başka bir liman seçmeyi dene.</div>';
  $("fbCnt").textContent=res.length+" hat";
  var bits=[];
  if(port) bits.push(N[port].n+"'a gelen ve giden");
  else if(tab!=="all"){ var tt=TABS.filter(function(t){return t.k===tab;})[0]; if(tt) bits.push(tt.l.replace(/&#8596;/g,"-")); }
  if(q) bits.push('"'+q+'"');
  TOGS.forEach(function(t){ if(on[t.k]) bits.push(t.l.toLocaleLowerCase("tr")); });
  $("fbSub").innerHTML = bits.length ? "· "+esc(bits.join(", ")) : "· süreye göre sıralı, en kısadan uzuna";
  paint();
 }
 function setPort(p){
  port=(port===p)?null:p;
  if(port){
   var has=false; R.forEach(function(r){ if((r.f===port||r.t===port)&&(tab==="all"||r.k===tab)) has=true; });
   if(!has){ tab="all"; Array.prototype.forEach.call($("fbTabs").children,function(b){b.setAttribute("aria-selected",b.getAttribute("data-k")==="all");}); }
  }
  drawChips(); draw();
 }
 svg.addEventListener("click",function(e){
  var d=e.target.closest?e.target.closest(".fb-dur"):null;
  if(d){
   var bt=root.querySelector('.fb-top[aria-controls="fbB'+d.getAttribute("data-go")+'"]');
   if(bt){ if(bt.getAttribute("aria-expanded")!=="true"){ bt.setAttribute("aria-expanded","true"); $("fbB"+d.getAttribute("data-go")).hidden=false; }
     bt.parentNode.scrollIntoView({behavior:"smooth",block:"center"}); }
   return;
  }
  var g=e.target.closest?e.target.closest(".fb-node"):null;
  if(g) setPort(g.getAttribute("data-id"));
 });
 svg.addEventListener("keydown",function(e){
  if(e.key==="Enter"||e.key===" "){ var g=e.target.closest?e.target.closest(".fb-node"):null;
   if(g){ e.preventDefault(); setPort(g.getAttribute("data-id")); } }
 });
 root.addEventListener("click",function(e){
  var tb=e.target.closest(".fb-tabs button");
  if(tb){ tab=tb.getAttribute("data-k"); port=null;
   Array.prototype.forEach.call($("fbTabs").children,function(b){b.setAttribute("aria-selected",b===tb);});
   drawChips(); draw(); return; }
  var ch=e.target.closest(".fb-pick"); if(ch){ setPort(ch.getAttribute("data-p")); return; }
  var tg=e.target.closest(".fb-tog");
  if(tg){ var k=tg.getAttribute("data-k"); on[k]=!on[k]; tg.setAttribute("aria-pressed",!!on[k]); draw(); return; }
  var rt=e.target.closest(".fb-top");
  if(rt){ var o=rt.getAttribute("aria-expanded")==="true";
   rt.setAttribute("aria-expanded",!o);
   document.getElementById(rt.getAttribute("aria-controls")).hidden=o; return; }
  if(e.target.id==="fbClear"){ port=null; drawChips(); draw(); return; }
  if(e.target.id==="fbReset"){ tab="all"; port=null; q=""; on={}; $("fbQ").value="";
   Array.prototype.forEach.call($("fbTabs").children,function(b){b.setAttribute("aria-selected",b.getAttribute("data-k")==="all");});
   Array.prototype.forEach.call($("fbTogs").children,function(b){b.setAttribute("aria-pressed","false");});
   drawChips(); draw(); }
 });
 $("fbQ").addEventListener("input",function(e){ q=e.target.value.trim().toLocaleLowerCase("tr"); draw(); });

 drawChips(); draw();
})();
</script>
GBCFBJS;
	}
}

/* Cikti YOK. Bu modul kisa kod tipidir: gbc-core.php onu
   [gbc_...] kisa koduna ve [wpcode id=...] cagrisina baglar. */
