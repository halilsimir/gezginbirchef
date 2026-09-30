<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 29013 — Şema Çekirdek. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 92 · Schema-Core v2 (Kimlik + Breadcrumb + Yedek Ana Varlik)
 * -------------------------------------------------------------------
 * v2 DEGISIKLIKLERI (2026-08):
 *  1) SearchAction / sitelinks search box KALDIRILDI.
 *     Google bu ozelligi 21 Kasim 2024'te kaldirdi, artik okumuyor.
 *  2) YEDEK ANA VARLIK eklendi. Sablonu olmayan yazilarda (eski gezi/vlog
 *     yazilari, Rota sayfalari) hicbir Article/Recipe/ItemList basilmiyordu.
 *     Artik wp_footer(20) asamasinda, hicbir sablon ana varlik basmadiysa
 *     Article basiliyor.
 *  3) YEDEK VideoObject eklendi. Sayfada YouTube gomulu ama sablon
 *     VideoObject basmadiysa, video ID'sinden VideoObject uretiliyor.
 *  4) html_entity_decode: tum metin alanlari artik &amp; / &#8217; gibi
 *     HTML entity kacaklarindan arindiriliyor.
 *  5) Organization'a description eklendi; logo/sameAs korundu.
 *
 * MIMARI:
 *  - wp_head(99) + wp_footer(2) : kimlik grafi.
 *  - wp_footer(20) : yedek ana varlik. Sablon snippet'leri kendi varliklarini
 *    wp_footer(5) veya icerik render sirasinda bastigi icin bu asamada
 *    $GLOBALS['gbc_main_entity'] bayragi guvenilir sekilde okunabilir.
 *
 * GERI ALMA: Bu snippet'i pasife almak yeterli.
 */

/* --------------- Yardimcilar --------------- */

if ( ! function_exists( 'gbc_txt' ) ) {
	/** HTML entity + tag temizligi. Schema'ya giren HER metin buradan gecer. */
	function gbc_txt( $v, $limit = 0 ) {
		if ( is_array( $v ) || is_object( $v ) ) {
			return '';
		}
		$v = wp_strip_all_tags( (string) $v );
		$v = html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$v = preg_replace( '/\s+/u', ' ', $v );
		$v = trim( $v );
		if ( $limit > 0 && function_exists( 'mb_strlen' ) && mb_strlen( $v, 'UTF-8' ) > $limit ) {
			$v = rtrim( mb_substr( $v, 0, $limit, 'UTF-8' ) ) . '…';
		}
		return $v;
	}
}

if ( ! function_exists( 'gbc_acf' ) ) {
	/** ACF alanini guvenli oku. ACF yoksa bos doner. */
	function gbc_acf( $key, $id ) {
		if ( ! function_exists( 'get_field' ) ) {
			return '';
		}
		$v = get_field( $key, $id );
		return is_string( $v ) ? $v : ( empty( $v ) ? '' : $v );
	}
}

if ( ! function_exists( 'gbc_acf_dolu' ) ) {
	function gbc_acf_dolu( $key, $id ) {
		$v = gbc_acf( $key, $id );
		return ! empty( $v );
	}
}

if ( ! function_exists( 'gbc_yt_id' ) ) {
	/** Metinden ilk YouTube video ID'sini cikar. */
	function gbc_yt_id( $s ) {
		if ( ! is_string( $s ) || '' === $s ) {
			return '';
		}
		$re = '#(?:youtube\.com/(?:embed/|v/|watch\?(?:.*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#';
		return preg_match( $re, $s, $m ) ? $m[1] : '';
	}
}

if ( ! function_exists( 'gbc_kelime_say' ) ) {
	/** UTF-8 uyumlu kelime sayaci (str_word_count Turkce'de yaniltir). */
	function gbc_kelime_say( $s ) {
		return preg_match_all( '/\p{L}+/u', (string) $s );
	}
}

/* --------------- 1) Kimlik grafi --------------- */

