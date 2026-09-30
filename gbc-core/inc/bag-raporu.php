<?php
/**
 * GBC Core · Bağlantı Raporu (v1.37.0, 30 Eylül 2026)
 * ------------------------------------------------------------
 * Sayfa Denetimi ekranının altına dört gruplu bir rapor basar:
 *   1) Ortaklık bağlantıları
 *   2) YouTube bağlantıları
 *   3) Diğer dış bağlantılar
 *   4) İç bağlantılar
 * Her satırın yanında çalışıyor / yönlendiriyor / ölü yazar.
 *
 * KAPSAM: yalnız içerik alanı ('entry-content' ile yorum/footer arası).
 * Üst menü, footer ve paylaş düğmeleri rapora girmez; onlar her sayfada
 * aynıdır ve sayfanın kendi bağlantısı değildir.
 *
 * ORTAKLIK BAĞLANTISI ASLA AÇILMAZ. tp.media, pxf.io, tpx.li, CJ gibi
 * ağ adreslerini sunucudan çağırmak ağa SAHTE TIKLAMA düşürür ve sub_id
 * raporunu kirletir. Bunun yerine bağlantının gittiği ASIL sayfa (u=
 * parametresi) kontrol edilir; bozulan zaten o sayfa olur. Hedefi adres
 * içinde yazmayan kısa bağlantılar (skyscanner.pxf.io/zzvBL0 gibi) hiç
 * açılmaz, "kısa bağlantı" diye işaretlenir.
 *
 * HIZ: istekler paralel gider (Requests::request_multiple), sonuç her
 * adres için 12 saat saklanır. Ziyaretçi isteğinde bu dosya yüklenmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GBC_BR_ONBELLEK', 12 * HOUR_IN_SECONDS );
define( 'GBC_BR_SINIR', 150 );

/** Ortaklık ağlarının alan adları. Bunlar sunucudan ASLA çağrılmaz. */
function gbc_br_ag_alanlari() {
	return array( 'tp.media', 'tpx.li', 'pxf.io', 'emrldtp.cc', 'travelpayouts.com', 'tp.st',
		'jdoqocy.com', 'kqzyfj.com', 'anrdoezrs.net', 'dpbolvw.net', 'tkqlhce.com', 'awin1.com' );
}

/** Adres bir ortaklık AĞINDAN mı geçiyor? */
function gbc_br_ag_mi( $url ) {
	$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	if ( '' === $h ) { return false; }
	foreach ( gbc_br_ag_alanlari() as $a ) {
		if ( $h === $a || substr( $h, - ( strlen( $a ) + 1 ) ) === '.' . $a ) { return true; }
	}
	if ( function_exists( 'gbc_ort_cj_mi' ) && gbc_ort_cj_mi( $url ) ) { return true; }
	return false;
}

/** Adres bir ortaklık bağlantısı mı? (ağ ya da doğrudan satıcı) */
function gbc_br_ortaklik_mi( $url, $sinif = '' ) {
	if ( false !== strpos( ' ' . $sinif . ' ', ' gbc-in ' ) ) { return true; }
	if ( gbc_br_ag_mi( $url ) ) { return true; }
	$alanlar = function_exists( 'gbc_seo_ortaklik_alanlari' ) ? gbc_seo_ortaklik_alanlari()
		: array( 'booking.com', 'getyourguide.com', 'ferryhopper.com', 'omio.', 'discovercars.com', 'yesim.app', 'skyscanner' );
	$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	foreach ( $alanlar as $a ) {
		if ( '' !== $h && false !== strpos( $h, $a ) ) { return true; }
	}
	return false;
}

/**
 * Ortaklık bağlantısında sunucudan GÜVENLE açılabilecek adres.
 *
 * @return string Açılacak asıl sayfa; '' ise açılmaz (kısa bağlantı).
 */
function gbc_br_ortaklik_hedef( $url ) {
	$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	$q   = (string) wp_parse_url( $url, PHP_URL_QUERY );
	if ( '' !== $q ) {
		parse_str( $q, $p );
		foreach ( array( 'u', 'url', 'murl', 'deeplink' ) as $k ) {
			if ( ! empty( $p[ $k ] ) && is_string( $p[ $k ] ) && 0 === stripos( $p[ $k ], 'http' ) ) {
				return $p[ $k ];
			}
		}
	}
	/* Ağ adresi ama hedef yazmıyor: kısa bağlantı, AÇILMAZ. */
	if ( gbc_br_ag_mi( $url ) ) { return ''; }

	/* Doğrudan satıcı bağlantısı: takip parametreleri atılır, yalnız sayfa sınanır. */
	$s = wp_parse_url( $url );
	if ( empty( $s['host'] ) ) { return ''; }
	return ( isset( $s['scheme'] ) ? $s['scheme'] : 'https' ) . '://' . $s['host'] . ( isset( $s['path'] ) ? $s['path'] : '/' );
}

/** YouTube video kimliği (yoksa ''). */
function gbc_br_yt_kimlik( $url ) {
	$url = html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' );
	if ( preg_match( '~youtu\.be/([A-Za-z0-9_-]{11})~', $url, $m ) ) { return $m[1]; }
	if ( preg_match( '~youtube\.com/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})~', $url, $m ) ) { return $m[1]; }
	if ( preg_match( '~[?&]v=([A-Za-z0-9_-]{11})~', $url, $m ) ) { return $m[1]; }
	return '';
}

/** Paylaş düğmesi mi? (rapora girmez) */
function gbc_br_paylas_mi( $url, $sinif ) {
	if ( false !== strpos( ' ' . $sinif . ' ', ' gz-share-a ' ) ) { return true; }
	return (bool) preg_match( '~^https?://(www\.)?(wa\.me/|api\.whatsapp\.com/send|facebook\.com/sharer|twitter\.com/intent|x\.com/intent|t\.me/share|pinterest\.[a-z.]+/pin/create|linkedin\.com/share)~i', $url );
}

/** Sayfanın içerik alanını keser. Menü ve footer dışarıda kalır. */
function gbc_br_icerik_alani( $html ) {
	$bas = strpos( $html, 'entry-content' );
	if ( false === $bas ) { return $html; }
	$bas = strrpos( substr( $html, 0, $bas ), '<' );
	$son = strlen( $html );
	foreach ( array( 'id="sefe-sor"', 'id="comments"', '<footer' ) as $isaret ) {
		$k = strpos( $html, $isaret, $bas );
		if ( false !== $k && $k < $son ) { $son = $k; }
	}
	return substr( $html, (int) $bas, $son - (int) $bas );
}

/**
 * İçerikteki bağlantıları dört gruba ayırır.
 *
 * @return array( ortaklik => [], youtube => [], dis => [], ic => [] )
 *   Her kayıt: url, metin, adet, test (açılacak adres), tur, rel, not
 */
