<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 29969 — Bölüm Medyası. GBC Core'a taşındı, 28 Eylül 2026. */


/**
 * ============================================================================
 *  GBC · 27 · BÖLÜM MEDYASI  ·  v2.5
 * ----------------------------------------------------------------------------
 *  Ne yapar:
 *    Gezi Rehberi (GBC · 20) içindeki HER bölümün altına o bölüme ait
 *    galeri + Instagram + YouTube Shorts/video bloğu basar.
 *
 *  Nasıl beslenir:
 *    Tek bir ACF "textarea" alanı. Her satır bir medya. Sıra = ekrandaki sıra.
 *
 *      21207 | Akropolis güney yamacından Parthenon sütunları
 *      21208
 *      https://www.instagram.com/p/CxYzAbC123/ | Kostas souvlaki kuyruğu
 *      https://www.youtube.com/shorts/AbCdEf12345 | Lykabettus füniküler
 *      https://youtu.be/ZMOWUOjrQU8 | Atina genel vlog
 *
 *    Sol taraf = medya (WP medya ID'si ya da URL)
 *    "|" sağı  = alt metin / başlık  (isteğe bağlı ama görsellerde ŞİDDETLE önerilir)
 *
 *  Bağımlılık: YOK. Instagram oEmbed, embed.js, Font Awesome, jQuery kullanmaz.
 *  Üçüncü parti script yalnızca kullanıcı tıklarsa yüklenir (facade).
 *
 *  UX/UI sözleşmesi (gbc-ux-ui):
 *    · sınıf öneki .gbc-sm-   · token'lar --gbc-*   · emoji ikon yok, inline SVG
 *    · görsel kırpılmaz; CLS width/height nitelikleriyle karşılanır
 *    · görsellerde width/height/srcset/lazy → wp_get_attachment_image()
 *    · alt metni ACF'den gelir, şablon ezmez
 *    · dokunma hedefi ≥ 44px · turuncu yalnız dolgu, metin #BF360C
 *    · prefers-reduced-motion desteklenir
 *    · CSS yalnızca fonksiyon gerçekten çağrıldıysa basılır (kullanılmayan CSS = 0)
 * ============================================================================
 */

/* --- Telif / lisans (Google Images "Licensable" rozeti için) ----------------
   Bu iki sayfa SİTEDE VARSA doldur. Boş bırakırsan lisans alanları şemaya
   hiç yazılmaz — geçersiz şema basmaktansa hiç basmamak doğrudur.        */
if ( ! defined( 'GBC_SM_LISANS_URL' ) )  define( 'GBC_SM_LISANS_URL',  '' ); // ör. https://gezginbirchef.com/telif-hakki/
if ( ! defined( 'GBC_SM_LISANS_EDIN' ) ) define( 'GBC_SM_LISANS_EDIN', '' ); // ör. https://gezginbirchef.com/iletisim/
if ( ! defined( 'GBC_SM_YAZAR' ) )       define( 'GBC_SM_YAZAR',       'Halil Şımır' );


/* ==========================================================================
   1) AYRIŞTIRICI — ham metni yapılandırılmış diziye çevirir
   ========================================================================== */
if ( ! function_exists( 'gbc_sm_ayristir' ) ) {
function gbc_sm_ayristir( $ham ) {
	$out = array();
	if ( ! is_string( $ham ) || trim( $ham ) === '' ) return $out;

	$satirlar = preg_split( '/\r\n|\r|\n/', $ham );

	foreach ( $satirlar as $satir ) {
		$satir = trim( wp_strip_all_tags( $satir ) );
		if ( $satir === '' || strpos( $satir, '#' ) === 0 ) continue; // # ile başlayan satır = not

		/* Üç parça = KART fotoğrafı:  1 | 28331 | alt metni
		   İki/tek parça = bölüm galerisi: 28331 | alt metni  ·  28331   */
		$kart_no = 0;
		$etiket  = '';
		if ( strpos( $satir, '|' ) !== false ) {
			$parca = array_map( 'trim', explode( '|', $satir ) );
			if ( count( $parca ) >= 3 && preg_match( '/^\d{1,3}$/', $parca[0] ) ) {
				$kart_no = (int) $parca[0];
				$satir   = $parca[1];
				$etiket  = implode( ' | ', array_slice( $parca, 2 ) );
			} else {
				$satir  = $parca[0];
				$etiket = implode( ' | ', array_slice( $parca, 1 ) );
			}
		}
		if ( $satir === '' ) continue;

		// --- YouTube Shorts
		if ( preg_match( '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#i', $satir, $m ) ) {
			$out[] = array( 'tip' => 'short', 'id' => $m[1], 'etiket' => $etiket, 'kart' => $kart_no );
			continue;
		}
		// --- YouTube normal (watch / youtu.be / embed / live)
		if ( preg_match( '#(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|live/))([A-Za-z0-9_-]{6,})#i', $satir, $m ) ) {
			$out[] = array( 'tip' => 'yt', 'id' => $m[1], 'etiket' => $etiket, 'kart' => $kart_no );
			continue;
		}
		// --- Instagram post / reel / tv
		if ( preg_match( '#instagram\.com/(?:p|reel|reels|tv)/([A-Za-z0-9_-]+)#i', $satir, $m ) ) {
			$out[] = array( 'tip' => 'ig', 'id' => $m[1], 'etiket' => $etiket, 'kart' => $kart_no );
			continue;
		}
		// --- WP medya kütüphanesi ID'si
		if ( preg_match( '/^\d+$/', $satir ) ) {
			$out[] = array( 'tip' => 'gorsel', 'id' => (int) $satir, 'etiket' => $etiket, 'kart' => $kart_no );
			continue;
		}
		// --- Doğrudan görsel URL'si (son çare; ID tercih edilir)
		if ( preg_match( '#^https?://\S+\.(?:jpe?g|png|webp|avif|gif)(?:\?\S*)?$#i', $satir ) ) {
			$ek_id = attachment_url_to_postid( $satir );
			$out[] = $ek_id
				? array( 'tip' => 'gorsel', 'id' => $ek_id,  'etiket' => $etiket, 'kart' => $kart_no )
				: array( 'tip' => 'gorsel_url', 'id' => esc_url_raw( $satir ), 'etiket' => $etiket, 'kart' => $kart_no );
			continue;
		}
		// tanınmayan satır sessizce atlanır
	}
	return $out;
}
}


