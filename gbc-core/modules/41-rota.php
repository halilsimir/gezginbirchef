<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30696 — Rota Motoru. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 63 · Rota Motoru  [gbc_rota]   —  v3
 * ------------------------------------------------------------------
 * Gezi rehberine tek satırla girer:  [gbc_rota]
 * Yan sütun / dar alan için:         [gbc_rota mod=dar]
 *
 * Veri (bulunduğu yazının post meta'sı):
 *   gbc_rota_duraklar : Akropolis [Akropolis]
 *                       Akropolis Müzesi [Akropolis, Plaka]
 *                       Sounion Burnu (Tam Gün) [Sounion]
 *   gbc_rota_yemek    : Ariston (Sabah) [Syntagma, Ermou]
 *                       Kostas (Öğle) [Syntagma, Ermou]
 *                       Lukumades (Tatlı) [Ermou, Monastiraki]
 *                       To Steki Tou Ilia (Akşam) [Akropolis]
 *
 * v3 DEĞİŞİKLİKLERİ
 * - Tıklayınca SAYFA YENİLENMİYOR. Plan yerinde, anında kuruluyor (JS).
 * - JS yoksa bağlantılar yine çalışıyor (GET) — kademeli geliştirme.
 * - Çok daha derli toplu: tek satır seçim şeridi, sıkı gün kartları,
 *   geniş ekranda iki kolon, dar alanda tek kolon.
 * - Gün pilleri mobilde alt alta yığılmıyor, yatay kayan şerit oluyor.
 *
 * ÖLÇÜLMÜŞ KISIT (değiştirme): Gezi şablonu 22607 içerik alanlarını
 * wp_kses'ten geçiriyor; <form>, <select>, <style>, <script> SİLİNİYOR.
 * Bu yüzden seçim <a> ile, CSS wp_head'e, JS ve JSON-LD wp_footer'a basılıyor.
 */

if ( ! function_exists( 'gbc_rota_ayristir' ) ) {

	function gbc_rota_ayristir( $metin, $yemek_mi = false ) {
		$satirlar = preg_split( '/\r\n|\r|\n/u', (string) $metin );
		$cikti    = array();

		foreach ( $satirlar as $satir ) {
			$satir = trim( $satir );
			if ( '' === $satir ) { continue; }

			$bolgeler = array();
			if ( preg_match( '/\[(.*?)\]/u', $satir, $e ) ) {
				$ham      = mb_strtolower( trim( $e[1] ), 'UTF-8' );
				$bolgeler = array_values( array_filter( array_map( 'trim', preg_split( '/[,|\/&]+/u', $ham ) ) ) );
				$satir    = trim( str_replace( $e[0], '', $satir ) );
			}
			if ( empty( $bolgeler ) ) { continue; }

			$ogun = 'aksam'; $tam_gun = false;

			if ( $yemek_mi ) {
				if ( preg_match( '/\((.*?)\)/u', $satir, $o ) ) {
					$et = mb_strtolower( $o[1], 'UTF-8' );
					if ( false !== mb_strpos( $et, 'sabah' ) )     { $ogun = 'sabah'; }
					elseif ( false !== mb_strpos( $et, 'öğle' ) )  { $ogun = 'ogle'; }
					elseif ( false !== mb_strpos( $et, 'ogle' ) )  { $ogun = 'ogle'; }
					elseif ( false !== mb_strpos( $et, 'tatlı' ) ) { $ogun = 'tatli'; }
					elseif ( false !== mb_strpos( $et, 'tatli' ) ) { $ogun = 'tatli'; }
					$satir = trim( str_replace( $o[0], '', $satir ) );
				}
			} elseif ( preg_match( '/\(\s*tam\s*g[üu]n\s*\)/iu', $satir ) ) {
				$tam_gun = true;
				$satir   = trim( preg_replace( '/\(\s*tam\s*g[üu]n\s*\)/iu', '', $satir ) );
			}

			$satir = trim( preg_replace( '/\s{2,}/u', ' ', $satir ) );
			if ( '' === $satir ) { continue; }

			$cikti[] = array( 'ad' => $satir, 'bolgeler' => $bolgeler, 'ana' => $bolgeler[0], 'ogun' => $ogun, 'tam_gun' => $tam_gun );
		}
		return $cikti;
	}

	function gbc_rota_sec( &$havuz, $bolgeler, $ogun = null ) {
		foreach ( $havuz as $i => $o ) {
			if ( $ogun && $o['ogun'] !== $ogun ) { continue; }
			if ( $o['ana'] === $bolgeler[0] ) { unset( $havuz[ $i ] ); $havuz = array_values( $havuz ); return $o; }
		}
		foreach ( $havuz as $i => $o ) {
			if ( $ogun && $o['ogun'] !== $ogun ) { continue; }
			if ( array_intersect( $o['bolgeler'], $bolgeler ) ) { unset( $havuz[ $i ] ); $havuz = array_values( $havuz ); return $o; }
		}
		return null;
	}

	function gbc_rota_ogun_adi( $k ) {
		$m = array( 'sabah' => 'Kahvaltı', 'ogle' => 'Öğle', 'tatli' => 'Tatlı', 'aksam' => 'Akşam' );
		return isset( $m[ $k ] ) ? $m[ $k ] : 'Öğün';
	}

	function gbc_rota_bas_harf( $s ) {
		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( mb_substr( $s, 0, 1, 'UTF-8' ), MB_CASE_UPPER, 'UTF-8' ) . mb_substr( $s, 1, null, 'UTF-8' );
		}
		return ucfirst( $s );
	}

	/** Gün kartlarını üretir; şemayı da referansla döndürür. */
	function gbc_rota_gunler( $gezi, $yeme, $gun, $ogun, &$sema ) {
		$plan  = array( 0 => array(), 1 => array( 'aksam' ), 2 => array( 'ogle', 'aksam' ), 3 => array( 'sabah', 'ogle', 'tatli', 'aksam' ) );
		$ogunler = isset( $plan[ $ogun ] ) ? $plan[ $ogun ] : array();
		$kart = ''; $sira = 0; $kullanilan = array();

		for ( $g = 1; $g <= $gun; $g++ ) {
			if ( empty( $gezi ) ) { break; }
			$capa   = array_shift( $gezi );
			$bolge  = $capa['bolgeler'];
			$gunluk = array( $capa );

			if ( ! $capa['tam_gun'] ) {
				for ( $e = 0; $e < 3; $e++ ) {
					$ek = gbc_rota_sec( $gezi, $bolge );
					if ( ! $ek ) { break; }
					$gunluk[] = $ek;
					$bolge    = array_unique( array_merge( $bolge, $ek['bolgeler'] ) );
				}
			}

			$adaylar = array();
			foreach ( $gunluk as $d ) { if ( ! in_array( $d['ana'], $adaylar, true ) ) { $adaylar[] = $d['ana']; } }
			$etiket = $adaylar[0];
			foreach ( $adaylar as $ad ) { if ( ! in_array( $ad, $kullanilan, true ) ) { $etiket = $ad; break; } }
			$kullanilan[] = $etiket;

			$kart .= '<div class="gbc-rota-gun"><h3 class="gbc-rota-gun-bas"><span class="gbc-rota-gun-no">' . $g . '. Gün</span> '
				. esc_html( gbc_rota_bas_harf( $etiket ) ) . '</h3><ol class="gbc-rota-liste">';
			foreach ( $gunluk as $d ) {
				$sira++;
				$kart  .= '<li>' . esc_html( $d['ad'] ) . '</li>';
				$sema[] = array( '@type' => 'ListItem', 'position' => $sira, 'name' => $d['ad'] );
			}
			$kart .= '</ol>';

			if ( ! empty( $ogunler ) && ! empty( $yeme ) ) {
				$kart .= '<ul class="gbc-rota-yemek">';
				foreach ( $ogunler as $o ) {
					$s = gbc_rota_sec( $yeme, $bolge, $o );
					$kart .= '<li><b>' . esc_html( gbc_rota_ogun_adi( $o ) ) . '</b> ' . ( $s ? esc_html( $s['ad'] ) : 'Bölgede serbest' ) . '</li>';
				}
				$kart .= '</ul>';
			}
			$kart .= '</div>';
		}
		return $kart;
	}

	function gbc_rota_veri_var( $pid = 0 ) {
		if ( ! $pid ) { $pid = get_queried_object_id(); }
		return $pid && get_post_meta( $pid, 'gbc_rota_duraklar', true );
	}

	/* CSS — wp_head (içerik alanı <style> siliyor) */
	function gbc_rota_css() {
		if ( ! is_singular() || ! gbc_rota_veri_var() ) { return; }
		echo '<style id="gbc-rota-css">'
		. '.gbc-rota{--bk:#BF360C;margin:20px 0;font-size:14px;grid-column:1/-1;width:100%;max-width:100%;box-sizing:border-box}'
		. '.gbc-rota-panel{background:#fff;border:1px solid #ecd9cf;border-radius:14px;padding:12px 14px;box-shadow:0 1px 2px rgba(0,0,0,.04);width:100%;box-sizing:border-box}'
		. '.gbc-rota-sat{display:flex;align-items:center;gap:10px}'
		. '.gbc-rota-sat+.gbc-rota-sat{margin-top:8px;padding-top:8px;border-top:1px solid #f4ece8}'
		. '.gbc-rota-et{flex:0 0 auto;font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#9a6b53;white-space:nowrap}'
		. '.gbc-rota-pils{display:flex;gap:6px;margin:0;padding:2px 0;list-style:none;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}'
		. '.gbc-rota-pils::-webkit-scrollbar{display:none}'
		. '.gbc-rota-pils li{flex:0 0 auto}'
		. '.gbc-rota-pil{display:block;padding:7px 13px;border:1px solid #e6d3c8;border-radius:999px;background:#fff;color:#5a3a2a;font-size:13.5px;line-height:1.25;text-decoration:none;white-space:nowrap;transition:background .12s,border-color .12s,color .12s}'
		. '.gbc-rota-pil:hover{border-color:var(--bk);color:var(--bk)}'
		. '.gbc-rota-pil.acik{background:var(--bk);border-color:var(--bk);color:#fff;font-weight:700}'
		. '.gbc-rota-cikti{margin-top:12px}'
		. '.gbc-rota-gun{border:1px solid #efe7e3;border-radius:10px;padding:11px 13px;background:#fff}'
		. '.gbc-rota-gun+.gbc-rota-gun{margin-top:8px}'
		. '.gbc-rota .gbc-rota-gun .gbc-rota-gun-bas{margin:0 0 8px!important;padding:0!important;font-size:15.5px!important;line-height:1.35!important;color:#2c3e50!important;font-weight:700!important;letter-spacing:0!important;text-transform:none!important;border:0!important}'
		. '.gbc-rota .gbc-rota-gun-no{display:inline-block;background:var(--bk);color:#fff!important;border-radius:5px;padding:2px 8px;font-size:11.5px!important;font-weight:700;margin-right:7px;vertical-align:2px;letter-spacing:.02em}'
		. '.gbc-rota-liste{margin:0;padding-left:18px;line-height:1.65;font-size:13.5px;color:#333}'
		. '.gbc-rota-yemek{list-style:none;margin:8px 0 0;padding:7px 0 0;border-top:1px dashed #eee;font-size:13px;color:#555;line-height:1.7}'
		. '.gbc-rota-yemek b{color:var(--bk);font-weight:700;margin-right:3px}'
		. '.gbc-rota-not{font-size:12px;color:#8a8a8a;margin:9px 0 0}'
		. '@media(min-width:760px){.gbc-rota:not(.gbc-rota-dar) .gbc-rota-cikti{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start}'
		. '.gbc-rota:not(.gbc-rota-dar) .gbc-rota-gun+.gbc-rota-gun{margin-top:0}}'
		. '@media(min-width:1150px){.gbc-rota:not(.gbc-rota-dar) .gbc-rota-cikti{grid-template-columns:repeat(3,minmax(0,1fr))}}'
		. '.gbc-rota-dar .gbc-rota-sat{flex-direction:column;align-items:stretch;gap:6px}'
		. '.gbc-rota-dar .gbc-rota-pil{font-size:13px;padding:6px 11px}'
		. '@media(max-width:600px){.gbc-rota-sat{flex-direction:column;align-items:stretch;gap:6px}.gbc-rota-et{font-size:11px}.gbc-rota-pil{font-size:14px;padding:8px 13px}}'
		. '.gz-k-gun .gz-day-row.gbc-rota-atla{cursor:pointer;position:relative;padding-right:20px;transition:background .12s}'
		. '.gz-k-gun .gz-day-row.gbc-rota-atla:hover{background:#fdf4f0}'
		. '.gz-k-gun .gz-day-row.gbc-rota-atla::after{content:"\2193";position:absolute;right:6px;top:50%;transform:translateY(-50%);color:#BF360C;font-weight:700;font-size:15px;line-height:1}'
		. '.gbc-rota-parla{animation:gbcRotaParla 1.1s ease-out 1}'
		. '@keyframes gbcRotaParla{0%{box-shadow:0 0 0 3px rgba(191,54,12,.35)}100%{box-shadow:0 0 0 3px rgba(191,54,12,0)}}'
		. '</style>';
	}
	add_action( 'wp_head', 'gbc_rota_css', 20 );

	/* JS + JSON-LD — wp_footer (içerik alanı <script> siliyor) */
	function gbc_rota_footer() {
		if ( ! empty( $GLOBALS['gbc_rota_sema'] ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $GLOBALS['gbc_rota_sema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		}
		if ( empty( $GLOBALS['gbc_rota_veri'] ) ) { return; }
		echo '<script id="gbc-rota-js">window.GBC_ROTA=' . wp_json_encode( $GLOBALS['gbc_rota_veri'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';'
			. gbc_rota_js() . '</script>';
	}
	add_action( 'wp_footer', 'gbc_rota_footer', 99 );

	function gbc_rota_js() {
		return <<<'JS'
(function(){var K=window.GBC_ROTA;if(!K)return;var kok=document.getElementById('gbc-rota');if(!kok)return;
var cikti=kok.querySelector('.gbc-rota-cikti');var OG={0:[],1:['aksam'],2:['ogle','aksam'],3:['sabah','ogle','tatli','aksam']};
var AD={sabah:'Kahvaltı',ogle:'Öğle',tatli:'Tatlı',aksam:'Akşam'};
function bas(s){return s.charAt(0).toLocaleUpperCase('tr-TR')+s.slice(1);}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function sec(h,b,o){var i,j;for(i=0;i<h.length;i++){if(o&&h[i].o!==o)continue;if(h[i].z[0]===b[0])return h.splice(i,1)[0];}
for(i=0;i<h.length;i++){if(o&&h[i].o!==o)continue;for(j=0;j<h[i].z.length;j++)if(b.indexOf(h[i].z[j])>-1)return h.splice(i,1)[0];}return null;}
function kur(gun,ogun){var gz=K.g.map(function(x){return{n:x.n,z:x.z.slice(),f:x.f};});var ym=K.y.map(function(x){return{n:x.n,z:x.z.slice(),o:x.o};});
var ol=OG[ogun]||[],kul=[],html='';
for(var d=1;d<=gun;d++){if(!gz.length)break;var ca=gz.shift(),bo=ca.z.slice(),du=[ca];
if(!ca.f){for(var e=0;e<3;e++){var ek=sec(gz,bo);if(!ek)break;du.push(ek);ek.z.forEach(function(z){if(bo.indexOf(z)<0)bo.push(z);});}}
var ad=[];du.forEach(function(x){if(ad.indexOf(x.z[0])<0)ad.push(x.z[0]);});var et=ad[0];
for(var a=0;a<ad.length;a++){if(kul.indexOf(ad[a])<0){et=ad[a];break;}}kul.push(et);
html+='<div class="gbc-rota-gun"><h3 class="gbc-rota-gun-bas"><span class="gbc-rota-gun-no">'+d+'. Gün</span> '+esc(bas(et))+'</h3><ol class="gbc-rota-liste">';
du.forEach(function(x){html+='<li>'+esc(x.n)+'</li>';});html+='</ol>';
if(ol.length&&ym.length){html+='<ul class="gbc-rota-yemek">';ol.forEach(function(o){var s=sec(ym,bo,o);html+='<li><b>'+AD[o]+'</b> '+(s?esc(s.n):'Bölgede serbest')+'</li>';});html+='</ul>';}
html+='</div>';}return html;}
function im(g,v){var l=kok.querySelectorAll('[data-gbc-'+g+']');for(var i=0;i<l.length;i++){var on=l[i].getAttribute('data-gbc-'+g)===String(v);
l[i].className=on?'gbc-rota-pil acik':'gbc-rota-pil';l[i].setAttribute('aria-pressed',on?'true':'false');}}
var su={gun:K.d,ogun:K.m};
function ciz(adres){cikti.innerHTML=kur(su.gun,su.ogun);im('gun',su.gun);im('ogun',su.ogun);
if(adres===false)return;
try{var u=new URL(window.location.href);u.searchParams.set('gun',su.gun);u.searchParams.set('ogun',su.ogun);
history.replaceState(null,'',u.pathname+u.search+'#gbc-rota');}catch(e){}}
kok.addEventListener('click',function(ev){var a=ev.target.closest?ev.target.closest('[data-gbc-gun],[data-gbc-ogun]'):null;
if(!a||!kok.contains(a))return;ev.preventDefault();
if(a.hasAttribute('data-gbc-gun'))su.gun=parseInt(a.getAttribute('data-gbc-gun'),10);
if(a.hasAttribute('data-gbc-ogun'))su.ogun=parseInt(a.getAttribute('data-gbc-ogun'),10);ciz();});
/* Mobilde varsayilan gun sayisi daha az: telefonda 7 gunluk plan okunmuyor.
   URL'de gun/ogun varsa DOKUNULMAZ (paylasilan plan korunur).
   Sunucu masaustu varsayilanini basiyor, Google onu goruyor; daraltma
   yalnizca ekranda ve motor ilk ekranin cok altinda oldugu icin CLS'e girmiyor. */
if(!K.k && K.mob && K.mob<su.gun && window.matchMedia('(max-width:759px)').matches){su.gun=K.mob;ciz(false);}
/* Yan sutun "Atina Kac Gunde Gezilir" karti: sablon link basmiyor (kses),
   satirlari motor devraliyor. Tiklayinca plan aninda kuruluyor ve motora kayiyor. */
var yan=document.querySelectorAll('.gz-k-gun .gz-day-row');
for(var i=0;i<yan.length;i++){(function(row){
var no=row.querySelector('.gz-day-no');var g=no?parseInt(no.textContent.replace(/\D/g,''),10):0;
if(!g)return;row.classList.add('gbc-rota-atla');row.setAttribute('role','button');row.setAttribute('tabindex','0');
row.setAttribute('aria-label',g+' gunluk rotayi ciz');
function git(){su.gun=g;ciz();var y=kok.getBoundingClientRect().top+window.pageYOffset-80;
window.scrollTo({top:y,behavior:'smooth'});kok.classList.remove('gbc-rota-parla');void kok.offsetWidth;kok.classList.add('gbc-rota-parla');}
row.addEventListener('click',git);
row.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();git();}});
})(yan[i]);}
})();
JS;
	}

	function gbc_rota_baglanti( $gun, $ogun ) {
		return esc_url( add_query_arg( array( 'gun' => $gun, 'ogun' => $ogun ), get_permalink() ) ) . '#gbc-rota';
	}

	function gbc_rota_shortcode( $atts ) {
		$a = shortcode_atts( array( 'gun' => 3, 'ogun' => 2, 'mobilgun' => 1, 'maxgun' => 10, 'mod' => '', 'baslik' => '' ), $atts, 'gbc_rota' );

		$pid      = get_the_ID();
		if ( function_exists( 'gbc_rp_shortcode' ) && get_post_meta( $pid, 'gbc_rota_plan', true ) ) { return gbc_rp_shortcode( $a, $pid ); }
		$duraklar = get_post_meta( $pid, 'gbc_rota_duraklar', true );
		$yemekler = get_post_meta( $pid, 'gbc_rota_yemek', true );
		if ( empty( $duraklar ) ) { return ''; }

		$max  = max( 1, min( 15, intval( $a['maxgun'] ) ) );
		$dar  = ( 'dar' === $a['mod'] );
		$gun  = isset( $_GET['gun'] )  ? intval( $_GET['gun'] )  : intval( $a['gun'] );
		$ogun = isset( $_GET['ogun'] ) ? intval( $_GET['ogun'] ) : intval( $a['ogun'] );
		$gun  = max( 1, min( $max, $gun ) );
		$ogun = max( 0, min( 3, $ogun ) );

		$gezi = gbc_rota_ayristir( $duraklar, false );
		$yeme = gbc_rota_ayristir( $yemekler, true );
		if ( empty( $gezi ) ) { return ''; }

		// JS'in aynı planı kurabilmesi için ayrıştırılmış veri
		$GLOBALS['gbc_rota_veri'] = array(
			'd' => $gun,
			'm' => $ogun,
			// k=1 ise gun/ogun URL'den geldi, dokunma. k=0 ise varsayilan, mobilde daraltilabilir.
			'k' => ( isset( $_GET['gun'] ) || isset( $_GET['ogun'] ) ) ? 1 : 0,
			'mob' => max( 1, min( 15, intval( $a['mobilgun'] ) ) ),
			'g' => array_map( function( $x ) { return array( 'n' => $x['ad'], 'z' => $x['bolgeler'], 'f' => $x['tam_gun'] ? 1 : 0 ); }, $gezi ),
			'y' => array_map( function( $x ) { return array( 'n' => $x['ad'], 'z' => $x['bolgeler'], 'o' => $x['ogun'] ); }, $yeme ),
		);

		$sema = array();
		$kart = gbc_rota_gunler( $gezi, $yeme, $gun, $ogun, $sema );

		if ( ! empty( $sema ) ) {
			$bas = $a['baslik'] ? $a['baslik'] : get_the_title( $pid );
			$GLOBALS['gbc_rota_sema'] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'name'            => $bas . ' - ' . $gun . ' günlük rota',
				'numberOfItems'   => count( $sema ),
				'itemListElement' => $sema,
			);
		}

		$pil_gun = '';
		for ( $d = 1; $d <= $max; $d++ ) {
			$pil_gun .= '<li><a class="gbc-rota-pil' . ( $d === $gun ? ' acik' : '' ) . '" data-gbc-gun="' . $d . '" rel="nofollow"'
				. ' aria-pressed="' . ( $d === $gun ? 'true' : 'false' ) . '" href="' . gbc_rota_baglanti( $d, $ogun ) . '">' . $d . '</a></li>';
		}
		$etiketler = array( 0 => 'Yok', 1 => 'Akşam', 2 => 'Öğle + akşam', 3 => 'Hepsi' );
		$pil_ogun  = '';
		foreach ( $etiketler as $k => $et ) {
			$pil_ogun .= '<li><a class="gbc-rota-pil' . ( $k === $ogun ? ' acik' : '' ) . '" data-gbc-ogun="' . $k . '" rel="nofollow"'
				. ' aria-pressed="' . ( $k === $ogun ? 'true' : 'false' ) . '" href="' . gbc_rota_baglanti( $gun, $k ) . '">' . esc_html( $et ) . '</a></li>';
		}

		return '<div class="gbc-rota' . ( $dar ? ' gbc-rota-dar' : '' ) . '" id="gbc-rota" data-gun="' . $gun . '">'
			. '<div class="gbc-rota-panel">'
			. '<div class="gbc-rota-sat"><span class="gbc-rota-et">Kaç gün</span><ul class="gbc-rota-pils">' . $pil_gun . '</ul></div>'
			. '<div class="gbc-rota-sat"><span class="gbc-rota-et">Yemek</span><ul class="gbc-rota-pils">' . $pil_ogun . '</ul></div>'
			. '</div>'
			. '<div class="gbc-rota-cikti">' . $kart . '</div>'
			. '<p class="gbc-rota-not">Duraklar yürüme mesafesine göre günlere bölünüyor.</p>'
			. '</div>';
	}

	add_shortcode( 'gbc_rota', 'gbc_rota_shortcode' );
}

/* ------------------------------------------------------------------
 * v4 · PLAN MODU  (meta: gbc_rota_plan)
 * Rota, sayfadaki "Kaç günde gezilir" rotasına göre elle kurulur.
 * Biçim:
 *   ## 1g | Tek gün başlığı        (yalnız 1 gün seçilince)
 *   ## 1 | Gün başlığı              (sayı = öncelik; N gün seçilince en düşük N blok girer)
 *   Durak adı > /site-ici/adres/#kart
 *   @sabah Mekân > /atina-ne-yenir/#place-1   (sabah, ogle, tatli, aksam)
 * Bloklar dosyadaki sırayla gösterilir. Bağlantısız satır düz yazı basılır.
 * Eşleşmeyen öğün yazılmaz ("Bölgede serbest" yok).
 * ------------------------------------------------------------------ */
if ( ! function_exists( 'gbc_rp_shortcode' ) ) {

	function gbc_rp_url( $u ) {
		$u = trim( (string) $u );
		if ( '' === $u ) { return ''; }
		if ( 0 === strpos( $u, '/' ) ) { $u = home_url( $u ); }
		return esc_url_raw( $u );
	}

	function gbc_rp_satir( $s ) {
		$p = explode( '>', $s, 2 );
		return array( trim( $p[0] ), isset( $p[1] ) ? gbc_rp_url( $p[1] ) : '' );
	}

	function gbc_rp_oku( $metin ) {
		$metin   = html_entity_decode( (string) $metin, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$bloklar = array();
		$i = -1;
		foreach ( preg_split( '/\r\n|\r|\n/u', (string) $metin ) as $s ) {
			$s = trim( $s );
			if ( '' === $s ) { continue; }
			if ( 0 === strpos( $s, '##' ) ) {
				$p = explode( '|', trim( substr( $s, 2 ) ), 2 );
				$bloklar[] = array( 'k' => trim( $p[0] ), 't' => isset( $p[1] ) ? trim( $p[1] ) : '', 'd' => array(), 'y' => array() );
				$i = count( $bloklar ) - 1;
				continue;
			}
			if ( $i < 0 ) { continue; }
			if ( 0 === strpos( $s, '@' ) ) {
				$p = preg_split( '/\s+/u', substr( $s, 1 ), 2 );
				$og = strtolower( $p[0] );
				if ( isset( $p[1] ) && in_array( $og, array( 'sabah', 'ogle', 'tatli', 'aksam' ), true ) ) {
					$bloklar[ $i ]['y'][ $og ] = gbc_rp_satir( $p[1] );
				}
				continue;
			}
			$bloklar[ $i ]['d'][] = gbc_rp_satir( $s );
		}
		return $bloklar;
	}

	function gbc_rp_sayili( $bloklar ) {
		$n = 0;
		foreach ( $bloklar as $b ) { if ( ctype_digit( $b['k'] ) ) { $n++; } }
		return $n;
	}

	function gbc_rp_sec( $bloklar, $gun ) {
		if ( 1 === $gun ) {
			foreach ( $bloklar as $b ) { if ( '1g' === $b['k'] ) { return array( $b ); } }
		}
		$say = array();
		foreach ( $bloklar as $idx => $b ) { if ( ctype_digit( $b['k'] ) ) { $say[ $idx ] = intval( $b['k'] ); } }
		asort( $say );
		$al = array_slice( array_keys( $say ), 0, $gun );
		sort( $al );
		$cikti = array();
		foreach ( $al as $idx ) { $cikti[] = $bloklar[ $idx ]; }
		return $cikti;
	}

	function gbc_rp_link( $x ) {
		$ad = esc_html( $x[0] );
		if ( empty( $x[1] ) ) { return '<span class="gbc-rp-ad">' . $ad . '</span>'; }
		$host = wp_parse_url( $x[1], PHP_URL_HOST );
		$ic   = ( ! $host || $host === wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $ic ) {
			return '<a class="gbc-rp-a" href="' . esc_url( $x[1] ) . '">' . $ad . '<span class="gz-ok-ic" aria-hidden="true"></span></a>';
		}
		return '<a class="gbc-rp-a" href="' . esc_url( $x[1] ) . '" target="_blank" rel="noopener">' . $ad . '<span class="gz-ok-dis" aria-hidden="true"></span></a>';
	}

	function gbc_rp_html( $secili, $ogun, &$sema ) {
		$plan = array( 0 => array(), 1 => array( 'aksam' ), 2 => array( 'ogle', 'aksam' ), 3 => array( 'sabah', 'ogle', 'tatli', 'aksam' ) );
		$ad   = array( 'sabah' => 'Kahvaltı', 'ogle' => 'Öğle', 'tatli' => 'Tatlı', 'aksam' => 'Akşam' );
		$og   = isset( $plan[ $ogun ] ) ? $plan[ $ogun ] : array();
		$h = ''; $sira = 0;
		foreach ( $secili as $g => $b ) {
			$h .= '<div class="gbc-rota-gun"><h3 class="gbc-rota-gun-bas"><span class="gbc-rota-gun-no">' . ( $g + 1 ) . '. Gün</span> ' . esc_html( $b['t'] ) . '</h3><ol class="gbc-rota-liste">';
			foreach ( $b['d'] as $d ) {
				$sira++;
				$h .= '<li>' . gbc_rp_link( $d ) . '</li>';
				$it = array( '@type' => 'ListItem', 'position' => $sira, 'name' => $d[0] );
				if ( ! empty( $d[1] ) ) { $it['url'] = $d[1]; }
				$sema[] = $it;
			}
			$h .= '</ol>';
			$y = '';
			foreach ( $og as $o ) {
				if ( empty( $b['y'][ $o ] ) ) { continue; }
				$y .= '<li><b>' . $ad[ $o ] . '</b> ' . gbc_rp_link( $b['y'][ $o ] ) . '</li>';
			}
			if ( $y ) { $h .= '<ul class="gbc-rota-yemek">' . $y . '</ul>'; }
			$h .= '</div>';
		}
		return $h;
	}

	function gbc_rp_shortcode( $a, $pid ) {
		$bloklar = gbc_rp_oku( get_post_meta( $pid, 'gbc_rota_plan', true ) );
		$max     = gbc_rp_sayili( $bloklar );
		if ( ! $max ) { return ''; }
		$gun  = isset( $_GET['gun'] ) ? intval( $_GET['gun'] ) : intval( $a['gun'] );
		$ogun = isset( $_GET['ogun'] ) ? intval( $_GET['ogun'] ) : intval( $a['ogun'] );
		$gun  = max( 1, min( $max, $gun ) );
		$ogun = max( 0, min( 3, $ogun ) );

		$GLOBALS['gbc_rp_veri'] = array(
			'd'   => $gun,
			'm'   => $ogun,
			'k'   => ( isset( $_GET['gun'] ) || isset( $_GET['ogun'] ) ) ? 1 : 0,
			'mob' => max( 1, min( $max, intval( $a['mobilgun'] ) ) ),
			'b'   => $bloklar,
			'h'   => wp_parse_url( home_url(), PHP_URL_HOST ),
		);

		$sema = array();
		$kart = gbc_rp_html( gbc_rp_sec( $bloklar, $gun ), $ogun, $sema );
		if ( $sema ) {
			$GLOBALS['gbc_rota_sema'] = array(
				'@context' => 'https://schema.org', '@type' => 'ItemList',
				'name' => get_the_title( $pid ) . ' - ' . $gun . ' günlük rota',
				'numberOfItems' => count( $sema ), 'itemListElement' => $sema,
			);
		}

		$pg = '';
		for ( $d = 1; $d <= $max; $d++ ) {
			$pg .= '<li><a class="gbc-rota-pil' . ( $d === $gun ? ' acik' : '' ) . '" data-gbc-gun="' . $d . '" rel="nofollow" aria-pressed="' . ( $d === $gun ? 'true' : 'false' ) . '" href="' . gbc_rota_baglanti( $d, $ogun ) . '">' . $d . '</a></li>';
		}
		$po = '';
		foreach ( array( 0 => 'Yok', 1 => 'Akşam', 2 => 'Öğle + akşam', 3 => 'Hepsi' ) as $k => $et ) {
			$po .= '<li><a class="gbc-rota-pil' . ( $k === $ogun ? ' acik' : '' ) . '" data-gbc-ogun="' . $k . '" rel="nofollow" aria-pressed="' . ( $k === $ogun ? 'true' : 'false' ) . '" href="' . gbc_rota_baglanti( $gun, $k ) . '">' . esc_html( $et ) . '</a></li>';
		}
		return '<div class="gbc-rota gbc-rota-v4" id="gbc-rota" data-gun="' . $gun . '">'
			. '<div class="gbc-rota-panel">'
			. '<div class="gbc-rota-sat"><span class="gbc-rota-et">Kaç gün</span><ul class="gbc-rota-pils">' . $pg . '</ul></div>'
			. '<div class="gbc-rota-sat"><span class="gbc-rota-et">Yemek</span><ul class="gbc-rota-pils">' . $po . '</ul></div>'
			. '</div><div class="gbc-rota-cikti">' . $kart . '</div></div>';
	}

	function gbc_rp_css() {
		if ( ! is_singular() || ! get_post_meta( get_queried_object_id(), 'gbc_rota_plan', true ) ) { return; }
		echo '<style id="gbc-rp-css">'
		. '.entry-content .gbc-rota-v4{margin:22px 0}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-panel{border:1px solid #e8e8e8;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:14px 18px}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-et{font-family:Montserrat,sans-serif;font-size:12px;font-weight:700;letter-spacing:.04em;color:#6E6E6E;min-width:64px}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-pil{font-size:15px;padding:7px 14px;color:#1f1f1f;border-color:#e0e0e0}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-pil.acik{color:#fff;background:#BF360C;border-color:#BF360C}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-cikti{margin-top:14px}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-gun{border:1px solid #e8e8e8;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:18px 20px 16px;background:#fff}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-gun .gbc-rota-gun-bas{font-family:Montserrat,sans-serif!important;font-size:18px!important;font-weight:800!important;line-height:1.3!important;color:#000!important;margin:0 0 12px!important}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-gun-no{font-family:Montserrat,sans-serif;background:#BF360C;color:#fff!important;border-radius:6px;padding:3px 8px;font-size:12px!important;font-weight:700;margin-right:8px;vertical-align:2px}'
		. '.entry-content .gbc-rota-v4 ol.gbc-rota-liste{list-style:none;counter-reset:gbcrp;margin:0!important;padding:0!important}'
		. '.entry-content .gbc-rota-v4 ol.gbc-rota-liste>li{counter-increment:gbcrp;position:relative;padding-left:34px;margin:0 0 8px!important;font-size:17px;line-height:1.5;color:#000}'
		. '.entry-content .gbc-rota-v4 ol.gbc-rota-liste>li::before{content:counter(gbcrp);position:absolute;left:0;top:1px;width:24px;height:24px;border-radius:50%;background:#BF360C;color:#fff;font:700 12px/24px Montserrat,sans-serif;text-align:center}'
		. '.entry-content .gbc-rota-v4 a.gbc-rp-a{color:#000!important;font-weight:600;text-decoration:none!important;border-bottom:1px solid #F1C7B2}'
		. '.entry-content .gbc-rota-v4 a.gbc-rp-a:hover{color:#BF360C!important;border-bottom-color:#BF360C}'
		. '.entry-content .gbc-rota-v4 ul.gbc-rota-yemek{list-style:none;margin:12px 0 0!important;padding:10px 0 0!important;border-top:1px dashed #e8e8e8}'
		. '.entry-content .gbc-rota-v4 ul.gbc-rota-yemek>li{margin:0 0 6px!important;padding:0!important;font-size:16px;line-height:1.5;color:#000}'
		. '.entry-content .gbc-rota-v4 ul.gbc-rota-yemek>li::before{display:none!important}'
		. '.entry-content .gbc-rota-v4 ul.gbc-rota-yemek b{display:inline-block;min-width:80px;color:#BF360C!important;font-weight:700;margin-right:4px}'
		. '.entry-content .gbc-rota-v4 .gbc-rota-gun+.gbc-rota-gun{margin-top:12px}'
		. '@media(min-width:760px){.entry-content .gbc-rota-v4 .gbc-rota-cikti{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}.entry-content .gbc-rota-v4 .gbc-rota-gun+.gbc-rota-gun{margin-top:0}}'
		. '@media(min-width:1150px){.entry-content .gbc-rota-v4 .gbc-rota-cikti{grid-template-columns:repeat(3,minmax(0,1fr))}}'
		. '@media(max-width:600px){.entry-content .gbc-rota-v4 .gbc-rota-panel{padding:12px 14px}.entry-content .gbc-rota-v4 .gbc-rota-gun{padding:16px}.entry-content .gbc-rota-v4 .gbc-rota-pil{font-size:14px;padding:7px 12px}}'
		. '</style>';
	}
	add_action( 'wp_head', 'gbc_rp_css', 21 );

	function gbc_rp_footer() {
		if ( empty( $GLOBALS['gbc_rp_veri'] ) ) { return; }
		/* Şema (ItemList) eski altbilgi fonksiyonu gbc_rota_footer basıyor; burada tekrar basılmaz. */
		echo '<script id="gbc-rp-js">window.GBC_RP=' . wp_json_encode( $GLOBALS['gbc_rp_veri'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';' . gbc_rp_js() . '</script>';
	}
	add_action( 'wp_footer', 'gbc_rp_footer', 98 );

	function gbc_rp_js() {
		return <<<'JS'
(function(){var K=window.GBC_RP;if(!K)return;var kok=document.getElementById('gbc-rota');if(!kok)return;var cikti=kok.querySelector('.gbc-rota-cikti');
var OG={0:[],1:['aksam'],2:['ogle','aksam'],3:['sabah','ogle','tatli','aksam']};var AD={sabah:'Kahvaltı',ogle:'Öğle',tatli:'Tatlı',aksam:'Akşam'};
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function lk(x){if(!x[1])return '<span class="gbc-rp-ad">'+esc(x[0])+'</span>';var ic=true;try{ic=new URL(x[1]).host===K.h;}catch(e){}
return ic?'<a class="gbc-rp-a" href="'+esc(x[1])+'">'+esc(x[0])+'<span class="gz-ok-ic" aria-hidden="true"></span></a>':'<a class="gbc-rp-a" href="'+esc(x[1])+'" target="_blank" rel="noopener">'+esc(x[0])+'<span class="gz-ok-dis" aria-hidden="true"></span></a>';}
function sec(g){var B=K.b,i;if(g===1){for(i=0;i<B.length;i++)if(B[i].k==='1g')return[B[i]];}
var s=[];for(i=0;i<B.length;i++)if(/^\d+$/.test(B[i].k))s.push([parseInt(B[i].k,10),i]);s.sort(function(a,b){return a[0]-b[0];});
var al=s.slice(0,g).map(function(x){return x[1];}).sort(function(a,b){return a-b;});return al.map(function(i){return B[i];});}
function kur(g,o){var ol=OG[o]||[],h='';sec(g).forEach(function(b,n){h+='<div class="gbc-rota-gun"><h3 class="gbc-rota-gun-bas"><span class="gbc-rota-gun-no">'+(n+1)+'. Gün</span> '+esc(b.t)+'</h3><ol class="gbc-rota-liste">';
b.d.forEach(function(d){h+='<li>'+lk(d)+'</li>';});h+='</ol>';var y='';ol.forEach(function(k){if(b.y&&b.y[k])y+='<li><b>'+AD[k]+'</b> '+lk(b.y[k])+'</li>';});
if(y)h+='<ul class="gbc-rota-yemek">'+y+'</ul>';h+='</div>';});return h;}
function im(g,v){var l=kok.querySelectorAll('[data-gbc-'+g+']');for(var i=0;i<l.length;i++){var on=l[i].getAttribute('data-gbc-'+g)===String(v);l[i].className=on?'gbc-rota-pil acik':'gbc-rota-pil';l[i].setAttribute('aria-pressed',on?'true':'false');}}
var su={gun:K.d,ogun:K.m};
function ciz(adres){cikti.innerHTML=kur(su.gun,su.ogun);im('gun',su.gun);im('ogun',su.ogun);if(adres===false)return;
try{var u=new URL(window.location.href);u.searchParams.set('gun',su.gun);u.searchParams.set('ogun',su.ogun);history.replaceState(null,'',u.pathname+u.search+'#gbc-rota');}catch(e){}}
kok.addEventListener('click',function(ev){var a=ev.target.closest?ev.target.closest('[data-gbc-gun],[data-gbc-ogun]'):null;if(!a||!kok.contains(a))return;ev.preventDefault();
if(a.hasAttribute('data-gbc-gun'))su.gun=parseInt(a.getAttribute('data-gbc-gun'),10);if(a.hasAttribute('data-gbc-ogun'))su.ogun=parseInt(a.getAttribute('data-gbc-ogun'),10);ciz();});
if(!K.k&&K.mob&&K.mob<su.gun&&window.matchMedia('(max-width:759px)').matches){su.gun=K.mob;ciz(false);}
var yan=document.querySelectorAll('.gz-k-gun .gz-day-row');for(var i=0;i<yan.length;i++){(function(row){var no=row.querySelector('.gz-day-no');var g=no?parseInt(no.textContent.replace(/\D/g,''),10):0;if(!g)return;
row.classList.add('gbc-rota-atla');row.setAttribute('role','button');row.setAttribute('tabindex','0');row.setAttribute('aria-label',g+' günlük rotayı çiz');
function git(){su.gun=Math.min(g,kok.querySelectorAll('[data-gbc-gun]').length);ciz();var y=kok.getBoundingClientRect().top+window.pageYOffset-80;window.scrollTo({top:y,behavior:'smooth'});kok.classList.remove('gbc-rota-parla');void kok.offsetWidth;kok.classList.add('gbc-rota-parla');}
row.addEventListener('click',git);row.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();git();}});})(yan[i]);}
})();
JS;
	}
}