function gbc_br_ayikla( $html ) {
	$g = array( 'ortaklik' => array(), 'youtube' => array(), 'dis' => array(), 'ic' => array() );
	$alan = gbc_br_icerik_alani( (string) $html );
	if ( '' === trim( $alan ) ) { return $g; }

	$ev = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	if ( ! preg_match_all( '~<a\b([^>]*)>(.*?)</a>~is', $alan, $ma, PREG_SET_ORDER ) ) { return $g; }

	foreach ( $ma as $a ) {
		$nit = $a[1];
		if ( ! preg_match( '~\bhref\s*=\s*(["\'])(.*?)\1~is', $nit, $mh ) ) { continue; }
		$url = html_entity_decode( trim( $mh[2] ), ENT_QUOTES, 'UTF-8' );
		if ( '' === $url || '#' === $url[0] || preg_match( '~^(mailto|tel|javascript|sms):~i', $url ) ) { continue; }

		$sinif = preg_match( '~\bclass\s*=\s*(["\'])(.*?)\1~is', $nit, $mc ) ? $mc[2] : '';
		$rel   = preg_match( '~\brel\s*=\s*(["\'])(.*?)\1~is', $nit, $mr ) ? strtolower( $mr[2] ) : '';
		$aff   = preg_match( '~\bdata-aff\s*=\s*(["\'])(.*?)\1~is', $nit, $mf ) ? $mf[2] : '';
		if ( gbc_br_paylas_mi( $url, $sinif ) ) { continue; }

		$metin = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $a[2] ) ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $metin ) > 70 ) { $metin = mb_substr( $metin, 0, 68 ) . '…'; }

		if ( 0 === strpos( $url, '//' ) ) { $url = 'https:' . $url; }
		if ( '/' === $url[0] ) { $url = home_url( $url ); }
		$h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $h ) { continue; }

		/* 1) İç bağlantı */
		if ( $h === $ev || 'www.' . $h === $ev || $h === 'www.' . $ev ) {
			$yol = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( '' === $yol ) { $yol = '/'; }
			if ( preg_match( '~\.(jpe?g|png|webp|gif|svg|avif|pdf|mp4)$~i', $yol ) ) { continue; }
			if ( preg_match( '~^/(wp-admin|wp-login\.php|feed)~', $yol ) ) { continue; }
			$anahtar = 'ic|' . $yol;
			gbc_br_ekle( $g['ic'], $anahtar, array( 'url' => home_url( $yol ), 'metin' => $metin, 'test' => home_url( $yol ), 'tur' => 'ic', 'rel' => $rel ) );
			continue;
		}

		/* 2) Ortaklık */
		if ( gbc_br_ortaklik_mi( $url, $sinif ) ) {
			$hedef = gbc_br_ortaklik_hedef( $url );
			$kayit = array( 'url' => $url, 'metin' => $metin, 'test' => $hedef, 'tur' => 'ortaklik', 'rel' => $rel,
				'hedef' => $hedef, 'ag' => gbc_br_ag_mi( $url ), 'aff' => $aff );
			gbc_br_ekle( $g['ortaklik'], 'ort|' . $url, $kayit );
			continue;
		}

		/* 3) YouTube */
		if ( preg_match( '~(^|\.)(youtube\.com|youtu\.be|youtube-nocookie\.com)$~', $h ) ) {
			$vid = gbc_br_yt_kimlik( $url );
			if ( '' !== $vid ) {
				$test = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $vid );
				gbc_br_ekle( $g['youtube'], 'yt|' . $vid, array( 'url' => 'https://www.youtube.com/watch?v=' . $vid, 'metin' => $metin,
					'test' => $test, 'tur' => 'youtube', 'rel' => $rel, 'vid' => $vid ) );
			} else {
				$yal = strtok( $url, '?' );
				gbc_br_ekle( $g['youtube'], 'yt|' . rtrim( strtolower( $yal ), '/' ), array( 'url' => $yal, 'metin' => $metin,
					'test' => $yal, 'tur' => 'dis', 'rel' => $rel ) );
			}
			continue;
		}

		/* 4) Diğer dış */
		gbc_br_ekle( $g['dis'], 'dis|' . rtrim( strtolower( $url ), '/' ), array( 'url' => $url, 'metin' => $metin,
			'test' => $url, 'tur' => 'dis', 'rel' => $rel ) );
	}
	return $g;
}

/** Aynı adres ikinci kez geçerse sayacı artırır, ilk metni tutar. */
function gbc_br_ekle( &$liste, $anahtar, $kayit ) {
	if ( isset( $liste[ $anahtar ] ) ) {
		$liste[ $anahtar ]['adet']++;
		if ( '' === $liste[ $anahtar ]['metin'] && '' !== $kayit['metin'] ) { $liste[ $anahtar ]['metin'] = $kayit['metin']; }
		return;
	}
	$kayit['adet'] = 1;
	$liste[ $anahtar ] = $kayit;
}

/**
 * HTTP cevabından hüküm.
 *
 * @return array( durum, not )  durum: iyi | yon | engel | kirik | ulasilamadi | kisa
 */
function gbc_br_hukum( $kod, $hata, $tur, $konum = '' ) {
	$kod = (int) $kod;
	if ( '' !== (string) $hata ) { return array( 'ulasilamadi', (string) $hata ); }
	if ( 'youtube' === $tur ) {
		if ( 200 === $kod ) { return array( 'iyi', 'Video yayında.' ); }
		if ( 401 === $kod || 403 === $kod ) { return array( 'iyi', 'Video yayında (başka sitede gömme kapalı).' ); }
		if ( 400 === $kod || 404 === $kod ) { return array( 'kirik', 'Video silinmiş ya da gizli.' ); }
	}
	if ( $kod >= 200 && $kod < 300 ) { return array( 'iyi', 'HTTP ' . $kod ); }
	if ( $kod >= 300 && $kod < 400 ) {
		if ( 'ic' === $tur ) {
			return array( 'yon', 'HTTP ' . $kod . ( $konum ? ' → ' . $konum : '' ) . ' · bağlantıyı son adrese çevir.' );
		}
		return array( 'iyi', 'HTTP ' . $kod . ' — yönlendiriyor' );
	}
	if ( 403 === $kod || 429 === $kod || 999 === $kod ) {
		return array( 'engel', 'HTTP ' . $kod . ' — site sunucu isteğini bot sayıyor. Tarayıcıda açılır.' );
	}
	if ( 404 === $kod || 410 === $kod ) { return array( 'kirik', 'HTTP ' . $kod . ' — sayfa yok.' ); }
	if ( $kod >= 500 ) { return array( 'ulasilamadi', 'HTTP ' . $kod . ' — karşı sunucu hata veriyor.' ); }
	if ( 0 === $kod ) { return array( 'ulasilamadi', 'cevap yok' ); }
	return array( 'kirik', 'HTTP ' . $kod );
}

function gbc_br_onbellek_anahtar( $test, $tur ) {
	return 'gbc_br_' . md5( $tur . '|' . $test );
}

/**
 * Kayıtları sınar. Önbellekte olan tekrar çağrılmaz.
 *
 * @param array $kayitlar tur/test içeren kayıtlar (referansla doldurulur: durum, kod, not, zaman)
 * @param bool  $taze     true ise önbellek yok sayılır.
 */
function gbc_br_sina( &$kayitlar, $taze = false ) {
	$bekleyen = array();
	$n = 0;
	foreach ( $kayitlar as $k => $r ) {
		if ( '' === (string) $r['test'] ) {
			$kayitlar[ $k ] += array( 'durum' => 'kisa', 'kod' => 0,
				'not' => 'Kısa bağlantı: hedefi adreste yazmıyor. Sahte tıklama olmasın diye açılmadı; ortaklık panelinden doğrula.', 'zaman' => 0 );
			continue;
		}
		$ob = gbc_br_onbellek_anahtar( $r['test'], $r['tur'] );
		$c  = $taze ? false : get_transient( $ob );
		if ( is_array( $c ) ) {
			$kayitlar[ $k ] = array_merge( $r, $c );
			continue;
		}
		if ( $n++ >= GBC_BR_SINIR ) {
			$kayitlar[ $k ] += array( 'durum' => '', 'kod' => 0, 'not' => 'Sınır aşıldı, bu tur denenmedi.', 'zaman' => 0 );
			continue;
		}
		$bekleyen[ $k ] = $r;
	}
	if ( ! $bekleyen ) { return; }

	$sonuc = gbc_br_istek_paralel( $bekleyen );
	foreach ( $sonuc as $k => $s ) {
		list( $durum, $not ) = gbc_br_hukum( $s['kod'], $s['hata'], $bekleyen[ $k ]['tur'], $s['konum'] );
		$c = array( 'durum' => $durum, 'kod' => (int) $s['kod'], 'not' => $not, 'zaman' => time() );
		/* Ulaşılamayan sonuç kısa saklanır, bir sonraki açılışta yeniden denensin. */
		set_transient( gbc_br_onbellek_anahtar( $bekleyen[ $k ]['test'], $bekleyen[ $k ]['tur'] ), $c,
			'ulasilamadi' === $durum ? HOUR_IN_SECONDS : GBC_BR_ONBELLEK );
		$kayitlar[ $k ] = array_merge( $kayitlar[ $k ], $c );
	}
}

/**
 * İstekleri paralel atar.
 *
 * @return array anahtar => array( kod, hata, konum )
 */
/**
 * v1.43.1: İç bağlantıyı istek atmadan çözer (sitenin kendisine yük bindirmesin).
 * Yayındaki bir yazı/sayfaysa: adres aynıysa 200, farklıysa 301 → güncel adres.
 * Bulunamazsa null: o zaman HTTP ile (önbellekten) sorulur.
 */
function gbc_br_ic_yerel( $url ) {
	if ( ! function_exists( 'url_to_postid' ) ) { return null; }
	$yol = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	$yol = '/' . trim( $yol, '/' ) . '/';
	if ( '//' === $yol ) { return array( 'kod' => 200, 'hata' => '', 'konum' => '' ); }
	$pid = (int) url_to_postid( $url );
	if ( ! $pid || 'publish' !== get_post_status( $pid ) ) { return null; }
	$pl = (string) get_permalink( $pid );
	$py = '/' . trim( (string) wp_parse_url( $pl, PHP_URL_PATH ), '/' ) . '/';
	if ( '//' === $py ) { $py = '/'; }
	return $py === $yol ? array( 'kod' => 200, 'hata' => '', 'konum' => '' ) : array( 'kod' => 301, 'hata' => '', 'konum' => $pl );
}

