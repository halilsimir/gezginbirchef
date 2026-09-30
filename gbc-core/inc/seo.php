<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — SEO Komuta.
 *
 * VERİ KAYNAĞI: Rank Math'in kendi Search Console tablosu. Site zaten
 * Google'a bağlı ve 34 MB geçmiş verisi burada duruyor; dışarıdan API
 * çağrısı yapmaya gerek yok, kota da harcanmıyor.
 *
 * İKİ KURAL — ikisi de ölçümle öğrenildi:
 *   1) Web araması ile GÖRSEL araması asla toplanmaz. Görselin tıklanma
 *      oranı web aramasının onda biri kadardır; ikisi karışırsa iyi bir
 *      sayfa kötü görünür ve yanlış iş yaptırır. Rank Math tablosu arama
 *      türünü ayrı tutuyorsa ayrılır; tutmuyorsa ekran bunu AÇIKÇA yazar
 *      ve "CTR düşük" hükmü verilmez.
 *   2) Ölçülmeyen sayı uydurulmaz. Veri yoksa kutu boş kalır ve neden
 *      boş olduğu yazılır.
 *
 * HIZ: bu dosya ziyaretçi isteğinde hiç yüklenmez (gbc-core.php yalnız
 * is_admin() / DOING_CRON durumunda çağırır).
 */

define( 'GBC_SEO_SON', 'gbc_seo_son' );

/* ============================================================
   VERİ KATMANI — Rank Math Search Console tablosu
   ============================================================ */

