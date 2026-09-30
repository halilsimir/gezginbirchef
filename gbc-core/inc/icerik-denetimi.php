<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — İçerik Denetimi (toplu).
 *
 * NEDEN VAR
 * ---------
 * Sayfa Denetimi tek bir adresi ölçer. Sitede 1064 yazı/sayfa var ve
 * hepsinin GÖVDESİ yalnızca iki kısa koddan ibaret:
 *
 *     [wpcode id="24751"] [wpcode id="24752"]
 *
 * Yani post_content'e bakan her denetim "içerik yok, H2 yok, görsel yok"
 * der ve 1043 sahte alarm üretir. Gerçek içerik ACF alanlarında duruyor.
 * Bu modül ACF alanlarını okur.
 *
 * ŞABLON NORMU — TAHMİN YOK
 * -------------------------
 * "Şu alan zorunlu" diye elle liste yazmıyoruz; Gezi şablonunun PHP'si
 * 54 ayrı get_field() çağırıyor ve bunların çoğu isteğe bağlı. Bunun
 * yerine ÖLÇÜYORUZ: bir şablondaki yazıların yüzde kaçı o alanı dolduruyor?
 *
 *   doluluk >= %70  -> o şablonun NORMU. Boşsa gerçek eksik.
 *   doluluk <  %70  -> isteğe bağlı alan. Boşsa sorun değil, rapor edilmez.
 *
 * Böylece eşik tek yerde, sayılabilir ve sahte alarm üretmez. Eşiğin
 * altında kalan alanlar ekranda ayrı bir sütunda "isteğe bağlı" olarak
 * gösterilir; gizlenmez.
 *
 * 29 Eyl 2026 · v1.23.0
 */

define( 'GBC_IC_NORM_ESIK', 70 );   /* yüzde */
define( 'GBC_IC_PARTI',     25 );   /* bir turda taranan yazı sayısı — asıl fren süre */
define( 'GBC_IC_SURE',      12 );   /* saniye: bu süreyi aşınca parti erken biter */
define( 'GBC_IC_DURUM',     'gbc_icerik_denetim' );
define( 'GBC_IC_HAM',       'gbc_icerik_ham' );
/* KAYIT BICIMI surumu. Tarama yalniz BU deger degisince sifirlanir.
   30 Eyl 2026: once GBC_CORE_SURUM ile karsilastiriliyordu; eklentinin
   her kucuk surumunde yarim kalan tarama basa doruyordu (1.26.1'de 84
   sayfa taranmisti, 1.27.0 kurulunca hepsi cope gidecekti). Oysa o iki
   surum arasinda kaydin BICIMI hic degismedi. Bicim degisirse burayi
   artir; surum numarasi tek basina tarama sifirlatmaz. */
define( 'GBC_IC_BICIM',     4 );

/**
 * Şablon haritası: WPCode snippet kimliği -> şablon adı.
 *
 * 29 Eyl 2026'da canlı sayımla doğrulandı (yazı adedi parantez içinde).
 * CSS snippet'i ayrı bir kimlik taşıdığı için ikisi de listede.
 */
function gbc_ic_sablonlar() {
	return array(
		22607 => 'Gezi',    22608 => 'Gezi',      /* 31 */
		23108 => 'Liste',   23109 => 'Liste',     /* 35 */
		23489 => 'Detay',   23490 => 'Detay',     /* 105 */
		23340 => 'Rota',    23342 => 'Rota',      /* 2  */
		24751 => 'Tarif',   24752 => 'Tarif',     /* 756 */
		26922 => 'Blog',    26923 => 'Blog',      /* 100 */
		24156 => 'Sözlük',  24157 => 'Sözlük',    /* 19 */
		23531 => 'Ana',     23533 => 'Ana',       /* 1  */
		23888 => 'Yazar',                          /* 1  */
		30128 => 'Yasal',                          /* 2  */
		24883 => 'Linkler',                        /* 1  */
	);
}

/**
 * Bir yazının şablonunu gövdesindeki kısa koddan bulur.
 *
 * @param string $govde post_content.
 * @return array ( 'sablon' => string, 'kodlar' => int[] )
 */
function gbc_ic_sablon_bul( $govde ) {
	$harita = gbc_ic_sablonlar();
	$kodlar = array();
	if ( preg_match_all( '/\[wpcode\s+id=["\']?(\d+)/i', (string) $govde, $m ) ) {
		foreach ( $m[1] as $k ) { $kodlar[] = (int) $k; }
	}
	$ad = '';
	foreach ( $kodlar as $k ) {
		if ( isset( $harita[ $k ] ) ) { $ad = $harita[ $k ]; break; }
	}
	if ( '' === $ad ) { $ad = $kodlar ? 'bilinmeyen' : 'şablonsuz'; }
	return array( 'sablon' => $ad, 'kodlar' => array_values( array_unique( $kodlar ) ) );
}

/**
 * Bir değerin gerçekten dolu olup olmadığı.
 *
 * ACF boş repeater'ı array(), boş ilişkiyi false, boş metni '' döndürür;
 * bazı alanlar da '0' ya da '<p>&nbsp;</p>' gibi görünürde dolu ama
 * aslında boş değer taşır.
 */
function gbc_ic_dolu( $v ) {
	if ( null === $v || false === $v ) { return false; }
	if ( is_array( $v ) ) {
		foreach ( $v as $x ) { if ( gbc_ic_dolu( $x ) ) { return true; } }
		return false;
	}
	if ( is_numeric( $v ) ) { return true; }
	$s = trim( wp_strip_all_tags( (string) $v ) );
	$s = trim( str_replace( array( "\xc2\xa0", '&nbsp;' ), ' ', $s ) );
	return '' !== $s;
}

/**
 * Bir yazının ACF alanları.
 *
 * ACF yoksa postmeta'ya düşeriz: alt çizgiyle başlamayan ve eşleniği
 * "_ad" olarak duran anahtarlar ACF alanıdır.
 */
/**
 * Bir sablonun ACF alan ADLARI — grup tanimindan, degerden bagimsiz.
 *
 * Grup basina bir kez hesaplanir (istek ici onbellek). Alan adlari
 * tanimin kendisinden gelir; bir yazida o alan hic yazilmamis olsa bile
 * listede durur — "bos" ile "yok" ayrimi ancak boyle yapilabilir.
 *
 * @param int $pid Yazi kimligi.
 * @return array Alan adlari.
 */
