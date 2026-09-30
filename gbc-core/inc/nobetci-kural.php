<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC Core — Kontrol Merkezi (kural tabanlı sayfa taraması).
 * Eski adı "Nöbetçi" idi; ekran adı 28 Eylül 2026'da değişti, slug aynı
 * kaldı (gbc-nobetci-kural) ki eski bağlantılar kırılmasın.
 *
 * TARAMA KURALLARI — 28 Eylül 2026'da yazıldı, kod bunlara uyar:
 *
 * 1) ZİYARETÇİ GÖZÜYLE. Sayfa çerezsiz, oturumsuz, önbellekli hâliyle
 *    indirilir. Önbellek atlatılmaz; ziyaretçi ne görüyorsa o ölçülür.
 *    Yönetici HTML'i kullanılmaz.
 *
 * 2) İZ, GERÇEK ÇIKTIDA ARANIR. HTML DOMDocument ile okunur, iz bir CSS
 *    seçicisiyle aranır. <style>, <script> ve CSS/JS dosyası içindeki
 *    eşleşmeler sayılmaz — DOM üzerinde element aradığımız için metin
 *    içindeki geçişler zaten eşleşemez.
 *    TEK İSTİSNA: görünür çıktısı olmayan ölçüm motorları (GA4, tıklama
 *    sayacı). Onların izi betiğin kendisidir; tablo bunu "betik" diye
 *    ayrı işaretler, gizlemez.
 *
 * 3) HER MOTOR İÇİN "NEREDE OLMALI" YAZILIR. Dört durum:
 *      ✓ Çalışıyor = olmalı ve var
 *      ✗ EKSİK     = olmalı ama yok    → SORUN
 *      ! FAZLA     = olmamalı ama var  → SORUN
 *      – Gerekmez  = olmamalı ve yok
 *
 * 4) SAYFA SAYFA SONUÇ. Her sayfa bir satır; süzgeç ve CSV var.
 *
 * 5) SAYFA TARAMASIYLA ÖLÇÜLEMEYEN MOTOR sayfa tablosundan çıkarılır,
 *    sunucuda kontrol edilir (Şablon CSS dosyaya).
 *
 * 6) KAYNAK SÜTUNU kodun şu an gerçekten çalıştığı dosyayı yazar.
 *
 * 7) KARŞILAŞTIRMA. Dün ✓ olup bugün ✗ olan her sayfa+motor ayrı alarm
 *    olarak Günlük Kontrol'e düşer.
 */

define( 'GBC_NK_META',  '_gbc_nk' );
define( 'GBC_NK_DURUM', 'gbc_nk_durum' );
define( 'GBC_NK_ZORLA', 'gbc_nk_zorla' );
define( 'GBC_NK_ADET',  9 );

/**
 * KURAL SÜRÜMÜ — motor haritası her değiştiğinde bir artar.
 * Sayfanın kayıtlı sonucu bu sürümden eskiyse satır "yeniden taranacak"
 * diye işaretlenir ve sonraki turlarda ÖNCE o sayfa taranır.
 *   1 = ilk kural seti
 *   2 = ilk tur düzeltmesi (YouTube izi, ilgili yazı seçicileri, kur koşulu)
 *   3 = 28 Eyl 2026 üçüncü düzeltme (YouTube ham iz, İç link kutusu tek motor)
 *   4 = 28 Eyl 2026 birleşme: iki Nöbetçi tek Kontrol Merkezi oldu.
 *       Karar bekleyen 8 motor da ölçülüyor (N/A), CSS gövdesi açılıyor,
 *       otomatik iz tabanı ve sayfa boyutu karşılaştırması eklendi.
 */
define( 'GBC_NK_KURAL_SURUM', 5 );

/* ============================================================
   MOTOR HARİTASI
   kapsam: hepsi | ana_haric | sablon | kosul | sayilir | na | sunucu
   iz:     dom (CSS seçici) | ham (HTML'de düzenli ifade) | betik | sema | sunucu
   ============================================================ */