if ( ! function_exists( 'gbc_core_identity_schema' ) ) {

	function gbc_core_identity_schema() {

		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( ! empty( $GLOBALS['gbc_core_identity_done'] ) ) {
			return;
		}

		$id = get_the_ID();
		if ( ! $id ) {
			return;
		}

		$GLOBALS['gbc_core_identity_done'] = true;

		$home      = home_url( '/' );
		$permalink = get_permalink( $id );

		$org_id    = $home . '#organization';
		$site_id   = $home . '#website';
		$person_id = 'https://gezginbirchef.com/halil-simir/#person';
		$page_id   = $permalink . '#webpage';
		$img_id    = $permalink . '#primaryimage';

		$site_logo = get_site_icon_url( 512 );
		if ( ! $site_logo ) {
			$site_logo = $home . 'wp-content/uploads/2022/05/gezginbirchef-logo-2x-3-200x123.png';
		}

		$organization = array(
			'@type'       => 'Organization',
			'@id'         => $org_id,
			'name'        => gbc_txt( get_bloginfo( 'name' ) ),
			'url'         => $home,
			'description' => gbc_txt( get_bloginfo( 'description' ) ),
			'logo'        => array(
				'@type'  => 'ImageObject',
				'url'    => $site_logo,
				'width'  => 512,
				'height' => 512,
			),
			'sameAs'      => array(
				'https://www.instagram.com/gezginbirchef',
				'https://www.youtube.com/channel/UC0kyISQAJvxLIUV-lkZ5HbQ',
				'https://www.facebook.com/gezginbirchef',
			),
		);

		// NOT: potentialAction/SearchAction bilerek YOK - Google 21.11.2024'te kaldirdi.
		$website = array(
			'@type'      => 'WebSite',
			'@id'        => $site_id,
			'url'        => $home,
			'name'       => gbc_txt( get_bloginfo( 'name' ) ),
			'publisher'  => array( '@id' => $org_id ),
			'inLanguage' => 'tr-TR',
		);

		$person = array(
			'@type'         => 'Person',
			'@id'           => $person_id,
			'name'          => 'Halil Şımır',
			'alternateName' => 'Gezginbirchef',
			'url'           => 'https://gezginbirchef.com/halil-simir/',
			'image'         => 'https://gezginbirchef.com/wp-content/uploads/2025/06/halil-Simir.jpeg',
			'jobTitle'      => 'Profesyonel Şef ve Seyahat Vloggerı',
			'worksFor'      => array( '@id' => $org_id ),
			'sameAs'        => array(
				'https://www.instagram.com/gezginbirchef',
				'https://www.youtube.com/channel/UC0kyISQAJvxLIUV-lkZ5HbQ?sub_confirmation=1',
			),
			'knowsAbout'    => array(
				'Dünya mutfakları',
				'Gastronomi',
				'Restoran keşifleri',
				'Gezi rehberleri',
				'Seyahat rotaları',
				'Yemek tarifleri',
			),
		);

		$webpage = array(
			'@type'         => 'WebPage',
			'@id'           => $page_id,
			'url'           => $permalink,
			'name'          => gbc_txt( get_the_title( $id ) ),
			'isPartOf'      => array( '@id' => $site_id ),
			'inLanguage'    => 'tr-TR',
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
		);

		$graph = array( $organization, $website, $person );

		$thumb_id  = get_post_thumbnail_id( $id );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
		if ( $thumb_url ) {
			$webpage['primaryImageOfPage'] = array( '@id' => $img_id );
		}

		$tarif_kategorileri = array(
			'yemek-tarifleri',
			'ana-yemek-tarifleri',
			'corbalar-tarifleri',
			'hamur-isi-ve-ekmek',
			'icecek-tarifleri',
			'kahvaltilik-ve-soslar',
			'salata-ve-meze-tarifleri',
			'tatli-tarifleri',
			'zeytinyagli-ve-sebze-yemekleri',
		);

		// Hub / rehber sayfalari rehber_icerik kullanir; bunlar tarif kategorisinde
		// olsa bile Recipe basmiyor, dolayisiyla breadcrumb'i biz basmaliyiz.
		$rehber_sayfasi  = gbc_acf_dolu( 'rehber_icerik', $id );
		$breadcrumb_atla = in_category( $tarif_kategorileri, $id ) && ! $rehber_sayfasi;

		$breadcrumb = null;
		if ( ! $breadcrumb_atla ) {
			$bc_id  = $permalink . '#breadcrumb';
			$crumbs = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Anasayfa',
					'item'     => $home,
				),
			);
			$pos  = 2;
			$cats = get_the_category( $id );
			if ( ! empty( $cats ) ) {
				$crumbs[] = array(
					'@type'    => 'ListItem',
					'position' => $pos,
					'name'     => gbc_txt( $cats[0]->name ),
					'item'     => get_category_link( $cats[0]->term_id ),
				);
				$pos++;
			}
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => gbc_txt( get_the_title( $id ) ),
				'item'     => $permalink,
			);

			$breadcrumb            = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $bc_id,
				'itemListElement' => $crumbs,
			);
			$webpage['breadcrumb'] = array( '@id' => $bc_id );
		}

		$graph[] = $webpage;

		if ( $breadcrumb ) {
			$graph[] = $breadcrumb;
		}

		if ( $thumb_url ) {
			$thumb_meta = wp_get_attachment_metadata( $thumb_id );
			/* GBC 2026-09-07: TAM BOY gorsel icin ayri ImageObject dugumu. Olculdu: asagidaki #primaryimage 1024x1024 KIRPILMIS surumu gosteriyor, Recipe.image ise tam boy orijinali veriyor. Google Gorseller'de siralanan tam boy dosya; lisans meta verisi yalnizca kirpilmis surumde kalirsa siralanan goruntuye hic ulasmiyor. Iki dosya gercekten ayri iki dosya, o yuzden ayri dugum dogru bicim; contentUrl ile eslestikleri icin cakismiyorlar. */
			$tam_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : '';
			/* Bu blok KOSULSUZ acilir: $lisans burada hesaplaniyor ve asagidaki
			   #primaryimage dugumu de onu kullaniyor. Kosula baglarsak, tam boy
			   ile 1024 surumu ayni dosya oldugunda (1024px'ten kucuk gorseller)
			   $lisans hic tanimlanmiyor ve ...$lisans yayilimi PHP 8'de olumcul
			   hata veriyor. Tam boy DUGUMU ise asagida ayrica kosula bagli. */
			if ( true ) {
				/* GBC 2026-09-07: LISANS SADECE BIZE AIT GORSELLERE (Umut'un kurali).
				   Vize rehberlerinde AI illustrasyon, bazi sayfalarda kurum logosu,
				   harita ekran goruntusu ve resmi kaynak gorseli var. Onlarda
				   creator / copyrightNotice basmak yanlis beyan olur, ayrica
				   Google'a sahibi olmadigimiz bir goruntuyu lisansliyormus gibi
				   gostermek olur. Isaret: postta YA DA ekin kendisinde
				   gbc_gorsel_harici = 1 ise lisans alanlari hic basilmaz,
				   dugum sade ImageObject olarak kalir (contentUrl/width/height durur).
				   PHP 8.1+ dizi yayilimi ( ...$lisans ) kullaniliyor; sunucu 8.3.33. */
				$gbc_pid    = get_queried_object_id();
				$gbc_harici = ( $gbc_pid && get_post_meta( $gbc_pid, 'gbc_gorsel_harici', true ) )
					|| ( $thumb_id && get_post_meta( $thumb_id, 'gbc_gorsel_harici', true ) );
				/* GBC 2026-09-07 v2: MANTIK TERSINE CEVRILDI (Umut'un bilgisiyle).
				   Once "harici degilse lisansla" idi. Ama Umut soyledi: bloglarin
				   kapaklari AI, tariflerde stok + kendi cekimi + AI karisik ve
				   ayirt edilemiyor. Sahibi olmadigimiz bir gorsele "telifi
				   Halil Simir'da, lisanslamak icin buraya gelin" demek yanlis
				   beyan; stok gorselde hak iddiasi ayrica hukuki risk.
				   EXIF ile ayirmayi denedim, olmuyor: sitedeki gorsellerin
				   TAMAMINDA kamera bilgisi silinmis (Tinos ve Ibiza kapaklari
				   dahil, ki onlar Umut'un kendi cekimi). Yani otomatik ayrac yok.
				   Bu yuzden varsayilan KAPALI. Lisans yalnizca acikca
				   isaretlenmis gorsellere basilir: postta ya da ekin kendisinde
				   gbc_gorsel_bizim = 1. Gezi rehberleri toplu isaretlendi
				   (Umut: "gezileri olduğu gibi ben cekiyorum, videodan kare
				   aliyorum ya da telefondan"). Tarifler tek tek isaretlenecek. */
				$gbc_bizim = ( $gbc_pid && get_post_meta( $gbc_pid, 'gbc_gorsel_bizim', true ) )
					|| ( $thumb_id && get_post_meta( $thumb_id, 'gbc_gorsel_bizim', true ) )
					/* Gezi silosu toplu ACIK. Umut'un beyani: "gezileri oldugu gibi
					   ben cekiyorum, videodan kare aliyorum ya da telefondan."
					   922 Gezi Rehberi + alt kategorileri (921 Avrupa, 936 Balkan,
					   1175 Yunanistan) = 127 yazi, arti 751 Lezzet Duraklari = 32
					   yazi (Umut: "lezzet duraklari da benim fotom"; mekanlarda
					   kendisi cekiyor). Vize sayfalari da bu agacin
					   icinde ama gbc_gorsel_harici = 1 ile ayrica disarida; harici
					   her zaman kazanir. Tarifler ve bloglar KAPALI kalir, cunku
					   Umut soyledi: bloglarin kapaklari AI, tariflerde stok +
					   kendi cekimi + AI karisik ve ayirt edilemiyor. */
					;
					/* GBC 2026-09-07 v3: KATEGORI KURALI KALDIRILDI.
					   v2'de gezi silosu ve lezzet duraklari kategori bazinda
					   toplu acilmisti. Umut sonra soyledi: "ana kapaklar da one
					   cikan gorseller AI zaten, altta kullandigim gorseller benim
					   aldigim kareler."
					   Bu sema TAM OLARAK one cikan gorseli isaretliyor
					   (#primaryimage ve #primaryimage-full). Yani kategori kurali
					   acikken AI kapaklara "fotografi Halil Simir cekti, telifi
					   onda" demis oluyorduk. Kaldirildi.
					   Sonuc: lisans artik SADECE elle gbc_gorsel_bizim = 1
					   isaretlenmis post/eklere basilir. Su an hicbiri isaretli
					   degil, yani lisans hicbir yerde basilmiyor. Dogru durum bu:
					   sahibi olmadigimiz gorselde hak iddia etmiyoruz.
					   Gercek fotograflar ICERIK icindeki gorseller; onlari
					   isaretlemek ayri bir is (ayri ImageObject dugumleri). */
				$lisans = array();
				if ( $gbc_bizim && ! $gbc_harici ) $lisans = array(
					'license'            => 'https://gezginbirchef.com/gorsel-kullanim-ve-lisans/',
					'acquireLicensePage' => 'https://gezginbirchef.com/gorsel-kullanim-ve-lisans/#lisans-al',
					'creditText'         => 'Halil Şımır / Gezginbirchef',
					'creator'            => array( '@type' => 'Person', 'name' => 'Halil Şımır' ),
					'copyrightNotice'    => 'Halil Şımır',
				




				);

				if ( $tam_url && $tam_url !== $thumb_url ) $graph[] = array(
					'@type'              => 'ImageObject',
					'@id'                => $img_id . '-full',
					'url'                => $tam_url,
					'contentUrl'         => $tam_url,
					...$lisans,





				);
			}

			$graph[]    = array(
				'@type'  => 'ImageObject',
				'@id'    => $img_id,
				'url'        => $thumb_url,
				/* GBC 2026-09-07: Gorsel lisans meta verisi (Google "Lisanslanabilir" rozeti).
				   Google contentUrl istiyor; 'url' tek basina yetmiyor. license /
				   acquireLicensePage /gorsel-kullanim-ve-lisans/ sayfasina bakar.
				   Olculdu: site ceyrekte 250.000+ gorsel arama gosterimi aliyor,
				   en buyuk gosterim kanali bu. Recipe.image'e DOKUNULMADI (dizi
				   halindeki duz URL'ler en guvenli bicim, tarif zengin sonucu bozulmasin). */
				'contentUrl'         => $thumb_url,
				...$lisans,




				'width'  => isset( $thumb_meta['sizes']['large']['width'] ) ? $thumb_meta['sizes']['large']['width'] : 1024,
				'height' => isset( $thumb_meta['sizes']['large']['height'] ) ? $thumb_meta['sizes']['large']['height'] : 768,
			);
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
			. '</script>' . "\n";
	}

	add_action( 'wp_head', 'gbc_core_identity_schema', 99 );
	add_action( 'wp_footer', 'gbc_core_identity_schema', 2 );
}

