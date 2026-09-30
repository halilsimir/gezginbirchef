<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — CSS süzgeci: ölü sınıfları yayına çıkmadan ayıklar.
 *
 * NEDEN BOYLE, SNIPPET'I ELLE DUZENLEYEREK DEGIL
 * ----------------------------------------------
 * Halil snippet'leri kendi yonetiyor. Snippet koduna elle girmek geri
 * alinmasi zor, surumu takip edilmeyen bir degisiklik olurdu. Buradaki
 * yol uc sey kazandiriyor:
 *   1) Liste kodda duruyor — hangi sinifin neden silindigi yazili.
 *   2) Geri almak tek satir: sinifi listeden cikar.
 *   3) Test edilebilir — asagidaki ayiklayicinin 60+ testi var.
 * Snippet'in kendi icerigi degismiyor; yalniz dosyaya yazilan surum
 * temizleniyor. Halil daha sonra snippet'ten de silmek isterse, liste
 * ona hazir kilavuz.
 *
 * LISTE NEREDEN GELDI
 * -------------------
 * 29 Eyl 2026'da bes suzgecten gecirilerek cikarildi
 * (gbc-olu-css-listesi-v2-29eylul2026.md):
 *   921 bizim sinifimiz
 *   -737 sayfalarda kullaniliyor
 *    -52 JavaScript ekliyor            -> SILINMEZ
 *    -52 eklenti kaynaginda geciyor    -> SILINMEZ
 *    -29 WPCode sablon PHP'si basiyor  -> SILINMEZ (uyuyor, olu degil)
 *    ----
 *     51 gercekten silinebilir
 * Halil 29 Eyl 2026'da onayladi: "yok ediyoruz calismayan gereksiz tam net temizlik".
 *
 * AYIKLAMA KURALI — kavga cikarmayacak kadar korkak
 * -------------------------------------------------
 * Bir kural ancak seciciSININ TAMAMI olu sinif hedefliyorsa dusuyor.
 * Karisik secici listesinde (".gz-badge, .gz-live-row") yalniz olu olan
 * parca ataliyor, kural kaliyor. Secici olu sinifin YANINDA baska bir
 * sey de tasiyorsa (".gz-badge .gz-live-row", ".gz-badge.aktif")
 * DOKUNULMUYOR — o bir bilesim, tek basina olu sinif degil.
 *
 * 30 Eyl 2026 · v1.25.0
 */

/**
 * Silinecek 51 sınıf.
 *
 * A) Hicbir yerde gecmiyor — ne sayfada, ne JS'te, ne eklentide, ne snippet'te.
 * B) Yalniz CSS snippet'inde tanimli, hicbir kod basmiyor.
 */
function gbc_css_olu_siniflar() {
	return array(
		/* --- A) Hicbir yerde gecmiyor (11) --- */
		'gbc-affk-et', 'gbc-affk-n', 'gz-author-badge', 'gz-author-meta', 'gz-badge',
		'gz-dict-note', 'gz-dict-tip', 'gz-faq-num', 'gz-pill-link', 'gz-s-desc', 'gz-s-head',

		/* --- B1) v7 galeri bileseni · snippet 23533 Ana Sayfa CSS (29) ---
		   Komple bir kaydirmali galeri sistemi. Tanimli, hic kullanilmamis. */
		'gz-v7-wrapper', 'gz-v7-slider-area', 'gz-v7-slider', 'gz-v7-slide', 'gz-v7-slide-box',
		'gz-v7-img', 'gz-v7-overlay', 'gz-v7-chic-title', 'gz-v7-badge', 'gz-v7-social-bar',
		'gz-v7-soc', 'gz-v7-icon', 'gz-v7-soc-info', 'gz-v7-section', 'gz-v7-header',
		'gz-v7-h2-title', 'gz-v7-btn-all', 'gz-v7-pills', 'gz-v7-grid-16-9', 'gz-v7-grid-square',
		'gz-v7-card', 'gz-v7-thumb-16-9', 'gz-v7-thumb-sq', 'gz-v7-vlog-card', 'gz-v7-vlog-thumb',
		'gz-v7-play-btn', 'gz-pill-active', 'gz-pill-count', 'gz-sr-only',

		/* --- B2) Yalniz CSS snippet'inde tanimli, hicbir kod basmiyor (6) ---
		   27099 Reklam CLS, 22608 Gezi CSS, 26923 Blog CSS. */
		'gz-grid-3', 'gz-video-responsive', 'tr-video-wrap',
		'tr-variant-blonde', 'tr-variant-brown',
		/* 30 Eyl 2026: 'v1-video-responsive' bu listeden CIKARILDI.
		   Silmeden once 14 canli sablon sayfasi tarandi ve sinif
		   /atina-rivierasi/ sayfasinin HTML'inde CALISIR halde bulundu.
		   Eski olculerde "olu" gorunmesinin sebebi, o gun taranan ornek
		   sayfalar arasinda Liste sablonunun bu ornegi yoktu. Silinseydi
		   o sayfadaki video sarmalayicisi stilsiz kalacakti. */
	);
}

