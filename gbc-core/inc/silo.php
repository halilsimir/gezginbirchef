<?php
/**
 * GBC Core · Silo ağacı (Sayfa Denetimi içinde) — v1.45.0, 30 Eylül 2026
 * ------------------------------------------------------------
 * HİYERARŞİ plan tablosundan gelir (inc/plan.php):
 *   bölge hub'ı ("İtalya Gezilecek Yerler — Ana Hub")
 *     ├ şehir rehberi (ana satır, örn. #14 Sorrento)
 *     │   └ alt sayfalar (↳ satırlar: 14.1, 14.2…)
 *     └ hub yardımcıları (hub'ın ↳ satırları: vize, dil…)
 *   Dikey bağ: şehir ↔ hub, şehir ↔ alt sayfa. Yatay bağ: aynı bölgedeki şehirler, yardımcı sayfalar.
 *
 * GERÇEK BAĞLAR sayfanın kendisinden okunur:
 *   Giden: bağlantı raporunun iç linkleri (render edilmiş sayfa; ACF içeriği dahil).
 *   Gelen: denetlenmiş sayfaların kaydettiği giden linkler (_gbc_giden) + Rank Math iç link tablosu.
 *   Rank Math tek başına yetmez: içerik ACF alanlarında, onun sayacı yazı gövdesine (kısa kod) bakıyor.
 *   Gelen bağ, karşı sayfa henüz denetlenmediyse "ölçülmedi" yazar (yanlış "yok" demez).
 *
 * PUAN (GBC skorunun 8. bölümü, 10): yukarı bağ 4 · yukarıdan gelen bağ 2 · alt sayfalara bağ 2 · yatay bağ 2.
 * Uygulanmayan madde puana katılmaz. Sayfa plan tablosunda yoksa bölüm "veri yok" sayılır.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bağlantı raporundaki iç linklerin hedef sayfa numaraları. */
function gbc_silo_giden( $br ) {
	$o = array();
	foreach ( ( is_array( $br ) && ! empty( $br['ic'] ) ) ? $br['ic'] : array() as $r ) {
		$p = (int) url_to_postid( $r['url'] );
		if ( $p ) { $o[ $p ] = true; }
	}
	return $o;
}

/** Bu sayfaya bağ veren sayfalar: pid => 'denetim' | 'rankmath'. */
function gbc_silo_gelen( $pid ) {
	global $wpdb;
	$o = array();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gbc_giden' AND meta_value LIKE %s",
		'%,' . (int) $pid . ',%' ) ) as $p ) {
		if ( (int) $p !== (int) $pid ) { $o[ (int) $p ] = 'denetim'; }
	}
	if ( function_exists( 'gbc_env_gelen' ) ) {
		foreach ( (array) gbc_env_gelen( $pid, 300 ) as $r ) {
			$p = (int) $r['post_id'];
			if ( $p && $p !== (int) $pid && ! isset( $o[ $p ] ) ) { $o[ $p ] = 'rankmath'; }
		}
	}
	return $o;
}

/** Karşı sayfanın giden bağları ölçüldü mü (denetlendi mi). */
function gbc_silo_olculdu( $pid ) {
	return '' !== (string) get_post_meta( (int) $pid, '_gbc_giden', true );
}

/** Plan satırının sitedeki sayfası (hafif: önce önbellek, sonra yol karşılaştırması, en son url_to_postid). */
function gbc_silo_satir_pid( $s ) {
	if ( isset( $s['e_pid'] ) ) { return (int) $s['e_pid']; }
	if ( ! empty( $s['pid'] ) && get_post_status( $s['pid'] ) ) { return (int) $s['pid']; }
	foreach ( array( 'mevcut', 'hedef' ) as $a ) {
		if ( '' !== $s[ $a ] ) { $x = (int) url_to_postid( $s[ $a ] ); if ( $x ) { return $x; } }
	}
	return 0;
}