function gbc_ic_grup_alanlari( $pid ) {
	static $bellek = array();

	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
		return array();
	}

	$gruplar = acf_get_field_groups( array( 'post_id' => $pid ) );
	if ( ! is_array( $gruplar ) || ! $gruplar ) { return array(); }

	$anahtar = implode( ',', wp_list_pluck( $gruplar, 'key' ) );
	if ( isset( $bellek[ $anahtar ] ) ) { return $bellek[ $anahtar ]; }

	$adlar = array();
	foreach ( $gruplar as $g ) {
		foreach ( (array) acf_get_fields( $g ) as $f ) {
			if ( ! empty( $f['name'] ) ) { $adlar[] = $f['name']; }
		}
	}
	$adlar = array_values( array_unique( $adlar ) );

	/* Onbellek sinirsiz buyumesin: en fazla 20 farkli grup bilesimi. */
	if ( count( $bellek ) > 20 ) { $bellek = array(); }
	$bellek[ $anahtar ] = $adlar;
	return $adlar;
}

/**
 * Bir yazının ACF alanları.
 *
 * 30 Eyl 2026 · v1.35.0 — ÖLÇÜLEN KUSUR, tanı aracıyla bulundu.
 * -----------------------------------------------------------------
 * Eskiden yalniz get_fields( $pid, false ) kullaniliyordu. O fonksiyon
 * alanlari ACF ISARETCISINDEN sayar: her alanin yaninda duran
 * "_alanadi" => "field_xxx" eslenigi. Bir deger dogrudan postmeta'ya
 * yazildiysa (Make senaryosu, REST, toplu SQL) isaretci olusmaz ve alan
 * get_fields() icin GORUNMEZ olur.
 *
 * Kefir (#3867) tanisi, 30 Eyl 2026:
 *     alan                taramada  get_field()  ham meta  isaretci
 *     tarif_eslikciler    evet      dizi(3)      dizi(3)   var
 *     tarif_faq           HAYIR     815          815       YOK
 *     tarif_giris         HAYIR     470          470       YOK
 *     tarif_temel         HAYIR     238          238       YOK
 * get_fields() 30+ alanlik Tarif sablonunda SADECE 1 alan dondurdu.
 * Veri yerindeydi, sayfa dogru basiyordu; yalniz DENETIM kordu. Bu
 * yuzden "96 tarifte SSS eksik" gibi sayilar gercek disi cikti.
 *
 * YENI YOL — isaretciye hic bakmaz:
 *   1) Alan ADLARI sablonun ACF grup TANIMINDAN alinir.
 *   2) Degerler tek bir get_post_meta( $pid ) cagrisiyla okunur.
 * Tek sorgu, isaretciden bagimsiz, tam liste. get_fields() sonucu da
 * ustune birlestirilir: isaretcili alanlarda ACF'nin cozdugu deger
 * (tekrarlayici, grup) ham metadan daha dogrudur.
 *
 * @param int $pid Yazi kimligi.
 * @return array ad => ham deger
 */
function gbc_ic_alanlar( $pid ) {
	$out = array();

	/* 1) Grup tanimindaki adlar + ham postmeta degerleri. */
	$adlar = gbc_ic_grup_alanlari( $pid );
	if ( $adlar ) {
		$meta = get_post_meta( $pid );
		if ( ! is_array( $meta ) ) { $meta = array(); }
		foreach ( $adlar as $ad ) {
			if ( ! isset( $meta[ $ad ] ) ) { $out[ $ad ] = null; continue; }
			$v = $meta[ $ad ];
			$v = ( is_array( $v ) && 1 === count( $v ) ) ? $v[0] : $v;
			$out[ $ad ] = maybe_unserialize( $v );
		}
	}

	/* 2) ACF'nin kendi cozdugu degerler ustune yazilir — yalniz DOLU
	      olanlar. Bos bir ACF degeri, ham metadaki dolu degeri ezmemeli. */
	if ( function_exists( 'get_fields' ) ) {
		$a = get_fields( $pid, false );
		if ( is_array( $a ) ) {
			foreach ( $a as $ad => $v ) {
				if ( ! isset( $out[ $ad ] ) || gbc_ic_dolu( $v ) ) { $out[ $ad ] = $v; }
			}
		}
	}
	if ( $out ) { return $out; }

	/* 3) ACF hic yoksa: postmeta'dan isaretcili anahtarlar. */
	$ham = get_post_meta( $pid );
	if ( is_array( $ham ) ) {
		foreach ( $ham as $k => $v ) {
			if ( '' === $k || '_' === $k[0] ) { continue; }
			if ( ! isset( $ham[ '_' . $k ] ) ) { continue; }
			$out[ $k ] = is_array( $v ) && 1 === count( $v ) ? maybe_unserialize( $v[0] ) : $v;
		}
	}
	return $out;
}

/**
 * Bir tarifin MUTFAĞI.
 *
 * Ayri bir ACF alani YOK. Mutfak bilgisi tarif_temel metninin icinde
 * "Mutfak: Italyan" biciminde bir satir olarak duruyor (30 Eyl 2026'da
 * canli iki tarifte dogrulandi). Tarif <-> Gezi ic linkleme icin bu satir
 * tek olculebilir baglanti noktasi, o yuzden ayrica cikariliyor.
 *
 * @param array $alan ACF alanlari.
 * @return string Mutfak adi ya da ''.
 */
function gbc_ic_mutfak( $alan ) {
	$t = isset( $alan['tarif_temel'] ) ? (string) $alan['tarif_temel'] : '';
	if ( '' === trim( $t ) ) { return ''; }
	if ( ! preg_match( '/^\s*Mutfak\s*:\s*(.+)$/mu', $t, $m ) ) { return ''; }
	$v = trim( wp_strip_all_tags( $m[1] ) );
	$v = trim( $v, " \t\n\r\0\x0B.,;" );
	return $v;
}

/**
 * Bir gezi rehberinin ÜLKESİ.
 *
 * Gezi sablonu (22607) info_country alanini okuyor; Liste/Rota sablonlarinda
 * karsiligi yok, orada bos doner.
 */
function gbc_ic_ulke( $alan ) {
	foreach ( array( 'info_country', 'info_city' ) as $k ) {
		if ( empty( $alan[ $k ] ) ) { continue; }
		$v = trim( wp_strip_all_tags( (string) $alan[ $k ] ) );
		if ( '' !== $v ) { return $v; }
	}
	return '';
}

/**
 * Tarama durumunu okur.
 */
function gbc_ic_durum() {
	$d = get_option( GBC_IC_DURUM, array() );
	if ( ! is_array( $d ) ) { $d = array(); }
	return wp_parse_args( $d, array(
		'ofset'     => 0,
		'toplam'    => 0,
		'baslangic' => 0,
		'bitis'     => 0,
		'surum'     => '',
		'bicim'     => 0,      /* kayit bicimi surumu — bkz. GBC_IC_BICIM */
		'isleniyor' => 0,      /* su an islenen kimlik — surec olurse burada kalir */
		'atlanan'   => array(),/* sureci olduren kimlikler */
	) );
}