function gbc_br_istek_paralel( $kayitlar ) {
	$sonuc_yerel = array();
	foreach ( $kayitlar as $k => $r ) {
		if ( 'ic' !== $r['tur'] ) { continue; }
		$y = gbc_br_ic_yerel( $r['test'] );
		if ( is_array( $y ) ) { $sonuc_yerel[ $k ] = $y; unset( $kayitlar[ $k ] ); }
	}
	if ( ! $kayitlar ) { return $sonuc_yerel; }
	$ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	$bas = array( 'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8', 'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8' );

	$sinif = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
	$sonuc = array();

	if ( '' !== $sinif ) {
		$istek = array();
		foreach ( $kayitlar as $k => $r ) {
			$ic = ( 'ic' === $r['tur'] );
			$istek[ $k ] = array(
				'url'     => $r['test'],
				'type'    => $ic ? 'HEAD' : 'GET',
				'headers' => $ic ? array() : $bas, /* v1.43.1: iç link önbellekten; önbelleği atlamak her linkte PHP+veritabanı açıyordu */
				'options' => array(
					'timeout'          => 12,
					'connect_timeout'  => 8,
					'useragent'        => $ua,
					'follow_redirects' => ! $ic,
					'redirects'        => 6,
					'verify'           => false,
					'max_bytes'        => 131072,
				),
			);
		}
		/* v1.43.1: hepsi birden değil, 4'erli gruplar hâlinde. */
		$cevap = array();
		foreach ( array_chunk( $istek, 4, true ) as $grup ) {
			try {
				$cevap += (array) call_user_func( array( $sinif, 'request_multiple' ), $grup, array() );
			} catch ( \Exception $e ) {
				continue;
			}
		}
		foreach ( $kayitlar as $k => $r ) {
			$c = isset( $cevap[ $k ] ) ? $cevap[ $k ] : null;
			if ( is_object( $c ) && isset( $c->status_code ) ) {
				$konum = '';
				if ( isset( $c->headers['location'] ) ) { $konum = (string) $c->headers['location']; }
				$sonuc[ $k ] = array( 'kod' => (int) $c->status_code, 'hata' => '', 'konum' => $konum );
			} elseif ( $c instanceof \Exception ) {
				$sonuc[ $k ] = array( 'kod' => 0, 'hata' => $c->getMessage(), 'konum' => '' );
			} else {
				$sonuc[ $k ] = array( 'kod' => 0, 'hata' => 'cevap yok', 'konum' => '' );
			}
			/* HEAD'e 405/501 dönen sunucuya GET ile bir daha bakılır. */
			if ( 'ic' === $r['tur'] && in_array( $sonuc[ $k ]['kod'], array( 405, 501 ), true ) ) {
				$sonuc[ $k ] = gbc_br_istek_tek( $r, $ua, $bas, 'GET' );
			}
		}
		return $sonuc + $sonuc_yerel;
	}

	foreach ( $kayitlar as $k => $r ) {
		$sonuc[ $k ] = gbc_br_istek_tek( $r, $ua, $bas, 'ic' === $r['tur'] ? 'HEAD' : 'GET' );
	}
	return $sonuc + $sonuc_yerel;
}

/** Tek istek (yedek yol). */
function gbc_br_istek_tek( $r, $ua, $bas, $yontem ) {
	$ic = ( 'ic' === $r['tur'] );
	$c = wp_remote_request( $r['test'], array(
		'method'              => $yontem,
		'timeout'             => 12,
		'redirection'         => $ic ? 0 : 6,
		'user-agent'          => $ua,
		'headers'             => $bas,
		'sslverify'           => false,
		'limit_response_size' => 131072,
	) );
	if ( is_wp_error( $c ) ) { return array( 'kod' => 0, 'hata' => $c->get_error_message(), 'konum' => '' ); }
	return array( 'kod' => (int) wp_remote_retrieve_response_code( $c ), 'hata' => '',
		'konum' => (string) wp_remote_retrieve_header( $c, 'location' ) );
}

/** Tek sayfanın tam raporu: ayıkla + sına. */
function gbc_br_rapor( $html, $taze = false ) {
	$g = gbc_br_ayikla( $html );
	/* Hepsini tek listede sına ki paralel gitsin. */
	$hepsi = array();
	foreach ( $g as $grup => $liste ) {
		foreach ( $liste as $k => $r ) { $hepsi[ $grup . '#' . $k ] = $r; }
	}
	gbc_br_sina( $hepsi, $taze );
	foreach ( $hepsi as $gk => $r ) {
		list( $grup, $k ) = explode( '#', $gk, 2 );
		$g[ $grup ][ $k ] = $r;
	}
	gbc_br_yapi_ekle( $g['ortaklik'] );
	return $g;
}

/**
 * Travelpayouts sabitleri. Değişirse filtreyle ezilir.
 * Kampanya => p eşleşmesi ölçülerek alındı (Sorrento 31232, 30 Eylül 2026).
 */
function gbc_br_tp_sabit() {
	return apply_filters( 'gbc_br_tp_sabit', array(
		'marker'   => '767959',
		'trs'      => '565047',
		'kampanya' => array( '84' => '2076', '91' => '2078', '108' => '3965', '117' => '3555' ),
		'ad'       => array( '84' => 'Booking', '91' => 'Omio', '108' => 'GetYourGuide', '117' => 'DiscoverCars' ),
	) );
}

/**
 * Ortaklık bağlantısının YAPISI doğru mu — kazanç bize yansır mı?
 * Hiç istek atmaz, yalnız adresi okur.
 *
 * @param string $url      Bağlantı.
 * @param string $aff      data-aff (defter kimliği), yoksa ''.
 * @param array  $sub_sayac Sayfadaki sub_id => adet.
 * @return array( 'yapi' => ok|hata|bilinmiyor, 'yapi_not' => string[] )
 */
function gbc_br_yapi( $url, $aff, $sub_sayac ) {
	$not = array();
	$hata = false;
	$h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $p );
	$al = static function ( $k ) use ( $p ) { return isset( $p[ $k ] ) && is_string( $p[ $k ] ) ? trim( $p[ $k ] ) : ''; };

	if ( 'tp.media' === $h ) {
		$t = gbc_br_tp_sabit();
		if ( $al( 'marker' ) !== $t['marker'] ) { $hata = true; $not[] = 'marker ' . ( '' === $al( 'marker' ) ? 'yok' : $al( 'marker' ) ) . ' (olması gereken ' . $t['marker'] . ') — kazanç başka hesaba ya da hiçbir yere gider'; }
		if ( $al( 'trs' ) !== $t['trs'] ) { $hata = true; $not[] = 'trs ' . ( '' === $al( 'trs' ) ? 'yok' : $al( 'trs' ) ) . ' (olması gereken ' . $t['trs'] . ')'; }
		$c = $al( 'campaign_id' );
		if ( '' === $c || '' === $al( 'p' ) ) {
			$hata = true; $not[] = 'campaign_id ya da p eksik';
		} elseif ( isset( $t['kampanya'][ $c ] ) && $t['kampanya'][ $c ] !== $al( 'p' ) ) {
			$hata = true; $not[] = $t['ad'][ $c ] . ' için p=' . $t['kampanya'][ $c ] . ' olmalı, ' . $al( 'p' ) . ' yazılmış';
		}
		$u = $al( 'u' );
		if ( '' === $u || 0 !== stripos( $u, 'http' ) ) { $hata = true; $not[] = 'hedef adres (u=) yok ya da bozuk'; }
		$sub = $al( 'sub_id' );
		if ( '' === $sub ) {
			$hata = true; $not[] = 'sub_id yok — hangi sayfadan geldiği raporda görünmez';
		} else {
			if ( '' !== $aff && $sub !== $aff ) { $hata = true; $not[] = 'sub_id (' . $sub . ') defter kimliğiyle (' . $aff . ') aynı değil'; }
			if ( isset( $sub_sayac[ $sub ] ) && $sub_sayac[ $sub ] > 1 ) { $not[] = 'aynı sub_id sayfada ' . (int) $sub_sayac[ $sub ] . ' farklı bağlantıda var'; }
		}
	} elseif ( false !== strpos( $h, 'tpx.li' ) ) {
		$hata = true; $not[] = 'kısa link (tpx.li): sub_id rapora düşmüyor, uzun tp.media bağlantısıyla değiştir';
	} elseif ( false !== strpos( $h, 'pxf.io' ) ) {
		$sub = '' !== $al( 'subId1' ) ? $al( 'subId1' ) : $al( 'subid1' );
		if ( '' === $sub ) { $hata = true; $not[] = 'subId1 yok — hangi sayfadan geldiği raporda görünmez'; }
		else { $not[] = 'Impact bağlantısı, subId1=' . $sub; }
	} elseif ( function_exists( 'gbc_ort_cj_mi' ) && gbc_ort_cj_mi( $url ) ) {
		if ( '' === $al( 'sid' ) ) { $hata = true; $not[] = 'CJ bağlantısında sid yok'; }
		else { $not[] = 'CJ bağlantısı, sid=' . $al( 'sid' ); }
	} else {
		$not[] = 'ağ parametresi yok; kazanç ancak satıcının kendi ortaklık kodu varsa yansır';
		return array( 'yapi' => 'bilinmiyor', 'yapi_not' => $not );
	}
	if ( '' === $aff ) { $not[] = 'elle yazılmış (kısa koddan gelmiyor, defter takibi dışında)'; }
	return array( 'yapi' => $hata ? 'hata' : 'ok', 'yapi_not' => $not );
}