function gbc_nk_motorlar() {
	return array(

		'ga4' => array(
			'ad'     => __( 'GA4 ölçüm', 'gbc-core' ),
			'kaynak' => 'modules/30-olcum.php',
			'iz'     => 'betik',
			'desen'  => 'G-8P6FY60W1W',
			'kapsam' => 'hepsi',
			'not'    => __( 'Görünür çıktısı yok; izi ölçüm betiğidir.', 'gbc-core' ),
		),
		'tiklama' => array(
			'ad'     => __( 'Tıklama motoru', 'gbc-core' ),
			'kaynak' => 'modules/30-olcum.php',
			'iz'     => 'betik',
			'desen'  => 'outbound_click',
			'kapsam' => 'hepsi',
			'not'    => __( 'Görünür çıktısı yok; izi olay betiğidir.', 'gbc-core' ),
		),
		'aff_sayac' => array(
			'ad'     => __( 'Ortaklık tık sayacı', 'gbc-core' ),
			'kaynak' => 'modules/70-ortaklik-denetim.php',
			'iz'     => 'betik',
			'desen'  => 'gbc_aff_tik',
			'kapsam' => 'hepsi',
			'not'    => __( 'Görünür çıktısı yok; izi sayaç betiğidir.', 'gbc-core' ),
		),
		'sema' => array(
			'ad'     => __( 'Şema motoru', 'gbc-core' ),
			'kaynak' => 'modules/21-sema-motoru.php',
			'iz'     => 'sema',
			'desen'  => '',
			'kapsam' => 'hepsi',
			'not'    => __( 'JSON-LD çözülür, düğüm tipleri okunur.', 'gbc-core' ),
		),
		'sema_cekirdek' => array(
			'ad'     => __( 'Şema çekirdeği (BreadcrumbList)', 'gbc-core' ),
			'kaynak' => 'modules/20-sema-cekirdek.php',
			'iz'     => 'sema',
			'desen'  => 'BreadcrumbList',
			'kapsam' => 'ana_haric',
		),
		'emoji' => array(
			'ad'     => __( 'Emoji YOK', 'gbc-core' ),
			'kaynak' => 'modules/60-font-temizlik.php',
			'iz'     => 'dom',
			'secici' => 'script[src*="wp-emoji-release"]',
			'kapsam' => 'hepsi',
			'ters'   => true,  /* bulunması SORUN; bulunmaması doğru */
		),
		'ic_link' => array(
			'ad'     => __( 'İç link kutusu', 'gbc-core' ),
			'kaynak' => 'modules/52-ilgili-yazilar.php · modules/21-sema-motoru.php',
			'iz'     => 'dom',
			/* Dort kutudan HERHANGI biri varsa yazi ic linke bagli sayilir.
			   Virgul = VEYA. 28 Eyl 2026: eski "Ilgili yazilar" ve "Silo ic
			   linkleme" motorlari birlestirildi; ayri olculunce ayni isi yapan
			   iki kutudan biri yok diye bosuna ✗ veriyordu. */
			'secici' => '.gbc-silo, section.gbc-related, .gz-rel-list, .gz2-alakali',
			'kapsam' => 'ana_haric',
			'not'    => __( 'Silo kutusu, ilgili yazılar, Gezi/Liste kutuları — biri yeterli.', 'gbc-core' ),
		),
		'yt' => array(
			'ad'     => __( 'YouTube perdesi', 'gbc-core' ),
			'kaynak' => 'modules/54-yt-facade.php',
			/* 28 Eyl 2026: DOM secicisi birakildi. LiteSpeed betigi
			   src="data:text/javascript;base64,..." bicimine cevirip yerini
			   degistirebiliyor; DOM'da element olarak yakalanamiyordu.
			   Artik ham HTML'de kimlik etiketi araniyor. */
			'iz'     => 'ham',
			'desen'  => '#<script[^>]+id=["\']gbc-yt-facade["\']#i',
			'kapsam' => 'hepsi',
		),
		'icindekiler' => array(
			'ad'     => __( 'İçindekiler', 'gbc-core' ),
			'kaynak' => 'modules/21-sema-motoru.php',
			'iz'     => 'dom',
			'secici' => 'div#gbc-toc',
			'kapsam' => 'sablon',
			'sablon' => array( 'Gezi', 'Liste', 'Detay', 'Rota', 'Tarif', 'Blog', 'Sözlük' ),
			/* v1 "Liste rehberi" düzeninde (v1-lejant) kutu BİLEREK basılmaz —
			   kural eski JS sürümünden devralındı, motor da öyle davranıyor.
			   Bu sayfalarda "eksik" değil "gerekmez" yazar. 28 Eylül 2026. */
			'istisna_desen' => '#v1-lejant#i',
			'istisna_not'   => __( 'v1 liste düzeni: İçindekiler bu şablonda bilerek basılmıyor.', 'gbc-core' ),
		),
		'aff_bildirim' => array(
			'ad'     => __( 'Ortaklık bildirimi', 'gbc-core' ),
			'kaynak' => 'modules/71-ortaklik-motoru.php',
			'iz'     => 'dom',
			'secici' => '.gbc-ortaklik-not',
			'kapsam' => 'kosul',
			'kosul'  => 'ortaklik_var',
		),
		'aff_bag' => array(
			'ad'     => __( 'Ortaklık bağlantısı', 'gbc-core' ),
			'kaynak' => 'modules/71-ortaklik-motoru.php',
			'iz'     => 'dom',
			'secici' => 'a[data-aff]',
			'kapsam' => 'sayilir',   /* zorunlu degil, yalnizca sayilir */
		),
		'silo_sayi' => array(
			'ad'     => __( 'Silo kutusu', 'gbc-core' ),
			'kaynak' => 'modules/21-sema-motoru.php',
			'iz'     => 'dom',
			'secici' => '.gbc-silo',
			'kapsam' => 'sayilir',   /* zorunlu degil; Ic link kutusu zaten olcuyor */
		),
		'kur' => array(
			'ad'     => __( 'Canlı kur', 'gbc-core' ),
			'kaynak' => 'modules/44-kur.php',
			'iz'     => 'dom',
			'secici' => '.gbc-tl',
			'kapsam' => 'kosul',
			'kosul'  => 'fiyat_var',
		),
		'aff_stil' => array(
			'ad'     => __( 'Ortaklık stili', 'gbc-core' ),
			'kaynak' => 'modules/71-ortaklik-motoru.php',
			'iz'     => 'css',
			'desen'  => '.gbc-yan-ad',
			'kapsam' => 'hepsi',
			'not'    => __( 'LiteSpeed satır içi stili birleşik CSS dosyasına taşıyor; iz o dosyada aranır.', 'gbc-core' ),
		),

		/* ---- KARAR BEKLEYENLER ----------------------------------------
		   "Nerede olmalı" kuralı henüz yazılmadı. Tarama DIŞINDA bırakmak
		   yerine ÖLÇÜLÜYOR ama sorun sayılmıyor: durum "N/A". Böylece kod
		   hiç tetiklenmiyor mu, yoksa yalnız bazı sayfalarda mı çıkıyor,
		   ekranda görünür — kural da ona bakarak yazılır. */
		/* ================================================================
		   B7 KARARLARI — 29 Eylül 2026. 1046 sayfanın TAMAMI, kuralların
		   birebir kendi desenleriyle tarandı. Ölçülen dağılım:

		     Kart Video ....... 1046 / 1046
		     Galeri Lisansı ... 1046 / 1046
		     Şefe Sor ......... 1046 / 1046
		     AdSense .......... 1037 / 1046  (9 kurumsal SAYFA hariç)
		     Otel ............. 2   (105 Detay sayfasının 2'si)
		     Rota ............. 2
		     Feribot .......... 1
		     Yunan haritası ... 1
		     Bölüm medyası .... 1

		   İlk dördü zaten her yerde: ZORUNLU yapıldı, çünkü biri düşerse
		   ertesi sabah haber almak istiyoruz. AdSense'in olmadığı 9 sayfa
		   tam olarak kurumsal/yasal sayfalar (ana sayfa, iletişim, gizlilik,
		   görsel lisans, reklam politikası, proje iş birlikleri, tüm
		   bağlantılar, food stylist, halil-simir) — reklam OLMAMASI gereken
		   yerler. Bu yüzden kapsam 'sablon': yalnız içerik şablonlarında
		   beklenir, kurumsal sayfalar 'gerekmez' der.

		   Son beşi elle seçilmiş sayfalara konuyor. "Detay şablonunda
		   zorunlu" deseydik 103 SAHTE ALARM üretirdi. Kapsam 'sayilir':
		   nerede oldukları raporlanır, yoklukları alarm üretmez.
		   ================================================================ */
		'otel' => array(
			'ad' => __( 'Otel motoru', 'gbc-core' ), 'kaynak' => 'modules/40-otel.php',
			'iz' => 'ham', 'desen' => '#gbc-otel#i', 'kapsam' => 'sayilir',
		),
		'rota' => array(
			'ad' => __( 'Rota motoru', 'gbc-core' ), 'kaynak' => 'modules/41-rota.php',
			'iz' => 'ham', 'desen' => '#gbc-rota#i', 'kapsam' => 'sayilir',
		),
		'feribot' => array(
			'ad' => __( 'Feribot bulucu', 'gbc-core' ), 'kaynak' => 'modules/42-feribot.php',
			'iz' => 'ham', 'desen' => '#gbc-fb-veri#i', 'kapsam' => 'sayilir',
		),
		'yunan' => array(
			'ad' => __( 'Yunanistan haritası', 'gbc-core' ), 'kaynak' => 'modules/43-yunanistan.php',
			'iz' => 'ham', 'desen' => '#gbc-yn-veri#i', 'kapsam' => 'sayilir',
		),
		'bolum' => array(
			'ad' => __( 'Bölüm medyası', 'gbc-core' ), 'kaynak' => 'modules/50-bolum-medyasi.php',
			'iz' => 'ham', 'desen' => '#gbc-sm-(css|kap)#i', 'kapsam' => 'sayilir',
		),
		'kart_video' => array(
			'ad' => __( 'Kart video', 'gbc-core' ), 'kaynak' => 'modules/53-video-oynatici.php',
			'iz' => 'ham', 'desen' => '#gbc-vp-#i', 'kapsam' => 'hepsi',
		),
		'galeri_lisans' => array(
			'ad' => __( 'Galeri lisansı', 'gbc-core' ), 'kaynak' => 'modules/51-galeri-lisans.php',
			'iz' => 'sema', 'desen' => 'ImageObject', 'kapsam' => 'hepsi',
		),
		'ucus_perde' => array(
			'ad' => __( 'Uçuş perdesi', 'gbc-core' ), 'kaynak' => 'modules/55-ucus-perde.php',
			'iz' => 'ham', 'desen' => '#gbc-sky#i',
			/* B7 — karar verildi (29 Eylül 2026): perde yalnız Skyscanner
			   widget'ı olan sayfada beklenir. Widget şu an hiçbir sayfada yok,
			   bu yüzden motor her yerde "gerekmez" diyor. Eskiden N/A idi ve
			   152 sayfanın hiçbirinde çıkmadığı için "bozuk mu?" sorusu açıktı. */
			'kapsam' => 'kosul', 'kosul' => 'sky_widget',
		),
		'adsense' => array(
			'ad' => __( 'AdSense', 'gbc-core' ), 'kaynak' => 'modules/30-olcum.php',
			'iz' => 'ham', 'desen' => '#gbc-ad-ins|adsbygoogle#i',
			'kapsam' => 'sablon',
			'sablon' => array( 'Gezi', 'Liste', 'Detay', 'Rota', 'Tarif', 'Blog', 'Sözlük' ),
		),
		'sefe_sor' => array(
			'ad' => __( 'Şefe Sor', 'gbc-core' ), 'kaynak' => 'modules/10-guvenlik.php',
			'iz' => 'ham', 'desen' => '#gbc-sor-#i', 'kapsam' => 'hepsi',
		),

		'sablon_css' => array(
			'ad'     => __( 'Şablon CSS dosyaya', 'gbc-core' ),
			'kaynak' => 'modules/01-sablon-css.php',
			'iz'     => 'sunucu',
			'kapsam' => 'sunucu',
			'not'    => __( 'Sayfa taramasıyla ölçülmez; dosyalar sunucuda kontrol edilir.', 'gbc-core' ),
		),
	);
}

/**
 * Karar bekleyen motorlar için sorulacak soru.
 * Motor artık ÖLÇÜLÜYOR (kapsam 'na'); eksik olan yalnızca "nerede olmalı"
 * cevabı. Ekran, ölçülen kanıtla birlikte bu soruyu gösterir.
 */
function gbc_nk_karar_sorusu() {
	/* 29 Eylül 2026: BOŞ — dokuz motorun da kuralı yazıldı.
	   Karar, 1046 sayfanın tam taramasıyla verildi; gerekçeler motor
	   tanımlarının üstündeki B7 blokunda. Kapsamı 'na' olan motor
	   kalmadığı için ekranda soru gösterilmiyor.

	   Yeni bir motor 'na' ile eklenirse sorusu buraya yazılır. */
	return array();
}

/* ============================================================
   CSS SEÇİCİ → XPath
   Desteklenen: etiket, .sinif, #kimlik, [nitelik], [nitelik*="deger"],
   bunların birleşimi ve boşlukla alt eleman.
   ============================================================ */
function gbc_nk_xpath( $secici ) {
	/* Virgul = VEYA. "a, b" -> "//a | //b" */
	if ( false !== strpos( $secici, ',' ) ) {
		$yollar = array();
		foreach ( explode( ',', $secici ) as $tek ) {
			$tek = trim( $tek );
			if ( '' !== $tek ) { $yollar[] = gbc_nk_xpath( $tek ); }
		}
		return $yollar ? implode( ' | ', $yollar ) : '//none';
	}

	$parcalar = preg_split( '/\s+/', trim( $secici ) );
	$yol = '';

	foreach ( $parcalar as $p ) {
		if ( '' === $p ) { continue; }

		$etiket = '*';
		if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9]*)/', $p, $m ) ) {
			$etiket = $m[1];
			$p = substr( $p, strlen( $m[1] ) );
		}

		$kosul = array();

		if ( preg_match_all( '/\.([A-Za-z0-9_-]+)/', $p, $mc ) ) {
			foreach ( $mc[1] as $sinif ) {
				$kosul[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$sinif} ')";
			}
		}
		if ( preg_match( '/#([A-Za-z0-9_-]+)/', $p, $mi ) ) {
			$kosul[] = "@id='{$mi[1]}'";
		}
		if ( preg_match_all( '/\[([A-Za-z0-9_:-]+)(?:([*^$]?)=["\']([^"\']*)["\'])?\]/', $p, $ma, PREG_SET_ORDER ) ) {
			foreach ( $ma as $a ) {
				$nitelik = $a[1];
				if ( ! isset( $a[3] ) || '' === $a[3] ) {
					$kosul[] = "@{$nitelik}";
				} elseif ( '*' === $a[2] ) {
					$kosul[] = "contains(@{$nitelik}, '{$a[3]}')";
				} else {
					$kosul[] = "@{$nitelik}='{$a[3]}'";
				}
			}
		}

		$yol .= '//' . $etiket . ( $kosul ? '[' . implode( ' and ', $kosul ) . ']' : '' );
	}

	return $yol ? $yol : '//none';
}

