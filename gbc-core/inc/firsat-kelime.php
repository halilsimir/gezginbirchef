<?php
/**
 * GBC Core · Kelime fırsatları (v1.42.0, 30 Eylül 2026)
 *
 * Sayfa Denetimi her açıldığında o sayfanın kelime evreni analizinden
 * "yapılabilecek" işler çıkarılır ve `gbc_kw_firsat` meta'sına özet olarak yazılır
 * (yalnız değiştiyse). SEO · Fırsatlar ekranı bu özetleri bütün sayfalardan toplar.
 *
 * Türler:
 *   kelime     — alakalı, hacmi var, sayfada hiç geçmiyor (H2 / H3 / SSS / paragraf önerisiyle)
 *   yeni_sayfa — hacmi yüksek, ayrı konu: ayrı sayfa açılmalı
 *   tasi       — geçiyor ama yalnız metinde/SSS'de, hacmi yüksek: başlığa taşınmalı
 *   onay       — Claude'un alaka kararları onay bekliyor
 *   tara       — Search Console'da gösterim alıyor ama kelime evreni hiç taranmamış
 *   kanibal    — Sayfa Denetimi'nin bulduğu kanibalizasyon (kesin/risk), Search Console türüyle aynı süzgeçte
 *
 * Ağa, ortaklık adreslerine ya da dış servislere istek atmaz; yalnız kayıtlı veriyi okur.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const GBC_FK_META = 'gbc_kw_firsat';

/** Analizden sayfa özetini çıkarır. */
function gbc_fk_ozet( $kw, $kn = null ) {
	$satir = array();
	$onay  = 0;
	foreach ( (array) $kw['satir'] as $s ) {
		if ( ! empty( $s['claude_bekliyor'] ) ) { $onay++; }
		if ( 'evet' !== $s['al'] || null === $s['hh'] ) { continue; }
		$e = (string) $s['oneri'];
		if ( '' !== $s['yer'] && 'tasi' !== $e ) { continue; }
		if ( ! in_array( $e, array( 'h2', 'h3', 'sss', 'metin', 'sayfa', 'tasi' ), true ) ) { continue; }
		$kan = '';
		if ( is_array( $kn ) && isset( $kn['satir'][ $s['k'] ]['durum'] ) ) { $kan = (string) $kn['satir'][ $s['k'] ]['durum']; }
		$satir[] = array(
			'k'   => (string) $s['k'],
			'h'   => (int) $s['hh'],
			'e'   => $e,
			'n'   => (string) $s['oneri_not'],
			'yk'  => (string) $s['yakalama'],
			'gsc' => in_array( 'gsc', isset( $s['kay'] ) ? (array) $s['kay'] : array(), true ) ? 1 : 0,
			'kan' => $kan,
		);
	}
	usort( $satir, static function ( $a, $b ) { return $b['h'] - $a['h']; } );
	/* Sayfa Denetimi'nin bulduğu kanibalizasyon (Search Console + başlık hedefi). */
	$hacim = array();
	foreach ( (array) $kw['satir'] as $s ) { $hacim[ (string) $s['k'] ] = (int) $s['hh']; }
	$kan = array();
	if ( is_array( $kn ) && ! empty( $kn['satir'] ) ) {
		foreach ( $kn['satir'] as $k => $b ) {
			$kan[] = array( 'k' => (string) $k, 'd' => (string) $b['durum'], 'h' => isset( $hacim[ $k ] ) ? $hacim[ $k ] : 0, 'n' => (string) $b['oneri'] );
		}
	}
	return array(
		'kan'   => array_slice( $kan, 0, 20 ),
		'tohum' => (string) $kw['tohum'],
		'oran'  => (int) $kw['ozet']['oran'],
		'onay'  => $onay,
		'satir' => array_slice( $satir, 0, 40 ),
	);
}

