<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30188 — GBC · 01 · Şablon CSS'i Dosyaya. GBC Core'a tasindi, 28 Eylul 2026.
 *
 * NOT: 30188 icinde ayrica Ortaklik Baglanti Motoru'nun bir kopyasi vardi.
 * O kisim buraya alinmadi; ayri modul olarak 71-ortaklik-motoru.php icinde duruyor.
 *
 * NE YAPAR
 *   Sablon CSS snippet'leri [wpcode id="..."] kisa koduyla cagrildigi icin CSS
 *   sayfanin GOVDESINE satir ici <style> olarak basiliyordu (Gezi CSS 108 KB).
 *   Bu CSS tarayici onbellegine giremiyor ve LiteSpeed birlestirmesine girmiyor.
 *   Bu modul ayni CSS'i uploads/gbc-css altinda gercek bir .css dosyasina yazip
 *   <head>'den link'ler, kisa kodun satir ici ciktisini bastirir.
 *
 * GUVENLIK: dosya yazilamazsa enqueue yapilmaz, kisa kod eskisi gibi satir ici
 * basar. En kotu senaryoda bugunku davranisa geri duser, sayfa stilsiz kalmaz.
 *
 * SURUM: dosya adinda snippet'in post_modified damgasi var. Snippet duzenlenince
 * yeni dosya uretilir, eskisi silinir. Elle temizlik gerekmez.
 */

if ( ! function_exists( 'gbc_css_sablon_listesi' ) ) {
	/* Dosyaya tasinacak SABLON CSS snippet id'leri. */
	function gbc_css_sablon_listesi() {
		return array(
			22608, // Gezi
			23109, // Liste
			26923, // Blog
			23490, // Detay
			23342, // Rota
			23533, // AnaSayfa
			23825, // Kategori Arsiv
			24155, // Sozluk Arsiv
			24157, // Sozluk Detay
			24752, // Tarif Sistemi
		);
	}
}

if ( ! function_exists( 'gbc_css_dosya_url' ) ) {
	function gbc_css_dosya_url( $snippet_id ) {

		$p = get_post( $snippet_id );
		if ( ! $p || 'wpcode' !== $p->post_type ) {
			return false;
		}
		$css = (string) $p->post_content;
		if ( '' === trim( $css ) ) {
			return false;
		}

		/* 30 Eyl 2026 IKINCI DUZELTME — damga SUZULMUS icerikten uretiliyor.
		   Ilk halinde damga olu sinif LISTESININ ozetini tasiyordu. Liste
		   degismeyip yalniz suzgecin KURALI gelisince (v1.25.1'de torun
		   secicileri de kapsar oldu) damga ayni kaldi, eski dosya yeniden
		   kullanildi ve temizlik hic yansimadi — canli olcumde yakalandi.
		   Customizer tarafi zaten ciktinin ozetini kullaniyordu; burasi da
		   ayni yola getirildi: cikti degisirse ad degisir, istisnasiz. */
		if ( function_exists( 'gbc_css_temizle' ) ) {
			$t   = gbc_css_temizle( $css );
			$css = $t['css'];
		}
		$damga = substr( md5( $p->post_modified_gmt . '|' . $css ), 0, 10 );
		$up    = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return false;
		}

		$dizin = trailingslashit( $up['basedir'] ) . 'gbc-css';
		$ad    = $snippet_id . '-' . $damga . '.css';
		$yol   = $dizin . '/' . $ad;
		$url   = trailingslashit( $up['baseurl'] ) . 'gbc-css/' . $ad;

		if ( file_exists( $yol ) && filesize( $yol ) > 0 ) {
			return $url;
		}

		if ( ! wp_mkdir_p( $dizin ) ) {
			return false;
		}

		/* Ayni snippet'in eski surumlerini temizle. */
		$eskiler = glob( $dizin . '/' . $snippet_id . '-*.css' );
		if ( is_array( $eskiler ) ) {
			foreach ( $eskiler as $eski ) {
				if ( $eski !== $yol ) {
					@unlink( $eski );
				}
			}
		}

		/* Ayiklama yukarida, damga hesaplanmadan once yapildi. */
		$yazilan = @file_put_contents( $yol, $css );
		if ( false === $yazilan || $yazilan < 1 ) {
			return false;
		}
		return $url;
	}
}

