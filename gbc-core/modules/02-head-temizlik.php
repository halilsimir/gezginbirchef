<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · 02 · Head Temizliği
 * --------------------------------------------------------------
 * ÖLÇÜLEN SORUN (28 Eylül 2026, ana sayfa): logonun preload satırı
 * <head>'de İKİ KEZ, birebir aynı şekilde basılıyordu:
 *   <link rel="preload" fetchpriority="high" as="image"
 *         href=".../gezginbirchef-logo-2x-3-150x93.png">
 * Astra hem başlık logosu için hem de LCP görseli için ayrı ayrı
 * basıyor; ikisi aynı dosyaya çıkınca satır tekrarlanıyor.
 *
 * NE YAPIYOR: <head> çıktısını tamponlar ve BİREBİR AYNI preload
 * satırının ikinci kopyasını siler. Başka hiçbir şeye dokunmaz —
 * sırayı değiştirmez, farklı adresleri birleştirmez, tek kopya olan
 * satırları elemez. Tamponda beklenmedik bir şey olursa çıktı
 * olduğu gibi geçer.
 *
 * NEDEN ZARARSIZ: tarayıcı zaten aynı adresi iki kez indirmez;
 * kazanç HTML'in temizliği ve doğru ölçüm. Bu yüzden kural da
 * "ikinci kopyayı sil"den ibaret.
 *
 * 28 Eylül 2026 — İKİNCİ TUR: yalnız wp_head tamponu yetmedi, canlıda
 * logo satırı hâlâ üç kez görünüyordu. Ölçüldü: satırlar LiteSpeed'in
 * birleştirilmiş CSS linkinin hemen ardında, yani LiteSpeed sayfayı
 * yeniden düzenledikten SONRAKI hâlde duruyor. Çözüm: LiteSpeed varsa
 * onun kendi son tampon süzgecine (litespeed_buffer_finalize) bağlanmak;
 * yoksa sayfanın tamamını tamponlamak. İkisi de yalnız <head> bölümüne
 * dokunur, gövdeye hiç karışmaz.
 */

if ( ! function_exists( 'gbc_head_tampon_ac' ) ) {
	function gbc_head_tampon_ac() {
		if ( is_admin() || is_feed() ) { return; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }
		ob_start( 'gbc_head_tekille' );
	}
	add_action( 'wp_head', 'gbc_head_tampon_ac', 0 );
}

if ( ! function_exists( 'gbc_head_tampon_kapat' ) ) {
	function gbc_head_tampon_kapat() {
		if ( is_admin() || is_feed() ) { return; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }
		if ( ob_get_level() > 0 ) { @ob_end_flush(); }
	}
	add_action( 'wp_head', 'gbc_head_tampon_kapat', PHP_INT_MAX );
}

if ( ! function_exists( 'gbc_head_tekille' ) ) {
	/**
	 * Aynı preload satırının ikinci ve sonraki kopyalarını siler.
	 * Karşılaştırma href üzerinden yapılır; as/type farklı olsa bile
	 * aynı dosya iki kez preload edilmez.
	 */
	function gbc_head_tekille( $html ) {
		if ( ! is_string( $html ) || '' === $html ) { return $html; }
		if ( substr_count( $html, 'rel="preload"' ) + substr_count( $html, "rel='preload'" ) < 2 ) {
			return $html;
		}

		$gorulen = array();
		$yeni = preg_replace_callback(
			'#<link\b[^>]*\brel=["\']?preload["\']?[^>]*>#i',
			static function ( $m ) use ( &$gorulen ) {
				if ( ! preg_match( '#\bhref=["\']([^"\']+)["\']#i', $m[0], $h ) ) { return $m[0]; }
				$adres = $h[1];
				if ( isset( $gorulen[ $adres ] ) ) { return ''; }   /* ikinci kopya */
				$gorulen[ $adres ] = true;
				return $m[0];
			},
			$html
		);

		return is_string( $yeni ) ? $yeni : $html;
	}
}

/* ============================================================
   SON TAMPON — LiteSpeed sonrası
   ============================================================ */
if ( ! function_exists( 'gbc_head_son_tampon' ) ) {
	/** Yalnızca </head>'e kadar olan kısımda tekilleştirir. */
	function gbc_head_son_tampon( $html ) {
		if ( ! is_string( $html ) || '' === $html ) { return $html; }
		$son = stripos( $html, '</head>' );
		if ( false === $son || $son < 1 ) { return $html; }

		$bas   = substr( $html, 0, $son );
		$temiz = gbc_head_tekille( $bas );
		if ( ! is_string( $temiz ) || '' === $temiz ) { return $html; }
		if ( $temiz === $bas ) { return $html; }

		return $temiz . substr( $html, $son );
	}
}

if ( ! function_exists( 'gbc_head_kanca_kur' ) ) {
	function gbc_head_kanca_kur() {
		if ( is_admin() || is_feed() ) { return; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }

		/* 1) LiteSpeed varsa onun son tamponuna bağlan — en doğru yer. */
		if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\\Core' ) ) {
			add_filter( 'litespeed_buffer_finalize', 'gbc_head_son_tampon', 99 );
			return;
		}

		/* 2) LiteSpeed yoksa sayfanın tamamını tamponla. */
		ob_start( 'gbc_head_son_tampon' );
	}
	add_action( 'template_redirect', 'gbc_head_kanca_kur', 1 );
}
