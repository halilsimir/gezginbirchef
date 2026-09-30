<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Hız & Sağlık.
 *
 * HIZ MALIYETI: bu dosya ziyaretci isteginde HIC yuklenmez. gbc-core.php
 * onu yalniz is_admin() ya da DOING_CRON durumunda cagirir. Olcum yapan
 * bir aracin sitenin kendisini yavaslatmasi kabul edilemez.
 *
 * NE OLCER
 *   1) Sablon bazinda: sunucu cevap suresi, HTML boyutu, CSS/JS dosya
 *      sayisi ve agirligi, gorsel sayisi. Her sablondan bir ornek sayfa.
 *   2) Soguk / sicak fark: ayni sayfa onbellek atlatilarak ve normal
 *      istenir. Aradaki fark LiteSpeed'in ne kadar is yaptigini gosterir.
 *   3) Agir dosyalar: en buyuk 12 varlik.
 *   4) Kullanilmayan CSS tahmini: birlesik CSS'teki sinif secicileri
 *      ornek sayfalarin HTML'inde aranir; hic gecmeyenler sayilir.
 *   5) LiteSpeed ayarlari: canli okunur, en iyi uygulamayla karsilastirilir.
 *   6) WordPress Site Sagligi: cekirdegin kendi dogrudan testleri.
 *   7) PageSpeed Insights: API anahtari girilirse mobil + masaustu puani.
 */

define( 'GBC_HZ_SON',  'gbc_hz_son' );
define( 'GBC_HZ_AYAR', 'gbc_hz_ayar' );
define( 'GBC_HZ_PSITEST', 'gbc_hz_psi_test' );

function gbc_hz_ayar( $anahtar = null ) {
	$v = array( 'psi_anahtar' => '', 'siklik' => 'weekly' );

	/* COKLU SITE: PSI anahtari bir kez girilsin, butun ag kullansin.
	   Once ag ayarina bakilir, site ayari onu ezebilir. */
	if ( is_multisite() ) {
		$ag = get_site_option( GBC_HZ_AYAR, array() );
		if ( is_array( $ag ) ) { $v = array_merge( $v, array_intersect_key( $ag, $v ) ); }
	}
	$k = get_option( GBC_HZ_AYAR, array() );
	if ( is_array( $k ) ) {
		foreach ( array_intersect_key( $k, $v ) as $kk => $vv ) {
			if ( '' !== $vv ) { $v[ $kk ] = $vv; }
		}
	}
	return ( null === $anahtar ) ? $v : ( isset( $v[ $anahtar ] ) ? $v[ $anahtar ] : '' );
}

/* ============================================================
   ORNEK SAYFALAR — her sablondan bir tane
   ============================================================ */
function gbc_hz_sayfalar() {
	/* COKLU SITE: olcum her zaman ICINDE BULUNULAN sitenin sayfalarini alir.
	   Agdaki baska bir siteyi olcmek icin o sitenin panelinden calistir —
	   boylece her sitenin kendi rakamlari karismaz. */
	$liste = array( 'Ana sayfa' => home_url( '/' ) );
	$sablon = array(
		22607 => 'Gezi rehberi',
		23108 => 'Liste rehberi',
		23489 => 'Detay',
		23340 => 'Rota',
		24751 => 'Tarif',
		26922 => 'Blog rehberi',
		24156 => 'Sözlük',
	);
	global $wpdb;
	foreach ( $sablon as $sid => $ad ) {
		$pid = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status='publish'
			 AND post_type IN ('post','page') AND post_content LIKE %s
			 ORDER BY post_modified DESC LIMIT 1",
			'%[wpcode id="' . (int) $sid . '"%'
		) );
		if ( $pid ) { $liste[ $ad ] = get_permalink( (int) $pid ); }
	}
	return $liste;
}

/* ============================================================
   TEK SAYFA OLCUMU
   ============================================================ */
function gbc_hz_sayfa_olc( $url, $soguk = true ) {

	$istek = $url;
	$bas   = array( 'user-agent' => 'GBC-Hiz/1.0' );
	if ( $soguk ) {
		$istek .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'gbc_hz=' . time() . wp_rand( 100, 999 );
		$bas['Cache-Control'] = 'no-cache, no-store, max-age=0';
		$bas['X-LSCACHE']     = 'no-cache';
	}

	/* ÖNEMLİ (28 Eylül 2026): buradan 'br' İSTENMEZ.
	   libcurl brotli açamıyor; sunucu br döndürünce curl gövdeyi çözmeye
	   çalışıp "cURL error 61: Unrecognized content encoding type" veriyor
	   ve bütün şablon tablosu hata basıyordu. Gövdeyi okuyacağımız istek
	   gzip ile yapılır; brotli boyutu aşağıda AYRI bir istekle, gövde hiç
	   açılmadan (decompress => false) ölçülür. */
	$bas['Accept-Encoding'] = 'gzip, deflate';

	$t0 = microtime( true );
	$c  = wp_remote_get( $istek, array( 'timeout' => 25, 'sslverify' => false, 'headers' => $bas ) );
	$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );

	if ( is_wp_error( $c ) ) {
		return array( 'hata' => $c->get_error_message(), 'ms' => $ms );
	}

	$kod  = (int) wp_remote_retrieve_response_code( $c );
	$html = (string) wp_remote_retrieve_body( $c );
	$bsl  = wp_remote_retrieve_headers( $c );
	$lsc  = is_object( $bsl ) ? ( $bsl['x-litespeed-cache'] ?? '' ) : '';

	/* HTML'in TELDEN GEÇEN boyutu: content-length varsa o, sunucu hiç
	   sıkıştırmıyorsa gövdenin kendisi, chunked yanıtta ikinci bir istekle
	   decompress=false. Açılmış boyut ayrıca saklanıyor. 28 Eylül 2026. */
	$html_ham    = strlen( $html );
	$uzunluk     = (int) wp_remote_retrieve_header( $c, 'content-length' );
	$kodlama     = (string) wp_remote_retrieve_header( $c, 'content-encoding' );
	$sikis_tur   = '' !== $kodlama ? $kodlama : 'yok';

	/* 29 Eylül 2026 — İKİNCİ DÜZELTME. İlk düzeltmede yalnız "else"
	   dalı ortak yardımcıya bağlanmıştı; panel yine 130 KB (ham boyut)
	   gösterdi. Sebep: ilk dal. Sunucu content-length + content-encoding
	   döndürdüğünde o rakam doğrudan "indirilen bayt" sayılıyordu — ama
	   o content-length AÇILMIŞ gövdenin uzunluğuydu.

	   Artık ayrı dal yok: hangi istekten gelirse gelsin rakam ortak
	   yardımcıdan geçiyor, o da açılmış boyuttan küçük olmayan hiçbir
	   ölçümü kabul etmiyor. Kabul etmezse yerelde hesaplıyor. */
	$olculen = ( $uzunluk > 0 && '' !== $kodlama ) ? $uzunluk : 0;
	$olcum_kodlama = $kodlama;

	if ( $olculen < 1 || $olculen >= $html_ham ) {
		/* Brotli dahil gerçek tel boyutu: gövde HİÇ AÇILMADAN ölçülür.
		   decompress => false olduğu için curl kodlamayı çözmeye
		   kalkmaz, 61 hatası çıkmaz. */
		$bas_br = $bas;
		$bas_br['Accept-Encoding'] = 'br, gzip, deflate';
		$c2 = wp_remote_get( $istek, array(
			'timeout'    => 25,
			'sslverify'  => false,
			'decompress' => false,
			'headers'    => $bas_br,
		) );
		if ( ! is_wp_error( $c2 ) ) {
			$u2 = (int) wp_remote_retrieve_header( $c2, 'content-length' );
			$k2 = (string) wp_remote_retrieve_header( $c2, 'content-encoding' );
			$g2 = strlen( (string) wp_remote_retrieve_body( $c2 ) );
			$olculen       = $u2 > 0 ? $u2 : $g2;
			$olcum_kodlama = $k2;
		}
	}

	$t          = gbc_hz_sikis_tahmin( $html, $olculen, $olcum_kodlama );
	$html_sikis = $t['boyut'];
	$sikis_tur  = ( 'sikistirilmiyor' === $t['yontem'] ) ? 'yok' : $t['yontem'];

	$css = array();
	$js  = array();
	$img = 0;

	if ( preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m ) ) {
		$css = array_unique( $m[1] );
	}
	if ( preg_match_all( '#<script[^>]+src=["\']([^"\']+)["\']#i', $html, $m ) ) {
		$js = array_unique( array_filter( $m[1], static function ( $u ) {
			return 0 !== strpos( $u, 'data:' );
		} ) );
	}
	$img = preg_match_all( '#<img[\s>]#i', $html );

	/* Satir ici agirlik */
	$satir_js = 0;
	if ( preg_match_all( '#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#is', $html, $m ) ) {
		foreach ( $m[1] as $x ) { $satir_js += strlen( $x ); }
	}
	$satir_css = 0;
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
		foreach ( $m[1] as $x ) { $satir_css += strlen( $x ); }
	}

	return array(
		'kod'        => $kod,
		'ms'         => $ms,
		'lscache'    => (string) $lsc,
		'html'       => $html_sikis,   /* indirilen (sikistirilmis) */
		'html_ham'   => $html_ham,     /* acilmis */
		'sikis'      => $sikis_tur,    /* br | gzip | yok */
		'css_adet'   => count( $css ),
		'js_adet'    => count( $js ),
		'resim_adet' => (int) $img,
		'satir_js'   => $satir_js,
		'satir_css'  => $satir_css,
		'css_url'    => array_slice( array_values( $css ), 0, 6 ),
		'js_url'     => array_slice( array_values( $js ), 0, 20 ),
	);
}

/**
 * Bir varlık listesinin ağırlığı — ZİYARETÇİNİN GERÇEKTEN İNDİRDİĞİ boyut.
 *
 * 28 Eylül 2026'da düzeltildi: eskiden açılmış (gzip'ten çıkmış) boyut
 * sayılıyordu, o yüzden CSS/JS ağırlığı olduğundan 4-5 kat büyük
 * görünüyordu. Artık sıkıştırılmış boyut ölçülüyor:
 *   1) content-length başlığı varsa o (sunucunun gönderdiği bayt),
 *   2) sunucu hiç sıkıştırmıyorsa gövdenin kendisi,
 *   3) chunked yanıtta başlık yoksa ikinci bir istek decompress=false ile.
 * Açılmış boyut da $ham dizisine yazılıyor; ekran ikisini birlikte gösterir.
 * Aynı adres tur boyunca bir kez inilir.
 *
 * @param array $urller Adresler.
 * @param array $tekil  (referans) adres => sıkıştırılmış bayt.
 * @param array $ham    (referans, isteğe bağlı) adres => açılmış bayt.
 * @return int Sıkıştırılmış toplam.
 */
/**
 * SIKIŞTIRILMIŞ BOYUT TAHMİNİ — tek yerden.
 *
 * 29 Eylül 2026. Aynı hata ÜÇ ayrı yerde ortaya çıktı:
 *   1) CSS dosyaları  — panel 316 KB dedi, gerçek 50 KB
 *   2) JS dosyaları   — panel 186 KB dedi, gerçek 60 KB
 *   3) Sayfa HTML'i   — panel 324 KB dedi, gerçek 108 KB
 *
 * Kök sebep hepsinde aynı: sunucu brotli ile sıkıştırıyor, WordPress'in
 * HTTP katmanı (libcurl) brotli İSTEYEMİYOR, sunucu da bu istekler için
 * gzip'e düşmüyor. Yanıt sıkıştırılmamış geliyor ve açılmış boyut
 * "ziyaretçinin indirdiği bayt" diye raporlanıyor. Eşikler sıkıştırılmış
 * boyuta göre yazıldığı için sahte "acil" alarmları çıkıyor.
 *
 * İlk iki yeri ayrı ayrı yamamak hatayı üçüncü yerde saklı bıraktı.
 * Bu yüzden hesap ARTIK TEK YERDE. Yeni bir ölçüm eklenecekse buradan
 * geçsin.
 *
 * @param string $govde       Açılmış gövde.
 * @param int    $olculen     HTTP yoluyla ölçülen boyut (0 = ölçülemedi).
 * @param string $kodlama     Sunucunun bildirdiği content-encoding.
 * @return array array( boyut, yontem, sikismis_mi )
 */
function gbc_hz_sikis_tahmin( $govde, $olculen = 0, $kodlama = '' ) {
	$acilmis = strlen( (string) $govde );
	if ( $acilmis < 1 ) {
		return array( 'boyut' => 0, 'yontem' => 'bos', 'sikismis' => false );
	}

	/* Sunucu gerçekten sıkıştırılmış yanıt verdiyse onu kullan. */
	if ( $olculen > 0 && $olculen < $acilmis && '' !== $kodlama ) {
		return array( 'boyut' => $olculen, 'yontem' => 'http-' . $kodlama, 'sikismis' => true );
	}

	/* Vermediyse YERELDE hesapla. brotli eklentisi varsa onunla; yoksa
	   gzip ile — gzip brotli'den bir miktar büyük çıkar, yani tahmin
	   ÜSTTEN olur. Eşik alarmı için doğru yön: yanlışlıkla "iyi" demeyiz. */
	if ( function_exists( 'brotli_compress' ) ) {
		$yerel  = strlen( (string) brotli_compress( $govde, 5 ) );
		$yontem = 'yerel-brotli';
	} else {
		$yerel  = strlen( (string) gzencode( $govde, 9 ) );
		$yontem = 'yerel-gzip';
	}
	if ( $yerel > 0 && $yerel < $acilmis ) {
		return array( 'boyut' => $yerel, 'yontem' => $yontem, 'sikismis' => true );
	}

	return array( 'boyut' => $acilmis, 'yontem' => 'sikistirilmiyor', 'sikismis' => false );
}