/** Bu sayfanın plan çevresi: kendisi, üst, alt, yatay. */
function gbc_silo_cevre( $pid ) {
	$pa = get_transient( 'gbc_plan_analiz' );
	$satirlar = ( is_array( $pa ) && ! empty( $pa['satir'] ) ) ? $pa['satir'] : ( function_exists( 'gbc_plan_oku' ) ? gbc_plan_oku()['satir'] : array() );
	if ( ! $satirlar ) { return null; }

	/* Kendini bul: pid, sonra adres (veritabanına sormadan). */
	$yol = function_exists( 'gbc_plan_yol' ) ? gbc_plan_yol( get_permalink( $pid ) ) : '';
	$ben = null;
	foreach ( $satirlar as $s ) {
		if ( ( isset( $s['e_pid'] ) && (int) $s['e_pid'] === (int) $pid ) || ( ! isset( $s['e_pid'] ) && (int) $s['pid'] === (int) $pid ) ) { $ben = $s; break; }
	}
	if ( ! $ben ) {
		foreach ( $satirlar as $s ) {
			if ( ( '' !== $s['mevcut'] && gbc_plan_yol( $s['mevcut'] ) === $yol ) || ( '' !== $s['hedef'] && gbc_plan_yol( $s['hedef'] ) === $yol ) ) { $ben = $s; break; }
		}
	}
	if ( ! $ben ) { return null; }

	$hub_mi = (bool) preg_match( '/ana hub/iu', $ben['ad'] );
	$bolge = array_values( array_filter( $satirlar, static function ( $s ) use ( $ben ) { return $s['bolge'] === $ben['bolge'] && $s['no'] !== $ben['no']; } ) );
	$hub = null;
	foreach ( $bolge as $s ) { if ( '' === $s['ust'] && preg_match( '/ana hub/iu', $s['ad'] ) ) { $hub = $s; break; } }

	$ust = null; $alt = array(); $yatay = array(); $yardimci = array();
	if ( $hub_mi ) {
		foreach ( $bolge as $s ) {
			if ( '' === $s['ust'] ) { $alt[] = $s; }
			elseif ( $s['ust'] === $ben['no'] ) { $alt[] = $s; }
		}
	} elseif ( '' !== $ben['ust'] ) {
		foreach ( $bolge as $s ) {
			if ( $s['no'] === $ben['ust'] ) { $ust = $s; }
			elseif ( $s['ust'] === $ben['ust'] ) { $yatay[] = $s; }
		}
	} else {
		$ust = $hub;
		foreach ( $bolge as $s ) {
			if ( $s['ust'] === $ben['no'] ) { $alt[] = $s; }
			elseif ( '' === $s['ust'] && ( ! $hub || $s['no'] !== $hub['no'] ) ) { $yatay[] = $s; }
			elseif ( $hub && $s['ust'] === $hub['no'] ) { $yardimci[] = $s; }
		}
	}
	$coz = static function ( $l ) { foreach ( $l as $i => $s ) { $l[ $i ]['_pid'] = gbc_silo_satir_pid( $s ); } return $l; };
	if ( $ust ) { $ust['_pid'] = gbc_silo_satir_pid( $ust ); }
	return array( 'ben' => $ben, 'hub_mi' => $hub_mi, 'ust' => $ust, 'alt' => $coz( $alt ), 'yatay' => $coz( $yatay ), 'yardimci' => $coz( $yardimci ) );
}

/**
 * Silo analizi: düğümler, bağ durumları, puan maddeleri, yapılacaklar.
 * @return array|null null = sayfa plan tablosunda yok.
 */
