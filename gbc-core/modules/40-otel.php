<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30242 — Otel Motoru. GBC Core'a taşındı, 28 Eylül 2026. */

/* ==================================================================
 * GBC 95 . OTEL MOTORU . PHP          [gbc_otel dest=sehir]     v3
 * ------------------------------------------------------------------
 * KURULUM: WPCode > snippet 30242 > kod alanina gir, TUMUNU SEC
 * (Ctrl+A), SIL, bu dosyanin TAMAMINI yapistir, kaydet.
 * Bu dosya bastan sona PHP'dir. Basina php acilis etiketi EKLEME,
 * WPCode onu kendisi ekliyor.
 * ------------------------------------------------------------------
 * v3'TE DEGISEN
 *  - Sonuc artik 1-2-3 diye numaralanmiyor. TEK net cevap ustte,
 *    "Sana en uygun bolge" rozetiyle, tam genislikte Booking butonu.
 *  - Altinda "Bunlar da olur" basligiyla iki kisa alternatif.
 *  - En altta "Baktigimiz oteller" seridi; ilk siradaki semtin oteli
 *    basa geliyor ve vurgulaniyor.
 *  - Her destinasyona istege bagli 'oteller' alani eklendi. Yoksa
 *    serit hic basilmiyor, hata vermiyor.
 * ------------------------------------------------------------------
 * YENI DESTINASYONA OTEL SERIDI EKLEMEK
 *   'oteller' => array(
 *     'baslik' => 'Baktigimiz oteller',
 *     'liste'  => array(
 *       array( 'ad'=>'Otel Adi', 'semt'=>'bolge_anahtari', 'not'=>'Semt',
 *              'url'=>'https://.../#place-7' ),
 *     ),
 *   ),
 *   'semt' degeri o destinasyonun 'bolgeler' dizisindeki anahtarla
 *   ayni olursa, o bolge birinci cikinca otel basa aliniyor.
 * ================================================================== */

/**
 * GBC · 95 · Otel Motoru · PHP   [gbc_otel dest=sehir]
 * ------------------------------------------------------------------
 * Sayfaya iki yoldan girer:
 *   1) Liste/Gezi alanina kisa kod:  [gbc_otel dest=atina]
 *   2) Yazinin post meta'sina:       gbc_otel_dest = atina
 *
 * VERI iki kaynaktan gelir ve birlestirilir:
 *   - gbc_otel_veri_temel()  : bu dosyanin icindeki destinasyonlar
 *   - post meta 30242 / gbc_otel_dest_data : kod degismeden eklenenler
 *   Ayni anahtar iki yerde varsa META kazanir.
 *
 * ORTAKLIK: her bolgenin 'slot' degeri defterde (30120) kayitli id'dir.
 * URL'yi gbc_tp_get() uretir, "is birligi" etiketini 30204 basar;
 * motor ikinci kez basmaz. Slotu olmayan bolge baglanti almaz.
 *
 * OLCULMUS KISIT: sablonlar icerik alanlarini wp_kses'ten geciriyor;
 * <form>, <select>, <style>, <script> siliniyor. Bu yuzden secim <a>
 * ile, CSS wp_head'e, JS ve JSON-LD wp_footer'a basiliyor.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ================================================================== */
/*  VERI                                                              */
/* ================================================================== */

