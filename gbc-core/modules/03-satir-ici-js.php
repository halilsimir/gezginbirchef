<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · 03 · Satır İçi JS'i Dosyaya
 * --------------------------------------------------------------
 * ÖLÇÜLEN SORUN (28 Eylül 2026, /budapeste-gezi-rehberi/): sayfa HTML'i
 * 378 KB. İçinden 73 KB satır içi <script>, ayrıca 43 KB de LiteSpeed'in
 * satır içi betikleri çevirdiği data:text/javascript;base64 adresi.
 * Base64 kodlama boyutu ÜÇTE BİR büyütüyor, yani bizim 5 KB'lık betiğimiz
 * HTML'de 6,7 KB yer kaplıyor — ve her sayfada yeniden iniyor, hiçbir
 * zaman tarayıcı önbelleğine girmiyor.
 *
 * NE YAPIYOR: kendi modüllerimizin sabit betiklerini uploads/gbc-js
 * altında gerçek bir .js dosyasına yazar ve <script src> ile bağlar.
 * Dosya adında içeriğin özeti var; betik değişince yeni dosya üretilir,
 * eskisi silinir.
 *
 * KAZANÇ: HTML küçülür, betik ikinci sayfada hiç inmez (tarayıcı
 * önbelleği), LiteSpeed de onu kendi birleştirmesine katabilir.
 *
 * GÜVENLİK: dosya yazılamazsa betik ESKİSİ GİBİ satır içi basılır.
 * En kötü senaryoda bugünkü davranışa geri düşer, hiçbir şey kaybolmaz.
 * <script> etiketindeki id korunur — Kontrol Merkezi izleri bozulmaz.
 */

if ( ! function_exists( 'gbc_js_dosya_url' ) ) {
function gbc_js_dosya_url( $ad, $js ) {

	$js = (string) $js;
	if ( '' === trim( $js ) ) { return false; }

	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) { return false; }

	$damga = substr( md5( $js ), 0, 10 );
	$dizin = trailingslashit( $up['basedir'] ) . 'gbc-js';
	$dosya = sanitize_key( $ad ) . '-' . $damga . '.js';
	$yol   = $dizin . '/' . $dosya;
	$url   = trailingslashit( $up['baseurl'] ) . 'gbc-js/' . $dosya;

	if ( file_exists( $yol ) && filesize( $yol ) > 0 ) { return $url; }
	if ( ! wp_mkdir_p( $dizin ) ) { return false; }

	/* Aynı betiğin eski sürümlerini temizle. */
	$eskiler = glob( $dizin . '/' . sanitize_key( $ad ) . '-*.js' );
	if ( is_array( $eskiler ) ) {
		foreach ( $eskiler as $eski ) { if ( $eski !== $yol ) { @unlink( $eski ); } }
	}

	$yazilan = @file_put_contents( $yol, $js );
	if ( false === $yazilan || $yazilan < 1 ) { return false; }
	return $url;
}
}

if ( ! function_exists( 'gbc_js_bas' ) ) {
/**
 * Betiği dosyadan bağlar; yazamazsa satır içi basar.
 *
 * @param string $ad Dosya adı öneki (ör. yt-facade).
 * @param string $js Betiğin gövdesi (<script> etiketi olmadan).
 * @param string $id <script> etiketine verilecek id.
 */
function gbc_js_bas( $ad, $js, $id ) {
	$url = gbc_js_dosya_url( $ad, $js );
	if ( $url ) {
		echo '<script id="' . esc_attr( $id ) . '" src="' . esc_url( $url ) . '"></script>' . "\n";
		return;
	}
	echo '<script id="' . esc_attr( $id ) . '">' . $js . '</script>' . "\n";
}
}
