<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 28 Eylul 2026 (Halil): "sayfadaki ilk video perde yapilmaz" kurali kaldirildi.
   Eskiden ilk iframe oldugu gibi birakiliyor, yalniz sonrakiler perdeye
   ceviriliyordu. Artik ILK VIDEO DA kapak resmi olarak gelir, tiklayinca
   oynar. VideoObject semasi bu betikten bagimsiz, oldugu gibi duruyor. */
/* 28 Eylul 2026 (2. duzeltme): adres okuma sirasi ters cevrildi.
   LiteSpeed iframe'in src'sini "about:blank" yapip gercek adresi data-src'ye
   taşıyor. Eski sira once src'yi okudugu icin "about:blank" aliniyor, icinde
   /embed/ olmadigi icin islem yarida kesiliyor ve perde hic kurulmuyordu.
   Artik once data-src okunur; about:blank gelirse bos sayilir.
   Test sayfasi: /bolonya-bologna-gezi-rotalari-plan/ */
/* Kaynak: WPCode snippet 28208 — YouTube Facade. GBC Core'a taşındı, 28 Eylül 2026. */

add_action( 'wp_footer', function () {
    if ( is_admin() ) { return; }
    /* Betik gövdesi yakalanır, sonra dosyadan bağlanır (modules/03).
       Dosya yazılamazsa eskisi gibi satır içi basılır. */
    ob_start(); ?>
(function(){
var css=".gbc-yt-facade{position:relative;width:100%;aspect-ratio:16/9;background:#000;cursor:pointer;border-radius:8px;overflow:hidden;display:block}"
+".gbc-yt-facade img.gbc-yt-thumb{width:100%;height:100%;object-fit:cover;display:block;image-rendering:auto}"
+".gbc-yt-play{position:absolute;inset:0;margin:auto;width:68px;height:48px;background:rgba(0,0,0,.65);border:0;border-radius:14px;cursor:pointer;padding:0;transition:background .15s}"
+".gbc-yt-play:after{content:'';position:absolute;top:50%;left:54%;transform:translate(-50%,-50%);border-style:solid;border-width:11px 0 11px 19px;border-color:transparent transparent transparent #fff}"
+".gbc-yt-facade:hover .gbc-yt-play,.gbc-yt-facade:focus-visible .gbc-yt-play{background:#f00}"
+".gbc-yt-facade iframe{position:absolute;inset:0;width:100%;height:100%;border:0}";
var s=document.createElement('style');s.id='gbc-yt-css';s.textContent=css;document.head.appendChild(s);
/* v2: LCP icin i.ytimg.com'a HEMEN preconnect. Onceden sadece hover/touch
   aninda yapiliyordu; hero kapagi o zamana kadar bekliyordu. */
(function(){try{var pl=document.createElement('link');pl.rel='preconnect';pl.href='https://i.ytimg.com';document.head.appendChild(pl);var pd=document.createElement('link');pd.rel='dns-prefetch';pd.href='https://i.ytimg.com';document.head.appendChild(pd);}catch(e){}})();
var gbcFacadeCount=0;
var warmed=false;
function warm(){if(warmed)return;warmed=true;['https://www.youtube-nocookie.com','https://i.ytimg.com'].forEach(function(h){var l=document.createElement('link');l.rel='preconnect';l.href=h;document.head.appendChild(l);});}
function play(f,id){if(f.dataset.gbcPlaying)return;f.dataset.gbcPlaying='1';var r=document.createElement('iframe');r.dataset.gbcDone='1';r.src='https://www.youtube-nocookie.com/embed/'+id+'?autoplay=1&playsinline=1&rel=0&enablejsapi=1&origin='+encodeURIComponent(location.origin);/* 21 Eyl 2026: enablejsapi bastan ekleniyor. Yoksa 70 numaranin olcum kodu src'yi yeniden yaziyor, video iki kez yukleniyordu ve GA4 bu videolari hic olcmuyordu. */r.setAttribute('allow','accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');r.setAttribute('allowfullscreen','');r.setAttribute('title','YouTube video');f.innerHTML='';f.appendChild(r);}
/* Kapak cozunurlugu: maxresdefault (1280x720) once denenir. YouTube eksik kapak icin 404 DEGIL, 120x90 gri bir vekil gorsel donuyor; o yuzden onerror yetmez, yuklenen gorselin genisligi de olculuyor. Merdiven: maxresdefault -> hq720 -> sddefault -> hqdefault -> 0. hqdefault 480x360 (4:3) oldugu icin 16:9 kutuda kirpilip bulaniklasiyordu — 28 Eyl 2026. */
var YT_KAPAK=['maxresdefault','hq720','sddefault','hqdefault','0'];
function setThumb(img,id,i){i=i||0;img.onload=function(){if(img.naturalWidth&&img.naturalWidth<=200&&i<YT_KAPAK.length-1){setThumb(img,id,i+1);}else{img.onload=null;img.onerror=null;}};img.onerror=function(){if(i<YT_KAPAK.length-1){setThumb(img,id,i+1);}else{img.onload=null;img.onerror=null;}};img.src='https://i.ytimg.com/vi/'+id+'/'+YT_KAPAK[i]+'.jpg';}
function makeFacade(id){var f=document.createElement('div');f.className='gbc-yt-facade';f.setAttribute('role','button');f.setAttribute('tabindex','0');f.setAttribute('aria-label','Videoyu oynat');var img=document.createElement('img');img.className='gbc-yt-thumb';img.alt='';img.width=1280;img.height=720;img.decoding='async';gbcFacadeCount++;if(gbcFacadeCount===1){img.loading='eager';img.setAttribute('fetchpriority','high');img.setAttribute('data-no-lazy','1');}else{img.loading='lazy';}setThumb(img,id,0);f.appendChild(img);var b=document.createElement('button');b.className='gbc-yt-play';b.type='button';b.setAttribute('aria-label','Videoyu oynat');f.appendChild(b);f.addEventListener('pointerenter',warm,{once:true});f.addEventListener('touchstart',warm,{once:true,passive:true});f.addEventListener('click',function(){play(f,id);});f.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();play(f,id);}});return f;}
/* v3: SAYFADAKI ILK YouTube videosu facade YAPILMAZ.
   Gerekce: Google video zengin sonucu icin oynaticinin kullanici eylemi
   olmadan yuklenmesini istiyor. Ilk video genelde hero videosu ve
   VideoObject semasinin isaret ettigi video; gercek iframe olarak
   birakiliyor, lazy src'si hemen aciliyor. Diger videolar facade kalir. */
function build(){[].forEach.call(document.getElementsByTagName('iframe'),function(ifr){if(ifr.dataset.gbcDone)return;var src=ifr.getAttribute('data-src')||ifr.getAttribute('src')||'';if(src==='about:blank')src='';var p=src.indexOf('/embed/');if(p===-1)return;if(src.indexOf('youtube.com')===-1&&src.indexOf('youtube-nocookie.com')===-1)return;var id=src.substr(p+7,11);if(!/^[\w-]{11}$/.test(id))return;var f=makeFacade(id);f.dataset.gbcDone='1';ifr.parentNode.replaceChild(f,ifr);});}
if(document.readyState!=='loading')build();else document.addEventListener('DOMContentLoaded',build);
})();
<?php
    $gbc_yt_js = (string) ob_get_clean();
    if ( function_exists( 'gbc_js_bas' ) ) {
        gbc_js_bas( 'yt-facade', $gbc_yt_js, 'gbc-yt-facade' );
    } else {
        echo '<script id="gbc-yt-facade">' . $gbc_yt_js . '</script>';
    }
}, 20 );