/* ============================================================
   ZİYARETÇİ GÖZÜYLE İNDİRME
   ============================================================ */
function gbc_nk_indir( $url ) {
	$c = wp_remote_get( $url, array(
		'timeout'     => 25,
		'sslverify'   => false,
		'redirection' => 3,
		'cookies'     => array(),   /* cerezsiz */
		'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
		/* Onbellek ATLATILMAZ: ziyaretcinin gordugu kopya olculur. */
	) );
	if ( is_wp_error( $c ) ) { return array( 'hata' => $c->get_error_message() ); }

	return array(
		'kod'   => (int) wp_remote_retrieve_response_code( $c ),
		'html'  => (string) wp_remote_retrieve_body( $c ),
		'cache' => (string) wp_remote_retrieve_header( $c, 'x-litespeed-cache' ),
	);
}

/** Betik izleri için: satır içi base64 betikler de çözülerek eklenir. */
function gbc_nk_betik_govdesi( $html ) {
	$govde = $html;
	if ( preg_match_all( '#src=["\']data:text/javascript;base64,([^"\']+)["\']#i', $html, $m ) ) {
		foreach ( $m[1] as $b64 ) {
			$coz = base64_decode( $b64, true );
			if ( false !== $coz ) { $govde .= "\n" . $coz; }
		}
	}
	return $govde;
}

/**
 * CSS GÖVDESİ — LiteSpeed satır içi <style> bloklarını ve <link> dosyalarını
 * tek birleşik CSS dosyasında topluyor. Satır içi stil basan motorların izi
 * bu yüzden HTML'de görünmüyor; aynı alan adındaki stil dosyaları indirilip
 * gövdeye ekleniyor. Aynı dosya tur boyunca bir kez indirilir.
 */
function gbc_nk_css_govde( $html ) {
	static $onbellek = array();

	$govde = '';
	$alan  = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m ) ) {
		$sayac = 0;
		foreach ( array_unique( $m[1] ) as $url ) {
			if ( $sayac >= 4 ) { break; }
			$h = wp_parse_url( $url, PHP_URL_HOST );
			if ( $h && $h !== $alan ) { continue; }   /* Google Fonts vb. atlanir */
			$sayac++;
			if ( isset( $onbellek[ $url ] ) ) { $govde .= "\n" . $onbellek[ $url ]; continue; }
			$c = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => false ) );
			if ( is_wp_error( $c ) ) { $onbellek[ $url ] = ''; continue; }
			$onbellek[ $url ] = (string) wp_remote_retrieve_body( $c );
			$govde .= "\n" . $onbellek[ $url ];
		}
	}
	/* Satir ici <style> bloklari da girer. */
	if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $ms ) ) {
		$govde .= "\n" . implode( "\n", $ms[1] );
	}
	return $govde;
}

/**
 * OTOMATİK TABAN — sayfadaki bütün gbc-* / gz-* id ve sınıf adları.
 * Hiç tanımadığımız bir motor sussa bile adı kaybolduğu için yakalanır.
 */
/**
 * KOŞULLU İZLER — B5.
 *
 * Bu sınıflar sayfanın içeriğine göre BİLEREK bazen basılır bazen basılmaz.
 * Kaybolmaları arıza değildir, bu yüzden kayıp sayılmazlar:
 *
 *   gbc-yt-onyukle  videosu olmayan sayfada basılmaz (28 Eylül'de
 *                   muhallebili-biskuvili-pasta-tarifi'nde sahte
 *                   VideoObject şeması silindiği için kayboldu — doğru)
 *   gbc-toc, gbc-b/c/h/t/x   İçindekiler kutusu; v1-lejant düzeninde
 *                   bilerek basılmaz (DEFTER 34.6)
 *   gbc-kur-        canlı kur; sayfada yabancı tutar yoksa basılmaz
 *   gbc-vp-         video oynatıcı; videosu olmayan sayfada yok
 *   gbc-ad-         reklam; reklamsız sayfada yok
 *   gbc-gal-        galeri; galerisiz sayfada yok
 *   gbc-affk, gbc-ortaklik-  ortaklık bağlantısı olmayan sayfada yok
 *
 * Not: <style>, <link>, <script> tutamakları zaten toplanmıyor
 * (gbc_nk_izleri_topla, 28 Eylül 2026).
 */
function gbc_nk_kosullu_izler() {
	return (array) apply_filters( 'gbc_nk_kosullu_izler', array(
		'tam'  => array(
			'gbc-yt-onyukle', 'gbc-toc', 'gbc-b', 'gbc-c', 'gbc-h', 'gbc-t', 'gbc-x',
			'gbc-2', 'gbc-silo', 'gbc-related', 'gz2-alakali',
		),
		'onek' => array(
			'gbc-kur-', 'gbc-vp-', 'gbc-ad-', 'gbc-gal-', 'gbc-affk', 'gbc-ortaklik-',
			'gbc-related-', 'gbc-sor-', 'gbc-sale',
		),
	) );
}

/** Bir sınıf adı koşullu mu? */
function gbc_nk_kosullu_mu( $ad ) {
	$k = gbc_nk_kosullu_izler();
	if ( in_array( $ad, (array) $k['tam'], true ) ) { return true; }
	foreach ( (array) $k['onek'] as $o ) {
		if ( 0 === strpos( $ad, $o ) ) { return true; }
	}
	return false;
}

function gbc_nk_izleri_topla( $html ) {
	$bulunan = array();

	/* 28 Eylül 2026: <style>, <link>, <script> ve <noscript> etiketlerinin
	   id'leri SAYILMAZ. Bunlar içerik işareti değil, varlık tutamağı:
	   satır içi stil bir sayfada koşula bağlı basılıyor, LiteSpeed CSS
	   birleştirmesi de bunları toplayıp id'lerini siliyor. Sayınca
	   "gbc-kur-stil kayboldu" gibi yanlış alarmlar çıkıyordu. Gövdedeki
	   sınıf adları (asıl izler) olduğu gibi toplanmaya devam eder. */
	$govde = preg_replace( '#<(style|link|script|noscript)\b[^>]*>#i', '', (string) $html );
	if ( ! is_string( $govde ) ) { $govde = (string) $html; }

	if ( preg_match_all( '/\b(?:id|class)="([^"]*)"/i', $govde, $m ) ) {
		foreach ( $m[1] as $blok ) {
			foreach ( preg_split( '/\s+/', $blok ) as $ad ) {
				if ( '' !== $ad && preg_match( '/^(gbc|gz|gz2|v1|tr|fb|yn)-[a-z0-9-]+$/i', $ad ) ) {
					$bulunan[ $ad ] = true;
				}
			}
		}
	}
	$liste = array_keys( $bulunan );
	sort( $liste );
	return $liste;
}

/** JSON-LD düğüm tipleri. */
function gbc_nk_sema_tipleri( $dom ) {
	$tipler = array();
	$xp = new DOMXPath( $dom );
	foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$j = json_decode( trim( $s->textContent ), true );
		if ( ! is_array( $j ) ) { continue; }
		$dugumler = isset( $j['@graph'] ) && is_array( $j['@graph'] ) ? $j['@graph'] : array( $j );
		foreach ( $dugumler as $d ) {
			if ( empty( $d['@type'] ) ) { continue; }
			foreach ( (array) $d['@type'] as $t ) { $tipler[ $t ] = true; }
		}
	}
	return array_keys( $tipler );
}

/**
 * Sayfanın GÖRÜNÜR metninde yabancı para birimiyle yazılmış tutar var mı.
 * script ve style içeriği metne dahil edilmez. TL tek başına saymaz —
 * Canlı kur zaten yabancı tutarı TL'ye çevirmek için var.
 */
function gbc_nk_fiyat_var( $dom ) {
	$xp = new DOMXPath( $dom );
	foreach ( $xp->query( '//script | //style | //noscript' ) as $d ) {
		if ( $d->parentNode ) { $d->parentNode->removeChild( $d ); }
	}
	$govde = $dom->getElementsByTagName( 'body' )->item( 0 );
	$metin = $govde ? $govde->textContent : '';
	$metin = preg_replace( '/\s+/u', ' ', (string) $metin );

	/* Tutar once ya da sonra gelebilir: "120 €", "€120", "45 euro", "HUF 8000" */
	$birim = '(€|EUR|euro|\$|USD|dolar|forint|HUF|£|GBP|GEL|lari)';
	if ( preg_match( '/\d[\d\.,]*\s?' . $birim . '/iu', $metin ) ) { return true; }
	if ( preg_match( '/' . $birim . '\s?\d[\d\.,]*/iu', $metin ) ) { return true; }
	return false;
}