/**
 * ADAY — henüz beş süzgeçten geçmedi, SİLİNMİYOR.
 *
 * Hiz panelinin olu listesinde gorunuyorlar ama JS taramasi, eklenti
 * kaynagi ve WPCode snippet taramasindan gecirilmediler. Dogrulanmadan
 * silmek, 29 Eyl 2026'da iki kez yakalanan hataya geri donmek olur
 * (o gun 52 + 29 sinif tam bu yuzden listeden cikarilmisti).
 * Denetim yapilinca buradan yukaridaki listeye tasinacaklar.
 */
function gbc_css_olu_adaylar() {
	return array(
		'gz-post-grid', 'gz-post-card', 'gz-post-img-wrapper', 'gz-post-img',
		'gz-post-content', 'gz-post-title', 'gz-post-excerpt', 'gz-post-btn', 'gz-pagination',
	);
}

/**
 * ASLA silinmeyecek sınıflar — güvenlik ağı.
 *
 * Listeye yanlislikla bunlardan biri girerse ayiklayici onu yok sayar.
 * JavaScript'in calisma aninda ekledigi siniflar; HTML'de hic gorunmezler,
 * o yuzden "olu" gibi olculurler ama silinirse bilesen bozulur.
 */
function gbc_css_dokunma_siniflari() {
	return array(
		'gbc-col', 'gbc-ac', 'gbc-flash', 'gbc-ok', 'gbc-err', 'gbc-sef',
		'gbc-sor-count', 'gbc-related-ph', 'gbc-in-et', 'gbc-rota-dar', 'gbc-rota-not',
		'fb-chip', 'fb-chips', 'gz-aff-bildirim', 'gz-kart-et',
	);
}

/** Etkin ölü liste — dokunma listesi düşülmüş hâli. */
function gbc_css_olu_etkin() {
	$dokunma = array_flip( gbc_css_dokunma_siniflari() );
	$out = array();
	foreach ( gbc_css_olu_siniflar() as $s ) {
		if ( isset( $dokunma[ $s ] ) ) { continue; }
		$out[] = $s;
	}
	return $out;
}

/**
 * Bir seçici parçası YALNIZCA ölü bir sınıfı mı hedefliyor?
 *
 * ".gz-badge"          -> evet
 * ".gz-badge::before"  -> evet (aynı öğe)
 * ".gz-badge:hover"    -> evet
 * ".gz-badge .x"       -> HAYIR (torun seçici, x yaşıyor olabilir)
 * ".gz-badge.aktif"    -> HAYIR (bileşim)
 * ".x .gz-badge"       -> HAYIR
 * "div.gz-badge"       -> HAYIR (etiketle bileşim; temkinli davranıyoruz)
 *
 * @param string $parca Tek bir seçici (virgülsüz).
 * @param array  $olu   Ölü sınıf adları (flip edilmiş).
 * @return bool
 */