function gbc_silo_analiz( $pid, $br ) {
	$c = gbc_silo_cevre( $pid );
	$giden = gbc_silo_giden( $br );
	$gelen = gbc_silo_gelen( $pid );
	$r = array( 'giden_n' => count( $giden ), 'gelen_n' => count( $gelen ), 'gelen' => $gelen, 'cevre' => $c, 'madde' => array(), 'is' => array() );
	if ( ! $c ) { return $r; }

	$dugum = static function ( $s ) use ( $giden, $gelen ) {
		$p = (int) $s['_pid'];
		$yayin = $p && 'publish' === get_post_status( $p );
		return array( 's' => $s, 'pid' => $p, 'yayin' => $yayin,
			'veriyor' => $p && isset( $giden[ $p ] ),                                   /* bu → o */
			'aliyor'  => $p ? ( isset( $gelen[ $p ] ) ? true : ( gbc_silo_olculdu( $p ) ? false : null ) ) : null ); /* o → bu; null = ölçülmedi */
	};
	$r['ust'] = $c['ust'] ? $dugum( $c['ust'] ) : null;
	foreach ( array( 'alt', 'yatay', 'yardimci' ) as $k ) { $r[ $k ] = array_map( $dugum, $c[ $k ] ); }

	$ad = static function ( $x ) { return $x['s']['ad']; };
	/* Yukarı */
	if ( $r['ust'] ) {
		if ( $r['ust']['yayin'] ) {
			$r['madde'][] = array( 'Üst sayfaya bağ veriyor (' . $ad( $r['ust'] ) . ')', $r['ust']['veriyor'] ? 4 : 0, 4 );
			if ( ! $r['ust']['veriyor'] ) { $r['is'][] = array( 1, 'Silo: üst sayfaya link ver → "' . $ad( $r['ust'] ) . '" (' . get_permalink( $r['ust']['pid'] ) . ')' ); }
			if ( null !== $r['ust']['aliyor'] ) {
				$r['madde'][] = array( 'Üst sayfadan bağ alıyor', $r['ust']['aliyor'] ? 2 : 0, 2 );
				if ( ! $r['ust']['aliyor'] ) { $r['is'][] = array( 2, 'Silo: "' . $ad( $r['ust'] ) . '" sayfasından bu sayfaya link ver.' ); }
			}
		} else {
			$r['is'][] = array( 3, 'Silo: üst sayfa "' . $ad( $r['ust'] ) . '" henüz açılmadı; açılınca iki yönlü bağlanmalı.' );
		}
	}
	/* Aşağı */
	$alt_var = array_filter( $r['alt'], static function ( $x ) { return $x['yayin']; } );
	if ( $alt_var ) {
		$v = count( array_filter( $alt_var, static function ( $x ) { return $x['veriyor']; } ) );
		$r['madde'][] = array( 'Alt sayfaların hepsine bağ veriyor (' . $v . '/' . count( $alt_var ) . ')', 2 * $v / count( $alt_var ), 2 );
		foreach ( $alt_var as $x ) {
			if ( ! $x['veriyor'] ) { $r['is'][] = array( 2, 'Silo: alt sayfaya link ver → "' . $ad( $x ) . '"' ); }
			if ( false === $x['aliyor'] ) { $r['is'][] = array( 3, 'Silo: "' . $ad( $x ) . '" sayfası bu sayfaya geri link vermiyor.' ); }
		}
	}
	/* Yatay */
	$yatay_var = array_filter( array_merge( $r['yatay'], $r['yardimci'] ), static function ( $x ) { return $x['yayin']; } );
	if ( $yatay_var ) {
		$v = count( array_filter( $yatay_var, static function ( $x ) { return $x['veriyor']; } ) );
		$hedef = min( 2, count( $yatay_var ) );
		$r['madde'][] = array( 'Aynı bölgedeki en az ' . $hedef . ' sayfaya yatay bağ (' . $v . ')', 2 * min( $v, $hedef ) / $hedef, 2 );
		if ( $v < $hedef ) {
			$oneri = array_slice( array_filter( $yatay_var, static function ( $x ) { return ! $x['veriyor']; } ), 0, 3 );
			$r['is'][] = array( 3, 'Silo: aynı bölgedeki sayfalara yatay link ver → ' . implode( ', ', array_map( static function ( $x ) { return '"' . $x['s']['ad'] . '"'; }, $oneri ) ) );
		}
	}
	return $r;
}

/** Skor bölümü (gbc_sd_skor bunu kullanır). */
function gbc_silo_skor_grubu( $si ) {
	if ( ! is_array( $si ) || empty( $si['cevre'] ) ) {
		return array( 'ad' => 'Silo', 'id' => 'gbc-k-silo', 'yok' => true, 'maddeler' => array( array( 'ad' => 'Sayfa plan tablosunda yok', 'puan' => 0, 'max' => 0, 'not' => 'puana katılmadı' ) ) );
	}
	$m = array();
	foreach ( $si['madde'] as $x ) { $m[] = array( 'ad' => $x[0], 'puan' => (int) round( $x[1] ), 'max' => (int) $x[2], 'not' => '' ); }
	if ( ! $m ) { return array( 'ad' => 'Silo', 'id' => 'gbc-k-silo', 'yok' => true, 'maddeler' => array( array( 'ad' => 'Bağlanacak yayında sayfa yok', 'puan' => 0, 'max' => 0, 'not' => 'puana katılmadı' ) ) ); }
	return array( 'ad' => 'Silo', 'id' => 'gbc-k-silo', 'maddeler' => $m );
}

/* ---------------- Ekran ---------------- */
function gbc_silo_ok( $v, $yon ) {
	if ( null === $v ) { return '<span title="karşı sayfa henüz denetlenmedi" style="color:#9AA0A6">' . ( '→' === $yon ? '→' : '←' ) . '?</span>'; }
	return '<span style="font-weight:700;color:' . ( $v ? '#1A7F37' : '#B32D2E' ) . '" title="' . esc_attr( ( '→' === $yon ? 'bu sayfa ona link ' : 'o bu sayfaya link ' ) . ( $v ? 'veriyor' : 'vermiyor' ) ) . '">' . $yon . ( $v ? '✓' : '✗' ) . '</span>';
}
function gbc_silo_dugum_html( $x, $ic = 0 ) {
	$s = $x['s'];
	$durum = $x['yayin'] ? '' : ' <span style="font-size:11px;padding:1px 7px;border-radius:9px;background:#FBE7E7;color:#B32D2E">' . ( $x['pid'] ? 'taslak' : 'açılacak' ) . '</span>';
	$ad = $x['pid'] ? '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $x['pid'] ) ) . '#gbc-silo">' . esc_html( $s['ad'] ) . '</a>' : esc_html( $s['ad'] );
	$ok = $x['yayin'] ? ' <span style="margin-left:6px">' . gbc_silo_ok( $x['veriyor'], '→' ) . ' ' . gbc_silo_ok( $x['aliyor'], '←' ) . '</span>' : '';
	return '<div style="padding:4px 0 4px ' . ( 18 * $ic ) . 'px;border-left:' . ( $ic ? '2px solid #E3E5E9' : '0' ) . ';margin-left:' . ( $ic ? '8px' : '0' ) . '">' . $ad . $durum . $ok . '</div>';
}

