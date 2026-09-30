<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — İş Ortaklığı paneli.  v1 · 28 Eylül 2026
 * ------------------------------------------------------------------
 * Tek ekran, altı sekme:
 *   Genel Bakış · Bağlantılar · Programlar · Sayfalar · Üret · Kısa Kodlar
 *
 * Defter (30120 numaralı sayfa) kullanıcıya HAM GÖSTERİLMEZ. Bağlantılar
 * sekmesindeki tablo satır satır düzenlenir; her yazma işlemi yalnızca
 * ilgili satıra dokunur, öncesinde tam yedek alınır ve geri alınabilir.
 *
 * 21 Eylül 2026 tarihli "panelden defter yazma KAPATILDI" notunun sebebi
 * eski panelin defterin TAMAMINI tek textarea'dan üzerine yazmasıydı; bir
 * hata bütün defteri siliyordu. Burada yazma satır kapsamlı, nonce korumalı
 * ve yedekli olduğu için o gerekçe ortadan kalktı. Eski toplu yazma yolu
 * hâlâ kapalı.
 */

if ( ! function_exists( 'gbc_ort_defter_id' ) ) {
function gbc_ort_defter_id() {
	return defined( 'GBC_AFF_DEFTER_ID' ) ? (int) GBC_AFF_DEFTER_ID : 30120;
}
}

/* ============================================================
   1) DEFTER — OKUMA
   ------------------------------------------------------------
   30120 sayfasında beş <pre> bloğu var ve bağlantılar BİRDEN FAZLA
   bloğa yayılmış durumda (28 Eylül 2026 ölçümü: 349 + 318 = 667).
   Bu yüzden "en büyük bloğu al" yaklaşımı yetmez; bütün bloklar
   okunur, her satır hangi bloktan geldiğini yanında taşır ve yazma
   işlemi yalnızca o bloğa dokunur.
   ============================================================ */

/** Defter sayfası. */
function gbc_ort_sayfa() {
	$s = get_page_by_path( 'is-ortakligi-baglanti-defteri', OBJECT, 'page' );
	if ( ! $s || empty( $s->post_content ) ) { $s = get_post( gbc_ort_defter_id() ); }
	return $s;
}

/**
 * Sayfadaki bütün <pre> bloklarının iç sınırları, belge sırasıyla.
 *
 * @return array liste: array( 'bas' => int, 'son' => int )
 */
function gbc_ort_bloklar( $icerik ) {
	$out = array();
	if ( preg_match_all( '#<pre[^>]*>#i', (string) $icerik, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $t ) {
			$bas = $t[1] + strlen( $t[0] );
			$son = stripos( $icerik, '</pre>', $bas );
			if ( false === $son ) { continue; }
			$out[] = array( 'bas' => $bas, 'son' => $son );
		}
	}
	return $out;
}