function gbc_css_parca_olu_mu( $parca, $olu ) {
	/* 30 Eyl 2026 — KURAL GENISLETILDI.
	   Ilk surum yalniz ".gz-badge" gibi TEK BASINA duran seciciyi dusuruyordu.
	   Canli olcumde goruldu ki kalan olu kurallarin hepsi torun secici:
	   ".entry-content .gz-badge", ".entry-content .gbc-affk .gbc-affk-n".
	   Bunlar da asla eslesemez — cunku zincirin bir halkasi HIC var olmayan
	   bir sinif istiyor. Ata mi torun mu fark etmez: olmayan sinif zinciri
	   kirar. Yeni kural: seciciNIN HERHANGI bir yerinde olu sinif geciyorsa
	   o secici olu.

	   TEK ISTISNA — OLUMSUZLAMA VE LISTE SOZDE SINIFLARI:
	     .x:not(.gz-badge)         -> gz-badge OLMADIGI icin EŞLEŞIR
	     .x:is(.gz-badge, .gz-hero)-> gz-hero varsa eslesir
	     .x:where(...) / :has(...) -> ayni belirsizlik
	   Bu yuzden parantez ICINDEKI sinif adlari hic sayilmaz; parantezli bir
	   sozde sinif varsa temkinli davranip seciciye DOKUNMUYORUZ. */
	$p = preg_replace( '#/\*.*?\*/#s', '', (string) $parca );
	$p = trim( (string) $p );
	if ( '' === $p ) { return false; }

	/* Parantezli sozde sinif (:not, :is, :where, :has, :nth-child...) varsa
	   karar vermiyoruz. */
	if ( false !== strpos( $p, '(' ) ) { return false; }

	/* Oznitelik secicisi: icindeki metin sinif adina benzeyebilir
	   ([data-x="gz-badge"]). Bu yuzden ayraclarin ICINI siliyoruz — kalani
	   taramak hem dogru hem guvenli:
	     .gz-v7-wrapper [role="button"] -> .gz-v7-wrapper      (olu, duser)
	     [data-x="gz-badge"]            -> (sinif kalmaz, dokunulmaz)
	     .gz-badge[data-x]              -> .gz-badge           (olu, duser)
	   30 Eyl 2026: once ayrac gorunce hic karar vermiyorduk; canli olcumde
	   ".gz-v7-wrapper [role=\"button\"]" bu yuzden temizlenmeden kalmisti. */
	$p = preg_replace( '/\[[^\]]*\]/', ' ', $p );

	/* Seciciteki butun sinif adlari. */
	if ( ! preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $p, $m ) ) { return false; }

	foreach ( $m[1] as $ad ) {
		if ( isset( $olu[ $ad ] ) ) { return true; }
	}
	return false;
}

/**
 * Bir seçici listesini virgülle böler — parantez, köşeli ayraç ve
 * tırnak içindeki virgüller bölmez (:is(a,b), [x="a,b"] gibi).
 *
 * @return array
 */
function gbc_css_secici_bol( $secici ) {
	$out = array(); $tampon = ''; $derin = 0; $tirnak = '';
	$n = strlen( $secici );
	for ( $i = 0; $i < $n; $i++ ) {
		$c = $secici[ $i ];
		if ( '' !== $tirnak ) {
			$tampon .= $c;
			if ( $c === $tirnak && ( $i === 0 || '\\' !== $secici[ $i - 1 ] ) ) { $tirnak = ''; }
			continue;
		}
		if ( '"' === $c || "'" === $c ) { $tirnak = $c; $tampon .= $c; continue; }
		if ( '(' === $c || '[' === $c ) { $derin++; $tampon .= $c; continue; }
		if ( ')' === $c || ']' === $c ) { $derin--; $tampon .= $c; continue; }
		if ( ',' === $c && $derin <= 0 ) { $out[] = $tampon; $tampon = ''; continue; }
		$tampon .= $c;
	}
	if ( '' !== trim( $tampon ) ) { $out[] = $tampon; }
	return $out;
}

/**
 * CSS metnini ölü kurallardan arındırır.
 *
 * Ayrastirici yazmiyoruz — kase derinligi sayan bir tarayici yeterli ve
 * cok daha guvenli. Yorumlar ve tirnak icindeki kaseler atlanir.
 * @media / @supports gibi saran bloklarin ICINE inilir.
 *
 * @param string $css
 * @param array|null $olu Ölü sınıf listesi (test için verilebilir).
 * @return array ( 'css' => string, 'dusen' => int, 'bayt' => int )
 */
