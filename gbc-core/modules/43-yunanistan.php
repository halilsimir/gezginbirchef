<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30361 — Yunanistan Pillar. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 82 · YUNANISTAN PILLAR · PHP
 * 2026-09-10 · v1 · kisa kod: [wpcode id="BURAYA_ID"]
 *
 * NE YAPAR
 * Yunanistan pillar sayfasinin ustundeki blok: gercek koordinatlardan
 * cizilen Yunanistan haritasi + kume kume rehber kartlari.
 *
 * VERI NEREDEN GELIYOR
 * "Yunanistan Rehber Defteri" (30360). Umut oraya YER / SAYFA / REHBER
 * satiri ekler, blok kendini yeniden cizer. Koda dokunulmaz.
 *
 * SAYFASI OLMAYAN YERLER
 * Haritada baglam noktasi olarak gorunur, tiklanmaz, kartlarda listelenmez.
 * Olu link uretilmez. Sayfa acilinca deftere SAYFA satiri eklenir, yer
 * kendiliginden tiklanabilir hale gelir ve karti olusur.
 *
 * GERI ALMAK ICIN: snippet'i pasife al ve sayfadaki kisa kodu sil.
 */

if ( ! function_exists( 'gbc_yn_satirlar' ) ) {
	function gbc_yn_satirlar() {
		static $bellek = null;
		if ( $bellek !== null ) { return $bellek; }
		$bellek = array( 'yer' => array(), 'kume' => array(), 'rehber' => array() );

		$sayfa = get_page_by_path( 'yunanistan-rehber-defteri', OBJECT, 'page' );
		if ( ! $sayfa || empty( $sayfa->post_content ) ) { $sayfa = get_post( 30360 ); }
		if ( ! $sayfa || empty( $sayfa->post_content ) ) { return $bellek; }

		$ham = html_entity_decode( wp_strip_all_tags( $sayfa->post_content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$ham = str_replace( chr(13), chr(10), $ham );

		$sayfalar = array();

		foreach ( explode( chr(10), $ham ) as $satir ) {
			$satir = trim( $satir );
			if ( $satir === '' || strpos( $satir, '|' ) === false ) { continue; }
			$p   = array_map( 'trim', explode( '|', $satir ) );
			$tip = strtoupper( $p[0] );

			if ( $tip === 'YER' && count( $p ) >= 8 ) {
				$id = sanitize_key( $p[1] );
				if ( $id === '' || $id === 'id' ) { continue; }
				$bellek['yer'][ $id ] = array(
					'n'  => $p[2],
					'lo' => (float) $p[3],
					'la' => (float) $p[4],
					'k'  => $p[5],
					'h'  => (int) $p[6],
					'lp' => $p[7],
					's'  => array(),
				);
				if ( ! in_array( $p[5], $bellek['kume'], true ) ) { $bellek['kume'][] = $p[5]; }
			}

			if ( $tip === 'SAYFA' && count( $p ) >= 4 ) {
				$id   = sanitize_key( $p[1] );
				$slug = sanitize_title( $p[3] );
				if ( $id === '' || $slug === '' || $id === 'yer_id' ) { continue; }
				$sayfalar[] = array( $id, $p[2], $slug );
			}

			if ( $tip === 'REHBER' && count( $p ) >= 4 ) {
				$slug = sanitize_title( $p[2] );
				if ( $slug === '' ) { continue; }
				$bellek['rehber'][] = array(
					't' => $p[1],
					'u' => home_url( '/' . $slug . '/' ),
					'd' => $p[3],
				);
			}
		}

		foreach ( $sayfalar as $sf ) {
			if ( isset( $bellek['yer'][ $sf[0] ] ) ) {
				$bellek['yer'][ $sf[0] ]['s'][] = array( 't' => $sf[1], 'u' => home_url( '/' . $sf[2] . '/' ) );
			}
		}

		return $bellek;
	}
}

/* Cizim verisi. Bicim: [boylam, enlem].
   komsu : Arnavutluk + Kuzey Makedonya + Bulgaristan + Trakya + Anadolu,
           tek gri kutle. Yunanistan bunun uzerine yesil basiliyor,
           sinir kendiliginden olusuyor.
   deniz : Canakkale Bogazi ve Marmara, komsu kutlenin uzerine deniz
           rengiyle basiliyor.
   ada   : id bos ise sadece baglam adasi. */
if ( ! function_exists( 'gbc_yn_cografya' ) ) {
	function gbc_yn_cografya() {
		return array(
			'yun' => array(
				array(20.02,39.70),array(20.30,39.80),array(20.75,40.05),array(20.98,40.45),array(21.35,40.62),array(21.75,40.92),
				array(22.35,41.15),array(22.95,41.35),array(23.60,41.40),array(24.20,41.42),array(24.85,41.40),array(25.45,41.32),array(26.12,41.35),
				array(26.35,40.95),array(25.88,40.86),array(25.15,40.86),array(24.55,40.94),array(24.05,40.78),array(23.82,40.72),
array(23.78,40.42),array(24.06,40.30),array(24.38,40.16),array(24.28,40.12),array(23.92,40.28),array(23.72,40.38),
array(23.82,40.20),array(23.98,40.04),array(23.97,39.93),array(23.85,39.98),array(23.71,40.18),array(23.62,40.30),
array(23.52,40.14),array(23.42,39.97),array(23.34,39.92),array(23.27,40.00),array(23.29,40.20),array(23.18,40.36),
array(22.90,40.55),array(22.60,40.50),array(22.55,40.20),array(22.62,39.95),array(22.90,39.66),array(23.06,39.38),
array(23.28,39.20),array(23.12,39.08),array(22.96,39.14),array(22.98,38.92),array(23.20,38.74),array(23.45,38.56),
array(23.66,38.40),array(23.85,38.20),array(23.98,38.02),array(24.03,37.80),array(24.03,37.64),array(23.86,37.76),
array(23.72,37.90),array(23.45,37.98),array(23.15,38.02),array(22.98,38.04),array(22.70,38.34),array(22.30,38.38),
array(21.90,38.38),array(21.55,38.36),array(21.22,38.40),array(21.08,38.55),array(20.95,38.78),array(20.72,39.02),
array(20.45,39.20),array(20.30,39.30),
				
				
				
				
				
				
			),
			'mora' => array(
array(22.93,37.96),array(23.10,37.80),array(23.32,37.62),array(23.48,37.45),array(23.30,37.40),array(23.10,37.52),
array(22.88,37.55),array(22.78,37.35),array(22.98,37.10),array(23.08,36.88),array(23.12,36.70),array(22.98,36.74),
array(22.88,36.92),array(22.60,36.82),array(22.48,36.39),array(22.36,36.58),array(22.30,36.88),array(22.12,37.05),
array(21.96,36.84),array(21.70,36.76),array(21.63,36.98),array(21.55,37.28),array(21.40,37.66),array(21.30,37.96),
array(21.35,38.16),array(21.62,38.24),array(21.95,38.26),array(22.35,38.26),array(22.68,38.22),array(22.88,38.02),
),
'komsu' => array(
				array(18.40,42.60),array(30.60,42.60),array(30.60,36.00),array(29.72,36.12),array(29.20,36.52),array(28.66,36.62),
				array(28.38,36.82),array(27.78,36.66),array(27.62,36.94),array(27.20,36.97),array(27.72,37.20),array(27.20,37.34),
				array(27.62,37.56),array(27.16,37.70),array(27.38,37.92),array(27.20,38.44),array(27.02,38.36),array(26.24,38.30),
				array(27.06,38.62),array(26.92,39.02),array(26.62,39.32),array(27.02,39.58),array(26.80,39.92),array(26.32,40.08),
				array(26.65,40.35),array(26.15,40.60),array(26.12,41.35),array(25.45,41.32),array(24.85,41.40),array(24.20,41.42),
				array(23.60,41.40),array(22.95,41.35),array(22.35,41.15),array(21.75,40.92),array(21.35,40.62),array(20.98,40.45),
				array(20.75,40.05),array(20.30,39.80),array(20.02,39.70),array(18.40,39.70),
			),
			'deniz' => array(
				array(26.05,40.15),array(26.40,40.35),array(26.90,40.35),array(27.60,40.40),array(28.40,40.62),
				array(29.05,40.58),array(29.25,40.44),array(28.60,40.30),array(27.50,40.25),array(26.75,40.20),array(26.30,40.00),
			),
			'ada' => array(
				array('girit',array(array(23.52,35.52),array(24.10,35.62),array(24.72,35.42),array(25.20,35.35),array(25.75,35.20),array(26.30,35.30),array(26.20,35.10),array(25.60,35.00),array(24.90,35.05),array(24.20,35.15),array(23.60,35.22))),
				array('korfu',array(array(19.85,39.80),array(19.95,39.82),array(20.10,39.60),array(20.12,39.40),array(19.98,39.36),array(19.86,39.55))),
				array('kefalonya',array(array(20.40,38.28),array(20.60,38.35),array(20.75,38.20),array(20.68,38.06),array(20.48,38.10))),
				array('zakynthos',array(array(20.68,37.90),array(20.88,37.90),array(20.98,37.78),array(20.82,37.68),array(20.70,37.76))),
				array('midilli',array(array(25.95,39.22),array(26.05,39.35),array(26.25,39.40),array(26.45,39.38),array(26.60,39.28),array(26.57,39.12),array(26.45,39.02),array(26.38,39.10),array(26.30,38.98),array(26.20,39.05),array(26.13,38.97),array(26.05,39.08),array(25.98,39.10))),
				array('sakiz',array(array(25.92,38.60),array(26.05,38.62),array(26.13,38.52),array(26.17,38.38),array(26.15,38.25),array(26.05,38.16),array(25.97,38.22),array(25.94,38.35))),
				array('sisam',array(array(26.56,37.72),array(26.62,37.80),array(26.80,37.82),array(26.95,37.80),array(27.09,37.73),array(27.00,37.68),array(26.85,37.70),array(26.70,37.66))),
				array('limni',array(array(25.05,39.90),array(25.13,40.00),array(25.30,40.03),array(25.45,39.99),array(25.58,39.92),array(25.50,39.85),array(25.38,39.88),array(25.30,39.80),array(25.22,39.83),array(25.12,39.80))),
				array('kos',array(array(26.90,36.79),array(26.98,36.86),array(27.15,36.90),array(27.30,36.91),array(27.36,36.85),array(27.25,36.80),array(27.10,36.76),array(26.96,36.72))),
				array('rodos',array(array(28.22,36.46),array(28.25,36.38),array(28.20,36.25),array(28.10,36.12),array(27.95,36.00),array(27.80,35.88),array(27.72,35.93),array(27.86,36.12),array(27.98,36.28),array(28.10,36.42))),
				array('symi',array(array(27.79,36.62),array(27.85,36.66),array(27.91,36.62),array(27.90,36.56),array(27.84,36.53),array(27.79,36.57))),
				array('meis',array(array(29.55,36.15),array(29.59,36.17),array(29.63,36.15),array(29.62,36.12),array(29.57,36.11))),
				array('santorini',array(array(25.35,36.48),array(25.42,36.48),array(25.48,36.40),array(25.42,36.34),array(25.36,36.38))),
				array('mykonos',array(array(25.29,37.47),array(25.37,37.50),array(25.48,37.47),array(25.50,37.41),array(25.40,37.38),array(25.31,37.41))),
				array('tinos',array(array(25.01,37.66),array(25.12,37.68),array(25.24,37.60),array(25.30,37.53),array(25.22,37.49),array(25.12,37.55),array(25.03,37.58))),
				array('naxos',array(array(25.36,37.12),array(25.45,37.15),array(25.58,37.11),array(25.62,37.00),array(25.52,36.95),array(25.40,37.00))),
				array('paros',array(array(25.06,37.12),array(25.13,37.18),array(25.24,37.15),array(25.28,37.06),array(25.20,36.98),array(25.10,37.01),array(25.04,37.06))),
				array('milos',array(array(24.32,36.76),array(24.42,36.78),array(24.55,36.74),array(24.52,36.66),array(24.38,36.66))),
				array('kythnos',array(array(24.36,37.46),array(24.44,37.48),array(24.48,37.38),array(24.42,37.32),array(24.36,37.38))),
				array('paxos',array(array(20.14,39.24),array(20.22,39.24),array(20.24,39.16),array(20.16,39.14))),
				array('',array(array(24.46,38.92),array(24.60,38.98),array(24.72,38.86),array(24.60,38.74),array(24.46,38.80))),
				array('',array(array(23.42,39.18),array(23.62,39.24),array(23.82,39.16),array(23.66,39.06),array(23.46,39.08))),
array('',array(array(23.10,38.98),array(23.42,38.90),array(23.70,38.72),array(24.00,38.55),array(24.30,38.38),array(24.58,38.20),array(24.42,38.02),array(24.20,38.22),array(23.90,38.40),array(23.62,38.58),array(23.32,38.72),array(23.02,38.85))),
				array('',array(array(27.15,36.55),array(27.25,36.58),array(27.32,36.50),array(27.22,36.45))),
			),
		);
	}
}

if ( ! function_exists( 'gbc_yn_render' ) ) {
	function gbc_yn_render() {
		$d = gbc_yn_satirlar();
		if ( empty( $d['yer'] ) ) {
			return current_user_can( 'edit_posts' )
				? '<div style="border:1px solid #B71C1C;background:#FBECEA;color:#8a2a20;padding:14px 16px;border-radius:10px;font-size:15px">Yunanistan Rehber Defteri okunamadi. Sayfa 30360 duruyor mu, satirlar bozulmus mu kontrol et.</div>'
				: '';
		}

		$veri = array(
			'yer'    => $d['yer'],
			'kume'   => $d['kume'],
			'rehber' => $d['rehber'],
			'geo'    => gbc_yn_cografya(),
		);
		$json = wp_json_encode( $veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( ! $json ) { return ''; }

		ob_start();
		echo '<script type="application/json" id="gbc-yn-veri">' . $json . '</script>';
		echo gbc_yn_kabuk();
		echo gbc_yn_js();
		return ob_get_clean();
	}
}

if ( ! function_exists( 'gbc_yn_kabuk' ) ) {
	function gbc_yn_kabuk() {
		$s = <<<'GBCYNHTML'
<style>
.gbc-yn{--o:#E65100;--os:#FDF0E7;--t:#00796B;--ink:#1a1a1a;--ink2:#4a4a4a;--mut:#7a7a7a;
 --ln:#e8e8e8;--ln2:#f2efec;--card:#fff;
 --sea:#DCE9F1;--komsu:#E4E2DE;--landln:#C9BFB0;--yun:#DCE7C8;--yunln:#B5C596;
 --yunyazi:#6E8050;--denizyazi:#5A7C93;--mavi:#01579B;--mavis:#EAF2F9;--yesil:#1B5E20;--yesils:#EAF3EB;
 font-family:inherit;color:var(--ink);margin:0 0 34px;line-height:1.55}
.gbc-yn *{box-sizing:border-box}
.gbc-yn button,.gbc-yn .yn-pick{text-transform:none !important;letter-spacing:normal !important;
 min-height:auto;text-shadow:none}
.gbc-yn .yn-card{background:var(--card);border:1px solid var(--ln);border-radius:16px;
 box-shadow:0 4px 16px rgba(0,0,0,.05);overflow:hidden;margin:0 0 16px}
.gbc-yn .yn-head{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:baseline;
 padding:14px 18px;border-bottom:1px solid var(--ln2)}
.gbc-yn .yn-t{font-size:16px !important;font-weight:800 !important;margin:0 !important;
 flex:1 1 auto;line-height:1.3 !important;color:var(--ink) !important}
.gbc-yn .yn-t span{display:block;font-size:13.5px;font-weight:400;color:var(--mut)}
.gbc-yn .yn-tsub{flex:1 1 100%;display:block;font-size:13.5px;font-weight:400;color:var(--mut);line-height:1.45;margin:0}
.gbc-yn .yn-picker{padding:13px 18px 14px;border-bottom:1px solid var(--ln2);background:#fdfcfb}
.gbc-yn .yn-pgroup{display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;margin:0 0 8px}
.gbc-yn .yn-pgroup:last-child{margin-bottom:0}
.gbc-yn .yn-plabel{font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
 color:var(--mut);flex:0 0 auto;min-width:132px;padding-top:5px}
.gbc-yn .yn-prow{display:flex;flex-wrap:wrap;gap:6px;flex:1 1 0;min-width:0}
.gbc-yn .yn-pick{border:1px solid var(--ln);background:#fff;color:var(--ink2);font-family:inherit;
 font-size:13.5px;font-weight:600;padding:5px 11px;border-radius:999px;cursor:pointer;line-height:1.35}
.gbc-yn .yn-pick:hover{border-color:var(--o);color:var(--o);background:var(--os)}
.gbc-yn .yn-scroll{overflow-x:auto;background:var(--sea);-webkit-overflow-scrolling:touch}
.gbc-yn svg.yn-map{display:block;width:100%;min-width:620px;height:auto}
.gbc-yn .yn-swipe{display:none;text-align:center;font-size:12.5px;color:var(--mut);padding:7px 0 0}
.gbc-yn .komsu{fill:var(--komsu);stroke:var(--landln);stroke-width:1.2}
.gbc-yn .denizk{fill:var(--sea);stroke:none}
.gbc-yn .yun{fill:var(--yun);stroke:var(--yunln);stroke-width:1.5}
.gbc-yn .ulke{font-size:11px;font-weight:700;letter-spacing:.13em;fill:var(--mut);opacity:.85;pointer-events:none}
.gbc-yn .ulke.yn{font-size:14px;letter-spacing:.2em;fill:var(--yunyazi);opacity:.9}
.gbc-yn .dnz{font-size:12.5px;font-style:italic;letter-spacing:.08em;fill:var(--denizyazi);
 opacity:.95;pointer-events:none;paint-order:stroke;stroke:var(--sea);stroke-width:3.5px}
.gbc-yn .yn-nd{cursor:pointer}
.gbc-yn .yn-nd.yok{cursor:default;pointer-events:none}
.gbc-yn .yn-dot{transition:stroke-width .15s}
.gbc-yn .yn-nd:hover .yn-dot{stroke-width:4}
.gbc-yn .yn-lbl{font-size:12.5px;font-weight:700;fill:var(--ink);paint-order:stroke;
 stroke:var(--sea);stroke-width:3.5px}
.gbc-yn .yn-nd.yok .yn-lbl{font-size:11.5px;font-weight:500;fill:var(--mut)}
.gbc-yn .yn-hint{padding:10px 18px;border-top:1px solid var(--ln2);font-size:13.5px;color:var(--mut)}
.gbc-yn .yn-hint b{color:var(--ink);font-weight:700}
.gbc-yn .yn-kume{margin:26px 0 0}
.gbc-yn .yn-kt{font-size:15px !important;font-weight:800 !important;letter-spacing:.04em;
 text-transform:uppercase;color:var(--o) !important;margin:0 0 12px !important;line-height:1.3 !important}
.gbc-yn .yn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;align-items:start}
.gbc-yn .yn-grid.tek .yn-yer{grid-column:1 / -1}
.gbc-yn .yn-grid.tek .yn-lst{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:6px}
.gbc-yn .yn-kume[data-tip="ada"] .yn-kt{color:var(--mavi) !important}
.gbc-yn .yn-kume[data-tip="doga"] .yn-kt{color:var(--yesil) !important}
.gbc-yn .yn-kume[data-tip="ada"] .yn-yer{border-left:3px solid var(--mavi)}
.gbc-yn .yn-kume[data-tip="kara"] .yn-yer{border-left:3px solid var(--o)}
.gbc-yn .yn-kume[data-tip="doga"] .yn-yer{border-left:3px solid var(--yesil)}
.gbc-yn .yn-kume[data-tip="ada"] .yn-yer:hover{border-color:var(--mavi);box-shadow:0 4px 14px rgba(1,87,155,.12)}
.gbc-yn .yn-kume[data-tip="doga"] .yn-yer:hover{border-color:var(--yesil);box-shadow:0 4px 14px rgba(27,94,32,.12)}
.gbc-yn .yn-kume[data-tip="ada"] a.yn-lnk:hover{background:var(--mavis);border-color:#a9c6dd;color:var(--mavi) !important}
.gbc-yn .yn-kume[data-tip="doga"] a.yn-lnk:hover{background:var(--yesils);border-color:#a9c9ad;color:var(--yesil) !important}
.gbc-yn .yn-pgroup[data-tip="ada"] .yn-plabel{color:var(--mavi)}
.gbc-yn .yn-pgroup[data-tip="doga"] .yn-plabel{color:var(--yesil)}
.gbc-yn .yn-pgroup[data-tip="ada"] .yn-pick:hover{border-color:var(--mavi);color:var(--mavi);background:var(--mavis)}
.gbc-yn .yn-pgroup[data-tip="doga"] .yn-pick:hover{border-color:var(--yesil);color:var(--yesil);background:var(--yesils)}
.gbc-yn .yn-say{margin-left:8px;font-size:12px;font-weight:700;color:var(--mut);letter-spacing:.02em}
.gbc-yn .yn-yer{background:#fff;border:1px solid var(--ln);border-radius:14px;padding:15px 17px 16px}
.gbc-yn .yn-yer:hover{border-color:#f0b48a;box-shadow:0 4px 14px rgba(0,0,0,.06)}
.gbc-yn .yn-yer.on{border-color:var(--o);box-shadow:0 0 0 3px var(--os)}
.gbc-yn .yn-yn{font-size:17px !important;font-weight:800 !important;margin:0 0 10px !important;
 line-height:1.25 !important;color:var(--ink) !important}
.gbc-yn .yn-lst{list-style:none;margin:0 !important;padding:0 !important;display:flex;
 flex-direction:column;gap:6px}
.gbc-yn .yn-lst li{margin:0 !important;padding:0 !important}
.gbc-yn .yn-lst li::before{content:none !important}
.gbc-yn a.yn-lnk{display:block;font-size:15px;color:var(--ink2) !important;text-decoration:none !important;
 padding:5px 9px;border-radius:8px;background:#fafbfb;border:1px solid #f0f0f0;line-height:1.4}
.gbc-yn a.yn-lnk:hover{background:var(--os);border-color:#f0b48a;color:var(--o) !important}
.gbc-yn a.yn-lnk::after{content:none !important}
.gbc-yn .yn-plan{margin:26px 0 0}
.gbc-yn .yn-pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.gbc-yn a.yn-plnk{display:block;background:#fff;border:1px solid var(--ln);border-radius:12px;
 padding:13px 15px;text-decoration:none !important;color:var(--ink) !important}
.gbc-yn a.yn-plnk:hover{border-color:var(--o);background:var(--os)}
.gbc-yn a.yn-plnk::after{content:none !important}
.gbc-yn a.yn-plnk b{display:block;font-size:15.5px;font-weight:800;margin:0 0 4px}
.gbc-yn a.yn-plnk span{display:block;font-size:13.5px;color:var(--ink2);line-height:1.5}
.entry-content .v1-single-content-box p > a:not(.gbc-in):not([class*="gbc-silo"]),
.entry-content ul.gz2-facts a:not(.gbc-in),
.entry-content .gbc-step a:not(.gbc-in),
.entry-content .gz2-note a:not(.gbc-in){color:#BF360C !important;text-decoration:none !important;box-shadow:inset 0 -1px 0 rgba(191,54,12,.35)}
.entry-content .v1-single-content-box p > a:not(.gbc-in):hover,
.entry-content ul.gz2-facts a:not(.gbc-in):hover,
.entry-content .gbc-step a:not(.gbc-in):hover,
.entry-content .gz2-note a:not(.gbc-in):hover{box-shadow:inset 0 -2px 0 #BF360C}
.entry-content ul.gz2-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));flex-wrap:wrap}
.entry-content ul.gz2-facts li{border-right:1px solid #F0DFD6;border-bottom:1px solid #F0DFD6}
.entry-content .gz2-note{background:#FBF3EF;border-left:3px solid #BF360C;border-radius:0 10px 10px 0;padding:13px 16px;margin:18px 0}
.entry-content .gz2-note.gz2-warn{background:#FDF3F3;border-left-color:#B91C1C}
.entry-content .gz2-note.gz2-ok{background:#EDF7F0;border-left-color:#1B7F4B}
.entry-content .gbc-src{display:flex;flex-wrap:wrap;gap:4px 16px;align-items:baseline;padding:12px 16px;background:#FAFBFB;border:1px solid #ECECEC;border-radius:10px;margin:18px 0}
.entry-content .gbc-src .l{flex:0 0 auto;min-width:150px;font-weight:700;color:#7a7a7a;text-align:left;font-size:13px !important;line-height:1.5 !important}
.entry-content .gbc-src .v{flex:1 1 260px;color:#1a1a1a;text-align:left;font-weight:400;font-size:15px !important;line-height:1.6 !important}
@media (max-width:700px){
 .gbc-yn .yn-picker{padding:11px 13px 12px}
 .gbc-yn .yn-plabel{min-width:0;flex:0 0 auto;font-size:11px;padding-top:6px}
 .gbc-yn .yn-pgroup{flex-wrap:nowrap;gap:0 7px}
 .gbc-yn .yn-pick{font-size:12.5px;padding:4px 9px}
 .gbc-yn svg.yn-map{min-width:560px}
 .gbc-yn .yn-swipe{display:block}
 .gbc-yn .yn-grid{grid-template-columns:1fr}
 .gbc-yn .yn-head{padding:12px 14px}
 .gbc-yn .yn-hint{padding:10px 14px}
}
@media (prefers-reduced-motion:reduce){.gbc-yn *{transition:none !important}}
</style>

<div class="gbc-yn" id="gbcYn">
 <div class="yn-card">
  <div class="yn-head">
   <h2 class="yn-t">Yunanistan Haritası: Adalar, Şehirler ve Rehberler</h2><span class="yn-tsub">Bir yere tıkla, o bölgenin rehberlerine git</span>
  </div>
  <div class="yn-picker" id="ynPicker"></div>
  <div class="yn-swipe">haritayı yana kaydır</div>
  <div class="yn-scroll"><svg class="yn-map" id="ynMap" role="img" aria-label="Yunanistan haritasi: adalar, ana kara sehirleri ve rehberi olan yerler"></svg></div>
  <div class="yn-hint" id="ynHint"></div>
 </div>
 <div id="ynKumeler"></div>
 <div class="yn-plan" id="ynPlan"></div>
</div>
GBCYNHTML;
		return $s;
	}
}

if ( ! function_exists( 'gbc_yn_js' ) ) {
	function gbc_yn_js() {
		return <<<'GBCYNJS'
<script>
(function(){
 var el=document.getElementById("gbc-yn-veri"); if(!el) return;
 var D; try{ D=JSON.parse(el.textContent); }catch(e){ return; }
 var Y=D.yer, KUME=D.kume, REH=D.rehber, GEO=D.geo;
 var root=document.getElementById("gbcYn"), svg=document.getElementById("ynMap");
 if(!root||!svg) return;

 var W=1000,PL=38,PR=38,PT=46,PB=30;
 var LO0=19.40,LO1=30.10,LA0=34.80,LA1=42.00;
 var SX=(W-PL-PR)/(LO1-LO0), SY=SX*1.27;
 var H=Math.round(PT+PB+(LA1-LA0)*SY);
 function X(lo){return PL+(lo-LO0)*SX;}
 function Y2(la){return PT+(LA1-la)*SY;}
 function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}

 function poly(pts){
  var P=pts.map(function(p){return [X(p[0]),Y2(p[1])];});
  var n=P.length,t=0.15,d="M "+P[0][0].toFixed(1)+" "+P[0][1].toFixed(1);
  for(var i=0;i<n;i++){
   var p0=P[(i-1+n)%n],p1=P[i],p2=P[(i+1)%n],p3=P[(i+2)%n];
   var c1=[p1[0]+(p2[0]-p0[0])*t,p1[1]+(p2[1]-p0[1])*t];
   var c2=[p2[0]-(p3[0]-p1[0])*t,p2[1]-(p3[1]-p1[1])*t];
   d+=" C "+c1[0].toFixed(1)+" "+c1[1].toFixed(1)+", "+c2[0].toFixed(1)+" "+c2[1].toFixed(1)+", "+p2[0].toFixed(1)+" "+p2[1].toFixed(1);
  }
  return d+" Z";
 }

 svg.setAttribute("viewBox","0 0 "+W+" "+H);
 var s='<path class="komsu" d="'+poly(GEO.komsu)+'"></path>';
 s+='<path class="denizk" d="'+poly(GEO.deniz)+'"></path>';
 s+='<path class="yun" d="'+poly(GEO.yun)+'"></path>';
 s+='<path class="yun" d="'+poly(GEO.mora)+'"></path>';
 GEO.ada.forEach(function(a){ s+='<path class="yun" d="'+poly(a[1])+'"></path>'; });
 function ulke(t,lo,la,c){return '<text class="ulke'+(c||"")+'" x="'+X(lo).toFixed(1)+'" y="'+Y2(la).toFixed(1)+'" text-anchor="middle">'+t+'</text>';}
 function dnz(t,lo,la){return '<text class="dnz" x="'+X(lo).toFixed(1)+'" y="'+Y2(la).toFixed(1)+'" text-anchor="middle">'+t+'</text>';}
 s+=ulke("BULGAR&#304;STAN",24.60,41.85)+ulke("KUZEY MAKEDONYA",21.60,41.72)+ulke("ARNAVUTLUK",19.95,40.75)
  +ulke("T&#220;RK&#304;YE",28.70,39.30)+ulke("YUNAN&#304;STAN",21.90,39.90," yn");
 s+=dnz("EGE DEN&#304;Z&#304;",25.75,38.95)+dnz("&#304;YON DEN&#304;Z&#304;",19.95,38.30)+dnz("AKDEN&#304;Z",23.30,35.35);

 s+='<g id="ynNodes">';
 Object.keys(Y).forEach(function(id){
  var p=Y[id], x=X(p.lo), y=Y2(p.la), var_=(p.s&&p.s.length>0);
  var r=var_?6:4;
  var fill=var_?"#E65100":"#ffffff";
  var st=var_?"#E65100":"#9aa39a";
  var lp=p.lp||"r", off=r+7;
  var LX=(lp==="l")?x-off:(lp==="r")?x+off:x;
  var LY=(lp==="t")?y-off-3:(lp==="b")?y+off+10:y+4.5;
  var AN=(lp==="l")?"end":(lp==="r")?"start":"middle";
  s+='<g class="yn-nd'+(var_?"":" yok")+'" data-id="'+id+'"'+(var_?' tabindex="0" role="button" aria-label="'+esc(p.n)+'"':"")+'>';
  if(var_) s+='<circle cx="'+x.toFixed(1)+'" cy="'+y.toFixed(1)+'" r="16" fill="transparent"></circle>';
  s+='<circle class="yn-dot" cx="'+x.toFixed(1)+'" cy="'+y.toFixed(1)+'" r="'+r+'" fill="'+fill+'" stroke="'+st+'" stroke-width="2"></circle>';
  s+='<text class="yn-lbl" x="'+LX.toFixed(1)+'" y="'+LY.toFixed(1)+'" text-anchor="'+AN+'">'+esc(p.n)+'</text>';
  s+='</g>';
 });
 s+='</g>';
 svg.innerHTML=s;

 var TIP={"Attika":"kara","Kuzey Yunanistan":"kara","Bati Trakya":"kara","Orta Yunanistan":"doga","Mora":"doga"};
 function tip(k){
  if(TIP[k]) return TIP[k];
  if(k.indexOf("Trakya")>-1) return "kara";
  if(k.indexOf("Ada")>-1||k.indexOf("ada")>-1||k.indexOf("Kikladlar")>-1||k.indexOf("Onikiadalar")>-1||k.indexOf("Girit")>-1) return "ada";
  return "kara";
 }
 var ids=Object.keys(Y);
 var dolu=ids.filter(function(id){return Y[id].s&&Y[id].s.length>0;});
 var sayfaSayisi=0; dolu.forEach(function(id){sayfaSayisi+=Y[id].s.length;});

 var ph="";
 KUME.forEach(function(k){
  var list=dolu.filter(function(id){return Y[id].k===k;})
   .sort(function(a,b){return Y[a].n.localeCompare(Y[b].n,"tr");});
  if(!list.length) return;
  ph+='<div class="yn-pgroup" data-tip="'+tip(k)+'"><span class="yn-plabel">'+esc(k)+'</span><div class="yn-prow">'+list.map(function(id){
   return '<button class="yn-pick" type="button" data-p="'+id+'">'+esc(Y[id].n)+'</button>';
  }).join("")+'</div></div>';
 });
 document.getElementById("ynPicker").innerHTML=ph;

 var kh="";
 KUME.forEach(function(k){
  var list=dolu.filter(function(id){return Y[id].k===k;})
   .sort(function(a,b){return (Y[b].s.length-Y[a].s.length)||Y[a].n.localeCompare(Y[b].n,"tr");});
  if(!list.length) return;
  kh+='<section class="yn-kume" data-tip="'+tip(k)+'"><h3 class="yn-kt">'+esc(k)+'</h3><div class="yn-grid'+((list.length===1&&Y[list[0]].s.length>=3)?" tek":"")+'">';
  list.forEach(function(id){
   var p=Y[id];
   kh+='<article class="yn-yer" id="yn-'+id+'"><h4 class="yn-yn">'+esc(p.n)+'<span class="yn-say">'+p.s.length+' rehber</span></h4><ul class="yn-lst">';
   p.s.forEach(function(x){ kh+='<li><a class="yn-lnk" href="'+esc(x.u)+'">'+esc(x.t)+'</a></li>'; });
   kh+='</ul></article>';
  });
  kh+='</div></section>';
 });
 document.getElementById("ynKumeler").innerHTML=kh;

 if(REH&&REH.length){
  var rh='<h3 class="yn-kt">Gitmeden Önce Okunacak Rehberler</h3><div class="yn-pgrid">';
  REH.forEach(function(r){ rh+='<a class="yn-plnk" href="'+esc(r.u)+'"><b>'+esc(r.t)+'</b><span>'+esc(r.d)+'</span></a>'; });
  rh+='</div>';
  document.getElementById("ynPlan").innerHTML=rh;
 }

 document.getElementById("ynHint").innerHTML="<b>"+dolu.length+" yer, "+sayfaSayisi+" rehber</b> · haritadaki turuncu noktaların rehberi hazır, gri noktalar sırada";

 function git(id){
  var k=document.getElementById("yn-"+id);
  if(!k) return;
  root.querySelectorAll(".yn-yer.on").forEach(function(x){x.classList.remove("on");});
  k.classList.add("on");
  k.scrollIntoView({behavior:"smooth",block:"center"});
 }
 svg.addEventListener("click",function(e){
  var g=e.target.closest?e.target.closest(".yn-nd"):null;
  if(g&&!g.classList.contains("yok")) git(g.getAttribute("data-id"));
 });
 svg.addEventListener("keydown",function(e){
  if(e.key==="Enter"||e.key===" "){ var g=e.target.closest?e.target.closest(".yn-nd"):null;
   if(g&&!g.classList.contains("yok")){ e.preventDefault(); git(g.getAttribute("data-id")); } }
 });
 root.addEventListener("click",function(e){
  var b=e.target.closest(".yn-pick");
  if(b) git(b.getAttribute("data-p"));
 });
})();
</script>
GBCYNJS;
	}
}

/* Cikti YOK. Bu modul kisa kod tipidir: gbc-core.php onu
   [gbc_...] kisa koduna ve [wpcode id=...] cagrisina baglar. */