/** Tabloyu ve sütunlarını bulur. Sütun adlarını tahmin etmez, okur. */
function gbc_seo_kaynak() {
	static $bellek = null;
	if ( null !== $bellek ) { return $bellek; }

	global $wpdb;
	$aday = $wpdb->prefix . 'rank_math_analytics_gsc';
	$var  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $aday ) );
	if ( ! $var ) {
		$bellek = array( 'var' => false, 'not' => sprintf( __( '%s tablosu bulunamadı. Rank Math Analytics modülü açık mı?', 'gbc-core' ), $aday ) );
		return $bellek;
	}

	$sutunlar = array();
	foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$aday}`" ) as $s ) {
		$sutunlar[ strtolower( $s->Field ) ] = true;
	}

	$gerek = array( 'page', 'query', 'clicks', 'impressions', 'position', 'created' );
	$eksik = array();
	foreach ( $gerek as $g ) { if ( ! isset( $sutunlar[ $g ] ) ) { $eksik[] = $g; } }
	if ( $eksik ) {
		$bellek = array( 'var' => false, 'not' => sprintf( __( 'Tablo var ama şu sütunlar yok: %s', 'gbc-core' ), implode( ', ', $eksik ) ) );
		return $bellek;
	}

	$bellek = array(
		'var'    => true,
		'tablo'  => $aday,
		'sutun'  => $sutunlar,
		/* Arama turu sutunu Rank Math surumune gore degisir; varsa kullanilir. */
		'tur'    => isset( $sutunlar['search_type'] ) ? 'search_type' : ( isset( $sutunlar['type'] ) ? 'type' : '' ),
	);
	return $bellek;
}

/** Tarih sınırı — son N gün. */
function gbc_seo_sinir( $gun ) {
	return gmdate( 'Y-m-d', time() - ( (int) $gun * DAY_IN_SECONDS ) );
}

/** Arama türü koşulu: web dışı satırlar dışarıda bırakılır (sütun varsa). */
function gbc_seo_tur_kosulu( $k ) {
	if ( empty( $k['tur'] ) ) { return ''; }
	return " AND ( `{$k['tur']}` = 'web' OR `{$k['tur']}` = '' OR `{$k['tur']}` IS NULL ) ";
}

/** Genel özet. */
function gbc_seo_ozet( $gun = 90 ) {
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return null; }
	global $wpdb;

	$r = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(clicks) tik, SUM(impressions) gos, AVG(position) sira,
		        COUNT(DISTINCT query) kelime, COUNT(DISTINCT page) sayfa
		   FROM `{$k['tablo']}`
		  WHERE created >= %s" . gbc_seo_tur_kosulu( $k ),
		gbc_seo_sinir( $gun )
	), ARRAY_A );

	if ( ! $r ) { return null; }
	$r['ctr'] = ( $r['gos'] > 0 ) ? ( $r['tik'] / $r['gos'] * 100 ) : 0;
	return $r;
}

/** Sıra aralıklarına göre kelime dağılımı. */
function gbc_seo_dagilim( $gun = 90 ) {
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return array(); }
	global $wpdb;

	$satirlar = $wpdb->get_results( $wpdb->prepare(
		"SELECT query, AVG(position) p, SUM(impressions) g
		   FROM `{$k['tablo']}`
		  WHERE created >= %s" . gbc_seo_tur_kosulu( $k ) . "
		  GROUP BY query",
		gbc_seo_sinir( $gun )
	), ARRAY_A );

	$d = array( '1-3' => 0, '4-10' => 0, '11-20' => 0, '20+' => 0 );
	foreach ( (array) $satirlar as $s ) {
		$p = (float) $s['p'];
		if ( $p <= 3 )       { $d['1-3']++; }
		elseif ( $p <= 10 )  { $d['4-10']++; }
		elseif ( $p <= 20 )  { $d['11-20']++; }
		else                 { $d['20+']++; }
	}
	return $d;
}

/** Bir sayfanın bütün kelimeleri. */
function gbc_seo_sayfa_kelime( $url, $gun = 90, $limit = 60 ) {
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return array(); }
	global $wpdb;

	/* Rank Math sayfayi bazen tam adres, bazen yol olarak yazar. Ikisini de ara. */
	$yol = wp_parse_url( $url, PHP_URL_PATH );
	$yol = $yol ? $yol : $url;

	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT query, SUM(clicks) tik, SUM(impressions) gos, AVG(position) sira
		   FROM `{$k['tablo']}`
		  WHERE created >= %s AND ( page = %s OR page = %s )" . gbc_seo_tur_kosulu( $k ) . "
		  GROUP BY query
		  ORDER BY tik DESC, gos DESC
		  LIMIT %d",
		gbc_seo_sinir( $gun ), $url, $yol, $limit
	), ARRAY_A );
}

/** Sıraya göre beklenen tıklanma oranı (%). Sektör ortalaması, kaba. */
function gbc_seo_beklenen_ctr( $sira ) {
	$t = array( 1 => 27.0, 2 => 15.0, 3 => 11.0, 4 => 8.0, 5 => 7.0, 6 => 5.0, 7 => 4.0, 8 => 3.2, 9 => 2.8, 10 => 2.4 );
	$s = (int) round( $sira );
	if ( $s < 1 ) { $s = 1; }
	return isset( $t[ $s ] ) ? $t[ $s ] : 1.5;
}

/* ============================================================
   FIRSATLAR — hepsi ölçülen veriden üretilir
   ============================================================ */
function gbc_seo_firsatlar( $gun = 90 ) {
	$k = gbc_seo_kaynak();
	if ( empty( $k['var'] ) ) { return array(); }
	global $wpdb;

	$satirlar = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT query, page, SUM(clicks) tik, SUM(impressions) gos, AVG(position) sira
		   FROM `{$k['tablo']}`
		  WHERE created >= %s" . gbc_seo_tur_kosulu( $k ) . "
		  GROUP BY query, page
		 HAVING gos >= 50
		  ORDER BY gos DESC
		  LIMIT 4000",
		gbc_seo_sinir( $gun )
	), ARRAY_A );

	$firsat   = array();
	$kelimede = array();
	$yil_simdi = (int) gmdate( 'Y' );

	foreach ( $satirlar as $s ) {
		$sira = (float) $s['sira'];
		$gos  = (int) $s['gos'];
		$tik  = (int) $s['tik'];
		$ctr  = $gos > 0 ? ( $tik / $gos * 100 ) : 0;

		/* 1) CTR boslugu — ilk 10'da, cok gosterim, beklenenin dortte biri alti.
		      Arama turu ayrilamiyorsa bu kural CALISTIRILMAZ: gosterimlerin
		      gorsel aramasindan gelme ihtimali hukmu yanlis yapar. */
		if ( ! empty( $k['tur'] ) && $sira <= 10 && $gos >= 300 ) {
			$bek = gbc_seo_beklenen_ctr( $sira );
			if ( $ctr < $bek / 4 ) {
				$firsat[] = array(
					'tur'   => 'ctr',
					'puan'  => $gos * ( $bek - $ctr ) / 100,
					'kelime' => $s['query'],
					'sayfa' => $s['page'],
					'sira'  => $sira,
					'gos'   => $gos,
					'tik'   => $tik,
					'ctr'   => $ctr,
					'not'   => sprintf( __( '%1$d. sırada beklenen tıklanma %%%2$s, gerçekleşen %%%3$s. Başlık ve açıklama yeniden yazılmalı.', 'gbc-core' ),
						(int) round( $sira ), number_format_i18n( $bek, 1 ), number_format_i18n( $ctr, 2 ) ),
				);
			}
		}

		/* 2) Esik — 11-20 arasi, ilk sayfaya yakin. */
		if ( $sira > 10 && $sira <= 20 && $gos >= 100 ) {
			$firsat[] = array(
				'tur'   => 'esik',
				'puan'  => $gos * 0.8,
				'kelime' => $s['query'],
				'sayfa' => $s['page'],
				'sira'  => $sira,
				'gos'   => $gos,
				'tik'   => $tik,
				'ctr'   => $ctr,
				'not'   => __( 'İlk sayfanın eşiğinde. İçeriği genişletmek ve iç bağlantı vermek en ucuz kazanç.', 'gbc-core' ),
			);
		}

		/* 3) Yil ifadesi — aramada gecen yil, icerikte eski kalmis olabilir. */
		if ( preg_match( '/\b(20\d\d)\b/', (string) $s['query'], $y ) ) {
			$yil = (int) $y[1];
			if ( $yil >= $yil_simdi ) {
				$firsat[] = array(
					'tur'   => 'yil',
					'puan'  => $gos * 0.5,
					'kelime' => $s['query'],
					'sayfa' => $s['page'],
					'sira'  => $sira,
					'gos'   => $gos,
					'tik'   => $tik,
					'ctr'   => $ctr,
					'not'   => sprintf( __( 'Arama %d yılını içeriyor. Sayfadaki yıl ifadesi güncel mi?', 'gbc-core' ), $yil ),
				);
			}
		}

		/* 4) Kanibalizasyon icin topla. */
		$q = (string) $s['query'];
		if ( ! isset( $kelimede[ $q ] ) ) { $kelimede[ $q ] = array(); }
		$kelimede[ $q ][] = $s;
	}

	foreach ( $kelimede as $q => $liste ) {
		if ( count( $liste ) < 2 ) { continue; }
		$toplam = 0;
		foreach ( $liste as $l ) { $toplam += (int) $l['gos']; }
		if ( $toplam < 200 ) { continue; }
		$firsat[] = array(
			'tur'   => 'kanibal',
			'puan'  => $toplam * 0.7,
			'kelime' => $q,
			'sayfa' => $liste[0]['page'],
			'sira'  => (float) $liste[0]['sira'],
			'gos'   => $toplam,
			'tik'   => 0,
			'ctr'   => 0,
			'not'   => sprintf( __( 'Aynı arama %d ayrı sayfadan çıkıyor. Güç bölünüyor; biri sahip olmalı, diğeri ona bağlanmalı.', 'gbc-core' ), count( $liste ) ),
		);
	}

	usort( $firsat, static function ( $a, $b ) {
		return ( $b['puan'] == $a['puan'] ) ? 0 : ( ( $b['puan'] < $a['puan'] ) ? -1 : 1 );
	} );

	return array_slice( $firsat, 0, 120 );
}

/* ============================================================
   ENVANTER — silo bazında sayım
   ============================================================ */
function gbc_seo_envanter() {
	$sonuc = array(
		'toplam' => 0,
		'taslak' => 0,
		'gezi'   => 0,
		'yemek'  => 0,
		'silo'   => array(),
	);

	$say = wp_count_posts( 'post' );
	$sonuc['toplam'] = isset( $say->publish ) ? (int) $say->publish : 0;
	$sonuc['taslak'] = isset( $say->draft ) ? (int) $say->draft : 0;

	$gezi_kok  = get_term_by( 'slug', 'gezi-rehberi', 'category' );
	$yemek_kok = get_term_by( 'slug', 'yemek-tarifleri', 'category' );

	foreach ( (array) get_categories( array( 'hide_empty' => false ) ) as $k ) {
		$sonuc['silo'][] = array(
			'ad'    => $k->name,
			'slug'  => $k->slug,
			'adet'  => (int) $k->count,
			'ust'   => (int) $k->parent,
			'id'    => (int) $k->term_id,
		);
		if ( $gezi_kok && ( $k->term_id === $gezi_kok->term_id || $k->parent === $gezi_kok->term_id ) ) {
			$sonuc['gezi'] += (int) $k->count;
		}
		if ( $yemek_kok && ( $k->term_id === $yemek_kok->term_id || $k->parent === $yemek_kok->term_id ) ) {
			$sonuc['yemek'] += (int) $k->count;
		}
	}

	return $sonuc;
}

/* ============================================================
   İÇERİK DENETİMİ — canlı sayfa indirilip ölçülür
   ============================================================ */
function gbc_seo_ortaklik_alanlari() {
	return array( 'booking.com', 'getyourguide.com', 'tp.media', 'travelpayouts', 'ferryhopper.com',
		'omio.', 'discovercars.com', 'yesim.app', 'skyscanner', 'hotellook', 'aviasales' );
}

/** Tek sayfanın teknik denetimi. Canlı HTML üzerinden, tahminsiz. */
function gbc_seo_icerik_denetim( $post_id ) {
	$url = get_permalink( (int) $post_id );
	if ( ! $url ) { return array( 'hata' => __( 'Adres bulunamadı.', 'gbc-core' ) ); }

	$c = wp_remote_get( add_query_arg( 'gbc_seo', time(), $url ), array(
		'timeout'   => 25,
		'sslverify' => false,
		'headers'   => array( 'Cache-Control' => 'no-cache', 'X-LSCACHE' => 'no-cache' ),
	) );
	if ( is_wp_error( $c ) ) { return array( 'hata' => $c->get_error_message() ); }

	$html = (string) wp_remote_retrieve_body( $c );
	$d = array( 'url' => $url, 'kod' => (int) wp_remote_retrieve_response_code( $c ), 'bayt' => strlen( $html ) );

	/* Baslik agaci */
	$d['basliklar'] = array();
	if ( preg_match_all( '/<(h[1-4])\b[^>]*>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$metin = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $x[2] ) ) );
			if ( '' === $metin ) { continue; }
			$d['basliklar'][] = array( 'tip' => strtolower( $x[1] ), 'metin' => $metin );
		}
	}
	$d['h1_adet'] = 0;
	foreach ( $d['basliklar'] as $b ) { if ( 'h1' === $b['tip'] ) { $d['h1_adet']++; } }

	/* Atlama: h2'den h4'e gecis */
	$d['atlama'] = 0;
	$onceki = 1;
	foreach ( $d['basliklar'] as $b ) {
		$sev = (int) substr( $b['tip'], 1 );
		if ( $sev > $onceki + 1 ) { $d['atlama']++; }
		$onceki = $sev;
	}

	/* Gorseller ve alt metni.
	   29 Eyl 2026: eskiden yalniz SAYI veriliyordu ("1 alt metni eksik").
	   Hangi gorsel oldugunu bulmak icin sayfayi acip tek tek bakmak
	   gerekiyordu. Artik adres de tutuluyor.

	   LiteSpeed tembel yukleme src'yi data-src'ye tasiyor; ikisine de
	   bakiliyor, yoksa "adres okunamadi" yerine bos dizi doner. */
	$d['gorsel'] = 0; $d['alt_yok'] = 0; $d['alt_eksik'] = array();
	if ( preg_match_all( '/<img\b[^>]*>/i', $html, $mi ) ) {
		foreach ( $mi[0] as $img ) {
			$d['gorsel']++;
			if ( preg_match( '/\balt\s*=\s*["\'][^"\']+["\']/i', $img ) ) { continue; }
			$d['alt_yok']++;
			$kaynak = '';
			if ( preg_match( '/\b(?:data-src|data-lazyloaded-src|src)\s*=\s*["\']([^"\']+)["\']/i', $img, $mk ) ) {
				$kaynak = $mk[1];
			}
			if ( count( $d['alt_eksik'] ) < 12 ) {
				/* 29 Eyl 2026: gömülü data: URI'ler (yer tutucu SVG'ler)
				   base64 gövdesini ekrana döküyordu. Adları kısaltılıyor. */
				$gomulu = ( 0 === stripos( $kaynak, 'data:' ) );
				$d['alt_eksik'][] = array(
					'src'    => $gomulu ? '' : $kaynak,
					'ad'     => $gomulu
						? __( 'gömülü yer tutucu görsel', 'gbc-core' )
						: ( $kaynak ? basename( (string) wp_parse_url( $kaynak, PHP_URL_PATH ) ) : '' ),
					'gomulu' => $gomulu,
					'bos'    => (bool) preg_match( '/\balt\s*=\s*["\']\s*["\']/i', $img ),
				);
			}
		}
	}

	/* Meta */
	$d['baslik_etiketi'] = '';
	if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $mt ) ) {
		$d['baslik_etiketi'] = trim( wp_strip_all_tags( $mt[1] ) );
	}
	$d['aciklama'] = '';
	if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $md ) ) {
		$d['aciklama'] = trim( $md[1] );
	}

	/* Sema */
	$d['sema'] = array();
	if ( preg_match_all( '/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $ms ) ) {
		foreach ( $ms[1] as $blok ) {
			$j = json_decode( trim( $blok ), true );
			if ( ! is_array( $j ) ) { continue; }
			$dugumler = isset( $j['@graph'] ) && is_array( $j['@graph'] ) ? $j['@graph'] : array( $j );
			foreach ( $dugumler as $dg ) {
				if ( empty( $dg['@type'] ) ) { continue; }
				foreach ( (array) $dg['@type'] as $t ) { $d['sema'][ $t ] = true; }
			}
		}
	}
	$d['sema'] = array_keys( $d['sema'] );

	/* Yil ifadesi */
	$d['yillar'] = array();
	$metin = wp_strip_all_tags( $html );
	if ( preg_match_all( '/\b(20\d\d)\b/', $metin, $my ) ) {
		foreach ( array_count_values( $my[1] ) as $yil => $adet ) {
			$d['yillar'][ (int) $yil ] = (int) $adet;
		}
		krsort( $d['yillar'] );
	}

	/* Baglantilar */
	$ev = wp_parse_url( home_url(), PHP_URL_HOST );
	$d['ic'] = 0; $d['dis'] = 0; $d['ortaklik'] = array(); $d['dis_liste'] = array();
	$alanlar = gbc_seo_ortaklik_alanlari();
	if ( preg_match_all( '/<a\b([^>]*)href=["\']([^"\']+)["\']([^>]*)>/i', $html, $ma, PREG_SET_ORDER ) ) {
		$gorulen = array();
		foreach ( $ma as $a ) {
			$adres = $a[2];
			if ( 0 === strpos( $adres, '#' ) || 0 === strpos( $adres, 'mailto:' ) ) { continue; }
			$h = wp_parse_url( $adres, PHP_URL_HOST );
			if ( ! $h || $h === $ev ) { $d['ic']++; continue; }
			$d['dis']++;
			$nitelik = $a[1] . $a[3];
			$ort = '';
			foreach ( $alanlar as $al ) {
				if ( false !== stripos( $h . $adres, $al ) ) { $ort = $al; break; }
			}
			if ( $ort ) {
				if ( ! isset( $d['ortaklik'][ $ort ] ) ) { $d['ortaklik'][ $ort ] = 0; }
				$d['ortaklik'][ $ort ]++;
			}
			if ( isset( $gorulen[ $adres ] ) || count( $d['dis_liste'] ) >= 40 ) { continue; }
			$gorulen[ $adres ] = true;
			$d['dis_liste'][] = array(
				'url'  => $adres,
				'host' => $h,
				'ort'  => $ort,
				'rel'  => preg_match( '/\brel\s*=\s*["\']([^"\']*)["\']/i', $nitelik, $mr ) ? $mr[1] : '',
			);
		}
	}

	$d['ham'] = $html;
	return $d;
}

/**
 * ŞEMA DENETİMİ — beklenen ne, basılan ne?
 *
 * 29 Eylül 2026'da eklendi. Ekran o güne kadar yalnız BASILAN şemaları
 * listeliyordu; eksik olan hiç fark edilmiyordu.
 *
 * Kural yazarken tek tehlike sahte alarm. Bu yüzden beklenti İKİYE
 * ayrıldı:
 *   - TEMEL: her içerik sayfasında olması gereken düğümler. Bunlar
 *     modules/20-sema-cekirdek.php ve 21-sema-motoru.php tarafından
 *     zaten her sayfaya basılıyor; yoksa gerçekten sorun var.
 *   - KOŞULLU: yalnız sayfada tetikleyicisi varsa beklenir. Videosu
 *     olmayan sayfada VideoObject aramak sahte alarm olurdu.
 *
 * @param array $d gbc_seo_icerik_denetim() çıktısı.
 * @return array array( eksik, beklenen, fazla )
 */
function gbc_seo_sema_denetim( $d ) {
	$html = isset( $d['ham'] ) ? (string) $d['ham'] : '';
	$var  = array_map( 'strval', (array) ( isset( $d['sema'] ) ? $d['sema'] : array() ) );

	$beklenen = array(
		'WebSite'        => __( 'site geneli — Şema Çekirdeği basar', 'gbc-core' ),
		'Organization'   => __( 'site geneli — Şema Çekirdeği basar', 'gbc-core' ),
		'WebPage'        => __( 'her sayfa — Şema Motoru basar', 'gbc-core' ),
		'BreadcrumbList' => __( 'ana sayfa hariç her yerde', 'gbc-core' ),
		'Article'        => __( 'her yazı', 'gbc-core' ),
	);

	/* Koşullu düğümler: yalnız tetikleyicisi olan sayfada beklenir. */
	$kosul = array(
		'VideoObject' => array(
			( false !== stripos( $html, 'youtube.com/watch' ) || false !== stripos( $html, 'youtu.be/' )
			  || false !== stripos( $html, 'gbc-yt' ) || false !== stripos( $html, 'youtube-nocookie' ) ),
			__( 'sayfada video var', 'gbc-core' ),
		),
		'FAQPage' => array(
			( false !== stripos( $html, 'Sıkça Sorulan' ) || false !== stripos( $html, 'Sikca Sorulan' )
			  || false !== stripos( $html, 'gbc-sss' ) || false !== stripos( $html, 'faq' ) ),
			__( 'sayfada SSS bölümü var', 'gbc-core' ),
		),
		'ImageObject' => array(
			( false !== stripos( $html, 'gbc-gal' ) || false !== stripos( $html, 'wp-block-gallery' ) ),
			__( 'sayfada galeri var', 'gbc-core' ),
		),
		'TouristDestination' => array(
			( false !== strpos( $html, 'gz-full-wrapper' ) ),
			__( 'gezi rehberi şablonu — Şema Motoru basar', 'gbc-core' ),
		),
		'Recipe' => array(
			( false !== stripos( $html, 'tr-wrap' ) || false !== stripos( $html, 'tr-hero' ) ),
			__( 'tarif şablonu', 'gbc-core' ),
		),
	);
	foreach ( $kosul as $tip => $k ) {
		if ( $k[0] ) { $beklenen[ $tip ] = $k[1]; }
	}

	$eksik = array();
	foreach ( $beklenen as $tip => $sebep ) {
		if ( ! in_array( $tip, $var, true ) ) { $eksik[ $tip ] = $sebep; }
	}

	/* Beklenmeyen ama basılan düğümler — hata değil, bilgi. */
	$fazla = array_values( array_diff( $var, array_keys( $beklenen ) ) );

	return array( 'eksik' => $eksik, 'beklenen' => $beklenen, 'fazla' => $fazla );
}

/**
 * BAĞLANTI SAĞLIĞI HARİTASI — adres => kayıtlı sağlık sonucu.
 *
 * 29 Eylül 2026'da eklendi. Ortaklık bağlantı sağlığı taraması her gün
 * çalışıyor ve sonuçlar gbc_aff_saglik seçeneğinde duruyordu; ama Sayfa
 * Denetimi ekranı bunu hiç okumuyor, 40 bağlantının hepsine "bakılmadı"
 * diyordu. Veri elimizdeyken ekran boş konuşuyordu.
 *
 * Burada TEK İSTEK ATILMIYOR — yalnız kayıtlı sonuç okunuyor.
 *
 * @return array adres => array( durum, kod, not, zaman )
 */
function gbc_seo_saglik_haritasi() {
	if ( ! function_exists( 'gbc_ort_saglik' ) || ! function_exists( 'gbc_ort_satirlar' ) ) {
		return array();
	}
	$saglik = gbc_ort_saglik();
	if ( ! $saglik ) { return array(); }

	/* DİKKAT — 29 Eylül 2026'da canlıda yakalanan hata:
	   gbc_ort_satirlar() satırları ID İLE ANAHTARLANMIŞ döndürüyor;
	   satırın İÇİNDE 'id' alanı YOK (alanlar: etiket, program, url, ag,
	   blok, satir). İlk sürümde $satir['id'] aranıyordu, hep boş çıkıyor
	   ve her satır atlanıyordu — harita hiç dolmadı, ekran 40 bağlantıya
	   birden "bakılmadı" dedi. Test de geçmişti, çünkü testin sahte
	   verisi yanlış varsayımı taklit ediyordu. Artık anahtar döngüden
	   alınıyor ve test gerçek yapıyı kullanıyor. */
	$harita = array();
	foreach ( (array) gbc_ort_satirlar() as $id => $satir ) {
		if ( empty( $satir['url'] ) ) { continue; }
		$id = (string) $id;
		if ( empty( $saglik[ $id ] ) ) { continue; }
		$harita[ gbc_seo_adres_anahtar( $satir['url'] ) ] = $saglik[ $id ];
	}
	return $harita;
}

/**
 * Adres karşılaştırma anahtarı.
 *
 * 29 Eylül 2026'da ÖLÇÜLEREK eklendi: Sayfa Denetimi'ndeki 40 bağlantının
 * hiçbiri sağlık kaydıyla eşleşmedi. Sebep, sayfanın HAM kaynağında aynı
 * adresin iki farklı yazımla geçmesi — bir kısmında `&`, bir kısmında
 * `&amp;`. Defterdeki yazımla birebir karşılaştırma bu yüzden tutmuyordu.
 *
 * Burada ikisi de aynı anahtara indirgeniyor.
 */
function gbc_seo_adres_anahtar( $url ) {
	$u = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	return rtrim( $u, '/' );
}

/** Dış bağlantıları tek tek çağırır. Yavaş iştir, istenince çalışır. */
function gbc_seo_bag_kontrol( $liste, $sinir = 12 ) {
	$sonuc = array();
	$i = 0;
	foreach ( (array) $liste as $b ) {
		if ( $i++ >= $sinir ) { break; }
		$c = wp_remote_head( $b['url'], array( 'timeout' => 8, 'redirection' => 3, 'sslverify' => false ) );
		if ( is_wp_error( $c ) ) {
			$b['kod'] = 0;
			$b['hata'] = $c->get_error_message();
		} else {
			$b['kod'] = (int) wp_remote_retrieve_response_code( $c );
			/* Bazi sunucular HEAD'e 405 doner; o zaman GET ile bir daha bakilir. */
			if ( 405 === $b['kod'] || 501 === $b['kod'] ) {
				$c2 = wp_remote_get( $b['url'], array( 'timeout' => 10, 'redirection' => 3, 'sslverify' => false ) );
				if ( ! is_wp_error( $c2 ) ) { $b['kod'] = (int) wp_remote_retrieve_response_code( $c2 ); }
			}
		}
		$sonuc[] = $b;
	}
	return $sonuc;
}

/** Sayfa skoru — geçilen kontrol / toplam kontrol. */
function gbc_seo_skor( $d ) {
	if ( ! empty( $d['hata'] ) ) { return array( 'skor' => 0, 'gecen' => 0, 'toplam' => 0, 'maddeler' => array() );
	}
	$m = array();
	$m[] = array( 'ad' => __( 'Tek H1', 'gbc-core' ), 'ok' => ( 1 === (int) $d['h1_adet'] ) );
	$m[] = array( 'ad' => __( 'Başlık sırası atlamıyor', 'gbc-core' ), 'ok' => ( 0 === (int) $d['atlama'] ) );
	$m[] = array( 'ad' => __( 'En az 3 H2', 'gbc-core' ), 'ok' => ( count( array_filter( $d['basliklar'], static function ( $b ) { return 'h2' === $b['tip']; } ) ) >= 3 ) );
	$m[] = array( 'ad' => __( 'Bütün görsellerde alt metni', 'gbc-core' ), 'ok' => ( 0 === (int) $d['alt_yok'] ) );
	$m[] = array( 'ad' => __( 'Meta açıklama var', 'gbc-core' ), 'ok' => ( '' !== $d['aciklama'] ) );
	$m[] = array( 'ad' => __( 'Meta açıklama uzunluğu 120-165', 'gbc-core' ), 'ok' => ( mb_strlen( $d['aciklama'] ) >= 120 && mb_strlen( $d['aciklama'] ) <= 165 ) );
	$m[] = array( 'ad' => __( 'Başlık etiketi 30-62 karakter', 'gbc-core' ), 'ok' => ( mb_strlen( $d['baslik_etiketi'] ) >= 30 && mb_strlen( $d['baslik_etiketi'] ) <= 62 ) );
	$m[] = array( 'ad' => __( 'Şema basılıyor', 'gbc-core' ), 'ok' => ( ! empty( $d['sema'] ) ) );
	$m[] = array( 'ad' => __( 'En az 3 iç bağlantı', 'gbc-core' ), 'ok' => ( (int) $d['ic'] >= 3 ) );
	$m[] = array( 'ad' => __( 'Eski yıl ifadesi yok', 'gbc-core' ), 'ok' => gbc_seo_yil_temiz( $d ) );

	$gecen = 0;
	foreach ( $m as $x ) { if ( $x['ok'] ) { $gecen++; } }
	$toplam = count( $m );
	return array( 'skor' => $toplam ? (int) round( $gecen / $toplam * 100 ) : 0, 'gecen' => $gecen, 'toplam' => $toplam, 'maddeler' => $m );
}

function gbc_seo_yil_temiz( $d ) {
	$simdi = (int) gmdate( 'Y' );
	foreach ( (array) $d['yillar'] as $yil => $adet ) {
		if ( (int) $yil < $simdi ) { return false; }
	}
	return true;
}


/* ============================================================
   MOTORLAR — bu sayfada hangi GBC motoru çalışıyor, hangisi eksik
   ============================================================ */
function gbc_seo_motor_tanim() {
	return array(
		'sablon_gezi'  => array( 'ad' => __( 'Gezi şablonu', 'gbc-core' ),        'kod' => 22607, 'iz' => 'gz-full-wrapper' ),
		'sablon_liste' => array( 'ad' => __( 'Liste şablonu', 'gbc-core' ),       'kod' => 23108, 'iz' => '' ),
		'sablon_detay' => array( 'ad' => __( 'Detay şablonu', 'gbc-core' ),       'kod' => 23489, 'iz' => '' ),
		'sablon_rota'  => array( 'ad' => __( 'Rota şablonu', 'gbc-core' ),        'kod' => 23340, 'iz' => '' ),
		'sablon_tarif' => array( 'ad' => __( 'Tarif şablonu', 'gbc-core' ),       'kod' => 24751, 'iz' => '' ),
		'sablon_blog'  => array( 'ad' => __( 'Blog şablonu', 'gbc-core' ),        'kod' => 26922, 'iz' => '' ),
		'sablon_sozluk'=> array( 'ad' => __( 'Sözlük şablonu', 'gbc-core' ),      'kod' => 24156, 'iz' => '' ),
		'otel'         => array( 'ad' => __( 'Otel motoru', 'gbc-core' ),         'kod' => 30242, 'iz' => 'gbc-otel' ),
		'rota'         => array( 'ad' => __( 'Rota motoru', 'gbc-core' ),         'kod' => 30696, 'iz' => 'gbc-rota' ),
		'feribot'      => array( 'ad' => __( 'Feribot motoru', 'gbc-core' ),      'kod' => 30350, 'iz' => 'gbc-feribot', 'kisa' => 'gbc_feribot' ),
		'yunanistan'   => array( 'ad' => __( 'Yunanistan pillar', 'gbc-core' ),   'kod' => 30361, 'iz' => '', 'kisa' => 'gbc_yunanistan' ),
		'ortaklik'     => array( 'ad' => __( 'Ortaklık motoru', 'gbc-core' ),     'kod' => 31366, 'iz' => 'gbc-aff', 'kisa' => 'gbc_aff' ),
		'kur'          => array( 'ad' => __( 'Canlı kur', 'gbc-core' ),           'kod' => 30236, 'iz' => 'gbc-tl' ),
		'icindekiler'  => array( 'ad' => __( 'İçindekiler', 'gbc-core' ),         'kod' => 0,     'iz' => 'gbc-toc' ),
		'yazar'        => array( 'ad' => __( 'Yazar kutusu', 'gbc-core' ),        'kod' => 0,     'iz' => 'gz-author-box' ),
		'ilgili'       => array( 'ad' => __( 'İlgili yazılar', 'gbc-core' ),      'kod' => 0,     'iz' => 'gbc-related' ),
	);
}

/** Sayfada çalışan motorları bulur: hem kısa kod kaydından hem canlı HTML izinden. */
function gbc_seo_motorlar( $post_id, $html ) {
	$icerik = (string) get_post_field( 'post_content', (int) $post_id );
	$bulunan = array();

	foreach ( gbc_seo_motor_tanim() as $a => $m ) {
		$var = false;
		$nasil = array();

		if ( ! empty( $m['kod'] ) && false !== strpos( $icerik, '[wpcode id="' . $m['kod'] . '"' ) ) {
			$var = true; $nasil[] = __( 'kısa kod', 'gbc-core' );
		}
		if ( ! empty( $m['kisa'] ) && false !== strpos( $icerik, '[' . $m['kisa'] ) ) {
			$var = true; $nasil[] = __( 'kısa kod', 'gbc-core' );
		}
		if ( ! empty( $m['iz'] ) && false !== strpos( $html, $m['iz'] ) ) {
			$var = true; $nasil[] = __( 'sayfada görünüyor', 'gbc-core' );
		}

		if ( $var ) {
			$bulunan[ $a ] = array( 'ad' => $m['ad'], 'nasil' => implode( ' · ', array_unique( $nasil ) ) );
		}
	}

	/* Hangi sablonda hangi motor beklenir — eksikse yazilir, zorlanmaz. */
	$oneri = array();
	$gezi_gibi = isset( $bulunan['sablon_gezi'] ) || isset( $bulunan['sablon_liste'] ) || isset( $bulunan['sablon_rota'] );
	if ( $gezi_gibi ) {
		if ( ! isset( $bulunan['otel'] ) )     { $oneri[] = __( 'Otel motoru yok — konaklama aramasını bu sayfa karşılayabilir.', 'gbc-core' ); }
		if ( ! isset( $bulunan['ortaklik'] ) ) { $oneri[] = __( 'Ortaklık motoru yok — sayfada hiç iş ortaklığı bağlantısı üretilmiyor.', 'gbc-core' ); }
		if ( ! isset( $bulunan['rota'] ) )     { $oneri[] = __( 'Rota motoru yok — gün gün plan bölümü eklenebilir.', 'gbc-core' ); }
	}
	if ( isset( $bulunan['sablon_tarif'] ) && ! isset( $bulunan['ortaklik'] ) ) {
		$oneri[] = __( 'Tarif sayfasında ortaklık motoru yok — malzeme ve ekipman bağlantıları eklenebilir.', 'gbc-core' );
	}
	if ( ! isset( $bulunan['icindekiler'] ) ) {
		$oneri[] = __( 'İçindekiler kutusu basılmamış — uzun sayfalarda okunma süresini uzatır.', 'gbc-core' );
	}

	return array( 'calisan' => $bulunan, 'oneri' => $oneri );
}

/* ============================================================
   EKRANLAR
   ============================================================ */

function gbc_seo_kart( $baslik, $deger, $alt = '', $renk = '#14181F' ) {
	return '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:16px 20px;min-width:150px;flex:1">'
		. '<div style="font-size:12.5px;font-weight:600;color:#5C6470">' . esc_html( $baslik ) . '</div>'
		. '<div style="font-size:28px;font-weight:700;line-height:1.2;color:' . esc_attr( $renk ) . '">' . esc_html( $deger ) . '</div>'
		. ( $alt ? '<div style="font-size:12.5px;color:#5C6470;margin-top:2px">' . esc_html( $alt ) . '</div>' : '' )
		. '</div>';
}

function gbc_seo_uyari_kaynak() {
	$k = gbc_seo_kaynak();
	if ( ! empty( $k['var'] ) ) {
		if ( empty( $k['tur'] ) ) {
			echo '<div class="notice notice-warning inline" style="margin:16px 0"><p><strong>'
				. esc_html__( 'Arama türü ayrımı yok.', 'gbc-core' ) . '</strong> '
				. esc_html__( 'Bu Rank Math sürümünün tablosunda web / görsel ayrımı tutulmuyor. Görsel aramasının tıklanma oranı web aramasının onda biri kadar olduğu için, ikisi karışıkken "CTR düşük" hükmü verilmez — o kural kapalı tutuluyor. Sıralama, gösterim ve tıklama sayıları doğrudur.', 'gbc-core' )
				. '</p></div>';
		}
		return true;
	}
	echo '<div class="notice notice-error inline" style="margin:16px 0"><p><strong>'
		. esc_html__( 'Search Console verisi okunamadı.', 'gbc-core' ) . '</strong> '
		. esc_html( isset( $k['not'] ) ? $k['not'] : '' ) . '</p></div>';
	return false;
}

/* --- 1) GENEL BAKIŞ --- */
function gbc_seo_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$gun = isset( $_GET['gun'] ) ? max( 7, min( 365, (int) $_GET['gun'] ) ) : 90;

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo' ); }
	echo '<p style="max-width:960px;color:#444">'
		. esc_html__( 'Bütün rakamlar sitenin kendi Search Console geçmişinden okunur — dışarıya istek gitmez, kota harcanmaz. Ölçülmeyen hiçbir sayı yazılmaz.', 'gbc-core' )
		. '</p>';

	echo '<p>';
	foreach ( array( 30, 90, 180, 365 ) as $g ) {
		$aktif = ( $g === $gun );
		echo '<a class="button ' . ( $aktif ? 'button-primary' : '' ) . '" style="margin-right:6px" href="'
			. esc_url( admin_url( 'admin.php?page=gbc-seo&gun=' . $g ) ) . '">'
			. esc_html( sprintf( __( 'Son %d gün', 'gbc-core' ), $g ) ) . '</a>';
	}
	echo '</p>';

	if ( ! gbc_seo_uyari_kaynak() ) { echo '</div>'; return; }

	$o = gbc_seo_ozet( $gun );
	$e = gbc_seo_envanter();
	$d = gbc_seo_dagilim( $gun );

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_seo_kart( __( 'Tıklama', 'gbc-core' ), number_format_i18n( (int) $o['tik'] ), sprintf( __( 'son %d gün', 'gbc-core' ), $gun ) );
	echo gbc_seo_kart( __( 'Gösterim', 'gbc-core' ), number_format_i18n( (int) $o['gos'] ), '' );
	echo gbc_seo_kart( __( 'Ortalama sıra', 'gbc-core' ), number_format_i18n( (float) $o['sira'], 1 ), '',
		$o['sira'] <= 10 ? '#1A7F37' : '#8A6100' );
	echo gbc_seo_kart( __( 'Tıklanma oranı', 'gbc-core' ), '%' . number_format_i18n( (float) $o['ctr'], 2 ), '' );
	echo gbc_seo_kart( __( 'Görünen kelime', 'gbc-core' ), number_format_i18n( (int) $o['kelime'] ), sprintf( __( '%s sayfada', 'gbc-core' ), number_format_i18n( (int) $o['sayfa'] ) ) );
	echo '</div>';

	/* Envanter */
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Envanter', 'gbc-core' ) . '</h2>';
	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:12px 0">';
	echo gbc_seo_kart( __( 'Yayımda', 'gbc-core' ), number_format_i18n( $e['toplam'] ), sprintf( __( '%d taslak', 'gbc-core' ), $e['taslak'] ) );
	echo gbc_seo_kart( __( 'Gezi', 'gbc-core' ), number_format_i18n( $e['gezi'] ), __( 'gezi silosu', 'gbc-core' ), '#0B5B55' );
	echo gbc_seo_kart( __( 'Yemek', 'gbc-core' ), number_format_i18n( $e['yemek'] ), __( 'yemek silosu', 'gbc-core' ), '#A8380F' );
	echo '</div>';

	/* Sira dagilimi */
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Sıra dağılımı', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:600px"><tbody>';
	$etiket = array( '1-3' => __( '1–3. sıra', 'gbc-core' ), '4-10' => __( '4–10. sıra', 'gbc-core' ),
		'11-20' => __( '11–20. sıra (eşik)', 'gbc-core' ), '20+' => __( '20+ sıra', 'gbc-core' ) );
	$renk = array( '1-3' => '#1A7F37', '4-10' => '#8A6100', '11-20' => '#1B4E9B', '20+' => '#5C6470' );
	foreach ( $d as $a => $s ) {
		echo '<tr><td style="width:180px">' . esc_html( $etiket[ $a ] ) . '</td>'
			. '<td><strong style="color:' . esc_attr( $renk[ $a ] ) . '">' . esc_html( number_format_i18n( $s ) ) . '</strong> '
			. esc_html__( 'kelime', 'gbc-core' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<p style="margin-top:20px">'
		. '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-firsat' ) ) . '">' . esc_html__( 'Fırsatlar', 'gbc-core' ) . '</a> '
		. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa' ) ) . '">' . esc_html__( 'Sayfa denetimi', 'gbc-core' ) . '</a>'
		. '</p>';

	echo '</div>';
}

/* --- 2) FIRSATLAR ---
   v1.42.0: iki kaynak tek listede. Search Console (CTR, eşik, yıl, kanibalizasyon) +
   kelime evreni (eklenecek kelime, yeni sayfa, başlığa taşı, onay bekleyen, taranmamış sayfa). */
function gbc_seo_firsat_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$gun  = isset( $_GET['gun'] ) ? max( 7, min( 365, (int) $_GET['gun'] ) ) : 90;
	$tur  = isset( $_GET['tur'] ) ? sanitize_key( $_GET['tur'] ) : '';
	$fpid = isset( $_GET['fpid'] ) ? (int) $_GET['fpid'] : 0;

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Fırsatlar', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo-firsat' ); }
	$gsc_var = gbc_seo_uyari_kaynak();

	$hepsi = $gsc_var ? gbc_seo_firsatlar( $gun ) : array();
	foreach ( $hepsi as $i => $f ) { $hepsi[ $i ]['hacim'] = null; $hepsi[ $i ]['pid'] = 0; }
	if ( function_exists( 'gbc_fk_firsatlar' ) ) { $hepsi = array_merge( $hepsi, gbc_fk_firsatlar( $gun ) ); }
	usort( $hepsi, static function ( $a, $b ) {
		return ( $b['puan'] == $a['puan'] ) ? 0 : ( ( $b['puan'] < $a['puan'] ) ? -1 : 1 );
	} );
	if ( $fpid ) {
		$hepsi = array_values( array_filter( $hepsi, static function ( $f ) use ( $fpid ) {
			$p = ! empty( $f['pid'] ) ? (int) $f['pid'] : (int) url_to_postid( $f['sayfa'] );
			return $p === $fpid;
		} ) );
	}

	$sayac = array(); $hacim = array();
	foreach ( $hepsi as $f ) {
		$sayac[ $f['tur'] ] = isset( $sayac[ $f['tur'] ] ) ? $sayac[ $f['tur'] ] + 1 : 1;
		$hacim[ $f['tur'] ] = ( isset( $hacim[ $f['tur'] ] ) ? $hacim[ $f['tur'] ] : 0 ) + (int) $f['hacim'];
	}

	$adlar = array(
		'kelime'     => array( __( 'Eklenecek kelime', 'gbc-core' ), '#1A7F37', 'k' ),
		'yeni_sayfa' => array( __( 'Yeni sayfa', 'gbc-core' ), '#6B3FA0', 'k' ),
		'tasi'       => array( __( 'Başlığa taşı', 'gbc-core' ), '#1B4E9B', 'k' ),
		'onay'       => array( __( 'Onay bekliyor', 'gbc-core' ), '#8A6100', 'k' ),
		'tara'       => array( __( 'Taranmamış sayfa', 'gbc-core' ), '#5C6470', 'k' ),
		'ctr'        => array( __( 'CTR boşluğu', 'gbc-core' ), '#B32D2E', 'g' ),
		'esik'       => array( __( 'Eşik (11–20)', 'gbc-core' ), '#1B4E9B', 'g' ),
		'kanibal'    => array( __( 'Kanibalizasyon', 'gbc-core' ), '#8A6100', 'g' ),
		'yil'        => array( __( 'Yıl güncellemesi', 'gbc-core' ), '#5C6470', 'g' ),
	);
	$taban = 'admin.php?page=gbc-seo-firsat&gun=' . $gun . ( $fpid ? '&fpid=' . $fpid : '' );

	/* Üst kartlar */
	$kart = function ( $a, $alt ) use ( $sayac, $adlar, $taban, $tur ) {
		$n = isset( $sayac[ $a ] ) ? $sayac[ $a ] : 0;
		return '<a href="' . esc_url( admin_url( $taban . '&tur=' . $a ) ) . '" style="background:#fff;border:1px solid ' . ( $tur === $a ? $adlar[ $a ][1] : '#E3E5E9' ) . ';border-radius:14px;padding:14px 18px;display:block;text-decoration:none;color:inherit;min-width:0">'
			. '<div style="font-weight:600;color:#3C4149;font-size:13px">' . esc_html( $adlar[ $a ][0] ) . '</div>'
			. '<div style="font-size:28px;font-weight:700;color:' . ( $n ? $adlar[ $a ][1] : '#9AA0A6' ) . ';line-height:1.3">' . (int) $n . '</div>'
			. '<div style="color:#5C6470;font-size:12.5px">' . esc_html( $alt ) . '</div></a>';
	};
	$gsc_n = 0; foreach ( $hepsi as $f ) { if ( empty( $f['pid'] ) ) { $gsc_n++; } }
	echo '<style>.gbc-fr-kart{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:14px 0;max-width:1300px}'
		. '@media (max-width:600px){.gbc-fr-kart{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gbc-fr-kart>a{padding:12px 14px!important}}</style>';
	echo '<div class="gbc-fr-kart">';
	echo $kart( 'kelime', sprintf( __( 'toplam aylık %s arama', 'gbc-core' ), number_format_i18n( isset( $hacim['kelime'] ) ? $hacim['kelime'] : 0 ) ) );
	echo $kart( 'yeni_sayfa', sprintf( __( 'aylık %s arama', 'gbc-core' ), number_format_i18n( isset( $hacim['yeni_sayfa'] ) ? $hacim['yeni_sayfa'] : 0 ) ) );
	echo $kart( 'tasi', __( 'geçiyor ama zayıf yerde', 'gbc-core' ) );
	echo $kart( 'tara', __( 'Google gösteriyor, kelimeler çıkarılmamış', 'gbc-core' ) );
	echo '<a href="' . esc_url( admin_url( $taban ) ) . '" style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:14px 18px;display:block;text-decoration:none;color:inherit;min-width:0">'
		. '<div style="font-weight:600;color:#3C4149;font-size:13px">' . esc_html__( 'Search Console', 'gbc-core' ) . '</div>'
		. '<div style="font-size:28px;font-weight:700;color:' . ( $gsc_n ? '#B32D2E' : '#9AA0A6' ) . ';line-height:1.3">' . (int) $gsc_n . '</div>'
		. '<div style="color:#5C6470;font-size:12.5px">' . esc_html( $gsc_var ? __( 'CTR · eşik · yıl · kanibalizasyon', 'gbc-core' ) : __( 'veri okunamadı', 'gbc-core' ) ) . '</div></a>';
	echo '</div>';

	/* Süzgeç düğmeleri */
	echo '<p style="line-height:2.4">';
	echo '<a class="button ' . ( '' === $tur ? 'button-primary' : '' ) . '" style="margin-right:6px" href="' . esc_url( admin_url( $taban ) ) . '">'
		. esc_html( sprintf( __( 'Hepsi (%d)', 'gbc-core' ), count( $hepsi ) ) ) . '</a>';
	foreach ( $adlar as $a => $ad ) {
		$n = isset( $sayac[ $a ] ) ? $sayac[ $a ] : 0;
		if ( ! $n && $tur !== $a ) { continue; }
		echo '<a class="button ' . ( $tur === $a ? 'button-primary' : '' ) . '" style="margin-right:6px" href="' . esc_url( admin_url( $taban . '&tur=' . $a ) ) . '">'
			. esc_html( $ad[0] . ' (' . $n . ')' ) . '</a>';
	}
	if ( $fpid ) {
		echo ' <span style="margin-left:8px">' . esc_html( sprintf( __( 'Yalnız: %s', 'gbc-core' ), get_the_title( $fpid ) ) ) . '</span> <a href="'
			. esc_url( admin_url( 'admin.php?page=gbc-seo-firsat&gun=' . $gun . ( $tur ? '&tur=' . $tur : '' ) ) ) . '">' . esc_html__( 'bütün sayfalar', 'gbc-core' ) . '</a>';
	}
	echo '</p>';
	echo '<p style="color:#5C6470;max-width:1300px">' . esc_html__( 'Kelime fırsatları, Sayfa Denetimi açıldığında o sayfanın kelime evreninden çıkarılır; bir sayfayı denetledikçe liste güncellenir. Sıralama tahmini kazanca göredir: aylık arama × yakalama ihtimali; Google\'ın sayfayı o kelimede zaten gösterdiği kelimeler öne çıkar.', 'gbc-core' ) . '</p>';

	echo '<div style="overflow-x:auto;max-width:1300px"><table class="widefat striped"><thead><tr>'
		. '<th style="width:130px">' . esc_html__( 'Tür', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Kelime', 'gbc-core' ) . '</th>'
		. '<th style="width:80px">' . esc_html__( 'Aylık arama', 'gbc-core' ) . '</th>'
		. '<th style="width:60px">' . esc_html__( 'Sıra', 'gbc-core' ) . '</th>'
		. '<th style="width:80px">' . esc_html__( 'Gösterim', 'gbc-core' ) . '</th>'
		. '<th style="width:70px">' . esc_html__( 'Tıklama', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Sayfa ve ne yapılmalı', 'gbc-core' ) . '</th>'
		. '</tr></thead><tbody>';

	$yazildi = 0;
	foreach ( $hepsi as $f ) {
		if ( $tur && $f['tur'] !== $tur ) { continue; }
		if ( $yazildi++ >= 100 ) { break; }
		$pid = ! empty( $f['pid'] ) ? (int) $f['pid'] : (int) url_to_postid( $f['sayfa'] );
		$ad  = isset( $adlar[ $f['tur'] ] ) ? $adlar[ $f['tur'] ] : array( $f['tur'], '#5C6470', '' );
		$ikinci = '';
		if ( ! empty( $f['gsc'] ) && 'tara' !== $f['tur'] ) { $ikinci .= '<div style="font-size:11px;color:#1A7F37">' . esc_html__( 'Google zaten gösteriyor', 'gbc-core' ) . '</div>'; }
		if ( ! empty( $f['yk'] ) ) { $ikinci .= '<div style="font-size:11px;color:#5C6470">' . esc_html( sprintf( __( 'yakalama: %s', 'gbc-core' ), $f['yk'] ) ) . '</div>'; }
		$islem = '';
		if ( $pid ) {
			$islem .= ' · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid ) . ( 'tara' === $f['tur'] ? '' : '#gbc-kelime' ) ) . '">' . esc_html( 'onay' === $f['tur'] ? __( 'denetle ve onayla', 'gbc-core' ) : __( 'denetle', 'gbc-core' ) ) . '</a>';
			if ( 'tara' === $f['tur'] && function_exists( 'gbc_kw_islem_url' ) ) {
				$islem .= ' · <a class="button button-small" href="' . esc_url( gbc_kw_islem_url( $pid, 'tara' ) ) . '">' . esc_html__( 'Tara', 'gbc-core' ) . '</a>';
			}
			if ( ! $fpid ) {
				$islem .= ' · <a href="' . esc_url( admin_url( 'admin.php?page=gbc-seo-firsat&gun=' . $gun . '&fpid=' . $pid ) ) . '">' . esc_html__( 'bu sayfanın fırsatları', 'gbc-core' ) . '</a>';
			}
		}
		echo '<tr>';
		echo '<td><span style="color:' . esc_attr( $ad[1] ) . ';font-weight:600">' . esc_html( $ad[0] ) . '</span>' . $ikinci . '</td>';
		echo '<td><strong>' . esc_html( $f['kelime'] ) . '</strong></td>';
		echo '<td>' . ( null !== $f['hacim'] ? esc_html( number_format_i18n( (int) $f['hacim'] ) ) : '—' ) . '</td>';
		echo '<td>' . ( null !== $f['sira'] ? esc_html( number_format_i18n( $f['sira'], 1 ) ) : '—' ) . '</td>';
		echo '<td>' . ( null !== $f['gos'] ? esc_html( number_format_i18n( (int) $f['gos'] ) ) : '—' ) . '</td>';
		echo '<td>' . ( null !== $f['tik'] ? esc_html( number_format_i18n( (int) $f['tik'] ) ) : '—' ) . '</td>';
		echo '<td><a href="' . esc_url( $f['sayfa'] ) . '" target="_blank">' . esc_html( (string) wp_parse_url( $f['sayfa'], PHP_URL_PATH ) ) . '</a>' . $islem
			. '<div style="color:#3C4149;font-size:12.5px">' . esc_html( $f['not'] ) . '</div></td>';
		echo '</tr>';
	}
	if ( ! $yazildi ) {
		echo '<tr><td colspan="7"><em>' . esc_html__( 'Bu türde fırsat çıkmadı. Kelime fırsatları için sayfaları Sayfa Denetimi\'nde açıp kelimeleri tara.', 'gbc-core' ) . '</em></td></tr>';
	}
	echo '</tbody></table></div></div>';
}

/* --- 3) SAYFA DENETİMİ --- */
function gbc_seo_sayfa_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$pid = isset( $_GET['pid'] ) ? (int) $_GET['pid'] : 0;
	$gun = isset( $_GET['gun'] ) ? max( 7, min( 365, (int) $_GET['gun'] ) ) : 90;

	/* v1.37.3: sayfa adıyla, numarasıyla ya da adresiyle bulunur. */
	$ara = isset( $_GET['ara'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['ara'] ) ) ) : '';
	$ara_sonuc = array();
	if ( '' !== $ara && ! $pid ) {
		if ( ctype_digit( $ara ) ) {
			$pid = (int) $ara;
		} elseif ( preg_match( '~^https?://~i', $ara ) || 0 === strpos( $ara, '/' ) ) {
			$pid = (int) url_to_postid( 0 === strpos( $ara, '/' ) ? home_url( $ara ) : $ara );
		}
		if ( ! $pid ) {
			$ara_sonuc = get_posts( array(
				's'                => $ara,
				'post_type'        => array( 'post', 'page' ),
				'post_status'      => array( 'publish', 'draft', 'future', 'pending', 'private' ),
				'posts_per_page'   => 25,
				'orderby'          => 'relevance',
				'suppress_filters' => false,
			) );
			if ( 1 === count( $ara_sonuc ) ) { $pid = (int) $ara_sonuc[0]->ID; $ara_sonuc = array(); }
		}
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC SEO · Sayfa Denetimi', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-seo-sayfa' ); }

	/* Sayfa secimi */
	/* v1.47.1: arama kutusu + (sayfa seçiliyse) sağında "yeniden kontrol et" kutusu, tepede yan yana. */
	echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:stretch;margin:14px 0;max-width:1400px">';
	echo '<form method="get" style="margin:0;background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px;flex:1 1 520px;max-width:900px;box-sizing:border-box">';
	echo '<input type="hidden" name="page" value="gbc-seo-sayfa">';
	echo '<label for="gbc_seo_ara" style="font-weight:600;margin-right:8px">' . esc_html__( 'Sayfa adı, numarası ya da adresi', 'gbc-core' ) . '</label>';
	echo '<input type="text" id="gbc_seo_ara" name="ara" value="' . esc_attr( '' !== $ara ? $ara : ( $pid ? (string) $pid : '' ) ) . '" placeholder="' . esc_attr__( 'örn. Sorrento, 31232 ya da /sorrento-gezi-rehberi/', 'gbc-core' ) . '" style="width:100%;max-width:360px;box-sizing:border-box">';
	echo ' <button class="button button-primary">' . esc_html__( 'Bu sayfa için çalıştır', 'gbc-core' ) . '</button>';
	echo '<p style="color:#5C6470;font-size:12.5px;margin:8px 0 0">'
		. esc_html__( 'Sayfa canlı olarak indirilir ve ölçülür: başlık ağacı, şema, alt metni, meta, yıl ifadesi, iç ve dış bağlantılar, ortaklık bağları. Ardından o sayfanın Search Console kelimeleri listelenir.', 'gbc-core' )
		. '</p>';
	echo '</form>';
	if ( $pid && get_post_status( $pid ) ) {
		$tz = wp_nonce_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . (int) $pid . '&br_taze=1' ), 'gbc_br_taze' );
		echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px;flex:1 1 260px;box-sizing:border-box;display:flex;flex-direction:column;gap:8px;justify-content:center">'
			. '<div style="font-weight:700;font-size:14px;line-height:1.35">' . esc_html( get_the_title( $pid ) ) . '</div>'
			. '<div><a class="button button-primary" href="' . esc_url( $tz ) . '">' . esc_html__( 'Düzelttim — yeniden kontrol et', 'gbc-core' ) . '</a></div>'
			. '<div style="font-size:12.5px"><a href="' . esc_url( get_edit_post_link( $pid, '' ) ) . '">' . esc_html__( 'Sayfayı düzenle', 'gbc-core' ) . '</a> · <a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Sayfayı aç', 'gbc-core' ) . '</a></div>'
			. '<div style="color:#5C6470;font-size:12px">' . esc_html__( 'Bağlantılar ve Google dizini de yeniden sorulur.', 'gbc-core' ) . '</div></div>';
	}
	echo '</div>';

	if ( ! $pid && '' !== $ara ) {
		echo '<h2>' . esc_html( sprintf( __( '"%s" için bulunanlar', 'gbc-core' ), $ara ) ) . '</h2>';
		if ( ! $ara_sonuc ) {
			echo '<p><em>' . esc_html__( 'Bu adla sayfa bulunamadı. Başka bir kelime dene.', 'gbc-core' ) . '</em></p></div>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $ara_sonuc as $y ) {
			$dur = ( 'publish' === $y->post_status ) ? '' : ' <span style="color:#8A6100;font-size:12px">(' . esc_html( $y->post_status ) . ')</span>';
			echo '<tr><td style="width:80px">' . (int) $y->ID . '</td><td>' . esc_html( $y->post_title ) . $dur
				. '<div style="color:#5C6470;font-size:12px">' . esc_html( (string) wp_parse_url( get_permalink( $y->ID ), PHP_URL_PATH ) ) . '</div></td>'
				. '<td style="width:120px"><a class="button button-small" href="'
				. esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $y->ID ) ) . '">' . esc_html__( 'Denetle', 'gbc-core' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}

	if ( ! $pid ) {
		/* Yardimci: son yazilan 15 icerik */
		$son = get_posts( array( 'numberposts' => 15, 'post_status' => 'publish' ) );
		if ( $son ) {
			echo '<h2>' . esc_html__( 'Son güncellenenler', 'gbc-core' ) . '</h2><table class="widefat striped" style="max-width:900px"><tbody>';
			foreach ( $son as $y ) {
				echo '<tr><td style="width:80px">' . (int) $y->ID . '</td><td>' . esc_html( $y->post_title ) . '</td>'
					. '<td style="width:120px"><a class="button button-small" href="'
					. esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $y->ID ) ) . '">' . esc_html__( 'Denetle', 'gbc-core' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		return;
	}

	$d = gbc_seo_icerik_denetim( $pid );
	if ( ! empty( $d['hata'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $d['hata'] ) . '</p></div></div>';
		return;
	}

	$s = gbc_seo_skor( $d );

	/* 30 Eyl 2026 · v1.37.1: bağlantı raporu ve URL denetimi başta bir kez
	   hesaplanır; üst kartlar ve alttaki tablolar aynı sonucu kullanır. */
	$br    = function_exists( 'gbc_br_rapor' ) ? gbc_br_rapor( isset( $d['ham'] ) ? $d['ham'] : '', gbc_br_taze_mi() ) : null;
	$kw    = function_exists( 'gbc_kw_analiz' ) ? gbc_kw_analiz( $pid, isset( $d['ham'] ) ? $d['ham'] : '' ) : null;
	/* v1.40.0: kanibalizasyon (Search Console site geneli + başlık hedefi). */
	$kn    = ( $kw && function_exists( 'gbc_kn_analiz' ) ) ? gbc_kn_analiz( $pid, $d['url'], $kw['tohum'], $kw['satir'] ) : null;
	/* v1.42.0: bu sayfanın kelime fırsatları Fırsatlar ekranı için saklanır (yalnız değiştiyse yazılır). */
	if ( $kw && function_exists( 'gbc_fk_kaydet' ) ) { gbc_fk_kaydet( $pid, $kw, $kn ); }

	$url_m = function_exists( 'gbc_br_url_denetim' ) ? gbc_br_url_denetim( $d['url'], isset( $d['ham'] ) ? $d['ham'] : '', (int) $d['kod'] ) : array();

	/* v1.41.0: yeni düzen (inc/sayfa-duzen.php) varsa bütün ekran oradan basılır; aşağıdaki eski akış yedektir. */
	if ( is_array( $br ) && function_exists( 'gbc_sd_ekran' ) ) {
		gbc_sd_ekran( $pid, $d, $br, $url_m, $kw, $kn );
		echo '</div>';
		return;
	}

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	/* 30 Eyl 2026 · v1.37.2: puanlı skor (içerik, meta, URL, bağlantı, şema). */
	$sk = ( is_array( $br ) && function_exists( 'gbc_br_skor' ) ) ? gbc_br_skor( $d, $br, $url_m ) : null;
	if ( $sk ) {
		echo gbc_br_kart( __( 'GBC skoru', 'gbc-core' ), '%' . $sk['toplam'],
			esc_html( sprintf( __( '%1$d / %2$d puan', 'gbc-core' ), $sk['puan'], $sk['max'] ) ),
			$sk['toplam'] >= 80 ? '#1A7F37' : ( $sk['toplam'] >= 60 ? '#8A6100' : '#B32D2E' ), '#gbc-kontroller' );
	} else {
	echo gbc_seo_kart( __( 'GBC skoru', 'gbc-core' ), '%' . $s['skor'],
		sprintf( __( '%1$d / %2$d kontrol geçti', 'gbc-core' ), $s['gecen'], $s['toplam'] ),
		$s['skor'] >= 80 ? '#1A7F37' : ( $s['skor'] >= 60 ? '#8A6100' : '#B32D2E' ) );
	}
	echo function_exists( 'gbc_br_kart' )
		? gbc_br_kart( __( 'Başlık', 'gbc-core' ), (string) count( $d['basliklar'] ),
			esc_html( sprintf( __( '%1$d H1 · %2$d atlama', 'gbc-core' ), (int) $d['h1_adet'], (int) $d['atlama'] ) ), '#14181F', '#gbc-basliklar' )
		: gbc_seo_kart( __( 'Başlık', 'gbc-core' ), (string) count( $d['basliklar'] ),
		sprintf( __( '%1$d H1 · %2$d atlama', 'gbc-core' ), (int) $d['h1_adet'], (int) $d['atlama'] ) );
	echo function_exists( 'gbc_br_kart' )
		? gbc_br_kart( __( 'Görsel', 'gbc-core' ), (string) (int) $d['gorsel'],
			esc_html( sprintf( __( '%d alt metni eksik', 'gbc-core' ), (int) $d['alt_yok'] ) ),
			$d['alt_yok'] ? '#B32D2E' : '#1A7F37', '#gbc-k-icerik' )
		: gbc_seo_kart( __( 'Görsel', 'gbc-core' ), (string) (int) $d['gorsel'],
		sprintf( __( '%d alt metni eksik', 'gbc-core' ), (int) $d['alt_yok'] ),
		$d['alt_yok'] ? '#B32D2E' : '#1A7F37' );

	/* 29 Eyl 2026: hangi görselin eksik olduğu yazılıyor. Eskiden yalnız
	   sayı vardı, görseli bulmak için sayfayı açıp aramak gerekiyordu. */
	if ( ! empty( $d['alt_eksik'] ) ) {
		echo '<div style="max-width:1000px;margin:10px 0 0;padding:10px 14px;background:#FBE7E7;'
			. 'border-left:3px solid #B3261E;border-radius:3px">';
		echo '<strong>' . esc_html__( 'Alt metni eksik görseller', 'gbc-core' ) . '</strong><ul style="margin:6px 0 0">';
		foreach ( $d['alt_eksik'] as $g ) {
			$ad = '' !== $g['ad'] ? $g['ad'] : __( 'adresi okunamadı', 'gbc-core' );
			echo '<li>' . ( $g['src']
				? '<a href="' . esc_url( $g['src'] ) . '" target="_blank" rel="noopener">' . esc_html( $ad ) . '</a>'
				: esc_html( $ad ) );
			if ( $g['bos'] ) {
				echo ' <span style="color:#5C6470">' . esc_html__( '— alt="" boş bırakılmış', 'gbc-core' ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( (int) $d['alt_yok'] > count( $d['alt_eksik'] ) ) {
			echo '<p style="margin:6px 0 0;color:#5C6470">'
				. esc_html( sprintf(
					/* translators: %d: listelenmeyen gorsel sayisi */
					__('ve %d tane daha.', 'gbc-core' ),
					(int) $d['alt_yok'] - count( $d['alt_eksik'] ) ) ) . '</p>';
		}
		echo '</div>';
	}
	if ( is_array( $br ) && function_exists( 'gbc_br_ust_kartlar' ) ) {
		echo gbc_br_ust_kartlar( $br, $d, $url_m ); // kartlar içeride kaçırılıyor
		/* v1.38.0: Google dizini kartı. */
		if ( function_exists( 'gbc_gs_kart' ) ) { echo gbc_gs_kart( $d['url'] ); }
		/* v1.39.0: kelime kapsama kartı. */
		if ( $kw && function_exists( 'gbc_kw_kart' ) ) { echo gbc_kw_kart( $kw ); }
	} else {
	echo gbc_seo_kart( __( 'Bağlantı', 'gbc-core' ), (int) $d['ic'] . ' / ' . (int) $d['dis'],
		__( 'iç / dış', 'gbc-core' ) );
	}
	$ort_toplam = array_sum( $d['ortaklik'] );
	/* v1.37.3: rapor varsa ortaklık kartını gbc_br_ust_kartlar basar (29 link / 30 geçiş farkı tek yerde). */
	if ( ! is_array( $br ) ) echo function_exists( 'gbc_br_kart' )
		? gbc_br_kart( __( 'Ortaklık bağı', 'gbc-core' ), (string) $ort_toplam,
			esc_html( $d['ortaklik'] ? implode( ' · ', array_keys( $d['ortaklik'] ) ) : __( 'yok', 'gbc-core' ) ),
			$ort_toplam ? '#1A7F37' : '#8A6100', '#br-ortaklik' )
		: gbc_seo_kart( __( 'Ortaklık bağı', 'gbc-core' ), (string) $ort_toplam,
		$d['ortaklik'] ? implode( ' · ', array_keys( $d['ortaklik'] ) ) : __( 'yok', 'gbc-core' ),
		$ort_toplam ? '#1A7F37' : '#8A6100' );
	echo '</div>';

	/* v1.37.3: adres, HTTP ve boyut URL kartına ve URL kontrol başlığına taşındı. */
	if ( ! is_array( $br ) ) {
	echo '<p><a href="' . esc_url( $d['url'] ) . '" target="_blank">' . esc_html( $d['url'] ) . '</a> · '
		. esc_html( sprintf( __( 'HTTP %1$d · %2$s', 'gbc-core' ), (int) $d['kod'], size_format( (int) $d['bayt'] ) ) ) . '</p>';
	}

	/* Kontrol listesi — v1.37.2: puanlı tablo varsa o basılır. */
	if ( $sk && function_exists( 'gbc_br_skor_tablo' ) ) {
		gbc_br_skor_tablo( $sk );
	} else {
	echo '<h2 style="margin-top:22px">' . esc_html__( 'Kontroller', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:700px"><tbody>';
	foreach ( $s['maddeler'] as $m ) {
		echo '<tr><td style="width:60px">' . ( $m['ok']
			? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'GEÇTİ', 'gbc-core' ) . '</span>'
			: '<span style="color:#B32D2E;font-weight:700">' . esc_html__( 'KALDI', 'gbc-core' ) . '</span>' )
			. '</td><td>' . esc_html( $m['ad'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	if ( $url_m && function_exists( 'gbc_br_url_tablo' ) ) { gbc_br_url_tablo( $url_m ); }
	} /* eski kontrol tablosu */

	/* v1.38.0: Google bölümü (dizin, Search Console, Analytics) kontrollerin hemen altında. */
	if ( function_exists( 'gbc_gs_ekran' ) ) { gbc_gs_ekran( $pid, $d['url'] ); }
	/* v1.39.0: kelime evreni ve kapsama. */
	if ( $kw && function_exists( 'gbc_kw_ekran' ) ) { gbc_kw_ekran( $pid, $kw, $kn ); }
	if ( $kn && function_exists( 'gbc_kn_ekran' ) ) { gbc_kn_ekran( $kn, $kw ); }

	/* Baslik agaci */
	echo '<h2 id="gbc-basliklar" style="margin-top:22px">' . esc_html__( 'Başlık yapısı', 'gbc-core' ) . '</h2>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px;max-width:900px;line-height:1.9">';
	$onceki = 1;
	foreach ( $d['basliklar'] as $b ) {
		$sev = (int) substr( $b['tip'], 1 );
		$atladi = ( $sev > $onceki + 1 );
		$onceki = $sev;
		echo '<div style="padding-left:' . ( ( $sev - 1 ) * 18 ) . 'px' . ( $atladi ? ';color:#B32D2E' : '' ) . '">'
			. '<strong>' . esc_html( strtoupper( $b['tip'] ) ) . '</strong> ' . esc_html( $b['metin'] )
			. ( $atladi ? ' <span style="font-size:12px;font-weight:600">' . esc_html__( '← sıra atlandı', 'gbc-core' ) . '</span>' : '' )
			. '</div>';
	}
	echo '</div>';

	/* Sema + meta + yil — v1.37.2: şema baloncuk bloğu ayrı basılır. */
	if ( function_exists( 'gbc_br_sema_blok' ) ) {
		gbc_br_sema_blok( $d );
		echo '<h2 style="margin-top:22px">' . esc_html__( 'Meta ve yıl', 'gbc-core' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:1000px"><tbody>';
	} else {
	echo '<h2 style="margin-top:22px">' . esc_html__( 'Şema, meta ve yıl', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:1000px"><tbody>';
	echo '<tr><td style="width:180px">' . esc_html__( 'Basılan şema', 'gbc-core' ) . '</td><td>'
		. ( $d['sema'] ? '<code>' . esc_html( implode( '</code> <code>', $d['sema'] ) ) . '</code>'
			: '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'hiç şema yok', 'gbc-core' ) . '</span>' ) . '</td></tr>';

	/* 29 Eyl 2026: EKSİK şema denetimi. Ekran eskiden yalnız basılanı
	   listeliyordu; olması gerekip de olmayan hiç görünmüyordu. */
	$sd = gbc_seo_sema_denetim( $d );
	echo '<tr><td>' . esc_html__( 'Eksik şema', 'gbc-core' ) . '</td><td>';
	if ( $sd['eksik'] ) {
		echo '<ul style="margin:0">';
		foreach ( $sd['eksik'] as $tip => $sebep ) {
			echo '<li><code style="color:#B3261E;font-weight:700">' . esc_html( $tip ) . '</code> '
				. '<span style="color:#5C6470">— ' . esc_html( $sebep ) . '</span></li>';
		}
		echo '</ul>';
	} else {
		echo '<span style="color:#1A7F37;font-weight:600">'
			. esc_html( sprintf(
				/* translators: %d: beklenen dugum sayisi */
				__( 'Beklenen %d düğümün hepsi basılıyor.', 'gbc-core' ),
				count( $sd['beklenen'] ) ) )
			. '</span>';
	}
	echo '</td></tr>';
	}
	echo '<tr><td>' . esc_html__( 'Başlık etiketi', 'gbc-core' ) . '</td><td>' . esc_html( $d['baslik_etiketi'] )
		. ' <span style="color:#5C6470">(' . (int) mb_strlen( $d['baslik_etiketi'] ) . ')</span></td></tr>';
	echo '<tr><td>' . esc_html__( 'Meta açıklama', 'gbc-core' ) . '</td><td>'
		. ( '' !== $d['aciklama'] ? esc_html( $d['aciklama'] ) . ' <span style="color:#5C6470">(' . (int) mb_strlen( $d['aciklama'] ) . ')</span>'
			: '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'yok', 'gbc-core' ) . '</span>' ) . '</td></tr>';
	$yil_metin = array();
	foreach ( (array) $d['yillar'] as $y => $n ) { $yil_metin[] = $y . ' (' . $n . ')'; }
	echo '<tr><td>' . esc_html__( 'Yıl ifadeleri', 'gbc-core' ) . '</td><td>'
		. ( $yil_metin ? esc_html( implode( ' · ', $yil_metin ) ) : esc_html__( 'yok', 'gbc-core' ) )
		. ( gbc_seo_yil_temiz( $d ) ? '' : ' <span style="color:#B32D2E;font-weight:600">' . esc_html__( '← eski yıl geçiyor, güncelle', 'gbc-core' ) . '</span>' )
		. '</td></tr>';
	echo '</tbody></table>';

	/* Motorlar */
	$mot_html = gbc_seo_motorlar( $pid, isset( $d['ham'] ) ? $d['ham'] : '' );
	echo '<h2 style="margin-top:22px">' . esc_html__( 'Bu sayfada çalışan motorlar', 'gbc-core' ) . '</h2>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px;max-width:1000px">';
	if ( $mot_html['calisan'] ) {
		echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">';
		foreach ( $mot_html['calisan'] as $m ) {
			echo '<span style="background:#E7F1EF;color:#0B5B55;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600">'
				. esc_html( $m['ad'] ) . '</span>';
		}
		echo '</div>';
	} else {
		echo '<p style="margin:0 0 10px;color:#8A6100"><strong>' . esc_html__( 'Hiçbir GBC motoru bulunamadı.', 'gbc-core' ) . '</strong></p>';
	}
	if ( $mot_html['oneri'] ) {
		echo '<div style="border-top:1px solid #EDE9E1;padding-top:10px">';
		echo '<div style="font-size:13px;font-weight:600;color:#5C6470;margin-bottom:6px">' . esc_html__( 'Eklenebilir', 'gbc-core' ) . '</div>';
		foreach ( $mot_html['oneri'] as $o ) {
			echo '<div style="font-size:13.5px;color:#3C4149;line-height:1.7">· ' . esc_html( $o ) . '</div>';
		}
		echo '</div>';
	}
	echo '</div>';

	/* Search Console kelimeleri — v1.38.0: Google bölümü varsa eski (Rank Math tablosundan) liste basılmaz. */
	if ( ! function_exists( 'gbc_gs_ekran' ) ) {
	echo '<h2 style="margin-top:22px">' . esc_html__( 'Bu sayfanın sıralamaları', 'gbc-core' ) . '</h2>';
	if ( gbc_seo_uyari_kaynak() ) {
		$kelimeler = gbc_seo_sayfa_kelime( $d['url'], $gun );
		if ( ! $kelimeler ) {
			echo '<p><em>' . esc_html__( 'Bu adres için Search Console kaydı yok. Sayfa yeni olabilir ya da hiç gösterim almamış olabilir.', 'gbc-core' ) . '</em></p>';
		} else {
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
				. '<th>' . esc_html__( 'Kelime', 'gbc-core' ) . '</th>'
				. '<th style="width:80px">' . esc_html__( 'Sıra', 'gbc-core' ) . '</th>'
				. '<th style="width:100px">' . esc_html__( 'Gösterim', 'gbc-core' ) . '</th>'
				. '<th style="width:90px">' . esc_html__( 'Tıklama', 'gbc-core' ) . '</th>'
				. '<th style="width:80px">' . esc_html__( 'CTR', 'gbc-core' ) . '</th>'
				. '</tr></thead><tbody>';
			foreach ( $kelimeler as $kk ) {
				$ctr = ( $kk['gos'] > 0 ) ? ( $kk['tik'] / $kk['gos'] * 100 ) : 0;
				$sira = (float) $kk['sira'];
				$renk = $sira <= 3 ? '#1A7F37' : ( $sira <= 10 ? '#8A6100' : '#1B4E9B' );
				echo '<tr><td><strong>' . esc_html( $kk['query'] ) . '</strong></td>'
					. '<td><span style="color:' . esc_attr( $renk ) . ';font-weight:700">' . esc_html( number_format_i18n( $sira, 1 ) ) . '</span></td>'
					. '<td>' . esc_html( number_format_i18n( (int) $kk['gos'] ) ) . '</td>'
					. '<td>' . esc_html( number_format_i18n( (int) $kk['tik'] ) ) . '</td>'
					. '<td>%' . esc_html( number_format_i18n( $ctr, 2 ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	} /* eski sıralama listesi */

	/* 30 Eyl 2026 · v1.37.0: dört gruplu bağlantı raporu (inc/bag-raporu.php).
	   Yüklü değilse eski dış bağlantı tablosu basılır. */
	if ( function_exists( 'gbc_br_ekran' ) ) {
		gbc_br_ekran( $pid, isset( $d['ham'] ) ? $d['ham'] : '', $br );
	} else {
	/* Dis baglantilar */
	echo '<h2 style="margin-top:22px">' . esc_html__( 'Dış bağlantılar', 'gbc-core' ) . '</h2>';
	$kontrol = isset( $_GET['bag'] ) && check_admin_referer( 'gbc_seo_bag' );
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid . '&bag=1' ), 'gbc_seo_bag' ) ) . '">'
		. esc_html__( 'Bağlantıları tek tek çağır (yavaş)', 'gbc-core' ) . '</a></p>';

	$saglik_haritasi = gbc_seo_saglik_haritasi();
	$liste = $kontrol ? gbc_seo_bag_kontrol( $d['dis_liste'] ) : $d['dis_liste'];
	if ( ! $liste ) {
		echo '<p><em>' . esc_html__( 'Dış bağlantı yok.', 'gbc-core' ) . '</em></p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
			. '<th>' . esc_html__( 'Adres', 'gbc-core' ) . '</th>'
			. '<th style="width:150px">' . esc_html__( 'Program', 'gbc-core' ) . '</th>'
			. '<th style="width:160px">' . esc_html__( 'rel', 'gbc-core' ) . '</th>'
			. '<th style="width:90px">' . esc_html__( 'Cevap', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $liste as $b ) {
			$kod = isset( $b['kod'] ) ? (int) $b['kod'] : null;
			echo '<tr><td style="word-break:break-all"><a href="' . esc_url( $b['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $b['url'] ) . '</a></td>';
			echo '<td>' . ( $b['ort'] ? '<strong style="color:#A8380F">' . esc_html( $b['ort'] ) . '</strong>' : '—' ) . '</td>';
			echo '<td>' . ( $b['rel'] ? esc_html( $b['rel'] )
				: ( $b['ort'] ? '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'eksik (sponsored olmalı)', 'gbc-core' ) . '</span>' : '—' ) ) . '</td>';
			/* 29 Eyl 2026: canlı istek atılmadıysa GÜNLÜK TARAMANIN kayıtlı
			   sonucuna bakılır. Veri zaten vardı, ekran okumuyordu. */
			$anahtar = gbc_seo_adres_anahtar( $b['url'] );
			if ( null === $kod && ! empty( $saglik_haritasi[ $anahtar ] ) ) {
				$kayit = $saglik_haritasi[ $anahtar ];
				echo '<td>' . wp_kses_post( gbc_ort_saglik_hucre( $kayit ) ) . '</td></tr>';
				continue;
			}
			echo '<td>' . ( null === $kod ? '<span style="color:#5C6470">' . esc_html__( 'bakılmadı', 'gbc-core' ) . '</span>'
				: ( ( $kod >= 200 && $kod < 400 )
					? '<span style="color:#1A7F37;font-weight:700">' . (int) $kod . '</span>'
					: '<span style="color:#B32D2E;font-weight:700">' . ( $kod ? (int) $kod : esc_html__( 'ulaşılamadı', 'gbc-core' ) ) . '</span>' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	} /* gbc_br_ekran yoksa */

	echo '<p style="margin-top:18px"><a class="button button-primary" href="'
		. esc_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid ) ) . '">'
		. esc_html__( 'Düzelttim — yeniden kontrol et', 'gbc-core' ) . '</a> '
		. '<a class="button" href="' . esc_url( get_edit_post_link( $pid, '' ) ) . '">' . esc_html__( 'Sayfayı düzenle', 'gbc-core' ) . '</a></p>';

	echo '</div>';
}