/* ==========================================================================
   2) ANA RENDER — şablondan çağrılan tek fonksiyon
      Kullanım:  <?php gbc_bolum_medyasi( 'media_places' ); ?>
   ========================================================================== */
if ( ! function_exists( 'gbc_bolum_medyasi' ) ) {
function gbc_bolum_medyasi( $alan, $arg = array() ) {

	if ( ! function_exists( 'get_field' ) ) return;   // ACF kapalıysa sessizce çık
	$ham = get_field( $alan );
	$ogeler = gbc_sm_ayristir( $ham );
	if ( empty( $ogeler ) ) return;

	$arg = wp_parse_args( $arg, array(
		'baslik'  => '',     // isteğe bağlı küçük başlık
		'sinif'   => '',
	) );

	// kart numarası taşıyan satırlar galeriye DEĞİL, kartın içine gider
	$gorseller = array_values( array_filter( $ogeler, function( $o ) {
		return ( $o['tip'] === 'gorsel' || $o['tip'] === 'gorsel_url' ) && empty( $o['kart'] );
	} ) );
	$videolar  = array_values( array_filter( $ogeler, function( $o ) {
		return $o['tip'] === 'short' || $o['tip'] === 'yt';
	} ) );
	$instalar  = array_values( array_filter( $ogeler, function( $o ) {
		return $o['tip'] === 'ig';
	} ) );

	/* Tampona yaz. Alan dolu görünse bile geçerli tek bir medya çıkmayabilir
	   (silinmiş ek, bozuk ID). O durumda boş sarmalayıcı bile basılmaz. */
	ob_start();

	if ( $arg['baslik'] ) {
		echo '<h3 class="gbc-sm-baslik">' . esc_html( $arg['baslik'] ) . '</h3>';
	}

	/* ---------- GALERİ ---------- */
	if ( $gorseller ) {

		/* Önce DOĞRULA, sonra çiz. Silinmiş ya da bozuk bir ek yüzünden
		   boş bir galeri kutusu basılmasın; düzen sınıfı da gerçekten
		   görünecek görsel sayısına göre seçilsin. */
		$hazir = array();
		foreach ( $gorseller as $g ) {

			if ( $g['tip'] === 'gorsel' ) {
				$tam = wp_get_attachment_image_url( $g['id'], 'full' );
				if ( ! $tam ) continue;                       // ek silinmiş → atla
				$alt = $g['etiket'] !== ''
					? $g['etiket']
					: trim( (string) get_post_meta( $g['id'], '_wp_attachment_image_alt', true ) );
				if ( $alt === '' ) $alt = get_the_title() . ' — fotoğraf';

				$img = wp_get_attachment_image( $g['id'], 'medium_large', false, array(
					'alt'      => $alt,
					'loading'  => 'lazy',
					'decoding' => 'async',
					'class'    => 'gbc-sm-img',
				) );
				if ( ! $img ) continue;
				gbc_sm_sema_kaydet( $g['id'], $tam, $alt );
			} else {
				$tam = $g['id'];
				$alt = $g['etiket'] !== '' ? $g['etiket'] : get_the_title() . ' — fotoğraf';
				$img = '<img class="gbc-sm-img" src="' . esc_url( $tam ) . '" alt="' . esc_attr( $alt )
				     . '" loading="lazy" decoding="async" width="768" height="576">';
			}

			$hazir[] = array( 'img' => $img, 'tam' => $tam, 'alt' => $alt, 'etiket' => $g['etiket'] );
		}

		if ( $hazir ) {
			$adet  = count( $hazir );
			$duzen = $adet === 1 ? 'tek' : ( $adet === 2 ? 'ikili' : 'izgara' );
			echo '<div class="gbc-sm-galeri gbc-sm-galeri--' . $duzen . '">';

			foreach ( $hazir as $h ) {
				$sira = gbc_sm_lightbox_kaydet( $h['tam'], $h['alt'] );
				echo '<figure class="gbc-sm-kutu">';
				echo   '<button type="button" class="gbc-sm-tetik" data-gbcsm="' . (int) $sira . '"'
				     . ' aria-label="' . esc_attr( $h['alt'] ) . ' — büyüt">';
				echo     $h['img'];
				echo     '<span class="gbc-sm-buyut" aria-hidden="true"></span>';
				echo   '</button>';
				if ( $h['etiket'] !== '' ) {
					echo '<figcaption class="gbc-sm-altyazi">' . esc_html( $h['etiket'] ) . '</figcaption>';
				}
				echo '</figure>';
			}
			echo '</div>';
		}
	}

	/* ---------- VIDEO (facade) ---------- */
	if ( $videolar ) {
		echo '<div class="gbc-sm-video-sira">';
		foreach ( $videolar as $v ) {
			$short = ( $v['tip'] === 'short' );
			$vid   = $v['id'];
			$kapak = 'https://i.ytimg.com/vi/' . rawurlencode( $vid ) . '/hqdefault.jpg';
			$ad    = $v['etiket'] !== '' ? $v['etiket'] : 'Videoyu oynat';

			echo '<div class="gbc-sm-video' . ( $short ? ' gbc-sm-video--short' : '' ) . '">';
			echo   '<button type="button" class="gbc-sm-oynat" data-yt="' . esc_attr( $vid ) . '"'
			     . ' aria-label="' . esc_attr( $ad ) . ' — YouTube\'da oynat">';
			echo     '<img src="' . esc_url( $kapak ) . '" alt="" loading="lazy" decoding="async"'
			     . ' width="480" height="360" aria-hidden="true">';
			echo     '<span class="gbc-sm-play" aria-hidden="true">' . gbc_sm_ikon( 'play' ) . '</span>';
			echo   '</button>';
			if ( $v['etiket'] !== '' ) {
				echo '<span class="gbc-sm-video-ad">' . esc_html( $v['etiket'] ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/* ---------- INSTAGRAM (facade — tıklayınca gömülür) ---------- */
	if ( $instalar ) {
		echo '<div class="gbc-sm-ig-sira">';
		foreach ( $instalar as $i ) {
			$kod = $i['id'];
			$ad  = $i['etiket'] !== '' ? $i['etiket'] : 'Instagram gönderisi';
			echo '<div class="gbc-sm-ig">';
			echo   '<button type="button" class="gbc-sm-ig-tetik" data-ig="' . esc_attr( $kod ) . '"'
			     . ' aria-label="' . esc_attr( $ad ) . ' — Instagram gönderisini yükle">';
			echo     '<span class="gbc-sm-ig-ikon" aria-hidden="true">' . gbc_sm_ikon( 'instagram' ) . '</span>';
			echo     '<span class="gbc-sm-ig-metin">' . esc_html( $ad ) . '</span>';
			echo     '<span class="gbc-sm-ig-alt">Instagram\'da gör</span>';
			echo   '</button>';
			echo '</div>';
		}
		echo '</div>';
	}

	$ic = trim( ob_get_clean() );

	// Hiçbir geçerli medya üretilmediyse: ne sarmalayıcı, ne CSS, ne şema.
	if ( $ic === '' ) return;
	if ( strpos( $ic, 'gbc-sm-kutu' ) === false
	  && strpos( $ic, 'gbc-sm-oynat' ) === false
	  && strpos( $ic, 'gbc-sm-ig-tetik' ) === false ) return;

	echo '<div class="gbc-sm ' . esc_attr( $arg['sinif'] ) . '">' . $ic . '</div>';
}
}


/* ==========================================================================
   3) LIGHTBOX KAYIT DEFTERİ
      Not: GBC · 20'nin kendi lightbox'ı (gzGalleryImages) yalnızca ana galeri
      VARSA basılır. Çakışmamak ve her koşulda çalışmak için bölüm medyası
      kendi bağımsız lightbox'ını kullanır.
   ========================================================================== */
if ( ! function_exists( 'gbc_sm_lightbox_kaydet' ) ) {
function gbc_sm_lightbox_kaydet( $url, $alt ) {
	if ( ! isset( $GLOBALS['gbc_sm_lb'] ) ) $GLOBALS['gbc_sm_lb'] = array();
	$GLOBALS['gbc_sm_lb'][] = array( 'u' => $url, 'a' => $alt );
	return count( $GLOBALS['gbc_sm_lb'] ) - 1;
}
}

if ( ! function_exists( 'gbc_sm_sema_kaydet' ) ) {
function gbc_sm_sema_kaydet( $ek_id, $url, $alt ) {
	if ( ! isset( $GLOBALS['gbc_sm_sema'] ) ) $GLOBALS['gbc_sm_sema'] = array();
	$GLOBALS['gbc_sm_sema'][ $url ] = array( 'id' => $ek_id, 'alt' => $alt );
}
}


/* ==========================================================================
   4) FOOTER — lightbox + JS + ImageObject şeması (yalnızca kullanıldıysa)
   ========================================================================== */
add_action( 'wp_footer', 'gbc_sm_footer', 30 );
if ( ! function_exists( 'gbc_sm_footer' ) ) {
function gbc_sm_footer() {

	// CSS wp_head'de basıldıysa bileşen bu sayfada var demektir.
	if ( empty( $GLOBALS['gbc_sm_kullanildi'] ) ) return;

	$lb = isset( $GLOBALS['gbc_sm_lb'] ) ? array_values( $GLOBALS['gbc_sm_lb'] ) : array();
	?>
<div id="gbc-sm-lb" class="gbc-sm-lb" hidden role="dialog" aria-modal="true" aria-label="Fotoğraf görüntüleyici">
	<button type="button" class="gbc-sm-lb-kapat" aria-label="Kapat">&times;</button>
	<button type="button" class="gbc-sm-lb-onceki" aria-label="Önceki fotoğraf">&#10094;</button>
	<img id="gbc-sm-lb-img" src="" alt="" decoding="async">
	<button type="button" class="gbc-sm-lb-sonraki" aria-label="Sonraki fotoğraf">&#10095;</button>
	<p id="gbc-sm-lb-yazi" class="gbc-sm-lb-yazi"></p>
</div>
<script id="gbc-sm-js">
(function(){
"use strict";
var L = <?php echo wp_json_encode( $lb ); ?>;
var kutu = document.getElementById('gbc-sm-lb');
var img  = document.getElementById('gbc-sm-lb-img');
var yazi = document.getElementById('gbc-sm-lb-yazi');
var i = 0, sonOdak = null;

function goster(n){
  if(!L.length) return;
  i = (n + L.length) % L.length;
  img.src = L[i].u; img.alt = L[i].a || '';
  yazi.textContent = (L[i].a || '') + (L.length > 1 ? '  ·  ' + (i+1) + ' / ' + L.length : '');
}
function ac(n){ sonOdak = document.activeElement; goster(n); kutu.hidden = false;
  document.documentElement.style.overflow='hidden'; kutu.querySelector('.gbc-sm-lb-kapat').focus(); }
function kapat(){ kutu.hidden = true; img.src=''; document.documentElement.style.overflow='';
  if(sonOdak) sonOdak.focus(); }

document.addEventListener('click', function(e){
  var t = e.target.closest ? e.target.closest('.gbc-sm-tetik') : null;
  if(t){ e.preventDefault(); ac(parseInt(t.getAttribute('data-gbcsm'),10)||0); return; }

  // YouTube facade → iframe
  var y = e.target.closest ? e.target.closest('.gbc-sm-oynat') : null;
  if(y){
    e.preventDefault();
    var vid = y.getAttribute('data-yt'), sar = y.parentNode;
    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + vid + '?autoplay=1&rel=0&modestbranding=1';
    f.title = y.getAttribute('aria-label') || 'YouTube video';
    f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    f.setAttribute('allowfullscreen','');
    f.loading = 'lazy'; f.className = 'gbc-sm-iframe';
    sar.replaceChild(f, y);
    return;
  }

  // Instagram facade → resmi gömme (script yalnızca burada yüklenir)
  var g = e.target.closest ? e.target.closest('.gbc-sm-ig-tetik') : null;
  if(g){
    e.preventDefault();
    var kod = g.getAttribute('data-ig'), sar2 = g.parentNode;
    var bq = document.createElement('blockquote');
    bq.className = 'instagram-media';
    bq.setAttribute('data-instgrm-permalink','https://www.instagram.com/p/'+kod+'/');
    bq.setAttribute('data-instgrm-version','14');
    bq.style.cssText = 'background:#FFF;border:0;margin:0 auto;max-width:540px;min-width:280px;width:100%';
    sar2.replaceChild(bq, g);
    if(window.instgrm){ window.instgrm.Embeds.process(); }
    else if(!document.getElementById('gbc-ig-sdk')){
      var s = document.createElement('script');
      s.id='gbc-ig-sdk'; s.async=true; s.src='https://www.instagram.com/embed.js';
      document.body.appendChild(s);
    }
  }
});

if(kutu){
  kutu.querySelector('.gbc-sm-lb-kapat').addEventListener('click', kapat);
  kutu.querySelector('.gbc-sm-lb-onceki').addEventListener('click', function(){ goster(i-1); });
  kutu.querySelector('.gbc-sm-lb-sonraki').addEventListener('click', function(){ goster(i+1); });
  kutu.addEventListener('click', function(e){ if(e.target === kutu) kapat(); });
  document.addEventListener('keydown', function(e){
    if(kutu.hidden) return;
    if(e.key === 'Escape')     kapat();
    if(e.key === 'ArrowRight') goster(i+1);
    if(e.key === 'ArrowLeft')  goster(i-1);
  });
  var sx=0, dx=0;
  kutu.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; }, {passive:true});
  kutu.addEventListener('touchmove',  function(e){ dx = e.touches[0].clientX - sx; }, {passive:true});
  kutu.addEventListener('touchend',   function(){ if(Math.abs(dx)>50) goster(dx<0 ? i+1 : i-1); dx=0; });
}
})();
</script>
	<?php
	gbc_sm_sema_bas();
}
}


/* ==========================================================================
   5) ImageObject ŞEMASI
      Google Görseller'de "Licensable" rozeti ve AI Overview alıntısı için.
      VideoObject BİLEREK basılmaz: uploadDate zorunlu alandır, bölüm
      videolarında o veri yok. Videolar şemayı GBC · 93 (video_N_url +
      GBC · 94 tarih haritası) üzerinden alır. Geçersiz şema basmaktansa
      hiç basmamak doğrudur.
   ========================================================================== */
if ( ! function_exists( 'gbc_sm_sema_bas' ) ) {
function gbc_sm_sema_bas() {
	if ( empty( $GLOBALS['gbc_sm_sema'] ) ) return;

	$sayfa  = get_permalink();
	$graph  = array();

	/* Ana galeride (gal_img_1-20 / detay_resim_1-20) zaten bulunan görseller
	   atlanır. GBC · 20 onlar için kendi ImageGallery > ImageObject düğümünü
	   basıyor; aynı contentUrl'i iki kez tarif etmek şemayı kirletir. */
	$galeri_urlleri = array();
	if ( function_exists( 'get_field' ) ) {
		for ( $i = 1; $i <= 20; $i++ ) {
			foreach ( array( "gal_img_{$i}", "detay_resim_{$i}" ) as $g_alan ) {
				$g = get_field( $g_alan );
				if ( ! $g ) continue;
				if ( is_array( $g ) && ! empty( $g['url'] ) )      $galeri_urlleri[ $g['url'] ] = 1;
				elseif ( is_numeric( $g ) ) {
					$u = wp_get_attachment_image_url( (int) $g, 'full' );
					if ( $u ) $galeri_urlleri[ $u ] = 1;
				}
				elseif ( is_string( $g ) )                          $galeri_urlleri[ $g ] = 1;
			}
		}
	}

	foreach ( $GLOBALS['gbc_sm_sema'] as $url => $bilgi ) {
		if ( isset( $galeri_urlleri[ $url ] ) ) continue;
		$meta = wp_get_attachment_metadata( $bilgi['id'] );
		$node = array(
			'@type'            => 'ImageObject',
			'@id'              => $url . '#image',
			'contentUrl'       => $url,
			'url'              => $url,
			'name'             => $bilgi['alt'],
			'description'      => $bilgi['alt'],
			'representativeOfPage' => false,
			'mainEntityOfPage' => $sayfa,
			'creator'          => array( '@type' => 'Person', 'name' => GBC_SM_YAZAR ),
			'copyrightNotice'  => '© ' . GBC_SM_YAZAR . ' · gezginbirchef.com',
			'creditText'       => 'gezginbirchef.com',
		);
		if ( ! empty( $meta['width'] ) )  $node['width']  = (int) $meta['width'];
		if ( ! empty( $meta['height'] ) ) $node['height'] = (int) $meta['height'];
		if ( GBC_SM_LISANS_URL )  $node['license']            = GBC_SM_LISANS_URL;
		if ( GBC_SM_LISANS_EDIN ) $node['acquireLicensePage'] = GBC_SM_LISANS_EDIN;

		$graph[] = $node;
	}

	echo "\n<script type=\"application/ld+json\" id=\"gbc-sm-sema\">"
	   . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ),
	                     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	   . "</script>\n";
}
}


/* ==========================================================================
   6) İKONLAR — inline SVG (emoji yok, Font Awesome yok)
   ========================================================================== */
if ( ! function_exists( 'gbc_sm_ikon' ) ) {
function gbc_sm_ikon( $ad ) {
	switch ( $ad ) {
		case 'buyutec':
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>';
		case 'play':
			return '<svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.3-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14z"/></svg>';
		case 'instagram':
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.6" cy="6.4" r="1.1" fill="currentColor" stroke="none"/></svg>';
	}
	return '';
}
}


/* ==========================================================================
   7) CSS — yalnızca bileşen sayfada gerçekten kullanıldıysa basılır
   ========================================================================== */
/* CSS'i wp_head'de basıyoruz.
   NEDEN: şablon her bölümü "if ( get_field('card_x_list') )" ile kontrol
   ediyor. O kontrol de ACF filtresini tetikliyor ve filtrenin döndürdüğü
   her şey bir if koşulunun içinde kaybolup gidiyor. CSS'i filtreden
   döndürürsek ilk çağrıda çöpe gidiyor, ikinci çağrıda "zaten basıldı"
   sayılıp hiç basılmıyordu. Bu yüzden CSS içerikten tamamen ayrıldı. */
add_action( 'wp_head', 'gbc_sm_css_bas', 99 );
if ( ! function_exists( 'gbc_sm_css_bas' ) ) {
function gbc_sm_css_bas() {
	if ( is_admin() || ! is_singular() ) return;
	if ( ! function_exists( 'get_field' ) ) return;

	// wp_head döngü DIŞINDA çalışır; post ID'yi açıkça veriyoruz.
	$pid = get_queried_object_id();
	if ( ! $pid ) return;

	// Bu yazıda medya alanlarından herhangi biri dolu mu?
	$alanlar = array_values( gbc_sm_eslesme() );
	$alanlar[] = 'media_route';
	$alanlar[] = 'media_budget';

	$var = false;
	foreach ( $alanlar as $alan ) {
		$d = get_field( $alan, $pid );
		if ( is_string( $d ) && trim( $d ) !== '' ) { $var = true; break; }
	}
	if ( ! $var ) return;   // medya yoksa tek bayt CSS yüklenmez

	echo gbc_sm_css_al();
}
}

/** CSS'i BİR KEZ döndürür. İkinci çağrıda boş döner.
 *  Hem galeri (echo) hem kart enjeksiyonu (filtre çıktısına ekleme) kullanır. */
if ( ! function_exists( 'gbc_sm_css_al' ) ) {
function gbc_sm_css_al() {
	if ( ! empty( $GLOBALS['gbc_sm_kullanildi'] ) ) return '';
	$GLOBALS['gbc_sm_kullanildi'] = true;
	ob_start();
	?>
<style id="gbc-sm-css">
.gbc-sm{
  /* gbc-ux-ui · references/02 token'larına köprü */
  --gbc-sm-fg:#000; --gbc-sm-muted:#475569; --gbc-sm-border:#e0e0e0;
  --gbc-sm-card:#f9f9f9; --gbc-sm-turuncu:#e65100; --gbc-sm-turuncu-metin:#BF360C;
  --gbc-sm-r:12px; --gbc-sm-r-lg:16px;
  margin:20px 0 4px;
}
.gbc-sm-baslik{font-size:17.5px!important;line-height:1.38;font-weight:700;color:var(--gbc-sm-fg);margin:0 0 12px}

/* ---------- galeri ---------- */
.gbc-sm-galeri{gap:12px}
.gbc-sm-galeri--tek{display:block}
.gbc-sm-galeri--ikili{columns:2;column-gap:12px}
.gbc-sm-galeri--izgara{columns:3;column-gap:12px}
.gbc-sm-kutu{margin:0 0 12px;min-width:0;break-inside:avoid;-webkit-column-break-inside:avoid}
.gbc-sm-tetik{
  display:block;width:100%;padding:0;border:0;background:var(--gbc-sm-card);
  border-radius:var(--gbc-sm-r);overflow:hidden;cursor:zoom-in;position:relative;
  line-height:0;min-height:44px;
}
.gbc-sm-tetik .gbc-sm-img{
  width:100%;height:auto;display:block;transition:transform .25s ease;
}
.gbc-sm-tetik:hover .gbc-sm-img{transform:scale(1.04)}
.gbc-sm-tetik:focus-visible{outline:3px solid var(--gbc-sm-turuncu);outline-offset:2px}
.gbc-sm-buyut{
  position:absolute;right:8px;bottom:8px;width:36px;height:36px;border-radius:50%;
  background:rgba(0,0,0,.55) center/20px 20px no-repeat;
  /* büyüteç ikonu CSS'te: DOMDocument SVG'yi bozuyor (viewBox→viewbox) */
  background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>');
  opacity:0;transition:opacity .2s ease;pointer-events:none;
}
.gbc-sm-tetik:hover .gbc-sm-buyut,.gbc-sm-tetik:focus-visible .gbc-sm-buyut{opacity:1}
.gbc-sm-altyazi{
  font-size:15px!important;line-height:1.5;color:var(--gbc-sm-muted);
  margin:6px 2px 0;max-width:none;
}

/* ---------- video ---------- */
.gbc-sm-video-sira{display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;align-items:flex-start}
.gbc-sm-video{flex:1 1 320px;max-width:520px;min-width:0}
.gbc-sm-video--short{flex:0 0 240px;max-width:240px}
.gbc-sm-oynat{
  display:block;width:100%;padding:0;border:0;background:#000;cursor:pointer;
  border-radius:var(--gbc-sm-r);overflow:hidden;position:relative;line-height:0;
  aspect-ratio:16/9;
}
.gbc-sm-video--short .gbc-sm-oynat{aspect-ratio:9/16}
.gbc-sm-oynat img{width:100%;height:100%;object-fit:cover;display:block;opacity:.88;transition:opacity .2s ease}
.gbc-sm-oynat:hover img{opacity:1}
.gbc-sm-oynat:focus-visible{outline:3px solid var(--gbc-sm-turuncu);outline-offset:2px}
.gbc-sm-play{
  position:absolute;inset:0;margin:auto;width:60px;height:60px;border-radius:50%;
  display:grid;place-items:center;background:var(--gbc-sm-turuncu);color:#000;
  box-shadow:0 4px 15px rgba(0,0,0,.35);transition:transform .2s ease;
}
.gbc-sm-oynat:hover .gbc-sm-play{transform:scale(1.08)}
.gbc-sm-iframe{width:100%;aspect-ratio:16/9;border:0;border-radius:var(--gbc-sm-r);display:block}
.gbc-sm-video--short .gbc-sm-iframe{aspect-ratio:9/16}
.gbc-sm-video-ad{
  display:block;font-size:15px;line-height:1.5;color:var(--gbc-sm-muted);margin-top:6px;
}

/* ---------- instagram facade ---------- */
.gbc-sm-ig-sira{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}
.gbc-sm-ig{flex:1 1 280px;max-width:400px;min-width:0}
.gbc-sm-ig-tetik{
  display:flex;align-items:center;gap:12px;width:100%;min-height:56px;
  padding:12px 16px;text-align:left;cursor:pointer;
  background:#fff;border:1px solid var(--gbc-sm-border);border-radius:var(--gbc-sm-r);
  box-shadow:0 4px 15px rgba(0,0,0,.03);transition:box-shadow .2s ease,border-color .2s ease;
}
.gbc-sm-ig-tetik:hover{box-shadow:0 10px 30px rgba(0,0,0,.08);border-color:var(--gbc-sm-turuncu)}
.gbc-sm-ig-tetik:focus-visible{outline:3px solid var(--gbc-sm-turuncu);outline-offset:2px}
.gbc-sm-ig-ikon{flex:0 0 auto;color:var(--gbc-sm-turuncu-metin);display:grid;place-items:center}
.gbc-sm-ig-metin{flex:1 1 auto;font-size:16px;line-height:1.4;font-weight:600;color:var(--gbc-sm-fg)}
.gbc-sm-ig-alt{flex:0 0 auto;font-size:13.5px;font-weight:700;color:var(--gbc-sm-turuncu-metin);white-space:nowrap}

/* ---------- lightbox ---------- */
.gbc-sm-lb{
  position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);
  display:flex;align-items:center;justify-content:center;padding:56px 16px;
}
.gbc-sm-lb[hidden]{display:none}
#gbc-sm-lb-img{max-width:min(96vw,1400px);max-height:82vh;width:auto;height:auto;
  border-radius:8px;object-fit:contain}
.gbc-sm-lb button{
  position:absolute;background:rgba(0,0,0,.45);color:#fff;border:0;cursor:pointer;
  width:48px;height:48px;border-radius:50%;font-size:30px;line-height:1;
  display:grid;place-items:center;transition:background .2s ease;
}
.gbc-sm-lb button:hover{background:rgba(0,0,0,.75)}
.gbc-sm-lb button:focus-visible{outline:3px solid var(--gbc-sm-turuncu,#e65100);outline-offset:2px}
.gbc-sm-lb-kapat{top:12px;right:12px}
.gbc-sm-lb-onceki{left:12px;top:50%;transform:translateY(-50%)}
.gbc-sm-lb-sonraki{right:12px;top:50%;transform:translateY(-50%)}
.gbc-sm-lb-yazi{
  position:absolute;left:50%;bottom:14px;transform:translateX(-50%);
  margin:0;max-width:90vw;text-align:center;color:#fff;font-size:15px!important;
  line-height:1.5;background:rgba(0,0,0,.55);padding:8px 18px;border-radius:20px;
}

/* ---------- mobil ---------- */
@media (max-width:640px){
  .gbc-sm-galeri--izgara{columns:2;column-gap:10px}
  .gbc-sm-galeri--ikili{columns:2;column-gap:10px}
  .gbc-sm-video{flex:1 1 100%;max-width:100%}
  .gbc-sm-video--short{flex:0 0 46%;max-width:46%}
  .gbc-sm-ig{flex:1 1 100%;max-width:100%}
  .gbc-sm-buyut{opacity:1}
  .gbc-sm-lb{padding:64px 8px}
  .gbc-sm-lb-onceki{left:6px}.gbc-sm-lb-sonraki{right:6px}
}

/* ---------- hareket azaltma ---------- */
@media (prefers-reduced-motion:reduce){
  .gbc-sm *,.gbc-sm-lb *{transition:none!important;animation:none!important}
  .gbc-sm-tetik:hover .gbc-sm-img{transform:none}
  .gbc-sm-oynat:hover .gbc-sm-play{transform:none}
}

/* ---------- kart içi fotoğraf ---------- */
.gz-place-card.gbc-sm-kartli{position:relative}
.gbc-sm-kart-foto{margin:14px 0 0}
.gbc-sm-kart-tetik{
  display:block;width:100%;padding:0;border:0;background:var(--gbc-sm-card,#f9f9f9);
  border-radius:10px;overflow:hidden;cursor:zoom-in;position:relative;line-height:0;
}
.gbc-sm-kart-img{width:100%;height:auto;display:block;transition:transform .25s ease}
.gbc-sm-kart-tetik:hover .gbc-sm-kart-img{transform:scale(1.04)}
.gbc-sm-kart-tetik:focus-visible{outline:3px solid var(--gbc-sm-turuncu,#e65100);outline-offset:2px}

/* MOBİL (varsayılan): kart zaten flex-column; fotoğrafı en sona al.
   Kaynak sırada fotoğraf metinden önce duruyor (masaüstü float'ı için),
   burada görsel sırayı order ile çeviriyoruz. */
.gz-place-card.gbc-sm-kartli{display:flex;flex-direction:column}
.gz-place-card.gbc-sm-kartli .gbc-sm-kart-foto{order:99;margin:14px 0 0}

/* MASAÜSTÜ: fotoğraf sağa yaslanır, metin etrafından VE altından akar.
   Sabit sütun kullanmıyoruz; uzun metinde fotoğrafın altı boş kalmasın. */
@media (min-width:721px){
  .gz-place-card.gbc-sm-kartli{display:block}
  .gz-place-card.gbc-sm-kartli .gbc-sm-kart-foto{
    order:0;float:right;width:240px;margin:0 0 14px 20px;
  }
  /* float'ı temizle: kısa metinde fotoğraf karttan taşmasın */
  .gz-place-card.gbc-sm-kartli::after{content:"";display:table;clear:both}
  /* Arka planı olan kutular float'ın yanında sıkışmasın: her zaman
     fotoğrafın ALTINDAN, tam genişlikte başlasınlar. Düz paragraflar
     fotoğrafın etrafından akmaya devam eder. */
  .gz-place-card.gbc-sm-kartli .gz-chef-note,
  .gz-place-card.gbc-sm-kartli .gz-mini-card-box,
  .gz-place-card.gbc-sm-kartli .gz-btn-wrap{clear:right}
}
@media (min-width:721px) and (max-width:900px){
  .gz-place-card.gbc-sm-kartli .gbc-sm-kart-foto{width:190px;margin-left:16px}
}
</style>
	<?php
	return ob_get_clean();
}
}


/* ==========================================================================
   8) KART İÇİ FOTOĞRAF
      "1 | 28331 | alt metni" satırı, o bölümün metnindeki 1 numaralı
      .gz-place-card kartının içine fotoğrafı yerleştirir.

      Şablona DOKUNMAZ: ACF'nin acf/format_value/name=... kancasını kullanır.
      Yani card_places_list ekrana basılırken araya giriyoruz.

      Kırpma YOK: aspect-ratio ve object-fit kullanılmıyor. Görsel kendi
      oranıyla durur; CLS'i WordPress'in bastığı width/height nitelikleri
      karşılar (tarayıcı doğru yeri baştan ayırır).
   ========================================================================== */

/** Hangi metin alanı hangi medya alanından beslenir. */
if ( ! function_exists( 'gbc_sm_eslesme' ) ) {
function gbc_sm_eslesme() {
	return array(
		'card_places_list' => 'media_places',
		'card_food_list'   => 'media_food',
		'card_stay_list'   => 'media_stay',
		'card_night_list'  => 'media_night',
		'card_trans_list'  => 'media_trans',
		'card_trip_list'   => 'media_trip',
		'card_event_list'  => 'media_event',
		'card_hist_list'   => 'media_hist',
		'card_photo_list'  => 'media_photo',
		'card_kid_list'    => 'media_kid',
		'card_shop_list'   => 'media_shop',
		'card_safe_list'   => 'media_safe',
	);
}
}

add_action( 'init', 'gbc_sm_kanca_kur' );
if ( ! function_exists( 'gbc_sm_kanca_kur' ) ) {
function gbc_sm_kanca_kur() {
	foreach ( gbc_sm_eslesme() as $metin_alani => $medya_alani ) {
		add_filter( "acf/format_value/name={$metin_alani}", function( $deger, $post_id, $field ) use ( $medya_alani ) {
			return gbc_sm_kartlara_foto_koy( $deger, $medya_alani, $post_id );
		}, 20, 3 );
	}
}
}

if ( ! function_exists( 'gbc_sm_kartlara_foto_koy' ) ) {
function gbc_sm_kartlara_foto_koy( $html, $medya_alani, $post_id ) {

	// Yalnızca ziyaretçinin gördüğü tekil sayfada çalış. Yönetici ekranında,
	// REST'te, beslemede ve arama sonucunda ham metin bozulmasın.
	if ( is_admin() || wp_doing_ajax() || ! is_singular() || ! in_the_loop() ) return $html;
	if ( ! is_string( $html ) || strpos( $html, 'gz-place-card' ) === false ) return $html;

	$ham = get_field( $medya_alani, $post_id );
	$ogeler = gbc_sm_ayristir( $ham );

	// yalnızca kart numarası taşıyan görseller
	$kartlar = array();
	foreach ( $ogeler as $o ) {
		if ( empty( $o['kart'] ) ) continue;
		if ( $o['tip'] !== 'gorsel' && $o['tip'] !== 'gorsel_url' ) continue;
		$kartlar[ $o['kart'] ] = $o;
	}
	if ( ! $kartlar ) return $html;

	if ( ! class_exists( 'DOMDocument' ) ) return $html;

	$dom = new DOMDocument();
	$onceki = libxml_use_internal_errors( true );
	$ok = $dom->loadHTML(
		'<?xml encoding="utf-8" ?><div id="gbc-sm-kok">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $onceki );
	if ( ! $ok ) return $html;   // ayrıştırılamadıysa metne dokunma

	$xp = new DOMXPath( $dom );
	$kart_dugumleri = $xp->query( '//div[contains(concat(" ",normalize-space(@class)," ")," gz-place-card ")]' );
	if ( ! $kart_dugumleri || ! $kart_dugumleri->length ) return $html;

	$sira = 0;
	$degisti = false;

	foreach ( $kart_dugumleri as $kart ) {
		$sira++;

		// Kart numarası: rozetteki sayı varsa o, yoksa sıradaki numara
		$no = $sira;
		$rozet = $xp->query( './/div[contains(@class,"gz-num-badge")]', $kart );
		if ( $rozet && $rozet->length ) {
			$r = trim( $rozet->item( 0 )->textContent );
			if ( preg_match( '/^\d{1,3}$/', $r ) ) $no = (int) $r;
		}
		if ( empty( $kartlar[ $no ] ) ) continue;

		$o = $kartlar[ $no ];

		if ( $o['tip'] === 'gorsel' ) {
			$tam = wp_get_attachment_image_url( $o['id'], 'full' );
			if ( ! $tam ) continue;
			$alt = $o['etiket'] !== ''
				? $o['etiket']
				: trim( (string) get_post_meta( $o['id'], '_wp_attachment_image_alt', true ) );
			if ( $alt === '' ) {
				$b = $xp->query( './/div[contains(@class,"gz-inner-title")]', $kart );
				$alt = ( $b && $b->length ) ? trim( $b->item( 0 )->textContent ) : get_the_title( $post_id );
			}
			$img_html = wp_get_attachment_image( $o['id'], 'medium_large', false, array(
				'alt'      => $alt,
				'loading'  => 'lazy',
				'decoding' => 'async',
				'class'    => 'gbc-sm-kart-img',
			) );
			if ( ! $img_html ) continue;
			gbc_sm_sema_kaydet( $o['id'], $tam, $alt );
		} else {
			$tam = $o['id'];
			$alt = $o['etiket'] !== '' ? $o['etiket'] : get_the_title( $post_id );
			$img_html = '<img class="gbc-sm-kart-img" src="' . esc_url( $tam ) . '" alt="'
			          . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
		}

		$idx = gbc_sm_lightbox_kaydet( $tam, $alt );

		$parca = '<div class="gbc-sm-kart-foto">'
		       . '<button type="button" class="gbc-sm-tetik gbc-sm-kart-tetik" data-gbcsm="' . (int) $idx . '"'
		       . ' aria-label="' . esc_attr( $alt ) . ' — büyüt">'
		       . $img_html
		       . '<span class="gbc-sm-buyut" aria-hidden="true"></span>'
		       . '</button></div>';

		// parçayı DOM'a çevirip kartın SONUNA ekle (metinden sonra)
		$gecici = new DOMDocument();
		$onceki2 = libxml_use_internal_errors( true );
		$ok2 = $gecici->loadHTML( '<?xml encoding="utf-8" ?><div id="w">' . $parca . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $onceki2 );
		if ( ! $ok2 ) continue;

		$w = $gecici->getElementById( 'w' );
		if ( ! $w || ! $w->firstChild ) continue;

		/* Kaynak sırada metinden ÖNCE olmalı: masaüstünde float:right ile
		   metin fotoğrafın etrafından ve altından akabilsin diye.
		   Mobilde CSS order ile görsel olarak en sona alınıyor. */
		$yeni = $dom->importNode( $w->firstChild, true );
		$rozet_d = $xp->query( './/div[contains(@class,"gz-num-badge")]', $kart );
		$hedef = ( $rozet_d && $rozet_d->length && $rozet_d->item(0)->parentNode === $kart )
			? $rozet_d->item(0)->nextSibling
			: $kart->firstChild;
		if ( $hedef ) $kart->insertBefore( $yeni, $hedef );
		else          $kart->appendChild( $yeni );
		$kart->setAttribute( 'class', trim( $kart->getAttribute( 'class' ) . ' gbc-sm-kartli' ) );
		$degisti = true;
	}

	if ( ! $degisti ) return $html;

	$kok = $dom->getElementById( 'gbc-sm-kok' );
	if ( ! $kok ) return $html;

	$cikti = '';
	foreach ( $kok->childNodes as $c ) $cikti .= $dom->saveHTML( $c );
	if ( $cikti === '' ) return $html;

	/* DOMDocument'in HTML ayrıştırıcısı nitelik adlarını küçük harfe çevirir.
	   SVG büyük/küçük harfe duyarlıdır; kullanıcının metnindeki gömülü SVG
	   bozulmasın diye camelCase adları geri koyuyoruz. */
	$cikti = str_replace(
		array( ' viewbox=', ' preserveaspectratio=', ' clippath=', ' patternunits=',
		       ' gradientunits=', ' gradienttransform=', ' stopcolor=', ' stopopacity=',
		       ' textlength=', ' lengthadjust=', ' markerwidth=', ' markerheight=' ),
		array( ' viewBox=', ' preserveAspectRatio=', ' clipPath=', ' patternUnits=',
		       ' gradientUnits=', ' gradientTransform=', ' stopColor=', ' stopOpacity=',
		       ' textLength=', ' lengthAdjust=', ' markerWidth=', ' markerHeight=' ),
		$cikti
	);
	return $cikti;
}
}