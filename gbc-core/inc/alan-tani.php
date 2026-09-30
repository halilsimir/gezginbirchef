<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · Alan Tanı — tek yazıyı tarama gözüyle okur.
 *
 * NEDEN
 * -----
 * 30 Eyl 2026: İçerik Denetimi, dolu olan alanları BOŞ raporluyor.
 * Canlıda doğrulandı — Kefir (#3867) ve Kete (#3871) yazılarında
 * tarif_faq alanı dolu (748 ve 812 karakter, sayfada SSS bölümü
 * görünüyor) ama denetim "eksik" diyor. Yönetici hesabından baştan
 * tam tarama çalıştırıldı, sonuç değişmedi: veri eski değil, OKUMA
 * hatalı.
 *
 * Değerlerin şekli sebep değil: bayraklı Kefir ile temiz Focaccia'nın
 * tarif_faq değeri birebir aynı yapıda (düz metin, etiketsiz, "S:" ile
 * başlıyor). Yani fark yazıda değil, get_fields() çağrısının o yazı
 * için ne döndürdüğünde.
 *
 * Bu ekran tahmin yürütmeyi bitirir: tek bir yazıyı taramanın kullandığı
 * yoldan okur ve yanına ham veritabanı değerini koyar. İkisi ayrışıyorsa
 * hangi katmanda ayrıştığı görülür.
 *
 * SALT OKUMA. Hiçbir şey yazmaz, hiçbir ayarı değiştirmez.
 *
 * 30 Eyl 2026 · v1.34.0
 */

/**
 * Bir yazıyı üç ayrı yoldan okur ve karşılaştırır.
 *
 * @param int $pid Yazı kimliği.
 * @return array Tanı tablosu.
 */
function gbc_tani_oku( $pid ) {
	$pid = (int) $pid;
	$out = array( 'pid' => $pid );

	$p = get_post( $pid );
	if ( ! $p ) { $out['hata'] = 'Yazı bulunamadı.'; return $out; }

	$out['baslik'] = $p->post_title;
	$out['tur']    = $p->post_type;
	$out['durum']  = $p->post_status;
	$out['sablon'] = function_exists( 'gbc_ic_sablon_bul' )
		? gbc_ic_sablon_bul( $p->post_content )['sablon'] : '?';

	/* 1) TARAMANIN YOLU — birebir aynı çağrı. */
	$tarama = function_exists( 'gbc_ic_alanlar' ) ? gbc_ic_alanlar( $pid ) : array();
	$out['tarama_alan_sayisi'] = is_array( $tarama ) ? count( $tarama ) : -1;

	/* 2) ACF'nin BİÇİMLENDİRİLMİŞ yolu — fark biçimlendirmeden mi geliyor? */
	$bicimli = function_exists( 'get_fields' ) ? get_fields( $pid, true ) : array();
	$out['bicimli_alan_sayisi'] = is_array( $bicimli ) ? count( $bicimli ) : -1;

	/* 3) Bu yazıya hangi ACF alan grupları uyuyor? */
	$out['alan_gruplari'] = array();
	if ( function_exists( 'acf_get_field_groups' ) ) {
		foreach ( (array) acf_get_field_groups( array( 'post_id' => $pid ) ) as $g ) {
			$out['alan_gruplari'][] = isset( $g['title'] ) ? $g['title'] : '?';
		}
	}

	/* 4) Alan alan karşılaştırma. Normda geçen adlar + tarif/gezi alanları. */
	$adlar = array();
	foreach ( array_keys( (array) $tarama ) as $a )  { $adlar[ $a ] = true; }
	foreach ( array_keys( (array) $bicimli ) as $a ) { $adlar[ $a ] = true; }
	/* Denetimin şikâyet ettiği alanlar listede yoksa bile bak. */
	foreach ( array( 'tarif_faq', 'tarif_alt_linkler', 'tarif_temel', 'tarif_giris' ) as $a ) {
		$adlar[ $a ] = true;
	}
	ksort( $adlar );

	$satir = array();
	foreach ( array_keys( $adlar ) as $ad ) {
		$t_var = array_key_exists( $ad, (array) $tarama );
		$t_deg = $t_var ? $tarama[ $ad ] : null;
		$b_var = array_key_exists( $ad, (array) $bicimli );

		$ham       = get_post_meta( $pid, $ad, true );          /* ham postmeta */
		$isaretci  = get_post_meta( $pid, '_' . $ad, true );    /* ACF alan anahtarı */
		$tek       = function_exists( 'get_field' ) ? get_field( $ad, $pid, false ) : null;

		$uz = static function ( $v ) {
			if ( is_array( $v ) )  { return 'dizi(' . count( $v ) . ')'; }
			if ( null === $v )     { return 'null'; }
			if ( false === $v )    { return 'false'; }
			$s = trim( (string) $v );
			return '' === $s ? 'boş' : strlen( $s );
		};

		$dolu_tarama = function_exists( 'gbc_ic_dolu' ) ? gbc_ic_dolu( $t_deg ) : null;
		$dolu_ham    = function_exists( 'gbc_ic_dolu' ) ? gbc_ic_dolu( $ham )   : null;

		/* Yalnız AYRIŞAN ve dolu olabilecek satırlar ilgi çekici. */
		$ayrisma = ( $dolu_tarama !== $dolu_ham );
		if ( ! $ayrisma && ! $dolu_ham ) { continue; }

		$satir[] = array(
			'ad'           => $ad,
			'taramada_var' => $t_var ? 'evet' : 'HAYIR',
			'tarama'       => $uz( $t_deg ),
			'get_field'    => $uz( $tek ),
			'ham_meta'     => $uz( $ham ),
			'isaretci'     => $isaretci ? 'var' : 'YOK',
			'bicimlide'    => $b_var ? 'evet' : 'hayır',
			'AYRISMA'      => $ayrisma ? 'EVET' : '',
		);
	}
	$out['satirlar'] = $satir;
	$out['ayrisan']  = count( array_filter( $satir, static function ( $s ) { return 'EVET' === $s['AYRISMA']; } ) );
	return $out;
}

/**
 * Ekran — İçerik Denetimi sayfasının altına eklenir.
 */
function gbc_tani_ekran() {
	$pid = isset( $_GET['gbc_tani'] ) ? (int) $_GET['gbc_tani'] : 0;

	echo '<h2 style="margin-top:28px">Alan Tanı <span class="description" style="font-weight:400">'
		. '— tek yazıyı taramanın gözüyle okur (salt okuma)</span></h2>';
	echo '<form method="get" style="margin:8px 0 14px">'
		. '<input type="hidden" name="page" value="gbc-icerik">'
		. '<input type="number" name="gbc_tani" value="' . ( $pid ? esc_attr( $pid ) : '' ) . '" '
		. 'placeholder="yazı numarası" style="width:140px">'
		. ' <button class="button">Oku</button>'
		. ' <span class="description">Denetim bir alanı boş diyor ama sayfada görünüyorsa buraya numarayı yaz.</span>'
		. '</form>';

	if ( ! $pid ) { return; }

	$t = gbc_tani_oku( $pid );
	if ( ! empty( $t['hata'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $t['hata'] ) . '</p></div>';
		return;
	}

	echo '<p><strong>#' . (int) $t['pid'] . '</strong> ' . esc_html( $t['baslik'] )
		. ' · ' . esc_html( $t['tur'] ) . ' · ' . esc_html( $t['durum'] )
		. ' · şablon: <code>' . esc_html( $t['sablon'] ) . '</code></p>';
	echo '<p>Taramanın gördüğü alan: <strong>' . (int) $t['tarama_alan_sayisi'] . '</strong>'
		. ' · biçimlendirilmiş okuma: <strong>' . (int) $t['bicimli_alan_sayisi'] . '</strong>'
		. ' · eşleşen ACF grubu: <strong>' . esc_html( implode( ', ', $t['alan_gruplari'] ) ?: 'YOK' ) . '</strong></p>';

	if ( $t['ayrisan'] ) {
		echo '<div class="notice notice-error" style="margin:10px 0;padding:8px 12px"><p style="margin:0">'
			. '<strong>' . (int) $t['ayrisan'] . ' alanda AYRIŞMA var</strong> — tarama ile veritabanı aynı şeyi söylemiyor.'
			. '</p></div>';
	} else {
		echo '<div class="notice notice-success" style="margin:10px 0;padding:8px 12px"><p style="margin:0">'
			. 'Ayrışma yok — tarama bu yazıyı doğru okuyor.</p></div>';
	}

	echo '<table class="widefat striped"><thead><tr>'
		. '<th>Alan</th><th>Taramada var mı</th><th>Tarama değeri</th><th>get_field()</th>'
		. '<th>Ham postmeta</th><th>ACF işaretçisi</th><th>Biçimlide</th><th>Ayrışma</th>'
		. '</tr></thead><tbody>';
	foreach ( $t['satirlar'] as $s ) {
		$vurgu = 'EVET' === $s['AYRISMA'] ? ' style="background:#fcf0f1"' : '';
		echo '<tr' . $vurgu . '>'
			. '<td><code>' . esc_html( $s['ad'] ) . '</code></td>'
			. '<td>' . esc_html( $s['taramada_var'] ) . '</td>'
			. '<td>' . esc_html( $s['tarama'] ) . '</td>'
			. '<td>' . esc_html( $s['get_field'] ) . '</td>'
			. '<td>' . esc_html( $s['ham_meta'] ) . '</td>'
			. '<td>' . esc_html( $s['isaretci'] ) . '</td>'
			. '<td>' . esc_html( $s['bicimlide'] ) . '</td>'
			. '<td><strong>' . esc_html( $s['AYRISMA'] ) . '</strong></td>'
			. '</tr>';
	}
	echo '</tbody></table>';
}