/** Ortaklık grubunun her satırına yapı sonucunu ekler. */
function gbc_br_yapi_ekle( &$liste ) {
	$sub = array();
	foreach ( $liste as $r ) {
		parse_str( (string) wp_parse_url( $r['url'], PHP_URL_QUERY ), $p );
		$s = isset( $p['sub_id'] ) && is_string( $p['sub_id'] ) ? $p['sub_id'] : '';
		if ( '' !== $s ) { $sub[ $s ] = isset( $sub[ $s ] ) ? $sub[ $s ] + 1 : 1; }
	}
	foreach ( $liste as $k => $r ) {
		$liste[ $k ] = array_merge( $r, gbc_br_yapi( $r['url'], isset( $r['aff'] ) ? $r['aff'] : '', $sub ) );
	}
}

/** Yapı rozeti. */
function gbc_br_yapi_rozet( $y ) {
	$s = array(
		'ok'         => array( '✓ doğru', '#1A7F37', '#E8F3EC' ),
		'hata'       => array( '✗ HATALI', '#B3261E', '#FBE7E7' ),
		'bilinmiyor' => array( '? doğrulanamadı', '#5C6470', '#F1EFEA' ),
	);
	$x = isset( $s[ $y ] ) ? $s[ $y ] : $s['bilinmiyor'];
	return '<span style="display:inline-block;white-space:nowrap;font-weight:700;font-size:12.5px;padding:2px 9px;border-radius:999px;'
		. 'color:' . esc_attr( $x[1] ) . ';background:' . esc_attr( $x[2] ) . '">' . esc_html( $x[0] ) . '</span>';
}

/** Rapor özeti: durum => adet. */
function gbc_br_ozet( $g ) {
	$o = array( 'toplam' => 0, 'iyi' => 0, 'yon' => 0, 'engel' => 0, 'kirik' => 0, 'ulasilamadi' => 0, 'kisa' => 0, 'yapi_hata' => 0, 'yapi_ok' => 0 );
	foreach ( $g as $liste ) {
		foreach ( $liste as $r ) {
			$o['toplam']++;
			if ( isset( $r['yapi'] ) && 'hata' === $r['yapi'] ) { $o['yapi_hata']++; }
			if ( isset( $r['yapi'] ) && 'ok' === $r['yapi'] ) { $o['yapi_ok']++; }
			$d = isset( $r['durum'] ) ? $r['durum'] : '';
			if ( isset( $o[ $d ] ) ) { $o[ $d ]++; }
		}
	}
	return $o;
}

/**
 * Grup × durum tablosu. Üstteki bütün sayılar buradan okunur;
 * "27 çalışıyor nereden geliyor" sorusunun cevabı bu tablodur.
 */
function gbc_br_matris_veri( $g ) {
	$sut = array( 'iyi', 'kirik', 'yon', 'engel', 'ulasilamadi', 'kisa' );
	$ad  = array( 'ortaklik' => 'Ortaklık', 'youtube' => 'YouTube', 'dis' => 'Diğer dış', 'ic' => 'İç' );
	$v = array();
	$top = array_fill_keys( array_merge( array( 'adres', 'gecis' ), $sut ), 0 );
	foreach ( $ad as $grup => $etiket ) {
		$sat = array_fill_keys( array_merge( array( 'adres', 'gecis' ), $sut ), 0 );
		foreach ( $g[ $grup ] as $r ) {
			$sat['adres']++;
			$sat['gecis'] += (int) $r['adet'];
			$d = isset( $r['durum'] ) ? $r['durum'] : '';
			if ( isset( $sat[ $d ] ) ) { $sat[ $d ]++; }
		}
		foreach ( $sat as $k => $n ) { $top[ $k ] += $n; }
		$v[ $grup ] = array( 'ad' => $etiket ) + $sat;
	}
	$v['toplam'] = array( 'ad' => 'Toplam' ) + $top;
	return $v;
}