/* ============================================================
   TEK SAYFA TARAMASI
   ============================================================ */
function gbc_nk_tara_sayfa( $pid ) {
	$pid = (int) $pid;
	$url = get_permalink( $pid );
	$icerik = (string) get_post_field( 'post_content', $pid );

	$d = gbc_nk_indir( $url );
	if ( ! empty( $d['hata'] ) ) {
		return array( 'pid' => $pid, 'url' => $url, 'hata' => $d['hata'], 'zaman' => time(), 'ks' => GBC_NK_KURAL_SURUM );
	}

	$html  = $d['html'];
	$betik = gbc_nk_betik_govdesi( $html );
	$css   = gbc_nk_css_govde( $html );

	$onceki = libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $onceki );
	$xp = new DOMXPath( $dom );

	$sablon = function_exists( 'gbc_env_sablon' ) ? gbc_env_sablon( $icerik ) : '—';
	$ana_sayfa_mi = ( (int) get_option( 'page_on_front' ) === (int) $pid );
	$semalar = gbc_nk_sema_tipleri( $dom );

	/* Kosullar — sayfanin kendi ozelliginden okunur */
	$aff_adet = $xp->query( gbc_nk_xpath( 'a[data-aff]' ) )->length;
	if ( ! $aff_adet ) {
		/* data-aff yoksa bilinen ortaklik alanlarina bak */
		foreach ( (array) ( function_exists( 'gbc_seo_ortaklik_alanlari' ) ? gbc_seo_ortaklik_alanlari() : array() ) as $al ) {
			if ( false !== stripos( $html, $al ) ) { $aff_adet++; }
		}
	}
	$kosullar = array(
		'ortaklik_var' => ( $aff_adet > 0 ),
		'fiyat_var'    => gbc_nk_fiyat_var( $dom ),
		/* B7 — Uçuş perdesi yalnız Skyscanner widget'ı OLAN sayfada beklenir.
		   29 Eylül 2026'da ölçüldü: widgets.skyscanner.net/widget-server/js/loader.js
		   artık hiçbir sayfada YOK; widget şablondan kaldırılmış. Perde bozuk
		   değil, yapacak işi kalmamış. Widget geri gelirse koşul sağlanır ve
		   motor yeniden denetlenir. */
		'sky_widget'   => ( false !== stripos( $html, 'widgets.skyscanner.net' ) ),
	);

	$sonuc = array(
		'pid'    => $pid,
		'url'    => $url,
		'yol'    => wp_parse_url( $url, PHP_URL_PATH ),
		'baslik' => get_the_title( $pid ),
		'sablon' => $sablon,
		'kod'    => (int) $d['kod'],
		'cache'  => $d['cache'],
		'aff'    => (int) $aff_adet,
		'bayt'   => strlen( $html ),
		'izler'  => gbc_nk_izleri_topla( $html ),
		'zaman'  => time(),
		'ks'     => GBC_NK_KURAL_SURUM,   /* hangi kural setiyle tarandi */
		'motor'  => array(),
	);

	foreach ( gbc_nk_motorlar() as $a => $m ) {
		if ( 'sunucu' === $m['kapsam'] ) { continue; }  /* kural 5 */

		/* 1) Beklenen mi */
		$beklenen = false;
		switch ( $m['kapsam'] ) {
			case 'hepsi':     $beklenen = true; break;
			case 'ana_haric': $beklenen = ! $ana_sayfa_mi; break;
			case 'sablon':    $beklenen = in_array( $sablon, (array) $m['sablon'], true ); break;
			case 'kosul':     $beklenen = ! empty( $kosullar[ $m['kosul'] ] ); break;
			case 'sayilir':   $beklenen = null; break;  /* zorunlu degil */
			case 'na':        $beklenen = null; break;  /* kural yazilmadi: olculur, sorun sayilmaz */
		}
		if ( ! empty( $m['ters'] ) ) { $beklenen = false; }  /* olmamali */
		if ( ! empty( $m['ana_disi'] ) && $ana_sayfa_mi ) { $beklenen = false; }

		/* Sayfaya özel istisna: motor bu sayfada bilerek çalışmıyor.
		   "eksik" alarmı yerine "gerekmez" hükmü verilir. */
		if ( ! empty( $m['istisna_desen'] ) && preg_match( $m['istisna_desen'], $html ) ) {
			$beklenen = false;
		}

		/* 2) Bulundu mu */
		$bulundu = false;
		if ( 'dom' === $m['iz'] ) {
			if ( ! empty( $m['xpath'] ) ) {
				$yol = $m['xpath'];
			} else {
				$sec = $m['secici'];
				if ( ! empty( $m['secici_sablon'][ $sablon ] ) ) { $sec = $m['secici_sablon'][ $sablon ]; }
				$yol = gbc_nk_xpath( $sec );
			}
			$bulundu = ( $xp->query( $yol )->length > 0 );
		} elseif ( 'ham' === $m['iz'] ) {
			/* Kural 2 istisnasi: LiteSpeed'in tasidigi ya da donusturdugu
			   etiketler DOM'da element olarak yakalanamiyor. */
			$bulundu = (bool) preg_match( $m['desen'], $html );
		} elseif ( 'betik' === $m['iz'] ) {
			$bulundu = ( false !== strpos( $betik, $m['desen'] ) );
		} elseif ( 'css' === $m['iz'] ) {
			/* Iz birlesik CSS dosyasinda; HTML'de de olabilir. */
			$bulundu = ( false !== strpos( $css, $m['desen'] ) || false !== strpos( $html, $m['desen'] ) );
		} elseif ( 'sema' === $m['iz'] ) {
			$bulundu = ! empty( $m['desen'] ) ? in_array( $m['desen'], $semalar, true ) : ( ! empty( $semalar ) );
		}

		/* 3) Durum */
		if ( 'na' === $m['kapsam'] ) {
			$durum = $bulundu ? 'na_var' : 'na_yok';   /* kural bekliyor: sorun degil */
		} elseif ( null === $beklenen ) {
			$durum = $bulundu ? 'var' : 'yok';   /* yalnizca sayilir */
		} elseif ( $beklenen && $bulundu )  { $durum = 'ok'; }
		elseif ( $beklenen && ! $bulundu )  { $durum = 'eksik'; }
		elseif ( ! $beklenen && $bulundu )  { $durum = 'fazla'; }
		else                                { $durum = 'gerekmez'; }

		$sonuc['motor'][ $a ] = $durum;
	}

	return $sonuc;
}

/* ============================================================
   YENİDEN TARANACAK — kural değişince eski satırlar geçersizdir
   ============================================================ */
/**
 * Kayıtlı sonuç bugünkü kurallarla mı üretilmiş?
 * Hayırsa satır "yeniden taranacak" sayılır: tabloda işaretlenir ve
 * sonraki turlarda sıranın başına geçer.
 */
/**
 * "Yeniden taranacak" satır sayısı — B3.
 *
 * Tek yerden ve HER SEFERINDE canlı hesaplanır. Eski kodda sayı hem
 * turun raporundan hem kayıtlardan okunuyordu; "Tümünü yeniden tara"ya
 * basınca rapordaki eski sayı ekranda kalıyor, kullanıcı düğme
 * çalışmadı sanıyordu.
 */
function gbc_nk_bekleyen_sayisi() {
	return count( gbc_nk_eskimis_kodlar() );
}

/** Son turun zamanı — öz denetim (B8) buna bakar. */
function gbc_nk_son_tur() {
	$d = get_option( GBC_NK_DURUM );
	return ( is_array( $d ) && ! empty( $d['zaman'] ) ) ? (int) $d['zaman'] : 0;
}

function gbc_nk_eskimis( $k ) {
	if ( ! is_array( $k ) ) { return true; }
	if ( (int) ( isset( $k['ks'] ) ? $k['ks'] : 0 ) < GBC_NK_KURAL_SURUM ) { return true; }
	$zorla = (int) get_option( GBC_NK_ZORLA, 0 );
	if ( $zorla && (int) ( isset( $k['zaman'] ) ? $k['zaman'] : 0 ) < $zorla ) { return true; }
	return false;
}

/** Yeniden taranacak sayfaların kodları — en eski denetim önce. */
function gbc_nk_eskimis_kodlar( $limit = 1000 ) {
	global $wpdb;
	$satirlar = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
		GBC_NK_META, (int) $limit
	), ARRAY_A );

	$liste = array();
	foreach ( $satirlar as $s ) {
		$v = maybe_unserialize( $s['meta_value'] );
		if ( gbc_nk_eskimis( $v ) ) {
			$liste[ (int) $s['post_id'] ] = is_array( $v ) && ! empty( $v['zaman'] ) ? (int) $v['zaman'] : 0;
		}
	}
	asort( $liste );
	return array_keys( $liste );
}

/* ============================================================
   SUNUCU KONTROLÜ — sayfa taramasıyla ölçülemeyen motor
   ============================================================ */