/**
 * Taranacak bütün kimlikler — yazı + sayfa, her durum.
 */
function gbc_ic_kimlikler() {
	global $wpdb;
	$sql = "SELECT ID FROM {$wpdb->posts}
	        WHERE post_type IN ('post','page')
	          AND post_status IN ('publish','draft','pending','private','future')
	        ORDER BY ID ASC";
	return array_map( 'intval', (array) $wpdb->get_col( $sql ) );
}

/**
 * Bir parti tarar. Baştan başlamak için $sifirla = true.
 *
 * @return array ( 'islenen', 'ofset', 'toplam', 'bitti' )
 */
function gbc_ic_tara( $sifirla = false ) {
	$kimlikler = gbc_ic_kimlikler();
	$toplam    = count( $kimlikler );

	$ham = get_option( GBC_IC_HAM, array() );
	if ( ! is_array( $ham ) ) { $ham = array(); }
	$d = gbc_ic_durum();

	if ( $sifirla || (int) $d['bicim'] !== (int) GBC_IC_BICIM ) {
		$ham = array();
		$d['ofset']     = 0;
		$d['baslangic'] = time();
		$d['surum']     = GBC_CORE_SURUM;
		$d['bicim']     = (int) GBC_IC_BICIM;
		$d['isleniyor'] = 0;
		$d['atlanan']   = array();
	}

	$ofset = (int) $d['ofset'];
	$parti = array_slice( $kimlikler, $ofset, GBC_IC_PARTI );

	$basladi = microtime( true );
	$islenen = 0;

	foreach ( $parti as $pid ) {
		/* SURE FRENI: paylasimli sunucuda 60 yazi max_execution_time'i
		   asiyordu. Parti bitmeden sure dolarsa kaldigi yerden devam eder. */
		if ( $islenen > 0 && ( microtime( true ) - $basladi ) > GBC_IC_SURE ) { break; }

		/* COKME DONGUSU KORUMASI.
		   Tek bir yazi PHP surecini oldururse (cok buyuk ACF, bozuk veri)
		   ofset ilerlemez ve her tiklamada ayni yerde olunur. Bu yuzden
		   islemeden ONCE kimligi diske yaziyoruz. Surec olur de ayni kimlik
		   tekrar sirada cikarsa, o yazi atlanir ve deftere sorun olarak
		   dusulur — tarama devam eder. */
		if ( (int) $d['isleniyor'] === (int) $pid ) {
			$d['atlanan'][ $pid ] = true;
			$d['isleniyor'] = 0;
			update_option( GBC_IC_DURUM, $d, false );
			$islenen++;
			continue;
		}
		if ( isset( $d['atlanan'][ $pid ] ) ) { $islenen++; continue; }

		$d['isleniyor'] = (int) $pid;
		update_option( GBC_IC_DURUM, $d, false );

		$p = get_post( $pid );
		if ( ! $p ) { $d['isleniyor'] = 0; $islenen++; continue; }

		$s   = gbc_ic_sablon_bul( $p->post_content );
		$alan = gbc_ic_alanlar( $pid );

		$dolu = array();
		foreach ( (array) $alan as $ad => $v ) {
			if ( gbc_ic_dolu( $v ) ) { $dolu[] = $ad; }
		}

		/* 29 Eyl 2026 — SAHTE ALARM DUZELTMESI.
		   Ilk yazimda "ozeti bos" ayri bir bayraktı ve 209 sayfayi
		   isaretliyordu. Oysa snippet kaynaklarina bakinca post_excerpt'i
		   yalniz Detay (23489) ve Blog (26922) sablonlari okuyor; 756
		   Tarif yazisinda ozet hicbir yerde gorunmuyor. Elle "su sablonda
		   ozet gerekir" listesi tutmak yerine ozeti ve one cikan gorseli
		   normun icine sozde alan olarak koyuyoruz: o sablondaki yazilarin
		   %70'inden fazlasi dolduruyorsa gerekli, degilse degil. Boylece
		   kural tek yerde ve olcumle belirleniyor. */
		$ozet_uz = strlen( trim( wp_strip_all_tags( (string) $p->post_excerpt ) ) );
		$one_id  = (int) get_post_thumbnail_id( $pid );
		$alan['«özet»']             = $ozet_uz ? 'var' : '';
		$alan['«öne çıkan görsel»'] = $one_id  ? 'var' : '';
		if ( $ozet_uz ) { $dolu[] = '«özet»'; }
		if ( $one_id )  { $dolu[] = '«öne çıkan görsel»'; }

		/* 'alanlar' (tum alan adlari) KALDIRILDI: hicbir yerde okunmuyordu,
		   yalniz secenegi sisiriyordu. Norm ve bulgular sadece 'dolu' kullanir. */
		$ham[ $pid ] = array(
			'mutfak'  => gbc_ic_mutfak( $alan ),
			'ulke'    => gbc_ic_ulke( $alan ),
			'baslik'  => $p->post_title,
			'slug'    => $p->post_name,
			'durum'   => $p->post_status,
			'tur'     => $p->post_type,
			'sablon'  => $s['sablon'],
			'kodlar'  => $s['kodlar'],
			'dolu'    => $dolu,
			'ozet'    => strlen( trim( wp_strip_all_tags( (string) $p->post_excerpt ) ) ),
			'one'     => (int) get_post_thumbnail_id( $pid ),
			'guncel'  => substr( (string) $p->post_modified, 0, 10 ),
		);

		/* Buyuk ACF degerlerini hemen birak; 1064 yazilik turda bellek
		   yoksa surec oluyor. */
		unset( $alan, $dolu, $p );
		if ( function_exists( 'wp_cache_delete' ) ) { wp_cache_delete( $pid, 'posts' ); }
		if ( function_exists( 'wp_cache_delete' ) ) { wp_cache_delete( $pid, 'post_meta' ); }

		$d['isleniyor'] = 0;
		$islenen++;

		/* Artimli kayit: cokme olursa yapilan is kaybolmasin. */
		if ( 0 === $islenen % 4 ) {
			$d['ofset'] = $ofset + $islenen;
			update_option( GBC_IC_HAM, $ham, false );
			update_option( GBC_IC_DURUM, $d, false );
		}
	}

	$ofset += $islenen;
	$bitti  = $ofset >= $toplam;

	$d['ofset']     = $bitti ? 0 : $ofset;
	$d['toplam']    = $toplam;
	$d['isleniyor'] = 0;
	if ( $bitti ) { $d['bitis'] = time(); }

	update_option( GBC_IC_HAM, $ham, false );
	update_option( GBC_IC_DURUM, $d, false );

	if ( $bitti ) { gbc_ic_sorunlari_isle(); }
	gbc_ic_ayna();   /* 30 Eyl 2026: her partide — ilerleme disaridan gorulebilsin */

	return array(
		'islenen' => $islenen,
		'ofset'   => $bitti ? $toplam : $ofset,
		'toplam'  => $toplam,
		'bitti'   => $bitti,
	);
}