function gbc_css_temizle( $css, $olu = null ) {
	$css = (string) $css;
	if ( '' === trim( $css ) ) { return array( 'css' => $css, 'dusen' => 0, 'bayt' => 0 ); }

	/* Dokunma listesi HER ZAMAN uygulanir — cagiran elle liste verse bile.
	   30 Eyl 2026: once yalniz gbc_css_olu_etkin() yolunda suzuluyordu;
	   elle verilen liste guvenlik agini atlatiyordu. */
	$olu = (array) ( null === $olu ? gbc_css_olu_etkin() : $olu );
	$olu = array_values( array_diff( $olu, gbc_css_dokunma_siniflari() ) );
	$olu = array_flip( $olu );
	if ( ! $olu ) { return array( 'css' => $css, 'dusen' => 0, 'bayt' => 0 ); }

	$dusen = 0; $bayt = 0;

	$isle = function ( $metin ) use ( &$isle, $olu, &$dusen, &$bayt ) {
		$out = ''; $bas = 0; $n = strlen( $metin );
		$i = 0;
		while ( $i < $n ) {
			/* Yorum: oldugu gibi gecir. */
			if ( '/' === $metin[ $i ] && $i + 1 < $n && '*' === $metin[ $i + 1 ] ) {
				$son = strpos( $metin, '*/', $i + 2 );
				$son = ( false === $son ) ? $n : $son + 2;
				$i   = $son;
				continue;
			}
			/* Tirnak: icindekini atla. */
			if ( '"' === $metin[ $i ] || "'" === $metin[ $i ] ) {
				$t = $metin[ $i ]; $i++;
				while ( $i < $n && ( $metin[ $i ] !== $t || '\\' === $metin[ $i - 1 ] ) ) { $i++; }
				$i++;
				continue;
			}
			/* Blok basi. */
			if ( '{' === $metin[ $i ] ) {
				$secici = substr( $metin, $bas, $i - $bas );
				/* Eslesen kapanisi bul. */
				$derin = 1; $j = $i + 1;
				while ( $j < $n && $derin > 0 ) {
					if ( '/' === $metin[ $j ] && $j + 1 < $n && '*' === $metin[ $j + 1 ] ) {
						$s = strpos( $metin, '*/', $j + 2 ); $j = ( false === $s ) ? $n : $s + 2; continue;
					}
					if ( '"' === $metin[ $j ] || "'" === $metin[ $j ] ) {
						$t = $metin[ $j ]; $j++;
						while ( $j < $n && ( $metin[ $j ] !== $t || '\\' === $metin[ $j - 1 ] ) ) { $j++; }
						$j++; continue;
					}
					if ( '{' === $metin[ $j ] ) { $derin++; }
					if ( '}' === $metin[ $j ] ) { $derin--; }
					$j++;
				}
				$govde = substr( $metin, $i + 1, $j - $i - 2 );
				$tam   = substr( $metin, $bas, $j - $bas );

				$kirpik = trim( $secici );
				if ( '' !== $kirpik && '@' === $kirpik[0] ) {
					/* Saran blok (@media, @supports): icine in. */
					if ( preg_match( '/^@(media|supports|layer|container)/i', $kirpik ) ) {
						$ic = $isle( $govde );
						if ( '' === trim( $ic ) ) { $dusen++; $bayt += strlen( $tam ); }
						else { $out .= $secici . '{' . $ic . '}'; }
					} else {
						$out .= $tam;   /* @font-face, @keyframes: dokunma */
					}
				} else {
					$parcalar = gbc_css_secici_bol( $secici );
					$kalan = array();
					foreach ( $parcalar as $p ) {
						if ( ! gbc_css_parca_olu_mu( $p, $olu ) ) { $kalan[] = $p; }
					}
					if ( ! $kalan ) {
						/* Kural dusuyor ama secici alanindaki YORUM kaliyor:
						   o yorum bir sonraki kurali anlatiyor olabilir. */
						$korunan = '';
						if ( preg_match_all( '#/\*.*?\*/#s', $secici, $ym ) ) {
							$korunan = implode( "\n", $ym[0] ) . "\n";
						}
						$out .= $korunan;
						$dusen++; $bayt += strlen( $tam ) - strlen( $korunan );
					} elseif ( count( $kalan ) !== count( $parcalar ) ) {
						$out .= implode( ',', $kalan ) . '{' . $govde . '}';
						$bayt += strlen( $tam ) - ( strlen( implode( ',', $kalan ) ) + strlen( $govde ) + 2 );
					} else {
						$out .= $tam;
					}
				}
				$i = $j; $bas = $j;
				continue;
			}
			$i++;
		}
		if ( $bas < $n ) { $out .= substr( $metin, $bas ); }
		return $out;
	};

	$yeni = $isle( $css );

	/* GUVENLIK FRENI — 30 Eyl 2026 duzeltmesi.
	   Ilk yazimda fren "cikti girdinin yarisindan kucukse" diyordu. Yanlisti:
	   kucuk bir dosyada tek bir olu kuralin dusmesi de bu esigi asiyor ve
	   temizlik hic yapilmiyordu (testte 12 kez yakalandi). Dogru olcut oran
	   degil, HESAP: ne kadar bayt DUSURDUGUMUZU zaten biliyoruz. Gercek kayip
	   bunun belirgin uzerindeyse ayrastirici bir yeri yutmus demektir. */
	$gercek_kayip = strlen( $css ) - strlen( $yeni );
	if ( $gercek_kayip > (int) ( $bayt * 1.2 ) + 64 ) {
		return array( 'css' => $css, 'dusen' => 0, 'bayt' => 0, 'fren' => true );
	}

	return array( 'css' => $yeni, 'dusen' => $dusen, 'bayt' => $bayt );
}