function gbc_nk_sunucu_kontrol() {
	$yukleme = wp_upload_dir();
	$klasor  = trailingslashit( $yukleme['basedir'] ) . 'gbc-css';
	$adres   = trailingslashit( $yukleme['baseurl'] ) . 'gbc-css';

	$d = array( 'klasor' => $klasor, 'dosya' => array(), 'var' => false );
	if ( ! is_dir( $klasor ) ) {
		$d['not'] = __( 'uploads/gbc-css klasörü yok.', 'gbc-core' );
		return $d;
	}
	$d['var'] = true;

	$liste = glob( $klasor . '/*.css' );
	foreach ( (array) array_slice( (array) $liste, 0, 10 ) as $yol ) {
		$ad = basename( $yol );
		$c  = wp_remote_head( $adres . '/' . $ad, array( 'timeout' => 10, 'sslverify' => false ) );
		$d['dosya'][] = array(
			'ad'   => $ad,
			'bayt' => (int) filesize( $yol ),
			'kod'  => is_wp_error( $c ) ? 0 : (int) wp_remote_retrieve_response_code( $c ),
		);
	}
	$d['adet'] = is_array( $liste ) ? count( $liste ) : 0;
	return $d;
}

/* ============================================================
   TUR — birkaç sayfa tara, karşılaştır, alarm üret
   ============================================================ */
/**
 * Tur sırası — her turda SEKİZ şablondan en az birer sayfa.
 * İlk tur yalnız Gezi ve Liste taramıştı; artık her şablon her turda
 * temsil ediliyor, boşta kalan kontenjan en eski denetimlere gidiyor.
 */
function gbc_nk_sablon_kodlari() {
	return array(
		'Gezi'   => array( 22607, 22608 ),
		'Liste'  => array( 23108 ),
		'Detay'  => array( 23489 ),
		'Rota'   => array( 23340 ),
		'Tarif'  => array( 24751 ),
		'Blog'   => array( 26922 ),
		'Sözlük' => array( 24156 ),
	);
}

/** Bir şablondan, denetimi en eski (ya da hiç denetlenmemiş) bir sayfa. */
function gbc_nk_sablondan_bir( $kodlar, $haric ) {
	global $wpdb;

	$parca = array();
	foreach ( (array) $kodlar as $kod ) {
		$parca[] = $wpdb->prepare( 'p.post_content LIKE %s', '%[wpcode id="' . (int) $kod . '"%' );
	}
	$kosul = '(' . implode( ' OR ', $parca ) . ')';

	$disi = '';
	if ( $haric ) {
		$disi = ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $haric ) ) . ')';
	}

	/* Once hic denetlenmemis */
	$id = $wpdb->get_var(
		"SELECT p.ID FROM {$wpdb->posts} p
		  LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '" . GBC_NK_META . "'
		  WHERE p.post_status='publish' AND p.post_type IN ('post','page')
		    AND {$kosul}{$disi} AND m.meta_id IS NULL
		  ORDER BY p.post_modified DESC LIMIT 1"
	);
	if ( $id ) { return (int) $id; }

	/* Sonra denetimi en eski olan */
	$id = $wpdb->get_var(
		"SELECT p.ID FROM {$wpdb->posts} p
		  INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '" . GBC_NK_META . "'
		  WHERE p.post_status='publish' AND p.post_type IN ('post','page')
		    AND {$kosul}{$disi}
		  ORDER BY m.meta_id ASC LIMIT 1"
	);
	return $id ? (int) $id : 0;
}

/**
 * Açık sorunu olan sayfaların kodları — B2.
 *
 * Sorun kimlikleri nk-<motor>-<pid>, nk-iz-<pid>, nk-bayt-<pid> biçiminde.
 * Sondaki sayı sayfa kodu. Bu sayfalar sıranın en başına geçer: bir sorun
 * düzeldiyse bunu ilk öğrenmemiz gereken yer orası.
 */
function gbc_nk_sorunlu_kodlar() {
	if ( ! function_exists( 'gbc_sorunlar' ) ) { return array(); }
	$out = array();
	foreach ( gbc_sorunlar() as $id => $x ) {
		if ( 0 !== strpos( $id, 'nk-' ) ) { continue; }
		if ( preg_match( '/-(\d+)$/', $id, $m ) ) { $out[ (int) $m[1] ] = true; }
	}
	return array_keys( $out );
}

function gbc_nk_sira_al( $adet ) {
	$secilen = array();

	/* 1) Ana sayfa — her turda */
	$ana = (int) get_option( 'page_on_front' );
	if ( $ana ) { $secilen[] = $ana; }

	/* 1b) AÇIK SORUNU OLAN SAYFALAR — B2. Sorun düzeldiyse en çabuk
	       burada anlaşılır; yanlış alarmlar da en hızlı böyle kapanır. */
	foreach ( gbc_nk_sorunlu_kodlar() as $pid ) {
		if ( count( $secilen ) >= $adet ) { break; }
		if ( ! in_array( (int) $pid, $secilen, true ) ) { $secilen[] = (int) $pid; }
	}

	/* 2) YENİDEN TARANACAKLAR — kural değişmişse sıranın başı onlarındır */
	foreach ( gbc_nk_eskimis_kodlar() as $pid ) {
		if ( count( $secilen ) >= $adet ) { break; }
		if ( ! in_array( (int) $pid, $secilen, true ) ) { $secilen[] = (int) $pid; }
	}

	/* 3) Yedi şablondan birer sayfa */
	foreach ( gbc_nk_sablon_kodlari() as $kodlar ) {
		if ( count( $secilen ) >= $adet ) { break; }
		$id = gbc_nk_sablondan_bir( $kodlar, $secilen );
		if ( $id ) { $secilen[] = $id; }
	}

	/* 4) Kalan kontenjan: hiç denetlenmemişler, sonra en eskiler */
	if ( count( $secilen ) < $adet ) {
		$hic = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $adet - count( $secilen ),
			'fields'         => 'ids',
			'post__not_in'   => $secilen,
			'meta_query'     => array( array( 'key' => GBC_NK_META, 'compare' => 'NOT EXISTS' ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		foreach ( (array) $hic as $p ) { $secilen[] = (int) $p; }
	}
	if ( count( $secilen ) < $adet ) {
		$eski = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $adet - count( $secilen ),
			'fields'         => 'ids',
			'post__not_in'   => $secilen,
			'meta_key'       => GBC_NK_META,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		) );
		foreach ( (array) $eski as $p ) { $secilen[] = (int) $p; }
	}

	return array_values( array_unique( array_filter( $secilen ) ) );
}

