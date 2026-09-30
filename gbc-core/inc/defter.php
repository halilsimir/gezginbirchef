<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Çalışma Dosyası (v1.46.0: Kılavuz + Sürüm geçmişi).
 *
 * KILAVUZ: eklentinin kilavuz/ klasöründeki Markdown dosyaları. Her dosya bir sekme:
 * çalışma kuralları, yazım kuralları, şablon haritası, sayfa tipleri (Gezi, Liste,
 * Detay, Rota, Tarif, Blog/Sözlük), ortaklık, tasarım, teknik. Halil'in isteği:
 * "her şeyi tekrar öğrenmeyelim; her gittiğinde oradan yapılsın".
 * SÜRÜM GEÇMİŞİ: DEFTER.md (hangi sürümde ne yapıldı, ne ölçüldü).
 *
 * Hepsi gbc_defter seçeneğine de yazılır (MCP'ye açık): başka bir sohbet tek çağrıda
 * bütün kuralları ve geçmişi okuyabilir.
 */

define( 'GBC_DEFTER', 'gbc_defter' );

/** Kılavuz dosyaları: dosya adı => array( baslik, ozet, metin ). Ada göre sıralı. */
function gbc_kilavuz_dosyalar() {
	$o = array();
	$yollar = glob( GBC_CORE_DIR . 'kilavuz/*.md' );
	if ( ! $yollar ) { return $o; }
	sort( $yollar );
	foreach ( $yollar as $y ) {
		$metin = (string) file_get_contents( $y );
		$satir = preg_split( '/\R/', $metin );
		$baslik = isset( $satir[0] ) ? trim( ltrim( $satir[0], '# ' ) ) : basename( $y, '.md' );
		$ozet = '';
		foreach ( array_slice( $satir, 1, 4 ) as $s ) { if ( 0 === strpos( ltrim( $s ), '>' ) ) { $ozet = trim( ltrim( trim( $s ), '> ' ) ); break; } }
		$o[ basename( $y, '.md' ) ] = array( 'baslik' => $baslik, 'ozet' => $ozet, 'metin' => $metin );
	}
	return $o;
}

/** Dosyalar değiştiyse seçeneği tazele. Damga: sürüm + bütün dosya boyutları. */
add_action( 'admin_init', 'gbc_defter_esitle' );
function gbc_defter_esitle() {
	$yol = GBC_CORE_DIR . 'DEFTER.md';
	$damga = GBC_CORE_VER . ':' . ( file_exists( $yol ) ? (int) filesize( $yol ) : 0 );
	foreach ( (array) glob( GBC_CORE_DIR . 'kilavuz/*.md' ) as $k ) { $damga .= ':' . (int) filesize( $k ); }
	$var = get_option( GBC_DEFTER, array() );
	if ( is_array( $var ) && isset( $var['damga'] ) && $var['damga'] === $damga ) { return; }
	$kil = array();
	foreach ( gbc_kilavuz_dosyalar() as $ad => $x ) { $kil[ $ad ] = $x['metin']; }
	update_option( GBC_DEFTER, array(
		'damga'   => $damga,
		'surum'   => GBC_CORE_VER,
		'zaman'   => time(),
		'okuma'   => 'Önce kilavuz[01-calisma], sonra işe göre ilgili kılavuz dosyası; metin = sürüm geçmişi (DEFTER.md).',
		'kilavuz' => $kil,
		'metin'   => file_exists( $yol ) ? (string) file_get_contents( $yol ) : '',
	), false );
}

/* ---------------- Küçük Markdown çevirici (başlık, liste, alıntı, tablo, kod, kalın, bağlantı) ---------------- */
function gbc_md_satir( $s ) {
	$s = esc_html( $s );
	$s = preg_replace( '/`([^`]+)`/', '<code style="background:#F3F1EC;padding:1px 5px;border-radius:4px;font-size:12.5px;overflow-wrap:anywhere;word-break:break-word">$1</code>', $s );
	$s = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s );
	$s = preg_replace( '/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $s );
	$s = preg_replace_callback( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', static function ( $m ) {
		return '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
	}, $s );
	return $s;
}
function gbc_md( $metin, $ara = '' ) {
	$satirlar = preg_split( '/\R/', (string) $metin );
	$h = ''; $liste = ''; $tablo = array(); $kod = false; $p = array();
	$kapat_p = static function () use ( &$p, &$h ) { if ( $p ) { $h .= '<p style="margin:6px 0 10px">' . implode( ' ', $p ) . '</p>'; $p = array(); } };
	$kapat_l = static function () use ( &$liste, &$h ) { if ( '' !== $liste ) { $h .= '</' . $liste . '>'; $liste = ''; } };
	$kapat_t = static function () use ( &$tablo, &$h ) {
		if ( ! $tablo ) { return; }
		$h .= '<div style="overflow-x:auto"><table class="widefat striped" style="margin:8px 0 12px;width:auto;min-width:50%">';
		foreach ( $tablo as $i => $r ) {
			if ( 1 === $i && preg_match( '/^[\s|:\-]+$/', implode( '', $r ) ) ) { continue; }
			$h .= '<tr>';
			foreach ( $r as $c ) { $h .= ( 0 === $i ? '<th>' : '<td>' ) . gbc_md_satir( trim( $c ) ) . ( 0 === $i ? '</th>' : '</td>' ); }
			$h .= '</tr>';
		}
		$h .= '</table></div>'; $tablo = array();
	};
	foreach ( $satirlar as $s ) {
		if ( 0 === strpos( trim( $s ), '```' ) ) {
			$kapat_p(); $kapat_l(); $kapat_t();
			$h .= $kod ? '</pre>' : '<pre style="background:#F6F7F7;border:1px solid #E3E5E9;border-radius:6px;padding:10px;white-space:pre-wrap;word-break:break-word;font-size:12.5px">';
			$kod = ! $kod; continue;
		}
		if ( $kod ) { $h .= esc_html( $s ) . "\n"; continue; }
		$t = trim( $s );
		if ( '' === $t ) { $kapat_p(); $kapat_l(); $kapat_t(); continue; }
		if ( 0 === strpos( $t, '|' ) ) { $kapat_p(); $kapat_l(); $tablo[] = array_slice( explode( '|', $t ), 1, -1 ); continue; }
		$kapat_t();
		if ( preg_match( '/^(#{1,4})\s+(.*)$/', $t, $m ) ) {
			$kapat_p(); $kapat_l();
			$n = strlen( $m[1] );
			$st = array( 1 => 'font-size:21px;margin:4px 0 6px', 2 => 'font-size:17px;margin:22px 0 6px;padding-top:10px;border-top:1px solid #EEE', 3 => 'font-size:14.5px;margin:14px 0 4px', 4 => 'font-size:13.5px;margin:10px 0 4px' );
			$h .= '<h' . ( $n + 1 ) . ' style="' . $st[ $n ] . '">' . gbc_md_satir( $m[2] ) . '</h' . ( $n + 1 ) . '>';
			continue;
		}
		if ( 0 === strpos( $t, '>' ) ) {
			$kapat_p(); $kapat_l();
			$h .= '<div style="border-left:3px solid #C9A227;background:#FBF7EC;padding:8px 12px;margin:6px 0 12px;color:#3C4149">' . gbc_md_satir( ltrim( $t, '> ' ) ) . '</div>';
			continue;
		}
		if ( preg_match( '/^([-*]|\d+\.)\s+(.*)$/', $t, $m ) ) {
			$kapat_p();
			$tur = ctype_digit( rtrim( $m[1], '.' ) ) ? 'ol' : 'ul';
			if ( $liste !== $tur ) { $kapat_l(); $h .= '<' . $tur . ' style="margin:4px 0 10px 22px;list-style:' . ( 'ol' === $tur ? 'decimal' : 'disc' ) . '">'; $liste = $tur; }
			$ic = preg_match( '/^\s{2,}/', $s ) ? 'margin-left:18px;' : '';
			$h .= '<li style="' . $ic . 'margin:3px 0;line-height:1.55">' . gbc_md_satir( $m[2] ) . '</li>';
			continue;
		}
		$kapat_l();
		$p[] = gbc_md_satir( $t );
	}
	$kapat_p(); $kapat_l(); $kapat_t();
	if ( $kod ) { $h .= '</pre>'; }
	if ( '' !== $ara ) {
		$q = preg_quote( esc_html( $ara ), '/' );
		$h = preg_replace_callback( '/(>[^<]*)/u', static function ( $m ) use ( $q ) {
			return preg_replace( '/(' . $q . ')/iu', '<mark style="background:#FFE58F">$1</mark>', $m[1] );
		}, $h );
	}
	return $h;
}