if ( ! function_exists( 'gbc_css_customizer_dosyasi' ) ) {
	/**
	 * Customizer (Ek CSS) de dosyaya aliniyor.
	 * Sebep: LiteSpeed data-no-optimize'i CSS linkinde DINLEMIYOR (olculdu).
	 * Link her turlu birlestirilmis dosyaya giriyor ve o dosya <head>'in en
	 * basinda duruyor. Cozum LiteSpeed'le kavga etmek degil, onunla calismak:
	 * Customizer'i da ayni boru hattina sokuyoruz. Ikisi de wp_head 200'de,
	 * ONCE Customizer SONRA sablon basiliyor; sira korunuyor.
	 */
	function gbc_css_customizer_dosyasi() {
		if ( ! function_exists( 'wp_get_custom_css' ) ) { return false; }
		$css = (string) wp_get_custom_css();
		if ( '' === trim( $css ) ) { return false; }

		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) { return false; }

		/* Once ayikla, damgayi AYIKLANMIS icerikten uret: liste degisince
		   dosya adi da degisir, eski surum onbellekte asili kalmaz. */
		if ( function_exists( 'gbc_css_temizle' ) ) {
			$t   = gbc_css_temizle( $css );
			$css = $t['css'];
		}

		$damga = substr( md5( $css ), 0, 10 );
		$dizin = trailingslashit( $up['basedir'] ) . 'gbc-css';
		$ad    = 'customizer-' . $damga . '.css';
		$yol   = $dizin . '/' . $ad;
		$url   = trailingslashit( $up['baseurl'] ) . 'gbc-css/' . $ad;

		if ( file_exists( $yol ) && filesize( $yol ) > 0 ) { return $url; }
		if ( ! wp_mkdir_p( $dizin ) ) { return false; }

		$eskiler = glob( $dizin . '/customizer-*.css' );
		if ( is_array( $eskiler ) ) {
			foreach ( $eskiler as $eski ) { if ( $eski !== $yol ) { @unlink( $eski ); } }
		}

		$yazilan = @file_put_contents( $yol, $css );
		if ( false === $yazilan || $yazilan < 1 ) { return false; }
		return $url;
	}
}

if ( ! function_exists( 'gbc_css_dosyaya_al' ) ) {
	function gbc_css_dosyaya_al() {

		if ( is_admin() || is_feed() ) {
			return;
		}

		/* ---- 1) SABLON CSS'i: sadece kisa kod tasiyan tekil sayfalarda ---- */
		$post_id = (int) get_queried_object_id();
		if ( $post_id && is_singular() ) {
			$icerik = get_post_field( 'post_content', $post_id );
			if ( is_string( $icerik ) && false !== strpos( $icerik, '[wpcode' )
			     && preg_match_all( '/\[wpcode[^\]]*id=["\']?(\d+)/', $icerik, $m ) ) {

				$liste = gbc_css_sablon_listesi();
				foreach ( array_unique( $m[1] ) as $sid ) {
					$sid = (int) $sid;
					if ( ! in_array( $sid, $liste, true ) ) {
						continue;
					}
					$url = gbc_css_dosya_url( $sid );
					if ( $url ) {
						/* Basilacaklar listesine al; kisa kodu da sustur. */
						$GLOBALS['gbc_css_basilacak'][ $sid ]  = $url;
						$GLOBALS['gbc_css_dosyalandi'][ $sid ] = true;
					}
				}
			}
		}

		/* ---- 2) CUSTOMIZER CSS'i: HER SAYFADA ----
		   Dosya yazilamazsa remove_action HIC calismaz, Customizer eskisi gibi
		   satir ici basar. En kotu senaryo bugunku davranis. */
		$cust = gbc_css_customizer_dosyasi();
		if ( $cust ) {
			$GLOBALS['gbc_css_customizer'] = $cust;
			remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
		}
	}
	add_action( 'wp_enqueue_scripts', 'gbc_css_dosyaya_al', 20 );
}

if ( ! function_exists( 'gbc_css_linkleri_bas' ) ) {
	/* Customizer CSS'i wp_head'de 101. oncelikte basiliyor; biz 200'deyiz. */
	function gbc_css_linkleri_bas() {
		$sablonlar = ( ! empty( $GLOBALS['gbc_css_basilacak'] ) && is_array( $GLOBALS['gbc_css_basilacak'] ) )
			? $GLOBALS['gbc_css_basilacak'] : array();
		if ( empty( $GLOBALS['gbc_css_customizer'] ) && ! $sablonlar ) {
			return;
		}
		/* SIRA ONEMLI: once Customizer, sonra sablon. */
		if ( ! empty( $GLOBALS['gbc_css_customizer'] ) ) {
			printf(
				'<link rel="stylesheet" id="gbc-css-customizer" href="%1$s" media="all" />' . "\n",
				esc_url( $GLOBALS['gbc_css_customizer'] )
			);
		}
		foreach ( $sablonlar as $sid => $url ) {
			printf(
				'<link rel="stylesheet" id="gbc-css-%1$d" href="%2$s" media="all" />' . "\n",
				(int) $sid,
				esc_url( $url )
			);
		}
	}
	add_action( 'wp_head', 'gbc_css_linkleri_bas', 200 );
}

if ( ! function_exists( 'gbc_css_kisa_kodu_sustur' ) ) {
	/* Dosyaya alinan snippet'in kisa kodu artik hicbir sey basmaz. */
	function gbc_css_kisa_kodu_sustur( $ret, $tag, $attr ) {
		if ( 'wpcode' !== $tag ) {
			return $ret;
		}
		$sid = isset( $attr['id'] ) ? (int) $attr['id'] : 0;
		if ( $sid && ! empty( $GLOBALS['gbc_css_dosyalandi'][ $sid ] ) ) {
			return '';
		}
		return $ret;
	}
	add_filter( 'pre_do_shortcode_tag', 'gbc_css_kisa_kodu_sustur', 10, 3 );
}