function gbc_br_matris( $g ) {
	$v = gbc_br_matris_veri( $g );
	$bas = array( 'adres' => 'Link', 'gecis' => 'Sayfada geçiş', 'iyi' => '✓ Çalışıyor', 'kirik' => '✗ Ölü', 'yon' => '↪ Yönlendiren',
		'engel' => '⚠ Bot engeli', 'ulasilamadi' => '? Ulaşılamadı', 'kisa' => '– Kısa link' );
	$renk = array( 'iyi' => '#1A7F37', 'kirik' => '#B32D2E', 'yon' => '#8A6100' );
	echo '<table class="widefat" style="max-width:1000px;margin:12px 0"><thead><tr><th>' . esc_html__( 'Grup', 'gbc-core' ) . '</th>';
	foreach ( $bas as $b ) { echo '<th style="text-align:center">' . esc_html( $b ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $v as $grup => $sat ) {
		$t = ( 'toplam' === $grup );
		echo '<tr' . ( $t ? ' style="background:#F6F7F7;font-weight:700"' : '' ) . '><td>'
			. ( $t ? esc_html( $sat['ad'] ) : '<a href="#br-' . esc_attr( $grup ) . '">' . esc_html( $sat['ad'] ) . '</a>' ) . '</td>';
		foreach ( array_keys( $bas ) as $k ) {
			$n = (int) $sat[ $k ];
			$c = ( $n && isset( $renk[ $k ] ) ) ? ';color:' . $renk[ $k ] . ';font-weight:700' : ( $n ? '' : ';color:#B0B4BA' );
			echo '<td style="text-align:center' . $c . '">' . $n . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin:0 0 10px;color:#5C6470;font-size:12.5px">'
		. esc_html__( '"Link" aynı adresi bir kez sayar; "Sayfada geçiş" aynı link sayfada iki kez varsa ikisini de sayar. Bot engeli ve kısa link bozuk demek değildir: site sunucudan gelen kontrolü reddediyor ya da link hedefini adreste yazmıyor.', 'gbc-core' )
		. '</p>';
}

/** Ortaklık linklerinin program adları (hedef ya da ağ alanından). */
function gbc_br_programlar( $liste ) {
	$harita = array( 'getyourguide' => 'GetYourGuide', 'booking' => 'Booking', 'omio' => 'Omio', 'discovercars' => 'DiscoverCars',
		'skyscanner' => 'Skyscanner', 'ferryhopper' => 'Ferryhopper', 'yesim' => 'Yesim', 'hotellook' => 'Hotellook', 'aviasales' => 'Aviasales' );
	$say = array();
	foreach ( $liste as $r ) {
		$h = strtolower( ( ! empty( $r['hedef'] ) ? $r['hedef'] : '' ) . ' ' . $r['url'] );
		$bul = 'Diğer';
		foreach ( $harita as $k => $ad ) { if ( false !== strpos( $h, $k ) ) { $bul = $ad; break; } }
		$say[ $bul ] = isset( $say[ $bul ] ) ? $say[ $bul ] + 1 : 1;
	}
	arsort( $say );
	return $say;
}

/** Gruba göre sayım: benzersiz adres ve sayfada toplam geçiş. */
function gbc_br_sayim( $g ) {
	$o = array();
	foreach ( array( 'ortaklik', 'youtube', 'dis', 'ic' ) as $grup ) {
		$gecis = 0;
		foreach ( $g[ $grup ] as $r ) { $gecis += (int) $r['adet']; }
		$o[ $grup ] = array( 'adres' => count( $g[ $grup ] ), 'gecis' => $gecis );
	}
	return $o;
}

/**
 * Sayfanın KENDİ adresinin denetimi. İstek atmaz; adres ve canlı HTML okunur.
 *
 * @return array liste: array( ad, ok(bool), not )
 */
function gbc_br_url_denetim( $url, $html, $kod = 200 ) {
	$m    = array();
	$yol  = (string) wp_parse_url( $url, PHP_URL_PATH );
	$slug = trim( $yol, '/' );
	$son  = false !== strrpos( $slug, '/' ) ? substr( $slug, strrpos( $slug, '/' ) + 1 ) : $slug;

	$m[] = array( 'ad' => 'Adres açılıyor (HTTP 200)', 'ok' => ( 200 === (int) $kod ), 'not' => 'HTTP ' . (int) $kod );

	$tr = (bool) preg_match( '/[çğıöşüÇĞİÖŞÜ]/u', rawurldecode( $slug ) ) || (bool) preg_match( '/%[0-9a-f]{2}/i', $slug );
	$m[] = array( 'ad' => 'Türkçe karakter ve kodlanmış karakter yok', 'ok' => ! $tr,
		'not' => $tr ? 'ç ğ ı ö ş ü gibi harfler adreste %C3 diye kodlanır, paylaşılınca bozuk görünür' : '' );

	$duz = (bool) preg_match( '~^[a-z0-9-/]+$~', $slug );
	$m[] = array( 'ad' => 'Yalnız küçük harf, rakam ve tire', 'ok' => $duz,
		'not' => $duz ? '' : 'büyük harf, alt çizgi, nokta ya da boşluk var' );

	$cift = ( false !== strpos( $slug, '--' ) ) || '-' === substr( $son, -1 ) || '-' === substr( $son, 0, 1 );
	$m[] = array( 'ad' => 'Çift ya da uçta tire yok', 'ok' => ! $cift, 'not' => '' );

	$kopya = (bool) preg_match( '~-\d{1,2}$~', $son );
	$m[] = array( 'ad' => 'Kopya adres izi yok (-2, -3 ile bitmiyor)', 'ok' => ! $kopya,
		'not' => $kopya ? 'WordPress aynı adres varken sona sayı ekler; eski bir kopya ya da taslak olabilir' : '' );

	$yil = (bool) preg_match( '~(^|-)20\d\d(-|$)~', $son );
	$m[] = array( 'ad' => 'Adreste yıl yok', 'ok' => ! $yil,
		'not' => $yil ? 'yıl eskir; her yıl güncelleyince adres değiştirmek gerekir' : '' );

	$uz = function_exists( 'mb_strlen' ) ? mb_strlen( $son ) : strlen( $son );
	$kel = count( array_filter( explode( '-', $son ) ) );
	$m[] = array( 'ad' => 'Kısa ve okunur (en çok 60 karakter, 7 kelime)', 'ok' => ( $uz <= 60 && $kel <= 7 ),
		'not' => $uz . ' karakter, ' . $kel . ' kelime' );

	$can = '';
	if ( preg_match( '~<link[^>]+rel=["\']canonical["\'][^>]*>~i', $html, $mc ) && preg_match( '~href=["\']([^"\']+)~i', $mc[0], $mh ) ) {
		$can = html_entity_decode( $mh[1], ENT_QUOTES, 'UTF-8' );
	}
	$can_ok = ( '' !== $can && rtrim( $can, '/' ) === rtrim( $url, '/' ) );
	$m[] = array( 'ad' => 'Canonical bu adresi gösteriyor', 'ok' => $can_ok,
		'not' => '' === $can ? 'canonical etiketi yok' : ( $can_ok ? '' : 'canonical başka adres: ' . $can ) );

	$noindex = false;
	if ( preg_match_all( '~<meta[^>]+name=["\'](robots|googlebot)["\'][^>]*>~i', $html, $mr ) ) {
		foreach ( $mr[0] as $etiket ) { if ( false !== stripos( $etiket, 'noindex' ) ) { $noindex = true; } }
	}
	$m[] = array( 'ad' => 'Google\'a kapalı değil (noindex yok)', 'ok' => ! $noindex,
		'not' => $noindex ? 'sayfada noindex var; Google bu sayfayı dizine almaz' : '' );

	return $m;
}

/** Üst özet kartları: bağlantı ayrımı, meta, başlık, URL. Hepsi tıklanır, aşağıdaki ayrıntıya iner. */
function gbc_br_ust_kartlar( $g, $d, $url_m ) {
	$say = gbc_br_sayim( $g );
	$top = 0; $gec = 0;
	foreach ( $say as $x ) { $top += $x['adres']; $gec += $x['gecis']; }
	$lnk = static function ( $id, $metin ) { return '<a href="#' . esc_attr( $id ) . '" style="color:#1B4E9B;text-decoration:underline">' . esc_html( $metin ) . '</a>'; };
	$alt = $lnk( 'br-ortaklik', $say['ortaklik']['adres'] . ' ortaklık' ) . ' · '
		. $lnk( 'br-youtube', $say['youtube']['adres'] . ' YouTube' ) . ' · '
		. $lnk( 'br-dis', $say['dis']['adres'] . ' diğer dış' ) . ' · '
		. $lnk( 'br-ic', $say['ic']['adres'] . ' iç' )
		. '<br>' . esc_html( sprintf( __( 'sayfada %d kez geçiyor', 'gbc-core' ), $gec ) );
	$h = gbc_br_kart( __( 'Bağlantı (içerikte)', 'gbc-core' ), (string) $top, $alt );

	$o = gbc_br_ozet( $g );
	$bozuk = $o['kirik'] + $o['yon'];
	$h .= gbc_br_kart( __( 'Bağlantı sağlığı', 'gbc-core' ), $bozuk ? '✗ ' . $bozuk : '✓',
		esc_html( sprintf( __( '%1$d çalışıyor · %2$d ölü · %3$d yönlendiren · %4$d kontrol edilemedi', 'gbc-core' ),
			$o['iyi'], $o['kirik'], $o['yon'], $o['engel'] + $o['ulasilamadi'] + $o['kisa'] ) ),
		$bozuk ? '#B32D2E' : '#1A7F37', '#br-rapor' );

	/* Ortaklık kartı raporla aynı sayıyı söyler: link sayısı + sayfada geçiş. */
	$ort = $say['ortaklik'];
	$cok = 0;
	foreach ( $g['ortaklik'] as $r ) { if ( (int) $r['adet'] > 1 ) { $cok++; } }
	$prog = array();
	foreach ( gbc_br_programlar( $g['ortaklik'] ) as $pad => $pn ) { $prog[] = $pad . ' ' . $pn; }
	$h .= gbc_br_kart( __( 'Ortaklık linki', 'gbc-core' ), (string) $ort['adres'],
		esc_html( sprintf( __( 'sayfada %d kez geçiyor', 'gbc-core' ), $ort['gecis'] )
			. ( $cok ? ' · ' . sprintf( __( '%d link birden fazla yerde', 'gbc-core' ), $cok ) : '' ) )
		. ( $prog ? '<br>' . esc_html( implode( ' · ', $prog ) ) : '' ),
		$ort['adres'] ? '#1A7F37' : '#8A6100', '#br-ortaklik' );

	$ac = (string) $d['aciklama'];
	$au = function_exists( 'mb_strlen' ) ? mb_strlen( $ac ) : strlen( $ac );
	$ac_ok = ( $au >= 120 && $au <= 165 );
	$h .= gbc_br_kart( __( 'Meta açıklama', 'gbc-core' ), '' === $ac ? '✗ yok' : ( $ac_ok ? '✓' : '✗' ),
		esc_html( '' === $ac ? __( 'yazılmamış', 'gbc-core' ) : sprintf( __( '%d karakter (120–165 olmalı)', 'gbc-core' ), $au ) ),
		$ac_ok ? '#1A7F37' : '#B32D2E', '#gbc-k-meta' );

	$bt = (string) $d['baslik_etiketi'];
	$bu = function_exists( 'mb_strlen' ) ? mb_strlen( $bt ) : strlen( $bt );
	$bt_ok = ( $bu >= 30 && $bu <= 62 );
	$h .= gbc_br_kart( __( 'Başlık etiketi', 'gbc-core' ), $bt_ok ? '✓' : '✗',
		esc_html( sprintf( __( '%d karakter (30–62 olmalı)', 'gbc-core' ), $bu ) ), $bt_ok ? '#1A7F37' : '#B32D2E', '#gbc-k-meta' );

	$kalan = 0;
	foreach ( $url_m as $x ) { if ( ! $x['ok'] ) { $kalan++; } }
	$h .= gbc_br_kart( __( 'URL', 'gbc-core' ), $kalan ? '✗ ' . $kalan : '✓',
		esc_html( sprintf( 'HTTP %d · %s', (int) $d['kod'], function_exists( 'size_format' ) ? size_format( (int) $d['bayt'] ) : round( (int) $d['bayt'] / 1024 ) . ' KB' ) )
		. '<br>' . esc_html( $kalan ? sprintf( __( '%d kontrol kaldı', 'gbc-core' ), $kalan ) : sprintf( __( '%d kontrolün hepsi geçti', 'gbc-core' ), count( $url_m ) ) ),
		$kalan ? '#B32D2E' : '#1A7F37', '#gbc-k-url' );

	$bilgi = gbc_br_sema_bilgi( isset( $d['ham'] ) ? $d['ham'] : '' );
	$sd = function_exists( 'gbc_seo_sema_denetim' ) ? gbc_seo_sema_denetim( $d ) : array( 'eksik' => array() );
	$on = gbc_br_sema_oneri( isset( $d['ham'] ) ? $d['ham'] : '', $bilgi );
	$h .= gbc_br_kart( __( 'Şema', 'gbc-core' ), $sd['eksik'] ? '✗ ' . count( $sd['eksik'] ) : '✓',
		esc_html( sprintf( __( '%1$d tür basılıyor · %2$d eksik · %3$d öneri', 'gbc-core' ), count( $bilgi['tip'] ), count( $sd['eksik'] ), count( $on ) ) ),
		$sd['eksik'] ? '#B32D2E' : '#1A7F37', '#gbc-sema' );
	return $h;
}

/** URL kontrol tablosu. */
function gbc_br_url_tablo( $url_m ) {
	echo '<h2 style="margin-top:22px">' . esc_html__( 'URL kontrolü', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:900px"><tbody>';
	foreach ( $url_m as $x ) {
		echo '<tr><td style="width:60px">' . ( $x['ok']
			? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'GEÇTİ', 'gbc-core' ) . '</span>'
			: '<span style="color:#B32D2E;font-weight:700">' . esc_html__( 'KALDI', 'gbc-core' ) . '</span>' )
			. '</td><td>' . esc_html( $x['ad'] )
			. ( '' !== $x['not'] ? ' <span style="color:#5C6470;font-size:12.5px">— ' . esc_html( $x['not'] ) . '</span>' : '' )
			. '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Durum rozeti. */
function gbc_br_rozet( $durum ) {
	$s = array(
		'iyi'         => array( '✓ çalışıyor', '#1A7F37', '#E8F3EC' ),
		'yon'         => array( '↪ yönlendiriyor', '#8A6100', '#FCF6E8' ),
		'engel'       => array( '⚠ bot engeli', '#8A6100', '#FCF6E8' ),
		'kirik'       => array( '✗ ÖLÜ', '#B3261E', '#FBE7E7' ),
		'ulasilamadi' => array( '? ulaşılamadı', '#8A6100', '#FCF6E8' ),
		'kisa'        => array( '– kısa bağlantı', '#5C6470', '#F1EFEA' ),
		''            => array( '– denenmedi', '#5C6470', '#F1EFEA' ),
	);
	$x = isset( $s[ $durum ] ) ? $s[ $durum ] : $s[''];
	return '<span style="display:inline-block;white-space:nowrap;font-weight:700;font-size:12.5px;padding:2px 9px;border-radius:999px;'
		. 'color:' . esc_attr( $x[1] ) . ';background:' . esc_attr( $x[2] ) . '">' . esc_html( $x[0] ) . '</span>';
}

/** Sayfa Denetimi ekranındaki rapor bölümü. */
function gbc_br_taze_mi() {
	return isset( $_GET['br_taze'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gbc_br_taze' );
}

function gbc_br_ekran( $pid, $html, $g = null ) {
	$pid  = (int) $pid;
	if ( ! is_array( $g ) ) { $g = gbc_br_rapor( $html, gbc_br_taze_mi() ); }
	$o    = gbc_br_ozet( $g );

	echo '<h2 id="br-rapor" style="margin-top:26px">' . esc_html__( 'Bağlantı raporu', 'gbc-core' ) . '</h2>';
	echo '<p style="max-width:1000px;color:#444;margin-top:4px">'
		. esc_html__( 'Yalnız içerik alanındaki bağlantılar (menü, footer ve paylaş düğmeleri hariç). Sonuçlar 12 saat saklanır. Ortaklık bağlantısının kendisi açılmaz; sahte tıklama olmasın diye gittiği asıl sayfa kontrol edilir. Link yapısı (marker, sub_id, kampanya) ise adresten okunur: doğruysa kazanç bu sayfaya yazılır.', 'gbc-core' )
		. '</p>';

	gbc_br_matris( $g );

	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-seo-sayfa&pid=' . $pid . '&br_taze=1' ), 'gbc_br_taze' ) ) . '">'
		. esc_html__( 'Bağlantıları şimdi yeniden kontrol et', 'gbc-core' ) . '</a></p>';

	$basliklar = array(
		'ortaklik' => __( 'Ortaklık bağlantıları', 'gbc-core' ),
		'youtube'  => __( 'YouTube bağlantıları', 'gbc-core' ),
		'dis'      => __( 'Diğer dış bağlantılar', 'gbc-core' ),
		'ic'       => __( 'İç bağlantılar', 'gbc-core' ),
	);
	$sira = array( 'kirik' => 0, 'yon' => 1, 'ulasilamadi' => 2, 'engel' => 3, 'kisa' => 4, '' => 5, 'iyi' => 6 );

	foreach ( $basliklar as $grup => $baslik ) {
		$liste = array_values( $g[ $grup ] );
		usort( $liste, static function ( $a, $b ) use ( $sira ) {
			$x = isset( $sira[ $a['durum'] ] ) ? $sira[ $a['durum'] ] : 5;
			$y = isset( $sira[ $b['durum'] ] ) ? $sira[ $b['durum'] ] : 5;
			if ( isset( $a['yapi'] ) && 'hata' === $a['yapi'] ) { $x = -1; }
			if ( isset( $b['yapi'] ) && 'hata' === $b['yapi'] ) { $y = -1; }
			return $x - $y;
		} );
		echo '<h3 id="br-' . esc_attr( $grup ) . '" style="margin:20px 0 8px;scroll-margin-top:50px">' . esc_html( $baslik ) . ' <span style="color:#5C6470;font-weight:400">(' . count( $liste ) . ')</span></h3>';
		if ( ! $liste ) {
			echo '<p style="margin:0"><em>' . esc_html__( 'Bu grupta bağlantı yok.', 'gbc-core' ) . '</em></p>';
			continue;
		}
		$ort = ( 'ortaklik' === $grup );
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
			. '<th style="width:140px">' . esc_html__( 'Sayfa durumu', 'gbc-core' ) . '</th>'
			. ( $ort ? '<th style="width:130px">' . esc_html__( 'Link yapısı', 'gbc-core' ) . '</th>' : '' )
			. '<th style="width:260px">' . esc_html__( 'Bağlantı metni', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Adres', 'gbc-core' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $liste as $r ) {
			$adres = '<a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener" style="word-break:break-all">' . esc_html( $r['url'] ) . '</a>';
			$ek = array();
			if ( 'ortaklik' === $grup ) {
				if ( ! empty( $r['hedef'] ) && $r['hedef'] !== $r['url'] ) {
					$ek[] = esc_html__( 'Kontrol edilen sayfa:', 'gbc-core' ) . ' <a href="' . esc_url( $r['hedef'] ) . '" target="_blank" rel="noopener" style="word-break:break-all">' . esc_html( $r['hedef'] ) . '</a>';
				}
				if ( false === strpos( (string) $r['rel'], 'sponsored' ) ) {
					$ek[] = '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'rel="sponsored" eksik', 'gbc-core' ) . '</span>';
				}
			}
			if ( (int) $r['adet'] > 1 ) {
				$ek[] = esc_html( sprintf( __( 'Sayfada %d kez geçiyor.', 'gbc-core' ), (int) $r['adet'] ) );
			}
			if ( ! empty( $r['not'] ) && 'iyi' !== $r['durum'] ) {
				$ek[] = esc_html( $r['not'] );
			}
			if ( $ort && ! empty( $r['yapi_not'] ) ) {
				foreach ( $r['yapi_not'] as $yn ) { $ek[] = ( 'hata' === $r['yapi'] ? '<span style="color:#B32D2E">' . esc_html( $yn ) . '</span>' : esc_html( $yn ) ); }
			}
			echo '<tr><td>' . gbc_br_rozet( $r['durum'] ) . '</td>'
				. ( $ort ? '<td>' . gbc_br_yapi_rozet( isset( $r['yapi'] ) ? $r['yapi'] : '' ) . '</td>' : '' )
				. '<td>' . esc_html( '' !== $r['metin'] ? $r['metin'] : '—' ) . '</td>'
				. '<td>' . $adres . ( $ek ? '<div style="color:#5C6470;font-size:12.5px;margin-top:3px">' . implode( ' · ', $ek ) . '</div>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

/* ============================================================
   v1.37.2 · 30 Eylül 2026 — PUANLI GBC SKORU, TIKLANABİLİR KARTLAR,
   ŞEMA BALONCUKLARI
   ------------------------------------------------------------
   Skor 100 üzerinden, beş başlıkta:
     İçerik 25 · Meta 15 · URL 15 · Bağlantılar 30 · Şema 15
   Her maddenin ağırlığı tabloda yazar; "neden 88" sorusunun
   cevabı ekranda görünür. Sunucudan doğrulanamayan (bot engeli,
   kısa bağlantı) hiçbir şey puan kırmaz.
   ============================================================ */

/** Kart: alt satırda HTML (bağlantılar) kabul eder, istenirse bütün kart tıklanır. */
function gbc_br_kart( $baslik, $deger, $alt_html = '', $renk = '#14181F', $href = '' ) {
	$ic = '<div style="font-size:12.5px;font-weight:600;color:#5C6470">' . esc_html( $baslik ) . '</div>'
		. '<div style="font-size:28px;font-weight:700;line-height:1.2;color:' . esc_attr( $renk ) . '">' . esc_html( $deger ) . '</div>'
		. ( '' !== $alt_html ? '<div style="font-size:12.5px;color:#5C6470;margin-top:2px;line-height:1.6">' . $alt_html . '</div>' : '' );
	$stil = 'background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:16px 20px;min-width:150px;flex:1;display:block;text-decoration:none;color:inherit';
	if ( '' !== $href ) {
		return '<a href="' . esc_attr( $href ) . '" style="' . $stil . '" title="' . esc_attr__( 'Aşağıda ayrıntısını gör', 'gbc-core' ) . '">' . $ic . '</a>';
	}
	return '<div style="' . $stil . '">' . $ic . '</div>';
}

/** Şemadaki tür sayıları ve ayrıntılar (canlı HTML'den). */
function gbc_br_sema_bilgi( $html ) {
	$b = array( 'tip' => array(), 'cekim' => 0, 'geo' => false, 'bozuk' => 0 );
	if ( ! preg_match_all( '/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', (string) $html, $ms ) ) { return $b; }
	foreach ( $ms[1] as $blok ) {
		$j = json_decode( trim( $blok ), true );
		if ( ! is_array( $j ) ) { $b['bozuk']++; continue; }
		$dugum = isset( $j['@graph'] ) && is_array( $j['@graph'] ) ? $j['@graph'] : array( $j );
		foreach ( $dugum as $dg ) {
			if ( ! is_array( $dg ) || empty( $dg['@type'] ) ) { continue; }
			foreach ( (array) $dg['@type'] as $t ) { $b['tip'][ $t ] = isset( $b['tip'][ $t ] ) ? $b['tip'][ $t ] + 1 : 1; }
			if ( ! empty( $dg['includesAttraction'] ) ) { $b['cekim'] += count( (array) $dg['includesAttraction'] ); }
			if ( ! empty( $dg['geo'] ) ) { $b['geo'] = true; }
		}
	}
	return $b;
}

/**
 * Eklenebilecek şemalar — zorunlu değil, sayfada karşılığı varsa önerilir.
 * Sahte alarm olmasın diye yalnız tetikleyicisi olan sayfada çıkar.
 */
function gbc_br_sema_oneri( $html, $bilgi ) {
	$o = array();
	$gezi = ( false !== strpos( $html, 'gz-full-wrapper' ) );
	if ( $gezi && isset( $bilgi['tip']['TouristDestination'] ) && 0 === (int) $bilgi['cekim']
		&& false !== strpos( $html, 'gz-place-card' ) ) {
		$o['TouristAttraction'] = __( 'Gezilecek yer kartları var ama şemaya tek tek yer olarak girmiyor. gbc_schema_yerler alanı doldurulunca her durak (plaj, müze, manzara noktası) Google\'a ayrı yer olarak bildirilir.', 'gbc-core' );
	}
	if ( $gezi && ! $bilgi['geo'] ) {
		$o['GeoCoordinates'] = __( 'Şehrin koordinatı şemada yok. gbc_schema_geo alanına "enlem,boylam" yazılınca TouristDestination\'a konum eklenir.', 'gbc-core' );
	}
	if ( false !== strpos( $html, 'v1-card-item' ) && ! isset( $bilgi['tip']['ItemList'] ) ) {
		$o['ItemList'] = __( 'Liste sayfası; kartlar sıralı liste olarak işaretlenebilir.', 'gbc-core' );
	}
	return $o;
}

/** Şema baloncukları: basılan (yeşil) · eksik (kırmızı) · önerilen (mavi, kesikli). */
function gbc_br_sema_blok( $d ) {
	$html  = isset( $d['ham'] ) ? (string) $d['ham'] : '';
	$bilgi = gbc_br_sema_bilgi( $html );
	$sd    = function_exists( 'gbc_seo_sema_denetim' ) ? gbc_seo_sema_denetim( $d ) : array( 'eksik' => array(), 'beklenen' => array() );
	$oneri = gbc_br_sema_oneri( $html, $bilgi );
	$yuv   = 'display:inline-block;border-radius:999px;padding:5px 13px;margin:0 6px 8px 0;font-size:13px;font-weight:600;';

	echo '<h2 id="gbc-sema" style="margin-top:22px">' . esc_html__( 'Şema', 'gbc-core' ) . '</h2>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:14px 18px;max-width:1000px">';

	echo '<div style="font-size:12.5px;font-weight:600;color:#5C6470;margin-bottom:6px">' . esc_html__( 'Basılan şemalar', 'gbc-core' ) . '</div>';
	if ( $bilgi['tip'] ) {
		foreach ( $bilgi['tip'] as $t => $n ) {
			echo '<span style="' . $yuv . 'background:#E8F3EC;color:#1A7F37;border:1px solid #BFDCC8">✓ ' . esc_html( $t )
				. ( $n > 1 ? ' <span style="font-weight:400">×' . (int) $n . '</span>' : '' ) . '</span>';
		}
	} else {
		echo '<p style="margin:0 0 8px;color:#B32D2E;font-weight:600">' . esc_html__( 'Hiç şema basılmıyor.', 'gbc-core' ) . '</p>';
	}
	if ( $bilgi['bozuk'] ) {
		echo '<p style="margin:4px 0 8px;color:#B32D2E;font-weight:600">' . esc_html( sprintf( __( '%d şema bloğu bozuk JSON, Google okuyamaz.', 'gbc-core' ), (int) $bilgi['bozuk'] ) ) . '</p>';
	}

	echo '<div style="font-size:12.5px;font-weight:600;color:#5C6470;margin:10px 0 6px">' . esc_html__( 'Olması gereken ama eksik', 'gbc-core' ) . '</div>';
	if ( $sd['eksik'] ) {
		foreach ( $sd['eksik'] as $t => $neden ) {
			echo '<span style="' . $yuv . 'background:#FBE7E7;color:#B3261E;border:1px solid #F0C2BF" title="' . esc_attr( $neden ) . '">✗ ' . esc_html( $t ) . '</span>';
		}
		echo '<div style="font-size:12.5px;color:#5C6470">';
		foreach ( $sd['eksik'] as $t => $neden ) { echo '<div>· <strong>' . esc_html( $t ) . '</strong>: ' . esc_html( $neden ) . '</div>'; }
		echo '</div>';
	} else {
		echo '<span style="color:#1A7F37;font-weight:600;font-size:13px">' . esc_html( sprintf( __( 'Yok. Beklenen %d şemanın hepsi basılıyor.', 'gbc-core' ), count( $sd['beklenen'] ) ) ) . '</span>';
	}

	echo '<div style="font-size:12.5px;font-weight:600;color:#5C6470;margin:14px 0 6px">' . esc_html__( 'Öneriler — eklenebilir', 'gbc-core' ) . '</div>';
	if ( $oneri ) {
		foreach ( $oneri as $t => $neden ) {
			echo '<span style="' . $yuv . 'background:#EEF3FB;color:#1B4E9B;border:1px dashed #9DB7E0">+ ' . esc_html( $t ) . '</span>';
		}
		echo '<div style="font-size:12.5px;color:#5C6470">';
		foreach ( $oneri as $t => $neden ) { echo '<div>· <strong>' . esc_html( $t ) . '</strong>: ' . esc_html( $neden ) . '</div>'; }
		echo '</div>';
	} else {
		echo '<span style="color:#5C6470;font-size:13px">' . esc_html__( 'Bu sayfaya uygun ek şema önerisi yok.', 'gbc-core' ) . '</span>';
	}
	echo '</div>';
}

/**
 * Puanlı GBC skoru.
 *
 * @return array( 'toplam' => int, 'gruplar' => [ ad, id, puan, max, maddeler[ ad, puan, max, not ] ] )
 */
function gbc_br_skor( $d, $g, $url_m ) {
	$uz = static function ( $s ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s ) : strlen( (string) $s ); };
	$m  = static function ( $ad, $ok, $max, $not = '' ) { return array( 'ad' => $ad, 'puan' => $ok ? $max : 0, 'max' => $max, 'not' => $not ); };
	$k  = static function ( $ad, $puan, $max, $not = '' ) { return array( 'ad' => $ad, 'puan' => max( 0, min( $max, $puan ) ), 'max' => $max, 'not' => $not ); };
	$gr = array();

	/* 1) İçerik — 25 */
	$h2 = 0;
	foreach ( (array) $d['basliklar'] as $b ) { if ( 'h2' === $b['tip'] ) { $h2++; } }
	$alt = (int) $d['alt_yok'];
	$yil = function_exists( 'gbc_seo_yil_temiz' ) ? gbc_seo_yil_temiz( $d ) : true;
	$gr[] = array( 'ad' => 'İçerik yapısı', 'id' => 'gbc-k-icerik', 'maddeler' => array(
		$m( 'Tek H1', 1 === (int) $d['h1_adet'], 6, (int) $d['h1_adet'] . ' H1' ),
		$m( 'Başlık sırası atlamıyor', 0 === (int) $d['atlama'], 5, (int) $d['atlama'] ? (int) $d['atlama'] . ' atlama' : '' ),
		$m( 'En az 3 H2', $h2 >= 3, 4, $h2 . ' H2' ),
		$k( 'Bütün görsellerde alt metni', 6 - 2 * $alt, 6, $alt ? $alt . ' görselde eksik (her biri −2)' : (int) $d['gorsel'] . ' görselin hepsi dolu' ),
		$m( 'Eski yıl ifadesi yok', $yil, 4 ),
	) );

	/* 2) Meta — 15 */
	$au = $uz( $d['aciklama'] );
	$bu = $uz( $d['baslik_etiketi'] );
	$gr[] = array( 'ad' => 'Meta', 'id' => 'gbc-k-meta', 'maddeler' => array(
		$m( 'Meta açıklama var', '' !== (string) $d['aciklama'], 5 ),
		$m( 'Meta açıklama 120–165 karakter', $au >= 120 && $au <= 165, 5, $au . ' karakter' ),
		$m( 'Başlık etiketi 30–62 karakter', $bu >= 30 && $bu <= 62, 5, $bu . ' karakter' ),
	) );

	/* 3) URL — 15 (HTTP 200, canonical, noindex kritik: 3'er puan; diğer altı madde 1'er) */
	$kritik = array( 'Adres açılıyor (HTTP 200)' => 3, 'Canonical bu adresi gösteriyor' => 3, 'Google\'a kapalı değil (noindex yok)' => 3 );
	$um = array();
	foreach ( (array) $url_m as $x ) {
		$max = isset( $kritik[ $x['ad'] ] ) ? $kritik[ $x['ad'] ] : 1;
		$um[] = $m( $x['ad'], $x['ok'], $max, $x['not'] );
	}
	$gr[] = array( 'ad' => 'URL', 'id' => 'gbc-k-url', 'maddeler' => $um,
		'ek' => '<a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener" style="font-weight:400">' . esc_html( $d['url'] ) . '</a>'
			. ' <span style="font-weight:400;color:#5C6470">· ' . esc_html( sprintf( 'HTTP %d · %s', (int) $d['kod'],
				function_exists( 'size_format' ) ? size_format( (int) $d['bayt'] ) : round( (int) $d['bayt'] / 1024 ) . ' KB' ) ) . '</span>' );

	/* 4) Bağlantılar — 30 */
	$o = gbc_br_ozet( $g );
	$ic_yon = 0; $rel_eksik = 0;
	foreach ( $g['ic'] as $r ) { if ( 'yon' === $r['durum'] ) { $ic_yon++; } }
	foreach ( $g['ortaklik'] as $r ) { if ( false === strpos( (string) $r['rel'], 'sponsored' ) ) { $rel_eksik++; } }
	$ic_say = count( $g['ic'] );
	$ort_say = count( $g['ortaklik'] );
	$gr[] = array( 'ad' => 'Bağlantılar', 'id' => 'gbc-k-bag', 'maddeler' => array(
		$m( 'En az 3 iç bağlantı', $ic_say >= 3, 5, $ic_say . ' iç bağlantı' ),
		$k( 'Ölü bağlantı yok', 10 - 4 * $o['kirik'], 10, $o['kirik'] ? $o['kirik'] . ' ölü bağlantı (her biri −4)' : '' ),
		$k( 'İç bağlantılar doğrudan son adrese gidiyor', 3 - $ic_yon, 3, $ic_yon ? $ic_yon . ' iç bağlantı yönlendiriyor (her biri −1)' : '' ),
		$k( 'Ortaklık linklerinin yapısı doğru', 8 - 3 * $o['yapi_hata'], 8, $ort_say ? ( $o['yapi_hata'] ? $o['yapi_hata'] . ' hatalı (her biri −3)' : $o['yapi_ok'] . ' link kazanç yazmaya uygun' ) : 'ortaklık linki yok' ),
		$k( 'Ortaklık linklerinde rel="sponsored"', 4 - 2 * $rel_eksik, 4, $rel_eksik ? $rel_eksik . ' linkte eksik (her biri −2)' : '' ),
	) );

	/* 5) Şema — 15 */
	$sd = function_exists( 'gbc_seo_sema_denetim' ) ? gbc_seo_sema_denetim( $d ) : array( 'eksik' => array() );
	$ek = count( $sd['eksik'] );
	$gr[] = array( 'ad' => 'Şema', 'id' => 'gbc-k-sema', 'maddeler' => array(
		$m( 'Şema basılıyor', ! empty( $d['sema'] ), 3 ),
		$k( 'Olması gereken şemaların hepsi var', 12 - 4 * $ek, 12, $ek ? implode( ', ', array_keys( $sd['eksik'] ) ) . ' eksik (her biri −4)' : '' ),
	) );

	$top = 0; $maxt = 0;
	foreach ( $gr as $i => $x ) {
		$p = 0; $mx = 0;
		foreach ( $x['maddeler'] as $y ) { $p += $y['puan']; $mx += $y['max']; }
		$gr[ $i ]['puan'] = $p; $gr[ $i ]['max'] = $mx;
		$top += $p; $maxt += $mx;
	}
	return array( 'toplam' => $maxt ? (int) round( $top / $maxt * 100 ) : 0, 'puan' => $top, 'max' => $maxt, 'gruplar' => $gr );
}

/** Puanlı kontrol tablosu (eski 10 maddelik tablonun yerine). */
function gbc_br_skor_tablo( $sk ) {
	echo '<h2 id="gbc-kontroller" style="margin-top:22px">' . esc_html__( 'Kontroller', 'gbc-core' )
		. ' <span style="font-weight:400;color:#5C6470;font-size:14px">' . esc_html( sprintf( __( '%1$d / %2$d puan', 'gbc-core' ), $sk['puan'], $sk['max'] ) ) . '</span></h2>';
	echo '<table class="widefat" style="max-width:1000px"><tbody>';
	foreach ( $sk['gruplar'] as $gr ) {
		$tam = ( $gr['puan'] === $gr['max'] );
		echo '<tr id="' . esc_attr( $gr['id'] ) . '" style="background:#F6F7F7"><td colspan="3" style="font-weight:700;font-size:14px">'
			. esc_html( $gr['ad'] ) . ( ! empty( $gr['ek'] ) ? ' <span style="font-size:13px">— ' . $gr['ek'] . '</span>' : '' )
			. ' <span style="float:right;color:' . ( $tam ? '#1A7F37' : '#B32D2E' ) . '">'
			. (int) $gr['puan'] . ' / ' . (int) $gr['max'] . '</span></td></tr>';
		foreach ( $gr['maddeler'] as $y ) {
			$ok = ( $y['puan'] === $y['max'] );
			$yarim = ( ! $ok && $y['puan'] > 0 );
			echo '<tr><td style="width:70px">' . ( $ok
				? '<span style="color:#1A7F37;font-weight:700">' . esc_html__( 'GEÇTİ', 'gbc-core' ) . '</span>'
				: '<span style="color:' . ( $yarim ? '#8A6100' : '#B32D2E' ) . ';font-weight:700">' . esc_html( $yarim ? __( 'EKSİK', 'gbc-core' ) : __( 'KALDI', 'gbc-core' ) ) . '</span>' )
				. '</td><td>' . esc_html( $y['ad'] )
				. ( '' !== $y['not'] ? ' <span style="color:#5C6470;font-size:12.5px">— ' . esc_html( $y['not'] ) . '</span>' : '' )
				. '</td><td style="width:70px;text-align:right;font-weight:600">' . (int) $y['puan'] . ' / ' . (int) $y['max'] . '</td></tr>';
		}
	}
	echo '</tbody></table>';
}