/**
 * Şablon normu: her şablonda her alanın doluluk yüzdesi.
 *
 * @return array sablon => array( 'adet' => int, 'alan' => array( ad => yuzde ) )
 */
function gbc_ic_norm() {
	$ham = get_option( GBC_IC_HAM, array() );
	if ( ! is_array( $ham ) ) { return array(); }

	$sayac = array();
	foreach ( $ham as $pid => $r ) {
		$s = $r['sablon'];
		if ( ! isset( $sayac[ $s ] ) ) { $sayac[ $s ] = array( 'adet' => 0, 'alan' => array() ); }
		$sayac[ $s ]['adet']++;
		foreach ( (array) $r['dolu'] as $ad ) {
			if ( ! isset( $sayac[ $s ]['alan'][ $ad ] ) ) { $sayac[ $s ]['alan'][ $ad ] = 0; }
			$sayac[ $s ]['alan'][ $ad ]++;
		}
	}

	$disi = gbc_ic_norm_disi();

	$out = array();
	foreach ( $sayac as $s => $x ) {
		$n = max( 1, (int) $x['adet'] );
		$a = array();
		foreach ( $x['alan'] as $ad => $c ) {
			if ( isset( $disi[ $ad ] ) ) { continue; }
			$a[ $ad ] = (int) round( $c * 100 / $n );
		}
		arsort( $a );
		$out[ $s ] = array( 'adet' => (int) $x['adet'], 'alan' => $a );
	}
	ksort( $out );
	return $out;
}

/**
 * Norma GİRMEYEN alanlar — doldurmak editörün işi değil.
 *
 * 30 Eyl 2026 · v1.36.0 — Halil doğruladı:
 * "Puanlama insanlardan geliyor, boş çıkması çok normal."
 *
 * Norm ölçüsü şunu sorar: "aynı şablondaki yazıların %70'i bu alanı
 * dolduruyorsa, boş bırakan eksiktir." Bu mantık YALNIZCA editörün
 * yazdığı alanlar için geçerli. Değeri ziyaretçiden gelen bir alanda
 * "boş" demek "henüz kimse oy vermedi" demektir — kusur değil, durum.
 * Tarama 1.35.0'da işaretçisiz alanları da görmeye başlayınca bu iki
 * alan ilk kez ortaya çıktı ve 24'er tarifte sahte eksik üretti.
 *
 * Buraya YALNIZCA değeri dışarıdan gelen alanlar yazılır. Editörün
 * doldurması gereken bir alan buraya konursa gerçek eksik gizlenir —
 * "sorun GİZLENEREK sıfıra inilmeyecek" kuralının ihlali olur.
 *
 * @return array ad => true
 */
function gbc_ic_norm_disi() {
	$liste = array(
		'tarif_puan'        => true,  /* ziyaretçi oyu */
		'tarif_puan_sayisi' => true,  /* ziyaretçi oy adedi */
	);
	return (array) apply_filters( 'gbc_ic_norm_disi', $liste );
}

/**
 * Bulgular: her yazı için normun altında kalan alanlar.
 *
 * Tek başına duran şablonlar (adet < 5) normu istatistiksel olarak
 * taşıyamaz — Ana Sayfa tek yazı, kendi kendinin normu olur ve hiçbir
 * zaman eksik çıkmaz. Bu yüzden alan denetimi yalnız adet >= 5 olan
 * şablonlara uygulanır; küçükler ekranda "norm yok" diye işaretlenir.
 */
function gbc_ic_bulgular() {
	$ham  = get_option( GBC_IC_HAM, array() );
	$norm = gbc_ic_norm();
	if ( ! is_array( $ham ) || ! $ham ) { return array(); }

	$out = array();
	foreach ( $ham as $pid => $r ) {
		$s  = $r['sablon'];
		$ek = array();

		if ( isset( $norm[ $s ] ) && $norm[ $s ]['adet'] >= 5 ) {
			$dolu = array_flip( (array) $r['dolu'] );
			foreach ( $norm[ $s ]['alan'] as $ad => $yuzde ) {
				if ( $yuzde < GBC_IC_NORM_ESIK ) { continue; }
				if ( isset( $dolu[ $ad ] ) ) { continue; }
				$ek[] = $ad . ' (%' . $yuzde . ')';
			}
		}

		$bayrak = array();
		if ( 'şablonsuz'   === $s ) { $bayrak[] = 'şablon kısa kodu yok'; }
		if ( 'bilinmeyen'  === $s ) { $bayrak[] = 'tanınmayan kısa kod: ' . implode( ',', (array) $r['kodlar'] ); }
		/* Ozet ve one cikan gorsel artik yukarida normun icinde; burada
		   yalniz sablondan bagimsiz, tartisilmaz eksikler kaliyor. */
		if ( ! $r['slug'] ) { $bayrak[] = 'kalıcı bağlantı (slug) yok'; }

		if ( ! $ek && ! $bayrak ) { continue; }

		$out[ $pid ] = array(
			'baslik' => $r['baslik'],
			'slug'   => $r['slug'],
			'durum'  => $r['durum'],
			'tur'    => $r['tur'],
			'sablon' => $s,
			'eksik'  => $ek,
			'bayrak' => $bayrak,
			'guncel' => $r['guncel'],
			'puan'   => count( $ek ) + count( $bayrak ) * 2,
		);
	}

	uasort( $out, static function ( $a, $b ) { return $b['puan'] <=> $a['puan']; } );
	return $out;
}

/**
 * Mutfak ve ülke dağılımı — iç linkleme için ham malzeme.
 *
 * @return array ( 'mutfak' => array( ad => adet ), 'ulke' => array( ad => adet ) )
 */