/** Tek bir bloğun çözülmüş metni. */
function gbc_ort_blok_ham( $sira ) {
	$sayfa = gbc_ort_sayfa();
	if ( ! $sayfa ) { return ''; }
	$b = gbc_ort_bloklar( $sayfa->post_content );
	if ( ! isset( $b[ (int) $sira ] ) ) { return ''; }
	$ic = substr( $sayfa->post_content, $b[ $sira ]['bas'], $b[ $sira ]['son'] - $b[ $sira ]['bas'] );
	return html_entity_decode( wp_strip_all_tags( $ic ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** Bir metin satırı bağlantı kaydı mı? Öyleyse alanlarını döndürür. */
function gbc_ort_satir_coz( $satir ) {
	$satir = trim( (string) $satir );
	if ( '' === $satir || false === strpos( $satir, '|' ) ) { return null; }
	$p = array_map( 'trim', explode( '|', $satir ) );
	if ( count( $p ) < 4 ) { return null; }
	$id = sanitize_key( $p[0] );
	if ( '' === $id || 'id' === $id ) { return null; }
	if ( '' !== $p[3] && 0 !== stripos( $p[3], 'http' ) ) { return null; }
	return array(
		'id'      => $id,
		'etiket'  => $p[1],
		'program' => $p[2],
		'url'     => $p[3],
		'ag'      => isset( $p[4] ) ? $p[4] : '',
	);
}

/**
 * Bütün bloklardaki bağlantı satırları.
 *
 * @return array id => array( etiket, program, url, ag, blok, satir )
 */
function gbc_ort_satirlar() {
	$out   = array();
	$sayfa = gbc_ort_sayfa();
	if ( ! $sayfa ) { return $out; }

	$bloklar = gbc_ort_bloklar( $sayfa->post_content );
	foreach ( $bloklar as $bi => $b ) {
		$ham = gbc_ort_blok_ham( $bi );
		if ( '' === $ham ) { continue; }
		$no = -1;
		foreach ( preg_split( '/\R/u', $ham ) as $satir ) {
			$no++;
			$c = gbc_ort_satir_coz( $satir );
			if ( ! $c ) { continue; }
			if ( isset( $out[ $c['id'] ] ) ) { continue; } /* ilk kayıt geçerli */
			$out[ $c['id'] ] = array(
				'etiket'  => $c['etiket'],
				'program' => $c['program'],
				'url'     => $c['url'],
				'ag'      => $c['ag'],
				'blok'    => $bi,
				'satir'   => $no,
			);
		}
	}
	return $out;
}

/** Yeni kayıtların ekleneceği blok: en çok bağlantı taşıyan blok. */
function gbc_ort_ekleme_blogu() {
	$sayac = array();
	foreach ( gbc_ort_satirlar() as $s ) {
		$b = (int) $s['blok'];
		$sayac[ $b ] = isset( $sayac[ $b ] ) ? $sayac[ $b ] + 1 : 1;
	}
	if ( ! $sayac ) { return 0; }
	arsort( $sayac );
	return (int) key( $sayac );
}

/** Motorun gördüğü toplam kayıt. */
function gbc_ort_motor_adet() {
	if ( function_exists( 'gbc_aff_defter' ) ) { return count( (array) gbc_aff_defter() ); }
	return count( gbc_ort_satirlar() );
}

/* ============================================================
   2) DEFTER — YAZMA (satır kapsamlı, yedekli)
   ============================================================ */

/** Yedekleri saklayan seçenek adı. */
function gbc_ort_yedek_anahtar() { return 'gbc_aff_defter_yedek'; }

/**
 * Yazmadan önce SAYFANIN TAMAMINI saklar (son 10 kopya).
 * Blok bazlı değil sayfa bazlı: bir yazma yanlış bloğa dokunsa bile
 * geri dönüş noktası eksiksiz olsun.
 */
function gbc_ort_yedek_al( $icerik, $aciklama = '' ) {
	$y = get_option( gbc_ort_yedek_anahtar() );
	if ( ! is_array( $y ) ) { $y = array(); }
	array_unshift( $y, array(
		'zaman'  => current_time( 'mysql' ),
		'kim'    => get_current_user_id(),
		'ne'     => (string) $aciklama,
		'satir'  => count( gbc_ort_satirlar() ),
		'icerik' => (string) $icerik,
	) );
	if ( count( $y ) > 10 ) { $y = array_slice( $y, 0, 10 ); }
	update_option( gbc_ort_yedek_anahtar(), $y, false );
}

/** Bir yazmanın ardından önbellekleri düşürür. */
function gbc_ort_onbellek_bosalt() {
	if ( function_exists( 'gbc_aff_defter_bosalt' ) ) { gbc_aff_defter_bosalt(); }
	delete_transient( 'gbc_ort_kullanim' );
	delete_transient( 'gbc_ort_sablon' );
	delete_transient( 'gbc_ort_bos' );
	delete_transient( 'gbc_ort_kisa' );
}

/**
 * Tek bir <pre> bloğunun içeriğini değiştirir ve sayfayı kaydeder.
 * wp_update_post ayrıca WordPress revizyonu bırakır: iki kat yedek.
 *
 * @return true|string
 */
function gbc_ort_blok_yaz( $sira, $yeni_ic, $aciklama ) {
	$sayfa = gbc_ort_sayfa();
	if ( ! $sayfa ) { return 'Defter sayfası bulunamadı.'; }
	$bloklar = gbc_ort_bloklar( $sayfa->post_content );
	$sira = (int) $sira;
	if ( ! isset( $bloklar[ $sira ] ) ) { return 'Defterdeki blok bulunamadı (' . $sira . ').'; }

	/* Güvenlik freni: defterin tamamı bir hamlede %40'tan fazla küçülemez. */
	$onceki = count( gbc_ort_satirlar() );
	$eski_blok = 0;
	foreach ( gbc_ort_satirlar() as $s ) { if ( (int) $s['blok'] === $sira ) { $eski_blok++; } }
	$yeni_blok = 0;
	foreach ( preg_split( '/\R/u', (string) $yeni_ic ) as $satir ) {
		if ( gbc_ort_satir_coz( $satir ) ) { $yeni_blok++; }
	}
	$sonraki = $onceki - $eski_blok + $yeni_blok;
	if ( $onceki > 20 && $sonraki < ( $onceki * 0.6 ) ) {
		return 'İşlem durduruldu: defter ' . $onceki . ' kayıttan ' . $sonraki . ' kayda düşüyordu. Bu bir hata gibi görünüyor, hiçbir şey yazılmadı.';
	}

	gbc_ort_yedek_al( $sayfa->post_content, $aciklama );

	$icerik = substr( $sayfa->post_content, 0, $bloklar[ $sira ]['bas'] )
		. "\n" . trim( (string) $yeni_ic ) . "\n"
		. substr( $sayfa->post_content, $bloklar[ $sira ]['son'] );

	$sonuc = wp_update_post( array( 'ID' => $sayfa->ID, 'post_content' => wp_slash( $icerik ) ), true );
	if ( is_wp_error( $sonuc ) ) { return $sonuc->get_error_message(); }

	gbc_ort_onbellek_bosalt();
	return true;
}

/** Bir satırı id'sinden bulur; bulamazsa -1. */
function gbc_ort_satir_bul( $satirlar, $id ) {
	$id = sanitize_key( $id );
	foreach ( $satirlar as $i => $s ) {
		if ( false === strpos( $s, '|' ) ) { continue; }
		$ilk = sanitize_key( trim( substr( $s, 0, strpos( $s, '|' ) ) ) );
		if ( $ilk === $id ) { return $i; }
	}
	return -1;
}

/** Tek satırı günceller — kimliğin bulunduğu bloğa dokunur. */
function gbc_ort_defter_guncelle( $id, $etiket, $program, $url, $ag ) {
	$id = sanitize_key( $id );
	if ( '' === $id ) { return 'Kimlik boş olamaz.'; }
	$defter = gbc_ort_satirlar();
	if ( ! isset( $defter[ $id ] ) ) { return 'Bu kimlik defterde bulunamadı: ' . $id; }

	$blok = (int) $defter[ $id ]['blok'];
	$satirlar = preg_split( '/\R/u', gbc_ort_blok_ham( $blok ) );
	$i = gbc_ort_satir_bul( $satirlar, $id );
	if ( $i < 0 ) { return 'Satır blokta bulunamadı: ' . $id; }
	$satirlar[ $i ] = gbc_ort_satir_kur( $id, $etiket, $program, $url, $ag );
	return gbc_ort_blok_yaz( $blok, implode( "\n", $satirlar ), 'güncelle: ' . $id );
}

/** Yeni satır ekler — en kalabalık bloğun sonuna. */
function gbc_ort_defter_ekle( $id, $etiket, $program, $url, $ag ) {
	$id = sanitize_key( $id );
	if ( '' === $id ) { return 'Kimlik boş olamaz.'; }
	$defter = gbc_ort_satirlar();
	if ( isset( $defter[ $id ] ) ) { return 'Bu kimlik zaten var: ' . $id; }

	$blok = gbc_ort_ekleme_blogu();
	$ham  = gbc_ort_blok_ham( $blok );
	$satirlar = ( '' === trim( $ham ) ) ? array() : preg_split( '/\R/u', $ham );
	$satirlar[] = gbc_ort_satir_kur( $id, $etiket, $program, $url, $ag );
	return gbc_ort_blok_yaz( $blok, implode( "\n", $satirlar ), 'ekle: ' . $id );
}

/** Satırı siler. */
function gbc_ort_defter_sil( $id ) {
	$id = sanitize_key( $id );
	$defter = gbc_ort_satirlar();
	if ( ! isset( $defter[ $id ] ) ) { return 'Bu kimlik defterde bulunamadı: ' . $id; }

	$blok = (int) $defter[ $id ]['blok'];
	$satirlar = preg_split( '/\R/u', gbc_ort_blok_ham( $blok ) );
	$i = gbc_ort_satir_bul( $satirlar, $id );
	if ( $i < 0 ) { return 'Satır blokta bulunamadı: ' . $id; }
	unset( $satirlar[ $i ] );
	return gbc_ort_blok_yaz( $blok, implode( "\n", array_values( $satirlar ) ), 'sil: ' . $id );
}

/** Satır metnini kurar — boru karakteri alanların içinden temizlenir. */
function gbc_ort_satir_kur( $id, $etiket, $program, $url, $ag ) {
	$t = function ( $v ) {
		$v = wp_strip_all_tags( (string) $v );
		$v = str_replace( array( '|', "\r", "\n", "\t" ), ' ', $v );
		return trim( preg_replace( '/\s+/u', ' ', $v ) );
	};
	return sanitize_key( $id ) . ' | ' . $t( $etiket ) . ' | ' . $t( $program ) . ' | ' . esc_url_raw( trim( (string) $url ) ) . ' | ' . $t( $ag );
}

/** Yedekten geri alır — sayfanın tamamı o hâle döner. */
function gbc_ort_geri_al( $sira ) {
	$y = get_option( gbc_ort_yedek_anahtar() );
	if ( ! is_array( $y ) || ! isset( $y[ (int) $sira ]['icerik'] ) ) { return 'Yedek bulunamadı.'; }
	$sayfa = gbc_ort_sayfa();
	if ( ! $sayfa ) { return 'Defter sayfası bulunamadı.'; }

	/* Geri almadan önce şu anki hâli de yedekle: geri alma da geri alınabilsin. */
	gbc_ort_yedek_al( $sayfa->post_content, 'geri alma öncesi' );

	$sonuc = wp_update_post( array( 'ID' => $sayfa->ID, 'post_content' => wp_slash( $y[ (int) $sira ]['icerik'] ) ), true );
	if ( is_wp_error( $sonuc ) ) { return $sonuc->get_error_message(); }
	gbc_ort_onbellek_bosalt();
	return true;
}

/* ============================================================
   3) AĞ, PROGRAM VE HEDEF ÇÖZÜMLEME
   ============================================================ */

/** Adresin ana alanı (www yok). */
function gbc_ort_alan( $url ) {
	$a = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $a );
}

/** Ortaklık adresinin içindeki GERÇEK hedef. tp.media/r?...&u=<hedef> */
function gbc_ort_hedef( $url ) {
	$q = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
	if ( '' === $q ) { return ''; }
	parse_str( $q, $p );
	foreach ( array( 'u', 'url', 'murl', 'deeplink' ) as $anahtar ) {
		if ( ! empty( $p[ $anahtar ] ) && 0 === stripos( $p[ $anahtar ], 'http' ) ) { return $p[ $anahtar ]; }
	}
	return '';
}

/** Bağlantının hangi ağdan gittiği. */
function gbc_ort_ag( $url ) {
	$a = gbc_ort_alan( $url );
	if ( '' === $a ) { return ''; }
	if ( 'tp.media' === $a || false !== strpos( $a, 'tpx.li' ) || false !== strpos( $a, 'travelpayouts' ) ) { return 'Travelpayouts'; }
	if ( false !== strpos( $a, 'pxf.io' ) || false !== strpos( $a, 'impact' ) ) { return 'Impact'; }
	if ( gbc_ort_cj_mi( $url ) ) { return 'CJ Affiliate'; }
	return 'Doğrudan';
}

/**
 * Bağlantının program anahtarı. Önce hedef alanına, hedef yoksa
 * ağ alanının kendisine bakar (ferryhopper.com, yesim.tpx.li gibi).
 */
function gbc_ort_prog_anahtar( $url ) {
	$hedef = gbc_ort_hedef( $url );
	if ( '' !== $hedef ) { return gbc_ort_alan( $hedef ); }
	$a = gbc_ort_alan( $url );
	if ( false !== strpos( $a, 'skyscanner' ) ) { return 'skyscanner.net'; }
	if ( 0 === strpos( $a, 'yesim.' ) ) { return 'yesim.com'; }
	return $a;
}

/**
 * Mecralar — hangi kanaldan ortaklık bağlantısı verilebilir.
 * Programlar sekmesindeki izin matrisinin sütunları.
 */
function gbc_ort_mecralar() {
	return array(
		'site'      => __( 'Web sitesi', 'gbc-core' ),
		'youtube'   => __( 'YouTube', 'gbc-core' ),
		'instagram' => __( 'Instagram', 'gbc-core' ),
		'tiktok'    => __( 'TikTok', 'gbc-core' ),
		'pinterest' => __( 'Pinterest', 'gbc-core' ),
		'facebook'  => __( 'Facebook', 'gbc-core' ),
		'eposta'    => __( 'E-posta bülteni', 'gbc-core' ),
		'whatsapp'  => __( 'WhatsApp / Telegram', 'gbc-core' ),
		'ppc'       => __( 'Marka üstüne reklam', 'gbc-core' ),
	);
}

/** İzin değerlerinin görünen karşılığı. */
function gbc_ort_izin_adi( $v ) {
	$a = array(
		'var'  => array( __( 'izinli', 'gbc-core' ), '#1A7F37', '#E8F3EC' ),
		'yok'  => array( __( 'YASAK', 'gbc-core' ), '#B3261E', '#FBE7E7' ),
		'sart' => array( __( 'şartlı', 'gbc-core' ), '#8A6100', '#FCF6E8' ),
		'?'    => array( __( 'bilinmiyor', 'gbc-core' ), '#5C6470', '#F1EFEA' ),
	);
	return isset( $a[ $v ] ) ? $a[ $v ] : $a['?'];
}

/**
 * Bilinen programların tohum tablosu.
 *
 * KOMİSYON / ÇEREZ / ONAY ve MECRA verileri 28-29 Eylül 2026'da resmi
 * kaynaklardan araştırıldı; her satırda kaynak adresi duruyor. Panelden
 * elle girilen değer bu tohumu EZER — yani panelden düzelttiğinde
 * araştırma değeri değil senin yazdığın geçerli olur.
 *
 * '?' = kaynak bulunamadı. Uydurulmuş rakam YOK.
 */
function gbc_ort_program_tohum() {
	return (array) apply_filters( 'gbc_ort_program_tohum', array(
		'booking.com' => array(
			'ad' => 'Booking.com', 'ag' => 'Travelpayouts',
			'alanlar' => array( 'booking.com' ),
			'oran' => '5', 'cerez' => 'tek oturum', 'onay' => '60-90',
			'kaynak' => 'https://www.travelpayouts.com/en/offers/bookingcom-affiliate-program/',
			'guncel' => '18.07.2025',
			'mecra' => array( 'site' => 'var', 'youtube' => 'yok', 'instagram' => 'yok', 'tiktok' => 'yok',
				'pinterest' => 'yok', 'facebook' => 'yok', 'eposta' => '?', 'whatsapp' => 'yok', 'ppc' => 'yok' ),
			'not' => 'Otel %5, uçuş 1,5 €, araç %5 (ön ödemeli) / %3. Çerez TEK OTURUM — kullanıcı o oturumda almazsa hak yok. Onay check-out’tan 60-90 gün sonra.',
			'uyari' => 'SOSYAL MEDYA YASAK. Program sayfasında açıkça yazıyor: “No Social Media unless explicitly approved by the Booking.com team.” Booking bağlantısını YouTube, Instagram, TikTok, Pinterest, Facebook ve WhatsApp gruplarında PAYLAŞMA. Sosyalde siteye yönlendir, bağlantıyı site üzerinden ver. Tarih/kişi sayısı taşıyan adres de yazılmaz.',
		),
		'getyourguide.com' => array(
			'ad' => 'GetYourGuide', 'ag' => 'Travelpayouts',
			'alanlar' => array( 'getyourguide.com' ),
			'oran' => '8', 'cerez' => '31', 'onay' => 'tur tarihi sonrası',
			'kaynak' => 'https://www.travelpayouts.com/en/offers/getyourguide-affiliate-program/',
			'guncel' => '25.06.2026',
			'mecra' => array( 'site' => 'var', 'youtube' => '?', 'instagram' => '?', 'tiktok' => '?',
				'pinterest' => '?', 'facebook' => '?', 'eposta' => '?', 'whatsapp' => '?', 'ppc' => 'yok' ),
			'not' => '%8 komisyon, 31 gün çerez, last-click. Mobil uygulamada takip YOK — kullanıcı tarayıcıdan almalı. Ödeme her ayın 5’inde tamamlanmış rezervasyonlar için.',
			'uyari' => 'Marka üstüne reklam sözleşmeyle yasak: GYG adını PPC/SEM anahtar kelimesi olarak kullanamazsın. Sosyal medya için yazılı kural yok — panelin “About” sekmesinden teyit et.',
		),
		'discovercars.com' => array(
			'ad' => 'DiscoverCars', 'ag' => 'Travelpayouts',
			'alanlar' => array( 'discovercars.com' ),
			'oran' => '60', 'cerez' => '365', 'onay' => 'araç teslimi sonrası',
			'kaynak' => 'https://www.travelpayouts.com/en/offers/discovercarhire-affiliate-program/',
			'guncel' => '09.08.2025',
			'mecra' => array( 'site' => 'var', 'youtube' => '?', 'instagram' => '?', 'tiktok' => '?',
				'pinterest' => '?', 'facebook' => '?', 'eposta' => '?', 'whatsapp' => '?', 'ppc' => '?' ),
			'not' => 'Travelpayouts üzerinden %60 (kiralama komisyonundan) + %25 (Full Coverage). Ortalama 18 $/rezervasyon. 365 gün çerez — defterdeki en uzunu.',
			'uyari' => 'DiscoverCars’ın KENDİ sayfası %70 + %30 diyor, Travelpayouts %60 + %25. Doğrudan ortaklık daha yüksek olabilir — panelden hangisinin geçerli olduğunu doğrula.',
		),
		'omio.com' => array(
			'ad' => 'Omio', 'ag' => 'Travelpayouts',
			'alanlar' => array( 'omio.com', 'omio.it' ),
			'oran' => '6', 'cerez' => '30', 'onay' => '?',
			'kaynak' => 'https://www.travelpayouts.com/en/offers/omio-affiliate-program/',
			'guncel' => '09.08.2025',
			'mecra' => array( 'site' => 'var', 'youtube' => '?', 'instagram' => '?', 'tiktok' => '?',
				'pinterest' => '?', 'facebook' => '?', 'eposta' => '?', 'whatsapp' => '?', 'ppc' => '?' ),
			'not' => '%6, 30 gün çerez. Ödeme fatura onayından 30-60 gün sonra, asgari 100 €. Tren ve otobüs.',
			'uyari' => 'Cashback ve Deutsche Bahn bilet indirimleri komisyona DAHİL DEĞİL.',
		),
		'ferryhopper.com' => array(
			'ad' => 'Ferryhopper', 'ag' => 'Doğrudan',
			'alanlar' => array( 'ferryhopper.com' ),
			'oran' => '?', 'cerez' => '?', 'onay' => '?',
			'kaynak' => 'https://partners.ferryhopper.com/affiliates',
			'guncel' => '',
			'mecra' => array( 'site' => 'var', 'youtube' => '?', 'instagram' => 'var', 'tiktok' => '?',
				'pinterest' => '?', 'facebook' => 'var', 'eposta' => 'var', 'whatsapp' => 'var', 'ppc' => '?' ),
			'not' => 'Doğrudan ortaklık, aracı ağ yok. Başvuru formunda tanıtım yöntemi olarak web sitesi, sosyal medya, e-posta bülteni ve doğrudan müşteri iletişimi sunuluyor.',
			'uyari' => 'Komisyon ve çerez KAMUYA AÇIK DEĞİL. Şartlar sadece başvuru formunda onaylanıyor, dışarıdan okunamıyor. Üçüncü taraf listeler %1-1,6 / 30 gün diyor ama bunlar ABD ağ programı olabilir, güvenme. Ferryhopper ekibine e-posta atıp yazılı teyit al.',
		),
		'skyscanner.net' => array(
			'ad' => 'Skyscanner', 'ag' => 'Impact',
			'alanlar' => array( 'skyscanner.net', 'skyscanner.com.tr' ),
			'oran' => '?', 'cerez' => '30', 'onay' => '?',
			'kaynak' => 'https://www.partners.skyscanner.net/product/affiliates',
			'guncel' => '',
			'mecra' => array( 'site' => 'sart', 'youtube' => 'sart', 'instagram' => 'sart', 'tiktok' => 'sart',
				'pinterest' => '?', 'facebook' => '?', 'eposta' => '?', 'whatsapp' => '?', 'ppc' => 'yok' ),
			'not' => 'Çerez 30 gün. Komisyon sabit değil: Skyscanner’ın tedarikçiden aldığı komisyonun bir yüzdesi, performansa göre değişken. Gerçek oran yalnız Impact sözleşme ekranında görünür.',
			'uyari' => 'İKİ AYRI PROGRAM VAR. Normal Affiliate Programme web sitesi ve ayda 5.000+ tekil ziyaretçi istiyor. Sosyal kanallar için ayrı “Creator Programme” var (1.000+ takipçi) — YouTube açıklama linki ve link-in-bio bu kapsamda uygun. Senin durumunda ikisine de başvurmak gerekiyor: site için Affiliate, kanallar için Creator. Marka üstüne reklam KESİN YASAK.',
		),
		'yesim.com' => array(
			'ad' => 'Yesim', 'ag' => 'Travelpayouts',
			'alanlar' => array( 'yesim.com' ),
			'oran' => '18', 'cerez' => '90', 'onay' => '?',
			'kaynak' => 'https://www.travelpayouts.com/en/offers/yesim-affiliate-program/',
			'guncel' => '',
			'mecra' => array( 'site' => 'var', 'youtube' => 'var', 'instagram' => 'var', 'tiktok' => 'var',
				'pinterest' => 'var', 'facebook' => 'var', 'eposta' => 'var', 'whatsapp' => 'sart', 'ppc' => 'yok' ),
			'not' => '%18 komisyon — oranı en yüksek program. 90 gün çerez. Ortalama sepet ~25 €, 150+ ülke. eSIM.',
			'uyari' => 'Travelpayouts sözleşmesi web sitesi zorunluluğu getirmiyor, sosyal medya serbest — ama o platformun KENDİ kurallarını ihlal etmemek şartıyla. İzinsiz toplu mesaj (spam) yasak, marka üstüne reklam yasak.',
		),
	) );
}

/** Kullanıcının doldurduğu program bilgileri. */
function gbc_ort_program_ayar() {
	$a = get_option( 'gbc_aff_program_ayar' );
	return is_array( $a ) ? $a : array();
}

/**
 * Program tablosu: tohum + defterden ölçülen adet + kaydedilmiş oranlar.
 *
 * @return array anahtar => array( ad, ag, alanlar, oran, cerez, onay, not, adet, tik, tik30 )
 */
function gbc_ort_programlar() {
	$tohum = gbc_ort_program_tohum();
	$ayar  = gbc_ort_program_ayar();
	$tik   = gbc_ort_tik();

	$bos = array( 'ad' => '', 'ag' => '', 'alanlar' => array(), 'not' => '', 'uyari' => '',
		'kaynak' => '', 'guncel' => '', 'mecra' => array(),
		'oran' => '', 'cerez' => '', 'onay' => '',
		'adet' => 0, 'tik' => 0, 'tik30' => 0, 'sablon' => '', 'kisa' => array() );

	$liste = array();
	foreach ( $tohum as $k => $t ) {
		$liste[ $k ] = array_merge( $bos, array( 'ad' => $k ), $t );
		/* '?' = kaynak bulunamadı; tabloda boş kutu olarak görünsün. */
		foreach ( array( 'oran', 'cerez', 'onay' ) as $alan ) {
			if ( '?' === $liste[ $k ][ $alan ] ) { $liste[ $k ][ $alan ] = ''; }
		}
	}

	foreach ( gbc_ort_satirlar() as $id => $s ) {
		$k = gbc_ort_prog_anahtar( $s['url'] );
		if ( '' === $k ) { $k = 'bilinmeyen'; }
		/* omio.it gibi ikinci alanları ana programa bağla */
		foreach ( $liste as $ana => $p ) {
			if ( in_array( $k, (array) $p['alanlar'], true ) ) { $k = $ana; break; }
		}
		if ( ! isset( $liste[ $k ] ) ) {
			$liste[ $k ] = array_merge( $bos, array( 'ad' => $k, 'ag' => gbc_ort_ag( $s['url'] ), 'alanlar' => array( $k ) ) );
		}
		$liste[ $k ]['adet']++;
		if ( isset( $tik[ $id ]['t'] ) ) { $liste[ $k ]['tik'] += (int) $tik[ $id ]['t']; }
		if ( isset( $tik[ $id ] ) && function_exists( 'gbc_aff_tik_son' ) ) {
			$s30 = gbc_aff_tik_son( $tik[ $id ], 30 );
			$liste[ $k ]['tik30'] += ( null === $s30 ? 0 : (int) $s30 );
		}
	}

	foreach ( $ayar as $k => $v ) {
		if ( ! isset( $liste[ $k ] ) ) { continue; }
		foreach ( array( 'oran', 'cerez', 'onay', 'not' ) as $alan ) {
			if ( isset( $v[ $alan ] ) && '' !== $v[ $alan ] ) { $liste[ $k ][ $alan ] = $v[ $alan ]; }
		}
	}

	$sablon = gbc_ort_sablonlar();
	$kisa   = gbc_ort_kisa_liste();
	foreach ( $liste as $k => $v ) {
		if ( isset( $sablon[ $k ] ) ) { $liste[ $k ]['sablon'] = $sablon[ $k ]; }
		$liste[ $k ]['kisa'] = isset( $kisa[ $k ] ) ? $kisa[ $k ] : array();
	}

	uasort( $liste, function ( $a, $b ) { return $b['adet'] - $a['adet']; } );
	return $liste;
}

/* ============================================================
   4) BAĞLANTI ÜRETİCİ — defterden şablon öğrenir
   ============================================================ */

/**
 * Defterdeki gerçek bağlantılardan her program için şablon çıkarır.
 * Hedefi %HEDEF%, etiketi %SUB% ile değiştirir. Böylece yeni bağlantı
 * hiçbir API'ye sormadan, var olanlarla birebir aynı biçimde üretilir.
 *
 * @return array program anahtarı => şablon
 */
function gbc_ort_sablonlar( $tazele = false ) {
	$onbellek = get_transient( 'gbc_ort_sablon' );
	if ( ! $tazele && is_array( $onbellek ) ) { return $onbellek; }

	$sablon = array();
	$sayac  = array();
	$dogru  = array();

	foreach ( gbc_ort_satirlar() as $s ) {
		$url = (string) $s['url'];
		if ( '' === $url ) { continue; }
		$k = gbc_ort_prog_anahtar( $url );
		if ( '' === $k ) { continue; }

		$hedef = gbc_ort_hedef( $url );
		if ( '' !== $hedef ) {
			/* Yönlendirmeli ağ: hedefi ve etiketi yer tutucuya çevir. */
			$t = $url;
			$t = str_replace( rawurlencode( $hedef ), '%HEDEF%', $t );
			$t = str_replace( urlencode( $hedef ), '%HEDEF%', $t );
			$t = preg_replace( '/([?&](?:sub_id|subid|subId1|p1|utm_campaign)=)[^&]*/i', '$1%SUB%', $t );
			if ( false !== strpos( $t, '%HEDEF%' ) ) {
				if ( ! isset( $sayac[ $k ] ) ) { $sayac[ $k ] = array(); }
				if ( ! isset( $sayac[ $k ][ $t ] ) ) { $sayac[ $k ][ $t ] = 0; }
				$sayac[ $k ][ $t ]++;
			}
			continue;
		}

		/* Doğrudan ortaklık: adrese eklenen sabit parametreleri topla. */
		$q = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $q ) { continue; }
		parse_str( $q, $p );
		if ( ! isset( $dogru[ $k ] ) ) { $dogru[ $k ] = array(); }
		foreach ( $p as $ad => $deg ) {
			if ( ! is_string( $deg ) || '' === $deg ) { continue; }
			$anahtar = $ad . '=' . $deg;
			if ( ! isset( $dogru[ $k ][ $anahtar ] ) ) { $dogru[ $k ][ $anahtar ] = 0; }
			$dogru[ $k ][ $anahtar ]++;
		}
	}

	foreach ( $sayac as $k => $adaylar ) {
		arsort( $adaylar );
		$sablon[ $k ] = key( $adaylar );
	}

	foreach ( $dogru as $k => $parametreler ) {
		if ( isset( $sablon[ $k ] ) ) { continue; }
		arsort( $parametreler );
		$sabit = array();
		foreach ( $parametreler as $par => $adet ) {
			if ( $adet < 2 ) { continue; }
			$sabit[] = $par;
			if ( count( $sabit ) >= 3 ) { break; }
		}
		if ( $sabit ) { $sablon[ $k ] = '%HEDEF%?' . implode( '&', $sabit ); }
	}

	set_transient( 'gbc_ort_sablon', $sablon, 12 * HOUR_IN_SECONDS );
	return $sablon;
}

/** Ortaklık ağlarının kısa bağlantı sunucuları. */
function gbc_ort_kisa_alanlar() {
	return (array) apply_filters( 'gbc_ort_kisa_alanlar', array( 'pxf.io', 'sjv.io', 'tpx.li', 'tp.st' ) );
}

/** Adres bir ağın kısa bağlantısı mı? (hedef gömülü değil) */
function gbc_ort_kisa_mi( $url ) {
	$a = gbc_ort_alan( $url );
	if ( '' === $a ) { return false; }
	if ( '' !== gbc_ort_hedef( $url ) ) { return false; }
	/* CJ'nin kendi çözücüsü var; kısa bağlantı sayılmaz. */
	if ( gbc_ort_cj_mi( $url ) ) { return false; }
	foreach ( gbc_ort_kisa_alanlar() as $k ) {
		if ( $a === $k || substr( $a, - ( strlen( $k ) + 1 ) ) === '.' . $k ) { return true; }
	}
	return false;
}

/**
 * Defterdeki kısa bağlantılar. Skyscanner (Impact) ve Yesim böyle:
 * hedef adres bağlantının içinde yok, ağ her hedef için ayrı bir kod
 * üretiyor. Bunları sıfırdan kuramayız — ama var olan bir kısa
 * bağlantıyı yeni bir etiketle (sub_id / subId1) çoğaltmak tamamen
 * otomatik yapılabilir, rapor ayrışması da böyle sağlanıyor.
 *
 * @return array program anahtarı => array( taban => array( sub, adet, ornek ) )
 */
function gbc_ort_kisa_liste( $tazele = false ) {
	if ( ! $tazele ) {
		$o = get_transient( 'gbc_ort_kisa' );
		if ( is_array( $o ) ) { return $o; }
	}
	$out = array();
	foreach ( gbc_ort_satirlar() as $id => $s ) {
		if ( ! gbc_ort_kisa_mi( $s['url'] ) ) { continue; }
		$k = gbc_ort_prog_anahtar( $s['url'] );
		$parca = wp_parse_url( $s['url'] );
		if ( empty( $parca['host'] ) ) { continue; }
		$taban = ( isset( $parca['scheme'] ) ? $parca['scheme'] : 'https' ) . '://' . $parca['host'] . ( isset( $parca['path'] ) ? $parca['path'] : '' );
		$sub = 'sub_id';
		if ( ! empty( $parca['query'] ) ) {
			parse_str( $parca['query'], $q );
			foreach ( array( 'subId1', 'subid1', 'sub_id', 'subid', 'p1' ) as $ad ) {
				if ( isset( $q[ $ad ] ) ) { $sub = $ad; break; }
			}
		}
		if ( ! isset( $out[ $k ] ) ) { $out[ $k ] = array(); }
		if ( ! isset( $out[ $k ][ $taban ] ) ) { $out[ $k ][ $taban ] = array( 'sub' => $sub, 'adet' => 0, 'ornek' => $id ); }
		$out[ $k ][ $taban ]['adet']++;
	}
	set_transient( 'gbc_ort_kisa', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/** Kısa bağlantıyı yeni bir etiketle çoğaltır. */
function gbc_ort_kisa_uret( $taban, $sub_deger, $sub_ad = '' ) {
	$taban = trim( (string) $taban );
	if ( '' === $taban ) { return ''; }
	$parca = wp_parse_url( $taban );
	if ( empty( $parca['host'] ) ) { return ''; }
	if ( '' === $sub_ad ) {
		$sub_ad = ( false !== strpos( strtolower( $parca['host'] ), 'pxf.io' ) || false !== strpos( strtolower( $parca['host'] ), 'sjv.io' ) ) ? 'subId1' : 'sub_id';
	}
	$temiz = ( isset( $parca['scheme'] ) ? $parca['scheme'] : 'https' ) . '://' . $parca['host'] . ( isset( $parca['path'] ) ? $parca['path'] : '' );
	return $temiz . '?' . rawurlencode( $sub_ad ) . '=' . rawurlencode( sanitize_key( $sub_deger ) );
}

/**
 * Hedeften ortaklık bağlantısı üretir.
 *
 * @return array array( url, ag, program, yol, not )
 */
function gbc_ort_uret( $hedef, $sub_id = '' ) {
	$hedef = trim( (string) $hedef );
	$bos = array( 'url' => '', 'ag' => '', 'program' => '', 'yol' => '', 'not' => '' );
	if ( '' === $hedef || 0 !== stripos( $hedef, 'http' ) ) {
		$bos['not'] = 'Geçerli bir adres girilmedi.';
		return $bos;
	}

	$alan = gbc_ort_alan( $hedef );
	$sub  = sanitize_key( $sub_id );

	/* 0a) CJ bağlantısı: mülk (PID) değiştirerek kanal bazlı üretilir.
	       Varsayılan mülk site; kanal bazlı üretim Üret sekmesinde. */
	if ( gbc_ort_cj_mi( $hedef ) ) {
		$mulkler = gbc_ort_mulk_dolu();
		$pid = isset( $mulkler['site']['pid'] ) ? $mulkler['site']['pid'] : '';
		$c = gbc_ort_cj_coz( $hedef );
		if ( ! $c ) {
			$bos['not'] = 'CJ bağlantısı tanındı ama çözülemedi. Adres /click-PID-AID biçiminde olmalı.';
			return $bos;
		}
		if ( '' === $pid ) { $pid = $c['pid']; }
		return array(
			'url' => gbc_ort_cj_uret( $hedef, $pid, $sub ),
			'ag' => 'CJ Affiliate',
			'program' => '' !== $c['hedef'] ? gbc_ort_alan( $c['hedef'] ) : 'CJ advertiser ' . $c['aid'],
			'yol' => 'CJ — mülk ' . $pid, 'not' => '',
		);
	}

	/* 0b) Girilen adresin kendisi bir ağ kısa bağlantısıysa: hedef gömülü
	      değildir, yapılabilecek tek şey etiketi değiştirmektir. */
	if ( gbc_ort_kisa_mi( $hedef ) ) {
		$u = gbc_ort_kisa_uret( $hedef, $sub );
		$anahtar_k = gbc_ort_prog_anahtar( $hedef );
		$progs_k = gbc_ort_programlar();
		return array(
			'url' => $u, 'ag' => gbc_ort_ag( $u ),
			'program' => isset( $progs_k[ $anahtar_k ]['ad'] ) ? $progs_k[ $anahtar_k ]['ad'] : $anahtar_k,
			'yol' => 'kısa bağlantı, yeni etiketle', 'not' => '',
		);
	}

	/* 1) Travelpayouts motoru — tanıdığı programlarda kesin yol. */
	if ( function_exists( 'gbc_aff_tp_uret' ) ) {
		$u = gbc_aff_tp_uret( $hedef, $sub );
		if ( '' !== $u ) {
			return array(
				'url' => $u, 'ag' => 'Travelpayouts',
				'program' => function_exists( 'gbc_aff_tp_program_adi' ) ? gbc_aff_tp_program_adi( $hedef ) : $alan,
				'yol' => 'Travelpayouts motoru', 'not' => '',
			);
		}
	}

	/* 2) Defterden öğrenilmiş şablon. */
	$sablon = gbc_ort_sablonlar();
	$anahtar = '';
	foreach ( gbc_ort_programlar() as $k => $p ) {
		foreach ( (array) $p['alanlar'] as $a ) {
			if ( $alan === $a || substr( $alan, - ( strlen( $a ) + 1 ) ) === '.' . $a ) { $anahtar = $k; break 2; }
		}
	}
	if ( '' === $anahtar && isset( $sablon[ $alan ] ) ) { $anahtar = $alan; }

	if ( '' !== $anahtar && ! empty( $sablon[ $anahtar ] ) ) {
		$t = $sablon[ $anahtar ];
		if ( 0 === strpos( $t, '%HEDEF%' ) ) {
			/* Doğrudan ortaklık: sabit parametreleri hedefin sonuna ekle.
			   add_query_arg kullanılmıyor; hedefte zaten var olan bir
			   parametreyi ikinci kez yazmamak için elle birleştiriliyor. */
			$ek = ltrim( substr( $t, strlen( '%HEDEF%' ) ), '?&' );
			parse_str( $ek, $par );
			$mevcut = array();
			$qs = (string) wp_parse_url( $hedef, PHP_URL_QUERY );
			if ( '' !== $qs ) { parse_str( $qs, $mevcut ); }
			$parca = array();
			foreach ( (array) $par as $ad => $deg ) {
				if ( isset( $mevcut[ $ad ] ) ) { continue; }
				$parca[] = rawurlencode( $ad ) . '=' . rawurlencode( (string) $deg );
			}
			$u = $hedef;
			if ( $parca ) { $u .= ( false === strpos( $hedef, '?' ) ? '?' : '&' ) . implode( '&', $parca ); }
		} else {
			$u = str_replace( array( '%HEDEF%', '%SUB%' ), array( rawurlencode( $hedef ), rawurlencode( $sub ) ), $t );
		}
		$progs = gbc_ort_programlar();
		return array(
			'url' => $u, 'ag' => gbc_ort_ag( $u ),
			'program' => isset( $progs[ $anahtar ]['ad'] ) ? $progs[ $anahtar ]['ad'] : $anahtar,
			'yol' => 'defterden öğrenilen şablon', 'not' => '',
		);
	}

	$kisa = gbc_ort_kisa_liste();
	if ( '' !== $anahtar && ! empty( $kisa[ $anahtar ] ) ) {
		$bos['not'] = $alan . ' kısa bağlantı ağıyla çalışıyor: hedef adres bağlantının içinde taşınmıyor, ağ her hedef için ayrı bir kod üretiyor. Bu hedef için Impact ya da Travelpayouts panelinden bir kısa bağlantı al, sonra aşağıdaki “Kısa bağlantı çoğalt” kutusundan istediğin kadar etiketli kopya üret.';
		return $bos;
	}
	$bos['not'] = $alan . ' için ortaklık programı tanımlı değil. Bu alan adı hiçbir ağda kayıtlı değilse bağlantı üretilemez; kayıtlıysa Programlar sekmesinden bir örnek bağlantı ekle, motor biçimi kendisi öğrenir.';
	return $bos;
}

/** Adreste sabit tarih / kişi sayısı var mı? */
function gbc_ort_tarih_var( $url ) {
	if ( function_exists( 'gbc_aff_panel_tarih_var' ) ) { return gbc_aff_panel_tarih_var( $url ); }
	$u = strtolower( (string) $url );
	if ( '' === $u ) { return false; }
	foreach ( array( 'checkin', 'checkout', 'departuredate', 'returndate', 'date=', 'group_adults' ) as $p ) {
		if ( false !== strpos( $u, $p ) ) { return true; }
	}
	return (bool) preg_match( '/20[2-9][0-9]-[0-1][0-9]-[0-3][0-9]/', $u );
}

/* ============================================================
   5) KULLANIM TARAMASI, TIKLAMA, GA4
   ============================================================ */

/**
 * Hangi kimlik hangi sayfada geçiyor. ~1040 yazıyı tarar, bu yüzden
 * altı saat önbelleklenir; defter değişince önbellek düşer.
 *
 * @return array id => array( sayfa => array( post_id => true ), url, kaynak, program )
 */
function gbc_ort_kullanim( $tazele = false ) {
	if ( ! $tazele ) {
		$o = get_transient( 'gbc_ort_kullanim' );
		if ( is_array( $o ) ) { return $o; }
	}

	$bulgu = function_exists( 'gbc_aff_panel_tarama' ) ? gbc_aff_panel_tarama() : array();

	$ozet = array();
	foreach ( (array) $bulgu as $k ) {
		$id = $k['id'];
		if ( ! isset( $ozet[ $id ] ) ) {
			$ozet[ $id ] = array( 'sayfa' => array(), 'url' => '', 'kaynak' => $k['kaynak'], 'program' => '' );
		}
		$ozet[ $id ]['sayfa'][ (int) $k['post'] ] = true;
		if ( '' !== $k['url'] ) { $ozet[ $id ]['url'] = $k['url']; }
		if ( '' !== $k['program'] ) { $ozet[ $id ]['program'] = $k['program']; }
	}

	set_transient( 'gbc_ort_kullanim', $ozet, 6 * HOUR_IN_SECONDS );
	return $ozet;
}

/** Sayfa bazlı döküm: post_id => array( baslik, tip, idler ) */
function gbc_ort_sayfa_dokum( $kullanim ) {
	$s = array();
	foreach ( (array) $kullanim as $id => $k ) {
		foreach ( array_keys( (array) $k['sayfa'] ) as $pid ) {
			$pid = (int) $pid;
			if ( ! isset( $s[ $pid ] ) ) {
				$s[ $pid ] = array( 'baslik' => get_the_title( $pid ), 'tip' => get_post_type( $pid ), 'idler' => array() );
			}
			$s[ $pid ]['idler'][] = $id;
		}
	}
	uasort( $s, function ( $a, $b ) { return count( $b['idler'] ) - count( $a['idler'] ); } );
	return $s;
}

/** Site içi tıklama sayacı. */
function gbc_ort_tik() {
	$t = get_option( 'gbc_aff_tik' );
	return is_array( $t ) ? $t : array();
}

/** Toplam tıklama. */
function gbc_ort_tik_toplam( $tik ) {
	$n = 0;
	foreach ( (array) $tik as $v ) { $n += isset( $v['t'] ) ? (int) $v['t'] : 0; }
	return $n;
}

/**
 * GA4 verisinin YALNIZ önbellekteki hâli — ağ isteği yapmaz.
 *
 * Ekranlar bunu kullanır: bir yönetim sayfasının açılışı hiçbir zaman
 * Google'a giden bir isteği beklemez. Veriyi tazelemek "GA4'ü yenile"
 * düğmesinin işi.
 */
function gbc_ort_ga4_onbellek() {
	$o = get_transient( 'gbc_ort_ga4' );
	if ( is_array( $o ) ) { return $o; }
	return array( 'toplam' => null, 'kirilim' => array(), 'boyut' => false, 'hata' => '', 'zaman' => 0, 'gun' => 28, 'hic' => true );
}

/**
 * GA4'ten outbound_click sayıları. Yarım günde bir tazelenir.
 *
 * @return array array( toplam, kirilim, boyut, hata, zaman )
 */
function gbc_ort_ga4( $gun = 28, $tazele = false ) {
	$bos = array( 'toplam' => null, 'kirilim' => array(), 'boyut' => false, 'hata' => '', 'zaman' => 0 );

	if ( ! $tazele ) {
		$o = get_transient( 'gbc_ort_ga4' );
		if ( is_array( $o ) ) { return $o; }
	}
	if ( ! function_exists( 'gbc_km_ga4' ) ) {
		$bos['hata'] = 'GA4 okuyucusu yüklü değil.';
		return $bos;
	}

	$aralik = array( array( 'startDate' => (int) $gun . 'daysAgo', 'endDate' => 'yesterday' ) );
	$suzgec = array( 'filter' => array( 'fieldName' => 'eventName', 'stringFilter' => array( 'value' => 'outbound_click' ) ) );

	/* 1) Toplam */
	$a = gbc_km_ga4( array(
		'dateRanges'      => $aralik,
		'dimensions'      => array( array( 'name' => 'eventName' ) ),
		'metrics'         => array( array( 'name' => 'eventCount' ) ),
		'dimensionFilter' => $suzgec,
	) );
	if ( is_wp_error( $a ) ) {
		$bos['hata'] = $a->get_error_message();
		set_transient( 'gbc_ort_ga4', $bos, HOUR_IN_SECONDS );
		return $bos;
	}
	$toplam = 0;
	foreach ( (array) ( isset( $a['rows'] ) ? $a['rows'] : array() ) as $r ) {
		$toplam += (int) $r['metricValues'][0]['value'];
	}

	/* 2) Kırılım — customEvent:aff_id özel boyutu tanımlıysa gelir. */
	$kirilim = array();
	$boyut   = false;
	$b = gbc_km_ga4( array(
		'dateRanges'      => $aralik,
		'dimensions'      => array( array( 'name' => 'customEvent:aff_id' ) ),
		'metrics'         => array( array( 'name' => 'eventCount' ) ),
		'dimensionFilter' => $suzgec,
		'limit'           => 100,
	) );
	if ( ! is_wp_error( $b ) ) {
		$boyut = true;
		foreach ( (array) ( isset( $b['rows'] ) ? $b['rows'] : array() ) as $r ) {
			$ad = (string) $r['dimensionValues'][0]['value'];
			if ( '' === $ad || '(not set)' === $ad ) { continue; }
			$kirilim[ $ad ] = (int) $r['metricValues'][0]['value'];
		}
	}

	$sonuc = array( 'toplam' => $toplam, 'kirilim' => $kirilim, 'boyut' => $boyut, 'hata' => '', 'zaman' => time(), 'gun' => (int) $gun );
	set_transient( 'gbc_ort_ga4', $sonuc, 12 * HOUR_IN_SECONDS );
	return $sonuc;
}

/* ============================================================
   6) KISA KOD ENVANTERİ
   ============================================================ */

/** Kısa kod açıklamaları — kayıtlı kodlar WordPress'ten okunur. */
function gbc_ort_kod_bilgi() {
	return (array) apply_filters( 'gbc_ort_kod_bilgi', array(
		'gbc_aff' => array(
			'nerede'  => 'yazı içi',
			'ornek'   => '[gbc_aff id=bk_atina]Atina’da otel ara[/gbc_aff]',
			'aciklama'=> 'Metni ortaklık bağlantısına çevirir, iş birliği bildirimini sayfaya ekler, tıklamayı sayar.',
		),
		'gbc_otel' => array(
			'nerede'  => 'gezi şablonu',
			'ornek'   => '[gbc_otel slot=otel_budapeste_v]',
			'aciklama'=> 'Konaklama kutusu. Bağlantıyı defterden çeker; defterde yoksa hiçbir şey basmaz, kırık bağlantı çıkmaz.',
		),
		'gbc_rota' => array(
			'nerede'  => 'rota şablonu',
			'ornek'   => '[gbc_rota]',
			'aciklama'=> 'Rota bileşeni; içindeki ulaşım ve konaklama bağlantıları deftere bağlıdır.',
		),
	) );
}

/** Sitede kayıtlı bütün GBC kısa kodları. */
function gbc_ort_kayitli_kodlar() {
	global $shortcode_tags;
	$bilgi = gbc_ort_kod_bilgi();
	$out   = array();
	foreach ( (array) $shortcode_tags as $etiket => $geri ) {
		if ( 0 !== strpos( $etiket, 'gbc_' ) ) { continue; }
		$out[ $etiket ] = array(
			'geri'     => is_string( $geri ) ? $geri : 'kapanış',
			'nerede'   => isset( $bilgi[ $etiket ]['nerede'] ) ? $bilgi[ $etiket ]['nerede'] : '—',
			'ornek'    => isset( $bilgi[ $etiket ]['ornek'] ) ? $bilgi[ $etiket ]['ornek'] : '[' . $etiket . ']',
			'aciklama' => isset( $bilgi[ $etiket ]['aciklama'] ) ? $bilgi[ $etiket ]['aciklama'] : '',
		);
	}
	ksort( $out );
	return $out;
}

/** Motorun kendiliğinden uyguladığı kurallar. */
function gbc_ort_kurallar() {
	return array(
		'Her ortaklık bağlantısı sponsored nofollow noopener taşır.',
		'Bağlantılar yeni sekmede açılır.',
		'Ortaklık bağlantısı olan sayfaya bir kez iş birliği bildirimi basılır.',
		'Defterde olmayan kimlik boş döner — kırık bağlantı çıkmaz.',
		'Sabit tarih ve kişi sayısı taşıyan adresler uyarı alır.',
		'Tıklama hem sitede sayılır hem GA4’e outbound_click olarak gider.',
	);
}

/* ============================================================
   7) EKRAN — ortak parçalar
   ============================================================ */

function gbc_ort_sekmeler() {
	return array(
		'genel'    => 'Genel Bakış',
		'baglanti' => 'Bağlantılar',
		'program'  => 'Programlar',
		'sayfa'    => 'Sayfalar',
		'uret'     => 'Üret',
		'kod'      => 'Kısa Kodlar',
	);
}

function gbc_ort_url( $sekme, $ek = array() ) {
	$a = array_merge( array( 'page' => 'gbc-ortaklik', 'sekme' => $sekme ), (array) $ek );
	return add_query_arg( $a, admin_url( 'admin.php' ) );
}

function gbc_ort_stil() {
	echo '<style id="gbc-ort">'
		. '.gbc-ort-sekme{display:flex;gap:4px;border-bottom:1px solid #E6E2DA;margin:14px 0 20px}'
		. '.gbc-ort-sekme a{padding:10px 16px;font-size:14px;font-weight:600;text-decoration:none;color:#5C6470;border-bottom:2px solid transparent}'
		. '.gbc-ort-sekme a.acik{color:#17181A;border-bottom-color:#BF360C}'
		. '.gbc-ort-kart{background:#fff;border:1px solid #E6E2DA;border-radius:14px;padding:16px 18px;margin:0 0 16px}'
		. '.gbc-ort-ozet{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}'
		. '.gbc-ort-ozet>div{flex:1 1 150px;background:#fff;border:1px solid #E6E2DA;border-radius:12px;padding:12px 14px}'
		. '.gbc-ort-ozet strong{display:block;font-size:24px;font-weight:800;line-height:1.1}'
		. '.gbc-ort-ozet span{display:block;font-size:12px;color:#5C6470;margin-top:3px}'
		. '.gbc-ort-kod{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;background:#F6F4EF;border:1px solid #E6E2DA;border-radius:8px;padding:7px 9px;display:block;word-break:break-all}'
		. '.gbc-ort-rz{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700}'
		. '.gbc-ort-tp{background:#EAF4F3;color:#0B5B55}.gbc-ort-ip{background:#EFEAF6;color:#4B3A7A}.gbc-ort-dg{background:#F1EFEA;color:#4A5058}'
		. '.gbc-ort-uy{color:#B3261E;font-weight:600}.gbc-ort-iy{color:#1A7F37;font-weight:600}.gbc-ort-sr{color:#8A6100;font-weight:600}'
		. '.gbc-ort-mini{font-size:12px;color:#5C6470}'
		. '.gbc-ort-tablo input[type=text]{width:100%;font-size:12.5px;padding:4px 7px}'
		. '.gbc-ort-tablo td{vertical-align:top}'
		. '</style>';
}

/** Bağlantı sağlığının tablo hücresi. */
function gbc_ort_saglik_hucre( $k ) {
	if ( empty( $k['durum'] ) ) {
		return '<span class="gbc-ort-mini">henüz denenmedi</span>';
	}
	$stil = array(
		'iyi'         => array( 'çalışıyor', '#1A7F37', '#E8F3EC' ),
		'engel'       => array( 'ağ engeli', '#8A6100', '#FCF6E8' ),
		'kirik'       => array( 'ÖLÜ', '#B3261E', '#FBE7E7' ),
		'ulasilamadi' => array( 'ulaşılamadı', '#8A6100', '#FCF6E8' ),
		'bos'         => array( 'adres yok', '#B3261E', '#FBE7E7' ),
		'kisa'        => array( 'kısa bağlantı', '#5C6470', '#F1EFEA' ),
	);
	$d = isset( $stil[ $k['durum'] ] ) ? $stil[ $k['durum'] ] : array( $k['durum'], '#5C6470', '#F1EFEA' );
	$h = '<span class="gbc-ort-rz" style="background:' . esc_attr( $d[2] ) . ';color:' . esc_attr( $d[1] ) . '" title="' . esc_attr( $k['not'] ) . '">' . esc_html( $d[0] ) . '</span>';
	if ( ! empty( $k['zaman'] ) ) {
		$h .= '<br><span class="gbc-ort-mini">' . esc_html( human_time_diff( (int) $k['zaman'] ) ) . ' önce</span>';
	}
	if ( 'iyi' !== $k['durum'] && ! empty( $k['son_iyi'] ) ) {
		$h .= '<br><span class="gbc-ort-mini">son çalışma: ' . esc_html( human_time_diff( (int) $k['son_iyi'] ) ) . ' önce</span>';
	}
	return $h;
}

function gbc_ort_ag_rozet( $ag ) {
	$s = 'gbc-ort-dg';
	if ( 'Travelpayouts' === $ag ) { $s = 'gbc-ort-tp'; }
	if ( 'Impact' === $ag ) { $s = 'gbc-ort-ip'; }
	if ( 'CJ Affiliate' === $ag ) { $s = 'gbc-ort-cj'; }
	if ( '' === $ag ) { return ''; }
	return '<span class="gbc-ort-rz ' . $s . '">' . esc_html( $ag ) . '</span>';
}

/* ============================================================
   8) EKRAN — işlemler
   ============================================================ */

function gbc_ort_islem() {
	$mesaj = array( '', '' ); /* metin, tip (iyi|kotu) */

	if ( isset( $_GET['gbc_ort_tazele'] ) && check_admin_referer( 'gbc_ort_tazele' ) ) {
		delete_transient( 'gbc_ort_kullanim' );
		delete_transient( 'gbc_ort_sablon' );
		delete_transient( 'gbc_ort_bos' );
		delete_transient( 'gbc_ort_kisa' );
		gbc_ort_kullanim( true );
		gbc_ort_sablonlar( true );
		return array( 'Tarama yenilendi.', 'iyi' );
	}

	if ( isset( $_GET['gbc_ort_ga4'] ) && check_admin_referer( 'gbc_ort_ga4' ) ) {
		$g = gbc_ort_ga4( 28, true );
		if ( '' !== $g['hata'] ) { return array( 'GA4: ' . $g['hata'], 'kotu' ); }
		return array( 'GA4 verisi yenilendi.', 'iyi' );
	}

	if ( ! isset( $_POST['gbc_ort_islem'] ) ) { return $mesaj; }
	check_admin_referer( 'gbc_ort' );
	$islem = sanitize_key( wp_unslash( $_POST['gbc_ort_islem'] ) );
	$al = function ( $ad ) { return isset( $_POST[ $ad ] ) ? sanitize_text_field( wp_unslash( $_POST[ $ad ] ) ) : ''; };

	if ( 'satir_kaydet' === $islem ) {
		$s = gbc_ort_defter_guncelle( $al( 'id' ), $al( 'etiket' ), $al( 'program' ), $al( 'url' ), $al( 'ag' ) );
		return ( true === $s )
			? array( '“' . $al( 'id' ) . '” güncellendi. Sitede görünmesi için LiteSpeed önbelleğini temizle.', 'iyi' )
			: array( $s, 'kotu' );
	}

	if ( 'satir_sil' === $islem ) {
		$s = gbc_ort_defter_sil( $al( 'id' ) );
		return ( true === $s ) ? array( '“' . $al( 'id' ) . '” silindi. Geri almak için Genel Bakış → Yedekler.', 'iyi' ) : array( $s, 'kotu' );
	}

	if ( 'satir_ekle' === $islem ) {
		$s = gbc_ort_defter_ekle( $al( 'id' ), $al( 'etiket' ), $al( 'program' ), $al( 'url' ), $al( 'ag' ) );
		return ( true === $s ) ? array( '“' . $al( 'id' ) . '” eklendi.', 'iyi' ) : array( $s, 'kotu' );
	}

	if ( 'uret_ekle' === $islem ) {
		$hedef = isset( $_POST['hedef'] ) ? esc_url_raw( wp_unslash( $_POST['hedef'] ) ) : '';
		$id    = sanitize_key( $al( 'id' ) );
		$u = gbc_ort_uret( $hedef, '' !== $al( 'sub' ) ? $al( 'sub' ) : $id );
		if ( '' === $u['url'] ) { return array( $u['not'], 'kotu' ); }
		$s = gbc_ort_defter_ekle( $id, $al( 'etiket' ), $u['program'], $u['url'], $u['ag'] );
		return ( true === $s )
			? array( '“' . $id . '” üretildi ve deftere eklendi. Yazıya eklemek için: [gbc_aff id=' . $id . ']' . $al( 'etiket' ) . '[/gbc_aff]', 'iyi' )
			: array( $s, 'kotu' );
	}

	if ( 'saglik_tara' === $islem ) {
		$adet = max( 10, min( 200, (int) $al( 'adet' ) ) );
		$r = gbc_ort_saglik_parti( $adet );
		return array( sprintf(
			'%d bağlantı denendi: %d çalışıyor, %d ağ engeli, %d ÖLÜ, %d ulaşılamadı. Hiç denenmemiş kalan: %d.',
			$r['denenen'], $r['iyi'], $r['engel'], $r['kirik'], $r['ulasilamadi'], $r['kalan']
		), $r['kirik'] ? 'kotu' : 'iyi' );
	}

	if ( 'kisa_ekle' === $islem ) {
		$taban = isset( $_POST['taban'] ) ? esc_url_raw( wp_unslash( $_POST['taban'] ) ) : '';
		$sub_ad = sanitize_text_field( isset( $_POST['sub_ad'] ) ? wp_unslash( $_POST['sub_ad'] ) : '' );
		$id = sanitize_key( $al( 'id' ) );
		if ( '' === $id ) { return array( 'Kimlik boş olamaz.', 'kotu' ); }
		$u = gbc_ort_kisa_uret( $taban, $id, $sub_ad );
		if ( '' === $u ) { return array( 'Kısa bağlantı üretilemedi.', 'kotu' ); }
		$prog = gbc_ort_programlar();
		$k = gbc_ort_prog_anahtar( $u );
		$s = gbc_ort_defter_ekle( $id, $al( 'etiket' ), isset( $prog[ $k ]['ad'] ) ? $prog[ $k ]['ad'] : $k, $u, gbc_ort_ag( $u ) );
		return ( true === $s )
			? array( '“' . $id . '” üretildi ve deftere eklendi: ' . $u, 'iyi' )
			: array( $s, 'kotu' );
	}

	if ( 'mulk_kaydet' === $islem ) {
		$gelen = isset( $_POST['mulk'] ) ? (array) wp_unslash( $_POST['mulk'] ) : array();
		$kayit = array();
		foreach ( $gelen as $k => $v ) {
			$k = sanitize_key( $k );
			if ( '' === $k ) { continue; }
			$kayit[ $k ] = array(
				'ad'    => sanitize_text_field( isset( $v['ad'] ) ? $v['ad'] : '' ),
				'mecra' => sanitize_key( isset( $v['mecra'] ) ? $v['mecra'] : 'site' ),
				'pid'   => preg_replace( '/[^0-9]/', '', isset( $v['pid'] ) ? $v['pid'] : '' ),
			);
		}
		update_option( 'gbc_aff_cj_mulk', $kayit, false );
		$dolu = 0;
		foreach ( $kayit as $v ) { if ( '' !== $v['pid'] ) { $dolu++; } }
		return array( sprintf( 'Mülkler kaydedildi. %d mülkün PID’i dolu.', $dolu ), 'iyi' );
	}

	if ( 'program_kaydet' === $islem ) {
		$gelen = isset( $_POST['prg'] ) ? (array) wp_unslash( $_POST['prg'] ) : array();
		$ayar = array();
		foreach ( $gelen as $k => $v ) {
			$k = sanitize_text_field( $k );
			if ( '' === $k ) { continue; }
			$ayar[ $k ] = array(
				'oran'  => sanitize_text_field( isset( $v['oran'] ) ? $v['oran'] : '' ),
				'cerez' => sanitize_text_field( isset( $v['cerez'] ) ? $v['cerez'] : '' ),
				'onay'  => sanitize_text_field( isset( $v['onay'] ) ? $v['onay'] : '' ),
				'not'   => sanitize_text_field( isset( $v['not'] ) ? $v['not'] : '' ),
			);
		}
		update_option( 'gbc_aff_program_ayar', $ayar, false );
		return array( 'Program bilgileri kaydedildi.', 'iyi' );
	}

	if ( 'salter' === $islem ) {
		$kapali = isset( $_POST['gbc_aff_kapali'] ) ? '1' : '';
		update_option( 'gbc_aff_kapali', $kapali );
		return array( $kapali
			? 'Ana şalter KAPALI: sitede ortaklık bağlantısı basılmıyor. LiteSpeed önbelleğini temizle.'
			: 'Ana şalter AÇIK: ortaklık bağlantıları basılıyor. LiteSpeed önbelleğini temizle.', 'iyi' );
	}

	if ( 'geri_al' === $islem ) {
		$s = gbc_ort_geri_al( (int) $al( 'sira' ) );
		return ( true === $s ) ? array( 'Defter yedekten geri alındı.', 'iyi' ) : array( $s, 'kotu' );
	}

	return $mesaj;
}

/* ============================================================
   9) EKRAN — yönlendirici
   ============================================================ */

function gbc_ort_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	list( $mesaj, $tip ) = gbc_ort_islem();

	$sekmeler = gbc_ort_sekmeler();
	$aktif = isset( $_GET['sekme'] ) ? sanitize_key( wp_unslash( $_GET['sekme'] ) ) : 'genel';
	if ( ! isset( $sekmeler[ $aktif ] ) ) { $aktif = 'genel'; }

	gbc_ort_stil();

	echo '<div class="wrap">';
	echo '<h1>GBC İş Ortaklığı</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-ortaklik' ); }
	echo '<p class="gbc-ort-mini" style="max-width:820px">Bütün ortaklık bağlantıları, programlar, tıklamalar ve kısa kodlar tek yerde. Defterin ham hâli burada gösterilmez; satırlar tablodan düzenlenir, her değişiklikten önce yedek alınır.</p>';

	if ( '' !== $mesaj ) {
		echo '<div class="notice notice-' . ( 'kotu' === $tip ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $mesaj ) . '</p></div>';
	}

	echo '<div class="gbc-ort-sekme">';
	foreach ( $sekmeler as $k => $ad ) {
		echo '<a class="' . ( $k === $aktif ? 'acik' : '' ) . '" href="' . esc_url( gbc_ort_url( $k ) ) . '">' . esc_html( $ad ) . '</a>';
	}
	echo '</div>';

	$geri = 'gbc_ort_sekme_' . $aktif;
	if ( function_exists( $geri ) ) { call_user_func( $geri ); }

	echo '</div>';
}