/* ── LCP: ilk videonun kapak gorseli ─────────────────────────────────
   Olculdu (28 Eyl 2026): perde JS ile kuruldugu icin kapak gorselini
   tarayici gec kesfediyor; Rota'da mobil LCP 5,8 sn cikti.
   Cozum: sayfanin icerigindeki ILK YouTube videosunun kimligi sunucuda
   bulunur ve kapak gorseli <head>'de on yuklenir. Boylece tarayici
   gorseli JS'i beklemeden indirmeye baslar.
   Kapak maxresdefault (1280x720): hqdefault 480x360 ve 4:3 oldugu icin
   16:9 kutuda kirpilip bulaniklasiyordu. Eksikse tarayici merdivenden
   asagi iniyor (hq720 -> sddefault -> hqdefault); vekil gri gorsel
   genislikten anlasiliyor.                                               */
if ( ! function_exists( 'gbc_yt_ilk_video' ) ) {
function gbc_yt_ilk_video( $icerik ) {
	if ( ! is_string( $icerik ) || '' === $icerik ) { return ''; }
	$kaliplar = array(
		'#youtube(?:-nocookie)?\.com/embed/([\w-]{11})#i',
		'#youtube\.com/watch\?v=([\w-]{11})#i',
		'#youtu\.be/([\w-]{11})#i',
	);
	foreach ( $kaliplar as $k ) {
		if ( preg_match( $k, $icerik, $m ) ) { return $m[1]; }
	}
	return '';
}
}