function gbc_ic_etiket_dagilimi() {
	$ham = get_option( GBC_IC_HAM, array() );
	if ( ! is_array( $ham ) ) { return array( 'mutfak' => array(), 'ulke' => array() ); }

	$mut = array(); $ulk = array();
	foreach ( $ham as $pid => $r ) {
		if ( ! empty( $r['mutfak'] ) ) {
			$k = $r['mutfak'];
			if ( ! isset( $mut[ $k ] ) ) { $mut[ $k ] = array( 'adet' => 0, 'ornek' => array() ); }
			$mut[ $k ]['adet']++;
			if ( count( $mut[ $k ]['ornek'] ) < 3 ) { $mut[ $k ]['ornek'][] = (int) $pid; }
		}
		if ( ! empty( $r['ulke'] ) ) {
			$k = $r['ulke'];
			if ( ! isset( $ulk[ $k ] ) ) { $ulk[ $k ] = array( 'adet' => 0, 'ornek' => array() ); }
			$ulk[ $k ]['adet']++;
			if ( count( $ulk[ $k ]['ornek'] ) < 3 ) { $ulk[ $k ]['ornek'][] = (int) $pid; }
		}
	}
	uasort( $mut, static function ( $a, $b ) { return $b['adet'] <=> $a['adet']; } );
	uasort( $ulk, static function ( $a, $b ) { return $b['adet'] <=> $a['adet']; } );
	return array( 'mutfak' => $mut, 'ulke' => $ulk );
}

/**
 * Toplu sonucu sorun defterine yazar.
 *
 * 1064 ayrı sorun AÇMAYIZ — defter okunmaz hâle gelir. Bunun yerine
 * ÖZET sorunlar: kaç yazı hangi kategoride aksıyor. Ayrıntı bu ekranda.
 */
function gbc_ic_sorunlari_isle() {
	if ( ! function_exists( 'gbc_sorun_ac' ) || ! function_exists( 'gbc_sorun_kapat' ) ) { return; }

	$b = gbc_ic_bulgular();

	/* 30 Eyl 2026 · KURAL 2 — YANLIŞ ALARMLAR TEMİZLENDİ.
	   Defterdeki üç sorun tek tek incelendi ve hiçbiri gerçek çıkmadı:

	   · "Yayında 4 sayfada şablon kısa kodu yok" → dördü de KURUMSAL
	     SAYFA (Gizlilik #3, İletişim #3939, Food Stylist #8636,
	     Proje & İş Birlikleri #11209). Bunlar elle yazılan sayfalar;
	     tarif/gezi şablonu kullanmamaları DOĞRU. Kural artık yalnız
	     'post' türünü sayıyor — sayfalar şablon zorunluluğuna tabi değil.

	   · "6 yazının kalıcı bağlantısı boş" → altısı da TASLAK. WordPress
	     slug'ı yayına alırken başlıktan üretir; taslakta boş olması
	     normaldir. Kural artık yalnız yayındakileri sayıyor.

	   · "7 sayfada öne çıkan görsel yok" → hepsi TASLAK, yarım içerik.
	     Aynı gerekçe.

	   Bunlar GİZLENMİYOR: hepsi aşağıdaki bulgular tablosunda duruyor,
	   taslak olarak işaretli. Değişen tek şey, defterin bunları
	   "düzeltilmesi gereken kusur" diye saymaması. Yarım işi kusur
	   saymak defteri okunmaz hâle getiriyordu. */
	$sablonsuz = 0; $bilinmeyen = 0; $gorselsiz = 0; $slugsuz = 0; $alanEksik = 0;
	foreach ( $b as $x ) {
		$yayinda = ( 'publish' === $x['durum'] );
		$yazi    = ( 'post' === $x['tur'] );

		/* Şablon zorunluluğu YAZILAR için geçerli; kurumsal sayfalar hariç. */
		if ( 'şablonsuz' === $x['sablon'] && $yayinda && $yazi ) { $sablonsuz++; }

		/* Tanınmayan kısa kod her durumda sorundur: taslakta bile yanlış
		   snippet çağrılıyor demektir, yayına alınınca kırılır. */
		if ( 'bilinmeyen' === $x['sablon'] ) { $bilinmeyen++; }

		if ( $yayinda ) {
			foreach ( $x['bayrak'] as $f ) {
				if ( 0 === strpos( $f, 'kalıcı' ) ) { $slugsuz++; }
			}
			foreach ( $x['eksik'] as $f ) {
				if ( 0 === strpos( $f, '«öne çıkan görsel»' ) ) { $gorselsiz++; }
			}
			if ( $x['eksik'] ) { $alanEksik++; }
		}
	}

	$adres = admin_url( 'admin.php?page=gbc-icerik' );

	$d = gbc_ic_durum();
	$atlanan = is_array( $d['atlanan'] ) ? count( $d['atlanan'] ) : 0;
	if ( $atlanan > 0 ) {
		gbc_sorun_ac( 'ic-atlanan', 'icerik',
			sprintf( '%d sayfa taranamadı (PHP süreci düştü)', $atlanan ),
			'Bu sayfalar işlenirken PHP durdu — genelde çok büyük ACF verisi. Tarama devam etsin diye atlandılar; kimlikleri İçerik Denetimi ekranında yazıyor.',
			'yuksek', $adres, $atlanan );
	} else {
		gbc_sorun_kapat( 'ic-atlanan', 'atlanan sayfa yok' );
	}

	$kural = array(
		array( 'ic-sablonsuz',  $sablonsuz,  'yuksek',
			'Yayındaki %d YAZIDA şablon kısa kodu yok',
			'Gövdesinde [wpcode id="…"] bulunmayan yayındaki yazı. Eski düzende kalmış ya da şablonu düşmüş olabilir. Kurumsal sayfalar (Gizlilik, İletişim gibi) bu kurala tabi değildir. Denetim ekranında listeleniyor.' ),
		array( 'ic-bilinmeyen', $bilinmeyen, 'orta',
			'%d sayfa tanınmayan bir kısa kod kullanıyor',
			'Şablon haritasında olmayan bir WPCode kimliği. Ya yeni bir şablon eklendi ve haritaya yazılmadı, ya da silinmiş bir snippet çağrılıyor.' ),
		array( 'ic-gorselsiz',  $gorselsiz,  'orta',
			'Yayındaki %d sayfada öne çıkan görsel yok',
			'Aynı şablondaki yazıların en az %' . GBC_IC_NORM_ESIK . "'i öne çıkan görsel taşırken bu sayfa taşımıyor. "
			. 'Öne çıkan görsel liste kartlarında, paylaşım önizlemesinde ve şemada kullanılıyor.' ),
		array( 'ic-slugsuz',    $slugsuz,    'orta',
			'Yayındaki %d yazının kalıcı bağlantısı boş',
			'post_name boş. Taslaklar sayılmaz — WordPress slug\'ı yayına alırken üretir. Bu yazılar YAYINDA ve yine de boş.' ),
		array( 'ic-alan-eksik', $alanEksik,  'orta',
			'Yayındaki %d sayfada şablon normunun altında boş alan var',
			'Aynı şablondaki yazıların en az %' . GBC_IC_NORM_ESIK . "'inin doldurduğu bir alan bu sayfada boş. İsteğe bağlı alanlar (norm altı) sayılmaz." ),
	);

	foreach ( $kural as $k ) {
		list( $id, $adet, $onem, $baslik, $acik ) = $k;
		if ( $adet > 0 ) {
			gbc_sorun_ac( $id, 'icerik', sprintf( $baslik, $adet ), $acik, $onem, $adres, $adet );
		} else {
			gbc_sorun_kapat( $id, 'içerik denetiminde bulunamadı' );
		}
	}

	gbc_ic_ayna();
}