/* --------------- 2) Yedek ana varlik (Article + VideoObject) --------------- */

if ( ! function_exists( 'gbc_core_fallback_schema' ) ) {

	function gbc_core_fallback_schema() {

		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}
		if ( ! empty( $GLOBALS['gbc_core_fallback_done'] ) ) {
			return;
		}
		$id = get_the_ID();
		if ( ! $id ) {
			return;
		}
		$GLOBALS['gbc_core_fallback_done'] = true;

		/* Hangi sablon zaten ana varlik basiyor? */
		// Gezi (22607) / Blog (26922) / Tarif (24751) bu bayragi set eder.
		$sablon_basti = ! empty( $GLOBALS['gbc_main_entity'] );
		// Detay (23489) Article basar, bayrak set etmez -> ACF alanindan tespit.
		if ( gbc_acf_dolu( 'detailed_hero_title', $id ) ) {
			$sablon_basti = true;
		}
		// Sozluk Detay (24156) DefinedTerm basar -> ACF alanindan tespit.
		if ( gbc_acf_dolu( 'sozluk_ana_tanim', $id ) || gbc_acf_dolu( 'sozluk_kisa_tanim', $id ) ) {
			$sablon_basti = true;
		}

		if ( $sablon_basti ) {
			return;
		}

		$home      = home_url( '/' );
		$permalink = get_permalink( $id );
		$org_id    = $home . '#organization';
		$person_id = 'https://gezginbirchef.com/halil-simir/#person';
		$page_id   = $permalink . '#webpage';
		$img_id    = $permalink . '#primaryimage';

		$graph = array();

		/* 2a) Yedek Article */
		$icerik = get_post_field( 'post_content', $id );
		$ozet   = gbc_txt( get_the_excerpt( $id ), 250 );
		if ( '' === $ozet ) {
			$ozet = gbc_txt( $icerik, 250 );
		}

		$article = array(
			'@type'            => 'Article',
			'@id'              => $permalink . '#article',
			'isPartOf'         => array( '@id' => $page_id ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'headline'         => gbc_txt( get_the_title( $id ), 110 ),
			'datePublished'    => get_the_date( 'c', $id ),
			'dateModified'     => get_the_modified_date( 'c', $id ),
			'inLanguage'       => 'tr-TR',
			'author'           => array( '@id' => $person_id ),
			'publisher'        => array( '@id' => $org_id ),
		);

		if ( '' !== $ozet ) {
			$article['description'] = $ozet;
		}

		$thumb_id = get_post_thumbnail_id( $id );
		if ( $thumb_id ) {
			$article['image'] = array( '@id' => $img_id );
		}

		$cats = get_the_category( $id );
		if ( ! empty( $cats ) ) {
			$article['articleSection'] = gbc_txt( $cats[0]->name );
		}

		$tags = get_the_tags( $id );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$article['keywords'] = gbc_txt( implode( ', ', wp_list_pluck( $tags, 'name' ) ) );
		}

		// wordCount sadece gercekten anlamliysa. ACF sablonlarinda post_content
		// bos oldugu icin yaniltici "4 kelime" degeri basilmasin.
		$wc = gbc_kelime_say( wp_strip_all_tags( $icerik ) );
		if ( $wc >= 150 ) {
			$article['wordCount'] = $wc;
		}

		$cc = (int) get_comments_number( $id );
		if ( $cc > 0 ) {
			$article['commentCount']         = $cc;
			$article['interactionStatistic'] = array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/CommentAction',
				'userInteractionCount' => $cc,
			);
		}

		$graph[] = $article;

		/* 2b) Yedek VideoObject */
		$kaynaklar = array( $icerik );
		foreach ( array( 'hero_video', 'hero_video_listed', 'rota_video_listed', 'detailed_video_1', 'video_1_url', 'sozluk_video_url' ) as $f ) {
			$v = gbc_acf( $f, $id );
			if ( is_string( $v ) && '' !== $v ) {
				$kaynaklar[] = $v;
			}
		}
		$metalar = get_post_meta( $id );
		if ( is_array( $metalar ) ) {
			foreach ( $metalar as $mk => $mv ) {
				if ( 0 === strpos( $mk, '_oembed_' ) && isset( $mv[0] ) && is_string( $mv[0] ) ) {
					$kaynaklar[] = $mv[0];
				}
			}
		}

		$yt = '';
		foreach ( $kaynaklar as $k ) {
			$yt = gbc_yt_id( $k );
			if ( '' !== $yt ) {
				break;
			}
		}

		if ( '' !== $yt ) {
			$vdesc = gbc_txt( get_the_excerpt( $id ), 200 );
			if ( '' === $vdesc ) {
				$vdesc = gbc_txt( get_the_title( $id ) );
			}
			$graph[] = array(
				'@type'        => 'VideoObject',
				'@id'          => $permalink . '#video',
				'name'         => gbc_txt( get_the_title( $id ), 110 ),
				'description'  => $vdesc,
				'thumbnailUrl' => array( 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' ),
				'uploadDate'   => get_the_date( 'c', $id ),
				'embedUrl'     => 'https://www.youtube.com/embed/' . $yt,
				'publisher'    => array( '@id' => $org_id ),
			);
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
			. '</script>' . "\n";
	}

	add_action( 'wp_footer', 'gbc_core_fallback_schema', 20 );
}