if ( ! function_exists( 'gbc_otel_veri' ) ) {

function gbc_otel_veri() {
	static $bellek = null;
	if ( null !== $bellek ) { return $bellek; }
	$ek = get_post_meta( 30242, 'gbc_otel_dest_data', true );
	$temel = gbc_otel_veri_temel();
	$bellek = ( is_array( $ek ) && $ek ) ? array_merge( $temel, $ek ) : $temel;
	return $bellek;
}

function gbc_otel_veri_temel() {
	return array(

		'atina' => array(
			'baslik' => "Atina'da nerede kalmalısın?",
			'alt'    => 'Üç soru, on saniye. Atina’da asıl karar otel değil semt.',
			'sorular' => array(
				'q1' => array( 'metin' => 'İlk kez mi', 'siklar' => array(
					'ilk' => 'İlk kez', 'tekrar' => 'Daha önce geldim' ) ),
				'q2' => array( 'metin' => 'Kiminle', 'siklar' => array(
					'cift' => 'Çift', 'aile' => 'Çocuklu aile', 'grup' => 'Arkadaş grubu', 'tek' => 'Tek başıma' ) ),
				'q3' => array( 'metin' => 'Önceliğin', 'siklar' => array(
					'yuru' => 'Yürüyerek her yere', 'butce' => 'Bütçe', 'gece' => 'Gece hareketli olsun',
					'sessiz' => 'Aile ve sessizlik', 'ulasim' => 'Havalimanı bağlantısı' ) ),
			),
			'puan' => array(
				'q1' => array(
					'ilk'    => array( 'plaka' => 6, 'syntagma' => 2 ),
					'tekrar' => array( 'koukaki' => 3, 'kolonaki' => 2, 'monastiraki' => 1 ),
				),
				'q2' => array(
					'cift' => array( 'plaka' => 3, 'koukaki' => 2 ),
					'aile' => array( 'koukaki' => 4, 'plaka' => 1 ),
					'grup' => array( 'monastiraki' => 4, 'plaka' => 1 ),
					'tek'  => array( 'koukaki' => 2, 'monastiraki' => 2, 'syntagma' => 1 ),
				),
				'q3' => array(
					'yuru'   => array( 'plaka' => 7, 'monastiraki' => 2 ),
					'butce'  => array( 'koukaki' => 7, 'monastiraki' => 2 ),
					'gece'   => array( 'monastiraki' => 8 ),
					'sessiz' => array( 'kolonaki' => 6, 'koukaki' => 4 ),
					'ulasim' => array( 'syntagma' => 8 ),
				),
			),
			'bolgeler' => array(
				'plaka' => array(
					'ad' => 'Plaka ve Anafiotika', 'renk' => '', 'km' => "Akropolis'in eteği, merkezin ta kendisi",
					'kime' => 'İlk kez gelen, yürüyerek gezen',
					'ozet' => 'İlk kez geliyorsan doğru cevap burası. Araç trafiğine kapalı taş sokaklar; Akropolis, Antik Agora ve Monastiraki on dakika içinde.',
					'arti' => 'Her yere yürünüyor, gece sokaklar kalabalık ve rahat, ulaşıma para vermiyorsun.',
					'eksi' => 'Şehrin en pahalı bandı. Binalar eski, asansörsüz otel yaygın; Anafiotika merdivenli.',
					'slot' => 'atina_plaka',
					'ic' => array( 'Plaka bölümünü oku', 'https://gezginbirchef.com/atina-nerede-kalinir/#place-1' ),
				),
				'syntagma' => array(
					'ad' => 'Syntagma ve Ermou', 'renk' => '', 'km' => 'Şehrin tam merkezi, ulaşım düğümü',
					'kime' => 'Gece inen, sabah erken uçuşu olan',
					'ozet' => 'Havalimanı metrosu ve X95 otobüsü doğrudan buraya geliyor, valizle aktarma yapmıyorsun. Büyük ve kurumsal oteller bu çevrede.',
					'arti' => 'Havalimanı bağlantısı kapının önünde, resepsiyon 24 saat açık, her yere yürünüyor.',
					'eksi' => 'Meydana bakan odalar gürültülü, Ermou akşamları kalabalık.',
					'slot' => 'atina_syntagma',
					'ic' => array( 'Syntagma bölümünü oku', 'https://gezginbirchef.com/atina-nerede-kalinir/#place-2' ),
				),
				'koukaki' => array(
					'ad' => 'Koukaki', 'renk' => 'gz-yesil', 'km' => "Akropolis'in güney yamacının arkası",
					'kime' => 'Bütçesini koruyan, çocukla gelen, uzun kalan',
					'ozet' => 'Turist kalabalığından uzak, yerel halkın oturduğu sakin semt. Aynı paraya Plaka’dan daha yeni ve daha geniş oda çıkıyor.',
					'arti' => 'Fiyat ve konum dengesi en iyi burada, Akropolis Müzesi ve metro yürüme mesafesinde.',
					'eksi' => 'Turistik canlılık yok, bazı sokaklar yokuşlu.',
					'slot' => 'atina_koukaki',
					'ic' => array( 'Koukaki bölümünü oku', 'https://gezginbirchef.com/atina-nerede-kalinir/#place-3' ),
				),
				'monastiraki' => array(
					'ad' => 'Monastiraki ve Psirri', 'renk' => '', 'km' => "Antik Agora'nın yanı",
					'kime' => 'Geç saate kadar dışarıda olan, arkadaş grubu',
					'ozet' => 'Şehrin en hareketli gece bölgesi. Monastiraki durağı hem havalimanı hattına hem Pire hattına bağlanıyor.',
					'arti' => 'Merkeze yürünüyor, fiyatlar Plaka’nın altında, iki metro hattı elinin altında.',
					'eksi' => 'Gürültü. Sokağa bakan oda alma, iç avluya bakanı ya da üst katı seç.',
					'slot' => 'atina_monastiraki',
					'ic' => array( 'Monastiraki bölümünü oku', 'https://gezginbirchef.com/atina-nerede-kalinir/#place-4' ),
				),
				'kolonaki' => array(
					'ad' => 'Kolonaki', 'renk' => 'gz-yesil', 'km' => 'Lykavittos tepesinin eteği',
					'kime' => 'İkinci kez gelen, sakin mahalle isteyen',
					'ozet' => "Atina'nın en bakımlı mahallesi. Butik mağazalar, sakin kafeler, yerleşik bir nüfus.",
					'arti' => 'Şehrin en rahat bölgelerinden biri, Benaki ve Kiklad müzeleri yürüme mesafesinde.',
					'eksi' => 'Yokuş fazla; valizle ve her gün Akropolis tarafına inip çıkmak yorucu.',
					'slot' => 'atina_kolonaki',
					'ic' => array( 'Kolonaki bölümünü oku', 'https://gezginbirchef.com/atina-nerede-kalinir/#place-5' ),
				),
			),
			'oteller' => array(
				'baslik' => 'Baktığımız oteller',
				'liste'  => array(
					array( 'ad' => 'Hotel Byron', 'semt' => 'plaka', 'not' => 'Plaka', 'url' => 'https://gezginbirchef.com/atina-nerede-kalinir/#place-9' ),
					array( 'ad' => 'M18 Studios', 'semt' => 'monastiraki', 'not' => 'Monastiraki', 'url' => 'https://gezginbirchef.com/atina-nerede-kalinir/#place-10' ),
					array( 'ad' => 'Hotel Solomou Athens', 'semt' => '', 'not' => 'Omonia yakını', 'url' => 'https://gezginbirchef.com/atina-nerede-kalinir/#place-11' ),
					array( 'ad' => 'Apollo Hotel', 'semt' => '', 'not' => 'Merkez', 'url' => 'https://gezginbirchef.com/atina-nerede-kalinir/#place-12' ),
					array( 'ad' => 'Regal Hotel Mitropoleos', 'semt' => 'syntagma', 'not' => 'Syntagma yakını', 'url' => 'https://gezginbirchef.com/atina-nerede-kalinir/#place-13' ),
				),
			),
		),

		/* CAGLIARI ve RODOS buraya geri yazilacak.
		   Kalip yukaridaki 'atina' blogunun aynisi:
		   baslik, alt, sorular(q1,q2,q3), puan(q1,q2,q3), bolgeler(...).
		   Her bolge: ad, renk('' | gz-yesil | gz-mavi), km, kime, ozet,
		   arti, eksi, slot (defterdeki id), ic(array(baslik,url)). */

	);
}

/* ================================================================== */
/*  YARDIMCILAR                                                       */
/* ================================================================== */

function gbc_otel_ikon( $tip ) {
	if ( 'out' === $tip ) {
		return '<svg class="go-ikon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">'
			. '<path d="M7 17 17 7"/><path d="M9 7h8v8"/></svg>';
	}
	return '';
}

/** Bu yazi motoru kullaniyor mu? (wp_head CSS'i icin) */
function gbc_otel_hangi_dest( $pid = 0 ) {
	if ( ! $pid ) { $pid = get_queried_object_id(); }
	if ( ! $pid ) { return ''; }
	static $bellek = array();
	if ( isset( $bellek[ $pid ] ) ) { return $bellek[ $pid ]; }

	$dest = (string) get_post_meta( $pid, 'gbc_otel_dest', true );
	if ( '' === $dest ) {
		$tum = get_post_meta( $pid );
		foreach ( $tum as $anahtar => $degerler ) {
			if ( '_' === substr( $anahtar, 0, 1 ) ) { continue; }
			foreach ( (array) $degerler as $d ) {
				if ( is_string( $d ) && false !== strpos( $d, '[gbc_otel' ) ) {
					if ( preg_match( '/\[gbc_otel[^\]]*dest=["\']?([a-z0-9_-]+)/i', $d, $e ) ) {
						$dest = $e[1];
					}
					break 2;
				}
			}
		}
	}
	$dest = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $dest ) );
	$bellek[ $pid ] = $dest;
	return $dest;
}

} // function_exists