/**
 * MCP aynası — sır içermeyen özet.
 */
function gbc_ic_ayna() {
	$d    = gbc_ic_durum();
	$norm = gbc_ic_norm();
	$b    = gbc_ic_bulgular();

	$sablon = array();
	foreach ( $norm as $s => $x ) { $sablon[ $s ] = $x['adet']; }

	$taranan = count( (array) get_option( GBC_IC_HAM, array() ) );
	$et      = gbc_ic_etiket_dagilimi();
	update_option( 'gbc_icerik_durum', array(
		'zaman'    => time(),
		'surum'    => GBC_CORE_SURUM,
		'taranan'  => $taranan,
		'toplam'   => (int) $d['toplam'],
		'yuzde'    => $d['toplam'] ? (int) round( $taranan * 100 / max( 1, (int) $d['toplam'] ) ) : 0,
		'bitti'    => (int) $d['bitis'] > 0 && 0 === (int) $d['ofset'],
		'atlanan'  => is_array( $d['atlanan'] ) ? count( $d['atlanan'] ) : 0,
		'sablon'   => $sablon,
		'bulgu'    => count( $b ),
		/* Adet + ORNEK KIMLIKLER: tek tek duzeltilecek degerleri bulmak icin
		   hangi yazida gectigini bilmek gerekiyor (30 Eyl 2026). */
		'mutfak'   => array_map( static function ( $x ) { return array( 'n' => (int) $x['adet'], 'id' => $x['ornek'] ); }, $et['mutfak'] ),
		'ulke'     => array_map( static function ( $x ) { return array( 'n' => (int) $x['adet'], 'id' => $x['ornek'] ); }, $et['ulke'] ),
	), false );
}

/* Günlük tarama: cron her çalıştığında bir parti ilerler. */
add_action( 'gbc_gunluk_cron', 'gbc_ic_cron_parti', 30 );
function gbc_ic_cron_parti() { if ( function_exists( 'gbc_kilit_al' ) && ! gbc_kilit_al( 'icerik' ) ) { return; } gbc_ic_tara( false ); }

/* Ekrandaki düğmeler. */
add_action( 'admin_init', 'gbc_ic_islem' );
function gbc_ic_islem() {
	if ( empty( $_GET['gbc_ic'] ) ) { return; }
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$islem = sanitize_key( wp_unslash( $_GET['gbc_ic'] ) );
	check_admin_referer( 'gbc_ic_' . $islem );

	$sonuc = array();
	if ( 'tara' === $islem )    { $sonuc = gbc_ic_tara( false ); }
	if ( 'sifirla' === $islem ) { $sonuc = gbc_ic_tara( true ); }
	/* Alan koruma defterini bosalt — yazici duzeltildikten ya da deneme
	   kaydi birakildiktan sonra sorunu kapatmanin tek yolu. */
	if ( 'ak_temizle' === $islem ) {
		delete_option( 'gbc_ak_yakalananlar' );
		if ( function_exists( 'gbc_sorun_kapat' ) ) {
			gbc_sorun_kapat( 'alan-tip-uyusmazligi', 'defter elle temizlendi' );
		}
	}

	/* OTOMATIK DEVAM: 1064 sayfayi elle 40 kez tiklatmak makul degil.
	   'oto=1' ile geri donuyoruz; ekran bitmediyse bir sonraki partiyi
	   kendisi tetikliyor. Tur sayaci sonsuz donguye karsi ust sinir. */
	$adres = 'admin.php?page=gbc-icerik&gbc_ic_ok=1';
	if ( ! empty( $_GET['oto'] ) && empty( $sonuc['bitti'] ) ) {
		$tur = isset( $_GET['tur'] ) ? (int) $_GET['tur'] : 0;
		if ( $tur < 200 ) { $adres .= '&oto=1&tur=' . ( $tur + 1 ); }
	}

	wp_safe_redirect( admin_url( $adres ) );
	exit;
}

/**
 * İşlem bağlantısı.
 *
 * 30 Eyl 2026 — TAKILAN TARAMA HATASI.
 * Once wp_nonce_url() kullaniyordu. O fonksiyon URL'i HTML olarak KACIRIR:
 * "&" yerine "&amp;" dondurur. Bir <a href> icinde bu dogrudur, tarayici
 * cozer — bu yuzden elle tiklayinca calisiyordu. Ama ayni metni JavaScript'e
 * location.href olarak verince "&amp;" oldugu gibi gider; sunucu
 * "amp;gbc_ic" adinda bir parametre gorur, "gbc_ic" hic gelmez ve tarama
 * CALISMAZ. Otomatik tur bos donuyordu: kullanicinin ekraninda sayac
 * 50/1064'te takili kaldi, tur sayisi artmaya devam etti.
 * Cozum: ham URL uret (add_query_arg kacirmaz), kacirmayi basarken yap.
 *
 * @param string $islem tara | sifirla
 * @param array  $ek    ek sorgu parametreleri
 * @return string HAM url — yazdirirken esc_url() uygula.
 */
function gbc_ic_bag( $islem, $ek = array() ) {
	$arg = array_merge(
		array(
			'page'     => 'gbc-icerik',
			'gbc_ic'   => $islem,
			'_wpnonce' => wp_create_nonce( 'gbc_ic_' . $islem ),
		),
		(array) $ek
	);
	return add_query_arg( $arg, admin_url( 'admin.php' ) );
}

/**
 * Ekran.
 */