/* ---------------- Ekran ---------------- */
function gbc_defter_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	gbc_defter_esitle();
	$dosya = gbc_kilavuz_dosyalar();
	$sekme = isset( $_GET['k'] ) ? sanitize_key( $_GET['k'] ) : '';
	$ara   = isset( $_GET['ara'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['ara'] ) ) ) : '';
	if ( '' === $sekme || ( 'gecmis' !== $sekme && ! isset( $dosya[ $sekme ] ) ) ) { $sekme = $dosya ? (string) array_key_first( $dosya ) : 'gecmis'; }

	echo '<div class="wrap"><h1>GBC · Çalışma Dosyası</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-defter' ); }
	echo '<p style="max-width:980px;color:#3C4149;margin-top:0">Sitenin kural kitabı. Her iş buradaki kurala göre yapılır; yeni kural çıktıkça buraya eklenir. İşe başlamadan önce <strong>Çalışma Kuralları</strong>, sonra işin sayfa tipi okunur.</p>';

	/* Arama */
	echo '<form method="get" style="margin:0 0 12px;display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="page" value="gbc-defter">'
		. '<input type="search" name="ara" value="' . esc_attr( $ara ) . '" placeholder="Kurallarda ara: örn. etiket, eSIM, H2, layout_genis" style="width:100%;max-width:420px;box-sizing:border-box">'
		. '<button class="button">Ara</button></form>';

	/* Sekmeler: kılavuz dosyaları + sürüm geçmişi */
	echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px">';
	foreach ( $dosya as $ad => $x ) {
		$bu = ( $ad === $sekme && '' === $ara );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-defter&k=' . $ad ) ) . '" title="' . esc_attr( $x['ozet'] ) . '" style="text-decoration:none;font-size:13px;padding:6px 12px;border-radius:999px;'
			. ( $bu ? 'background:#17181A;color:#fff;font-weight:700' : 'background:#fff;color:#3C4149;border:1px solid #E6E2DA' ) . '">' . esc_html( $x['baslik'] ) . '</a>';
	}
	$bu = ( 'gecmis' === $sekme && '' === $ara );
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-defter&k=gecmis' ) ) . '" style="text-decoration:none;font-size:13px;padding:6px 12px;border-radius:999px;'
		. ( $bu ? 'background:#17181A;color:#fff;font-weight:700' : 'background:#F3F1EC;color:#5C6470;border:1px solid #E6E2DA' ) . '">Sürüm geçmişi</a>';
	echo '</div>';

	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:16px 22px;max-width:1000px;font-size:14px;line-height:1.6;overflow-wrap:anywhere;min-width:0">';
	if ( '' !== $ara ) {
		/* Arama sonuçları: eşleşen satırlar, dosyasıyla */
		$bulunan = 0;
		foreach ( $dosya + array( 'gecmis' => array( 'baslik' => 'Sürüm geçmişi', 'metin' => (string) @file_get_contents( GBC_CORE_DIR . 'DEFTER.md' ) ) ) as $ad => $x ) {
			$es = array();
			foreach ( preg_split( '/\R/', $x['metin'] ) as $s ) { if ( '' !== trim( $s ) && false !== mb_stripos( $s, $ara ) ) { $es[] = $s; } }
			if ( ! $es ) { continue; }
			$bulunan += count( $es );
			echo '<h3 style="margin:14px 0 4px"><a href="' . esc_url( admin_url( 'admin.php?page=gbc-defter&k=' . $ad ) ) . '">' . esc_html( $x['baslik'] ) . '</a> <span style="color:#5C6470;font-weight:400;font-size:12.5px">' . count( $es ) . ' eşleşme</span></h3>';
			echo gbc_md( implode( "\n", array_map( static function ( $s ) { $t = ltrim( $s ); return preg_match( '/^([-*]|\d+\.)\s/', $t ) ? $t : '- ' . ltrim( $t, '#> ' ); }, array_slice( $es, 0, 40 ) ) ), $ara );
		}
		if ( ! $bulunan ) { echo '<p><em>"' . esc_html( $ara ) . '" hiçbir kuralda geçmiyor.</em></p>'; }
	} elseif ( 'gecmis' === $sekme ) {
		$d = get_option( GBC_DEFTER, array() );
		echo '<p style="color:#5C6470;font-size:12.5px;margin-top:0">Sürüm ' . esc_html( (string) GBC_CORE_VER ) . ' · hangi sürümde ne yapıldı, ne ölçüldü. Kalıcı kurallar kılavuz sekmelerinde.</p>';
		/* Bölümler kapalı, en yenisi üstte */
		$metin = is_array( $d ) && ! empty( $d['metin'] ) ? (string) $d['metin'] : '';
		$parca = preg_split( '/^(?=## )/m', $metin );
		$bas = array_shift( $parca );
		foreach ( array_reverse( $parca ) as $i => $b ) {
			$ilk = strtok( $b, "\n" );
			echo '<details' . ( 0 === $i ? ' open' : '' ) . ' style="border-bottom:1px solid #EEE;padding:6px 0"><summary style="cursor:pointer;font-weight:600">' . esc_html( ltrim( $ilk, '# ' ) ) . '</summary>'
				. gbc_md( substr( $b, strlen( $ilk ) ) ) . '</details>';
		}
		if ( '' !== trim( (string) $bas ) ) { echo '<details style="padding:6px 0"><summary style="cursor:pointer;font-weight:600">Başlangıç notları</summary>' . gbc_md( $bas ) . '</details>'; }
	} else {
		echo gbc_md( $dosya[ $sekme ]['metin'] );
	}
	echo '</div></div>';
}