/* ================================================================== */
/*  KISA KOD                                                          */
/* ================================================================== */

if ( ! function_exists( 'gbc_otel_kisa_kod' ) ) {

function gbc_otel_kisa_kod( $atts ) {
	$a = shortcode_atts( array( 'dest' => '', 'baslik' => '' ), $atts, 'gbc_otel' );

	$dest = preg_replace( '/[^a-z0-9_-]/', '', strtolower( html_entity_decode( (string) $a['dest'] ) ) );
	if ( '' === $dest ) { $dest = gbc_otel_hangi_dest(); }

	$tum = gbc_otel_veri();
	if ( '' === $dest || ! isset( $tum[ $dest ] ) ) { return ''; }

	$d = $tum[ $dest ];
	if ( empty( $d['bolgeler'] ) ) { return ''; }

	$post_id = get_the_ID();
	$temel   = get_permalink( $post_id );

	/* secilen siklar (JS yoksa GET ile calisir) */
	$secim = array();
	foreach ( array( 'q1', 'q2', 'q3' ) as $q ) {
		$g = isset( $_GET[ 'o' . $q ] ) ? sanitize_key( wp_unslash( $_GET[ 'o' . $q ] ) ) : '';
		$secim[ $q ] = ( $g && isset( $d['sorular'][ $q ]['siklar'][ $g ] ) ) ? $g : '';
	}

	/* puanla */
	$puan = array();
	foreach ( $d['bolgeler'] as $anahtar => $b ) { $puan[ $anahtar ] = 0; }
	foreach ( $secim as $q => $sik ) {
		if ( '' === $sik || empty( $d['puan'][ $q ][ $sik ] ) ) { continue; }
		foreach ( $d['puan'][ $q ][ $sik ] as $anahtar => $p ) {
			if ( isset( $puan[ $anahtar ] ) ) { $puan[ $anahtar ] += (int) $p; }
		}
	}
	$cevap_var = ( '' !== $secim['q1'] || '' !== $secim['q2'] || '' !== $secim['q3'] );
	arsort( $puan );

	$GLOBALS['gbc_otel_aktif'] = true;
	$GLOBALS['gbc_otel_js_veri'] = array(
		'dest'     => $dest,
		'puan'     => isset( $d['puan'] ) ? $d['puan'] : array(),
		'bolgeler' => gbc_otel_js_bolgeler( $d['bolgeler'], $post_id ),
		'oteller'  => isset( $d['oteller'] ) ? $d['oteller'] : array(),
	);

	$baslik = '' !== $a['baslik'] ? $a['baslik'] : ( isset( $d['baslik'] ) ? $d['baslik'] : '' );

	$h  = '<div class="gbc-otel" id="gbc-otel" data-dest="' . esc_attr( $dest ) . '">';
	if ( '' !== $baslik ) {
		$h .= '<div class="go-bas"><b>' . esc_html( $baslik ) . '</b>';
		if ( ! empty( $d['alt'] ) ) { $h .= '<i>' . esc_html( $d['alt'] ) . '</i>'; }
		$h .= '</div>';
	}

	/* sorular */
	$h .= '<div class="go-sorular">';
	foreach ( array( 'q1', 'q2', 'q3' ) as $q ) {
		if ( empty( $d['sorular'][ $q ] ) ) { continue; }
		$s = $d['sorular'][ $q ];
		$h .= '<div class="go-soru"><span class="go-soru-et">' . esc_html( $s['metin'] ) . '</span><ul class="go-pils">';
		foreach ( $s['siklar'] as $k => $metin ) {
			$arg = array();
			foreach ( array( 'q1', 'q2', 'q3' ) as $q2 ) {
				$deger = ( $q2 === $q ) ? $k : $secim[ $q2 ];
				if ( '' !== $deger ) { $arg[ 'o' . $q2 ] = $deger; }
			}
			$url = ( $arg ? add_query_arg( $arg, $temel ) : $temel ) . '#gbc-otel';
			$h .= '<li><a class="go-pil' . ( $secim[ $q ] === $k ? ' acik' : '' ) . '"'
				. ' data-q="' . esc_attr( $q ) . '" data-sik="' . esc_attr( $k ) . '"'
				. ' href="' . esc_url( $url ) . '">' . esc_html( $metin ) . '</a></li>';
		}
		$h .= '</ul></div>';
	}
	$h .= '</div>';

	/* sonuc */
	$sema = array();
	$h .= '<div class="go-cikti">' . gbc_otel_sonuc( $d['bolgeler'], $puan, $cevap_var, $post_id, $sema, $d ) . '</div>';

	/* karsilastirma tablosu */
	$h .= gbc_otel_tablo( $d['bolgeler'], $post_id );

	$h .= '<p class="go-not">Seçim sayfayı yenilemeden çalışır. Bölge önerisi gezerken gördüklerimize dayanır, otel fiyatları tarihe göre değişir.</p>';
	$h .= '</div>';

	if ( $sema ) {
		$GLOBALS['gbc_otel_sema'] = array(
			'@context' => 'https://schema.org', '@type' => 'ItemList',
			'name' => $baslik ? $baslik : 'Bölge önerisi', 'itemListElement' => $sema,
		);
	}
	return $h;
}
add_shortcode( 'gbc_otel', 'gbc_otel_kisa_kod' );

/**
 * Ortaklik baglantisi. IKI defteri de tanir:
 *   1) gbc_tp_links secenegi  (gbc_tp_get ile; bud_*, rodos_*, ibiza_* ...)
 *   2) Ortaklik defteri 30120 ([gbc_aff] kisa koduyla; atina_*, otel_* ...)
 * Ilk kaynakta yoksa ikinciye duser. Ikisinde de yoksa bos doner,
 * kirik baglanti basilmaz.
 * Not: [gbc_aff] yolunda oku ve "is birligi" etiketini 30204 basiyor,
 * motor ikinci kez ikon EKLEMEZ (cift ok olmasin diye).
 */
function gbc_otel_baglanti( $b, $post_id, $sinif, $metin ) {
	$slot = isset( $b['slot'] ) ? (string) $b['slot'] : '';
	$slot = preg_replace( '/[^A-Za-z0-9_\-]/', '', $slot );
	if ( '' === $slot ) { return ''; }

	/* 1) Travelpayouts link deposu */
	/* GBC 21 Eylul 2026: oncelik duzeltildi. Defter (30120) tek dogru kaynak;
	   panel kaydi yalnizca gbc_aff kisa kodu hic yoksa devreye girer. */
	if ( function_exists( 'gbc_tp_get' ) && ! shortcode_exists( 'gbc_aff' ) ) {
		$url = gbc_tp_get( $slot );
		if ( $url ) {
			return '<a class="' . esc_attr( $sinif ) . '" href="' . esc_url( $url ) . '"'
				. ' target="_blank" rel="sponsored nofollow noopener"'
				. ' data-aff="' . esc_attr( $slot ) . '" data-prog="Booking.com"'
				. ' data-post="' . esc_attr( $post_id ) . '">'
				. esc_html( $metin ) . gbc_otel_ikon( 'out' ) . '</a>';
		}
	}

	/* 2) Ortaklik defteri 30120 */
	if ( shortcode_exists( 'gbc_aff' ) ) {
		$html = do_shortcode( '[gbc_aff id=' . $slot . ']' . $metin . '[/gbc_aff]' );
		if ( false !== strpos( $html, '<a' ) ) {
			return '<span class="' . esc_attr( $sinif . '-dis' ) . '">' . $html . '</span>';
		}
	}
	return '';
}

function gbc_otel_kart( $b, $post_id, $sira, $mod = 'tam' ) {
	$renk = ! empty( $b['renk'] ) ? ' ' . $b['renk'] : '';

	if ( 'mini' === $mod ) {
		$h  = '<article class="go-kart go-mini' . esc_attr( $renk ) . '">';
		$h .= '<div class="go-mini-ust"><span class="go-ad">' . esc_html( $b['ad'] ) . '</span>';
		if ( ! empty( $b['km'] ) ) { $h .= '<span class="go-km">' . esc_html( $b['km'] ) . '</span>'; }
		$h .= '</div>';
		if ( ! empty( $b['kime'] ) ) { $h .= '<p class="go-kime">' . esc_html( $b['kime'] ) . '</p>'; }
		$h .= '<p class="go-baglar go-baglar-mini">';
		$h .= gbc_otel_baglanti( $b, $post_id, 'go-tcta', $b['ad'] . ' otellerine bak' );
		if ( ! empty( $b['ic'][1] ) ) {
			$h .= '<a class="go-ic" href="' . esc_url( $b['ic'][1] ) . '">' . esc_html( $b['ic'][0] ) . '</a>';
		}
		$h .= '</p></article>';
		return $h;
	}

	$h  = '<article class="go-kart go-bir' . esc_attr( $renk ) . '">';
	$h .= '<div class="go-rozet">Sana en uygun bölge</div>';
	$h .= '<div class="go-kart-bas"><span class="go-ad">' . esc_html( $b['ad'] ) . '</span>';
	if ( ! empty( $b['km'] ) ) { $h .= '<span class="go-km">' . esc_html( $b['km'] ) . '</span>'; }
	$h .= '</div>';
	if ( ! empty( $b['kime'] ) ) { $h .= '<p class="go-kime"><b>Kime uygun:</b> ' . esc_html( $b['kime'] ) . '</p>'; }
	if ( ! empty( $b['ozet'] ) ) { $h .= '<p class="go-ozet">' . esc_html( $b['ozet'] ) . '</p>'; }
	if ( ! empty( $b['arti'] ) || ! empty( $b['eksi'] ) ) {
		$h .= '<div class="go-ae">';
		if ( ! empty( $b['arti'] ) ) { $h .= '<p class="go-arti"><b>Artısı</b> ' . esc_html( $b['arti'] ) . '</p>'; }
		if ( ! empty( $b['eksi'] ) ) { $h .= '<p class="go-eksi"><b>Eksisi</b> ' . esc_html( $b['eksi'] ) . '</p>'; }
		$h .= '</div>';
	}
	$cta = gbc_otel_baglanti( $b, $post_id, 'go-cta', $b['ad'] . ' otellerini gör' );
	if ( '' !== $cta ) { $h .= '<div class="go-cta-sat">' . $cta . '</div>'; }
	if ( ! empty( $b['ic'][1] ) ) {
		$h .= '<p class="go-baglar"><a class="go-ic" href="' . esc_url( $b['ic'][1] ) . '">' . esc_html( $b['ic'][0] ) . '</a></p>';
	}
	$h .= '</article>';
	return $h;
}

/** Kaldigimiz oteller seridi; ilk siradaki semtinkiler basa gelir. */
function gbc_otel_oteller( $d, $ust_semt ) {
	if ( empty( $d['oteller']['liste'] ) ) { return ''; }
	$liste = $d['oteller']['liste'];
	$once = array(); $sonra = array();
	foreach ( $liste as $o ) {
		if ( $ust_semt && ! empty( $o['semt'] ) && $o['semt'] === $ust_semt ) { $once[] = $o; } else { $sonra[] = $o; }
	}
	$sirali = array_merge( $once, $sonra );
	$bas = ! empty( $d['oteller']['baslik'] ) ? $d['oteller']['baslik'] : 'Bu semtlerde kaldığımız yerler';
	$h = '<div class="go-oteller"><span class="go-oteller-bas">' . esc_html( $bas ) . '</span><ul>';
	foreach ( $sirali as $i => $o ) {
		if ( empty( $o['ad'] ) || empty( $o['url'] ) ) { continue; }
		$vur = ( $ust_semt && ! empty( $o['semt'] ) && $o['semt'] === $ust_semt ) ? ' class="go-otel-vurgu"' : '';
		$h .= '<li' . $vur . '><a href="' . esc_url( $o['url'] ) . '">' . esc_html( $o['ad'] ) . '</a>';
		if ( ! empty( $o['not'] ) ) { $h .= '<span class="go-otel-not">' . esc_html( $o['not'] ) . '</span>'; }
		$h .= '</li>';
	}
	$h .= '</ul></div>';
	return $h;
}

function gbc_otel_sonuc( $bolgeler, $puan, $cevap_var, $post_id, &$sema, $d = array() ) {
	if ( ! $cevap_var ) {
		return '<p class="go-bos">Üç soruyu işaretle, sana uyan bölge en üstte çıksın. İşaretlemeden de aşağıdaki tablodan bütün bölgeleri karşılaştırabilirsin.</p>';
	}
	$h = ''; $sira = 0; $ust = ''; $mini = '';
	foreach ( $puan as $anahtar => $p ) {
		if ( $p <= 0 || empty( $bolgeler[ $anahtar ] ) ) { continue; }
		$sira++;
		if ( $sira > 3 ) { break; }
		if ( 1 === $sira ) {
			$ust = $anahtar;
			$h .= gbc_otel_kart( $bolgeler[ $anahtar ], $post_id, 1, 'tam' );
		} else {
			$mini .= gbc_otel_kart( $bolgeler[ $anahtar ], $post_id, $sira, 'mini' );
		}
		$sema[] = array( '@type' => 'ListItem', 'position' => $sira, 'name' => $bolgeler[ $anahtar ]['ad'] );
	}
	if ( '' === $h ) {
		return '<p class="go-bos">Bu üçlü için net bir eşleşme çıkmadı. Bir soruyu değiştirip dene ya da aşağıdaki tabloya bak.</p>';
	}
	if ( '' !== $mini ) {
		$h .= '<div class="go-digerleri"><span class="go-digerleri-bas">Bunlar da olur</span>' . $mini . '</div>';
	}
	$h .= gbc_otel_oteller( $d, $ust );
	return $h;
}

function gbc_otel_tablo( $bolgeler, $post_id ) {
	$h  = '<details class="go-det"><summary>Bütün bölgeleri tek tabloda karşılaştır</summary>';
	$h .= '<div class="go-tablo"><table><thead><tr><th>Bölge</th><th>Kime uygun</th><th>Merkeze uzaklık</th><th>Otele bak</th></tr></thead><tbody>';
	foreach ( $bolgeler as $anahtar => $b ) {
		$h .= '<tr class="' . esc_attr( isset( $b['renk'] ) ? $b['renk'] : '' ) . '" data-satir="' . esc_attr( $anahtar ) . '">';
		$h .= '<td class="go-b"><i></i>' . esc_html( $b['ad'] ) . '</td>';
		$h .= '<td>' . esc_html( isset( $b['kime'] ) ? $b['kime'] : '' ) . '</td>';
		$h .= '<td>' . esc_html( isset( $b['km'] ) ? $b['km'] : '' ) . '</td>';
		$h .= '<td class="go-tl">' . gbc_otel_baglanti( $b, $post_id, 'go-tcta', 'Otellere bak' ) . '</td>';
		$h .= '</tr>';
	}
	$h .= '</tbody></table></div></details>';
	return $h;
}

/** JS'in cizecegi kartlar icin hazir HTML (baglantilar PHP'de uretiliyor). */
function gbc_otel_js_bolgeler( $bolgeler, $post_id ) {
	$c = array();
	foreach ( $bolgeler as $anahtar => $b ) {
		$c[ $anahtar ] = array(
			'ad'   => $b['ad'],
			'tam'  => gbc_otel_kart( $b, $post_id, 1, 'tam' ),
			'mini' => gbc_otel_kart( $b, $post_id, 0, 'mini' ),
		);
	}
	return $c;
}

} // function_exists