/** Özet değiştiyse kaydeder. Tarih yalnız içerik değişince yenilenir. */
function gbc_fk_kaydet( $pid, $kw, $kn = null ) {
	$pid = (int) $pid;
	if ( ! $pid || ! is_array( $kw ) || empty( $kw['v']['kelimeler'] ) ) { return; }
	$yeni = gbc_fk_ozet( $kw, $kn );
	$eski = json_decode( (string) get_post_meta( $pid, GBC_FK_META, true ), true );
	$karsi = is_array( $eski ) ? $eski : array();
	unset( $karsi['t'] );
	if ( $karsi === $yeni ) { return; }
	$yeni['t'] = time();
	update_post_meta( $pid, GBC_FK_META, wp_slash( wp_json_encode( $yeni, JSON_UNESCAPED_UNICODE ) ) );
}

/** Bütün sayfalardaki özetler: pid => özet. */
function gbc_fk_hepsi() {
	global $wpdb;
	$r = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m
		   JOIN {$wpdb->posts} p ON p.ID = m.post_id
		  WHERE m.meta_key = %s AND p.post_status IN ('publish','draft','future','pending')",
		GBC_FK_META
	), ARRAY_A );
	$o = array();
	foreach ( $r as $x ) {
		$j = json_decode( (string) $x['meta_value'], true );
		if ( is_array( $j ) ) { $o[ (int) $x['post_id'] ] = $j; }
	}
	return $o;
}

/** Yakalama ihtimaline göre ağırlık. */
function gbc_fk_agirlik( $yk ) {
	$a = array( 'yüksek' => 1.0, 'orta' => 0.6, 'düşük' => 0.3 );
	return isset( $a[ $yk ] ) ? $a[ $yk ] : 0.5;
}

/**
 * Fırsat satırları — gbc_seo_firsatlar() ile aynı biçimde.
 * Puan 90 günlük gösterime denk tutulur (aylık hacim × 3) ki iki kaynak aynı listede sıralanabilsin.
 */