function gbc_hz_varlik_boyut( $urller, &$tekil, &$ham = null ) {
	$toplam = 0;
	foreach ( $urller as $u ) {
		if ( isset( $tekil[ $u ] ) ) { $toplam += (int) $tekil[ $u ]; continue; }

		$c = wp_remote_get( $u, array(
			'timeout'   => 20,
			'sslverify' => false,
			'headers'   => array( 'Accept-Encoding' => 'gzip, deflate' ),
		) );
		if ( is_wp_error( $c ) ) {
			$tekil[ $u ] = 0;
			if ( is_array( $ham ) ) { $ham[ $u ] = 0; }
			continue;
		}

		$govde = (string) wp_remote_retrieve_body( $c );          /* acilmis */

		/* ============================================================
		   SIKIŞTIRILMIŞ BOYUT — TEK YOL. 29 Eylül 2026'da düzeltildi.

		   ÖLÇÜLEN HATA: aynı türden sekiz CSS dosyasının yedisi 40-52 KB,
		   biri 252 KB görünüyordu. Sınıf sayıları (1009-1093) ve ölü sınıf
		   örnekleri birebir aynıydı — yani aynı dosya, altı kat farklı
		   rapor. Sebep üç dallı ölçümdü: content-length varsa o, kodlama
		   yoksa AÇILMIŞ GÖVDE, yoksa ikinci istek. Sunucu o dosyayı
		   sıkıştırmadan gönderince açılmış boyut "ziyaretçinin indirdiği
		   bayt" diye raporlanıyordu. Panelde sahte bir "acil" alarmı
		   çıkıyordu.

		   Artık tek yol: br ve gzip isteyen AYRI bir istek, gövde hiç
		   açılmadan (decompress => false). content-length varsa o, yoksa
		   ham gövdenin uzunluğu. Açılmış boyut ayrı tutuluyor; ikisi
		   eşitse sunucu gerçekten sıkıştırmıyor demektir ve satır öyle
		   işaretleniyor.
		   ============================================================ */
		$acilmis = strlen( $govde );
		$sikis   = $acilmis;
		$sikismis_mi = false;

		$k2 = '';
		$c2 = wp_remote_get( $u, array(
			'timeout'    => 20,
			'sslverify'  => false,
			'decompress' => false,
			'headers'    => array( 'Accept-Encoding' => 'br, gzip, deflate' ),
		) );
		if ( ! is_wp_error( $c2 ) ) {
			$u2 = (int) wp_remote_retrieve_header( $c2, 'content-length' );
			$k2 = (string) wp_remote_retrieve_header( $c2, 'content-encoding' );
			$g2 = strlen( (string) wp_remote_retrieve_body( $c2 ) );
			$olculen = $u2 > 0 ? $u2 : $g2;

			/* Sıkıştırılmış boyut açılmıştan BÜYÜK olamaz; olduysa ölçüm
			   güvenilmez, açılmış boyuta düşülür. */
			if ( $olculen > 0 && $olculen <= $acilmis ) {
				$sikis = $olculen;
				$sikismis_mi = ( '' !== $k2 && $olculen < $acilmis );
			}
		}

		/* ============================================================
		   YEREL HESAP — 29 Eylül 2026'da ÖLÇÜLEREK eklendi.

		   ÖLÇÜLEN HATA: panel "CSS 316 KB indiriliyor ... ziyaretçinin
		   gerçekten indirdiği bayt" diyordu. Tarayıcının kendi ağ ölçümü
		   (Resource Timing encodedBodySize) aynı dosya için 50 KB dedi.
		   316 KB, dosyanın AÇILMIŞ boyutunun ta kendisiydi (323.219 bayt).

		   SEBEP: sunucu brotli ile sıkıştırıyor, ama WordPress'in HTTP
		   katmanı (libcurl) brotli isteyemiyor — CURLOPT_ENCODING kendi
		   desteklediğiyle sınırlı. Sunucu bu dosyalar için gzip'e de
		   düşmeyince istek SIKIŞTIRILMAMIŞ dönüyor ve açılmış boyut
		   "indirilen bayt" diye raporlanıyordu. Eşik 60/80 KB olduğu için
		   50 KB'lık gerçek bir dosya "acil" alarmı üretiyordu. Sahte alarm.

		   ÇÖZÜM: HTTP yoluyla sıkışma gözlenmediyse boyutu YERELDE
		   hesapla. brotli eklentisi varsa onunla, yoksa gzip ile. gzip
		   brotli'den bir miktar büyük çıkar, yani tahmin ÜSTTEN olur —
		   eşik alarmı için doğru yön. Hangi yöntemle bulunduğu
		   kaydediliyor; panel bunu yazıyor ki ham sayı sanılmasın.
		   ============================================================ */
		/* 29 Eyl 2026: hesap ORTAK yardımcıya taşındı. Aynı hata CSS,
		   JS ve HTML'de ayrı ayrı çıktığı için mantık tek yerde tutuluyor. */
		$t = gbc_hz_sikis_tahmin( $govde, $sikismis_mi ? $sikis : 0, $k2 );
		$sikis       = $t['boyut'];
		$sikismis_mi = $t['sikismis'];
		$yontem      = $t['yontem'];

		$tekil[ $u ] = $sikis;
		if ( is_array( $ham ) ) { $ham[ $u ] = $acilmis; }
		$GLOBALS['gbc_hz_sikisma'][ $u ] = $sikismis_mi;
		$GLOBALS['gbc_hz_yontem'][ $u ]  = $yontem;
		$toplam += $sikis;
	}
	return $toplam;
}

/**
 * Bir CSS sınıfı bizim mi, temanın/çekirdeğin mi?
 *
 * LiteSpeed bütün CSS'i tek dosyada birleştiriyor; dosya adına bakarak
 * ayırmak mümkün değil. Ayrım sınıf adının önekinden yapılıyor. Bizim
 * şablonlarımız gbc-, gz-, gz2-, v1-, v11-, tr-, fb-, yn- öneklerini
 * kullanıyor; Astra ve WordPress çekirdeği ast-, wp-, has-, is-, alignfull
 * gibi kendi öneklerini. Tanımadığımız her şey "tema" sayılır — yani
 * silinecekler listesine YANLIŞLIKLA bizim olmayan bir sınıf girmez.
 */
/* ============================================================
   ÖLÜ SINIF GÜVENLİĞİ — 29 Eylül 2026
   ------------------------------------------------------------
   "Sayfada geçmiyor" ile "silinebilir" AYNI ŞEY DEĞİL.

   ÖLÇÜLEN TEHLİKE: panelin ölü sınıf listesinde ilk iki sırada
   .gbc-col ve .gbc-ac vardı. İkisi de JavaScript'in TIKLAMADA eklediği
   durum sınıfları (classList.add). Statik HTML'de hiç görünmezler ama
   silinirlerse akordeon ve sütun açma kapanma sessizce bozulur.
   Aynısı .gbc-flash, .gbc-ok, .gbc-err için de geçerli.

   Bu yüzden her ölü sınıf üç kovadan birine düşüyor:

     sil       Eklenti kaynağında HİÇ geçmiyor. Eski şablonlardan kalmış,
               güvenle silinir.
     js        classList/className ile çalışma anında ekleniyor. SİLİNMEZ.
     kosullu   Kaynakta var ama ölçülen sayfalarda basılmamış. Önce nerede
               kullanıldığı bulunur, sonra karar verilir.
   ============================================================ */

/** Eklentinin kendi kaynak metni — bir kez okunur. */
function gbc_hz_kaynak_metni() {
	static $metin = null;
	if ( null !== $metin ) { return $metin; }

	$metin = '';
	foreach ( array( 'modules', 'inc' ) as $klasor ) {
		$yol = GBC_CORE_DIR . $klasor;
		if ( ! is_dir( $yol ) ) { continue; }
		foreach ( (array) glob( $yol . '/*.php' ) as $dosya ) {
			/* 30 Eyl 2026 — KENDINI MASKELEME HATASI.
			   inc/css-suzgec.php OLU sinif adlarinin LISTESINI tutuyor.
			   Bu dosya taramaya girince her olu sinif "eklenti kaynaginda
			   geciyor" sayiliyor ve bir daha asla olu olarak raporlanmiyor;
			   yani liste kendi kendini gorunmez yapiyordu. Taramanin disinda. */
			if ( 'css-suzgec.php' === basename( $dosya ) ) { continue; }
			$i = file_get_contents( $dosya );
			if ( false !== $i ) { $metin .= "\n" . $i; }
		}
	}
	return $metin;
}

/** JavaScript'in çalışma anında eklediği sınıflar. */
function gbc_hz_js_siniflari() {
	static $liste = null;
	if ( null !== $liste ) { return $liste; }

	$liste = array();
	$k = gbc_hz_kaynak_metni();

	/* classList.add / toggle / remove ( 'x' , "y" ) */
	if ( preg_match_all( '/classList\s*\.\s*(?:add|toggle|remove)\s*\(([^)]*)\)/i', $k, $m ) ) {
		foreach ( $m[1] as $arg ) {
			if ( preg_match_all( '/["\']([A-Za-z0-9_-]{2,})["\']/', $arg, $t ) ) {
				foreach ( $t[1] as $ad ) { $liste[ $ad ] = true; }
			}
		}
	}
	/* className = 'x'  ya da  className += ' x' */
	if ( preg_match_all( '/className\s*\+?=\s*["\']([^"\']+)["\']/i', $k, $m ) ) {
		foreach ( $m[1] as $blok ) {
			foreach ( preg_split( '/\s+/', trim( $blok ) ) as $ad ) {
				if ( '' !== $ad ) { $liste[ $ad ] = true; }
			}
		}
	}

	$liste = array_keys( $liste );
	return $liste;
}

/**
 * Bir ölü sınıfla ne yapılmalı?
 *
 * @return string sil | js | kosullu
 */
function gbc_hz_olu_karar( $sinif ) {
	$sinif = ltrim( (string) $sinif, '.' );
	if ( '' === $sinif ) { return 'kosullu'; }

	if ( in_array( $sinif, gbc_hz_js_siniflari(), true ) ) { return 'js'; }

	/* Kaynakta geçiyor mu? Sınıf adı tam kelime olarak aranır. */
	$k = gbc_hz_kaynak_metni();
	if ( false !== strpos( $k, $sinif ) ) { return 'kosullu'; }

	return 'sil';
}

/** Karara göre etiket ve renk. */
function gbc_hz_olu_etiket( $karar ) {
	$a = array(
		'sil'     => array( __( 'silinebilir', 'gbc-core' ), '#1A7F37', '#E8F3EC' ),
		'js'      => array( __( 'SİLME — JS ekliyor', 'gbc-core' ), '#B3261E', '#FBE7E7' ),
		'kosullu' => array( __( 'koşullu — önce bak', 'gbc-core' ), '#8A6100', '#FCF6E8' ),
	);
	return isset( $a[ $karar ] ) ? $a[ $karar ] : $a['kosullu'];
}

function gbc_hz_sinif_kaynak( $sinif ) {
	$s = strtolower( (string) $sinif );
	foreach ( array( 'gbc-', 'gz-', 'gz2-', 'v1-', 'v11-', 'tr-', 'fb-', 'yn-' ) as $onek ) {
		if ( 0 === strpos( $s, $onek ) ) { return 'bizim'; }
	}
	return 'tema';
}

/* ============================================================
   KULLANILMAYAN CSS TAHMINI
   Birlesik CSS'teki .sinif secicileri toplanir, ornek sayfalarin
   HTML'inde aranir. Hic gecmeyenler "kullanilmiyor" sayilir.
   TAHMINDIR: JavaScript'in sonradan ekledigi siniflari goremez.
   ============================================================ */
function gbc_hz_olu_css( $css_metin, $html_yigin ) {
	if ( '' === $css_metin ) { return null; }

	preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]{2,})/', $css_metin, $m );
	$siniflar = array_unique( $m[1] );
	if ( ! $siniflar ) { return null; }

	$kullanilan = 0;
	$olu        = array();
	foreach ( $siniflar as $s ) {
		if ( false !== strpos( $html_yigin, $s ) ) {
			$kullanilan++;
		} else {
			if ( count( $olu ) < 40 ) { $olu[] = $s; }
		}
	}
	$toplam = count( $siniflar );
	return array(
		'toplam'      => $toplam,
		'kullanilan'  => $kullanilan,
		'olu'         => $toplam - $kullanilan,
		'yuzde'       => $toplam ? (int) round( ( $toplam - $kullanilan ) / $toplam * 100 ) : 0,
		'ornekler'    => $olu,
	);
}

/* ============================================================
   LITESPEED AYAR DENETIMI
   ============================================================ */