function gbc_silo_ekran( $pid, $si ) {
	echo '<div id="gbc-silo" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;margin:16px 0;max-width:1400px">';
	echo '<h2 style="margin:0 0 4px">Silo ağacı</h2><div style="color:#5C6470;font-size:12.5px;margin-bottom:10px">→✓ bu sayfa ona link veriyor · ←✓ o bu sayfaya link veriyor · ✗ yok · ? karşı sayfa henüz denetlenmedi (toplama saat başı ilerliyor). '
		. 'Bu sayfa ' . (int) $si['giden_n'] . ' iç sayfaya link veriyor, ' . (int) $si['gelen_n'] . ' sayfadan link alıyor (ölçülenler).</div>';
	$c = $si['cevre'];
	if ( ! $c ) {
		echo '<p><em>Bu sayfa plan tablosunda yok; hiyerarşisi bilinmiyor. Envanter → Plan → "Sitede var, tabloda yok" listesinde görünür. Tabloya eklenince ağaç burada çıkar.</em></p>';
	} else {
		echo '<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px" class="gbc-silo-ikili">';
		echo '<style>@media (max-width:900px){.gbc-silo-ikili{grid-template-columns:1fr!important}}</style>';
		echo '<div><strong style="font-size:13.5px">Dikey (hiyerarşi)</strong><div style="margin-top:6px;font-size:13.5px">';
		if ( ! empty( $si['ust'] ) ) { echo gbc_silo_dugum_html( $si['ust'] ); }
		echo '<div style="padding:4px 0 4px ' . ( ! empty( $si['ust'] ) ? 18 : 0 ) . 'px;margin-left:' . ( ! empty( $si['ust'] ) ? 8 : 0 ) . 'px;border-left:' . ( ! empty( $si['ust'] ) ? '2px solid #E3E5E9' : '0' ) . '"><strong style="background:#17181A;color:#fff;padding:2px 9px;border-radius:9px">' . esc_html( $c['ben']['ad'] ) . '</strong> <span style="color:#5C6470;font-size:12px">bu sayfa</span></div>';
		foreach ( $si['alt'] as $x ) { echo gbc_silo_dugum_html( $x, ! empty( $si['ust'] ) ? 2 : 1 ); }
		if ( ! $si['alt'] ) { echo '<div style="color:#9AA0A6;font-size:12.5px;padding-left:' . ( ! empty( $si['ust'] ) ? 44 : 26 ) . 'px">alt sayfa yok</div>'; }
		echo '</div></div>';
		echo '<div><strong style="font-size:13.5px">Yatay (aynı bölge: ' . esc_html( $c['ben']['bolge'] ) . ')</strong><div style="margin-top:6px;font-size:13.5px">';
		if ( $si['yardimci'] ) { echo '<div style="color:#5C6470;font-size:12px;margin-top:2px">Bölge yardımcı sayfaları</div>'; foreach ( $si['yardimci'] as $x ) { echo gbc_silo_dugum_html( $x ); } }
		if ( $si['yatay'] ) {
			echo '<div style="color:#5C6470;font-size:12px;margin-top:6px">Kardeş sayfalar</div>';
			$yayinda = array_filter( $si['yatay'], static function ( $x ) { return $x['yayin']; } );
			$acilacak = count( $si['yatay'] ) - count( $yayinda );
			foreach ( $yayinda as $x ) { echo gbc_silo_dugum_html( $x ); }
			if ( $acilacak ) { echo '<div style="color:#9AA0A6;font-size:12.5px">+ ' . (int) $acilacak . ' sayfa henüz açılmadı</div>'; }
		}
		if ( ! $si['yardimci'] && ! $si['yatay'] ) { echo '<div style="color:#9AA0A6;font-size:12.5px">yatay sayfa yok</div>'; }
		echo '</div></div></div>';
	}
	/* Bu sayfaya link verenler (ölçülen) */
	if ( $si['gelen'] ) {
		echo '<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600">Bu sayfaya link veren sayfalar (' . count( $si['gelen'] ) . ')</summary><ul style="margin:6px 0 0 18px;list-style:disc">';
		foreach ( array_slice( $si['gelen'], 0, 60, true ) as $p => $kay ) {
			echo '<li>' . esc_html( get_the_title( $p ) ) . ' <span style="color:#9AA0A6;font-size:11px">' . ( 'rankmath' === $kay ? 'Rank Math' : 'denetim' ) . '</span></li>';
		}
		echo '</ul></details>';
	}
	echo '</div>';
}