function gbc_nk_tur( $adet = null ) {
	$adet = $adet ? (int) $adet : GBC_NK_ADET;
	if ( function_exists( 'gbc_kilit_al' ) && ! gbc_kilit_al( 'kontrol-merkezi' ) ) {
		return array( 'zaman' => time(), 'sayfa' => 0, 'sorun' => 0, 'dusen' => 0, 'kayip' => 0, 'kucuk' => 0, 'kod' => 0, 'ks' => GBC_NK_KURAL_SURUM, 'kapanan' => 0, 'atlandi' => 1 );
	}
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
	ignore_user_abort( true );

	$bas = microtime( true );
	$rapor = array( 'zaman' => time(), 'sayfa' => 0, 'sorun' => 0, 'dusen' => 0,
		'kayip' => 0, 'kucuk' => 0, 'kod' => 0, 'ks' => GBC_NK_KURAL_SURUM );

	$rapor['kapanan'] = 0;

	foreach ( gbc_nk_sira_al( $adet ) as $pid ) {
		$onceki = get_post_meta( $pid, GBC_NK_META, true );
		$yeni   = gbc_nk_tara_sayfa( $pid );

		/* B1: bu sayfa için bu turda AÇIK KALMASI GEREKEN sorunlar.
		   Tur sonunda bu listede olmayan nk-*-<pid> sorunları kapanır.
		   Sayfa bazlı olduğu için taranmamış sayfaların sorunlarına
		   dokunulmaz. */
		$sayfa_bulunan = array();

		if ( empty( $yeni['hata'] ) ) {
			/* Kural 7: dun ✓ bugun ✗ olan her motor ayri alarm */
			if ( is_array( $onceki ) && ! empty( $onceki['motor'] ) ) {
				foreach ( $yeni['motor'] as $a => $durum ) {
					$eski_durum = isset( $onceki['motor'][ $a ] ) ? $onceki['motor'][ $a ] : '';
					if ( 'ok' === $eski_durum && 'eksik' === $durum ) {
						$rapor['dusen']++;
						$sayfa_bulunan[] = sanitize_key( 'nk-' . $a . '-' . $pid );
						if ( function_exists( 'gbc_sorun_ac' ) ) {
							$mm = gbc_nk_motorlar();
							gbc_sorun_ac(
								'nk-' . $a . '-' . $pid,
								'nobetci',
								sprintf( __( '%1$s durdu: %2$s', 'gbc-core' ), $mm[ $a ]['ad'], $yeni['baslik'] ),
								sprintf( __( 'Dün çalışıyordu, bugün sayfada yok. %s', 'gbc-core' ), $yeni['yol'] ),
								'yuksek',
								admin_url( 'admin.php?page=gbc-nobetci-kural&pid=' . $pid )
							);
						}
					}
				}
			}
			foreach ( $yeni['motor'] as $a => $durum ) {
				if ( 'eksik' === $durum || 'fazla' === $durum ) { $rapor['sorun']++; }
				/* Hâlâ eksikse sorunu açık tut — "dün ✓ bugün ✗" anı geçmiş olsa bile. */
				if ( 'eksik' === $durum ) { $sayfa_bulunan[] = sanitize_key( 'nk-' . $a . '-' . $pid ); }
			}

			/* OTOMATİK TABAN — dün duran bir sınıf adı bugün yoksa, o motoru
			   hiç tanımıyor olsak bile alarm. */
			if ( is_array( $onceki ) && ! empty( $onceki['izler'] ) && ! empty( $yeni['izler'] ) ) {
				$ham_kayip = array_values( array_diff( (array) $onceki['izler'], (array) $yeni['izler'] ) );

				/* B5: koşullu basılan öğeler kayıp sayılmaz. */
				$kayip = array();
				foreach ( $ham_kayip as $ad ) {
					if ( ! gbc_nk_kosullu_mu( $ad ) ) { $kayip[] = $ad; }
				}

				/* B5: sınıf kaybı TEK BAŞINA alarm değil. Aynı sayfada bir
				   motor da sustuysa alarm açılır; susmadıysa yalnız sayıya
				   girer, deftere yazılmaz. */
				$motor_susmus = false;
				foreach ( (array) $yeni['motor'] as $__d ) {
					if ( 'eksik' === $__d ) { $motor_susmus = true; break; }
				}

				if ( count( $kayip ) > 2 ) {
					$rapor['kayip'] += count( $kayip );
					if ( $motor_susmus ) {
						$sayfa_bulunan[] = sanitize_key( 'nk-iz-' . $pid );
						if ( function_exists( 'gbc_sorun_ac' ) ) {
							gbc_sorun_ac(
								'nk-iz-' . $pid, 'nobetci',
								sprintf( __( '%1$d sınıf adı sayfadan kayboldu: %2$s', 'gbc-core' ),
									count( $kayip ), $yeni['baslik'] ),
								implode( ', ', array_slice( $kayip, 0, 14 ) ) . ' — ' . $yeni['yol']
									. ' ' . __( '(aynı sayfada bir motor da susmuş)', 'gbc-core' ),
								'orta',
								admin_url( 'admin.php?page=gbc-nobetci-kural&pid=' . $pid )
							);
						}
					}
				}
				$yeni['kayip_bilgi'] = $kayip;
			}

			/* Sayfa boyutu tabanının %35 altına düştüyse bir şey basılmıyor.
			   28 Eylül 2026: küçülme TEK BAŞINA arıza değil — sıkıştırma,
			   önbellek durumu ve bizim kendi optimizasyonlarımız da sayfayı
			   küçültüyor. Alarm yalnızca küçülmeye BİR MOTORUN SUSMASI ya da
			   sınıf kaybı eşlik ediyorsa açılır; yoksa taban sessizce yenilenir. */
			$motor_sorunlu = false;
			foreach ( (array) $yeni['motor'] as $__d ) {
				if ( 'eksik' === $__d ) { $motor_sorunlu = true; break; }
			}
			$iz_kaybi = ( is_array( $onceki ) && ! empty( $onceki['izler'] ) && ! empty( $yeni['izler'] ) )
				? count( array_diff( (array) $onceki['izler'], (array) $yeni['izler'] ) ) : 0;

			if ( is_array( $onceki ) && ! empty( $onceki['bayt'] ) && ! empty( $yeni['bayt'] )
				&& $yeni['bayt'] < $onceki['bayt'] * 0.65
				&& ( $motor_sorunlu || $iz_kaybi > 2 ) ) {
				$rapor['kucuk']++;
				$sayfa_bulunan[] = sanitize_key( 'nk-bayt-' . $pid );
				if ( function_exists( 'gbc_sorun_ac' ) ) {
					gbc_sorun_ac(
						'nk-bayt-' . $pid, 'nobetci',
						sprintf( __( 'Sayfa küçüldü: %s', 'gbc-core' ), $yeni['baslik'] ),
						sprintf( __( 'Önce %1$s, şimdi %2$s. Bir motor çıktı basmıyor olabilir. %3$s', 'gbc-core' ),
							size_format( (int) $onceki['bayt'] ), size_format( (int) $yeni['bayt'] ), $yeni['yol'] ),
						'yuksek',
						admin_url( 'admin.php?page=gbc-nobetci-kural&pid=' . $pid )
					);
				}
			}
		}

		update_post_meta( $pid, GBC_NK_META, $yeni );

		/* B1 — SAYFA BAZLI TEMİZLİK. Sayfa hatasız tarandıysa, bu turda
		   açık kalması gerekmeyen nk-*-<pid> sorunları kapanır. Sayfa
		   indirilemediyse hiçbir şey kapatılmaz: bilgi yok demek, sorun
		   yok demek değildir. */
		if ( empty( $yeni['hata'] ) && function_exists( 'gbc_sorun_sayfa_temizle' ) ) {
			$rapor['kapanan'] += gbc_sorun_sayfa_temizle( $pid, $sayfa_bulunan );
		}

		$rapor['sayfa']++;
	}

	/* KOD SAĞLIĞI — her turda PHP tarafı da denetlenir. */
	if ( function_exists( 'gbc_kod_tara' ) ) {
		$kod = gbc_kod_tara( true );
		$rapor['kod'] = (int) $kod['sorun'];
	}

	$rapor['ms']       = (int) round( ( microtime( true ) - $bas ) * 1000 );
	$rapor['bekleyen'] = count( gbc_nk_eskimis_kodlar() );
	update_option( GBC_NK_DURUM, $rapor, false );
	return $rapor;
}

add_action( 'gbc_nk_tur', 'gbc_nk_tur' );
add_action( 'init', 'gbc_nk_cron_kur' );
function gbc_nk_cron_kur() {
	if ( ! wp_next_scheduled( 'gbc_nk_tur' ) ) {
		wp_schedule_event( time() + 900, 'hourly', 'gbc_nk_tur' );
	}
}

/* ============================================================
   KAYITLI SONUÇLAR
   ============================================================ */
function gbc_nk_kayitlar( $limit = 500 ) {
	global $wpdb;
	$satirlar = (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d", GBC_NK_META, (int) $limit
	), ARRAY_A );

	$liste = array();
	foreach ( $satirlar as $s ) {
		$v = maybe_unserialize( $s['meta_value'] );
		if ( is_array( $v ) ) { $liste[] = $v; }
	}
	return $liste;
}

/* ============================================================
   EKRAN
   ============================================================ */
function gbc_nk_rozet( $durum ) {
	$harita = array(
		'ok'       => array( '✓', '#1A7F37', __( 'Çalışıyor', 'gbc-core' ) ),
		'eksik'    => array( '✗', '#B3261E', __( 'EKSİK', 'gbc-core' ) ),
		'fazla'    => array( '!', '#8A6100', __( 'FAZLA', 'gbc-core' ) ),
		'gerekmez' => array( '–', '#B9BDC5', __( 'Gerekmez', 'gbc-core' ) ),
		'var'      => array( '•', '#0B5B55', __( 'Var', 'gbc-core' ) ),
		'yok'      => array( '·', '#B9BDC5', __( 'Yok', 'gbc-core' ) ),
		'na_var'   => array( '◉', '#5C6470', __( 'Var — kural bekliyor (N/A)', 'gbc-core' ) ),
		'na_yok'   => array( '○', '#C7CBD1', __( 'Yok — kural bekliyor (N/A)', 'gbc-core' ) ),
	);
	$x = isset( $harita[ $durum ] ) ? $harita[ $durum ] : array( '?', '#B9BDC5', '' );
	return '<span title="' . esc_attr( $x[2] ) . '" style="color:' . esc_attr( $x[1] ) . ';font-weight:700;font-size:15px">' . esc_html( $x[0] ) . '</span>';
}