/* ================================================================== */
/*  CSS, wp_head (icerik alani <style> siliyor)                       */
/* ================================================================== */

if ( ! function_exists( 'gbc_otel_css' ) ) {

function gbc_otel_css() {
	if ( ! is_singular() ) { return; }
	$dest = gbc_otel_hangi_dest();
	if ( '' === $dest ) { return; }
	$tum = gbc_otel_veri();
	if ( ! isset( $tum[ $dest ] ) ) { return; }

	echo '<style id="gbc-otel-css">'
	. '.gbc-otel{--bk:#BF360C;--lns:#ecd9cf;--bg:#fff;margin:22px 0;font-size:14px;grid-column:1/-1;width:100%;max-width:100%;box-sizing:border-box;border:1px solid var(--lns);border-radius:14px;background:var(--bg);overflow:hidden}'
	. '.gbc-otel .go-bas{padding:14px 18px 12px;border-bottom:1px solid var(--lns)}'
	. '.gbc-otel .go-bas b{display:block;font-size:17px;line-height:1.35;color:#2c3e50;font-weight:700}'
	. '.gbc-otel .go-bas i{display:block;margin-top:3px;font-style:normal;font-size:13px;color:#7b6a62;line-height:1.5}'
	. '.gbc-otel .go-sorular{padding:12px 18px}'
	. '.gbc-otel .go-soru{display:flex;align-items:center;gap:10px}'
	. '.gbc-otel .go-soru+.go-soru{margin-top:8px;padding-top:8px;border-top:1px solid #f4ece8}'
	. '.gbc-otel .go-soru-et{flex:0 0 auto;min-width:78px;font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#9a6b53}'
	. '.gbc-otel .go-pils{display:flex;gap:6px;margin:0;padding:2px 0;list-style:none;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}'
	. '.gbc-otel .go-pils::-webkit-scrollbar{display:none}'
	. '.gbc-otel .go-pils li{flex:0 0 auto;margin:0;padding:0}'
	. '.gbc-otel .go-pils li:before{display:none}'
	. '.gbc-otel .go-pil{display:block;padding:7px 13px;border:1px solid #e6d3c8;border-radius:999px;background:#fff;color:#5a3a2a;font-size:13.5px;line-height:1.25;text-decoration:none;white-space:nowrap;transition:background .12s,border-color .12s,color .12s}'
	. '.gbc-otel .go-pil:hover{border-color:var(--bk);color:var(--bk)}'
	. '.gbc-otel .go-pil.acik{background:var(--bk);border-color:var(--bk);color:#fff;font-weight:700}'
	. '.gbc-otel .go-cikti{padding:0 18px 4px}'
	. '.gbc-otel .go-bos{margin:0;padding:14px 0 16px;font-size:13.8px;color:#7b6a62;line-height:1.6}'
	. '.gbc-otel .go-kart{padding:15px 0;border-top:1px solid #f1e9e5}'
	. '.gbc-otel .go-bir{border-top:0;padding:16px 16px 18px;margin:12px 0 0;background:#FBF3EF;border:1px solid #F0DFD6;border-left:4px solid var(--bk);border-radius:12px}'
	. '.gbc-otel .go-rozet{display:inline-block;margin-bottom:8px;padding:4px 10px;border-radius:999px;background:var(--bk);color:#fff;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}'
	. '.gbc-otel .go-bir .go-ad{font-size:19px}'
	. '.gbc-otel .go-ae{margin:8px 0 0;padding:9px 12px;background:#fff;border:1px solid #f0e6e1;border-radius:9px}'
	. '.gbc-otel .go-ae p{margin:0}'
	. '.gbc-otel .go-ae p+p{margin-top:5px}'
	. '.gbc-otel .go-cta-sat{margin:13px 0 0}'
	. '.gbc-otel .go-cta-sat a,.gbc-otel .go-cta-sat .go-cta-dis a{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;box-sizing:border-box;padding:13px 18px;border-radius:10px;background:var(--bk);color:#fff!important;font-weight:700;font-size:15px;text-decoration:none;text-align:center}'
	. '.gbc-otel .go-cta-sat .go-cta-dis{display:block}'
	. '.gbc-otel .go-digerleri{margin:14px 0 0;padding:12px 0 0;border-top:1px dashed #e8ddd7}'
	. '.gbc-otel .go-digerleri-bas{display:block;margin-bottom:8px;font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#9a6b53}'
	. '.gbc-otel .go-mini{display:block;padding:11px 13px;margin-top:8px;border:1px solid #f0e6e1;border-top:1px solid #f0e6e1;border-radius:10px;background:#fff}'
	. '.gbc-otel .go-mini-ust{display:flex;align-items:baseline;flex-wrap:wrap;gap:7px;margin-bottom:4px}'
	. '.gbc-otel .go-mini .go-ad{font-size:15px}'
	. '.gbc-otel .go-mini .go-kime{margin:0 0 7px;font-size:13.4px;color:#5f5148}'
	. '.gbc-otel .go-baglar-mini{display:flex;flex-wrap:wrap;gap:6px 14px;margin:0}'
	. '.gbc-otel .go-oteller{margin:14px 0 0;padding:12px 14px;background:#fff;border:1px solid #f0e6e1;border-radius:10px}'
	. '.gbc-otel .go-oteller-bas{display:block;margin-bottom:7px;font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#9a6b53}'
	. '.gbc-otel .go-oteller ul{margin:0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px 8px}'
	. '.gbc-otel .go-oteller li{margin:0;padding:5px 10px;border:1px solid #ece1db;border-radius:999px;font-size:13px;background:#fdfbfa}'
	. '.gbc-otel .go-oteller li:before{display:none}'
	. '.gbc-otel .go-oteller li a{color:#5a3a2a;text-decoration:none}'
	. '.gbc-otel .go-oteller li a:hover{color:var(--bk)}'
	. '.gbc-otel .go-otel-vurgu{border-color:var(--bk)!important;background:#FBF3EF!important}'
	. '.gbc-otel .go-otel-vurgu a{color:var(--bk)!important;font-weight:700}'
	. '.gbc-otel .go-otel-not{display:block;font-size:11.5px;color:#8a7a72}'
	. '.gbc-otel .go-kart:first-child{border-top:0}'
	. '.gbc-otel .go-kart-bas{display:flex;align-items:baseline;flex-wrap:wrap;gap:8px;margin-bottom:7px}'
		. '.gbc-otel .go-ad{font-size:16px;font-weight:700;color:#2c3e50}'
	. '.gbc-otel .go-km{font-size:12.5px;color:#8a7a72}'
	. '.gbc-otel .go-kart p{margin:0 0 6px;font-size:13.8px;line-height:1.62;color:#444}'
	. '.gbc-otel .go-kart p b{color:#2c3e50}'
	. '.gbc-otel .go-arti b{color:#2e7d32}'
	. '.gbc-otel .go-eksi b{color:#c62828}'
	. '.gbc-otel .go-baglar{margin:9px 0 0;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center}'
	. '.gbc-otel .go-cta{display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border-radius:999px;background:var(--bk);color:#fff;font-weight:700;font-size:13.5px;text-decoration:none}'
	. '.gbc-otel .go-cta:hover{background:#a52f0a;color:#fff}'
	. '.gbc-otel .go-ic{color:#5a3a2a;font-size:13.5px;text-decoration:none;border-bottom:1px dotted #c8ab9c}'
	. '.gbc-otel .go-ic:hover{color:var(--bk);border-bottom-color:var(--bk)}'
	. '.gbc-otel .go-det{border-top:1px solid var(--lns);background:var(--bg)}'
	. '.gbc-otel .go-det summary{cursor:pointer;list-style:none;padding:13px 18px;font-size:14.5px;font-weight:600;color:#5a3a2a;position:relative}'
	. '.gbc-otel .go-det summary::-webkit-details-marker{display:none}'
	. '.gbc-otel .go-det summary::after{content:"";position:absolute;right:20px;top:50%;width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:translateY(-70%) rotate(45deg);transition:transform .15s}'
	. '.gbc-otel .go-det[open] summary::after{transform:translateY(-30%) rotate(-135deg)}'
	. '.gbc-otel .go-tablo{overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0 18px 16px}'
	. '.gbc-otel .go-tablo table{width:100%;border-collapse:collapse;font-size:13.4px}'
	. '.gbc-otel .go-tablo th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--lns);color:#5a3a2a;font-size:12px;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}'
	. '.gbc-otel .go-tablo td{padding:10px;border-bottom:1px solid #f1e9e5;color:#444;vertical-align:top}'
	. '.gbc-otel .go-tablo .go-b{font-weight:700;color:#2c3e50;white-space:nowrap}'
	. '.gbc-otel .go-tablo .go-b i{display:inline-block;width:8px;height:8px;border-radius:50%;background:#cfc3bc;margin-right:7px;vertical-align:1px}'
	. '.gbc-otel .go-tablo tr.gz-yesil .go-b i{background:#2e7d32}'
	. '.gbc-otel .go-tablo tr.gz-mavi .go-b i{background:#1565c0}'
	. '.gbc-otel .go-tl{white-space:nowrap}'
	. '.gbc-otel .go-tcta{display:inline-flex;align-items:center;gap:5px;color:var(--bk);font-weight:600;font-size:13.2px;text-decoration:none;white-space:nowrap}'
	. '.gbc-otel .go-tcta:hover{text-decoration:underline}'
	. '.gbc-otel .go-cta-dis a{display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border-radius:999px;background:var(--bk);color:#fff;font-weight:700;font-size:13.5px;text-decoration:none}'
	. '.gbc-otel .go-cta-dis a:hover{background:#a52f0a;color:#fff}'
	. '.gbc-otel .go-tcta-dis a{color:var(--bk);font-weight:600;font-size:13.2px;text-decoration:none;white-space:nowrap}'
	. '.gbc-otel .go-tcta-dis a:hover{text-decoration:underline}'
	. '.gbc-otel .go-ikon{flex:0 0 auto}'
	. '.gbc-otel .go-not{margin:0;padding:0 18px 16px;font-size:12px;color:#8a8a8a;line-height:1.6}'
	. '.gbc-otel .go-parla{animation:gbcOtelParla 1.1s ease-out 1}'
	. '@keyframes gbcOtelParla{0%{box-shadow:0 0 0 3px rgba(191,54,12,.30)}100%{box-shadow:0 0 0 3px rgba(191,54,12,0)}}'
	. '@media(max-width:600px){.gbc-otel .go-soru{flex-direction:column;align-items:stretch;gap:6px}'
	. '.gbc-otel .go-soru-et{min-width:0;font-size:11px}'
	. '.gbc-otel .go-pil{font-size:14px;padding:8px 13px}'
	. '.gbc-otel .go-bas,.gbc-otel .go-sorular,.gbc-otel .go-cikti,.gbc-otel .go-tablo,.gbc-otel .go-not{padding-left:14px;padding-right:14px}'
	. '.gbc-otel .go-det summary{padding-left:14px;padding-right:14px}'
	. '.gbc-otel .go-cta{width:100%;justify-content:center}'
	. '.gbc-otel .go-bir{padding:14px 13px 16px}'
	. '.gbc-otel .go-bir .go-ad{font-size:17px}'
	. '.gbc-otel .go-oteller ul{flex-direction:column}'
	. '.gbc-otel .go-oteller li{width:100%;box-sizing:border-box}}'
	. '</style>';
}
add_action( 'wp_head', 'gbc_otel_css', 20 );

/* ================================================================== */
/*  JS + JSON-LD, wp_footer (icerik alani <script> siliyor)           */
/* ================================================================== */

function gbc_otel_footer() {
	if ( ! empty( $GLOBALS['gbc_otel_sema'] ) ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $GLOBALS['gbc_otel_sema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	}
	if ( empty( $GLOBALS['gbc_otel_aktif'] ) || empty( $GLOBALS['gbc_otel_js_veri'] ) ) { return; }
	echo '<script id="gbc-otel-js">window.GBC_OTEL=' . wp_json_encode( $GLOBALS['gbc_otel_js_veri'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';' . gbc_otel_js() . '</script>';
}
add_action( 'wp_footer', 'gbc_otel_footer', 99 );

function gbc_otel_js() {
	return <<<'JS'
(function(){
 var V=window.GBC_OTEL; if(!V) return;
 var kok=document.getElementById('gbc-otel'); if(!kok) return;
 var cikti=kok.querySelector('.go-cikti'); if(!cikti) return;
 var secim={q1:'',q2:'',q3:''};
 kok.querySelectorAll('.go-pil.acik').forEach(function(a){ secim[a.getAttribute('data-q')]=a.getAttribute('data-sik'); });

 /* EKLENDI 21 Eylul 2026: ciz() icinde oteller() cagriliyordu ama boyle bir JS
    fonksiyonu hic tanimli degildi. Pile tiklaninca ReferenceError atiliyor,
    cikti.innerHTML hic yazilmiyor, yani motor tiklamaya hic cevap vermiyordu.
    Asagidaki fonksiyon PHP'deki gbc_otel_oteller() ile ayni isi yapiyor. */
 function esc(s){ return String(s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

 function oteller(ustSemt){
  var O=V.oteller; if(!O||!O.liste||!O.liste.length) return '';
  var once=[],sonra=[],i,o;
  for(i=0;i<O.liste.length;i++){ o=O.liste[i];
   if(ustSemt&&o.semt&&o.semt===ustSemt){ once.push(o); } else { sonra.push(o); } }
  var sirali=once.concat(sonra);
  var bas=O.baslik?O.baslik:'Bu semtlerde kaldığımız yerler';
  var h='<div class="go-oteller"><span class="go-oteller-bas">'+esc(bas)+'</span><ul>';
  for(i=0;i<sirali.length;i++){ o=sirali[i];
   if(!o.ad||!o.url) continue;
   var vur=(ustSemt&&o.semt&&o.semt===ustSemt)?' class="go-otel-vurgu"':'';
   h+='<li'+vur+'><a href="'+esc(o.url)+'">'+esc(o.ad)+'</a>';
   if(o.not){ h+='<span class="go-otel-not">'+esc(o.not)+'</span>'; }
   h+='</li>'; }
  return h+'</ul></div>';
 }

 function ciz(kaydir){
  var puan={},k;
  for(k in V.bolgeler){ puan[k]=0; }
  ['q1','q2','q3'].forEach(function(q){
   var s=secim[q]; if(!s) return;
   var m=(V.puan[q]||{})[s]; if(!m) return;
   for(var b in m){ if(puan.hasOwnProperty(b)) puan[b]+=m[b]; }
  });
  var cevapVar=!!(secim.q1||secim.q2||secim.q3);
  if(!cevapVar){
   cikti.innerHTML='<p class="go-bos">Üç soruyu işaretle, sana uyan bölgeler sırayla çıksın. İşaretlemeden de aşağıdaki tablodan bütün bölgeleri karşılaştırabilirsin.</p>';
   return;
  }
  var sira=Object.keys(puan).filter(function(b){return puan[b]>0;})
    .sort(function(a,b){return puan[b]-puan[a];}).slice(0,3);
  if(!sira.length){
   cikti.innerHTML='<p class="go-bos">Bu üçlü için net bir eşleşme çıkmadı. Bir soruyu değiştirip dene ya da aşağıdaki tabloya bak.</p>';
   return;
  }
  var h=V.bolgeler[sira[0]].tam, mini='';
  for(var i=1;i<sira.length;i++){
   mini+=V.bolgeler[sira[i]].mini;
  }
  if(mini) h+='<div class="go-digerleri"><span class="go-digerleri-bas">Bunlar da olur</span>'+mini+'</div>';
  h+=oteller(sira[0]);
  cikti.innerHTML=h;
  cikti.classList.remove('go-parla'); void cikti.offsetWidth; cikti.classList.add('go-parla');
  if(kaydir){
   try{
    var p=[]; ['q1','q2','q3'].forEach(function(q){ if(secim[q]) p.push('o'+q+'='+secim[q]); });
    history.replaceState(null,'',(p.length?'?'+p.join('&'):location.pathname)+'#gbc-otel');
   }catch(e){}
  }
 }

 kok.addEventListener('click',function(e){
  var a=e.target.closest('.go-pil'); if(!a) return;
  e.preventDefault();
  var q=a.getAttribute('data-q'), s=a.getAttribute('data-sik');
  secim[q]=(secim[q]===s)?'':s;
  kok.querySelectorAll('.go-pil[data-q="'+q+'"]').forEach(function(x){ x.classList.remove('acik'); });
  if(secim[q]) a.classList.add('acik');
  ciz(true);
 });
})();
JS;
}

} // function_exists