function gbc_hz_litespeed() {
	global $wpdb;

	/* COKLU SITE: LiteSpeed ayarlari hem site secenegi hem AG secenegi
	   olarak durabilir. Agda etkinlestirilmis kurulumda asil deger
	   sitemeta tablosundadir; yalniz options'a bakmak "ayar yok" der. */
	$ayar = array();

	$satirlar = $wpdb->get_results(
		"SELECT option_name, option_value FROM {$wpdb->options}
		 WHERE option_name LIKE 'litespeed.conf.%' LIMIT 400",
		ARRAY_A
	);
	foreach ( (array) $satirlar as $r ) {
		$ayar[ str_replace( 'litespeed.conf.', '', $r['option_name'] ) ] = $r['option_value'];
	}

	if ( is_multisite() ) {
		$ag = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->sitemeta}
				 WHERE site_id = %d AND meta_key LIKE 'litespeed.conf.%%' LIMIT 400",
				get_current_network_id()
			),
			ARRAY_A
		);
		foreach ( (array) $ag as $r ) {
			$k = str_replace( 'litespeed.conf.', '', $r['meta_key'] );
			/* Site ayari yoksa ag ayari gecerli. */
			if ( ! isset( $ayar[ $k ] ) || '' === $ayar[ $k ] ) {
				$ayar[ $k ] = $r['meta_value'];
			}
		}
	}

	if ( ! $ayar ) {
		return array( 'var' => false );
	}

	$oku = static function ( $k ) use ( $ayar ) {
		return isset( $ayar[ $k ] ) ? $ayar[ $k ] : null;
	};

	$k = array();
	$bilerek = function_exists( 'gbc_hz_bilerek' ) ? gbc_hz_bilerek() : array();

	$ek = static function ( &$k, $anahtar, $ad, $deger, $iyi, $not, $onem = 'orta' ) use ( $bilerek ) {
		/* Bilerek kapalı bırakılan ayar: beklenen değer tersine çevrilir,
		   satır "bilerek" diye işaretlenir, sorun sayılmaz. */
		$kasitli = isset( $bilerek[ $anahtar ] );
		if ( $kasitli ) {
			$iyi = $bilerek[ $anahtar ][0];
			$not = $bilerek[ $anahtar ][1];
			$onem = 'bilgi';
		}
		$k[] = array( 'anahtar' => $anahtar, 'ad' => $ad, 'deger' => $deger, 'iyi' => $iyi,
			'not' => $not, 'onem' => $onem, 'bilerek' => $kasitli );
	};

	$ek( $k, 'cache', __( 'Sayfa önbelleği', 'gbc-core' ), $oku( 'cache' ), '1',
		__( 'Kapalıysa her ziyaretçi için PHP baştan çalışır. En büyük tek kazanç budur.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'cache-browser', __( 'Tarayıcı önbelleği', 'gbc-core' ), $oku( 'cache-browser' ), '1',
		__( 'İkinci ziyarette resim, CSS ve JS hiç indirilmez.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'cache-ttl_browser', __( 'Tarayıcı önbellek süresi', 'gbc-core' ), $oku( 'cache-ttl_browser' ), '31557600',
		__( '1 yıl olmalı. Kısa süre ikinci ziyaretin hızını yer.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-css_min', __( 'CSS küçültme', 'gbc-core' ), $oku( 'optm-css_min' ), '1',
		__( 'Boşluk ve yorumları siler.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-css_comb', __( 'CSS birleştirme', 'gbc-core' ), $oku( 'optm-css_comb' ), '1',
		__( 'Çok dosya yerine tek dosya. Açık.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-js_min', __( 'JS küçültme', 'gbc-core' ), $oku( 'optm-js_min' ), '1',
		__( 'JS dosyalarını küçültür.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-js_defer', __( 'JS ertelenmiş yükleme', 'gbc-core' ), $oku( 'optm-js_defer' ), '1',
		__( 'Betikler sayfa çizildikten sonra yüklenir. En çok LCP\'yi düzeltir.', 'gbc-core' ), 'yuksek' );
	/* 28 Eylül 2026 — DÜZELTİLDİ. Panel yalnız optm-ccss_gen'e bakıp
	   "Kritik CSS Açık" diyordu; oysa CCSS'in sayfaya UYGULANMASI
	   optm-css_async'e bağlı. Canlıda css_async = 0 iken panel yeşil
	   gösteriyordu. Belirleyici anahtar css_async; ccss_gen ayrı satır. */
	$ek( $k, 'optm-css_async', __( 'Kritik CSS (CCSS) uygulanıyor mu', 'gbc-core' ), $oku( 'optm-css_async' ), '1',
		__( 'İlk ekranın CSS\'i satır içi basılır, gerisi sonra yüklenir. Bu kapalıyken kritik CSS üretilse bile kullanılmaz.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'optm-ccss_gen', __( 'Kritik CSS üretimi', 'gbc-core' ), $oku( 'optm-ccss_gen' ), '1',
		__( 'Kritik CSS arka planda üretilir. Tek başına yetmez; üstteki satır da açık olmalı.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-ucss', __( 'Kullanılmayan CSS (UCSS)', 'gbc-core' ), $oku( 'optm-ucss' ), '1',
		__( 'Sayfada geçmeyen CSS kurallarını atar.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'media-lazy', __( 'Görsel tembel yükleme', 'gbc-core' ), $oku( 'media-lazy' ), '1',
		__( 'Ekrana gelmeyen görsel indirilmez.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'img_optm-webp', __( 'WebP dönüşümü', 'gbc-core' ), $oku( 'img_optm-webp' ), '1',
		__( 'Görselleri %30 daha küçük biçimde sunar.', 'gbc-core' ), 'yuksek' );
	$ek( $k, 'img_optm-auto', __( 'Görsel sıkıştırma', 'gbc-core' ), $oku( 'img_optm-auto' ), '1',
		__( 'Yeni yüklenen görseller otomatik sıkışır.', 'gbc-core' ), 'orta' );
	$ek( $k, 'object', __( 'Nesne önbelleği', 'gbc-core' ), $oku( 'object' ), '1',
		__( 'Veritabanı sorgularını belleğe alır. Sunucuda Redis/Memcached varsa aç.', 'gbc-core' ), 'orta' );
	$ek( $k, 'guest', __( 'Misafir modu', 'gbc-core' ), $oku( 'guest' ), '1',
		__( 'İlk ziyaretçiye hazır kopya verir. Mobil puanı doğrudan yükseltir.', 'gbc-core' ), 'orta' );
	$ek( $k, 'optm-localize', __( 'Gömülü içerik ertelemesi', 'gbc-core' ), $oku( 'optm-localize' ), null,
		__( 'Dış betikleri kendi sunucundan sunar.', 'gbc-core' ), 'dusuk' );
	$ek( $k, 'optm-emoji_rm', __( 'Emoji kaldırma', 'gbc-core' ), $oku( 'optm-emoji_rm' ), '1',
		__( 'WordPress emoji betiğini siler.', 'gbc-core' ), 'dusuk' );
	$ek( $k, 'crawler', __( 'Tarayıcı ön yükleme (crawler)', 'gbc-core' ), $oku( 'crawler' ), null,
		__( 'Önbelleği kendi doldurur, ilk ziyaretçi beklemez.', 'gbc-core' ), 'dusuk' );

	return array( 'var' => true, 'kontroller' => $k, 'toplam_ayar' => count( $ayar ) );
}

/* ============================================================
   WORDPRESS SITE SAGLIGI — cekirdegin kendi dogrudan testleri
   ============================================================ */
function gbc_hz_site_sagligi() {
	if ( ! class_exists( 'WP_Site_Health' ) ) {
		$yol = ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		if ( ! file_exists( $yol ) ) { return array(); }
		require_once $yol;
	}
	if ( ! class_exists( 'WP_Site_Health' ) ) { return array(); }

	$sh = WP_Site_Health::get_instance();
	$testler = WP_Site_Health::get_tests();
	$cikti = array();

	foreach ( (array) ( $testler['direct'] ?? array() ) as $test ) {
		$ad = isset( $test['test'] ) ? $test['test'] : '';
		if ( ! is_string( $ad ) || '' === $ad ) { continue; }
		$yordam = 'get_test_' . $ad;
		if ( ! method_exists( $sh, $yordam ) ) { continue; }
		try {
			$s = $sh->$yordam();
		} catch ( \Throwable $e ) {
			continue;
		}
		if ( ! is_array( $s ) || empty( $s['label'] ) ) { continue; }
		$cikti[] = array(
			'baslik' => wp_strip_all_tags( $s['label'] ),
			'durum'  => isset( $s['status'] ) ? $s['status'] : 'recommended',
			'aciklama' => wp_strip_all_tags( isset( $s['description'] ) ? $s['description'] : '' ),
		);
	}
	/* Coklu siteye ozel bilgi satiri */
	if ( is_multisite() ) {
		$cikti[] = array(
			'baslik'   => __( 'Çoklu site ağı', 'gbc-core' ),
			'durum'    => 'good',
			'aciklama' => sprintf(
				__( 'Ağda %1$d site var. Bu ölçüm yalnız içinde bulunduğun siteyi kapsar; eklentiler ağ genelinde etkin olduğu için bir sitedeki ağır eklenti diğerlerini de yavaşlatır.', 'gbc-core' ),
				(int) get_blog_count()
			),
		);
	}

	return $cikti;
}

/* ============================================================
   PAGESPEED INSIGHTS
   ============================================================ */
function gbc_hz_psi( $url, $strateji ) {
	$anahtar = gbc_hz_ayar( 'psi_anahtar' );
	if ( '' === $anahtar ) { return array( 'hata' => 'anahtar-yok' ); }

	$api = add_query_arg( array(
		'url'      => $url,
		'strategy' => $strateji,
		'category' => 'performance',
		'key'      => $anahtar,
	), 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

	$c = wp_remote_get( $api, array( 'timeout' => 90 ) );
	if ( is_wp_error( $c ) ) { return array( 'hata' => $c->get_error_message() ); }
	$kod = (int) wp_remote_retrieve_response_code( $c );
	if ( 200 !== $kod ) { return array( 'hata' => 'HTTP ' . $kod ); }

	$j = json_decode( (string) wp_remote_retrieve_body( $c ), true );
	$lr = $j['lighthouseResult'] ?? array();
	$a  = $lr['audits'] ?? array();

	$firsat = array();
	foreach ( $a as $x ) {
		if ( ! empty( $x['details']['type'] ) && 'opportunity' === $x['details']['type']
		     && ! empty( $x['numericValue'] ) && $x['numericValue'] > 100 ) {
			$firsat[] = array( 'is' => $x['title'], 'ms' => (int) round( $x['numericValue'] ) );
		}
	}
	usort( $firsat, static function ( $p, $q ) { return $q['ms'] - $p['ms']; } );

	return array(
		'puan' => (int) round( ( $lr['categories']['performance']['score'] ?? 0 ) * 100 ),
		'LCP'  => $a['largest-contentful-paint']['displayValue'] ?? '',
		'CLS'  => $a['cumulative-layout-shift']['displayValue'] ?? '',
		'TBT'  => $a['total-blocking-time']['displayValue'] ?? '',
		'FCP'  => $a['first-contentful-paint']['displayValue'] ?? '',
		'firsatlar' => array_slice( $firsat, 0, 6 ),
	);
}



/* ============================================================
   TEK TIKLA DUZELT
   LiteSpeed ayarlari dogrudan veritabanina yazilmaz; eklentinin
   kendi kancasi kullanilir (litespeed_option_update). Boylece
   eklenti kendi ic onbellegini ve .htaccess'ini da gunceller.
   Sadece olcumde EKSIK cikan ayarlar degistirilir.
   ============================================================ */
/**
 * BİLEREK KAPALI AYARLAR.
 *
 * Bazı LiteSpeed ayarları ölçüme göre "eksik" görünür ama kapalı kalmaları
 * bir karardır. Bunlar ne uyarı listesinde sorun sayılır ne de "düzelt"
 * düğmesiyle açılır; tabloda "bilerek kapalı" diye işaretlenir.
 *
 * anahtar => array( beklenen deger, sebep )
 */
function gbc_hz_bilerek() {
	return array(
		'optm-ucss' => array( '0', __( 'Bilerek kapalı (28 Eylül 2026): UCSS açılınca ana sayfa sekmeleri, mobil kart çizgisi ve SVG bozuldu. Ölü CSS bunun yerine şablon başına CSS ayırarak temizleniyor.', 'gbc-core' ) ),
	);
}

function gbc_hz_duzeltilebilir() {
	/* anahtar => array( etiket, hedef deger, aciklama ) */
	return array(
		/* optm-ucss BİLEREK KAPALI — gbc_hz_bilerek(). Buraya konulursa
		   "Düzelt" düğmesi onu tekrar açar ve tasarım bozulur. */
		'optm-css_async'   => array( __( 'Kritik CSS uygulama (Load CSS Asynchronously)', 'gbc-core' ), 1,
			__( 'Bu açılmadan üretilen kritik CSS kullanılmaz.', 'gbc-core' ) ),
		'optm-ccss_gen'    => array( __( 'Kritik CSS üretimi', 'gbc-core' ), 1,
			__( 'İlk ekranın CSS’i satır içi basılır.', 'gbc-core' ) ),
		'optm-js_defer'    => array( __( 'JS ertelenmiş yükleme', 'gbc-core' ), 1,
			__( 'Betikler sayfa çizildikten sonra çalışır.', 'gbc-core' ) ),
		'optm-css_min'     => array( __( 'CSS küçültme', 'gbc-core' ), 1, '' ),
		'optm-js_min'      => array( __( 'JS küçültme', 'gbc-core' ), 1, '' ),
		'media-lazy'       => array( __( 'Görsel tembel yükleme', 'gbc-core' ), 1, '' ),
		'img_optm-webp'    => array( __( 'WebP dönüşümü', 'gbc-core' ), 1, '' ),
		'img_optm-auto'    => array( __( 'Görsel otomatik sıkıştırma', 'gbc-core' ), 1, '' ),
		'guest'            => array( __( 'Misafir modu', 'gbc-core' ), 1, '' ),
		'optm-emoji_rm'    => array( __( 'Emoji betiğini kaldır', 'gbc-core' ), 1, '' ),
		'cache-ttl_browser' => array( __( 'Tarayıcı önbellek süresi', 'gbc-core' ), 31557600, '' ),
	);
}

/* MCP ve OAuth uclari onbelleklenirse baglanti bir kez calisip sonra bozulur.
   LiteSpeed'in "onbellege alma" listesine bu yollar eklenir. */
function gbc_hz_mcp_yollari() {
	return array( '/wp-json/easy-mcp-ai/', '/.well-known/oauth-', '/.well-known/openid-configuration' );
}

function gbc_hz_mcp_haric_eksik() {
	$mevcut = apply_filters( 'litespeed_conf', null, 'cache-exc' );
	if ( ! is_array( $mevcut ) ) { $mevcut = array(); }
	$eksik = array();
	foreach ( gbc_hz_mcp_yollari() as $y ) {
		if ( ! in_array( $y, $mevcut, true ) ) { $eksik[] = $y; }
	}
	return array( 'mevcut' => $mevcut, 'eksik' => $eksik );
}

function gbc_hz_mcp_haric_ekle() {
	$d = gbc_hz_mcp_haric_eksik();
	if ( ! $d['eksik'] ) { return 0; }
	$yeni = array_values( array_unique( array_merge( $d['mevcut'], $d['eksik'] ) ) );
	do_action( 'litespeed_option_update', 'cache-exc', $yeni );
	$son = apply_filters( 'litespeed_conf', null, 'cache-exc' );
	if ( ! is_array( $son ) ) { return 0; }
	$sayac = 0;
	foreach ( gbc_hz_mcp_yollari() as $y ) { if ( in_array( $y, $son, true ) ) { $sayac++; } }
	return $sayac;
}

/** Nesne onbellegi: dosya var mi, WordPress kullaniyor mu, hangi sunucu ayarli. */
function gbc_hz_nesne_onbellek() {
	$d = array(
		'dosya'     => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
		'kullanim'  => function_exists( 'wp_using_ext_object_cache' ) ? (bool) wp_using_ext_object_cache() : false,
		'ls_acik'   => (string) apply_filters( 'litespeed_conf', '', 'object' ) === '1',
		'tur'       => (string) apply_filters( 'litespeed_conf', '', 'object-kind' ),
		'host'      => (string) apply_filters( 'litespeed_conf', '', 'object-host' ),
		'port'      => (string) apply_filters( 'litespeed_conf', '', 'object-port' ),
		'redis_php' => class_exists( 'Redis' ),
		'memc_php'  => class_exists( 'Memcached' ) || class_exists( 'Memcache' ),
	);

	/* Ayarli sunucuya gercekten baglanabiliyor muyuz — tahmin degil, deneme. */
	$d['baglanti'] = null;
	$host = $d['host'] ? $d['host'] : '127.0.0.1';
	$port = $d['port'] ? (int) $d['port'] : ( '1' === $d['tur'] ? 6379 : 11211 );
	if ( $host && 0 !== strpos( $host, '/' ) ) {
		$soket = @fsockopen( $host, $port, $eno, $estr, 2 );
		if ( $soket ) { $d['baglanti'] = true; fclose( $soket ); }
		else { $d['baglanti'] = false; $d['baglanti_hata'] = $estr ? $estr : 'bağlanamadı'; }
	}
	$d['host_denenen'] = $host . ':' . $port;
	return $d;
}

/** Olcumdeki LiteSpeed sonucundan, su an EKSIK olan duzeltilebilir ayarlari cikarir. */
function gbc_hz_eksik_ayarlar() {
	$ls = gbc_hz_litespeed();
	if ( empty( $ls['var'] ) || empty( $ls['kontroller'] ) ) { return array(); }

	$harita = gbc_hz_duzeltilebilir();
	$eksik  = array();
	foreach ( (array) $ls['kontroller'] as $k ) {
		if ( empty( $k['anahtar'] ) || ! isset( $harita[ $k['anahtar'] ] ) ) { continue; }
		if ( ! empty( $k['bilerek'] ) ) { continue; }   /* bilerek kapalı — sorun değil */
		if ( null === $k['iyi'] ) { continue; }
		if ( (string) $k['deger'] === (string) $k['iyi'] ) { continue; }
		$eksik[ $k['anahtar'] ] = array(
			'ad'     => $harita[ $k['anahtar'] ][0],
			'hedef'  => $harita[ $k['anahtar'] ][1],
			'simdi'  => $k['deger'],
			'neden'  => $harita[ $k['anahtar'] ][2] ? $harita[ $k['anahtar'] ][2] : $k['not'],
		);
	}
	return $eksik;
}

/** Secilen ayarlari uygular. Donen dizi: yapilan / yapilamayan. */
function gbc_hz_uygula( $anahtarlar ) {
	$rapor = array( 'oldu' => array(), 'olmadi' => array() );
	$harita = gbc_hz_duzeltilebilir();

	if ( ! defined( 'LSCWP_V' ) && ! class_exists( '\\LiteSpeed\\Core' ) ) {
		$rapor['olmadi'][] = __( 'LiteSpeed Cache eklentisi bulunamadı.', 'gbc-core' );
		return $rapor;
	}

	foreach ( (array) $anahtarlar as $a ) {
		if ( ! isset( $harita[ $a ] ) ) { continue; }
		$hedef = $harita[ $a ][1];

		/* Eklentinin kendi yazma kancasi. */
		do_action( 'litespeed_option_update', $a, $hedef );

		/* Gercekten yazildi mi — okuyup dogrula. Tahmin etmeyiz, olceriz. */
		$simdi = apply_filters( 'litespeed_conf', null, $a );
		if ( null === $simdi ) { $simdi = get_option( 'litespeed.conf.' . $a, null ); }

		if ( null !== $simdi && (string) $simdi === (string) $hedef ) {
			$rapor['oldu'][] = $harita[ $a ][0];
		} else {
			$rapor['olmadi'][] = $harita[ $a ][0];
		}
	}

	if ( $rapor['oldu'] ) {
		/* Yeni ayar eski onbellekli sayfalara islemez. */
		do_action( 'litespeed_purge_all' );
	}
	return $rapor;
}

/* ============================================================
   PAGESPEED BAGLANTI TESTI — tek cagri, birkac saniye
   Tam olcum 2-3 dakika surebilir; bu buton anahtarin calisip
   calismadigini beklemeden soyler. Sonuc secenekte saklanir.
   ============================================================ */
/** Sunucunun DISARI CIKIS IP'si. Sitenin A kaydiyla ayni olmak zorunda degil;
    Google API anahtarina IP kisiti konacaksa dogru adres budur. 12 saat saklanir. */
function gbc_hz_cikis_ip() {
	$v = get_transient( 'gbc_hz_cikis_ip' );
	if ( is_array( $v ) ) { return $v; }

	$v = array( 'v4' => '', 'v6' => '' );
	foreach ( array( 'v4' => 'https://api.ipify.org', 'v6' => 'https://api6.ipify.org' ) as $k => $u ) {
		$c = wp_remote_get( $u, array( 'timeout' => 8 ) );
		if ( ! is_wp_error( $c ) && 200 === (int) wp_remote_retrieve_response_code( $c ) ) {
			$ip = trim( (string) wp_remote_retrieve_body( $c ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { $v[ $k ] = $ip; }
		}
	}
	set_transient( 'gbc_hz_cikis_ip', $v, 12 * HOUR_IN_SECONDS );
	return $v;
}

function gbc_hz_psi_test() {
	$anahtar = gbc_hz_ayar( 'psi_anahtar' );
	$c = array( 'zaman' => time(), 'anahtar' => ( '' !== $anahtar ) );

	if ( '' === $anahtar ) {
		$c['hata'] = 'anahtar-yok';
		update_option( GBC_HZ_PSITEST, $c, false );
		return $c;
	}

	$bas = microtime( true );
	$son = gbc_hz_psi( home_url( '/' ), 'mobile' );
	$c['sure_ms'] = (int) round( ( microtime( true ) - $bas ) * 1000 );

	if ( ! empty( $son['hata'] ) ) {
		$c['hata'] = $son['hata'];
	} else {
		$c['puan'] = (int) $son['puan'];
		$c['LCP']  = $son['LCP'];
		$c['CLS']  = $son['CLS'];
		$c['TBT']  = $son['TBT'];
	}
	update_option( GBC_HZ_PSITEST, $c, false );
	return $c;
}

/* ============================================================
   TAM TARAMA
   ============================================================ */
/* ============================================================
   TARAMA — ADIM ADIM
   NEDEN ADIM ADIM: tek istekte 8 sayfa x 3 indirme + 4 PageSpeed cagrisi
   2-3 dakika suruyordu. Sunucu uzun istegi kesince tarayicida BEYAZ EKRAN
   kaliyor, o ana kadarki is de kayboluyordu (28 Eylul 2026'da olculdu).
   Artik her istek TEK is yapar, sonucu kaydeder, sayfa kendini yeniler.
   Bir adim koparsa yalniz o adim yinelenir; toplanan veri durur.
   ============================================================ */
define( 'GBC_HZ_IS', 'gbc_hz_is' );

/** Is listesini kurar. Her oge tek bir istekte bitecek kadar kucuk. */
function gbc_hz_is_baslat( $psi_de = false ) {
	$sayfalar = gbc_hz_sayfalar();

	$adimlar = array();
	foreach ( $sayfalar as $ad => $url ) {
		$adimlar[] = array( 'tur' => 'sayfa', 'ad' => $ad, 'url' => $url );
	}
	if ( $psi_de ) {
		$ilk = array_slice( $sayfalar, 0, 2, true );
		foreach ( $ilk as $ad => $url ) {
			$adimlar[] = array( 'tur' => 'psi', 'ad' => $ad, 'url' => $url, 'strateji' => 'mobile' );
			$adimlar[] = array( 'tur' => 'psi', 'ad' => $ad, 'url' => $url, 'strateji' => 'desktop' );
		}
	}
	$adimlar[] = array( 'tur' => 'kapanis', 'ad' => __( 'Ayarlar ve site sağlığı', 'gbc-core' ) );

	$is = array(
		'basladi' => time(),
		'psi'     => (bool) $psi_de,
		'i'       => 0,
		'adimlar' => $adimlar,
		'veri'    => array(
			'zaman'       => time(),
			'sayfa'       => array(),
			'psi'         => array(),
			'psi_istendi' => (bool) $psi_de,
			'tekil'       => array(),
			'css_gorulen' => array(),
			'css_sinif'   => array(),
			'kullanilan'  => array(),
		),
	);
	update_option( GBC_HZ_IS, $is, false );
	return $is;
}

/** CSS metnindeki sinif secicileri. */
function gbc_hz_css_siniflar( $css ) {
	preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]{2,})/', (string) $css, $m );
	return array_values( array_unique( $m[1] ) );
}

/** HTML'de class="" iceren butun simgeler. Alt dizge degil, TAM eslesme —
    eski yontem ".ast-float" icin "ast-float-left" gorunce kullanildi saniyordu. */
function gbc_hz_html_siniflar( $html ) {
	preg_match_all( '/\bclass\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $m );
	$c = array();
	foreach ( $m[1] as $blok ) {
		foreach ( preg_split( '/\s+/', trim( $blok ) ) as $t ) {
			if ( '' !== $t ) { $c[ $t ] = true; }
		}
	}
	return array_keys( $c );
}

/** Tek adim calistirir. Doner: array( bitti, etiket, kalan, toplam ). */
function gbc_hz_is_adim() {
	$is = get_option( GBC_HZ_IS, array() );
	if ( empty( $is['adimlar'] ) ) { return array( 'bitti' => true, 'etiket' => '', 'kalan' => 0, 'toplam' => 0 ); }

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 180 ); }
	ignore_user_abort( true );

	$i      = (int) $is['i'];
	$toplam = count( $is['adimlar'] );
	if ( $i >= $toplam ) { return array( 'bitti' => true, 'etiket' => '', 'kalan' => 0, 'toplam' => $toplam ); }

	$a = $is['adimlar'][ $i ];
	$v = $is['veri'];

	if ( 'sayfa' === $a['tur'] ) {
		$soguk = gbc_hz_sayfa_olc( $a['url'], true );
		$sicak = gbc_hz_sayfa_olc( $a['url'], false );

		if ( isset( $soguk['hata'] ) ) {
			$v['sayfa'][ $a['ad'] ] = array( 'hata' => $soguk['hata'], 'url' => $a['url'] );
		} else {
			$tekil    = $v['tekil'];
			$ham      = isset( $v['tekil_ham'] ) ? (array) $v['tekil_ham'] : array();
			$css_bayt = gbc_hz_varlik_boyut( $soguk['css_url'], $tekil, $ham );
			$js_bayt  = gbc_hz_varlik_boyut( $soguk['js_url'], $tekil, $ham );
			$v['tekil']     = $tekil;
			$v['tekil_ham'] = $ham;

			/* Acilmis toplamlar — ekranda parantez icinde gosterilir. */
			$css_ham = 0; foreach ( $soguk['css_url'] as $cu ) { $css_ham += isset( $ham[ $cu ] ) ? (int) $ham[ $cu ] : 0; }
			$js_ham  = 0; foreach ( $soguk['js_url'] as $ju )  { $js_ham  += isset( $ham[ $ju ] ) ? (int) $ham[ $ju ] : 0; }

			/* GA4 (gtag.js) ayri gosterilir: bilerek duruyor, "sil" onerisi
			   uretmesin diye kendi toplamimizdan dusuluyor. 28 Eylul 2026. */
			$ga4_bayt = 0; $ga4_adet = 0;
			foreach ( $soguk['js_url'] as $ju ) {
				if ( false === stripos( $ju, 'googletagmanager.com' ) && false === stripos( $ju, 'google-analytics.com' ) ) { continue; }
				$ga4_bayt += isset( $tekil[ $ju ] ) ? (int) $tekil[ $ju ] : 0;
				$ga4_adet++;
			}
			$js_bayt_net = max( 0, $js_bayt - $ga4_bayt );

			$ev = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			foreach ( $soguk['css_url'] as $cu ) {
				if ( isset( $v['css_gorulen'][ $cu ] ) ) { continue; }
				$v['css_gorulen'][ $cu ] = true;
				$h = wp_parse_url( $cu, PHP_URL_HOST );
				if ( $h && $h !== $ev ) { continue; }
				$r = wp_remote_get( $cu, array( 'timeout' => 20, 'sslverify' => false ) );
				if ( ! is_wp_error( $r ) ) {
					$dosya_siniflar = gbc_hz_css_siniflar( (string) wp_remote_retrieve_body( $r ) );
					$v['css_sinif'] = array_values( array_unique( array_merge(
						$v['css_sinif'], $dosya_siniflar
					) ) );
					/* Dosya bazında da sakla: hangi CSS dosyasında kaç ölü
					   kural var, tek tek görülebilsin. Şablon CSS'i zaten
					   şablon başına yükleniyor; toplam yüzde bu yüzden
					   yanıltıcıydı. */
					$v['css_dosya'][ $cu ] = $dosya_siniflar;
				}
			}

			$v['sayfa'][ $a['ad'] ] = array(
				'url'        => $a['url'],
				'kod'        => $soguk['kod'],
				'soguk_ms'   => $soguk['ms'],
				'sicak_ms'   => isset( $sicak['ms'] ) ? $sicak['ms'] : 0,
				'lscache'    => isset( $sicak['lscache'] ) ? $sicak['lscache'] : '',
				'html'       => $soguk['html'],
				'html_ham'   => isset( $soguk['html_ham'] ) ? $soguk['html_ham'] : 0,
				'css_adet'   => $soguk['css_adet'],
				'js_adet'    => $soguk['js_adet'],
				'resim_adet' => $soguk['resim_adet'],
				'css_bayt'   => $css_bayt,   /* indirilen (sikistirilmis) */
				'js_bayt'    => $js_bayt,
				'css_ham'    => $css_ham,    /* acilmis */
				'js_ham'     => $js_ham,
				'ga4_bayt'   => $ga4_bayt,   /* gtag.js — ayri gosterilir */
				'ga4_adet'   => $ga4_adet,
				'js_net'     => $js_bayt_net,/* GA4 haric bizim JS */
				'satir_js'   => $soguk['satir_js'],
				'satir_css'  => $soguk['satir_css'],
				'toplam'     => $soguk['html'] + $css_bayt + $js_bayt,
			);

			$r = wp_remote_get( $a['url'], array( 'timeout' => 20, 'sslverify' => false ) );
			if ( ! is_wp_error( $r ) ) {
				$v['kullanilan'] = array_values( array_unique( array_merge(
					$v['kullanilan'], gbc_hz_html_siniflar( (string) wp_remote_retrieve_body( $r ) )
				) ) );
			}
		}
		$etiket = sprintf( __( '%s ölçüldü', 'gbc-core' ), $a['ad'] );

	} elseif ( 'psi' === $a['tur'] ) {
		$anahtar = ( 'mobile' === $a['strateji'] ) ? 'mobil' : 'masaustu';
		$v['psi'][ $a['ad'] ][ $anahtar ] = gbc_hz_psi( $a['url'], $a['strateji'] );
		$etiket = sprintf( __( 'PageSpeed · %1$s · %2$s', 'gbc-core' ), $a['ad'],
			'mobile' === $a['strateji'] ? __( 'mobil', 'gbc-core' ) : __( 'masaüstü', 'gbc-core' ) );

	} else {
		$etiket = __( 'Ayarlar okundu', 'gbc-core' );
	}

	$is['veri'] = $v;
	$is['i']    = $i + 1;

	if ( $is['i'] >= $toplam ) {
		gbc_hz_is_bitir( $is );
		delete_option( GBC_HZ_IS );
		return array( 'bitti' => true, 'etiket' => $etiket, 'kalan' => 0, 'toplam' => $toplam );
	}

	update_option( GBC_HZ_IS, $is, false );
	return array( 'bitti' => false, 'etiket' => $etiket, 'kalan' => $toplam - $is['i'], 'toplam' => $toplam, 'sira' => $is['i'] );
}

/** Toplanan veriyi sonuca cevirir ve kaydeder. */
function gbc_hz_is_bitir( $is ) {
	$v = $is['veri'];

	$tekil     = isset( $v['tekil'] ) ? (array) $v['tekil'] : array();
	$tekil_ham = isset( $v['tekil_ham'] ) ? (array) $v['tekil_ham'] : array();
	arsort( $tekil );
	$ev   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$agir = array();
	foreach ( array_slice( $tekil, 0, 12, true ) as $u => $b ) {
		$h   = (string) wp_parse_url( $u, PHP_URL_HOST );
		$yol = (string) wp_parse_url( $u, PHP_URL_PATH );
		$agir[] = array(
			'dosya' => basename( $yol ),
			'yol'   => $yol,
			'host'  => $h,
			'dis'   => ( $h && $h !== $ev ),
			'url'   => $u,
			'bayt'  => $b,
			'ham'   => isset( $tekil_ham[ $u ] ) ? (int) $tekil_ham[ $u ] : 0,
		);
	}

	$tanimli  = isset( $v['css_sinif'] ) ? (array) $v['css_sinif'] : array();
	/* Sınıf kimin? Bizim şablonlarımız mı, tema/çekirdek mi (Halil, 28 Eylül 2026).
	   LiteSpeed bütün CSS'i tek dosyada birleştirdiği için dosya adından
	   ayırmak mümkün değil; ayrım sınıf adının önekinden yapılıyor. */
	$kullanim = isset( $v['kullanilan'] ) ? array_flip( (array) $v['kullanilan'] ) : array();
	$olu_css  = null;
	if ( $tanimli ) {
		$olu    = array();
		$say    = 0;
		$grup   = array(
			'bizim' => array( 'toplam' => 0, 'olu' => 0, 'ornek' => array() ),
			'tema'  => array( 'toplam' => 0, 'olu' => 0, 'ornek' => array() ),
		);
		foreach ( $tanimli as $c ) {
			$g = gbc_hz_sinif_kaynak( $c );
			$grup[ $g ]['toplam']++;
			if ( isset( $kullanim[ $c ] ) ) {
				$say++;
			} else {
				$grup[ $g ]['olu']++;
				if ( count( $grup[ $g ]['ornek'] ) < 20 ) { $grup[ $g ]['ornek'][] = $c; }
				if ( count( $olu ) < 40 ) { $olu[] = $c; }
			}
		}
		$t = count( $tanimli );
		foreach ( array( 'bizim', 'tema' ) as $g ) {
			$grup[ $g ]['yuzde'] = $grup[ $g ]['toplam']
				? (int) round( $grup[ $g ]['olu'] / $grup[ $g ]['toplam'] * 100 ) : 0;
		}
		$olu_css = array(
			'toplam'     => $t,
			'kullanilan' => $say,
			'olu'        => $t - $say,
			'yuzde'      => $t ? (int) round( ( $t - $say ) / $t * 100 ) : 0,
			'ornekler'   => $olu,
			'grup'       => $grup,
		);
	}

	/* Dosya dosya ölü CSS — hangi dosyayı temizlemeye değer. */
	$olu_dosya = array();
	foreach ( (array) ( isset( $v['css_dosya'] ) ? $v['css_dosya'] : array() ) as $durl => $siniflar ) {
		$siniflar = array_values( array_unique( (array) $siniflar ) );
		if ( ! $siniflar ) { continue; }
		$k = 0; $ornek = array();
		foreach ( $siniflar as $c ) {
			if ( isset( $kullanim[ $c ] ) ) { $k++; }
			elseif ( count( $ornek ) < 12 ) { $ornek[] = $c; }
		}
		$t = count( $siniflar );
		$biz = 0; $tem = 0;
		foreach ( $siniflar as $c ) {
			if ( 'bizim' === gbc_hz_sinif_kaynak( $c ) ) { $biz++; } else { $tem++; }
		}
		$olu_dosya[] = array(
			'url'        => $durl,
			'bizim'      => $biz,
			'tema'       => $tem,
			'dosya'      => basename( (string) wp_parse_url( $durl, PHP_URL_PATH ) ),
			'toplam'     => $t,
			'kullanilan' => $k,
			'olu'        => $t - $k,
			'yuzde'      => $t ? (int) round( ( $t - $k ) / $t * 100 ) : 0,
			'bayt'       => isset( $tekil[ $durl ] ) ? (int) $tekil[ $durl ] : 0,
			'ornekler'   => $ornek,
		);
	}
	usort( $olu_dosya, static function ( $a, $b ) { return $b['olu'] - $a['olu']; } );

	$sonuc = array(
		'zaman'       => time(),
		'sayfa'       => isset( $v['sayfa'] ) ? $v['sayfa'] : array(),
		'varlik'      => $agir,
		'olu_css'     => $olu_css,
		'olu_dosya'   => $olu_dosya,
		'litespeed'   => gbc_hz_litespeed(),
		'saglik'      => gbc_hz_site_sagligi(),
		'psi_istendi' => ! empty( $v['psi_istendi'] ),
		'olcum_ms'    => (int) ( ( time() - (int) $is['basladi'] ) * 1000 ),
	);
	if ( ! empty( $v['psi'] ) ) { $sonuc['psi'] = $v['psi']; }

	update_option( GBC_HZ_SON, $sonuc, false );
	return $sonuc;
}

/** Tek seferde tamami — cron icin. Tarayici beklemedigi icin bolmeye gerek yok. */
function gbc_hz_tara( $psi_de = false ) {
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 900 ); }
	ignore_user_abort( true );
	gbc_hz_is_baslat( $psi_de );
	$guvenlik = 0;
	do {
		$d = gbc_hz_is_adim();
		$guvenlik++;
	} while ( empty( $d['bitti'] ) && $guvenlik < 60 );
	return get_option( GBC_HZ_SON, array() );
}

/* ============================================================
   ONERILER — olculen degerlerden uretilir, hazir liste degil
   ============================================================ */
function gbc_hz_oneriler( $s ) {
	$o = array();
	$ek = static function ( &$o, $onem, $baslik, $neden, $nerede ) {
		$o[] = array( 'onem' => $onem, 'baslik' => $baslik, 'neden' => $neden, 'nerede' => $nerede );
	};

	/* 1) Sunucu cevap suresi */
	$soguk = array();
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( ! empty( $p['soguk_ms'] ) ) { $soguk[ $ad ] = (int) $p['soguk_ms']; }
	}
	if ( $soguk ) {
		$ort = (int) round( array_sum( $soguk ) / count( $soguk ) );
		if ( $ort > 800 ) {
			arsort( $soguk );
			$en = key( $soguk );
			$ek( $o, 'yuksek',
				sprintf( __( 'Önbelleksiz sunucu cevabı ortalama %d ms', 'gbc-core' ), $ort ),
				sprintf( __( 'En yavaş: %1$s (%2$d ms). Google botu ve ilk ziyaretçi bu süreyi bekliyor. 500 ms altı hedeflenmeli.', 'gbc-core' ), $en, reset( $soguk ) ),
				__( 'LiteSpeed → Cache → Crawler açılmalı: önbelleği kendi doldurur, ilk ziyaretçi beklemez. Nesne önbelleği (Redis) de bu süreyi düşürür.', 'gbc-core' ) );
		}
	}

	/* 1b) ÖLÇÜLEMEYEN ŞABLON — 29 Eylül 2026'da eklendi.
	 *
	 * OLAN: Hız tablosunda "Gezi rehberi — Too many redirects" yazıyordu.
	 * O şablon hiç ölçülemiyor, dolayısıyla HTML/CSS/JS eşiklerinin
	 * hiçbirine girmiyor. Ama bu durum YALNIZCA ekranda kırmızı bir
	 * hücre olarak duruyordu; sorun defterine düşmüyor, günlük kontrolde
	 * görünmüyordu. Yani şablonun tamamı sessizce denetim dışı kalmıştı.
	 *
	 * Ölçülemeyen şablon, "sorunu yok" demek değil — "bilmiyoruz" demek.
	 * Artık açıkça sorun sayılıyor. */
	$olcum_hata = array();
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( ! empty( $p['hata'] ) ) {
			$olcum_hata[ $ad ] = (string) $p['hata'];
		}
	}
	if ( $olcum_hata ) {
		$ilk = key( $olcum_hata );
		$ek( $o, 'yuksek',
			sprintf(
				/* translators: %s: sablon adlari */
				__( 'Ölçülemeyen şablon: %s', 'gbc-core' ),
				implode( ', ', array_keys( $olcum_hata ) )
			),
			sprintf(
				/* translators: 1: sablon adi, 2: hata metni */
				__( 'Bu şablon indirilemediği için HTML, CSS ve JS eşiklerinin hiçbirine girmiyor — yani sorunu yok değil, bilinmiyor. Sunucunun kendi isteğinde oluşan hata: %1$s → %2$s', 'gbc-core' ),
				$ilk, $olcum_hata[ $ilk ]
			),
			__( 'Adresi tarayıcıda açılıyorsa sorun sunucunun kendi kendine yaptığı istekte demektir: yönlendirme kuralları, www/https zorlaması ya da güvenlik eklentisinin sunucu isteğini engellemesi bakılmalı.', 'gbc-core' ) );
	}

	/* 2) CSS agirligi */
	$css_max = 0; $css_sayfa = '';
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( ! empty( $p['css_bayt'] ) && $p['css_bayt'] > $css_max ) { $css_max = $p['css_bayt']; $css_sayfa = $ad; }
	}
	/* Eşikler (Halil, 28 Eylül 2026): 60 KB altı sorun değil,
	   60-80 KB sarı (orta), 80 KB üstü kırmızı (yüksek). Sıkıştırılmış. */
	if ( $css_max > 60000 ) {
		$ek( $o, ( $css_max > 80000 ? 'yuksek' : 'orta' ),
			sprintf( __( 'CSS %s indiriliyor', 'gbc-core' ), size_format( $css_max ) ),
			sprintf( __( 'En ağır sayfa: %1$s. Ölçülen boyut sıkıştırılmış, yani ziyaretçinin gerçekten indirdiği bayt. Eşik: 60 KB üstü izlenir, 80 KB üstü acil. %2$s', 'gbc-core' ),
				$css_sayfa,
				$css_max > 80000 ? __( 'Şu an acil aralıkta.', 'gbc-core' ) : __( 'Şu an izleme aralığında.', 'gbc-core' ) ),
			__( 'Aşağıdaki “dosya dosya” tablosunda en çok ölü kuralı olan dosyadan başla. (UCSS bilerek kapalı — açılınca ana sayfa sekmeleri, mobil kart çizgisi ve SVG bozuluyor.)', 'gbc-core' ) );
	}

	/* 3) Olu CSS */
	if ( ! empty( $s['olu_css']['yuzde'] ) && $s['olu_css']['yuzde'] >= 40 ) {
		$ek( $o, 'yuksek',
			sprintf( __( 'CSS sınıflarının %%%d\'i hiçbir sayfada geçmiyor', 'gbc-core' ), (int) $s['olu_css']['yuzde'] ),
			sprintf( __( '%1$d sınıftan %2$d tanesi ölçülen sayfaların hiçbirinde kullanılmıyor. Her ziyaretçi bunları da indiriyor.', 'gbc-core' ),
				(int) $s['olu_css']['toplam'], (int) $s['olu_css']['olu'] ),
			__( 'UCSS bilerek kapalı (tasarımı bozuyordu). Çözüm: şablon CSS’ini şablon başına ayırmak ve kullanılmayan kuralları sayfa sayfa test ederek silmek.', 'gbc-core' ) );
	}

	/* 4) JS agirligi ve dosya sayisi */
	$js_max = 0; $js_adet = 0; $js_sayfa = ''; $js_ga4 = 0; $js_net = 0;
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		$net = isset( $p['js_net'] ) ? (int) $p['js_net'] : (int) ( isset( $p['js_bayt'] ) ? $p['js_bayt'] : 0 );
		if ( $net > $js_net ) {
			$js_net  = $net;
			$js_max  = (int) ( isset( $p['js_bayt'] ) ? $p['js_bayt'] : 0 );
			$js_ga4  = isset( $p['ga4_bayt'] ) ? (int) $p['ga4_bayt'] : 0;
			$js_adet = (int) $p['js_adet'];
			$js_sayfa = $ad;
		}
	}

	/* "Load JS Deferred" zaten açıksa o öneriyi hiç yazma. */
	$defer_acik = false;
	foreach ( (array) ( isset( $s['litespeed']['kontroller'] ) ? $s['litespeed']['kontroller'] : array() ) as $k ) {
		if ( 'optm-js_defer' === $k['anahtar'] ) { $defer_acik = ( '1' === (string) $k['deger'] ); break; }
	}
	if ( $js_net > 130000 ) {
		$ek( $o, 'yuksek',
			sprintf( __( 'JavaScript %1$s indiriliyor, %2$d dosya', 'gbc-core' ), size_format( $js_net ), $js_adet ),
			sprintf( __( 'En ağır sayfa: %1$s. Bu rakam GA4 HARİÇ — gtag.js ayrıca %2$s ve bilerek duruyor, toplam %3$s. JS indirmek değil ÇALIŞTIRMAK pahalı; mobil cihazda doğrudan gecikme olur.', 'gbc-core' ),
				$js_sayfa, size_format( $js_ga4 ), size_format( $js_max ) ),
			$defer_acik
				? __( '“Load JS Deferred” zaten açık. Sıradaki iş: kullanılmayan eklentilerin JS’ini tek tek ayıklamak ve kendi betiklerimizi dosyaya almak.', 'gbc-core' )
				: __( 'LiteSpeed → Page Optimization → JS Settings → “Load JS Deferred” aç. Sonra kullanılmayan eklentilerin JS’i tek tek ayıklanmalı.', 'gbc-core' ) );
	}

	/* 5) HTML boyutu — sıkıştırılmış ölçülür (Halil, 28 Eylül 2026).
	      150 KB üstü sarı, 250 KB üstü kırmızı. */
	$html_max = 0; $html_sayfa = ''; $html_ham = 0;
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( ! empty( $p['html'] ) && $p['html'] > $html_max ) {
			$html_max   = (int) $p['html'];
			$html_ham   = isset( $p['html_ham'] ) ? (int) $p['html_ham'] : 0;
			$html_sayfa = $ad;
		}
	}
	if ( $html_max > 150000 ) {
		$ek( $o, ( $html_max > 250000 ? 'yuksek' : 'orta' ),
			sprintf( __( '%1$s sayfasının HTML’i %2$s indiriliyor', 'gbc-core' ), $html_sayfa, size_format( $html_max ) ),
			sprintf( __( 'Sıkıştırılmış boyut — ziyaretçinin gerçekten indirdiği bayt%1$s. Büyük HTML ayrıştırma süresini uzatır; sebebi genelde satır içi betikler, JSON-LD şeması ve şablonun ürettiği gizli bölümlerdir.', 'gbc-core' ),
				$html_ham ? sprintf( __( ' (açılmış %s)', 'gbc-core' ), size_format( $html_ham ) ) : '' ),
			__( 'Satır içi JS’i dosyaya al; şema düğümlerini sınırla; ekranda görünmeyen bölümleri tıklanınca yükle.', 'gbc-core' ) );
	}

	/* 6) Onbellek gercekten calisiyor mu */
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( isset( $p['lscache'] ) && '' === trim( (string) $p['lscache'] ) ) {
			$ek( $o, 'yuksek',
				sprintf( __( '%s sayfasında LiteSpeed önbellek başlığı yok', 'gbc-core' ), $ad ),
				__( 'Sayfa hiç önbelleğe girmiyor olabilir. O zaman her ziyaretçi için PHP baştan çalışır.', 'gbc-core' ),
				__( 'LiteSpeed → Cache → Excludes listesini kontrol et; bu sayfa yanlışlıkla dışlanmış olabilir.', 'gbc-core' ) );
			break;
		}
	}

	/* 7) LiteSpeed ayarlari */
	if ( ! empty( $s['litespeed']['var'] ) ) {
		foreach ( (array) $s['litespeed']['kontroller'] as $k ) {
			if ( ! empty( $k['bilerek'] ) ) { continue; }   /* bilerek kapalı — sorun değil */
			if ( null === $k['iyi'] ) { continue; }
			if ( (string) $k['deger'] === (string) $k['iyi'] ) { continue; }
			$ek( $o, $k['onem'],
				sprintf( __( 'LiteSpeed: %s kapalı', 'gbc-core' ), $k['ad'] ),
				$k['not'],
				__( 'LiteSpeed Cache eklentisinin ayarlarından açılır.', 'gbc-core' ) );
		}
	}

	/* 8) Site sagligi kritikleri */
	foreach ( (array) $s['saglik'] as $t ) {
		if ( 'critical' === $t['durum'] ) {
			$ek( $o, 'yuksek', __( 'Site Sağlığı: ', 'gbc-core' ) . $t['baslik'],
				mb_substr( $t['aciklama'], 0, 220 ), __( 'Araçlar → Site Sağlığı', 'gbc-core' ) );
		}
	}

	$sira = array( 'yuksek' => 0, 'orta' => 1, 'dusuk' => 2 );
	usort( $o, static function ( $a, $b ) use ( $sira ) {
		return ( $sira[ $a['onem'] ] ?? 3 ) <=> ( $sira[ $b['onem'] ] ?? 3 );
	} );
	return $o;
}

/* ============================================================
   EKRAN
   ============================================================ */
function gbc_hz_kart( $baslik, $deger, $alt = '', $renk = '#14181F' ) {
	return '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:14px;padding:18px 22px;min-width:150px;flex:1">'
		. '<div style="font-size:12.5px;font-weight:600;color:#5C6470">' . esc_html( $baslik ) . '</div>'
		. '<div style="font-size:30px;font-weight:700;line-height:1.2;color:' . esc_attr( $renk ) . '">' . esc_html( $deger ) . '</div>'
		. ( $alt ? '<div style="font-size:12.5px;color:#5C6470;margin-top:2px">' . esc_html( $alt ) . '</div>' : '' )
		. '</div>';
}

/**
 * Adim adim olcumun ara ekrani. Her adimda sayfa kendini yeniler; tarayici
 * hicbir zaman uzun bir istek beklemez, sunucu da istegi kesmez.
 */
function gbc_hz_ilerleme_ekrani( $d ) {
	$sonraki = wp_nonce_url( admin_url( 'admin.php?page=gbc-hiz&gbc_hz_adim=1' ), 'gbc_hz' );
	$toplam  = max( 1, (int) $d['toplam'] );
	$biten   = isset( $d['sira'] ) ? (int) $d['sira'] : 0;
	$yuzde   = (int) round( $biten / $toplam * 100 );

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Hız & Sağlık', 'gbc-core' ) . '</h1>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:22px;max-width:700px">';
	echo '<p style="margin-top:0"><strong>' . esc_html__( 'Ölçülüyor…', 'gbc-core' ) . '</strong> '
		. esc_html( $d['etiket'] ) . '</p>';
	echo '<div style="background:#EEF0F3;border-radius:999px;height:12px;overflow:hidden">'
		. '<div style="background:#1A7F37;height:12px;width:' . (int) $yuzde . '%"></div></div>';
	echo '<p style="color:#5C6470;font-size:13px">'
		. esc_html( sprintf( __( '%1$d / %2$d adım. Bu sayfa kendi kendine ilerliyor; kapatmadan bekle.', 'gbc-core' ), $biten, $toplam ) )
		. '</p>';
	echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=gbc-hiz' ) ) . '">'
		. esc_html__( 'Durdur ve son sonuca dön', 'gbc-core' ) . '</a></p>';
	echo '</div></div>';

	/* Meta yenileme: JavaScript kapali olsa bile calisir. */
	echo '<meta http-equiv="refresh" content="1;url=' . esc_url( $sonraki ) . '">';
}

function gbc_hz_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_POST['gbc_hz_kaydet'] ) && check_admin_referer( 'gbc_hz' ) ) {
		$yeni = array(
			'psi_anahtar' => sanitize_text_field( wp_unslash( $_POST['gbc_hz_psi'] ?? '' ) ),
			'siklik'      => 'weekly',
		);
		update_option( GBC_HZ_AYAR, $yeni, false );
		/* Agdaki diger siteler de ayni anahtari kullansin. */
		if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
			update_site_option( GBC_HZ_AYAR, $yeni );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kaydedildi.', 'gbc-core' ) . '</p></div>';
	}

	if ( isset( $_POST['gbc_hz_duzelt'] ) && check_admin_referer( 'gbc_hz' ) ) {
		$sec = isset( $_POST['ayar'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['ayar'] ) ) : array();
		$r   = gbc_hz_uygula( $sec );
		if ( $r['oldu'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Açıldı:', 'gbc-core' ) . '</strong> '
				. esc_html( implode( ', ', $r['oldu'] ) ) . '. '
				. esc_html__( 'LiteSpeed önbelleği temizlendi; yeni ayar bir sonraki ziyaretten itibaren geçerli.', 'gbc-core' ) . '</p></div>';
		}
		if ( $r['olmadi'] ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Yazılamadı:', 'gbc-core' ) . '</strong> '
				. esc_html( implode( ', ', $r['olmadi'] ) ) . ' — '
				. esc_html__( 'bu ayarları LiteSpeed panelinden elle açmak gerekiyor.', 'gbc-core' ) . '</p></div>';
		}
	}

	if ( isset( $_POST['gbc_hz_mcp'] ) && check_admin_referer( 'gbc_hz' ) ) {
		$n = gbc_hz_mcp_haric_ekle();
		echo '<div class="notice notice-' . ( $n ? 'success' : 'error' ) . '"><p>'
			. esc_html( $n
				? sprintf( __( 'MCP ve OAuth adresleri önbellek dışına alındı (%d yol).', 'gbc-core' ), $n )
				: __( 'Önbellek dışı listesi yazılamadı; LiteSpeed → Cache → Excludes bölümünden elle eklenmeli.', 'gbc-core' ) )
			. '</p></div>';
	}

	if ( isset( $_GET['gbc_hz_psitest'] ) && check_admin_referer( 'gbc_hz' ) ) {
		$t = gbc_hz_psi_test();
		if ( ! empty( $t['hata'] ) ) {
			$aciklama = 'anahtar-yok' === $t['hata']
				? __( 'Anahtar kayıtlı değil. Aşağıdaki kutuya yapıştırıp Kaydet deyin.', 'gbc-core' )
				: ( 'HTTP 400' === $t['hata'] ? __( 'Anahtar geçersiz ya da PageSpeed Insights API bu anahtarda açık değil.', 'gbc-core' )
				: ( 'HTTP 403' === $t['hata'] ? __( 'Anahtar reddedildi: API etkin değil ya da anahtar kısıtlı.', 'gbc-core' )
				: ( 'HTTP 429' === $t['hata'] ? __( 'Kota doldu, birkaç dakika sonra tekrar deneyin.', 'gbc-core' )
				: __( 'Google cevap vermedi.', 'gbc-core' ) ) ) );
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'PageSpeed bağlanamadı:', 'gbc-core' ) . '</strong> '
				. esc_html( $t['hata'] ) . ' — ' . esc_html( $aciklama ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'PageSpeed çalışıyor.', 'gbc-core' ) . '</strong> '
				. esc_html( sprintf( __( 'Ana sayfa mobil puanı %1$d · LCP %2$s · cevap %3$d sn.', 'gbc-core' ),
					(int) $t['puan'], (string) $t['LCP'], (int) round( $t['sure_ms'] / 1000 ) ) )
				. '</p></div>';
		}
	}

	/* Olcum artik tek istekte degil, adim adim. Beyaz ekranin sebebi buydu. */
	if ( isset( $_GET['gbc_hz_tara'] ) && check_admin_referer( 'gbc_hz' ) ) {
		gbc_hz_is_baslat( isset( $_GET['psi'] ) );
		gbc_hz_ilerleme_ekrani( array( 'bitti' => false, 'etiket' => __( 'Başlıyor', 'gbc-core' ), 'kalan' => 0, 'toplam' => 0, 'sira' => 0 ) );
		return;
	}

	if ( isset( $_GET['gbc_hz_adim'] ) && check_admin_referer( 'gbc_hz' ) ) {
		$d = gbc_hz_is_adim();
		if ( empty( $d['bitti'] ) ) {
			gbc_hz_ilerleme_ekrani( $d );
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ölçüm tamamlandı.', 'gbc-core' ) . '</p></div>';
	}

	$s = get_option( GBC_HZ_SON, array() );
	$u = static function ( $ek = '' ) {
		return wp_nonce_url( admin_url( 'admin.php?page=gbc-hiz&gbc_hz_tara=1' . $ek ), 'gbc_hz' );
	};

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Hız & Sağlık', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-hiz' ); }
	echo '<p style="max-width:920px;color:#444">'
		. esc_html__( 'Her şablondan bir örnek sayfa gerçekten indirilir, önbellekli ve önbelleksiz ölçülür. Ölçüm yalnız yönetici tarafında çalışır; sitenin hızına etkisi sıfırdır.', 'gbc-core' )
		. '</p>';

	echo '<p><a class="button button-primary" href="' . esc_url( $u() ) . '">' . esc_html__( 'Ölç (~40 sn)', 'gbc-core' ) . '</a> ';
	if ( '' !== gbc_hz_ayar( 'psi_anahtar' ) ) {
		echo '<a class="button" href="' . esc_url( $u( '&psi=1' ) ) . '">' . esc_html__( 'Ölç + PageSpeed (~3 dk)', 'gbc-core' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gbc-hiz&gbc_hz_psitest=1' ), 'gbc_hz' ) ) . '">'
			. esc_html__( 'PageSpeed bağlantı testi (~10 sn)', 'gbc-core' ) . '</a>';
	} else {
		echo '<span style="color:#8A6100">' . esc_html__( 'PageSpeed anahtarı girilmedi — aşağıdaki kutuya yapıştırın.', 'gbc-core' ) . '</span>';
	}
	echo '</p>';
	echo '<p style="color:#5C6470;margin-top:-6px;font-size:13px">'
		. esc_html__( 'Butona bastıktan sonra sayfa ölçüm bitene kadar yüklenmeye devam eder. Sekmeyi kapatmayın; PageSpeed ölçümü Google tarafında yapıldığı için 2-3 dakika sürebilir.', 'gbc-core' )
		. '</p>';

	$t = get_option( GBC_HZ_PSITEST, array() );
	if ( ! empty( $t['zaman'] ) ) {
		$iyi = empty( $t['hata'] );
		echo '<p style="font-size:13px;color:' . ( $iyi ? '#1A7F37' : '#B32D2E' ) . '">'
			. esc_html( sprintf(
				$iyi ? __( 'Son PageSpeed testi: çalışıyor, mobil %1$d puan (%2$s).', 'gbc-core' )
				     : __( 'Son PageSpeed testi: başarısız — %1$s (%2$s).', 'gbc-core' ),
				$iyi ? (int) $t['puan'] : (string) $t['hata'],
				wp_date( 'j F H:i', (int) $t['zaman'] )
			) ) . '</p>';
	}

	if ( empty( $s['zaman'] ) ) {
		echo '<p><em>' . esc_html__( 'Henüz ölçüm yapılmadı.', 'gbc-core' ) . '</em></p>';
		gbc_hz_ayar_formu();
		echo '</div>';
		return;
	}

	/* --- Ozet --- */
	$soguk = array(); $sicak = array(); $agirlik = array();
	foreach ( (array) $s['sayfa'] as $p ) {
		if ( ! empty( $p['soguk_ms'] ) ) { $soguk[] = (int) $p['soguk_ms']; }
		if ( ! empty( $p['sicak_ms'] ) ) { $sicak[] = (int) $p['sicak_ms']; }
		if ( ! empty( $p['toplam'] ) )   { $agirlik[] = (int) $p['toplam']; }
	}
	$o_soguk = $soguk ? (int) round( array_sum( $soguk ) / count( $soguk ) ) : 0;
	$o_sicak = $sicak ? (int) round( array_sum( $sicak ) / count( $sicak ) ) : 0;
	$o_agir  = $agirlik ? (int) round( array_sum( $agirlik ) / count( $agirlik ) ) : 0;

	$oneri = gbc_hz_oneriler( $s );
	$acil  = count( array_filter( $oneri, static function ( $x ) { return 'yuksek' === $x['onem']; } ) );

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_hz_kart( __( 'Önbellekli cevap', 'gbc-core' ), $o_sicak . ' ms',
		__( 'Gerçek ziyaretçinin gördüğü', 'gbc-core' ), $o_sicak < 400 ? '#1A7F37' : '#8A6100' );
	echo gbc_hz_kart( __( 'Önbelleksiz cevap', 'gbc-core' ), $o_soguk . ' ms',
		__( 'Bot ve ilk ziyaretçi', 'gbc-core' ), $o_soguk < 800 ? '#1A7F37' : '#B32D2E' );
	echo gbc_hz_kart( __( 'Ortalama sayfa ağırlığı', 'gbc-core' ), size_format( $o_agir ),
		__( 'HTML + CSS + JS', 'gbc-core' ), $o_agir < 500000 ? '#1A7F37' : '#B32D2E' );
	if ( ! empty( $s['olu_css'] ) ) {
		echo gbc_hz_kart( __( 'Kullanılmayan CSS', 'gbc-core' ), '%' . (int) $s['olu_css']['yuzde'],
			(int) $s['olu_css']['olu'] . ' / ' . (int) $s['olu_css']['toplam'] . __( ' sınıf', 'gbc-core' ),
			$s['olu_css']['yuzde'] < 30 ? '#1A7F37' : '#B32D2E' );
	}
	echo gbc_hz_kart( __( 'Acil iş', 'gbc-core' ), (string) $acil, __( 'yüksek öncelikli', 'gbc-core' ),
		$acil ? '#B32D2E' : '#1A7F37' );
	echo '</div>';

	echo '<p style="color:#666">' . esc_html( sprintf( __( 'Son ölçüm: %s', 'gbc-core' ), date_i18n( 'j F Y, H:i', (int) $s['zaman'] ) ) ) . '</p>';

	/* --- Oneriler --- */
	if ( $oneri ) {
		echo '<h2 style="margin-top:26px">' . esc_html__( 'Yapılacaklar', 'gbc-core' ) . ' <span style="font-weight:400;font-size:14px;color:#8A919C">'
			. esc_html__( '— etkiye göre sıralı', 'gbc-core' ) . '</span></h2>';
		echo '<div style="max-width:1050px">';
		$renkler = array( 'yuksek' => array( '#9C2A2B', '#FBE7E7' ), 'orta' => array( '#8A6100', '#FCF3E1' ), 'dusuk' => array( '#5C6470', '#EEF0F3' ) );
		$n = 0;
		foreach ( $oneri as $x ) {
			$n++;
			$rk = $renkler[ $x['onem'] ] ?? $renkler['dusuk'];
			echo '<div style="background:#fff;border:1px solid #E3E5E9;border-left:4px solid ' . esc_attr( $rk[0] ) . ';border-radius:10px;padding:15px 18px;margin-bottom:10px">'
				. '<div style="display:flex;gap:12px;align-items:baseline">'
				. '<span style="background:' . esc_attr( $rk[1] ) . ';color:' . esc_attr( $rk[0] ) . ';font-weight:700;font-size:12px;padding:2px 9px;border-radius:12px">' . (int) $n . '</span>'
				. '<strong style="font-size:15.5px">' . esc_html( $x['baslik'] ) . '</strong></div>'
				. '<div style="margin:6px 0 0 34px;color:#3C434A;font-size:14px;line-height:1.55">' . esc_html( $x['neden'] ) . '</div>'
				. '<div style="margin:5px 0 0 34px;color:#5C6470;font-size:13.5px"><strong>' . esc_html__( 'Nerede:', 'gbc-core' ) . '</strong> ' . esc_html( $x['nerede'] ) . '</div>'
				. '</div>';
		}
		echo '</div>';
	} else {
		/* Ölçüm hiç yapılamadıysa "her şey yolunda" YAZILMAZ — 28 Eylül 2026.
		   Şablonların hepsi hata verirken panel yeşil yanıyordu. */
		$olculemeyen = 0; $olculen = 0;
		foreach ( (array) $s['sayfa'] as $__p ) {
			if ( ! empty( $__p['hata'] ) ) { $olculemeyen++; } else { $olculen++; }
		}
		if ( $olculemeyen > 0 && 0 === $olculen ) {
			echo '<p style="color:#B32D2E;margin-top:20px"><strong>' . esc_html__( 'Ölçülemedi.', 'gbc-core' ) . '</strong> '
				. esc_html( sprintf( __( '%d şablonun hiçbiri ölçülemedi, bu yüzden bir hüküm verilemiyor. Tablodaki hata metnine bak.', 'gbc-core' ), $olculemeyen ) ) . '</p>';
		} elseif ( $olculemeyen > 0 ) {
			echo '<p style="color:#8A6100;margin-top:20px"><strong>' . esc_html__( 'Kısmen ölçüldü.', 'gbc-core' ) . '</strong> '
				. esc_html( sprintf( __( 'Ölçülen %1$d şablon eşiklerin içinde; %2$d şablon ölçülemedi.', 'gbc-core' ), $olculen, $olculemeyen ) ) . '</p>';
		} else {
			echo '<p style="color:#1A7F37;margin-top:20px"><strong>' . esc_html__( 'Ölçülen her şey eşiklerin içinde.', 'gbc-core' ) . '</strong></p>';
		}
	}

	/* --- KRİTİK CSS TEST TEZGÂHI --- */
	if ( function_exists( 'gbc_ccss_test_adresleri' ) ) {
		$cd = gbc_ccss_durum();
		echo '<h2 style="margin-top:28px">' . esc_html__( 'Kritik CSS testi', 'gbc-core' ) . '</h2>';
		echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:18px 22px;max-width:1000px">';
		echo '<p style="margin-top:0;color:#444;line-height:1.6">'
			. esc_html__( 'Bu tezgâh ayarı YALNIZ adresinde gizli bilet olan isteklerde açar. Veritabanına hiçbir şey yazılmaz; LiteSpeed’in kendi litespeed_conf_force kancası kullanılıyor, resmî belgede “yalnız bu istek için, veritabanına yazılmaz” diye geçiyor. İstek bilerek önbelleklenebilir bırakılıyor: önbelleği kapatmak LiteSpeed’in CSS iyileştirme hattını da kapatıyor ve ölçüm anlamsızlaşıyor (29 Eylül 2026’da ölçüldü: 132 KB / 1 CSS yerine 268 KB / 5 CSS). Ziyaretçiye sızmaz, çünkü önbellek anahtarına sorgu dizesi de giriyor.', 'gbc-core' )
			. '</p>';

		/* Kritik CSS üretilmiş mi? */
		$hazir = $cd['dosya_adet'] > 0;
		echo '<p style="margin:10px 0"><strong>' . esc_html__( 'Kritik CSS durumu:', 'gbc-core' ) . '</strong> ';
		if ( $hazir ) {
			echo '<span style="color:#1A7F37;font-weight:700">'
				. esc_html( sprintf( __( '%d dosya üretilmiş', 'gbc-core' ), (int) $cd['dosya_adet'] ) ) . '</span>';
		} else {
			echo '<span style="color:#8A6100;font-weight:700">' . esc_html__( 'henüz üretilmemiş', 'gbc-core' ) . '</span>';
		}
		if ( $cd['kuyruk'] ) {
			echo ' · <span style="color:#8A6100">' . esc_html( sprintf( __( 'kuyrukta %d sayfa', 'gbc-core' ), (int) $cd['kuyruk'] ) ) . '</span>';
		}
		echo '</p>';
		echo '<p style="font-size:13px;color:#5C6470;line-height:1.6">'
			. esc_html__( 'Kritik CSS hazır değilse tehlike yok: LiteSpeed async’i o istek için kendisi kapatıyor (kaynak kodda “CCSS set to OFF due to CCSS not generated yet”). Sayfa normal yüklenir, stilsiz açılmaz.', 'gbc-core' )
			. '</p>';

		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr>'
			. '<th style="width:130px">' . esc_html__( 'Şablon', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Şimdiki hâli (taban)', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Kritik CSS açıkken', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( gbc_ccss_test_adresleri() as $ad => $u ) {
			echo '<tr><td><strong>' . esc_html( $ad ) . '</strong></td>';
			echo '<td><a href="' . esc_url( $u['taban'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'aç', 'gbc-core' ) . '</a></td>';
			echo '<td><a href="' . esc_url( $u['test'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'aç', 'gbc-core' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p style="font-size:12.5px;color:#5C6470;margin-bottom:0">'
			. esc_html__( 'İki sütunu yan yana açıp karşılaştır. Sağdaki bozuk görünüyorsa ayar AÇILMAZ. Özellikle bak: ana sayfa sekme butonları, mobil kart çizgileri, SVG oklar, mobil menü, İçindekiler kutusu.', 'gbc-core' )
			. '</p>';
		echo '</div>';
	}

	/* --- TEK TIKLA DUZELT --- */
	$eksik = gbc_hz_eksik_ayarlar();
	$mcp   = gbc_hz_mcp_haric_eksik();
	$no    = gbc_hz_nesne_onbellek();

	echo '<h2 style="margin-top:28px">' . esc_html__( 'Tek tıkla düzelt', 'gbc-core' ) . '</h2>';
	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:18px 22px;max-width:1000px">';

	if ( $eksik || $mcp['eksik'] ) {
		echo '<form method="post">';
		wp_nonce_field( 'gbc_hz' );

		if ( $eksik ) {
			echo '<p style="margin-top:0;color:#444">' . esc_html__( 'Aşağıdakiler ölçümde eksik çıktı. İşaretliler LiteSpeed’in kendi ayar kancasıyla açılır, sonra gerçekten yazılıp yazılmadığı okunarak doğrulanır.', 'gbc-core' ) . '</p>';
			foreach ( $eksik as $a => $d ) {
				echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="ayar[]" value="' . esc_attr( $a ) . '" checked> '
					. '<strong>' . esc_html( $d['ad'] ) . '</strong> '
					. '<span style="color:#5C6470">— ' . esc_html( $d['neden'] ) . '</span></label>';
			}
			echo '<p><button type="submit" name="gbc_hz_duzelt" value="1" class="button button-primary">'
				. esc_html__( 'İşaretlileri aç', 'gbc-core' ) . '</button></p>';
		} else {
			echo '<p style="margin-top:0;color:#1A7F37"><strong>' . esc_html__( 'LiteSpeed tarafında açılacak bir şey kalmadı.', 'gbc-core' ) . '</strong></p>';
		}

		if ( $mcp['eksik'] ) {
			echo '<hr style="border:0;border-top:1px solid #E3E5E9;margin:16px 0">';
			echo '<p style="color:#444"><strong>' . esc_html__( 'MCP / OAuth adresleri önbelleğe giriyor.', 'gbc-core' ) . '</strong> '
				. esc_html__( 'Önbelleklenmiş bir kimlik doğrulama cevabı, bağlantının bir kez çalışıp sonra bozulmasına yol açar. Eklenecek yollar:', 'gbc-core' ) . ' <code>'
				. esc_html( implode( '</code> <code>', gbc_hz_mcp_yollari() ) ) . '</code></p>';
			echo '<p><button type="submit" name="gbc_hz_mcp" value="1" class="button">'
				. esc_html__( 'Bu adresleri önbellek dışına al', 'gbc-core' ) . '</button></p>';
		}
		echo '</form>';
	} else {
		echo '<p style="margin:0;color:#1A7F37"><strong>' . esc_html__( 'Otomatik düzeltilecek bir ayar kalmadı.', 'gbc-core' ) . '</strong></p>';
	}

	echo '<hr style="border:0;border-top:1px solid #E3E5E9;margin:16px 0">';
	echo '<p style="color:#444;margin-bottom:6px"><strong>' . esc_html__( 'Nesne önbelleği (Redis)', 'gbc-core' ) . '</strong></p>';
	if ( $no['kullanim'] ) {
		echo '<p style="margin:0;color:#1A7F37">' . esc_html__( 'Çalışıyor: WordPress kalıcı nesne önbelleğini kullanıyor.', 'gbc-core' ) . '</p>';
	} else {
		$satir = array();
		$satir[] = $no['dosya'] ? __( 'object-cache.php kurulu.', 'gbc-core' ) : __( 'object-cache.php yok.', 'gbc-core' );
		$satir[] = sprintf( __( 'PHP eklentisi: Redis %1$s, Memcached %2$s.', 'gbc-core' ),
			$no['redis_php'] ? __( 'var', 'gbc-core' ) : __( 'yok', 'gbc-core' ),
			$no['memc_php'] ? __( 'var', 'gbc-core' ) : __( 'yok', 'gbc-core' ) );
		if ( null !== $no['baglanti'] ) {
			$satir[] = $no['baglanti']
				? sprintf( __( '%s adresine bağlanılıyor.', 'gbc-core' ), $no['host_denenen'] )
				: sprintf( __( '%1$s adresine BAĞLANILAMIYOR (%2$s).', 'gbc-core' ), $no['host_denenen'], (string) $no['baglanti_hata'] );
		}
		echo '<p style="margin:0;color:#B32D2E">' . esc_html( implode( ' ', $satir ) ) . '</p>';
		echo '<p style="margin:6px 0 0;color:#5C6470;font-size:13px">'
			. esc_html__( 'Kurulu ama çalışmayan bir drop-in her sayfada boşuna denenir. Sırayla: Hostinger panelinden Redis’i aç, sonra LiteSpeed → Cache → Object bölümünde sunucu 127.0.0.1, port 6379 olsun. Redis açılamıyorsa wp-content/object-cache.php silinmeli; veritabanına düşmek, bağlanamayan bir önbelleği her istekte denemekten hızlıdır.', 'gbc-core' )
			. '</p>';
	}
	echo '</div>';

	/* --- Sablon tablosu --- */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Şablon bazında', 'gbc-core' ) . '</h2>';
	echo '<p style="color:#5C6470;font-size:13px;max-width:1100px;margin:0 0 8px">'
		. esc_html__( 'Bütün boyutlar SIKIŞTIRILMIŞ — ziyaretçinin gerçekten indirdiği bayt. JS sütunu GA4 hariçtir; gtag.js bilerek duruyor ve altında ayrı yazılıyor.', 'gbc-core' )
		. '</p>';
	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th>' . esc_html__( 'Şablon', 'gbc-core' ) . '</th>'
		. '<th style="width:105px">' . esc_html__( 'Önbellekli', 'gbc-core' ) . '</th>'
		. '<th style="width:115px">' . esc_html__( 'Önbelleksiz', 'gbc-core' ) . '</th>'
		. '<th style="width:105px">HTML</th><th style="width:120px">CSS</th>'
		. '<th style="width:120px" title="' . esc_attr__( 'GA4 hariç; gtag.js ayrı satırda', 'gbc-core' ) . '">JS</th>'
		. '<th style="width:70px">' . esc_html__( 'Görsel', 'gbc-core' ) . '</th>'
		. '<th style="width:100px">' . esc_html__( 'Toplam', 'gbc-core' ) . '</th>'
		. '</tr></thead><tbody>';
	foreach ( (array) $s['sayfa'] as $ad => $p ) {
		if ( isset( $p['hata'] ) ) {
			echo '<tr><td><strong>' . esc_html( $ad ) . '</strong></td><td colspan="7" style="color:#B32D2E">' . esc_html( $p['hata'] ) . '</td></tr>';
			continue;
		}
		$rs = ( (int) $p['sicak_ms'] < 400 ) ? '#1A7F37' : '#8A6100';
		$rc = ( (int) $p['soguk_ms'] < 800 ) ? '#1A7F37' : '#B32D2E';
		echo '<tr>'
			. '<td><strong>' . esc_html( $ad ) . '</strong><br><a href="' . esc_url( $p['url'] ) . '" target="_blank" style="font-size:12px">' . esc_html__( 'aç', 'gbc-core' ) . '</a></td>'
			. '<td style="color:' . esc_attr( $rs ) . ';font-weight:600">' . (int) $p['sicak_ms'] . ' ms</td>'
			. '<td style="color:' . esc_attr( $rc ) . ';font-weight:600">' . (int) $p['soguk_ms'] . ' ms</td>'
			. '<td>' . esc_html( size_format( (int) $p['html'] ) )
				. ( ! empty( $p['html_ham'] ) && (int) $p['html_ham'] > (int) $p['html']
					? '<br><span style="font-size:11.5px;color:#8A919C">açılmış ' . esc_html( size_format( (int) $p['html_ham'] ) ) . '</span>' : '' ) . '</td>'
			. '<td>' . esc_html( size_format( (int) $p['css_bayt'] ) ) . '<br><span style="font-size:11.5px;color:#8A919C">' . (int) $p['css_adet'] . ' dosya'
				. ( ! empty( $p['css_ham'] ) ? ' · açılmış ' . esc_html( size_format( (int) $p['css_ham'] ) ) : '' ) . '</span></td>'
			. '<td>' . esc_html( size_format( isset( $p['js_net'] ) ? (int) $p['js_net'] : (int) $p['js_bayt'] ) )
				. '<br><span style="font-size:11.5px;color:#8A919C">' . (int) $p['js_adet'] . ' dosya'
				. ( ! empty( $p['ga4_bayt'] ) ? '<br>GA4 ayrı: ' . esc_html( size_format( (int) $p['ga4_bayt'] ) ) : '' )
				. '</span></td>'
			. '<td>' . (int) $p['resim_adet'] . '</td>'
			. '<td><strong>' . esc_html( size_format( (int) $p['toplam'] ) ) . '</strong></td>'
			. '</tr>';
	}
	echo '</tbody></table>';

	/* --- Agir dosyalar --- */
	if ( ! empty( $s['varlik'] ) ) {
		echo '<h2 style="margin-top:28px">' . esc_html__( 'En ağır 12 dosya', 'gbc-core' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:110px">' . esc_html__( 'Boyut', 'gbc-core' ) . '</th><th style="width:150px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th><th>' . esc_html__( 'Dosya', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $s['varlik'] as $v ) {
			$dis  = ! empty( $v['dis'] );
			$host = isset( $v['host'] ) ? (string) $v['host'] : '';
			$yol  = isset( $v['yol'] ) ? (string) $v['yol'] : $v['dosya'];
			echo '<tr><td><strong>' . esc_html( size_format( (int) $v['bayt'] ) ) . '</strong>'
				. ( ! empty( $v['ham'] ) && (int) $v['ham'] > (int) $v['bayt']
					? '<div style="font-size:11px;color:#8A919C">' . esc_html( sprintf( __( 'açılmış %s', 'gbc-core' ), size_format( (int) $v['ham'] ) ) ) . '</div>'
					: '' )
				. '</td>'
				. '<td>' . ( $dis
					? '<span style="color:#B32D2E;font-weight:600">' . esc_html__( 'DIŞ', 'gbc-core' ) . '</span> <span style="color:#5C6470">' . esc_html( $host ) . '</span>'
					: '<span style="color:#1A7F37">' . esc_html__( 'kendi sunucun', 'gbc-core' ) . '</span>' ) . '</td>'
				. '<td><a href="' . esc_url( $v['url'] ) . '" target="_blank" style="word-break:break-all">' . esc_html( $yol ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	/* --- Olu CSS --- */
	if ( ! empty( $s['olu_css'] ) ) {
		$oc = $s['olu_css'];
		echo '<h2 style="margin-top:28px">' . esc_html__( 'Kullanılmayan CSS', 'gbc-core' ) . '</h2>';
		echo '<p style="max-width:1000px;color:#444">'
			. esc_html( sprintf( __( 'Birleşik CSS dosyasında %1$d sınıf tanımlı. Ölçülen sayfaların hiçbirinde %2$d tanesi geçmiyor (%%%3$d).', 'gbc-core' ),
				(int) $oc['toplam'], (int) $oc['olu'], (int) $oc['yuzde'] ) )
			. ' <em>' . esc_html__( 'Bu bir tahmindir: JavaScript’in sonradan eklediği sınıfları göremez.', 'gbc-core' ) . '</em></p>';

		/* Bizim sınıflarımız ile tema/çekirdek ayrı satırda. */
		if ( ! empty( $oc['grup'] ) ) {
			$etiket = array(
				'bizim' => array( __( 'Bizim şablonlarımız', 'gbc-core' ), __( 'gbc- · gz- · gz2- · v1- · v11- · tr- · fb- · yn-', 'gbc-core' ), __( 'Temizlenecek yer burası.', 'gbc-core' ) ),
				'tema'  => array( __( 'Tema / çekirdek', 'gbc-core' ), __( 'Astra, WordPress ve eklentiler', 'gbc-core' ), __( 'Bize ait değil; silinmez, UCSS kapalı olduğu için de atılmıyor.', 'gbc-core' ) ),
			);
			echo '<table class="widefat striped" style="max-width:1100px;margin-bottom:14px"><thead><tr>'
				. '<th style="width:210px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th>'
				. '<th style="width:110px">' . esc_html__( 'Sınıf', 'gbc-core' ) . '</th>'
				. '<th style="width:150px">' . esc_html__( 'Kullanılmayan', 'gbc-core' ) . '</th>'
				. '<th>' . esc_html__( 'Not', 'gbc-core' ) . '</th></tr></thead><tbody>';
			foreach ( array( 'bizim', 'tema' ) as $g ) {
				if ( empty( $oc['grup'][ $g ]['toplam'] ) ) { continue; }
				$x = $oc['grup'][ $g ];
				$renk = ( 'bizim' === $g && $x['yuzde'] >= 40 ) ? '#B3261E' : ( 'bizim' === $g ? '#8A6100' : '#5C6470' );
				echo '<tr><td><strong>' . esc_html( $etiket[ $g ][0] ) . '</strong>'
					. '<div style="color:#8A919C;font-size:11px">' . esc_html( $etiket[ $g ][1] ) . '</div></td>'
					. '<td>' . (int) $x['toplam'] . '</td>'
					. '<td style="color:' . esc_attr( $renk ) . ';font-weight:700">' . (int) $x['olu'] . ' · %' . (int) $x['yuzde'] . '</td>'
					. '<td style="font-size:13px;color:#5C6470">' . esc_html( $etiket[ $g ][2] );
				if ( ! empty( $x['ornek'] ) ) {
					echo '<div style="font-size:11px;margin-top:4px">';
					foreach ( array_slice( $x['ornek'], 0, 12 ) as $c ) {
						if ( 'bizim' === $g ) {
							list( $ea, $er, $ez ) = gbc_hz_olu_etiket( gbc_hz_olu_karar( $c ) );
							echo '<span style="display:inline-block;margin:0 6px 4px 0;white-space:nowrap">'
								. '<code>.' . esc_html( $c ) . '</code> '
								. '<span style="background:' . esc_attr( $ez ) . ';color:' . esc_attr( $er )
								. ';border-radius:8px;padding:1px 6px;font-size:10px;font-weight:700">' . esc_html( $ea ) . '</span></span>';
						} else {
							echo '<code style="margin-right:5px">.' . esc_html( $c ) . '</code>';
						}
					}
					echo '</div>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( ! empty( $oc['ornekler'] ) ) {
			/* Bizim sınıflarımız önce: silinecek yer orası. Tema ve WordPress
			   çekirdeği sınıfları bizim silebileceğimiz şeyler değil. */
			$bizimki = array(); $temaki = array();
			foreach ( $oc['ornekler'] as $c ) {
				if ( 'bizim' === gbc_hz_sinif_kaynak( $c ) ) { $bizimki[] = $c; } else { $temaki[] = $c; }
			}

			if ( $bizimki ) {
				$kova = array( 'sil' => array(), 'kosullu' => array(), 'js' => array() );
				foreach ( $bizimki as $c ) { $kova[ gbc_hz_olu_karar( $c ) ][] = $c; }

				echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:16px 20px;max-width:1000px;margin:10px 0">';
				echo '<strong style="font-size:14px">' . esc_html__( 'Bizim ölü sınıflarımız — ne yapmalı', 'gbc-core' ) . '</strong>';
				echo '<p style="font-size:13px;color:#5C6470;margin:6px 0 12px;line-height:1.6">'
					. esc_html__( '“Sayfada geçmiyor” ile “silinebilir” aynı şey değil. Bazı sınıfları JavaScript tıklamada ekliyor; statik HTML’de hiç görünmezler ama silinirse açılır kapanır bölümler sessizce bozulur. Her sınıf kaynak kodda aranıp üç kovadan birine konuyor.', 'gbc-core' )
					. '</p>';

				$aciklama = array(
					'sil'     => __( 'Eklenti kaynağında hiç geçmiyor — eski şablonlardan kalmış, güvenle silinir.', 'gbc-core' ),
					'kosullu' => __( 'Kaynakta var ama ölçülen sayfalarda basılmamış. Önce nerede kullanıldığını bul.', 'gbc-core' ),
					'js'      => __( 'JavaScript çalışma anında ekliyor. SİLME — akordeon, sütun, uyarı kutusu bunlara bağlı.', 'gbc-core' ),
				);
				foreach ( array( 'sil', 'kosullu', 'js' ) as $kk ) {
					if ( empty( $kova[ $kk ] ) ) { continue; }
					list( $ea, $er, $ez ) = gbc_hz_olu_etiket( $kk );
					echo '<div style="margin-bottom:10px">';
					echo '<span style="background:' . esc_attr( $ez ) . ';color:' . esc_attr( $er )
						. ';border-radius:8px;padding:2px 8px;font-size:11px;font-weight:700">' . esc_html( $ea )
						. ' · ' . (int) count( $kova[ $kk ] ) . '</span> ';
					echo '<span style="font-size:12.5px;color:#5C6470">' . esc_html( $aciklama[ $kk ] ) . '</span>';
					echo '<div style="font-size:12px;line-height:2;margin-top:4px">';
					foreach ( $kova[ $kk ] as $c ) { echo '<code style="margin-right:6px">.' . esc_html( $c ) . '</code>'; }
					echo '</div></div>';
				}
				echo '</div>';
			}

			if ( $temaki ) {
				echo '<p style="font-size:12px;color:#8A919C;line-height:2;max-width:1000px">'
					. '<strong>' . esc_html__( 'Tema ve WordPress çekirdeği (bizim silemeyeceğimiz):', 'gbc-core' ) . '</strong><br>';
				foreach ( array_slice( $temaki, 0, 20 ) as $c ) { echo '<code style="margin-right:6px">.' . esc_html( $c ) . '</code>'; }
				echo '</p>';
			}
		}

		/* Dosya dosya — hangi dosyayı temizlemek işe yarar */
		if ( ! empty( $s['olu_dosya'] ) ) {
			echo '<h3 style="margin-top:20px">' . esc_html__( 'Dosya dosya — önce hangisi temizlenmeli', 'gbc-core' ) . '</h3>';
			echo '<p style="max-width:1000px;color:#5C6470;font-size:13.5px">'
				. esc_html__( 'Şablon CSS’i zaten şablon başına yükleniyor: Gezi CSS’i yalnız gezi rehberlerine iniyor. Bu yüzden toplam yüzde yanıltıcı — asıl soru her dosyanın KENDİ sayfalarında ne kadarının kullanıldığı. Aşağıdaki liste ölü sınıf sayısına göre sıralı; en üstteki dosya en çok kazanç getirir.', 'gbc-core' )
				. '</p>';
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
				. '<th>' . esc_html__( 'CSS dosyası', 'gbc-core' ) . '</th>'
				. '<th style="width:100px">' . esc_html__( 'İndirilen', 'gbc-core' ) . '</th>'
				. '<th style="width:110px">' . esc_html__( 'Sınıf', 'gbc-core' ) . '</th>'
				. '<th style="width:130px">' . esc_html__( 'Kullanılmayan', 'gbc-core' ) . '</th>'
				. '<th>' . esc_html__( 'Örnek ölü sınıflar', 'gbc-core' ) . '</th></tr></thead><tbody>';
			foreach ( $s['olu_dosya'] as $d ) {
				$renk = $d['yuzde'] >= 60 ? '#B3261E' : ( $d['yuzde'] >= 35 ? '#8A6100' : '#1A7F37' );
				echo '<tr><td><code style="font-size:12px">' . esc_html( $d['dosya'] ) . '</code>'
					. ( isset( $d['bizim'] ) ? '<div style="color:#8A919C;font-size:11px">'
						. esc_html( sprintf( __( 'bizim %1$d · tema %2$d', 'gbc-core' ), (int) $d['bizim'], (int) $d['tema'] ) )
						. '</div>' : '' ) . '</td>'
					. '<td>' . esc_html( $d['bayt'] ? size_format( (int) $d['bayt'] ) : '—' ) . '</td>'
					. '<td>' . (int) $d['toplam'] . '</td>'
					. '<td style="color:' . esc_attr( $renk ) . ';font-weight:700">' . (int) $d['olu'] . ' · %' . (int) $d['yuzde'] . '</td>'
					. '<td style="font-size:11.5px;color:#5C6470">';
				foreach ( (array) $d['ornekler'] as $c ) { echo '<code style="margin-right:5px">.' . esc_html( $c ) . '</code>'; }
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/* --- LiteSpeed --- */
	if ( ! empty( $s['litespeed']['var'] ) ) {
		echo '<h2 style="margin-top:28px">' . esc_html__( 'LiteSpeed ayarları', 'gbc-core' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th style="width:280px">' . esc_html__( 'Ayar', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Ne işe yarar', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $s['litespeed']['kontroller'] as $k ) {
			if ( ! empty( $k['bilerek'] ) ) {
				$rz = '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#5C4A16;background:#FBF1D9">'
					. esc_html__( 'bilerek kapalı', 'gbc-core' ) . '</span>';
			} elseif ( null === $k['iyi'] ) {
				$rz = '<span style="color:#5C6470">' . esc_html( (string) ( $k['deger'] ?? '—' ) ) . '</span>';
			} elseif ( (string) $k['deger'] === (string) $k['iyi'] ) {
				$rz = '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#1A6B31;background:#E4F3E8">' . esc_html__( 'Açık', 'gbc-core' ) . '</span>';
			} else {
				$rz = '<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#9C2A2B;background:#FBE7E7">' . esc_html__( 'KAPALI', 'gbc-core' ) . '</span>';
			}
			echo '<tr><td><strong>' . esc_html( $k['ad'] ) . '</strong></td><td>' . $rz . '</td><td style="color:#5C6470">' . esc_html( $k['not'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* --- Site sagligi --- */
	if ( ! empty( $s['saglik'] ) ) {
		$say = array( 'critical' => 0, 'recommended' => 0, 'good' => 0 );
		foreach ( $s['saglik'] as $t ) { if ( isset( $say[ $t['durum'] ] ) ) { $say[ $t['durum'] ]++; } }
		echo '<h2 style="margin-top:28px">' . esc_html__( 'WordPress Site Sağlığı', 'gbc-core' )
			. ' <span style="font-weight:400;font-size:14px;color:#8A919C">'
			. esc_html( sprintf( __( '— %1$d iyi · %2$d öneri · %3$d kritik', 'gbc-core' ), $say['good'], $say['recommended'], $say['critical'] ) )
			. '</span></h2>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
			. '<th style="width:110px">' . esc_html__( 'Durum', 'gbc-core' ) . '</th>'
			. '<th style="width:330px">' . esc_html__( 'Kontrol', 'gbc-core' ) . '</th>'
			. '<th>' . esc_html__( 'Açıklama', 'gbc-core' ) . '</th></tr></thead><tbody>';
		$agir_once = array( 'critical' => 0, 'recommended' => 1, 'good' => 2 );
		$liste = $s['saglik'];
		usort( $liste, static function ( $a, $b ) use ( $agir_once ) {
			return ( $agir_once[ $a['durum'] ] ?? 3 ) <=> ( $agir_once[ $b['durum'] ] ?? 3 );
		} );
		foreach ( $liste as $t ) {
			$h = array(
				'critical'    => array( __( 'Kritik', 'gbc-core' ), '#9C2A2B', '#FBE7E7' ),
				'recommended' => array( __( 'Öneri', 'gbc-core' ),  '#8A6100', '#FCF3E1' ),
				'good'        => array( __( 'İyi', 'gbc-core' ),    '#1A6B31', '#E4F3E8' ),
			);
			$v = $h[ $t['durum'] ] ?? array( $t['durum'], '#5C6470', '#EEF0F3' );
			echo '<tr><td><span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;color:'
				. esc_attr( $v[1] ) . ';background:' . esc_attr( $v[2] ) . '">' . esc_html( $v[0] ) . '</span></td>'
				. '<td><strong>' . esc_html( $t['baslik'] ) . '</strong></td>'
				. '<td style="color:#5C6470;font-size:13.5px">' . esc_html( mb_substr( $t['aciklama'], 0, 200 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* --- PageSpeed --- */
	if ( ! empty( $s['psi_istendi'] ) && empty( $s['psi'] ) ) {
		echo '<div class="notice notice-warning inline" style="margin-top:22px"><p>'
			. esc_html__( 'PageSpeed istendi ama sonuç dönmedi. "PageSpeed bağlantı testi" butonuna basın; hatanın ne olduğunu tek cümleyle söyler.', 'gbc-core' )
			. '</p></div>';
	}
	if ( ! empty( $s['psi'] ) ) {
		echo '<h2 style="margin-top:28px">' . esc_html__( 'Google PageSpeed', 'gbc-core' ) . '</h2>';
		foreach ( $s['psi'] as $ad => $p ) {
			echo '<h3 style="margin-bottom:6px">' . esc_html( $ad ) . '</h3>';
			echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px">';
			foreach ( array( 'mobil' => __( 'Mobil', 'gbc-core' ), 'masaustu' => __( 'Masaüstü', 'gbc-core' ) ) as $kk => $kad ) {
				$d = $p[ $kk ] ?? array();
				if ( ! empty( $d['hata'] ) ) {
					echo gbc_hz_kart( $kad, '—', $d['hata'], '#8A919C' );
					continue;
				}
				$pn = (int) ( $d['puan'] ?? 0 );
				$rk = $pn >= 90 ? '#1A7F37' : ( $pn >= 50 ? '#8A6100' : '#B32D2E' );
				echo gbc_hz_kart( $kad, (string) $pn,
					'LCP ' . ( $d['LCP'] ?? '—' ) . ' · CLS ' . ( $d['CLS'] ?? '—' ) . ' · TBT ' . ( $d['TBT'] ?? '—' ), $rk );
			}
			echo '</div>';
		}
	}

	gbc_hz_ayar_formu();
	echo '</div>';
}

function gbc_hz_ayar_formu() {
	$a = gbc_hz_ayar();
	echo '<h2 style="margin-top:28px">' . esc_html__( 'PageSpeed bağlantısı', 'gbc-core' ) . '</h2>';
	echo '<form method="post" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:18px 22px;max-width:700px">';
	wp_nonce_field( 'gbc_hz' );
	echo '<p style="margin-top:0;color:#444">'
		. esc_html__( 'Google PageSpeed Insights anahtarsız da çalışır ama kotaya takılır (HTTP 429). Ücretsiz anahtar alıp buraya yapıştırınca mobil ve masaüstü puanı her ölçümde gelir.', 'gbc-core' )
		. '</p>';

	/* Anahtara IP kisiti konacaksa dogru adres: sunucunun CIKIS IP'si.
	   Sitenin A kaydi (gelen trafik) ile ayni olmayabilir — 28 Eylul 2026'da
	   tam bu yuzden HTTP 403 alindi. */
	$ip = gbc_hz_cikis_ip();
	echo '<p style="color:#444;font-size:13px;background:#F6F7F9;border-radius:8px;padding:10px 12px">'
		. '<strong>' . esc_html__( 'Sunucunun dışarı çıkış adresi:', 'gbc-core' ) . '</strong> '
		. ( $ip['v4'] ? '<code>' . esc_html( $ip['v4'] ) . '</code>' : esc_html__( 'okunamadı', 'gbc-core' ) )
		. ( $ip['v6'] ? ' · <code>' . esc_html( $ip['v6'] ) . '</code>' : '' )
		. '<br>' . esc_html__( 'Anahtara IP kısıtı koyacaksan Google Cloud’a BU adresi yaz. Sitenin kendi IP’si (gelen trafiğin adresi) farklı olabilir; oraya site IP’si yazılırsa PageSpeed HTTP 403 verir.', 'gbc-core' )
		. '</p>';
	echo '<p><label for="gbc_hz_psi"><strong>' . esc_html__( 'API anahtarı', 'gbc-core' ) . '</strong></label><br>'
		. '<input type="text" id="gbc_hz_psi" name="gbc_hz_psi" class="regular-text" style="width:100%;max-width:520px" value="'
		. esc_attr( $a['psi_anahtar'] ) . '" placeholder="AIza..."></p>';
	echo '<p style="font-size:13px;color:#5C6470">'
		. esc_html__( 'Nereden alınır: console.cloud.google.com → yeni proje → API ve Hizmetler → Kitaplık → “PageSpeed Insights API” → Etkinleştir → Kimlik Bilgileri → API anahtarı oluştur. Ücretsiz, kredi kartı istemiyor.', 'gbc-core' )
		. '</p>';
	echo '<p><button type="submit" name="gbc_hz_kaydet" class="button button-primary">' . esc_html__( 'Kaydet', 'gbc-core' ) . '</button></p>';
	echo '</form>';
}