function gbc_fk_firsatlar( $gun = 90 ) {
	$o = array();
	$ad_eylem = array(
		'h2'    => __( 'Yeni H2 başlık', 'gbc-core' ),
		'h3'    => __( 'H3 ara başlık', 'gbc-core' ),
		'sss'   => __( 'SSS sorusu', 'gbc-core' ),
		'metin' => __( 'Paragrafa cümle', 'gbc-core' ),
	);
	$hepsi = gbc_fk_hepsi();
	foreach ( $hepsi as $pid => $f ) {
		$url = get_permalink( $pid );
		foreach ( (array) ( isset( $f['satir'] ) ? $f['satir'] : array() ) as $s ) {
			$h   = (int) $s['h'];
			$ek  = ! empty( $s['gsc'] ) ? ' ' . __( 'Google bu sayfayı bu kelimede zaten gösteriyor: en kolay kazanç.', 'gbc-core' ) : '';
			if ( ! empty( $s['kan'] ) ) { $ek .= ' ' . __( 'Dikkat: kanibalizasyon kaydı var, önce onu çöz.', 'gbc-core' ); }
			$carpan = ( ! empty( $s['gsc'] ) ? 1.5 : 1.0 ) * ( ! empty( $s['kan'] ) ? 0.5 : 1.0 );
			if ( 'sayfa' === $s['e'] ) {
				$tur = 'yeni_sayfa';
				$not = __( 'Yeni sayfa aç: ', 'gbc-core' ) . $s['n'];
				$p   = $h * 3 * 0.8;
			} elseif ( 'tasi' === $s['e'] ) {
				$tur = 'tasi';
				$not = $s['n'];
				$p   = $h * 3 * 0.5;
			} else {
				$tur = 'kelime';
				$not = ( isset( $ad_eylem[ $s['e'] ] ) ? $ad_eylem[ $s['e'] ] . ': ' : '' ) . $s['n'];
				$p   = $h * 3 * gbc_fk_agirlik( $s['yk'] );
			}
			$o[] = array(
				'tur' => $tur, 'puan' => $p * $carpan, 'kelime' => $s['k'], 'sayfa' => $url, 'pid' => $pid,
				'sira' => null, 'gos' => null, 'tik' => null, 'ctr' => null, 'hacim' => $h,
				'yk' => (string) $s['yk'], 'gsc' => ! empty( $s['gsc'] ),
				'not' => trim( $not . $ek ),
			);
		}
		foreach ( (array) ( isset( $f['kan'] ) ? $f['kan'] : array() ) as $c ) {
			$o[] = array(
				'tur' => 'kanibal', 'puan' => max( 60, (int) $c['h'] * 3 ) * ( 'kesin' === $c['d'] ? 1.0 : 0.7 ), 'kelime' => $c['k'], 'sayfa' => $url, 'pid' => $pid,
				'sira' => null, 'gos' => null, 'tik' => null, 'ctr' => null, 'hacim' => $c['h'] ? (int) $c['h'] : null, 'yk' => '', 'gsc' => false,
				'not' => ( 'kesin' === $c['d'] ? __( 'KESİN: başka sayfamız da bu kelimede gösterim alıyor. ', 'gbc-core' ) : __( 'RİSK: başka sayfamızın başlığı bu kelimeyi hedefliyor. ', 'gbc-core' ) ) . $c['n'],
			);
		}
		if ( ! empty( $f['onay'] ) ) {
			$o[] = array(
				'tur' => 'onay', 'puan' => 50 + (int) $f['onay'], 'kelime' => (string) $f['tohum'], 'sayfa' => $url, 'pid' => $pid,
				'sira' => null, 'gos' => null, 'tik' => null, 'ctr' => null, 'hacim' => null, 'yk' => '', 'gsc' => false,
				'not' => sprintf( __( '%d kelimede Claude\'un alaka kararı onayını bekliyor. Onaylanınca bu sayfanın fırsatları kesinleşir.', 'gbc-core' ), (int) $f['onay'] ),
			);
		}
	}

	/* Taranmamış sayfalar: Search Console'da gösterim alıyor, kelime evreni hiç yok. */
	if ( function_exists( 'gbc_seo_kaynak' ) ) {
		$k = gbc_seo_kaynak();
		if ( ! empty( $k['var'] ) ) {
			global $wpdb;
			$sayfalar = (array) $wpdb->get_results( $wpdb->prepare(
				"SELECT page, SUM(impressions) gos, SUM(clicks) tik, COUNT(DISTINCT query) kel
				   FROM `{$k['tablo']}`
				  WHERE created >= %s" . ( function_exists( 'gbc_seo_tur_kosulu' ) ? gbc_seo_tur_kosulu( $k ) : '' ) . "
				  GROUP BY page ORDER BY gos DESC LIMIT 60",
				gbc_seo_sinir( $gun )
			), ARRAY_A );
			$n = 0;
			foreach ( $sayfalar as $s ) {
				$pid = (int) url_to_postid( $s['page'] );
				if ( ! $pid || isset( $hepsi[ $pid ] ) ) { continue; }
				if ( '' !== (string) get_post_meta( $pid, 'gbc_kw_evren', true ) ) { continue; }
				if ( $n++ >= 25 ) { break; }
				$o[] = array(
					'tur' => 'tara', 'puan' => (int) $s['gos'] * 0.3, 'kelime' => get_the_title( $pid ), 'sayfa' => $s['page'], 'pid' => $pid,
					'sira' => null, 'gos' => (int) $s['gos'], 'tik' => (int) $s['tik'], 'ctr' => null, 'hacim' => null, 'yk' => '', 'gsc' => true,
					'not' => sprintf( __( 'Google bu sayfayı %1$d farklı aramada gösteriyor ama kelime evreni hiç taranmamış. "Tara" ile kelimeleri çıkar.', 'gbc-core' ), (int) $s['kel'] ),
				);
			}
		}
	}
	return $o;
}