/* ---------- GENEL BAKIŞ ---------- */

function gbc_ort_sekme_genel() {
	$defter   = gbc_ort_satirlar();
	$kullanim = gbc_ort_kullanim();
	$tik      = gbc_ort_tik();
	$prog     = gbc_ort_programlar();
	$ga4      = gbc_ort_ga4_onbellek();

	$kullanilmayan = 0; $tarihli = 0; $bos = 0;
	$sablon = function_exists( 'gbc_aff_panel_sablon_idleri' ) ? gbc_aff_panel_sablon_idleri() : array();
	foreach ( $defter as $id => $b ) {
		if ( ! isset( $kullanim[ $id ] ) && ! in_array( $id, $sablon, true ) ) { $kullanilmayan++; }
		if ( gbc_ort_tarih_var( $b['url'] ) ) { $tarihli++; }
		if ( '' === $b['url'] ) { $bos++; }
	}
	$defterde_yok = 0;
	foreach ( $kullanim as $id => $k ) { if ( ! isset( $defter[ $id ] ) ) { $defterde_yok++; } }

	$site_tik = gbc_ort_tik_toplam( $tik );

	echo '<div class="gbc-ort-ozet">';
	echo '<div><strong>' . count( $defter ) . '</strong><span>kayıtlı bağlantı</span></div>';
	echo '<div><strong>' . count( $prog ) . '</strong><span>program</span></div>';
	echo '<div><strong>' . count( gbc_ort_sayfa_dokum( $kullanim ) ) . '</strong><span>bağlantı taşıyan sayfa</span></div>';
	echo '<div><strong>' . (int) $site_tik . '</strong><span>site içi tıklama (toplam)</span></div>';
	echo '<div><strong>' . ( null === $ga4['toplam'] ? '—' : (int) $ga4['toplam'] ) . '</strong><span>GA4 outbound_click (28 gün)</span></div>';
	echo '</div>';

	/* Sorunlar */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Dikkat isteyenler</h2>';
	echo '<div class="gbc-ort-ozet" style="margin:0">';
	echo '<div><strong class="' . ( $kullanilmayan ? 'gbc-ort-sr' : 'gbc-ort-iy' ) . '">' . (int) $kullanilmayan . '</strong><span>defterde var, hiçbir sayfada yok</span></div>';
	echo '<div><strong class="' . ( $defterde_yok ? 'gbc-ort-uy' : 'gbc-ort-iy' ) . '">' . (int) $defterde_yok . '</strong><span>sayfada var, defterde yok</span></div>';
	echo '<div><strong class="' . ( $bos ? 'gbc-ort-uy' : 'gbc-ort-iy' ) . '">' . (int) $bos . '</strong><span>bağlantısı boş</span></div>';
	echo '<div><strong class="' . ( $tarihli ? 'gbc-ort-uy' : 'gbc-ort-iy' ) . '">' . (int) $tarihli . '</strong><span>sabit tarih içeren</span></div>';
	echo '</div>';
	echo '<p class="gbc-ort-mini" style="margin-bottom:0">Ayrıntı için <a href="' . esc_url( gbc_ort_url( 'baglanti' ) ) . '">Bağlantılar</a> sekmesindeki süzgeçleri kullan.</p>';
	echo '</div>';

	/* Bağlantı sağlığı */
	$sg = gbc_ort_saglik_ozet();
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Bağlantılar çalışıyor mu</h2>';
	if ( $sg['toplam'] && $sg['hic'] === $sg['toplam'] ) {
		echo '<p class="gbc-ort-mini">Henüz hiç kontrol edilmedi. Aşağıdaki düğmeyle başlat; sonrası her sabah kendiliğinden döner.</p>';
	} else {
		echo '<div class="gbc-ort-ozet" style="margin:0 0 12px">';
		echo '<div><strong class="gbc-ort-iy">' . (int) $sg['iyi'] . '</strong><span>çalışıyor</span></div>';
		echo '<div><strong class="' . ( $sg['kirik'] ? 'gbc-ort-uy' : 'gbc-ort-iy' ) . '">' . (int) $sg['kirik'] . '</strong><span>ÖLÜ — acil</span></div>';
		echo '<div><strong class="gbc-ort-sr">' . (int) $sg['engel'] . '</strong><span>ağ engeli (sağlam sayılır)</span></div>';
		echo '<div><strong class="gbc-ort-sr">' . (int) $sg['ulasilamadi'] . '</strong><span>ulaşılamadı</span></div>';
		echo '<div><strong>' . (int) $sg['hic'] . '</strong><span>henüz denenmedi</span></div>';
		echo '</div>';
		if ( $sg['kirik'] ) {
			echo '<p class="gbc-ort-uy">Ölü bağlantılar: <a href="' . esc_url( gbc_ort_url( 'baglanti', array( 'suz' => 'kirik' ) ) ) . '">listeyi aç ve düzelt</a> — '
				. esc_html( implode( ', ', array_slice( $sg['kirik_idler'], 0, 8 ) ) ) . '</p>';
		} else {
			echo '<p class="gbc-ort-iy">Ölü bağlantı yok.</p>';
		}
		if ( $sg['en_eski'] ) {
			echo '<p class="gbc-ort-mini">En eski kontrol ' . esc_html( human_time_diff( (int) $sg['en_eski'] ) ) . ' önce yapıldı.</p>';
		}
	}
	echo '<p class="gbc-ort-mini">403 ve 429 ölü sayılmaz: ortaklık ağları sunucudan gelen isteği bot sanıp reddediyor, aynı bağlantı tarayıcıda açılıyor. Ölü sayılan tek şey 404 ve 410.</p>';
	echo '<form method="post" style="display:flex;gap:8px;align-items:center">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="saglik_tara">';
	echo '<label class="gbc-ort-mini">Bu turda kaç bağlantı: <input type="number" name="adet" value="40" min="10" max="200" style="width:80px"></label>';
	echo '<button class="button button-primary">Şimdi kontrol et</button>';
	echo '<span class="gbc-ort-mini">En eski denenenlerden başlar. Her sabah 06:10’da 60 tanesi kendiliğinden döner.</span>';
	echo '</form></div>';

	/* GA4 karşılaştırma */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Ölçüm — site sayacı ve GA4</h2>';
	if ( ! empty( $ga4['hic'] ) ) {
		echo '<p class="gbc-ort-mini">GA4 verisi henüz çekilmedi. Bu sayfa açılırken Google’a istek atılmıyor — veriyi aşağıdaki düğme getirir, sonuç yarım gün saklanır.</p>';
	} elseif ( '' !== $ga4['hata'] ) {
		echo '<p class="gbc-ort-uy">GA4 okunamadı: ' . esc_html( $ga4['hata'] ) . '</p>';
		echo '<p class="gbc-ort-mini">Servis hesabı ve mülk numarası <a href="' . esc_url( admin_url( 'admin.php?page=gbc-api' ) ) . '">API Merkezi</a>’nde tanımlı olmalı.</p>';
	} else {
		echo '<p>Son ' . (int) ( isset( $ga4['gun'] ) ? $ga4['gun'] : 28 ) . ' günde GA4 <strong>' . (int) $ga4['toplam'] . '</strong> outbound_click saydı. Site içi sayaç kurulduğundan beri toplam <strong>' . (int) $site_tik . '</strong> tıklama görüyor.</p>';
		if ( ! $ga4['boyut'] ) {
			echo '<p class="gbc-ort-sr">Bağlantı bazlı kırılım yok: <code>aff_id</code> GA4’te özel boyut olarak tanımlı değil. Tanımlanması Google tarafında yapılır (Yönetici → Özel tanımlar), siteden yapılamaz.</p>';
		} else {
			echo '<p class="gbc-ort-iy">Bağlantı bazlı kırılım geliyor: ' . count( $ga4['kirilim'] ) . ' kimlik.</p>';
		}
	}
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( gbc_ort_url( 'genel', array( 'gbc_ort_ga4' => 1 ) ), 'gbc_ort_ga4' ) ) . '">GA4’ü yenile</a> ';
	echo '<a class="button" href="' . esc_url( wp_nonce_url( gbc_ort_url( 'genel', array( 'gbc_ort_tazele' => 1 ) ), 'gbc_ort_tazele' ) ) . '">Sayfa taramasını yenile</a></p>';
	echo '</div>';

	/* Program şeridi */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Programlar</h2><div class="gbc-ort-ozet" style="margin:0">';
	foreach ( $prog as $k => $p ) {
		if ( ! $p['adet'] ) { continue; }
		echo '<div><strong>' . (int) $p['adet'] . '</strong><span>' . esc_html( $p['ad'] ) . ' ' . gbc_ort_ag_rozet( $p['ag'] ) . '<br>' . (int) $p['tik'] . ' tıklama</span></div>';
	}
	echo '</div></div>';

	/* Ana şalter */
	$kapali = (string) get_option( 'gbc_aff_kapali' ) === '1';
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Ana şalter</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="salter">';
	echo '<label><input type="checkbox" name="gbc_aff_kapali" value="1" ' . checked( $kapali, true, false ) . '> Bütün ortaklık bağlantılarını kapat</label> ';
	echo '<button class="button">Kaydet</button>';
	echo '<p class="gbc-ort-mini">Açıkken kısa kodlar bağlantı yerine düz metin basar. Acil durum için.</p>';
	echo '</form></div>';

	/* Yedekler */
	$yedek = get_option( gbc_ort_yedek_anahtar() );
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Yedekler</h2>';
	if ( ! is_array( $yedek ) || ! $yedek ) {
		echo '<p class="gbc-ort-mini">Henüz yedek yok. Panelden yapılan her değişiklikten önce defterin tamamı buraya kopyalanır (son 10 kopya saklanır).</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Ne zaman</th><th>Değişiklik</th><th>Satır</th><th></th></tr></thead><tbody>';
		foreach ( $yedek as $i => $y ) {
			echo '<tr><td>' . esc_html( $y['zaman'] ) . '</td><td>' . esc_html( $y['ne'] ) . '</td><td>' . (int) $y['satir'] . '</td><td>';
			echo '<form method="post" onsubmit="return confirm(\'Defter bu yedekteki hâline dönecek. Emin misin?\')">';
			wp_nonce_field( 'gbc_ort' );
			echo '<input type="hidden" name="gbc_ort_islem" value="geri_al"><input type="hidden" name="sira" value="' . (int) $i . '">';
			echo '<button class="button button-small">Bu hâle dön</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

/* ---------- BAĞLANTILAR ----------
   Not: formlar tablonun DIŞINDA duruyor, hücrelerdeki alanlar onlara
   HTML5 form="" niteliğiyle bağlanıyor. <tr> içine <form> koymak geçersiz
   HTML'dir; tarayıcı formu tablonun dışına atar ve satır çalışmaz. */

function gbc_ort_sekme_baglanti() {
	$defter   = gbc_ort_satirlar();
	$kullanim = gbc_ort_kullanim();
	$tik      = gbc_ort_tik();
	$ga4      = gbc_ort_ga4_onbellek();
	$saglik   = gbc_ort_saglik();
	$sablonid = function_exists( 'gbc_aff_panel_sablon_idleri' ) ? gbc_aff_panel_sablon_idleri() : array();

	$ara   = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';
	$suzge = isset( $_GET['suz'] ) ? sanitize_key( wp_unslash( $_GET['suz'] ) ) : '';
	$sayfa_no = max( 1, isset( $_GET['sn'] ) ? (int) $_GET['sn'] : 1 );
	$adim  = 50;

	/* Defterde olmayan ama sayfada geçen kimlikler de listelenir. */
	$hepsi = $defter;
	foreach ( $kullanim as $id => $k ) {
		if ( isset( $hepsi[ $id ] ) ) { continue; }
		$hepsi[ $id ] = array( 'etiket' => '', 'program' => $k['program'], 'url' => $k['url'], 'ag' => '', 'satir' => -1, 'yok' => true );
	}

	$secili = array();
	foreach ( $hepsi as $id => $b ) {
		$kul = isset( $kullanim[ $id ] ) ? count( $kullanim[ $id ]['sayfa'] ) : 0;
		if ( 'kullanilmayan' === $suzge && ( $kul > 0 || in_array( $id, $sablonid, true ) ) ) { continue; }
		if ( 'defterde_yok' === $suzge && empty( $b['yok'] ) ) { continue; }
		if ( 'tarihli' === $suzge && ! gbc_ort_tarih_var( $b['url'] ) ) { continue; }
		if ( 'bos' === $suzge && '' !== $b['url'] ) { continue; }
		if ( 'kirik' === $suzge && ( empty( $saglik[ $id ]['durum'] ) || 'kirik' !== $saglik[ $id ]['durum'] ) ) { continue; }
		if ( 'denenmemis' === $suzge && ! empty( $saglik[ $id ]['zaman'] ) ) { continue; }
		if ( '' !== $ara ) {
			$havuz = $id . ' ' . $b['etiket'] . ' ' . $b['program'] . ' ' . $b['url'];
			if ( false === stripos( $havuz, $ara ) ) { continue; }
		}
		$secili[ $id ] = $b;
	}

	$toplam = count( $secili );
	$sayfa_adet = max( 1, (int) ceil( $toplam / $adim ) );
	if ( $sayfa_no > $sayfa_adet ) { $sayfa_no = $sayfa_adet; }
	$goster = array_slice( $secili, ( $sayfa_no - 1 ) * $adim, $adim, true );

	/* Süzgeç şeridi */
	echo '<div class="gbc-ort-kart">';
	echo '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
	echo '<input type="hidden" name="page" value="gbc-ortaklik"><input type="hidden" name="sekme" value="baglanti">';
	echo '<input type="search" name="ara" value="' . esc_attr( $ara ) . '" placeholder="kimlik, metin, program ya da adres" style="min-width:280px">';
	echo '<select name="suz">';
	$secenek = array( '' => 'hepsi', 'kirik' => 'ÖLÜ bağlantı', 'denenmemis' => 'hiç denenmemiş',
		'kullanilmayan' => 'sayfada kullanılmayan', 'defterde_yok' => 'defterde olmayan',
		'tarihli' => 'sabit tarih içeren', 'bos' => 'adresi boş' );
	foreach ( $secenek as $k => $ad ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $suzge, $k, false ) . '>' . esc_html( $ad ) . '</option>';
	}
	echo '</select>';
	echo '<button class="button">Süz</button>';
	echo '<span class="gbc-ort-mini">' . (int) $toplam . ' kayıt</span>';
	echo '</form></div>';

	/* Satır formları — tablonun dışında. */
	foreach ( $goster as $id => $b ) {
		$f = 'gbcf-' . md5( $id );
		$d = 'gbcd-' . md5( $id );
		echo '<form id="' . esc_attr( $f ) . '" method="post">';
		wp_nonce_field( 'gbc_ort' );
		echo '<input type="hidden" name="gbc_ort_islem" value="satir_kaydet"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"></form>';
		echo '<form id="' . esc_attr( $d ) . '" method="post" onsubmit="return confirm(\'' . esc_attr( $id ) . ' defterden silinecek. Yedek alınır. Emin misin?\')">';
		wp_nonce_field( 'gbc_ort' );
		echo '<input type="hidden" name="gbc_ort_islem" value="satir_sil"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"></form>';
	}

	/* Tablo */
	echo '<table class="widefat striped gbc-ort-tablo"><thead><tr>';
	echo '<th style="width:140px">Kimlik</th><th style="width:160px">Görünen metin</th><th style="width:120px">Program</th><th>Adres</th>'
		. '<th style="width:110px">Çalışıyor mu</th><th style="width:100px">Kullanım</th><th style="width:95px">Tıklama</th><th style="width:140px"></th>';
	echo '</tr></thead><tbody>';

	if ( ! $goster ) {
		echo '<tr><td colspan="8">Bu süzgeçle kayıt yok.</td></tr>';
	}

	foreach ( $goster as $id => $b ) {
		$f    = 'gbcf-' . md5( $id );
		$d    = 'gbcd-' . md5( $id );
		$kul  = isset( $kullanim[ $id ] ) ? count( $kullanim[ $id ]['sayfa'] ) : 0;
		$t    = isset( $tik[ $id ]['t'] ) ? (int) $tik[ $id ]['t'] : 0;
		$t30  = ( isset( $tik[ $id ] ) && function_exists( 'gbc_aff_tik_son' ) ) ? gbc_aff_tik_son( $tik[ $id ], 30 ) : null;
		$g4   = isset( $ga4['kirilim'][ $id ] ) ? (int) $ga4['kirilim'][ $id ] : null;
		$ag   = '' !== $b['ag'] ? $b['ag'] : gbc_ort_ag( $b['url'] );

		echo '<tr>';
		echo '<td><code>' . esc_html( $id ) . '</code>';
		if ( ! empty( $b['yok'] ) ) { echo '<br><span class="gbc-ort-uy" style="font-size:11px">defterde yok</span>'; }
		if ( in_array( $id, $sablonid, true ) ) { echo '<br><span class="gbc-ort-mini">şablon üretir</span>'; }
		echo '</td>';

		echo '<td><input form="' . esc_attr( $f ) . '" type="text" name="etiket" value="' . esc_attr( $b['etiket'] ) . '"></td>';
		echo '<td><input form="' . esc_attr( $f ) . '" type="text" name="program" value="' . esc_attr( $b['program'] ) . '"></td>';

		echo '<td><input form="' . esc_attr( $f ) . '" type="text" name="url" value="' . esc_attr( $b['url'] ) . '">';
		echo '<input form="' . esc_attr( $f ) . '" type="hidden" name="ag" value="' . esc_attr( $ag ) . '">';
		echo '<div class="gbc-ort-mini" style="margin-top:4px">' . gbc_ort_ag_rozet( $ag );
		$hedef = gbc_ort_hedef( $b['url'] );
		if ( '' !== $hedef ) { echo ' → ' . esc_html( gbc_ort_alan( $hedef ) ); }
		if ( gbc_ort_tarih_var( $b['url'] ) ) { echo ' <span class="gbc-ort-uy">sabit tarih</span>'; }
		if ( '' !== $b['url'] ) { echo ' <a href="' . esc_url( $b['url'] ) . '" target="_blank" rel="noopener nofollow">aç</a>'; }
		echo '</div></td>';

		echo '<td>' . gbc_ort_saglik_hucre( isset( $saglik[ $id ] ) ? $saglik[ $id ] : null ) . '</td>';

		echo '<td>';
		if ( $kul > 0 ) {
			echo '<a href="' . esc_url( gbc_ort_url( 'sayfa', array( 'ara' => $id ) ) ) . '">' . (int) $kul . ' sayfa</a>';
		} elseif ( in_array( $id, $sablonid, true ) ) {
			echo '<span class="gbc-ort-mini">şablondan</span>';
		} else {
			echo '<span class="gbc-ort-sr">kullanılmıyor</span>';
		}
		echo '</td>';

		echo '<td>' . (int) $t;
		if ( null !== $t30 ) { echo '<br><span class="gbc-ort-mini">30 gün: ' . (int) $t30 . '</span>'; }
		if ( null !== $g4 ) { echo '<br><span class="gbc-ort-mini">GA4: ' . (int) $g4 . '</span>'; }
		echo '</td>';

		echo '<td><button form="' . esc_attr( $f ) . '" class="button button-small button-primary">Kaydet</button> ';
		echo '<button form="' . esc_attr( $d ) . '" class="button button-small">Sil</button></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	/* Sayfalama */
	if ( $sayfa_adet > 1 ) {
		echo '<p style="margin-top:12px">';
		for ( $i = 1; $i <= $sayfa_adet; $i++ ) {
			$bag = gbc_ort_url( 'baglanti', array( 'ara' => $ara, 'suz' => $suzge, 'sn' => $i ) );
			echo ( $i === $sayfa_no )
				? '<strong style="padding:4px 9px">' . (int) $i . '</strong>'
				: '<a class="button button-small" style="margin-right:3px" href="' . esc_url( $bag ) . '">' . (int) $i . '</a>';
		}
		echo '</p>';
	}

	/* Yeni satır */
	echo '<div class="gbc-ort-kart" style="margin-top:18px">';
	echo '<h2 style="margin-top:0">Elle bağlantı ekle</h2>';
	echo '<p class="gbc-ort-mini">Hazır ortaklık adresin varsa buraya. Sadece hedef adresin varsa <a href="' . esc_url( gbc_ort_url( 'uret' ) ) . '">Üret</a> sekmesini kullan; bağlantıyı motor kendisi kurar.</p>';
	echo '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="satir_ekle">';
	echo '<label>Kimlik<br><input type="text" name="id" required style="width:150px"></label>';
	echo '<label>Görünen metin<br><input type="text" name="etiket" style="width:200px"></label>';
	echo '<label>Program<br><input type="text" name="program" style="width:140px"></label>';
	echo '<label>Adres<br><input type="text" name="url" style="width:320px"></label>';
	echo '<label>Ağ<br><input type="text" name="ag" style="width:130px"></label>';
	echo '<button class="button button-primary">Ekle</button>';
	echo '</form></div>';
}

/* ---------- PROGRAMLAR ---------- */

function gbc_ort_sekme_program() {
	$prog   = gbc_ort_programlar();
	$mecra  = gbc_ort_mecralar();

	$eksik = 0;
	foreach ( $prog as $p ) {
		if ( ! $p['adet'] ) { continue; }
		foreach ( array( 'oran', 'cerez', 'onay' ) as $a ) { if ( '' === $p[ $a ] ) { $eksik++; } }
	}

	/* --- Uyarılar en üstte: para ve hesap riski --- */
	$uyarili = array();
	foreach ( $prog as $k => $p ) { if ( ! empty( $p['uyari'] ) ) { $uyarili[ $k ] = $p; } }
	if ( $uyarili ) {
		echo '<div class="gbc-ort-kart" style="border-color:#F0DCD1;background:#FDF6F2">';
		echo '<h2 style="margin-top:0;color:#8A3A12">Önce bunları oku</h2>';
		foreach ( $uyarili as $k => $p ) {
			$agir = ( false !== strpos( $p['uyari'], 'YASAK' ) || false !== strpos( $p['uyari'], 'KESİN' ) );
			echo '<div style="display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #F0DCD1">';
			echo '<span class="gbc-ort-rz" style="background:' . ( $agir ? '#FBE7E7' : '#FCF6E8' ) . ';color:' . ( $agir ? '#B3261E' : '#8A6100' ) . ';flex-shrink:0">' . esc_html( $p['ad'] ) . '</span>';
			echo '<span style="font-size:13.5px;line-height:1.5;color:#5C4A16">' . esc_html( $p['uyari'] ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	/* --- İzin matrisi --- */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Hangi programı nerede paylaşabilirim</h2>';
	echo '<p class="gbc-ort-mini" style="max-width:860px">Her ağın kendi kuralı var ve ihlal hâlinde kazanç ödenmiyor, hesap kapatılabiliyor. Bu tablo 28-29 Eylül 2026’da resmi kaynaklardan derlendi. “bilinmiyor” yazan kutular, programın kuralı KAMUYA AÇIK OLMADIĞI için boş — ortaklık panelinin “About” sekmesinden bakıp elle işaretleyebilirsin.</p>';

	echo '<div style="overflow-x:auto"><table class="widefat striped" style="min-width:900px"><thead><tr>';
	echo '<th style="width:150px">Program</th>';
	foreach ( $mecra as $ad ) { echo '<th style="text-align:center;font-size:12px">' . esc_html( $ad ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $prog as $k => $p ) {
		/* Kuralı bilinen her program burada durur — henüz hiç bağlantı
		   vermediğin bir program için de "nerede paylaşabilirim" sorusunun
		   cevabı lazım. Defterden keşfedilmiş ama kuralı bilinmeyen
		   programlar (mecra verisi yok) listeye girmez. */
		if ( empty( $p['mecra'] ) ) { continue; }
		echo '<tr><td><strong>' . esc_html( $p['ad'] ) . '</strong><br>' . gbc_ort_ag_rozet( $p['ag'] );
		echo $p['adet'] ? '' : '<br><span class="gbc-ort-mini">henüz kullanılmıyor</span>';
		echo '</td>';
		foreach ( array_keys( $mecra ) as $mk ) {
			$v = isset( $p['mecra'][ $mk ] ) ? $p['mecra'][ $mk ] : '?';
			list( $ad, $renk, $zemin ) = gbc_ort_izin_adi( $v );
			echo '<td style="text-align:center"><span class="gbc-ort-rz" style="background:' . esc_attr( $zemin ) . ';color:' . esc_attr( $renk ) . '">' . esc_html( $ad ) . '</span></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
	echo '<p class="gbc-ort-mini" style="margin-bottom:0"><strong>şartlı</strong> = izinli ama bir koşulu var, o programın uyarısına bak. <strong>Marka üstüne reklam</strong> = Google’da programın kendi adına reklam vermek; neredeyse hepsinde yasak.</p>';
	echo '</div>';

	/* --- Komisyon tablosu --- */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Komisyon, çerez, onay</h2>';
	echo '<p class="gbc-ort-mini">Rakamlar araştırmadan geldi, kaynağı her satırda. Panelden düzelttiğinde seninki geçerli olur. Boş kutu = kaynak bulunamadı, uydurulmadı.</p>';
	if ( $eksik ) {
		echo '<p class="gbc-ort-sr">' . (int) $eksik . ' alan hâlâ boş — bunlar yalnız ortaklık panellerinden okunabiliyor.</p>';
	}
	echo '</div>';

	echo '<form method="post">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="program_kaydet">';
	echo '<table class="widefat striped gbc-ort-tablo"><thead><tr>';
	echo '<th style="width:140px">Program</th><th style="width:60px">Bağlantı</th><th style="width:85px">Tıklama</th>';
	echo '<th style="width:95px">Komisyon %</th><th style="width:100px">Çerez</th><th style="width:130px">Onay süresi</th><th>Not · kaynak</th>';
	echo '</tr></thead><tbody>';

	foreach ( $prog as $k => $p ) {
		$n = 'prg[' . esc_attr( $k ) . ']';
		echo '<tr>';
		echo '<td><strong>' . esc_html( $p['ad'] ) . '</strong><br>' . gbc_ort_ag_rozet( $p['ag'] ) . '<br><span class="gbc-ort-mini">' . esc_html( implode( ', ', (array) $p['alanlar'] ) ) . '</span></td>';
		echo '<td>' . (int) $p['adet'] . '</td>';
		echo '<td>' . (int) $p['tik'] . '<br><span class="gbc-ort-mini">30g: ' . (int) $p['tik30'] . '</span></td>';
		echo '<td><input type="text" name="' . $n . '[oran]" value="' . esc_attr( $p['oran'] ) . '" placeholder="—"></td>';
		echo '<td><input type="text" name="' . $n . '[cerez]" value="' . esc_attr( $p['cerez'] ) . '" placeholder="gün"></td>';
		echo '<td><input type="text" name="' . $n . '[onay]" value="' . esc_attr( $p['onay'] ) . '" placeholder="gün"></td>';
		echo '<td><input type="text" name="' . $n . '[not]" value="' . esc_attr( $p['not'] ) . '">';
		echo '<div class="gbc-ort-mini" style="margin-top:4px">';
		if ( $p['kaynak'] ) {
			echo '<a href="' . esc_url( $p['kaynak'] ) . '" target="_blank" rel="noopener">kaynak</a>';
			if ( $p['guncel'] ) { echo ' <span>· sayfa ' . esc_html( $p['guncel'] ) . ' güncellemesi</span>'; }
		}
		echo ( '' !== $p['sablon'] )
			? ' · <span class="gbc-ort-iy">bağlantı üretilebiliyor</span>'
			: ( ! empty( $p['kisa'] )
				? ' · <span class="gbc-ort-sr">kısa bağlantı ağı</span> — ' . count( $p['kisa'] ) . ' hazır bağlantı'
				: ' · <span class="gbc-ort-sr">bağlantı üretilemiyor</span>' );
		echo '</div></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p><button class="button button-primary">Kaydet</button></p>';
	echo '</form>';

	/* --- Karar tablosu --- */
	$dolu = array();
	foreach ( $prog as $k => $p ) {
		$o = (float) str_replace( array( '%', ',' ), array( '', '.' ), $p['oran'] );
		if ( $o > 0 && $p['adet'] ) {
			$dolu[ $k ] = array( 'ad' => $p['ad'], 'oran' => $o, 'cerez' => $p['cerez'], 'ag' => $p['ag'], 'alanlar' => $p['alanlar'] );
		}
	}
	echo '<div class="gbc-ort-kart" style="margin-top:18px">';
	echo '<h2 style="margin-top:0">Aynı hedef iki ağda: hangisi?</h2>';
	if ( count( $dolu ) < 2 ) {
		echo '<p class="gbc-ort-mini">Karşılaştırma için en az iki programın komisyon oranı gerekiyor.</p>';
	} else {
		uasort( $dolu, function ( $a, $b ) { return ( $b['oran'] <=> $a['oran'] ); } );
		echo '<table class="widefat striped"><thead><tr><th style="width:50px">Sıra</th><th>Program</th><th>Ağ</th><th>Komisyon</th><th>Çerez</th><th>Kapsadığı alanlar</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $dolu as $k => $v ) {
			$i++;
			echo '<tr><td>' . (int) $i . '</td><td>' . esc_html( $v['ad'] ) . '</td><td>' . gbc_ort_ag_rozet( $v['ag'] ) . '</td>';
			echo '<td><strong>' . esc_html( rtrim( rtrim( number_format( $v['oran'], 2, ',', '.' ), '0' ), ',' ) ) . '%</strong></td>';
			echo '<td>' . esc_html( $v['cerez'] ? $v['cerez'] . ( is_numeric( $v['cerez'] ) ? ' gün' : '' ) : '—' ) . '</td>';
			echo '<td><span class="gbc-ort-mini">' . esc_html( implode( ', ', (array) $v['alanlar'] ) ) . '</span></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="gbc-ort-mini">Oranlar farklı şeylerin yüzdesi: DiscoverCars’ın %60’ı <em>kiralama komisyonunun</em> payı, Booking’in %5’i <em>konaklama bedelinin</em>. Doğrudan kıyaslama değil; asıl ölçü rezervasyon başına kazanç. Çerez eşitlikte belirleyici olur.</p>';
	}
	echo '</div>';
}

/* ---------- SAYFALAR ---------- */

function gbc_ort_sekme_sayfa() {
	$kullanim = gbc_ort_kullanim();
	$dokum    = gbc_ort_sayfa_dokum( $kullanim );
	$tik      = gbc_ort_tik();
	$ara      = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';

	echo '<div class="gbc-ort-kart">';
	echo '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
	echo '<input type="hidden" name="page" value="gbc-ortaklik"><input type="hidden" name="sekme" value="sayfa">';
	echo '<input type="search" name="ara" value="' . esc_attr( $ara ) . '" placeholder="sayfa başlığı ya da bağlantı kimliği" style="min-width:300px">';
	echo '<button class="button">Süz</button>';
	echo '<span class="gbc-ort-mini">' . count( $dokum ) . ' sayfada ortaklık bağlantısı var</span>';
	echo '<a class="button" style="margin-left:auto" href="' . esc_url( wp_nonce_url( gbc_ort_url( 'sayfa', array( 'gbc_ort_tazele' => 1 ) ), 'gbc_ort_tazele' ) ) . '">Taramayı yenile</a>';
	echo '</form></div>';

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Sayfa</th><th style="width:80px">Tip</th><th style="width:70px">Bağlantı</th><th style="width:90px">Tıklama</th><th>Kimlikler</th>';
	echo '</tr></thead><tbody>';

	$gorunen = 0;
	foreach ( $dokum as $pid => $s ) {
		if ( '' !== $ara ) {
			$havuz = $s['baslik'] . ' ' . implode( ' ', $s['idler'] );
			if ( false === stripos( $havuz, $ara ) ) { continue; }
		}
		$gorunen++;
		$t = 0;
		foreach ( $s['idler'] as $id ) {
			if ( isset( $tik[ $id ]['s'][ $pid ] ) ) { $t += (int) $tik[ $id ]['s'][ $pid ]; }
		}
		$baslik = '' !== $s['baslik'] ? $s['baslik'] : '#' . (int) $pid;
		echo '<tr>';
		echo '<td><a href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html( $baslik ) . '</a> ';
		echo '<a class="gbc-ort-mini" href="' . esc_url( (string) get_permalink( $pid ) ) . '" target="_blank" rel="noopener">↗</a></td>';
		echo '<td><span class="gbc-ort-mini">' . esc_html( $s['tip'] ) . '</span></td>';
		echo '<td>' . count( $s['idler'] ) . '</td>';
		echo '<td>' . (int) $t . '</td>';
		echo '<td><span class="gbc-ort-mini">';
		$kisa = array_slice( $s['idler'], 0, 12 );
		foreach ( $kisa as $id ) {
			echo '<a href="' . esc_url( gbc_ort_url( 'baglanti', array( 'ara' => $id ) ) ) . '"><code>' . esc_html( $id ) . '</code></a> ';
		}
		if ( count( $s['idler'] ) > 12 ) { echo '+' . ( count( $s['idler'] ) - 12 ); }
		echo '</span></td></tr>';
	}
	if ( ! $gorunen ) { echo '<tr><td colspan="5">Kayıt yok.</td></tr>'; }
	echo '</tbody></table>';

	/* Ortaklığı hiç olmayan gezi rehberleri */
	$bos = gbc_ort_bos_sayfalar( $dokum );
	echo '<div class="gbc-ort-kart" style="margin-top:18px">';
	echo '<h2 style="margin-top:0">Ortaklık bağlantısı olmayan gezi yazıları</h2>';
	if ( ! $bos ) {
		echo '<p class="gbc-ort-iy">Yok — bütün gezi yazılarında en az bir bağlantı var.</p>';
	} else {
		echo '<p class="gbc-ort-mini">' . count( $bos ) . ' yazıda hiç ortaklık bağlantısı yok. Kazanç bırakılan yerler burası.</p>';
		echo '<p>';
		foreach ( array_slice( $bos, 0, 60 ) as $pid => $baslik ) {
			echo '<a class="button button-small" style="margin:0 4px 5px 0" href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html( $baslik ) . '</a>';
		}
		if ( count( $bos ) > 60 ) { echo '<span class="gbc-ort-mini">… ve ' . ( count( $bos ) - 60 ) . ' tane daha</span>'; }
		echo '</p>';
	}
	echo '</div>';
}

/** Başlığında gezi rehberi geçen, ortaklık bağlantısı olmayan yayınlar. */
function gbc_ort_bos_sayfalar( $dokum ) {
	$o = get_transient( 'gbc_ort_bos' );
	if ( is_array( $o ) ) { return $o; }

	global $wpdb;
	$satir = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type IN ('post','page')
		   AND ( post_title LIKE '%Gezi Rehberi%' OR post_title LIKE '%Gezilecek%' )
		 LIMIT 400"
	);
	$bos = array();
	foreach ( (array) $satir as $r ) {
		if ( isset( $dokum[ (int) $r->ID ] ) ) { continue; }
		$bos[ (int) $r->ID ] = $r->post_title;
	}
	set_transient( 'gbc_ort_bos', $bos, 6 * HOUR_IN_SECONDS );
	return $bos;
}

/* ---------- ÜRET ---------- */

function gbc_ort_sekme_uret() {
	$prog = gbc_ort_programlar();

	$destek = array();
	foreach ( $prog as $k => $p ) {
		if ( '' === $p['sablon'] && empty( $p['kisa'] ) && ! gbc_ort_tp_tanir( $k, $p ) ) { continue; }
		foreach ( (array) $p['alanlar'] as $a ) { $destek[ $a ] = $p['ad']; }
	}

	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Bağlantı üret</h2>';
	echo '<p class="gbc-ort-mini" style="max-width:840px">Hedef adresi yapıştır, gerisini motor yapar: hangi programa düştüğünü bulur, ortaklık bağlantısını kurar, kısa kodu yazar. Hiçbir ağa istek gitmez — biçim ya Travelpayouts motorundan ya da defterdeki gerçek bağlantılardan öğrenilmiştir.</p>';
	echo '<p class="gbc-ort-mini">Tanınan alan adları: ';
	foreach ( $destek as $a => $ad ) { echo '<code style="margin-right:5px">' . esc_html( $a ) . '</code>'; }
	if ( ! $destek ) { echo 'yok'; }
	echo '</p>';

	echo '<form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="uret_onizle">';
	echo '<label style="flex:1 1 420px">Hedef adres<br><input type="url" name="hedef" required style="width:100%" placeholder="https://www.booking.com/city/gr/athens.tr.html" value="' . esc_attr( isset( $_POST['hedef'] ) ? sanitize_text_field( wp_unslash( $_POST['hedef'] ) ) : '' ) . '"></label>';
	echo '<label>Kimlik<br><input type="text" name="id" style="width:160px" placeholder="bk_atina" value="' . esc_attr( isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '' ) . '"></label>';
	echo '<label>Görünen metin<br><input type="text" name="etiket" style="width:230px" placeholder="Atina’da otel ara" value="' . esc_attr( isset( $_POST['etiket'] ) ? sanitize_text_field( wp_unslash( $_POST['etiket'] ) ) : '' ) . '"></label>';
	echo '<button class="button button-primary">Üret</button>';
	echo '</form></div>';

	$islem = isset( $_POST['gbc_ort_islem'] ) ? sanitize_key( wp_unslash( $_POST['gbc_ort_islem'] ) ) : '';

	if ( 'uret_onizle' === $islem && check_admin_referer( 'gbc_ort' ) ) {
		$hedef  = isset( $_POST['hedef'] ) ? esc_url_raw( wp_unslash( $_POST['hedef'] ) ) : '';
		$id     = sanitize_key( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '' );
		$etiket = sanitize_text_field( isset( $_POST['etiket'] ) ? wp_unslash( $_POST['etiket'] ) : '' );
		if ( '' === $id ) { $id = gbc_ort_id_oner( $hedef ); }
		$u = gbc_ort_uret( $hedef, $id );

		echo '<div class="gbc-ort-kart">';
		if ( '' === $u['url'] ) {
			echo '<p class="gbc-ort-uy">' . esc_html( $u['not'] ) . '</p>';
		} else {
			echo '<h2 style="margin-top:0">Sonuç</h2>';
			echo '<p><strong>' . esc_html( $u['program'] ) . '</strong> ' . gbc_ort_ag_rozet( $u['ag'] ) . ' <span class="gbc-ort-mini">· ' . esc_html( $u['yol'] ) . '</span></p>';
			echo '<p class="gbc-ort-mini" style="margin-bottom:4px">Ortaklık bağlantısı</p><code class="gbc-ort-kod">' . esc_html( $u['url'] ) . '</code>';
			echo '<p class="gbc-ort-mini" style="margin:10px 0 4px">Yazıya eklenecek kısa kod</p>';
			echo '<code class="gbc-ort-kod">[gbc_aff id=' . esc_html( $id ) . ']' . esc_html( '' !== $etiket ? $etiket : 'bağlantı metni' ) . '[/gbc_aff]</code>';
			if ( gbc_ort_tarih_var( $hedef ) ) {
				echo '<p class="gbc-ort-uy" style="margin-top:10px">Bu adres sabit tarih ya da kişi sayısı taşıyor. Birkaç ay sonra anlamsız bir arama sonucu açar; şehir ya da arama sayfası daha iyi.</p>';
			}
			$defter = gbc_ort_satirlar();
			if ( isset( $defter[ $id ] ) ) {
				echo '<p class="gbc-ort-sr" style="margin-top:10px">“' . esc_html( $id ) . '” defterde zaten var. Başka bir kimlik seç ya da <a href="' . esc_url( gbc_ort_url( 'baglanti', array( 'ara' => $id ) ) ) . '">mevcut satırı düzenle</a>.</p>';
			} else {
				echo '<form method="post" style="margin-top:12px">';
				wp_nonce_field( 'gbc_ort' );
				echo '<input type="hidden" name="gbc_ort_islem" value="uret_ekle">';
				echo '<input type="hidden" name="hedef" value="' . esc_attr( $hedef ) . '">';
				echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
				echo '<input type="hidden" name="etiket" value="' . esc_attr( $etiket ) . '">';
				echo '<input type="hidden" name="sub" value="' . esc_attr( $id ) . '">';
				echo '<button class="button button-primary">Deftere ekle</button>';
				echo '</form>';
			}
		}
		echo '</div>';
	}

	/* Toplu üretim */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Toplu üret</h2>';
	echo '<p class="gbc-ort-mini">Her satıra bir hedef adres. İstersen başına kimlik ve görünen metin de yazabilirsin:<br><code>bk_atina | Atina’da otel ara | https://www.booking.com/city/gr/athens.tr.html</code></p>';
	echo '<form method="post">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="toplu_onizle">';
	echo '<textarea name="toplu" rows="6" style="width:100%;font-family:ui-monospace,Menlo,monospace;font-size:12px">' . esc_textarea( isset( $_POST['toplu'] ) ? wp_unslash( $_POST['toplu'] ) : '' ) . '</textarea>';
	echo '<p><button class="button button-primary">Üret ve göster</button></p>';
	echo '</form>';

	if ( in_array( $islem, array( 'toplu_onizle', 'toplu_ekle' ), true ) && check_admin_referer( 'gbc_ort' ) ) {
		$ham  = isset( $_POST['toplu'] ) ? wp_unslash( $_POST['toplu'] ) : '';
		$yaz  = ( 'toplu_ekle' === $islem );
		$liste = gbc_ort_toplu_coz( $ham );
		$defter = gbc_ort_satirlar();

		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th style="width:140px">Kimlik</th><th style="width:170px">Metin</th><th>Bağlantı</th><th style="width:140px">Durum</th></tr></thead><tbody>';
		$eklendi = 0; $atlandi = 0;
		foreach ( $liste as $sira => $s ) {
			$u = gbc_ort_uret( $s['url'], $s['id'] );
			$durum = '';
			if ( '' === $u['url'] ) {
				$durum = '<span class="gbc-ort-uy">üretilemedi</span>';
			} elseif ( isset( $defter[ $s['id'] ] ) ) {
				$durum = '<span class="gbc-ort-sr">kimlik zaten var</span>';
			} elseif ( $yaz ) {
				$s2 = gbc_ort_defter_ekle( $s['id'], $s['etiket'], $u['program'], $u['url'], $u['ag'] );
				if ( true === $s2 ) { $durum = '<span class="gbc-ort-iy">eklendi</span>'; $eklendi++; $defter[ $s['id'] ] = true; }
				else { $durum = '<span class="gbc-ort-uy">' . esc_html( $s2 ) . '</span>'; $atlandi++; }
			} else {
				$durum = '<span class="gbc-ort-mini">eklenmeye hazır</span>';
			}
			echo '<tr><td><code>' . esc_html( $s['id'] ) . '</code></td><td>' . esc_html( $s['etiket'] ) . '</td>';
			echo '<td>' . ( '' !== $u['url'] ? '<code class="gbc-ort-kod">' . esc_html( $u['url'] ) . '</code>' : '<span class="gbc-ort-mini">' . esc_html( $u['not'] ) . '</span>' ) . '</td>';
			echo '<td>' . $durum . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( $yaz ) {
			echo '<p class="gbc-ort-iy">' . (int) $eklendi . ' bağlantı deftere eklendi.</p>';
		} elseif ( $liste ) {
			echo '<form method="post" style="margin-top:10px">';
			wp_nonce_field( 'gbc_ort' );
			echo '<input type="hidden" name="gbc_ort_islem" value="toplu_ekle">';
			echo '<input type="hidden" name="toplu" value="' . esc_attr( $ham ) . '">';
			echo '<button class="button button-primary">Hepsini deftere ekle</button>';
			echo '</form>';
		}
	}
	echo '</div>';

	gbc_ort_kisa_kutu();
	gbc_ort_cj_kutu();
}

/* ---------- CJ: MÜLKLER VE KANAL BAZLI ÜRETİM ---------- */

function gbc_ort_cj_kutu() {
	$mulkler = gbc_ort_mulkler();
	$dolu    = gbc_ort_mulk_dolu();
	$mecra   = gbc_ort_mecralar();

	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">CJ mülkleri (PID)</h2>';
	echo '<p class="gbc-ort-mini" style="max-width:860px">CJ’de her kanalın ayrı bir <strong>Promotional Property ID</strong>’si var. Aynı hedefe farklı PID ile bağlantı verirsen CJ raporunda <strong>hangi kanalın kazandırdığını ayrı ayrı</strong> görürsün — sitede mi, YouTube’da mı, Instagram’da mı sattığın belli olur. PID’leri CJ’de <em>Account → Promotional Properties</em> sayfasından kopyala.</p>';

	if ( ! $dolu ) {
		echo '<p class="gbc-ort-sr">Henüz hiç PID girilmedi. En az site PID’ini gir, kanal bazlı üretim o zaman açılır.</p>';
	} else {
		echo '<p class="gbc-ort-iy">' . count( $dolu ) . ' mülkün PID’i dolu.</p>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="mulk_kaydet">';
	echo '<table class="widefat striped gbc-ort-tablo"><thead><tr>';
	echo '<th style="width:240px">Mülk</th><th style="width:150px">Mecra</th><th style="width:150px">PID</th><th>Bu mülkten hangi programı verebilirsin</th>';
	echo '</tr></thead><tbody>';

	$prog = gbc_ort_programlar();
	foreach ( $mulkler as $k => $m ) {
		$n = 'mulk[' . esc_attr( $k ) . ']';
		echo '<tr>';
		echo '<td><input type="text" name="' . $n . '[ad]" value="' . esc_attr( $m['ad'] ) . '"></td>';
		echo '<td><select name="' . $n . '[mecra]">';
		foreach ( $mecra as $mk => $mad ) {
			if ( 'ppc' === $mk ) { continue; }
			echo '<option value="' . esc_attr( $mk ) . '" ' . selected( $m['mecra'], $mk, false ) . '>' . esc_html( $mad ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="text" name="' . $n . '[pid]" value="' . esc_attr( $m['pid'] ) . '" placeholder="100370222"></td>';
		echo '<td><span class="gbc-ort-mini">';
		$izinli = array(); $yasak = array();
		foreach ( $prog as $pk => $p ) {
			if ( empty( $p['mecra'] ) ) { continue; }
			$iz = isset( $p['mecra'][ $m['mecra'] ] ) ? $p['mecra'][ $m['mecra'] ] : '?';
			if ( 'var' === $iz ) { $izinli[] = $p['ad']; }
			if ( 'yok' === $iz ) { $yasak[] = $p['ad']; }
		}
		if ( $izinli ) { echo '<span class="gbc-ort-iy">' . esc_html( implode( ', ', $izinli ) ) . '</span>'; }
		if ( $yasak ) { echo ( $izinli ? '<br>' : '' ) . '<span class="gbc-ort-uy">yasak: ' . esc_html( implode( ', ', $yasak ) ) . '</span>'; }
		if ( ! $izinli && ! $yasak ) { echo 'kural bilinmiyor'; }
		echo '</span></td></tr>';
	}
	echo '</tbody></table>';
	echo '<p><button class="button button-primary">Mülkleri kaydet</button></p>';
	echo '</form>';
	echo '</div>';

	/* --- Kanal bazlı üretim --- */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Bir CJ bağlantısını bütün kanallar için çoğalt</h2>';
	echo '<p class="gbc-ort-mini" style="max-width:860px">CJ’den aldığın bağlantıyı buraya yapıştır. Motor hedefi ve advertiser kimliğini (AID) aynen korur, yalnız PID’i değiştirir — her kanal için ayrı bağlantı çıkar. Hangi kanalda kullanman <strong>yasak</strong> olduğunu da söyler.</p>';

	echo '<form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">';
	wp_nonce_field( 'gbc_ort' );
	echo '<input type="hidden" name="gbc_ort_islem" value="cj_cogalt">';
	echo '<label style="flex:1 1 460px">CJ bağlantısı<br><input type="text" name="cj_url" style="width:100%" placeholder="https://www.jdoqocy.com/click-9265128-10463300?url=..." value="' . esc_attr( isset( $_POST['cj_url'] ) ? sanitize_text_field( wp_unslash( $_POST['cj_url'] ) ) : '' ) . '"></label>';
	echo '<label>Etiket (SID)<br><input type="text" name="cj_sid" style="width:170px" placeholder="atina_rehber" value="' . esc_attr( isset( $_POST['cj_sid'] ) ? sanitize_text_field( wp_unslash( $_POST['cj_sid'] ) ) : '' ) . '"></label>';
	echo '<button class="button button-primary">Çoğalt</button>';
	echo '</form>';

	$islem = isset( $_POST['gbc_ort_islem'] ) ? sanitize_key( wp_unslash( $_POST['gbc_ort_islem'] ) ) : '';
	if ( 'cj_cogalt' === $islem && check_admin_referer( 'gbc_ort' ) ) {
		$url = isset( $_POST['cj_url'] ) ? esc_url_raw( wp_unslash( $_POST['cj_url'] ) ) : '';
		$sid = sanitize_text_field( isset( $_POST['cj_sid'] ) ? wp_unslash( $_POST['cj_sid'] ) : '' );
		$c   = gbc_ort_cj_coz( $url );

		if ( ! $c ) {
			echo '<p class="gbc-ort-uy">Bu adres CJ bağlantısı olarak çözülemedi. CJ bağlantısı <code>/click-PID-AID</code> biçimindedir ve alan adı ' . esc_html( implode( ', ', array_slice( gbc_ort_cj_alanlar(), 0, 4 ) ) ) . ' gibi olur.</p>';
		} else {
			$hedef_alan = '' !== $c['hedef'] ? gbc_ort_alan( $c['hedef'] ) : '';
			$prog_anahtar = '';
			foreach ( gbc_ort_programlar() as $pk => $p ) {
				foreach ( (array) $p['alanlar'] as $a ) {
					if ( $hedef_alan === $a || ( $hedef_alan && substr( $hedef_alan, - ( strlen( $a ) + 1 ) ) === '.' . $a ) ) { $prog_anahtar = $pk; break 2; }
				}
			}

			echo '<div class="gbc-ort-mini" style="margin:10px 0">Çözüldü — PID <code>' . esc_html( $c['pid'] ) . '</code> · AID <code>' . esc_html( $c['aid'] ) . '</code>';
			if ( '' !== $c['hedef'] ) { echo ' · hedef <code>' . esc_html( $hedef_alan ) . '</code>'; }
			echo '</div>';

			if ( ! $dolu ) {
				echo '<p class="gbc-ort-sr">Önce yukarıdan en az bir mülkün PID’ini gir.</p>';
			} else {
				echo '<table class="widefat striped"><thead><tr><th style="width:230px">Kanal</th><th style="width:110px">Bu programda</th><th>Bağlantı</th></tr></thead><tbody>';
				foreach ( $dolu as $mk => $m ) {
					$iz = $prog_anahtar ? gbc_ort_mulk_izin( $m['mecra'], $prog_anahtar ) : '?';
					list( $iz_ad, $iz_renk, $iz_zemin ) = gbc_ort_izin_adi( $iz );
					$u = gbc_ort_cj_uret( $url, $m['pid'], '' !== $sid ? $sid : $mk );
					echo '<tr><td><strong>' . esc_html( $m['ad'] ) . '</strong><br><span class="gbc-ort-mini">PID ' . esc_html( $m['pid'] ) . '</span></td>';
					echo '<td><span class="gbc-ort-rz" style="background:' . esc_attr( $iz_zemin ) . ';color:' . esc_attr( $iz_renk ) . '">' . esc_html( $iz_ad ) . '</span></td>';
					echo '<td>';
					if ( 'yok' === $iz ) {
						echo '<span class="gbc-ort-uy">Bu kanalda paylaşman yasak — bağlantı üretilmedi.</span>';
					} else {
						echo '<code class="gbc-ort-kod">' . esc_html( $u ) . '</code>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table>';
				echo '<p class="gbc-ort-mini">Etiket (SID) boş bırakılırsa kanal anahtarı kullanılır. CJ raporunda <em>Reports → Performance</em> altında PID ve SID ayrı sütun olarak görünür.</p>';
			}
		}
	}
	echo '</div>';

	/* --- Ödeme takvimi --- */
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">CJ ödeme takvimi</h2>';
	echo '<p class="gbc-ort-mini">Bir satışın paraya dönmesi dört durumdan geçiyor. Kaynak: CJ New Publisher Welcome Kit 2026.</p>';
	echo '<table class="widefat striped"><thead><tr><th style="width:130px">Durum</th><th>Ne demek</th></tr></thead><tbody>';
	foreach ( gbc_ort_cj_odeme() as $d ) {
		echo '<tr><td><strong>' . esc_html( $d[0] ) . '</strong></td><td>' . esc_html( $d[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="gbc-ort-mini">Türkiye’den ödeme: CJ doğrudan banka havalesi 39 ülkede var; listede yoksan <strong>Payoneer</strong> üzerinden alınıyor. PayPal ve kredi kartına ödeme yapılmıyor. Hesabın <em>functional currency</em>’si bir kez seçiliyor ve <strong>sonradan değiştirilemiyor</strong>; farklı para biriminden gelen satışlarda CJ %3 dönüşüm farkı uyguluyor.</p>';
	echo '</div>';
}

/** Kısa bağlantı ağları için çoğaltma kutusu. */
function gbc_ort_kisa_kutu() {
	$kisa = gbc_ort_kisa_liste();
	if ( ! $kisa ) { return; }
	$prog = gbc_ort_programlar();

	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Kısa bağlantı çoğalt</h2>';
	echo '<p class="gbc-ort-mini" style="max-width:840px">Skyscanner (Impact) ve Yesim gibi ağlar hedef adresi bağlantının içinde taşımaz; her hedef için panellerinde ayrı bir kod üretirler. O kodu sıfırdan kuramayız — ama elimizdeki bir kısa bağlantıyı yeni bir etiketle çoğaltmak tamamen otomatik. Rapor kırılımı da bu etiketten çıkıyor.</p>';

	echo '<table class="widefat striped"><thead><tr><th>Program</th><th>Kısa bağlantı</th><th style="width:80px">Kaç yerde</th><th style="width:330px">Yeni etiketli kopya</th></tr></thead><tbody>';
	foreach ( $kisa as $k => $tabanlar ) {
		$ad = isset( $prog[ $k ]['ad'] ) ? $prog[ $k ]['ad'] : $k;
		foreach ( $tabanlar as $taban => $bilgi ) {
			$fid = 'gbck-' . md5( $taban );
			echo '<tr>';
			echo '<td>' . esc_html( $ad ) . '<br>' . gbc_ort_ag_rozet( gbc_ort_ag( $taban ) ) . '</td>';
			echo '<td><code class="gbc-ort-kod">' . esc_html( $taban ) . '</code>';
			echo '<span class="gbc-ort-mini">etiket alanı: <code>' . esc_html( $bilgi['sub'] ) . '</code> · örnek kayıt: <code>' . esc_html( $bilgi['ornek'] ) . '</code></span></td>';
			echo '<td>' . (int) $bilgi['adet'] . '</td>';
			echo '<td><form method="post" style="display:flex;gap:6px;align-items:center">';
			wp_nonce_field( 'gbc_ort' );
			echo '<input type="hidden" name="gbc_ort_islem" value="kisa_ekle">';
			echo '<input type="hidden" name="taban" value="' . esc_attr( $taban ) . '">';
			echo '<input type="hidden" name="sub_ad" value="' . esc_attr( $bilgi['sub'] ) . '">';
			echo '<input type="text" name="id" placeholder="yeni kimlik" required style="width:150px">';
			echo '<input type="text" name="etiket" placeholder="görünen metin" style="width:150px">';
			echo '<button class="button button-small button-primary">Üret ve ekle</button>';
			echo '</form></td></tr>';
		}
	}
	echo '</tbody></table></div>';
}

/** Travelpayouts motorunun tanıdığı program mı? */
function gbc_ort_tp_tanir( $anahtar, $p ) {
	if ( ! function_exists( 'gbc_aff_tp_programlar' ) ) { return false; }
	$tp = gbc_aff_tp_programlar();
	foreach ( (array) $p['alanlar'] as $a ) { if ( isset( $tp[ $a ] ) ) { return true; } }
	return isset( $tp[ $anahtar ] );
}

/** Hedeften makul bir kimlik önerir. */
function gbc_ort_id_oner( $hedef ) {
	$alan = gbc_ort_alan( $hedef );
	$on = 'aff';
	$kisalt = array( 'booking.com' => 'bk', 'getyourguide.com' => 'gyg', 'discovercars.com' => 'dc', 'omio.com' => 'omio', 'omio.it' => 'omio', 'ferryhopper.com' => 'fh', 'skyscanner.net' => 'sky' );
	foreach ( $kisalt as $a => $k ) {
		if ( $alan === $a || substr( $alan, - ( strlen( $a ) + 1 ) ) === '.' . $a ) { $on = $k; break; }
	}
	$yol = (string) wp_parse_url( $hedef, PHP_URL_PATH );
	$son = sanitize_key( basename( preg_replace( '/\.[a-z.]+$/i', '', $yol ) ) );
	if ( '' === $son ) { $son = sanitize_key( $alan ); }
	return substr( $on . '_' . $son, 0, 50 );
}

/** Toplu girdiyi satırlara çözer. */
function gbc_ort_toplu_coz( $ham ) {
	$out = array();
	foreach ( preg_split( '/\R/u', (string) $ham ) as $satir ) {
		$satir = trim( $satir );
		if ( '' === $satir ) { continue; }
		$id = ''; $etiket = ''; $url = '';
		if ( false !== strpos( $satir, '|' ) ) {
			$p = array_map( 'trim', explode( '|', $satir ) );
			if ( count( $p ) >= 3 ) {
				$id = sanitize_key( $p[0] ); $etiket = $p[1]; $url = $p[2];
			} else {
				$url = $p[ count( $p ) - 1 ];
			}
		} else {
			$url = $satir;
		}
		if ( 0 !== stripos( $url, 'http' ) ) { continue; }
		if ( '' === $id ) { $id = gbc_ort_id_oner( $url ); }
		if ( '' === $etiket ) { $etiket = $id; }
		$out[] = array( 'id' => $id, 'etiket' => $etiket, 'url' => esc_url_raw( $url ) );
	}
	return $out;
}

/* ---------- KISA KODLAR ---------- */

function gbc_ort_sekme_kod() {
	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Kısa kodlar</h2>';
	echo '<p class="gbc-ort-mini">Liste elle tutulmuyor: WordPress’te kayıtlı bütün GBC kısa kodları buraya kendiliğinden düşer.</p>';
	echo '<table class="widefat striped"><thead><tr><th style="width:150px">Kod</th><th style="width:130px">Nerede</th><th style="width:330px">Örnek</th><th>Ne yapar</th></tr></thead><tbody>';
	foreach ( gbc_ort_kayitli_kodlar() as $etiket => $k ) {
		echo '<tr><td><code>[' . esc_html( $etiket ) . ']</code><br><span class="gbc-ort-mini">' . esc_html( $k['geri'] ) . '()</span></td>';
		echo '<td><span class="gbc-ort-mini">' . esc_html( $k['nerede'] ) . '</span></td>';
		echo '<td><code class="gbc-ort-kod">' . esc_html( $k['ornek'] ) . '</code></td>';
		echo '<td>' . esc_html( $k['aciklama'] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';

	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Bozulmaz kurallar</h2>';
	echo '<p class="gbc-ort-mini">Motor bunları kendisi uygular; yazıya elle yazmaya gerek yok.</p><ul style="margin:0">';
	foreach ( gbc_ort_kurallar() as $r ) { echo '<li>' . esc_html( $r ) . '</li>'; }
	echo '</ul></div>';

	echo '<div class="gbc-ort-kart">';
	echo '<h2 style="margin-top:0">Ölçüm — GA4</h2>';
	echo '<p>Olay adı: <code>outbound_click</code></p>';
	echo '<p class="gbc-ort-mini">Parametreler: ';
	foreach ( array( 'aff_id', 'program', 'post_id', 'link_url', 'link_text', 'page_template' ) as $p ) {
		echo '<code style="margin-right:5px">' . esc_html( $p ) . '</code>';
	}
	echo '</p>';
	echo '<p class="gbc-ort-sr">Bu parametrelerin raporda sütun olarak görünmesi için GA4 yönetiminde özel boyut olarak tanımlanmaları gerekiyor. O iş Google tarafında; siteden yapılamaz.</p>';
	echo '</div>';
}

/* ============================================================
   10) ESKİ EKRANI YÖNLENDİR
   ============================================================ */

/* ------------------------------------------------------------
   ESKİ MENÜ KÖPRÜSÜ — 29 Eylül 2026'da ölçülen regresyon.

   v1.15.0'da 'gbc-aff-denetim' menüsü kaldırıldı. Ama canlıda İKİ AKTİF
   WPCode snippet'i alt sayfasını hâlâ o menünün altına asıyor:
     30992  "Ortaklık Durum Ekranı"  → gbc-aff-durum
     30870  "Ortaklık Kılavuzu"      → gbc-aff-kilavuz
   Ana menü olmayınca WordPress bu alt sayfaları hiç kaydetmiyor ve iki
   ekran birden erişilemez hâle gelmişti.

   Çözüm: menüde GÖRÜNMEYEN bir üst sayfa kaydediyoruz. Snippet'ler
   çocuklarını ona asabiliyor, ekranlar yeniden açılıyor. Snippet'ler
   kapatılırsa buranın hiçbir etkisi kalmaz.
   ------------------------------------------------------------ */
add_action( 'admin_menu', 'gbc_ort_eski_menu_koprusu', 999 );
function gbc_ort_eski_menu_koprusu() {
	global $submenu;
	if ( empty( $submenu['gbc-aff-denetim'] ) || ! is_array( $submenu['gbc-aff-denetim'] ) ) { return; }

	/* Snippet'lerin astığı alt sayfaları GBC menüsüne taşı. Snippet'lere
	   dokunulmuyor; yalnız menüdeki yerleri düzeltiliyor. */
	foreach ( $submenu['gbc-aff-denetim'] as $oge ) {
		if ( empty( $oge[2] ) ) { continue; }
		$submenu['gbc'][] = array(
			isset( $oge[0] ) ? '— ' . $oge[0] : $oge[2],
			isset( $oge[1] ) ? $oge[1] : 'manage_options',
			$oge[2],
			isset( $oge[3] ) ? $oge[3] : '',
		);
	}
	unset( $submenu['gbc-aff-denetim'] );
}

add_action( 'admin_init', 'gbc_ort_eski_yonlendir' );
function gbc_ort_eski_yonlendir() {
	if ( ! is_admin() ) { return; }
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'gbc-aff-denetim' !== $sayfa ) { return; }
	wp_safe_redirect( gbc_ort_url( 'genel' ) );
	exit;
}

/* ============================================================
   11) BAĞLANTI SAĞLIK KONTROLÜ
   ------------------------------------------------------------
   "Linkler çalışıyor mu?" sorusuna kesin cevap veren bölüm.

   NEDEN PARTİ PARTİ: defterde 667 bağlantı var. Hepsini tek sayfa
   açılışında denemek dakikalarca sürer ve zaman aşımına düşer. Bu
   yüzden her turda en eski denenmiş N bağlantı sınanır; günlük cron
   sırayı kendiliğinden döndürür.

   NEDEN 403 BOZUK SAYILMAZ: ortaklık ağları (tp.media, pxf.io) sunucudan
   gelen isteği bot sanıp 403/429 döndürüyor. Aynı bağlantı tarayıcıda
   sorunsuz açılıyor. 28 Eylül 2026'da ölçüldü. Bu yüzden 403 ve 429
   "ağ botu engelledi" diye ayrı işaretlenir, kırık sayılmaz.
   ============================================================ */

function gbc_ort_saglik_anahtar() { return 'gbc_aff_saglik'; }

/** Kayıtlı sağlık sonuçları. */
function gbc_ort_saglik() {
	$s = get_option( gbc_ort_saglik_anahtar() );
	return is_array( $s ) ? $s : array();
}

/**
 * HTTP cevabından hüküm çıkarır.
 *
 * @return array array( durum, aciklama )
 *   durum: iyi | engel | kirik | ulasilamadi | bos
 */
function gbc_ort_saglik_hukum( $kod, $hata = '' ) {
	$kod = (int) $kod;
	if ( '' !== $hata ) {
		return array( 'ulasilamadi', $hata );
	}
	if ( $kod >= 200 && $kod < 300 ) { return array( 'iyi', 'HTTP ' . $kod ); }
	if ( $kod >= 300 && $kod < 400 ) { return array( 'iyi', 'HTTP ' . $kod . ' — hedefe yönlendiriyor' ); }
	if ( 403 === $kod || 429 === $kod ) {
		return array( 'engel', 'HTTP ' . $kod . ' — ağ sunucu isteğini bot sayıyor. Tarayıcıda açılır, bağlantı sağlam.' );
	}
	if ( 404 === $kod || 410 === $kod ) { return array( 'kirik', 'HTTP ' . $kod . ' — sayfa yok. Bu bağlantı ölü.' ); }
	if ( $kod >= 500 ) { return array( 'ulasilamadi', 'HTTP ' . $kod . ' — karşı sunucu hata veriyor.' ); }
	if ( 0 === $kod ) { return array( 'ulasilamadi', 'cevap yok' ); }
	return array( 'kirik', 'HTTP ' . $kod );
}

/** Tek bağlantıyı sınar ve sonucu döndürür. */
function gbc_ort_saglik_dene( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'durum' => 'bos', 'kod' => 0, 'not' => 'Adres boş.', 'zaman' => time() );
	}
	if ( ! function_exists( 'gbc_aff_link_test' ) ) {
		return array( 'durum' => 'ulasilamadi', 'kod' => 0, 'not' => 'Bağlantı testi modülü yüklü değil.', 'zaman' => time() );
	}
	/* 30 Eyl 2026 · v1.37.0 — SAHTE TIKLAMA ÖNLEMİ.
	   Eskiden ağ adresinin kendisi (tp.media, pxf.io) sunucudan çağrılıyordu;
	   her gün 60 bağlantı ağa tıklama olarak düşüyor, sub_id raporunu
	   kirletiyordu. Artık bağlantının gittiği ASIL sayfa sınanır. Hedefi
	   adreste yazmayan kısa bağlantı hiç açılmaz. */
	if ( function_exists( 'gbc_br_ortaklik_hedef' ) ) {
		$hedef = gbc_br_ortaklik_hedef( $url );
		if ( '' === $hedef ) {
			return array( 'durum' => 'kisa', 'kod' => 0, 'not' => 'Kısa bağlantı: sahte tıklama olmasın diye açılmadı.', 'zaman' => time() );
		}
		$url = $hedef;
	} else {
		return array( 'durum' => 'kisa', 'kod' => 0, 'not' => 'Hedef çözücü yüklü değil; ağ adresi açılmadı.', 'zaman' => time() );
	}
	$c = gbc_aff_link_test( $url );
	if ( is_wp_error( $c ) ) {
		list( $d, $n ) = gbc_ort_saglik_hukum( 0, $c->get_error_message() );
		return array( 'durum' => $d, 'kod' => 0, 'not' => $n, 'zaman' => time() );
	}
	$kod = (int) wp_remote_retrieve_response_code( $c );
	list( $d, $n ) = gbc_ort_saglik_hukum( $kod );
	return array( 'durum' => $d, 'kod' => $kod, 'not' => $n, 'zaman' => time() );
}

/**
 * En eski denenmiş N bağlantıyı sınar.
 *
 * @return array array( denenen, iyi, engel, kirik, ulasilamadi, kalan )
 */
function gbc_ort_saglik_parti( $adet = 25 ) {
	$defter = gbc_ort_satirlar();
	$kayit  = gbc_ort_saglik();

	/* Hiç denenmemişler önce, sonra en eski denenmişler. */
	$sira = array();
	foreach ( $defter as $id => $b ) {
		$sira[ $id ] = isset( $kayit[ $id ]['zaman'] ) ? (int) $kayit[ $id ]['zaman'] : 0;
	}
	asort( $sira );

	$sonuc = array( 'denenen' => 0, 'iyi' => 0, 'engel' => 0, 'kirik' => 0, 'ulasilamadi' => 0, 'bos' => 0 );
	$n = 0;
	foreach ( $sira as $id => $z ) {
		if ( $n >= (int) $adet ) { break; }
		$d = gbc_ort_saglik_dene( $defter[ $id ]['url'] );
		/* Son iyi zamanı koru: bugün engel yediyse dün çalıştığını unutma. */
		if ( 'iyi' === $d['durum'] ) {
			$d['son_iyi'] = $d['zaman'];
		} elseif ( isset( $kayit[ $id ]['son_iyi'] ) ) {
			$d['son_iyi'] = (int) $kayit[ $id ]['son_iyi'];
		}
		$kayit[ $id ] = $d;
		$sonuc['denenen']++;
		if ( isset( $sonuc[ $d['durum'] ] ) ) { $sonuc[ $d['durum'] ]++; }
		$n++;
	}

	/* Defterden silinmiş kimliklerin kaydını temizle. */
	foreach ( array_keys( $kayit ) as $id ) {
		if ( ! isset( $defter[ $id ] ) ) { unset( $kayit[ $id ] ); }
	}

	update_option( gbc_ort_saglik_anahtar(), $kayit, false );

	$hic = 0;
	foreach ( $defter as $id => $b ) { if ( empty( $kayit[ $id ]['zaman'] ) ) { $hic++; } }
	$sonuc['kalan'] = $hic;
	return $sonuc;
}

/** Sağlık özeti — Genel Bakış kartları ve alarm için. */
function gbc_ort_saglik_ozet() {
	$defter = gbc_ort_satirlar();
	$kayit  = gbc_ort_saglik();
	$o = array( 'toplam' => count( $defter ), 'iyi' => 0, 'engel' => 0, 'kirik' => 0,
		'ulasilamadi' => 0, 'bos' => 0, 'hic' => 0, 'kirik_idler' => array(), 'en_eski' => 0 );

	foreach ( $defter as $id => $b ) {
		if ( empty( $kayit[ $id ]['durum'] ) ) { $o['hic']++; continue; }
		$d = $kayit[ $id ]['durum'];
		if ( isset( $o[ $d ] ) ) { $o[ $d ]++; }
		if ( 'kirik' === $d ) { $o['kirik_idler'][] = $id; }
		$z = (int) $kayit[ $id ]['zaman'];
		if ( 0 === $o['en_eski'] || $z < $o['en_eski'] ) { $o['en_eski'] = $z; }
	}
	return $o;
}

/* --- Günlük cron --- */
add_action( 'gbc_ort_saglik_cron', 'gbc_ort_saglik_cron_calis' );
function gbc_ort_saglik_cron_calis() {
	if ( function_exists( 'gbc_kilit_al' ) && ! gbc_kilit_al( 'ortaklik-sagligi' ) ) { wp_schedule_single_event( time() + 900, 'gbc_ort_saglik_cron' ); return; }
	$adet = (int) apply_filters( 'gbc_ort_saglik_parti_adet', 60 );
	$s = gbc_ort_saglik_parti( $adet );

	/* Kırık bağlantı varsa günlük deftere sorun aç. */
	if ( function_exists( 'gbc_sorun_ac' ) ) {
		$o = gbc_ort_saglik_ozet();
		if ( ! empty( $o['kirik'] ) ) {
			gbc_sorun_ac(
				'aff-kirik',
				'ortaklik',
				sprintf( __( '%d ortaklık bağlantısı ölü', 'gbc-core' ), (int) $o['kirik'] ),
				sprintf( __( 'Sunucu 404/410 döndürüyor: %s', 'gbc-core' ), implode( ', ', array_slice( $o['kirik_idler'], 0, 10 ) ) ),
				'yuksek',
				gbc_ort_url( 'baglanti', array( 'suz' => 'kirik' ) )
			);
		} elseif ( function_exists( 'gbc_sorun_kapat' ) ) {
			gbc_sorun_kapat( 'aff-kirik' );
		}
	}
	return $s;
}

/** Cron kaydı — günde bir, sabah 05:40 civarı. */
add_action( 'admin_init', 'gbc_ort_saglik_cron_kur' );
function gbc_ort_saglik_cron_kur() {
	if ( wp_next_scheduled( 'gbc_ort_saglik_cron' ) ) { return; }
	/* Günlük kontrolle çakışmasın diye 06:10. GMT düzeltmesi gunluk.php ile aynı. */
	$saat = strtotime( 'tomorrow 06:10', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	wp_schedule_event( $saat, 'daily', 'gbc_ort_saglik_cron' );
}

/* ============================================================
   12) CJ AFFILIATE (Commission Junction)
   ------------------------------------------------------------
   Kaynak: CJ New Publisher Welcome Kit 2026, slayt 24.
   Takip bağlantısının anatomisi:

     https://www.jdoqocy.com/click-9265128-10463300?sid=TEST&url=https%3A%2F%2F...
                             ^^^^^ ^^^^^^^ ^^^^^^^^  ^^^      ^^^
                             yol   PID     AID       SID      hedef

   PID  = Promotional Property ID. HER MECRANIN AYRI PID'i VAR:
          site, YouTube, Instagram, TikTok, Pinterest, Facebook…
          Aynı hedefe farklı PID ile bağlantı üretirsen CJ raporunda
          hangi kanalın kazandırdığını AYRI AYRI görürsün. Bu bölümün
          bütün amacı bu.
   AID  = bağlantının kendi kimliği, advertiser'a ait, değiştirilmez.
   SID  = kendi etiketin, serbest. Biz sayfa/kampanya adı koyuyoruz.

   CJ takip sunucusu tek alan adı değil, dönüşümlü kullanıyor.
   ============================================================ */

/** CJ'nin takip alan adları. */
function gbc_ort_cj_alanlar() {
	return (array) apply_filters( 'gbc_ort_cj_alanlar', array(
		'jdoqocy.com', 'dpbolvw.net', 'tkqlhce.com', 'anrdoezrs.net',
		'kqzyfj.com', 'ftjcfx.com', 'awltovhc.com', 'lduhtrp.net',
		'tqlkg.com', 'gopjn.com', 'sjv.io', 'emjcd.com',
	) );
}

/** Adres CJ takip bağlantısı mı? */
function gbc_ort_cj_mi( $url ) {
	$a = gbc_ort_alan( $url );
	if ( '' === $a ) { return false; }
	foreach ( gbc_ort_cj_alanlar() as $k ) {
		if ( $a === $k || substr( $a, - ( strlen( $k ) + 1 ) ) === '.' . $k ) { return true; }
	}
	return false;
}

/**
 * CJ bağlantısını parçalarına ayırır.
 *
 * @return array|null array( pid, aid, sid, hedef, alan )
 */
function gbc_ort_cj_coz( $url ) {
	$url = trim( (string) $url );
	if ( ! gbc_ort_cj_mi( $url ) ) { return null; }

	$p = wp_parse_url( $url );
	$yol = isset( $p['path'] ) ? $p['path'] : '';
	if ( ! preg_match( '#/click-(\d+)-(\d+)#', $yol, $m ) ) { return null; }

	$q = array();
	if ( ! empty( $p['query'] ) ) { parse_str( $p['query'], $q ); }

	$hedef = '';
	foreach ( array( 'url', 'u' ) as $anahtar ) {
		if ( ! empty( $q[ $anahtar ] ) && 0 === stripos( $q[ $anahtar ], 'http' ) ) { $hedef = $q[ $anahtar ]; break; }
	}

	return array(
		'pid'   => $m[1],
		'aid'   => $m[2],
		'sid'   => isset( $q['sid'] ) ? (string) $q['sid'] : '',
		'hedef' => $hedef,
		'alan'  => isset( $p['host'] ) ? $p['host'] : '',
	);
}

/**
 * Var olan bir CJ bağlantısını BAŞKA BİR MÜLK (PID) için yeniden kurar.
 * AID ve hedef aynı kalır — sadece hangi kanaldan geldiği değişir.
 *
 * @return string Boş dönerse bağlantı CJ değil ya da çözülemedi.
 */
function gbc_ort_cj_uret( $url, $pid, $sid = '' ) {
	$c = gbc_ort_cj_coz( $url );
	if ( ! $c ) { return ''; }
	$pid = preg_replace( '/[^0-9]/', '', (string) $pid );
	if ( '' === $pid ) { return ''; }

	$adres = 'https://' . $c['alan'] . '/click-' . $pid . '-' . $c['aid'];

	$par = array();
	$sid = sanitize_text_field( $sid );
	if ( '' !== $sid ) { $par[] = 'sid=' . rawurlencode( $sid ); }
	if ( '' !== $c['hedef'] ) { $par[] = 'url=' . rawurlencode( $c['hedef'] ); }

	return $par ? $adres . '?' . implode( '&', $par ) : $adres;
}

/* ---------- MÜLK (PID) DEFTERİ ---------- */

/** Mecra anahtarı => varsayılan mülk adı. CJ'deki Promotional Property'ler. */
function gbc_ort_mulk_tohum() {
	return array(
		'site'        => array( 'ad' => 'gezginbirchef.com', 'mecra' => 'site' ),
		'yt_gezi'     => array( 'ad' => 'YouTube — Gezginbirchef', 'mecra' => 'youtube' ),
		'yt_yemek'    => array( 'ad' => 'YouTube — Yemek Tarifleri', 'mecra' => 'youtube' ),
		'instagram'   => array( 'ad' => 'Instagram — @gezginbirchef', 'mecra' => 'instagram' ),
		'tiktok'      => array( 'ad' => 'TikTok — @gezginbirchef', 'mecra' => 'tiktok' ),
		'pinterest'   => array( 'ad' => 'Pinterest — gezginbirchef', 'mecra' => 'pinterest' ),
		'facebook'    => array( 'ad' => 'Facebook — gezginbirchef', 'mecra' => 'facebook' ),
		'lg_youtube'  => array( 'ad' => 'Living Greece — YouTube', 'mecra' => 'youtube' ),
		'lg_instagram'=> array( 'ad' => 'Living Greece — Instagram', 'mecra' => 'instagram' ),
		'lg_tiktok'   => array( 'ad' => 'Living Greece — TikTok', 'mecra' => 'tiktok' ),
		'bulten'      => array( 'ad' => 'E-posta bülteni', 'mecra' => 'eposta' ),
	);
}

/** Kayıtlı mülkler: anahtar => array( ad, mecra, pid ). */
function gbc_ort_mulkler() {
	$kayit = get_option( 'gbc_aff_cj_mulk' );
	if ( ! is_array( $kayit ) ) { $kayit = array(); }
	$out = array();
	foreach ( gbc_ort_mulk_tohum() as $k => $t ) {
		$out[ $k ] = array(
			'ad'    => isset( $kayit[ $k ]['ad'] ) && '' !== $kayit[ $k ]['ad'] ? $kayit[ $k ]['ad'] : $t['ad'],
			'mecra' => $t['mecra'],
			'pid'   => isset( $kayit[ $k ]['pid'] ) ? (string) $kayit[ $k ]['pid'] : '',
		);
	}
	/* Kullanıcının eklediği fazladan mülkler */
	foreach ( $kayit as $k => $v ) {
		if ( isset( $out[ $k ] ) ) { continue; }
		$out[ $k ] = array(
			'ad'    => isset( $v['ad'] ) ? $v['ad'] : $k,
			'mecra' => isset( $v['mecra'] ) ? $v['mecra'] : 'site',
			'pid'   => isset( $v['pid'] ) ? (string) $v['pid'] : '',
		);
	}
	return $out;
}

/** PID'i dolu mülkler. */
function gbc_ort_mulk_dolu() {
	$o = array();
	foreach ( gbc_ort_mulkler() as $k => $m ) {
		if ( '' !== $m['pid'] ) { $o[ $k ] = $m; }
	}
	return $o;
}

/**
 * Bir mülkün, o programın kurallarına göre kullanılabilir olup olmadığı.
 *
 * @return string var | yok | sart | ?
 */
function gbc_ort_mulk_izin( $mulk_mecra, $program_anahtar ) {
	$p = gbc_ort_programlar();
	if ( empty( $p[ $program_anahtar ]['mecra'][ $mulk_mecra ] ) ) { return '?'; }
	return $p[ $program_anahtar ]['mecra'][ $mulk_mecra ];
}

/** CJ ödeme takvimi — kitten (slayt 28 ve 41). */
function gbc_ort_cj_odeme() {
	return array(
		array( 'New', 'İşlem CJ sunucusuna düştü. Henüz kesinleşmedi.' ),
		array( 'Extended', 'Advertiser bir kez, bir ay uzatma hakkını kullandı.' ),
		array( 'Locked', 'İnceleme bitti, artık değiştirilemez. Genelde ayın 10’unda kilitlenir.' ),
		array( 'Closed', 'Ödenecek. Ayda iki kez kapanır: 11’inde kapananlar ~16’sında, 22’sinde kapananlar ~28’inde ödenir.' ),
	);
}