function gbc_ic_ekran() {
	/* Ekran her acildiginda ayna tazelenir: aynanin bicimi degistiginde
	   kullaniciyi yeniden taramaya zorlamamak icin. Tarama verisi zaten
	   diskte; ayna ondan hesaplaniyor. */
	if ( function_exists( 'gbc_ic_ayna' ) ) { gbc_ic_ayna(); }

	/* 30 Eyl 2026 · v1.32.0 — DEFTER KENDINI ONARIR.
	   gbc_ic_sorunlari_isle() yalniz tarama BITINCE cagriliyordu. Tarama
	   1065/1065 tamamken defter baska bir sebeple (surum supurmesi gibi)
	   bosalirsa, bulgular diskte durdugu halde deftere geri gelmiyordu:
	   ekran "309 bulgu" diyor, defter "0 sorun" diyordu. 30 Eyl'de tam
	   bu oldu. Veri zaten diskte; her ekran acilisinda ozet sorunlari
	   yeniden yaziyoruz. Yeni tarama GEREKMEZ. */
	if ( function_exists( 'gbc_ic_sorunlari_isle' ) ) { gbc_ic_sorunlari_isle(); }

	$d    = gbc_ic_durum();
	$ham  = get_option( GBC_IC_HAM, array() );
	$norm = gbc_ic_norm();
	$b    = gbc_ic_bulgular();
	$taranan = is_array( $ham ) ? count( $ham ) : 0;

	$sec = isset( $_GET['sablon'] ) ? sanitize_text_field( wp_unslash( $_GET['sablon'] ) ) : '';

	echo '<div class="wrap"><h1>GBC İçerik Denetimi</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-icerik' ); }

	echo '<p style="max-width:70em">Sitedeki her yazının gövdesi yalnız iki kısa koddan ibaret; gerçek içerik ACF alanlarında. '
		. 'Bu ekran o alanları okur. Bir alanın <strong>zorunlu</strong> sayılması elle yazılmaz: aynı şablondaki yazıların '
		. '%' . GBC_IC_NORM_ESIK . '\'inden fazlası dolduruyorsa o alan şablonun normudur, boşsa eksiktir. '
		. 'Altında kalanlar isteğe bağlıdır ve raporlanmaz.</p>';

	/* Tarama durumu */
	$yuzde = $d['toplam'] ? (int) round( $taranan * 100 / max( 1, (int) $d['toplam'] ) ) : 0;
	echo '<div class="notice notice-info" style="padding:12px"><p style="margin:0 0 8px">';
	if ( ! $taranan ) {
		echo '<strong>Henüz taranmadı.</strong> Aşağıdaki düğme her basışta ' . (int) GBC_IC_PARTI . ' sayfa tarar; '
			. 'günlük cron da kendiliğinden ilerletir.';
	} else {
		echo '<strong>Taranan:</strong> ' . (int) $taranan . ' / ' . (int) $d['toplam'] . ' (%' . $yuzde . ')';
		if ( $d['bitis'] ) {
			echo ' · <strong>Son tam tur:</strong> ' . esc_html( wp_date( 'j M Y H:i', (int) $d['bitis'] ) );
		}
	}
	$oto = ! empty( $_GET['oto'] );
	$tur = isset( $_GET['tur'] ) ? (int) $_GET['tur'] : 0;

	echo '</p><p style="margin:0">'
		. '<a class="button button-primary" href="' . esc_url( gbc_ic_bag( 'tara', array( 'oto' => 1 ) ) ) . '">Tümünü tara (durmadan)</a> '
		. '<a class="button" href="' . esc_url( gbc_ic_bag( 'tara' ) ) . '">Tek parti (' . (int) GBC_IC_PARTI . ')</a> '
		. '<a class="button" href="' . esc_url( gbc_ic_bag( 'sifirla', array( 'oto' => 1 ) ) ) . '">Baştan tara</a>';

	/* Alan koruma defteri doluysa: ne yakalandigini goster + temizleme yolu. */
	$ak = get_option( 'gbc_ak_yakalananlar', array() );
	if ( is_array( $ak ) && $ak ) {
		echo '<div class="notice notice-warning" style="margin:14px 0;padding:10px 12px">'
			. '<p style="margin:0 0 6px"><strong>Alan koruma: ' . count( $ak ) . ' kayıt.</strong> '
			. 'Metin bekleyen ACF alanına dizi yazılmış; düzenleme ekranı korundu, değer metne çevrildi.</p>'
			. '<table class="widefat striped" style="margin:8px 0"><thead><tr>'
			. '<th>Yazı</th><th>Alan</th><th>Tip</th><th>Kurtarıldı</th><th>Gelen değer</th><th>Ne zaman</th>'
			. '</tr></thead><tbody>';
		foreach ( array_slice( array_reverse( $ak ), 0, 20 ) as $k ) {
			$pid = isset( $k['pid'] ) ? (int) $k['pid'] : 0;
			echo '<tr><td>' . ( $pid
					? '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . $pid . '</a>'
					: '—' )
				. '</td><td><code>' . esc_html( isset( $k['alan'] ) ? $k['alan'] : '?' ) . '</code>'
				. '</td><td>' . esc_html( isset( $k['tip'] ) ? $k['tip'] : '?' ) . '</td>'
				. '<td>' . ( ! empty( $k['kurt'] ) ? 'evet' : '<strong>hayır</strong>' ) . '</td>'
				. '<td><code style="font-size:11px">' . esc_html( mb_substr( isset( $k['ham'] ) ? $k['ham'] : '', 0, 90 ) ) . '</code></td>'
				. '<td>' . ( empty( $k['zaman'] ) ? '—' : esc_html( human_time_diff( (int) $k['zaman'] ) . ' önce' ) ) . '</td></tr>';
		}
		echo '</tbody></table>'
			. '<p style="margin:6px 0 0"><a class="button" href="' . esc_url( gbc_ic_bag( 'ak_temizle' ) ) . '">Koruma defterini temizle</a> '
			. '<span class="description">Yazıcı düzeltildiyse ya da bunlar deneme kaydıysa temizle; sorun kapanır.</span></p>'
			. '</div>';
	}

	if ( $oto ) {
		echo ' &nbsp;<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=gbc-icerik' ) ) . '">■ Durdur</a>'
			. ' <span style="margin-left:10px;color:#2271b1"><strong>Otomatik tarama sürüyor</strong> · tur ' . (int) $tur . '</span>';
	}
	echo '</p></div>';

	/* Otomatik tur: bitmediyse 1,2 sn sonra bir sonraki partiyi tetikle.
	   JS ile, cunku boylece "Durdur"a basacak zaman kaliyor ve tarayici
	   sekmesi kapandiginda kendiliginden duruyor. */
	if ( $oto && ! empty( $d['toplam'] ) && $taranan < (int) $d['toplam'] && $tur < 200 ) {
		/* HAM url — HTML kacirmasi YOK. wp_json_encode tirnaklar icin yeterli. */
		$sonraki = gbc_ic_bag( 'tara', array( 'oto' => 1, 'tur' => $tur + 1 ) );
		echo '<script>setTimeout(function(){location.href=' . wp_json_encode( $sonraki ) . ';},1200);</script>'
			. '<noscript><meta http-equiv="refresh" content="2;url=' . esc_attr( $sonraki ) . '"></noscript>';
	}

	if ( ! empty( $d['atlanan'] ) && is_array( $d['atlanan'] ) ) {
		echo '<div class="notice notice-error" style="padding:12px"><p style="margin:0"><strong>'
			. (int) count( $d['atlanan'] ) . ' sayfa atlandı</strong> — işlenirken PHP süreci düştü. '
			. 'Kimlikler: ' . esc_html( implode( ', ', array_map( 'intval', array_keys( $d['atlanan'] ) ) ) )
			. '</p></div>';
	}

	if ( ! $taranan ) { echo '</div>'; return; }

	/* Şablon dağılımı */
	echo '<h2>Şablon dağılımı</h2><table class="widefat striped" style="max-width:60em"><thead><tr>'
		. '<th>Şablon</th><th style="text-align:right">Sayfa</th><th style="text-align:right">Norm alanı</th>'
		. '<th style="text-align:right">İsteğe bağlı</th><th style="text-align:right">Bulgulu sayfa</th><th></th>'
		. '</tr></thead><tbody>';
	foreach ( $norm as $s => $x ) {
		$nrm = 0; $ops = 0;
		foreach ( $x['alan'] as $y ) { if ( $y >= GBC_IC_NORM_ESIK ) { $nrm++; } else { $ops++; } }
		$bs = 0;
		foreach ( $b as $z ) { if ( $z['sablon'] === $s ) { $bs++; } }
		$uyari = $x['adet'] < 5 ? ' <span style="color:#b26200">· norm yok (5 sayfadan az)</span>' : '';
		echo '<tr><td><strong>' . esc_html( $s ) . '</strong>' . $uyari . '</td>'
			. '<td style="text-align:right">' . (int) $x['adet'] . '</td>'
			. '<td style="text-align:right">' . (int) $nrm . '</td>'
			. '<td style="text-align:right">' . (int) $ops . '</td>'
			. '<td style="text-align:right">' . ( $bs ? '<strong>' . (int) $bs . '</strong>' : '0' ) . '</td>'
			. '<td><a href="' . esc_url( admin_url( 'admin.php?page=gbc-icerik&sablon=' . rawurlencode( $s ) ) ) . '">bulguları gör</a></td></tr>';
	}
	echo '</tbody></table>';

	/* Mutfak <-> Ulke: ic linkleme malzemesi */
	$et = gbc_ic_etiket_dagilimi();
	if ( $et['mutfak'] || $et['ulke'] ) {
		echo '<h2 style="margin-top:2em">İç linkleme malzemesi</h2>';
		echo '<p style="max-width:70em">Tarif ↔ Gezi bağlantısı için tek ölçülebilir ortak nokta: tarifin mutfağı ve '
			. 'rehberin ülkesi. Mutfak, ayrı bir alan değil — <code>tarif_temel</code> içindeki “Mutfak:” satırından okunuyor.</p>';
		echo '<div style="display:flex;gap:2em;flex-wrap:wrap">';
		foreach ( array( 'mutfak' => 'Tariflerde mutfak', 'ulke' => 'Rehberlerde ülke/şehir' ) as $k => $bas ) {
			echo '<div><h3>' . esc_html( $bas ) . ' <span style="font-weight:400">(' . count( $et[ $k ] ) . ' farklı)</span></h3>'
				. '<table class="widefat striped" style="max-width:26em"><tbody>';
			$n = 0;
			foreach ( $et[ $k ] as $ad => $x ) {
				if ( ++$n > 25 ) { break; }
				echo '<tr><td>' . esc_html( $ad ) . '</td><td style="text-align:right">' . (int) $x['adet'] . '</td></tr>';
			}
			echo '</tbody></table>';
			if ( count( $et[ $k ] ) > 25 ) { echo '<p>İlk 25 gösteriliyor.</p>'; }
			echo '</div>';
		}
		echo '</div>';
	}

	/* Bulgular */
	$liste = $sec ? array_filter( $b, static function ( $x ) use ( $sec ) { return $x['sablon'] === $sec; } ) : $b;

	echo '<h2 style="margin-top:2em">Bulgular' . ( $sec ? ' · ' . esc_html( $sec ) : '' ) . ' <span style="font-weight:400">(' . count( $liste ) . ')</span></h2>';
	if ( $sec ) {
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=gbc-icerik' ) ) . '">← hepsi</a></p>';
	}
	if ( ! $liste ) {
		echo '<p>Bulgu yok.</p></div>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>'
		. '<th style="width:26em">Sayfa</th><th>Şablon</th><th>Durum</th><th>Eksik alanlar</th><th>Not</th><th>Güncelleme</th>'
		. '</tr></thead><tbody>';

	$n = 0;
	foreach ( $liste as $pid => $x ) {
		if ( ++$n > 300 ) { break; }
		$duzenle = get_edit_post_link( $pid, '' );
		$bak     = get_permalink( $pid );
		echo '<tr>';
		echo '<td><a href="' . esc_url( (string) $duzenle ) . '"><strong>' . esc_html( $x['baslik'] ) . '</strong></a>'
			. '<div style="color:#666;font-size:12px">#' . (int) $pid . ' · ' . esc_html( (string) $x['slug'] )
			. ( $bak ? ' · <a href="' . esc_url( $bak ) . '" target="_blank" rel="noopener">bak</a>' : '' ) . '</div></td>';
		echo '<td>' . esc_html( $x['sablon'] ) . '</td>';
		echo '<td>' . esc_html( 'publish' === $x['durum'] ? 'yayında' : $x['durum'] ) . '</td>';
		echo '<td>' . ( $x['eksik'] ? esc_html( implode( ', ', $x['eksik'] ) ) : '—' ) . '</td>';
		echo '<td>' . ( $x['bayrak'] ? '<span style="color:#b32d2e">' . esc_html( implode( ' · ', $x['bayrak'] ) ) . '</span>' : '—' ) . '</td>';
		echo '<td>' . esc_html( $x['guncel'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	if ( count( $liste ) > 300 ) {
		echo '<p>İlk 300 satır gösteriliyor; şablon süzgeciyle daralt.</p>';
	}
	if ( function_exists( 'gbc_tani_ekran' ) ) { gbc_tani_ekran(); }
	echo '</div>';
}
