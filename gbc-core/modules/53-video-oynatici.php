<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 31178 — Liste Kartı Video Oynatıcı. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 28 · Liste Kartı Video Oynatıcı · v1.1 (22 Eyl 2026; iframe JS ile kuruluyor, LiteSpeed lazy dokunmasın)
 * Liste şablonundaki kartlarda "YouTube'da izle" çipi siteden çıkarmaz:
 * video kartın içinde, o saniyeden açılır. Ana sayfadan gelen bağlantı
 * #izle=VIDEO&t=SANIYE&k=place-N ile doğru karta iner ve oynatıcıyı hazırlar.
 */
add_action( 'wp_footer', function () {
	if ( ! is_singular() ) { return; }
	echo '<style id="gbc-vp-css">';
	echo <<<'CSS'
.gbc-vp{margin:12px 0 14px;position:relative}
.gbc-vp-kutu{position:relative;width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000;box-shadow:0 2px 10px rgba(0,0,0,.15)}
.gbc-vp-kisa .gbc-vp-kutu{aspect-ratio:9/16;max-width:360px;margin:0 auto}
.gbc-vp-kutu iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.gbc-vp-kapat{display:inline-block;margin-top:8px;padding:6px 14px;border:1px solid #e0e0e0;border-radius:999px;background:#fff;color:#1f1f1f;font-size:14px;font-weight:600;cursor:pointer}
.gbc-vp-kapat:hover{border-color:#BF360C;color:#BF360C}
CSS;
	echo '</style><script id="gbc-vp-js">';
	echo <<<'JS'
(function(){
if(!document.querySelector('article.v1-card-item'))return;
function yt(h){try{var u=new URL(h,location.href);var id='',t=0;
if(/youtu\.be$/.test(u.hostname))id=u.pathname.slice(1);
else if(/youtube\.com$/.test(u.hostname.replace(/^www\.|^m\./,''))||/youtube\.com$/.test(u.hostname)){if(u.pathname.indexOf('/shorts/')===0)id=u.pathname.split('/')[2];else if(u.pathname.indexOf('/embed/')===0)id=u.pathname.split('/')[2];else id=u.searchParams.get('v')||'';}
var ts=u.searchParams.get('t')||u.searchParams.get('start')||'';if(ts){var m=String(ts).match(/(?:(\d+)h)?(?:(\d+)m)?(\d+)s?$/);t=m?((+m[1]||0)*3600+(+m[2]||0)*60+(+m[3]||0)):parseInt(ts,10)||0;}
return id?{id:id,t:t,kisa:u.pathname.indexOf('/shorts/')===0}:null;}catch(e){return null;}}
function kapat(){var o=document.querySelectorAll('.gbc-vp');for(var i=0;i<o.length;i++)o[i].parentNode.removeChild(o[i]);}
function ac(kart,v,oto,cip){kapat();var k=document.createElement('div');k.className='gbc-vp'+(v.kisa?' gbc-vp-kisa':'');
var src='https://www.youtube.com/embed/'+encodeURIComponent(v.id)+'?start='+v.t+'&rel=0&playsinline=1&modestbranding=1'+(oto?'&autoplay=1':'');
var ku=document.createElement('div');ku.className='gbc-vp-kutu';var fr=document.createElement('if'+'rame');fr.setAttribute('title','Videoda izle');fr.setAttribute('allow','autoplay; encrypted-media; picture-in-picture; fullscreen');fr.setAttribute('allowfullscreen','');fr.setAttribute('data-no-lazy','1');fr.className='skip-lazy';ku.appendChild(fr);k.appendChild(ku);var bt=document.createElement('button');bt.type='button';bt.className='gbc-vp-kapat';bt.setAttribute('aria-label','Videoyu kapat');bt.textContent='Kapat';k.appendChild(bt);
var yer=(cip&&cip.closest('.gz-inner-tags'))||kart.querySelector('.gz-inner-tags')||kart.querySelector('h2,h3');
if(yer&&yer.parentNode)yer.parentNode.insertBefore(k,yer.nextSibling);else kart.insertBefore(k,kart.firstChild);
fr.setAttribute('src',src);bt.addEventListener('click',kapat);return k;}
document.addEventListener('click',function(e){var a=e.target.closest?e.target.closest('a.gz-vid-cip'):null;if(!a)return;var kart=a.closest('article.v1-card-item');if(!kart)return;var v=yt(a.getAttribute('href'));if(!v)return;e.preventDefault();ac(kart,v,true,a);});
function hashtan(){var h=location.hash.slice(1);if(h.indexOf('izle=')!==0)return;var p=new URLSearchParams(h);var id=p.get('izle'),t=parseInt(p.get('t')||'0',10)||0,kid=p.get('k')||'';var kart=kid?document.getElementById(kid):null;if(!id||!kart)return;
var cip=null,cs=kart.querySelectorAll('a.gz-vid-cip');for(var i=0;i<cs.length;i++){var v=yt(cs[i].getAttribute('href'));if(v&&v.id===id){cip=cs[i];break;}}
var k=ac(kart,{id:id,t:t,kisa:false},false,cip);function git(){var y=k.getBoundingClientRect().top+window.pageYOffset-140;window.scrollTo(0,y);}setTimeout(git,300);setTimeout(git,1200);window.addEventListener('load',function(){setTimeout(git,200);});}
function etiket(){var cs=document.querySelectorAll('article.v1-card-item a.gz-vid-cip');for(var i=0;i<cs.length;i++){var n=cs[i].childNodes;for(var j=0;j<n.length;j++){if(n[j].nodeType===3&&/YouTube.da izle/.test(n[j].nodeValue))n[j].nodeValue=n[j].nodeValue.replace(/YouTube.da izle/,'Videoda izle');}cs[i].removeAttribute('target');}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){etiket();hashtan();});else{etiket();hashtan();}
window.addEventListener('hashchange',hashtan);
})();
JS;
	echo '</script>';
}, 99 );