function gbc_nk_ekran() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$motorlar = gbc_nk_motorlar();
	$sayfa_motor = array();
	foreach ( $motorlar as $a => $m ) { if ( 'sunucu' !== $m['kapsam'] ) { $sayfa_motor[ $a ] = $m; } }

	/* CSV */
	if ( isset( $_GET['csv'] ) && check_admin_referer( 'gbc_nk' ) ) {
		$kayit = gbc_nk_kayitlar( 2000 );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=gbc-nobetci-' . gmdate( 'Y-m-d' ) . '.csv' );
		$cikti = fopen( 'php://output', 'w' );
		$bas = array( 'Adres', 'Baslik', 'Sablon', 'HTTP', 'Tarama' );
		foreach ( $sayfa_motor as $m ) { $bas[] = $m['ad']; }
		fputcsv( $cikti, $bas );
		foreach ( $kayit as $k ) {
			$s = array( $k['yol'], $k['baslik'], $k['sablon'], isset( $k['kod'] ) ? $k['kod'] : '',
				gbc_nk_eskimis( $k ) ? 'yeniden taranacak' : 'guncel' );
			foreach ( array_keys( $sayfa_motor ) as $a ) {
				$s[] = isset( $k['motor'][ $a ] ) ? $k['motor'][ $a ] : '';
			}
			fputcsv( $cikti, $s );
		}
		fclose( $cikti );
		exit;
	}

	if ( isset( $_GET['gbc_nk_hepsi'] ) && check_admin_referer( 'gbc_nk' ) ) {
		update_option( GBC_NK_ZORLA, time(), false );
		wp_cache_delete( GBC_NK_ZORLA, 'options' );
		$isaretli = gbc_nk_bekleyen_sayisi();
		echo '<div class="notice notice-success"><p>'
			. esc_html( sprintf(
				/* translators: %d: işaretlenen satır sayısı */
				__( '%d satır “yeniden taranacak” diye işaretlendi. Turlar sıraya onlardan başlayacak; hemen başlatmak için “Şimdi tara”ya bas.', 'gbc-core' ),
				$isaretli ) )
			. '</p></div>';
	}

	if ( isset( $_GET['gbc_nk_tur'] ) && check_admin_referer( 'gbc_nk' ) ) {
		$r = gbc_nk_tur();
		echo '<div class="notice notice-success"><p>'
			. esc_html( sprintf( __( '%1$d sayfa tarandı, %2$d saniye. Sorunlu motor: %3$d. Dün çalışıp bugün duran: %4$d.', 'gbc-core' ),
				$r['sayfa'], (int) round( $r['ms'] / 1000 ), $r['sorun'], $r['dusen'] ) ) . '</p></div>';
	}

	$sz_motor  = isset( $_GET['m'] ) ? sanitize_key( $_GET['m'] ) : '';
	$sz_sablon = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$yalniz    = isset( $_GET['sorun'] ) && '1' === $_GET['sorun'];

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC Kontrol Merkezi', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-nobetci-kural' ); }

	echo '<div style="background:#FDF3E0;border:1px solid #F0DCD1;border-radius:10px;padding:11px 14px;max-width:1000px;margin:12px 0;font-size:13px">'
		. esc_html__( 'Tablodaki satırlar SON TARAMANIN sonucudur. Kural değiştiğinde eski satırlar “yeniden taranacak” diye işaretlenir ve turlar sıraya onlardan başlar; o satırların sonucu tazelenene kadar güvenilmez.', 'gbc-core' )
		. '</div>';
	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'İki katman tek ekranda: önce PHP tarafı (dosya, sözdizimi, yükleme, imza fonksiyonu, kısa kod, cron), sonra canlı sayfa taraması. Sayfa, ziyaretçinin gördüğü hâliyle (çerezsiz, oturumsuz, önbellekli) indirilir; iz motorun gerçek çıktısında aranır. LiteSpeed’in data: URI’ye çevirdiği betikler çözülür, birleşik CSS dosyası indirilip gövdeye katılır.', 'gbc-core' )
		. '</p>';
	echo '<p style="max-width:1000px;color:#444">'
		. esc_html__( 'Kuralı henüz yazılmamış motorlar taramadan çıkarılmaz: ölçülür ama sorun sayılmaz, N/A (◉ var · ○ yok) diye işaretlenir. Aşağıdaki “Karar bekleyen motorlar” tablosu bunların hangi sayfalarda çıktığını gösterir — kural o kanıta bakılarak yazılır.', 'gbc-core' )
		. '</p>';

	$durum = get_option( GBC_NK_DURUM, array() );
	$kayit = gbc_nk_kayitlar( 800 );

	/* Ozet */
	$toplam_sorun = 0; $sayfali_sorun = 0; $bekleyen = 0;
	foreach ( $kayit as $k ) {
		$s = 0;
		foreach ( (array) $k['motor'] as $d ) { if ( 'eksik' === $d || 'fazla' === $d ) { $s++; } }
		if ( $s ) { $sayfali_sorun++; }
		$toplam_sorun += $s;
		if ( gbc_nk_eskimis( $k ) ) { $bekleyen++; }
	}
	/* B3: tabloda görünen 800 satırla sınırlı kalmasın, canlı say. */
	$bekleyen = gbc_nk_bekleyen_sayisi();

	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">';
	echo gbc_seo_kart( __( 'Taranan sayfa', 'gbc-core' ), number_format_i18n( count( $kayit ) ), '' );
	echo gbc_seo_kart( __( 'Sorunlu sayfa', 'gbc-core' ), number_format_i18n( $sayfali_sorun ), '',
		$sayfali_sorun ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Sorunlu motor', 'gbc-core' ), number_format_i18n( $toplam_sorun ),
		__( 'eksik + fazla', 'gbc-core' ), $toplam_sorun ? '#B3261E' : '#1A7F37' );
	$kod_rapor = function_exists( 'gbc_kod_tara' ) ? gbc_kod_tara() : array( 'sorun' => 0, 'uyari' => 0 );
	echo gbc_seo_kart( __( 'Kod sorunu', 'gbc-core' ), number_format_i18n( (int) $kod_rapor['sorun'] ),
		sprintf( __( '%d dikkat', 'gbc-core' ), (int) $kod_rapor['uyari'] ),
		! empty( $kod_rapor['sorun'] ) ? '#B3261E' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Yeniden taranacak', 'gbc-core' ), number_format_i18n( $bekleyen ),
		__( 'kural değişti', 'gbc-core' ), $bekleyen ? '#8A6100' : '#1A7F37' );
	echo gbc_seo_kart( __( 'Son tur', 'gbc-core' ),
		! empty( $durum['zaman'] ) ? human_time_diff( (int) $durum['zaman'] ) : __( 'hiç', 'gbc-core' ),
		! empty( $durum['zaman'] ) ? __( 'önce', 'gbc-core' ) : '' );
	echo '</div>';

	$u = static function ( $ek = '' ) { return wp_nonce_url( admin_url( 'admin.php?page=gbc-nobetci-kural' . $ek ), 'gbc_nk' ); };
	echo '<p><a class="button button-primary" href="' . esc_url( $u( '&gbc_nk_tur=1' ) ) . '">'
		. esc_html( sprintf( __( 'Şimdi tara (%d sayfa)', 'gbc-core' ), GBC_NK_ADET ) ) . '</a> ';
	echo '<a class="button" href="' . esc_url( $u( '&gbc_nk_hepsi=1' ) ) . '">'
		. esc_html__( 'Tümünü yeniden tara', 'gbc-core' ) . '</a> ';
	echo '<a class="button" href="' . esc_url( $u( '&csv=1' ) ) . '">' . esc_html__( 'CSV indir', 'gbc-core' ) . '</a></p>';

    /* Suzgec */
	echo '<form method="get" style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:12px 16px;margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
	echo '<input type="hidden" name="page" value="gbc-nobetci-kural">';
	echo '<label for="nk_m" style="font-weight:600">' . esc_html__( 'Motor', 'gbc-core' ) . '</label>';
	echo '<select id="nk_m" name="m"><option value="">' . esc_html__( 'Hepsi', 'gbc-core' ) . '</option>';
	foreach ( $sayfa_motor as $a => $m ) {
		echo '<option value="' . esc_attr( $a ) . '"' . selected( $sz_motor, $a, false ) . '>' . esc_html( $m['ad'] ) . '</option>';
	}
	echo '</select>';
	echo '<label for="nk_s" style="font-weight:600">' . esc_html__( 'Şablon', 'gbc-core' ) . '</label>';
	echo '<select id="nk_s" name="s"><option value="">' . esc_html__( 'Hepsi', 'gbc-core' ) . '</option>';
	foreach ( array( 'Gezi', 'Liste', 'Detay', 'Rota', 'Tarif', 'Blog', 'Sözlük', '—' ) as $sb ) {
		echo '<option value="' . esc_attr( $sb ) . '"' . selected( $sz_sablon, $sb, false ) . '>' . esc_html( $sb ) . '</option>';
	}
	echo '</select>';
	echo '<label style="font-weight:600"><input type="checkbox" name="sorun" value="1"' . checked( $yalniz, true, false ) . '> '
		. esc_html__( 'yalnız sorunlular', 'gbc-core' ) . '</label>';
	echo ' <button class="button button-primary">' . esc_html__( 'Süz', 'gbc-core' ) . '</button>';
	echo '</form>';

	/* Tablo */
	echo '<div style="overflow-x:auto">';
	echo '<table class="widefat striped" style="min-width:1200px"><thead><tr>'
		. '<th style="min-width:280px">' . esc_html__( 'Adres', 'gbc-core' ) . '</th>'
		. '<th style="width:96px">' . esc_html__( 'Şablon · yaş', 'gbc-core' ) . '</th>';
	foreach ( $sayfa_motor as $a => $m ) {
		echo '<th style="width:56px;text-align:center" title="' . esc_attr( $m['ad'] . ' · ' . $m['kaynak'] ) . '">'
			. esc_html( mb_substr( $m['ad'], 0, 9 ) ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	$yazildi = 0;
	foreach ( $kayit as $k ) {
		if ( $sz_sablon && $k['sablon'] !== $sz_sablon ) { continue; }
		$sorunlu = false;
		foreach ( (array) $k['motor'] as $a => $d ) {
			if ( 'eksik' === $d || 'fazla' === $d ) {
				if ( ! $sz_motor || $sz_motor === $a ) { $sorunlu = true; }
			}
		}
		if ( $yalniz && ! $sorunlu ) { continue; }
		if ( $yazildi++ >= 120 ) { break; }

		echo '<tr' . ( $sorunlu ? ' style="background:#FDF6F2"' : '' ) . '>';
		echo '<td><a href="' . esc_url( $k['url'] ) . '" target="_blank">' . esc_html( $k['yol'] ) . '</a>'
			. '<div style="color:#5C6470;font-size:12px">' . esc_html( $k['baslik'] ) . '</div></td>';
		echo '<td>' . esc_html( $k['sablon'] );
		if ( gbc_nk_eskimis( $k ) ) {
			echo '<div style="color:#8A6100;font-size:11px;font-weight:700" title="'
				. esc_attr__( 'Bu satır eski kurallarla tarandı; sıradaki turda yenilenecek.', 'gbc-core' ) . '">'
				. esc_html__( 'yeniden taranacak', 'gbc-core' ) . '</div>';
		} else {
			echo '<div style="color:#8A919C;font-size:11px">' . esc_html( human_time_diff( (int) $k['zaman'] ) ) . '</div>';
		}
		echo '</td>';
		foreach ( array_keys( $sayfa_motor ) as $a ) {
			$d = isset( $k['motor'][ $a ] ) ? $k['motor'][ $a ] : '';
			echo '<td style="text-align:center">' . gbc_nk_rozet( $d ) . '</td>';
		}
		echo '</tr>';
	}
	if ( ! $yazildi ) {
		echo '<tr><td colspan="' . ( count( $sayfa_motor ) + 2 ) . '"><em>'
			. esc_html__( 'Kayıt yok. "Şimdi tara" ile başla; tur saat başı kendiliğinden de ilerliyor.', 'gbc-core' ) . '</em></td></tr>';
	}
	echo '</tbody></table></div>';

	/* Motor haritasi */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Motor haritası — nerede olmalı', 'gbc-core' ) . '</h2>';
	$kapsam_ad = array(
		'hepsi'     => __( 'Her yazı ve sayfada', 'gbc-core' ),
		'ana_haric' => __( 'Ana sayfa hariç her yerde', 'gbc-core' ),
		'sablon'    => __( 'Belirli şablonlarda', 'gbc-core' ),
		'kosul'     => __( 'Koşullu', 'gbc-core' ),
		'sayilir'   => __( 'Zorunlu değil, yalnız sayılır', 'gbc-core' ),
		'sunucu'    => __( 'Sunucuda kontrol edilir', 'gbc-core' ),
	);
	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th style="width:200px">' . esc_html__( 'Motor', 'gbc-core' ) . '</th>'
		. '<th style="width:230px">' . esc_html__( 'Nerede olmalı', 'gbc-core' ) . '</th>'
		. '<th style="width:110px">' . esc_html__( 'İz türü', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Aranan iz', 'gbc-core' ) . '</th>'
		. '<th style="width:220px">' . esc_html__( 'Kaynak', 'gbc-core' ) . '</th></tr></thead><tbody>';
	foreach ( $motorlar as $a => $m ) {
		$nerede = isset( $kapsam_ad[ $m['kapsam'] ] ) ? $kapsam_ad[ $m['kapsam'] ] : $m['kapsam'];
		if ( 'sablon' === $m['kapsam'] ) { $nerede .= ': ' . implode( ', ', (array) $m['sablon'] ); }
		if ( 'kosul' === $m['kapsam'] ) {
			$kosul_ad = array(
				'ortaklik_var' => __( 'ortaklık bağlantısı olan sayfalar', 'gbc-core' ),
				'fiyat_var'    => __( 'fiyat yazan sayfalar', 'gbc-core' ),
				'sky_widget'   => __( 'Skyscanner widget’ı olan sayfalar (şu an hiçbiri)', 'gbc-core' ),
			);
			$nerede .= ': ' . ( isset( $kosul_ad[ $m['kosul'] ] ) ? $kosul_ad[ $m['kosul'] ] : $m['kosul'] );
		}
		if ( ! empty( $m['ters'] ) )     { $nerede = __( 'Hiçbir yerde olmamalı', 'gbc-core' ); }

		echo '<tr><td><strong>' . esc_html( $m['ad'] ) . '</strong></td>';
		echo '<td>' . esc_html( $nerede ) . '</td>';
		echo '<td>' . esc_html( $m['iz'] ) . '</td>';
		echo '<td><code>' . esc_html( isset( $m['secici'] ) ? $m['secici'] : ( isset( $m['desen'] ) ? $m['desen'] : '—' ) ) . '</code>'
			. ( ! empty( $m['not'] ) ? '<div style="color:#5C6470;font-size:12px">' . esc_html( $m['not'] ) . '</div>' : '' ) . '</td>';
		echo '<td><code style="font-size:12px">' . esc_html( $m['kaynak'] ) . '</code></td></tr>';
	}
	echo '</tbody></table>';

	/* Karar bekleyenler — artik olculuyor, kanitla birlikte gosteriliyor */
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Karar bekleyen motorlar — N/A', 'gbc-core' ) . '</h2>';
	echo '<p style="color:#5C6470;max-width:1000px">'
		. esc_html__( 'Bu motorların kodu çalışıyor olabilir ama “nerede olmalı” kuralı yazılmadığı için eksik/fazla denemiyor. Aşağıda taranan sayfalarda gerçekten çıkıp çıkmadıkları yazıyor. Hiç çıkmıyorsa kod tetiklenmiyor demektir; bazı sayfalarda çıkıyorsa kuralı o şablonlara göre yazarız.', 'gbc-core' )
		. '</p>';

	$sorular = gbc_nk_karar_sorusu();
	echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>'
		. '<th style="width:190px">' . esc_html__( 'Motor', 'gbc-core' ) . '</th>'
		. '<th style="width:120px">' . esc_html__( 'Çıktığı sayfa', 'gbc-core' ) . '</th>'
		. '<th style="width:250px">' . esc_html__( 'Örnek', 'gbc-core' ) . '</th>'
		. '<th>' . esc_html__( 'Karar sorusu', 'gbc-core' ) . '</th></tr></thead><tbody>';
	foreach ( $motorlar as $a => $m ) {
		if ( 'na' !== $m['kapsam'] ) { continue; }
		$var = 0; $bakilan = 0; $ornek = array(); $sablonlar = array();
		foreach ( $kayit as $k ) {
			if ( ! isset( $k['motor'][ $a ] ) ) { continue; }
			$bakilan++;
			if ( 'na_var' === $k['motor'][ $a ] ) {
				$var++;
				if ( count( $ornek ) < 2 ) { $ornek[ $k['yol'] ] = $k['url']; }
				$sablonlar[ $k['sablon'] ] = true;
			}
		}
		$renk = $var ? '#0B5B55' : '#8A6100';
		echo '<tr><td><strong>' . esc_html( $m['ad'] ) . '</strong>'
			. '<div style="color:#8A919C;font-size:11px"><code>' . esc_html( $m['kaynak'] ) . '</code></div></td>';
		echo '<td style="color:' . esc_attr( $renk ) . ';font-weight:700">'
			. esc_html( sprintf( '%1$d / %2$d', $var, $bakilan ) )
			. ( $sablonlar ? '<div style="color:#5C6470;font-size:11px;font-weight:500">' . esc_html( implode( ', ', array_keys( $sablonlar ) ) ) . '</div>' : '' )
			. '</td>';
		echo '<td style="font-size:12px">';
		if ( $ornek ) {
			foreach ( $ornek as $yol => $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $yol ) . '</a><br>';
			}
		} else {
			echo '<em style="color:#8A6100">' . esc_html__( 'hiçbir sayfada çıkmadı — kod tetiklenmiyor', 'gbc-core' ) . '</em>';
		}
		echo '</td>';
		echo '<td style="font-size:13px">' . esc_html( isset( $sorular[ $a ] ) ? $sorular[ $a ] : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	/* Sunucu kontrolu */
	echo '<h2 style="margin-top:26px">' . esc_html__( 'Sunucu kontrolü — Şablon CSS dosyaya', 'gbc-core' ) . '</h2>';
	$sk = gbc_nk_sunucu_kontrol();
	if ( empty( $sk['var'] ) ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html( $sk['not'] ) . '</p></div>';
	} else {
		echo '<p style="color:#5C6470">' . esc_html( sprintf( __( '%1$d dosya · klasör: %2$s', 'gbc-core' ), (int) $sk['adet'], $sk['klasor'] ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:800px"><thead><tr>'
			. '<th>' . esc_html__( 'Dosya', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Boyut', 'gbc-core' ) . '</th>'
			. '<th style="width:110px">' . esc_html__( 'Cevap', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $sk['dosya'] as $f ) {
			echo '<tr><td><code>' . esc_html( $f['ad'] ) . '</code></td>'
				. '<td>' . esc_html( size_format( $f['bayt'] ) ) . '</td>'
				. '<td>' . ( 200 === (int) $f['kod']
					? '<span style="color:#1A7F37;font-weight:700">200</span>'
					: '<span style="color:#B3261E;font-weight:700">' . (int) $f['kod'] . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* KOD SAĞLIĞI — PHP tarafı, aynı ekranın ikinci katmanı */
	if ( function_exists( 'gbc_kod_tablo' ) ) { gbc_kod_tablo( $kod_rapor ); }

	echo '</div>';
}