if ( ! function_exists( 'gbc_yt_metadan_video' ) ) {
/**
 * Yazının meta alanlarındaki ilk YouTube kimliği.
 * ACF alanları (video_url, related_videos, hero_video …) burada durur.
 * Sonuç bir günlük post meta'sına yazılır; meta taraması tekrarlanmaz.
 */
function gbc_yt_metadan_video( $pid ) {
	$onbellek = get_post_meta( $pid, '_gbc_yt_ilk', true );
	if ( is_array( $onbellek ) && isset( $onbellek['id'], $onbellek['zaman'] )
		&& ( time() - (int) $onbellek['zaman'] ) < DAY_IN_SECONDS ) {
		return (string) $onbellek['id'];
	}

	$id  = '';
	$tum = get_post_meta( $pid );
	if ( is_array( $tum ) ) {
		foreach ( $tum as $anahtar => $degerler ) {
			if ( '_' === substr( (string) $anahtar, 0, 1 ) ) { continue; }  /* gizli alanlar */
			foreach ( (array) $degerler as $d ) {
				if ( ! is_string( $d ) || '' === $d ) { continue; }
				$id = gbc_yt_ilk_video( $d );
				if ( '' === $id && preg_match( '/^[\w-]{11}$/', trim( $d ) ) ) {
					/* Alan yalnız kimliği tutuyor olabilir: alan adı video diyorsa kabul et. */
					if ( false !== stripos( (string) $anahtar, 'video' ) || false !== stripos( (string) $anahtar, 'yt' ) ) {
						$id = trim( $d );
					}
				}
				if ( '' !== $id ) { break 2; }
			}
		}
	}

	update_post_meta( $pid, '_gbc_yt_ilk', array( 'id' => $id, 'zaman' => time() ) );
	return $id;
}
}

if ( ! function_exists( 'gbc_yt_kapak_onyukle' ) ) {
function gbc_yt_kapak_onyukle() {
	if ( is_admin() || ! is_singular() ) { return; }
	$pid = get_queried_object_id();
	if ( ! $pid ) { return; }

	/* 1) Yazının kendi içeriği. */
	$id = gbc_yt_ilk_video( (string) get_post_field( 'post_content', $pid ) );

	/* 2) İçerik yalnızca kısa koddan ibaretse video ACF alanındadır.
	      Ölçüldü (28 Eylül 2026, /bolonya-bologna-gezi-rotalari-plan/):
	      post_content sadece [wpcode id="23340"][wpcode id="23342"] —
	      video snippet'in okuduğu alandan geliyor, ham içerikte hiç geçmiyor.
	      Bu yüzden kapak ön yüklemesi hiçbir şablonda basılmıyordu.
	      Çözüm: yazının meta alanlarında da ara. Sonuç günlük önbelleğe
	      alınır, her istekte meta taranmaz. */
	if ( '' === $id ) {
		$id = gbc_yt_metadan_video( $pid );
	}

	if ( '' === $id ) { return; }

	$GLOBALS['gbc_yt_onyuklendi'] = true;
	echo '<link rel="preload" as="image" fetchpriority="high" id="gbc-yt-onyukle"'
		. ' href="https://i.ytimg.com/vi/' . esc_attr( $id ) . '/maxresdefault.jpg">' . "\n";
}
add_action( 'wp_head', 'gbc_yt_kapak_onyukle', 2 );
}

/* ============================================================
   ÜÇÜNCÜ KATMAN — İÇERİKTEN ÖN YÜKLEME

   <head> taraması iki yere bakıyor: yazının içeriği ve meta alanları.
   Video bunların ikisinde de olmayabilir: Rota şablonunda post_content
   yalnızca [wpcode id="23340"] kısa kodundan ibaret, video snippet'in
   kendi sorgusundan geliyor (28 Eylül 2026'da ölçüldü — sayfada
   /embed/w4TA94KWtu4 var ama ham içerikte hiç geçmiyor).

   Bu süzgeç İŞLENMİŞ içeriğe bakar, yani orada ne varsa onu görür.
   <head>'de ön yükleme zaten basıldıysa hiç karışmaz. Etiket gövdenin
   en başına konur; tarayıcının ön tarama (preload scanner) mekanizması
   onu görselden çok önce görür.
   ============================================================ */
if ( ! function_exists( 'gbc_yt_icerikten_onyukle' ) ) {
function gbc_yt_icerikten_onyukle( $icerik ) {
	if ( ! empty( $GLOBALS['gbc_yt_onyuklendi'] ) ) { return $icerik; }
	if ( is_admin() || is_feed() || ! is_singular() ) { return $icerik; }
	if ( ! in_the_loop() || ! is_main_query() ) { return $icerik; }
	if ( ! is_string( $icerik ) || '' === $icerik ) { return $icerik; }

	$id = gbc_yt_ilk_video( $icerik );
	if ( '' === $id ) { return $icerik; }

	$GLOBALS['gbc_yt_onyuklendi'] = true;

	return '<link rel="preload" as="image" fetchpriority="high" id="gbc-yt-onyukle"'
		. ' href="https://i.ytimg.com/vi/' . esc_attr( $id ) . '/maxresdefault.jpg">'
		. $icerik;
}
add_filter( 'the_content', 'gbc_yt_icerikten_onyukle', 999 );
}