/* --------------- 3) Arsiv / sayfa kimlik dugumleri --------------- */
/* Kategori, etiket, sayfa ve yazar sayfalarinda CollectionPage / DefinedTermSet /
   ProfilePage dugumleri #website ve #person'a referans veriyordu ama bu dugumler
   o sayfalarda hic tanimli degildi -> kirik referans. Burada kompakt kimlik
   dugumlerini basiyoruz. Tekil yazilari gbc_core_identity_schema, ana sayfayi
   ise "Ana Sayfa Schema" snippet'i basar. */

if ( ! function_exists( 'gbc_core_arsiv_kimlik' ) ) {

	function gbc_core_arsiv_kimlik() {

		if ( is_admin() || is_front_page() || is_singular( 'post' ) || is_author() ) {
			return;
		}
		if ( ! empty( $GLOBALS['gbc_core_arsiv_done'] ) || ! empty( $GLOBALS['gbc_core_identity_done'] ) ) {
			return;
		}
		$GLOBALS['gbc_core_arsiv_done'] = true;

		$home      = home_url( '/' );
		$org_id    = $home . '#organization';
		$site_id   = $home . '#website';
		$person_id = 'https://gezginbirchef.com/halil-simir/#person';

		$site_logo = get_site_icon_url( 512 );
		if ( ! $site_logo ) {
			$site_logo = $home . 'wp-content/uploads/2022/05/gezginbirchef-logo-2x-3-200x123.png';
		}

		$graph = array(
			array(
				'@type'       => 'Organization',
				'@id'         => $org_id,
				'name'        => get_bloginfo( 'name' ),
				'url'         => $home,
				'description' => wp_strip_all_tags( html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
				'logo'        => array(
					'@type'  => 'ImageObject',
					'url'    => $site_logo,
					'width'  => 512,
					'height' => 512,
				),
				'sameAs'      => array(
					'https://www.instagram.com/gezginbirchef',
					'https://www.youtube.com/channel/UC0kyISQAJvxLIUV-lkZ5HbQ',
					'https://www.facebook.com/gezginbirchef',
				),
			),
			array(
				'@type'      => 'WebSite',
				'@id'        => $site_id,
				'url'        => $home,
				'name'       => get_bloginfo( 'name' ),
				'publisher'  => array( '@id' => $org_id ),
				'inLanguage' => 'tr-TR',
			),
			array(
				'@type'         => 'Person',
				'@id'           => $person_id,
				'name'          => 'Halil Şımır',
				'alternateName' => 'Gezginbirchef',
				'url'           => 'https://gezginbirchef.com/halil-simir/',
				'image'         => 'https://gezginbirchef.com/wp-content/uploads/2025/06/halil-Simir.jpeg',
				'jobTitle'      => 'Profesyonel Şef ve Seyahat Vloggerı',
				'worksFor'      => array( '@id' => $org_id ),
				'sameAs'        => array(
					'https://www.instagram.com/gezginbirchef',
					'https://www.youtube.com/channel/UC0kyISQAJvxLIUV-lkZ5HbQ?sub_confirmation=1',
				),
			),
		);

		echo '<script type="application/ld+json">'
			. wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
			. '</script>' . "\n";
	}

	add_action( 'wp_head', 'gbc_core_arsiv_kimlik', 99 );
	add_action( 'wp_footer', 'gbc_core_arsiv_kimlik', 2 );
}
