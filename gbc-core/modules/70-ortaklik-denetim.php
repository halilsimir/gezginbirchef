<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30126 — Ortaklık Denetim Paneli. GBC Core'a taşındı, 28 Eylül 2026. */

/* GBC · 94 · IS ORTAKLIGI DENETIM PANELI · v3 · 5 Eylul 2026
   ------------------------------------------------------------------
   NEREYE: WPCode -> PHP Snippet -> Konum: Run Everywhere
   NE YAPAR: WP paneline "Is Ortakligi" menusu ekler:
     1) Defter GOSTERILIR, salt okunur (21 Eylul 2026'dan beri yalnizca 30120 sayfasindan duzenlenir)
     2) Sitedeki BUTUN ortaklik baglantilari tek tabloda: kisa kod ve
        elle yazilmis <a data-aff> baglantilari birlikte
     3) Her baglanti icin: program, ag, URL, kac sayfada, tiklama
        (toplam / son 30 gun), HTTP durumu, uyarilar
     4) (KAPATILDI 21 Eylul 2026) deftere aktarma, sayac sifirlama yok
     5) Ana salter: butun ortaklik baglantilarini kapat

   v3'te duzelenler:
   - TIKLAMA SAYACI NONCE KULLANMIYOR. Sayfa LiteSpeed onbelleginde
     saatlerce dururken HTML'e gomulen nonce eskiyor, wp_verify_nonce
     basarisiz oluyor ve tiklama sessizce dusuyordu; sayac bu yuzden
     neredeyse hep sifir goruluyordu. Artik nonce yok; bot filtresi ve
     id dogrulamasi var. Sayac para hareketi yapmiyor, sadece sayiyor.
   - Gunluk kirilim tutuluyor (son 60 gun), "son 30 gun" sutunu cikti.
   - Tarama artik wpcode snippet'lerini atliyor; kendi kaynak kodumuzu
     "sayfada kullaniliyor" diye listeleyip yalanci kirmizi uyari
     veriyordu.
   - Elle yazilmis baglantilarin href'i ve program adi da okunuyor,
     boylece defterde olmayanlarin da URL'si ve HTTP durumu gorunuyor.
   Geri almak icin bu snippet'i pasif yap. Hicbir icerigi degistirmez. */

if ( ! defined( 'ABSPATH' ) ) { return; }

/* GBC v1.15.0, 28 Eylul 2026: bu ekranin menu kaydi KALDIRILDI.
   Is Ortakligi artik tek yerden yonetiliyor: inc/ortaklik.php ->
   "gbc-ortaklik" sayfasi. Eski adrese (page=gbc-aff-denetim) gelen
   istekler oraya yonlendiriliyor. Bu dosyadaki tarama, tiklama sayaci
   ve baglanti testi fonksiyonlari YENI PANEL TARAFINDAN KULLANILIYOR;
   dosyayi pasife alma.
if ( ! function_exists( 'gbc_aff_panel_menu' ) ) {
function gbc_aff_panel_menu() {
	add_submenu_page( 'gbc', 'GBC İş Ortaklığı Denetimi', 'İş Ortaklığı', 'manage_options', 'gbc-aff-denetim', 'gbc_aff_panel_ekran' );
}
}
*/

/* Defter sayfasi */
if ( ! function_exists( 'gbc_aff_panel_sayfa' ) ) {
function gbc_aff_panel_sayfa() {
	$s = get_page_by_path( 'is-ortakligi-baglanti-defteri', OBJECT, 'page' );
	if ( ! $s || empty( $s->post_content ) ) { $s = get_post( 30120 ); }
	return $s;
}
}

if ( ! function_exists( 'gbc_aff_panel_pre_sinir' ) ) {
function gbc_aff_panel_pre_sinir( $icerik ) {
	/* GBC 21 Eylul 2026: ilk <pre> yerine EN BUYUK <pre> secilir. 30120'de 5 blok var, ilki oran tablosu. GERI ACMA. */ /* ESKI: $b = strpos( $icerik, '<pre>' ); $s = strpos( $icerik, '</pre>' ); return array( $b + 5, $s ); */ $b = false; $s = false; $en_uzun = -1; if ( preg_match_all( '#<pre[^>]*>#i', $icerik, $m, PREG_OFFSET_CAPTURE ) ) { foreach ( $m[0] as $t ) { $bb = $t[1] + strlen( $t[0] ); $ss = stripos( $icerik, '</pre>', $bb ); if ( $ss === false ) { continue; } if ( ( $ss - $bb ) > $en_uzun ) { $en_uzun = $ss - $bb; $b = $bb; $s = $ss; } } }
	
	if ( $b === false || $s === false || $s < $b ) { return null; }
	return array( $b, $s );
}
}

if ( ! function_exists( 'gbc_aff_panel_satirlar' ) ) {
function gbc_aff_panel_satirlar() {
	/* Motor snippet'indeki gbc_aff_defter_oku() statik onbellek tutuyor;
	   panelde kaydettikten sonra eski defteri gosterirdi. Burada her
	   seferinde yeniden okunur. */
	$sayfa = gbc_aff_panel_sayfa();
	if ( ! $sayfa ) { return array(); }
	/* GBC 21 Eylul 2026: yalnizca <pre> bloklari okunur (motor 30204 ile ayni); eskiden butun sayfa metni okunuyordu. */ $ham = ''; if ( preg_match_all( '#<pre[^>]*>(.*?)</pre>#is', $sayfa->post_content, $gbc_pre ) ) { $ham = html_entity_decode( wp_strip_all_tags( implode( PHP_EOL, $gbc_pre[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
	$out = array();
	foreach ( preg_split( '/\R/u', $ham ) as $satir ) {
		$satir = trim( $satir );
		if ( $satir === '' || strpos( $satir, '|' ) === false ) { continue; }
		$p = array_map( 'trim', explode( '|', $satir ) );
		if ( count( $p ) < 4 ) { continue; }
		$id = sanitize_key( $p[0] );
		if ( $id === '' || $id === 'id' ) { continue; }
		if ( $p[3] !== '' && stripos( $p[3], 'http' ) !== 0 ) { continue; }
		$out[ $id ] = array( 'etiket' => $p[1], 'program' => $p[2], 'url' => $p[3], 'ag' => isset( $p[4] ) ? $p[4] : '' );
	}
	return $out;
}
}

if ( ! function_exists( 'gbc_aff_panel_ham' ) ) {
function gbc_aff_panel_ham() {
	$sayfa = gbc_aff_panel_sayfa();
	if ( ! $sayfa ) { return ''; }
	$sinir = gbc_aff_panel_pre_sinir( $sayfa->post_content );
	if ( ! $sinir ) { return ''; }
	$ic = substr( $sayfa->post_content, $sinir[0], $sinir[1] - $sinir[0] );
	return trim( html_entity_decode( $ic, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}
}

if ( ! function_exists( 'gbc_aff_panel_kaydet' ) ) {
function gbc_aff_panel_kaydet( $yeni ) { /* GBC 21 Eylul 2026: panelden defter yazma KAPATILDI. Defter yalnizca 30120 sayfasindan duzenlenir. GERI ACMA. */ return 'Defter bu panelden duzenlenmez. Degisiklikler 30120 numarali sayfadan yapilir.';
	$sayfa = gbc_aff_panel_sayfa();
	if ( ! $sayfa ) { return 'Defter sayfasi bulunamadi.'; }
	$sinir = gbc_aff_panel_pre_sinir( $sayfa->post_content );
	if ( ! $sinir ) { return 'Defter sayfasindaki <pre> blogu bulunamadi.'; }
	$temiz = array();
	foreach ( preg_split( '/\R/u', (string) $yeni ) as $satir ) {
		$satir = trim( wp_strip_all_tags( $satir ) );
		if ( $satir === '' ) { continue; }
		$temiz[] = $satir;
	}
	$govde  = "\n" . implode( "\n", $temiz ) . "\n";
	$icerik = substr( $sayfa->post_content, 0, $sinir[0] ) . $govde . substr( $sayfa->post_content, $sinir[1] );
	$sonuc  = wp_update_post( array( 'ID' => $sayfa->ID, 'post_content' => wp_slash( $icerik ) ), true );
	if ( is_wp_error( $sonuc ) ) { return $sonuc->get_error_message(); }
	return '';
}
}

/* Bir metindeki butun ortaklik baglantilarini cikar.
   Donen dizi: id => array( kaynak, url, program ) */
if ( ! function_exists( 'gbc_aff_panel_idler' ) ) {
function gbc_aff_panel_idler( $metin ) {
	$idler = array();
	$metin = (string) $metin;

	if ( preg_match_all( '#<a\s([^>]*data-aff[^>]*)>#iu', $metin, $ma ) ) {
		foreach ( $ma[1] as $attr ) {
			if ( ! preg_match( '/data-aff\s*=\s*["\']([A-Za-z0-9_-]+)["\']/u', $attr, $k ) ) { continue; }
			$id = sanitize_key( $k[1] );
			if ( $id === '' ) { continue; }
			$url  = preg_match( '/\shref\s*=\s*["\']([^"\']+)["\']/iu', ' ' . $attr, $h ) ? $h[1] : '';
			$prog = preg_match( '/data-prog\s*=\s*["\']([^"\']*)["\']/u', $attr, $p ) ? $p[1] : '';
			$idler[ $id ] = array( 'kaynak' => 'elle', 'url' => $url, 'program' => $prog );
		}
	}
	if ( preg_match_all( '/\[gbc_aff[^\]]*?id\s*=\s*[^A-Za-z0-9_-]*([A-Za-z0-9_-]+)/u', $metin, $m ) ) {
		foreach ( $m[1] as $x ) {
			$x = sanitize_key( $x );
			if ( $x !== '' ) { $idler[ $x ] = array( 'kaynak' => 'kisa kod', 'url' => '', 'program' => '' ); }
		}
	}
	return $idler;
}
}

/* Sitenin tamamini tara. wpcode snippet'leri haric: kendi kaynak kodumuz
   "kullanim" degil. */
if ( ! function_exists( 'gbc_aff_panel_tarama' ) ) {
function gbc_aff_panel_tarama() {
	global $wpdb;
	$bulgu = array();

	$icerikler = $wpdb->get_results(
		"SELECT ID, post_title, post_type, post_status, post_content AS govde
		 FROM {$wpdb->posts}
		 WHERE ( post_content LIKE '%[gbc_aff%' OR post_content LIKE '%data-aff=%' )
		   AND post_type NOT IN ('wpcode','revision')
		   AND post_status NOT IN ('trash','auto-draft','inherit')"
	);
	foreach ( (array) $icerikler as $r ) {
		foreach ( gbc_aff_panel_idler( $r->govde ) as $id => $b ) {
			$bulgu[] = array( 'post' => (int) $r->ID, 'baslik' => $r->post_title, 'tip' => $r->post_type, 'durum' => $r->post_status, 'alan' => 'yazi govdesi', 'id' => $id, 'kaynak' => $b['kaynak'], 'url' => $b['url'], 'program' => $b['program'] );
		}
	}

	$metalar = $wpdb->get_results(
		"SELECT post_id, meta_key, meta_value
		 FROM {$wpdb->postmeta}
		 WHERE meta_value LIKE '%[gbc_aff%' OR meta_value LIKE '%data-aff=%'"
	);
	foreach ( (array) $metalar as $r ) {
		if ( strpos( $r->meta_key, '_' ) === 0 ) { continue; }
		$p = get_post( (int) $r->post_id );
		if ( ! $p || $p->post_type === 'wpcode' ) { continue; }
		if ( in_array( $p->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) { continue; }
		foreach ( gbc_aff_panel_idler( $r->meta_value ) as $id => $b ) {
			$bulgu[] = array( 'post' => (int) $r->post_id, 'baslik' => $p->post_title, 'tip' => $p->post_type, 'durum' => $p->post_status, 'alan' => $r->meta_key, 'id' => $id, 'kaynak' => $b['kaynak'], 'url' => $b['url'], 'program' => $b['program'] );
		}
	}
	/* v5: boru ile ayrilmis alanlar. trip_links ("Ad | URL | not | ortaklik_id")
	   satirlarinda ortaklik id'si 4. kolonda DUZ METIN olarak durur; anahtar
	   sablonda uretildigi icin metada ne data-aff ne de kisa kod gecer.
	   Bu yuzden bu alanlardaki baglantilar "kullanilmiyor" gorunuyordu.
	   Yalanci id uretmemek icin sadece defterde kayitli id'ler sayilir. */
	$defter_ids = gbc_aff_panel_satirlar();
	if ( ! empty( $defter_ids ) ) {
		$boru = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('trip_links')"
		);
		foreach ( (array) $boru as $r ) {
			$p = get_post( (int) $r->post_id );
			if ( ! $p || $p->post_type === 'wpcode' ) { continue; }
			if ( in_array( $p->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) { continue; }
			foreach ( preg_split( '/\R/u', (string) $r->meta_value ) as $satir ) {
				$par = array_map( 'trim', explode( '|', $satir ) );
				if ( count( $par ) < 4 || $par[3] === '' ) { continue; }
				$id = sanitize_key( $par[3] );
				if ( $id === '' || ! isset( $defter_ids[ $id ] ) ) { continue; }
				$bulgu[] = array( 'post' => (int) $r->post_id, 'baslik' => $p->post_title, 'tip' => $p->post_type, 'durum' => $p->post_status, 'alan' => $r->meta_key, 'id' => $id, 'kaynak' => 'boru alani', 'url' => '', 'program' => '' );
			}
		}
	}

	return $bulgu;
}
}

if ( ! function_exists( 'gbc_aff_panel_tarih_var' ) ) {
function gbc_aff_panel_tarih_var( $url ) {
	$u = strtolower( (string) $url );
	if ( $u === '' ) { return false; }
	foreach ( array( 'checkin', 'checkout', 'departuredate', 'returndate', 'date=', 'group_adults' ) as $p ) {
		if ( strpos( $u, $p ) !== false ) { return true; }
	}
	if ( preg_match( '/20[2-9][0-9]-[0-1][0-9]-[0-3][0-9]/', $u ) ) { return true; }
	if ( preg_match( '#/[0-9]{6}/#', $u ) ) { return true; }
	return false;
}
}

/* ---------- TIKLAMA SAYACI ---------- */
add_action( 'wp_footer', 'gbc_aff_tik_js', 99 );
if ( ! function_exists( 'gbc_aff_tik_js' ) ) {
function gbc_aff_tik_js() {
	if ( is_admin() ) { return; }
	echo '<script id="gbc-aff-tik">(function(){var U=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';'
		. 'document.addEventListener("click",function(e){'
		. 'var a=e.target&&e.target.closest?e.target.closest("a[data-aff]"):null;if(!a)return;'
		. 'try{var d=new URLSearchParams();d.append("action","gbc_aff_tik");'
		. 'd.append("id",a.getAttribute("data-aff")||"");d.append("p",a.getAttribute("data-post")||"0");'
		. 'if(navigator.sendBeacon){navigator.sendBeacon(U,d);}else{fetch(U,{method:"POST",body:d,keepalive:true});}'
		. '}catch(x){}},true);})();</script>';
}
}

add_action( 'wp_ajax_gbc_aff_tik', 'gbc_aff_tik_yaz' );
add_action( 'wp_ajax_nopriv_gbc_aff_tik', 'gbc_aff_tik_yaz' );
if ( ! function_exists( 'gbc_aff_tik_yaz' ) ) {
function gbc_aff_tik_yaz() {
	if ( current_user_can( 'edit_posts' ) ) { wp_die( '', '', array( 'response' => 200 ) ); }
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) $_SERVER['HTTP_USER_AGENT'] ) : '';
	if ( $ua === '' ) { wp_die( '', '', array( 'response' => 200 ) ); }
	foreach ( array( 'bot', 'crawl', 'spider', 'slurp', 'headless', 'preview', 'monitor', 'python-requests', 'curl/', 'wget' ) as $bot ) {
		if ( strpos( $ua, $bot ) !== false ) { wp_die( '', '', array( 'response' => 200 ) ); }
	}
	$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	$p  = isset( $_POST['p'] ) ? (int) $_POST['p'] : 0;
	if ( $id === '' || strlen( $id ) > 60 ) { wp_die( '', '', array( 'response' => 200 ) ); }

	$veri = get_option( 'gbc_aff_tik' );
	if ( ! is_array( $veri ) ) { $veri = array(); }
	if ( count( $veri ) > 400 && ! isset( $veri[ $id ] ) ) { wp_die( '', '', array( 'response' => 200 ) ); }
	if ( ! isset( $veri[ $id ] ) || ! is_array( $veri[ $id ] ) ) { $veri[ $id ] = array( 't' => 0, 's' => array(), 'g' => array() ); }
	if ( ! isset( $veri[ $id ]['g'] ) || ! is_array( $veri[ $id ]['g'] ) ) { $veri[ $id ]['g'] = array(); }

	$veri[ $id ]['t'] = (int) $veri[ $id ]['t'] + 1;
	if ( $p > 0 ) {
		if ( ! isset( $veri[ $id ]['s'][ $p ] ) ) { $veri[ $id ]['s'][ $p ] = 0; }
		$veri[ $id ]['s'][ $p ] = (int) $veri[ $id ]['s'][ $p ] + 1;
	}
	$bugun = current_time( 'Y-m-d' );
	if ( ! isset( $veri[ $id ]['g'][ $bugun ] ) ) { $veri[ $id ]['g'][ $bugun ] = 0; }
	$veri[ $id ]['g'][ $bugun ] = (int) $veri[ $id ]['g'][ $bugun ] + 1;
	if ( count( $veri[ $id ]['g'] ) > 70 ) {
		ksort( $veri[ $id ]['g'] );
		$veri[ $id ]['g'] = array_slice( $veri[ $id ]['g'], -60, 60, true );
	}

	update_option( 'gbc_aff_tik', $veri, false );
	wp_die( '', '', array( 'response' => 200 ) );
}
}

if ( ! function_exists( 'gbc_aff_tik_son' ) ) {
function gbc_aff_tik_son( $kayit, $gun = 30 ) {
	if ( empty( $kayit['g'] ) || ! is_array( $kayit['g'] ) ) { return null; }
	$sinir = gmdate( 'Y-m-d', strtotime( '-' . (int) $gun . ' days', strtotime( current_time( 'Y-m-d' ) ) ) );
	$t = 0;
	foreach ( $kayit['g'] as $g => $adet ) {
		if ( $g > $sinir ) { $t += (int) $adet; }
	}
	return $t;
}
}

/* ---------- BAGLANTI TESTI ----------
   Ortaklik aglari (Travelpayouts tpx.li, Impact pxf.io) sunucudan gelen istegi
   bot sanip 403 dondurebiliyor. 403 "link bozuk" demek DEGILDIR; ayni link
   tarayicida acilir. Bu yuzden once yonlendirme TAKIP EDILMEDEN bakilir:
   kisa link 301/302 veriyorsa hedefe yonlendiriyor demektir, en guvenilir
   sinyal budur. Yonlendirme yoksa sonuna kadar takip edilip son kod alinir.
   Istek gercek bir tarayici gibi basliklarla gonderilir. */
if ( ! function_exists( 'gbc_aff_link_test' ) ) {
function gbc_aff_link_test( $u ) {
	$basliklar = array(
		'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language'           => 'tr-TR,tr;q=0.9,en;q=0.8',
		'Upgrade-Insecure-Requests' => '1',
	);
	$tarayici = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	$ayar = array( 'timeout' => 12, 'redirection' => 0, 'user-agent' => $tarayici, 'headers' => $basliklar );
	$c = wp_remote_get( $u, $ayar );
	if ( is_wp_error( $c ) ) { return $c; }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( in_array( $kod, array( 301, 302, 303, 307, 308 ), true ) ) { return $c; }
	$ayar['redirection'] = 5;
	$c2 = wp_remote_get( $u, $ayar );
	return is_wp_error( $c2 ) ? $c : $c2;
}
}

/* Bir id'nin kullanildigi sayfalari tek hucrede listeler. v4:
   eski "4. Nerede kullaniliyor" tablosu bu hucreye tasindi. */
if ( ! function_exists( 'gbc_aff_panel_sayfa_hucre' ) ) {
function gbc_aff_panel_sayfa_hucre( $sayfalar ) {
	$satir = array();
	foreach ( array_keys( (array) $sayfalar ) as $pid ) {
		$pid = (int) $pid;
		$bas = get_the_title( $pid );
		if ( $bas === '' ) { $bas = '#' . $pid; }
		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $bas ) > 42 ) { $bas = mb_substr( $bas, 0, 40 ) . '…'; }
		} elseif ( strlen( $bas ) > 60 ) { $bas = substr( $bas, 0, 58 ) . '…'; }
		$duz = get_post_status( $pid );
		$ek  = ( $duz && $duz !== 'publish' ) ? ' <span class="uy">(' . esc_html( $duz ) . ')</span>' : '';
		$satir[] = '<a href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html( $bas ) . '</a>' . $ek;
	}
	if ( empty( $satir ) ) { return '&mdash;'; }
	return '<small>' . implode( '<br>', $satir ) . '</small>';
}
}

/* v5: Sablonun CALISMA ANINDA urettigi id'ler.
   Otel motoru (snippet 30242) bolge kartlarinin data-aff degerini
   'bud_' . ilce_kodu seklinde birlestirerek uretiyor; id hicbir yerde
   duz metin olarak yazili degil, bu yuzden veritabani taramasi bulamiyor
   ve panel "hicbir sayfada kullanilmiyor" diye YANLIS uyari veriyordu.
   Yeni motor id'si eklersen buraya yaz ya da filtreyi kullan. */
if ( ! function_exists( 'gbc_aff_panel_sablon_idleri' ) ) {
function gbc_aff_panel_sablon_idleri() {
	return (array) apply_filters( 'gbc_aff_sablon_idleri', array(
		'bud_i', 'bud_v', 'bud_vi', 'bud_vii', 'bud_xiii',
	) );
}
}

/* Program kirilimi. v4: eski "3. Programlar" tablosu ozet seridine tasindi. */
if ( ! function_exists( 'gbc_aff_panel_prog_serit' ) ) {
function gbc_aff_panel_prog_serit( $defter, $kul_ozet, $tik ) {
	$hep = array();
	foreach ( (array) $defter as $id => $b ) { $hep[ $id ] = true; }
	foreach ( (array) $kul_ozet as $id => $b ) { $hep[ $id ] = true; }
	foreach ( (array) $tik as $id => $b ) { $hep[ $id ] = true; }
	$oz = array();
	foreach ( array_keys( $hep ) as $id ) {
		$p = ( isset( $defter[ $id ] ) && $defter[ $id ]['program'] !== '' )
			? $defter[ $id ]['program']
			: ( ( isset( $kul_ozet[ $id ] ) && $kul_ozet[ $id ]['program'] !== '' ) ? $kul_ozet[ $id ]['program'] : 'belirtilmemiş' );
		if ( ! isset( $oz[ $p ] ) ) { $oz[ $p ] = array( 'bag' => 0, 'tik' => 0, 'tik30' => 0 ); }
		$oz[ $p ]['bag']++;
		$oz[ $p ]['tik'] += isset( $tik[ $id ]['t'] ) ? (int) $tik[ $id ]['t'] : 0;
		$s = isset( $tik[ $id ] ) ? gbc_aff_tik_son( $tik[ $id ], 30 ) : null;
		$oz[ $p ]['tik30'] += ( $s === null ? 0 : $s );
	}
	if ( empty( $oz ) ) { return ''; }
	uasort( $oz, function ( $a, $b ) {
		if ( $b['tik'] === $a['tik'] ) { return $b['bag'] - $a['bag']; }
		return $b['tik'] - $a['tik'];
	} );
	$h = '<div class="ozet prog">';
	foreach ( $oz as $p => $v ) {
		$h .= '<div><strong>' . (int) $v['tik'] . '</strong><span>' . esc_html( $p ) . ' &middot; ' . (int) $v['bag'] . ' bağlantı &middot; 30 gün ' . (int) $v['tik30'] . '</span></div>';
	}
	return $h . '</div>';
}
}

/* Ek ozet kartlari: kullanilmayan, tarihli, en cok tiklanan. v4 */
if ( ! function_exists( 'gbc_aff_panel_ek_ozet' ) ) {
function gbc_aff_panel_ek_ozet( $defter, $kul_ozet, $tik ) {
	$kullanilmayan = 0;
	$sablon = gbc_aff_panel_sablon_idleri();
	foreach ( $defter as $id => $b ) { if ( ! isset( $kul_ozet[ $id ] ) && ! in_array( $id, $sablon, true ) ) { $kullanilmayan++; } }
	$tarihli = 0;
	foreach ( $defter as $id => $b ) { if ( gbc_aff_panel_tarih_var( $b['url'] ) ) { $tarihli++; } }
	$bos = 0;
	foreach ( $defter as $id => $b ) { if ( $b['url'] === '' ) { $bos++; } }
	$en_id = ''; $en_adet = 0;
	foreach ( (array) $tik as $id => $v ) {
		$a = isset( $v['t'] ) ? (int) $v['t'] : 0;
		if ( $a > $en_adet ) { $en_adet = $a; $en_id = $id; }
	}
	$h  = '<div><strong>' . (int) $kullanilmayan . '</strong><span>defterde var, sayfada yok</span></div>';
	$h .= '<div><strong>' . (int) $bos . '</strong><span>bağlantısı boş</span></div>';
	$h .= '<div><strong style="color:' . ( $tarihli > 0 ? '#b32d2e' : '#1a7f37' ) . '">' . (int) $tarihli . '</strong><span>sabit tarih içeren</span></div>';
	if ( $en_id !== '' ) {
		$h .= '<div style="min-width:200px"><strong>' . (int) $en_adet . '</strong><span>en çok tıklanan: <code>' . esc_html( $en_id ) . '</code></span></div>';
	}
	return $h;
}
}

/* ---------- EKRAN ---------- */
if ( ! function_exists( 'gbc_aff_panel_ekran' ) ) {
function gbc_aff_panel_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$mesaj = '';
	$hata  = '';
	$test  = array();

	/* GBC 21 Eylul 2026: panelden defter yazma KAPATILDI. Defter yalnizca 30120 sayfasindan duzenlenir. GERI ACMA. */ /* Burada yalnizca ana salter (gbc_aff_kapali) kaydedilir. */ if ( isset( $_POST['gbc_aff_salter'] ) && check_admin_referer( 'gbc_aff_panel' ) ) { update_option( 'gbc_aff_kapali', isset( $_POST['gbc_aff_kapali'] ) ? '1' : '' ); $mesaj = isset( $_POST['gbc_aff_kapali'] ) ? 'Ana salter KAPALI: sitede ortaklik baglantisi basilmiyor. LiteSpeed onbellegini temizleyin.' : 'Ana salter ACIK: ortaklik baglantilari basiliyor. LiteSpeed onbellegini temizleyin.'; } if ( false ) { /* eski Defteri kaydet isleyicisi, KAPALI */
		$yeni = isset( $_POST['gbc_aff_defter'] ) ? wp_unslash( $_POST['gbc_aff_defter'] ) : '';
		$h = gbc_aff_panel_kaydet( $yeni );
		update_option( 'gbc_aff_kapali', isset( $_POST['gbc_aff_kapali'] ) ? '1' : '' );
		if ( $h === '' ) { $mesaj = 'Defter kaydedildi. Degisikligin sitede gorunmesi icin LiteSpeed onbellegini temizleyin.'; }
		else { $hata = $h; }
	}

	if ( false ) { /* GBC 21 Eylul 2026: panelden tiklama sayaci sifirlama KAPATILDI; gbc_aff_tik verisi korunur. GERI ACMA. */
		update_option( 'gbc_aff_tik', array(), false );
		update_option( 'gbc_aff_tik_sifir', current_time( 'mysql' ), false );
		$mesaj = 'Tiklama sayaclari sifirlandi.';
	}

	$kullanim = gbc_aff_panel_tarama();

	if ( false ) { /* GBC 21 Eylul 2026: panelden defter yazma KAPATILDI. Defter yalnizca 30120 sayfasindan duzenlenir. GERI ACMA. */ /* eski Defterde olmayanlari aktar isleyicisi */
		$defter_o = gbc_aff_panel_satirlar();
		$ekle = array();
		foreach ( $kullanim as $k ) {
			if ( isset( $defter_o[ $k['id'] ] ) || isset( $ekle[ $k['id'] ] ) ) { continue; }
			if ( $k['url'] === '' ) { continue; }
			$ekle[ $k['id'] ] = array( 'url' => $k['url'], 'program' => $k['program'] );
		}
		if ( empty( $ekle ) ) {
			$mesaj = 'Deftere aktarilacak yeni baglanti yok.';
		} else {
			$satirlar = gbc_aff_panel_ham();
			foreach ( $ekle as $id => $b ) {
				$ag = ( strpos( $b['url'], 'tpx.li' ) !== false ) ? 'Travelpayouts' : ( ( strpos( $b['url'], 'pxf.io' ) !== false ) ? 'Impact' : '' );
				$satirlar .= "\n" . $id . ' | ' . $id . ' | ' . ( $b['program'] !== '' ? $b['program'] : 'BELIRT' ) . ' | ' . $b['url'] . ' | ' . $ag;
			}
			$h = gbc_aff_panel_kaydet( $satirlar );
			if ( $h === '' ) { $mesaj = count( $ekle ) . ' baglanti deftere eklendi. Gorunen metin ve program adi sutunlarini duzeltmeyi unutma.'; }
			else { $hata = $h; }
		}
	}

	$defter = gbc_aff_panel_satirlar();

	$kul_ozet = array();
	foreach ( $kullanim as $k ) {
		if ( ! isset( $kul_ozet[ $k['id'] ] ) ) { $kul_ozet[ $k['id'] ] = array( 'sayfa' => array(), 'url' => '', 'kaynak' => $k['kaynak'], 'program' => '' ); }
		$kul_ozet[ $k['id'] ]['sayfa'][ $k['post'] ] = true;
		if ( $k['url'] !== '' ) { $kul_ozet[ $k['id'] ]['url'] = $k['url']; }
		if ( $k['program'] !== '' ) { $kul_ozet[ $k['id'] ]['program'] = $k['program']; }
	}

	if ( isset( $_POST['gbc_aff_test'] ) && check_admin_referer( 'gbc_aff_panel' ) ) {
		$adresler = array();
		foreach ( $defter as $id => $b ) { $adresler[ $id ] = $b['url']; }
		foreach ( $kul_ozet as $id => $b ) { if ( ! isset( $adresler[ $id ] ) || $adresler[ $id ] === '' ) { $adresler[ $id ] = $b['url']; } }
		foreach ( $adresler as $id => $u ) {
			if ( $u === '' ) { $test[ $id ] = 'bos'; continue; }
			$c = gbc_aff_link_test( $u );
			if ( is_wp_error( $c ) ) { $test[ $id ] = 'hata: ' . $c->get_error_message(); }
			else { $test[ $id ] = (string) wp_remote_retrieve_response_code( $c ); }
		}
	}

	$tik = get_option( 'gbc_aff_tik' );
	if ( ! is_array( $tik ) ) { $tik = array(); }
	$sayfa = gbc_aff_panel_sayfa();

	echo '<div class="wrap"><h1>Is Ortakligi Denetimi</h1>';
	if ( $mesaj !== '' ) { echo '<div class="notice notice-success"><p>' . esc_html( $mesaj ) . '</p></div>'; }
	if ( $hata !== '' )  { echo '<div class="notice notice-error"><p>' . esc_html( $hata ) . '</p></div>'; }

	echo '<style>
	.gbc-p table{border-collapse:collapse;width:100%;background:#fff;margin:10px 0 26px}
	.gbc-p th,.gbc-p td{border:1px solid #e2e2e2;padding:8px 10px;text-align:left;vertical-align:top;font-size:13px}
	.gbc-p th{background:#f6f7f7;font-weight:600}
	.gbc-p .ok{color:#1a7f37;font-weight:600}
	.gbc-p .uy{color:#a26a00;font-weight:600}
	.gbc-p .kt{color:#b32d2e;font-weight:600}
	.gbc-p textarea{width:100%;font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.6}
	.gbc-p code{background:#f0f0f1;padding:1px 5px;border-radius:3px}
	.gbc-p .ozet{display:flex;gap:14px;flex-wrap:wrap;margin:14px 0}
	.gbc-p .ozet div{background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:10px 16px;min-width:130px}
	.gbc-p .ozet strong{display:block;font-size:22px;line-height:1.2}
	.gbc-p .ozet span{font-size:12px;color:#666}
	.gbc-p .ozet.prog{margin:-4px 0 18px}
	.gbc-p .ozet.prog div{background:#f6f7f7;min-width:0;padding:8px 14px}
	.gbc-p .ozet.prog strong{font-size:17px}
	</style><div class="gbc-p">';

	echo '<form method="post">';
	wp_nonce_field( 'gbc_aff_panel' );

	$t_toplam = 0; $t_30 = 0;
	foreach ( $tik as $tv ) { $t_toplam += isset( $tv['t'] ) ? (int) $tv['t'] : 0; $s = gbc_aff_tik_son( $tv, 30 ); $t_30 += ( $s === null ? 0 : $s ); }
	$disarda = 0;
	foreach ( $kul_ozet as $id => $b ) { if ( ! isset( $defter[ $id ] ) ) { $disarda++; } }
	$sifir = get_option( 'gbc_aff_tik_sifir' );
	echo '<div class="ozet">'
		. '<div><strong>' . count( $defter ) . '</strong><span>defterde baglanti</span></div>'
		. '<div><strong>' . count( $kul_ozet ) . '</strong><span>sitede kullanilan</span></div>'
		. '<div><strong>' . (int) $disarda . '</strong><span>defterde olmayan</span></div>'
		. '<div><strong>' . (int) $t_toplam . '</strong><span>toplam tiklama</span></div>'
		. '<div><strong>' . (int) $t_30 . '</strong><span>son 30 gun</span></div>'
		. gbc_aff_panel_ek_ozet( $defter, $kul_ozet, $tik )
		. '</div>';
	echo gbc_aff_panel_prog_serit( $defter, $kul_ozet, $tik );
	if ( $sifir ) { echo '<p style="color:#666;margin-top:-6px">Sayaclar en son ' . esc_html( $sifir ) . ' tarihinde sifirlandi. Gunluk kirilim en fazla 60 gun tutulur.</p>'; }

	echo '<h2>1. Defter</h2>';
	echo '<p>Bicim: <code>id | gorunen metin | program | https://... | ag</code> &nbsp; Son sutun (ag: Impact, Travelpayouts) istege bagli, sayfada basilmaz. &nbsp; Bag sutunu bos ise o baglanti sitede duz metne doner. <strong>id degistirilmez</strong>, sayfalar onunla cagiriyor. Defterdeki adres, sayfaya elle yazilmis adresi <strong>ezer</strong>; bir baglantiyi degistirmek icin sayfalara girmeye gerek yok.</p>';
	echo '<textarea rows="12" spellcheck="false" readonly>' . esc_textarea( gbc_aff_panel_ham() ) . '</textarea>';
	$kapali = get_option( 'gbc_aff_kapali' ) === '1';
	echo '<p style="margin:14px 0 4px"><label style="font-weight:600"><input type="checkbox" name="gbc_aff_kapali" value="1" ' . checked( $kapali, true, false ) . '> Butun ortaklik baglantilarini KAPAT</label> <span style="color:#666">(Defter silinmez. Isaretleyip Ana salteri kaydet dugmesine basinca sitede hicbir ortaklik baglantisi basilmaz, cumleler duz metin olarak kalir; isareti kaldirinca aynen geri gelir.)</span></p>';
	if ( $kapali ) { echo '<div class="notice notice-warning inline" style="margin:8px 0"><p><strong>Su anda kapali.</strong> Sitede hicbir ortaklik baglantisi gorunmuyor.</p></div>'; }
	echo '<p><button class="button" name="gbc_aff_salter" value="1" onclick="return confirm(&#39;Bu işlem sitedeki BÜTÜN ortaklık bağlantılarını kapatır/açar. Emin misin?&#39;);">Ana şalteri kaydet</button></p>'; echo '<p><em>Defter bu panelden düzenlenmez. Değişiklikler 30120 numaralı "İş Ortaklığı Bağlantı Defteri" sayfasından yapılır.</em></p><p>';
	echo '<button class="button" name="gbc_aff_test" value="1">Baglantilari test et</button> ';
	/* GBC 21 Eylul 2026: Defterde olmayanlari aktar dugmesi kaldirildi; defter yalnizca 30120 sayfasindan duzenlenir. */
	/* GBC 21 Eylul 2026: Tiklama sayaclarini sifirla dugmesi kaldirildi; gbc_aff_tik verisi korunur. */
	if ( $sayfa ) { echo ' &nbsp; <a class="button-link" href="' . esc_url( (string) get_edit_post_link( $sayfa->ID ) ) . '">Defter sayfasini ac</a>'; }
	echo '</p>';
	echo '<p style="color:#666;margin-top:-10px;max-width:900px">Test notu: Travelpayouts (<code>tpx.li</code>) ve Impact (<code>pxf.io</code>) kisa linkleri, sunucudan gelen istegi bot sanip <strong>HTTP 403</strong> dondurebiliyor. Bu <strong>link bozuk demek degildir</strong>; ayni adres tarayicida acilir. 403, 405 ve 429 sari isaretlenir, kirmizi degil. Emin olmak icin tablodaki adrese tiklayip yeni sekmede ac. Gercek bozukluk 404 ve 5xx ile gorunur.</p>';
	if ( $sayfa && $sayfa->post_status === 'trash' ) { echo '<div class="notice notice-error inline" style="margin:10px 0"><p><strong>Defter sayfasi cop kutusunda.</strong> WordPress copu 30 gunde bosaltiyor; bosalirsa butun ortaklik baglantilari kaybolur. Sayfayi geri yukle.</p></div>'; }

	echo '<h2>2. Bütün bağlantılar</h2><p style="color:#666;margin-top:-8px">Sitedeki tek liste. En çok tıklanan üstte. "Nerede kullanılıyor" sütunu o bağlantının geçtiği sayfaları veriyor; eski ayrı tablo kaldırıldı.</p>';
	$hepsi = array();
	foreach ( $defter as $id => $b ) { $hepsi[ $id ] = true; }
	foreach ( $kul_ozet as $id => $b ) { $hepsi[ $id ] = true; }
	foreach ( $tik as $id => $b ) { $hepsi[ $id ] = true; }

	echo '<table><tr><th>id</th><th>Program</th><th>Ağ</th><th>Nerede kullanılıyor</th><th>Tıklama<br><small>toplam / 30 gün</small></th><th>Bağlantı</th><th>Durum</th></tr>';
	if ( empty( $hepsi ) ) { echo '<tr><td colspan="7">Kayit yok.</td></tr>'; }
	/* v4: en cok tiklanan ustte. Tiklamasi olmayanlar id sirasinda kalir. */
	$sirali = array_keys( $hepsi );
	usort( $sirali, function ( $a, $b ) use ( $tik ) {
		$ta = isset( $tik[ $a ]['t'] ) ? (int) $tik[ $a ]['t'] : 0;
		$tb = isset( $tik[ $b ]['t'] ) ? (int) $tik[ $b ]['t'] : 0;
		if ( $ta === $tb ) { return strcmp( (string) $a, (string) $b ); }
		return $tb - $ta;
	} );
	$hepsi = array_fill_keys( $sirali, true );
	foreach ( array_keys( $hepsi ) as $id ) {
		$d_var   = isset( $defter[ $id ] );
		$k_var   = isset( $kul_ozet[ $id ] );
		$url     = ( $d_var && $defter[ $id ]['url'] !== '' ) ? $defter[ $id ]['url'] : ( $k_var ? $kul_ozet[ $id ]['url'] : '' );
		$prog    = ( $d_var && $defter[ $id ]['program'] !== '' ) ? $defter[ $id ]['program'] : ( $k_var ? $kul_ozet[ $id ]['program'] : '' );
		$ag      = $d_var ? $defter[ $id ]['ag'] : '';
		$sayfa_n = $k_var ? count( $kul_ozet[ $id ]['sayfa'] ) : 0;

		$d = array();
		if ( ! $d_var ) { $d[] = '<span class="uy">defterde YOK, sayfadaki adres gecerli</span>'; }
		elseif ( $defter[ $id ]['url'] === '' ) { $d[] = '<span class="kt">defterde var, bag bos: sitede duz metne doner</span>'; }
		else { $d[] = '<span class="ok">defterden yonetiliyor</span>'; }
		if ( $sayfa_n === 0 ) {
			if ( in_array( $id, gbc_aff_panel_sablon_idleri(), true ) ) {
				$d[] = '<span class="ok">şablon üretiyor (otel motoru); id çalışma anında birleştiriliyor, kaynakta aranmaz</span>';
			} else {
				$d[] = '<span class="uy">hiçbir sayfada kullanılmıyor</span>';
			}
		}
		if ( gbc_aff_panel_tarih_var( $url ) ) { $d[] = '<span class="kt">SABIT TARIH / KISI SAYISI VAR, degistir</span>'; }
		if ( $prog === '' && $url !== '' ) { $d[] = '<span class="uy">program adi bos, sayfa sonu aciklamasi firma adini yazamaz</span>'; }
		if ( isset( $test[ $id ] ) ) {
			$kod   = $test[ $id ];
			$sinif = in_array( (string) $kod, array( '200', '301', '302', '303', '307', '308' ), true ) ? 'ok' : ( in_array( (string) $kod, array( '401', '403', '405', '429', '503', '999' ), true ) ? 'uy' : 'kt' );
			$aciklama = ( $sinif === 'uy' ) ? ' &middot; agin bot korumasi, tarayicida acilir' : ( $sinif === 'kt' ? ' &middot; BOZUK, kontrol et' : ' &middot; calisiyor' );
			$d[]   = '<span class="' . $sinif . '">HTTP ' . esc_html( $kod ) . $aciklama . '</span>';
		}

		$tsay = isset( $tik[ $id ]['t'] ) ? (int) $tik[ $id ]['t'] : 0;
		$t30  = isset( $tik[ $id ] ) ? gbc_aff_tik_son( $tik[ $id ], 30 ) : null;
		$tdet = '';
		if ( ! empty( $tik[ $id ]['s'] ) && is_array( $tik[ $id ]['s'] ) ) {
			arsort( $tik[ $id ]['s'] );
			foreach ( array_slice( $tik[ $id ]['s'], 0, 3, true ) as $pid => $adet ) {
				$bas = get_the_title( (int) $pid );
				$tdet .= '<br><small>' . esc_html( $bas !== '' ? $bas : ( '#' . (int) $pid ) ) . ': ' . (int) $adet . '</small>';
			}
		}

		echo '<tr><td><code>' . esc_html( $id ) . '</code></td>'
			. '<td>' . ( $prog !== '' ? esc_html( $prog ) : '&mdash;' ) . '</td>'
			. '<td>' . ( $ag !== '' ? esc_html( $ag ) : '&mdash;' ) . '</td>'
			. '<td>' . ( $sayfa_n === 0 ? ( in_array( $id, gbc_aff_panel_sablon_idleri(), true ) ? '<small>şablondan basılıyor</small>' : '<span class="uy">kullanılmıyor</span>' ) : gbc_aff_panel_sayfa_hucre( $kul_ozet[ $id ]['sayfa'] ) ) . '</td>'
			. '<td><strong>' . $tsay . '</strong> / ' . ( $t30 === null ? '&mdash;' : (int) $t30 ) . $tdet . '</td>'
			. '<td style="word-break:break-all">' . ( $url === '' ? '&mdash;' : '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>' ) . '</td>'
			. '<td>' . implode( '<br>', $d ) . '</td></tr>';
	}
	echo '</table>';

	if ( false ) { /* v4: program tablosu ve nerede-kullaniliyor tablosu kapatildi; ikisi de yukari tasindi */
	echo '<h2>Program ozeti (eski tablo)</h2>';
	$prog_ozet = array();
	foreach ( array_keys( $hepsi ) as $id ) {
		$prog = ( isset( $defter[ $id ] ) && $defter[ $id ]['program'] !== '' ) ? $defter[ $id ]['program'] : ( ( isset( $kul_ozet[ $id ] ) && $kul_ozet[ $id ]['program'] !== '' ) ? $kul_ozet[ $id ]['program'] : 'belirtilmemis' );
		if ( ! isset( $prog_ozet[ $prog ] ) ) { $prog_ozet[ $prog ] = array( 'bag' => 0, 'tik' => 0, 'tik30' => 0 ); }
		$prog_ozet[ $prog ]['bag']++;
		$prog_ozet[ $prog ]['tik'] += isset( $tik[ $id ]['t'] ) ? (int) $tik[ $id ]['t'] : 0;
		$s = isset( $tik[ $id ] ) ? gbc_aff_tik_son( $tik[ $id ], 30 ) : null;
		$prog_ozet[ $prog ]['tik30'] += ( $s === null ? 0 : $s );
	}
	uasort( $prog_ozet, function ( $a, $b ) { return $b['tik'] - $a['tik']; } );
	echo '<table><tr><th>Program</th><th>Baglanti</th><th>Tiklama</th><th>Son 30 gun</th></tr>';
	foreach ( $prog_ozet as $p => $v ) {
		echo '<tr><td>' . esc_html( $p ) . '</td><td>' . (int) $v['bag'] . '</td><td><strong>' . (int) $v['tik'] . '</strong></td><td>' . (int) $v['tik30'] . '</td></tr>';
	}
	echo '</table>';

	/* v4: bu tablo kaldirildi, ayni bilgi ana tablonun Sayfa sutununda.
	   Geri istersen if(false) yerine if(true) yaz. */
	echo '<h2>Nerede kullaniliyor (eski tablo)</h2>';
	echo '<table><tr><th>Sayfa</th><th>Tip</th><th>Alan</th><th>id</th><th>Bicim</th><th>Durum</th></tr>';
	if ( empty( $kullanim ) ) { echo '<tr><td colspan="6">Sitede hicbir yerde ortaklik baglantisi bulunamadi.</td></tr>'; }
	foreach ( $kullanim as $k ) {
		$d = array();
		if ( ! isset( $defter[ $k['id'] ] ) ) {
			$d[] = ( $k['kaynak'] === 'elle' )
				? '<span class="uy">defterde kayitli degil, sayfadaki adres kullaniliyor</span>'
				: '<span class="kt">id defterde YOK, kisa kod hicbir sey basmiyor</span>';
		} elseif ( $defter[ $k['id'] ]['url'] === '' ) {
			$d[] = '<span class="kt">defterde var ama bag bos</span>';
		} else {
			$d[] = '<span class="ok">calisiyor</span>';
		}
		if ( $k['durum'] !== 'publish' ) { $d[] = 'sayfa durumu: ' . esc_html( $k['durum'] ); }
		$link = (string) get_edit_post_link( $k['post'] );
		echo '<tr><td><a href="' . esc_url( $link ) . '">' . esc_html( $k['baslik'] ) . '</a> <small>#' . (int) $k['post'] . '</small></td><td>' . esc_html( $k['tip'] ) . '</td><td><code>' . esc_html( $k['alan'] ) . '</code></td><td><code>' . esc_html( $k['id'] ) . '</code></td><td>' . esc_html( $k['kaynak'] ) . '</td><td>' . implode( '<br>', $d ) . '</td></tr>';
	}
	echo '</table>';

	}
	echo '<h2>3. Kurallar</h2><ul style="list-style:disc;padding-left:22px">';
	echo '<li>Yalnizca dunyaca taninan, kurumsal markalara baglanti verilir. Supheliyse satir bos kalir.</li>';
	echo '<li><strong>Sigorta, saglik, finans ve hukuk hicbir zaman eklenmez.</strong></li>';
	echo '<li>Baglantida sabit tarih, sabit kisi sayisi ve sabit rota olmaz; eskiyor.</li>';
	echo '<li>Resmi Kaynaklar kutusuna (.gbc-src) ve resmi bilgi cumlelerinin icine konmaz.</li>';
	echo '<li>Fiyat yazilmaz; fiyat ve sartlar is ortaginin kendi sayfasinda gosterilir.</li>';
	echo '<li>Yasal etiket ("is birligi") her baglantinin yaninda durur; motor (snippet 30204) eksikse ekler.</li>';
	echo '</ul>';

	echo '</form></div></div>';
}
}