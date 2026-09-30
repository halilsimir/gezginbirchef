<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 28270 — Analytics + Reklam (GA4 ölçüm). GBC Core'a taşındı, 28 Eylül 2026. */

/* GBC·Analytics + Reklam */

/* 1) GA4 ÖLÇÜM MOTORU — 15 Eylül 2026'da yeniden yazıldı.

   ESKİ KUSUR (canlıda ölçüldü):
   a) gtag yalnızca ilk etkileşimde ya da 5 saniye sonra yükleniyordu.
      5 saniye dolmadan ayrılan okur hiç sayılmıyordu; GA4 trafiği
      olduğundan az gösteriyordu ve Google Ads yanlış veriyle çalışıyordu.
   b) Dış/ortaklık tıklama takibi (snippet 28211) sayfaya HİÇ basılmıyordu.
      Canlı sayfada arandı: ne satır içi betiklerde ne LiteSpeed birleşik
      JS dosyalarında var. Basılsaydı bile ilk satırındaki
      "if(typeof window.gtag!=='function') return" yüzünden gtag henüz
      yüklenmediği için her seferinde geri dönerdi.
      SONUÇ: bugüne kadar tek bir dış bağlantı ya da ortaklık tıklaması
      kaydedilmedi. Ortaklık geliri ölçülemiyordu.

   YENİ YAPI: gtag kuyruğu <head> içinde senkron kurulur, kütüphane async
   gelir. Kütüphane gelmeden atılan olaylar dataLayer kuyruğunda bekler ve
   kütüphane gelince gönderilir; hiçbir olay kaybolmaz. */
add_action('wp_head', function () {
    echo '<script id="gbc-ga4-boot">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","G-8P6FY60W1W");</script>' . "\n";
    echo '<script async src="https://www.googletagmanager.com/gtag/js?id=G-8P6FY60W1W"></script>' . "\n";
}, 1);

/* 1b) TIKLAMA MOTORU — altbilgide */
add_action('wp_footer', function () {
    if (is_admin()) { return; }

    /* Sayfa tipi — raporlar bu kırılımla okunacak. Şablonu post_content
       içindeki [wpcode id=] belirliyor (envanterle birebir aynı numaralar). */
    $pt = 'diger'; $pid = 0;
    if (is_singular()) {
        $pid = (int) get_the_ID();
        $pt  = 'yazi';
        $map = array('24751'=>'tarif','22607'=>'gezi','23108'=>'liste','23489'=>'detay','23340'=>'rota','26922'=>'blog','24156'=>'sozluk');
        $c   = (string) get_post_field('post_content', $pid);
        if (preg_match('/\[wpcode[^\]]*id=["\']?(\d+)/', $c, $m) && isset($map[$m[1]])) { $pt = $map[$m[1]]; }
    } elseif (is_front_page() || is_home()) { $pt = 'anasayfa'; }
    elseif (is_search()) { $pt = 'arama'; }
    elseif (is_404())    { $pt = '404'; }
    elseif (is_category() || is_tag() || is_archive()) { $pt = 'arsiv'; }

    /* DÖRT OLAY:
         affiliate_click -> ortaklık bağlantısı (data-aff, .gbc-in ya da rel=sponsored)  [ANAHTAR OLAY]
         share_click     -> sosyal paylaşım
         video_click     -> YouTube
         outbound_click  -> kalan bütün dış bağlantılar
       ORTAK PARAMETRELER: link_domain, link_url, link_text, placement,
       section, page_type, post_id, scroll_pct.
       affiliate_click ayrıca: aff_program, aff_id.

       aff_id defterdeki (30120) sub_id ile BİREBİR aynı (bk_rodos,
       gyg_rodos_oldtown, dc_cagliari...). Böylece GA4 raporu ile
       Travelpayouts/Impact paneli satır satır karşılaştırılabiliyor.

       TUZAK: Travelpayouts betiği yükleme sonrası href-i emrldtp.cc
       adresine çeviriyor. O yüzden program ve id href-ten DEĞİL,
       data-prog / data-aff özniteliklerinden okunuyor (bunları
       snippet 30204 Ortaklık Bağlantı Motoru basıyor).

       section = bağlantının üstündeki en yakın H2/H3. Hangi bölümün
       tıklattığını gösterir; ortaklık yerleşimi bu veriyle optimize edilir. */
    $js  = 'var PT="' . $pt . '",PID=' . (int) $pid . ';';
    $js .= 'var SOC=/facebook\.com|instagram\.com|twitter\.com|x\.com|pinterest\.|wa\.me|t\.me|linkedin\./i,VID=/youtube\.com|youtu\.be/i;';
    $js .= 'function G(){if(window.gtag)window.gtag.apply(null,arguments);}';
    $js .= 'function H(a){try{return new URL(a.href,location.href).hostname;}catch(e){return "";}}';
    $js .= 'function SCR(){var d=document.documentElement,m=d.scrollHeight-window.innerHeight;return m>0?Math.min(100,Math.round((window.pageYOffset||d.scrollTop||0)/m*100)):0;}';
    $js .= 'function PL(a){if(!a.closest)return "diger";';
    $js .= 'if(a.closest(".gz-aff-son"))return "sayfa-sonu";';
    $js .= 'if(a.closest(".gz-sidebar-column,.gz2-hizli,.gz2-quick,.gbc-affk-a"))return "yan-sutun";';
    $js .= 'if(a.closest(".gz-author-box"))return "yazar-kutusu";';
    $js .= 'if(a.closest(".gbc-ad"))return "reklam";';
    $js .= 'if(a.closest(".entry-content"))return "govde";';
    $js .= 'if(a.closest("header,.site-header"))return "ust-menu";';
    $js .= 'if(a.closest("footer,.site-footer"))return "alt-menu";return "diger";}';
    /* SEC v2 (15 Eylül 2026) — BELGE SIRASINA göre en yakın önceki başlık.
       v1 kardeş-düğüm tırmanışı yapıyordu ve yan sütundaki bağlantılara
       gövdedeki alakasız H2'yi yazıyordu (ölçüldü: yan sütun bağlantısına
       "Sardunya Adası Kaç Günde Gezilir?" yazdı). Düz gezinme doğru sonucu
       veriyor: bağlantıya ulaşana kadar görülen son başlık. */
    $js .= 'function SEC(a){var w=document.createTreeWalker(document.body,NodeFilter.SHOW_ELEMENT,null,false),n,h="(bolumsuz)",c=0;while((n=w.nextNode())&&c<8000){c++;if(n===a)break;if(/^H[1-4]$/.test(n.tagName)){var t=n.textContent.replace(/\s+/g," ").trim();if(t)h=t.slice(0,90);}}return h;}';
    $js .= '';
    $js .= '';
    $js .= '';
    $js .= 'var LAST=0;document.addEventListener("click",function(e){';
    $js .= 'var a=(e.target&&e.target.closest)?e.target.closest("a[href]"):null;if(!a)return;';
    $js .= 'var h=H(a);if(!h||h===location.hostname)return;';
    $js .= 'var t=Date.now();if(t-LAST<150)return;LAST=t;';
    $js .= 'var id=a.getAttribute("data-aff")||"",pr=a.getAttribute("data-prog")||"",rel=(a.getAttribute("rel")||"").toLowerCase(),cls=(a.getAttribute("class")||"");';
    $js .= 'var aff=(!!id)||cls.indexOf("gbc-in")>-1||rel.indexOf("sponsored")>-1;';
    $js .= 'var d={link_domain:h,link_url:String(a.href).slice(0,100),link_text:(a.textContent||"").replace(/\s+/g," ").trim().slice(0,90),placement:PL(a),section:SEC(a),page_type:PT,post_id:PID,scroll_pct:SCR()};';
    $js .= 'if(aff){d.aff_program=pr||h;d.aff_id=id||"(idsiz)";G("event","affiliate_click",d);}';
    $js .= 'else if(SOC.test(h)){G("event","share_click",d);}';
    $js .= 'else if(VID.test(h)){G("event","video_click",d);}';
    $js .= 'else{G("event","outbound_click",d);}},true);';
    /* VIDEO ÖLÇÜMÜ — 16 Eylül 2026
       BULGU (ölçüldü): sitedeki YouTube gömülerinin hiçbirinde
       enablejsapi=1 yok. GA4'ün yerleşik video ölçümü bu parametre
       olmadan ÇALIŞMAZ; yani bugüne kadar "videoyu kaç kişi başlattı,
       ne kadarını izledi" verisi hiç toplanmadı.
       Ayrıca iframe'ler tembel yükleniyor: adres src'de değil data-src'de
       duruyor ve okur aşağı inince yerleşiyor. Bu yüzden sayfa açılışında
       bir kez bakmak yetmiyor, MutationObserver ile izleniyor.
       Bu blok yalnız enablejsapi=1 ekler; olayları GA4'ün kendi video
       ölçümü üretir (video_start / video_progress / video_complete,
       yanında video_title, video_percent, video_duration).
       ÇİFT SAYIM NOTU: bu yüzden ayrı bir video izleyici YAZILMADI. */
    /* DÜZELTME — 16 Eylül 2026, canlıda ölçüldü. İLK SÜRÜMÜN KUSURU: h = src || data-src yazıyordu. Tembel yükleyici iframe'in src'sini "about:blank" yapıyor; about:blank boş bir değer değil, o yüzden h="about:blank" oluyor ve YouTube deseni hiç tutmuyordu. Plachutta sayfasında ölçüldü: data-src "youtube.com/embed/xIrsGpOyEIo?feature=oembed" iken enablejsapi eklenmemişti. Artık src boş, about:blank ya da data: ise adres data-src'den okunuyor ve oraya yazılıyor. */
    $js .= 'function YT_AC(f){var s=f.getAttribute("src")||"",ds=f.getAttribute("data-src")||"";';
    $js .= 'var dsKullan=(!s||s==="about:blank"||s.indexOf("data:")===0),h=dsKullan?ds:s;';
    $js .= 'if(!/youtube\.com\/embed|youtube-nocookie\.com\/embed/.test(h))return;';
    $js .= 'if(/[?&]enablejsapi=1/.test(h))return;';
    $js .= 'var y=h+(h.indexOf("?")>-1?"&":"?")+"enablejsapi=1&origin="+encodeURIComponent(location.origin);';
    $js .= 'if(dsKullan)f.setAttribute("data-src",y);else f.setAttribute("src",y);}';
    $js .= 'function YT_TARA(k){if(!k||k.nodeType!==1)return;if(k.tagName==="IFRAME")YT_AC(k);';
    $js .= 'if(k.querySelectorAll){var l=k.querySelectorAll("iframe");for(var i=0;i<l.length;i++)YT_AC(l[i]);}}';
    $js .= 'YT_TARA(document.body);';
    $js .= 'new MutationObserver(function(ms){for(var i=0;i<ms.length;i++){var m=ms[i];';
    $js .= 'if(m.type==="attributes"&&m.target.tagName==="IFRAME"){YT_AC(m.target);continue;}';
    $js .= 'for(var j=0;j<m.addedNodes.length;j++)YT_TARA(m.addedNodes[j]);}})';
    $js .= '.observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:["src","data-src"]});';

    $js .= 'var MK=[25,50,75,100],HT={},TT=0;window.addEventListener("scroll",function(){var n=Date.now();if(n-TT<300)return;TT=n;var p=SCR();';
    $js .= 'for(var i=0;i<MK.length;i++){var m=MK[i];if(p>=m&&!HT[m]){HT[m]=1;G("event","scroll_depth",{scroll_pct:m,page_type:PT,post_id:PID});}}},{passive:true});';

    echo '<script id="gbc-olcum">(function(){' . $js . '})();</script>';
}, 20);

/* 2) Manuel AdSense — sadece tekil yazılar; içerik arası + içerik sonu */
add_filter('the_content', function ($content) {
    if (is_admin() || is_feed()) return $content;
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    if (strpos($content, 'gbc-ad-ins') !== false) return $content;

    $client = 'ca-pub-7154604851132509';
    $mid = '1732706452';
    $end = '8331949997';
    $box = function ($slot) use ($client) {
        return '<div class="gbc-ad"><ins class="adsbygoogle gbc-ad-ins" style="display:block;width:100%" data-ad-client="' . $client . '" data-ad-slot="' . $slot . '" data-ad-format="auto" data-full-width-responsive="true"></ins></div>';
    };

    /* REKLAM YERI — 2026-08-29 duzeltmesi.
       ESKI KUSUR: explode('</p>') ile sayfadaki 3. paragraf etiketinden
       sonra basiliyordu. Sablonla uretilen sayfalarda o 3. </p> bir
       BILESENIN icinde kaliyor. Atina'da olculdu: 280px reklam
       "Nasil Gidilir" satirinin paragrafi ile is ortakligi butonunun
       ARASINA giriyor, butonu ekran disina itiyordu. Oncelik affiliate
       oldugu icin bu dogrudan gelir kaybiydi.
       YENI KURAL: sayfa <article> bolumlerinden olusuyorsa reklam
       yalnizca IKI BOLUM ARASINA girer, hicbir bilesenin icine giremez.
       Duz yazilarda (bolum yok) eski paragraf davranisi korunur. */
    /* SINIR BULUCU — 2026-08-29
       Her sablon bolumlerini baska etiketle isaretliyor. Canli olculdu:
         Gezi (20)  : 7  x <article class="gz-content-box gz-sec">
         Liste (22) : 29 x <article class="v1-card-item">
         Tarif (50) : 0  article — her sey .tr-wrap icinde .tr-card kartlari
       Bu yuzden tek bir etiket yetmiyor. Aday etiketler sirayla denenir,
       yeterli sinir ureten ilki kullanilir.
       HICBIRI TUTMAZSA ara reklam BASILMAZ. Eskiden burada </p> ile
       korlemesine bolen bir yedek vardi; kartin ortasina reklam sokan
       kusur oydu. Bir reklam yuvasi kaybetmek, okuma akisini yarmaktan
       ve is ortakligi butonunu ekran disina itmekten iyidir. */
    $adaylar = array( '</article>', '<div class="tr-card"' );
    $sec = array( '' );
    foreach ( $adaylar as $aday ) {
        $dene = explode( $aday, $content );
        if ( count( $dene ) > 3 ) { $sec = $dene; $tag_bul = $aday; break; }
    }
    $once = ( isset( $tag_bul ) && substr( $tag_bul, 0, 2 ) !== '</' );
    $bolumlu = count($sec) > 3; /* v2 */
    $p   = $bolumlu ? $sec : explode('</p>', $content);
    $tag = $bolumlu ? $tag_bul : '</p>';
    /* KURAL — 2026-08-29
       Affiliate VARSA sayfa zaten kazaniyor: TEK ara reklam, ve o reklam
       affiliate'in bulundugu bolumden sonra gelmez (yan yana durunca ikisi
       de kaybediyor; reklam butonun dikkatini, buton reklamin tiklamasini
       caliyor). Affiliate YOKSA (yemek, sozluk, tarif, SSS agirlikli
       sayfalar) tek gelir reklam: IKI ara reklam.
       Her ikisi de yalnizca BOLUM ARASINA girer, hicbir kartin/bilesenin
       icine giremez. */
    $af  = (strpos($content, 'gbc-aff') !== false);
    $at  = $bolumlu ? 2 : 3;

    /* ================================================================
       REKLAM YERLESTIRME MOTORU — 2026-08-29
       Sayfa <article> bolumlerinden olusuyorsa reklamlar BURADA
       yerlestirilir; asagidaki eski paragraf mantigi devreye girmez.
       (Eski mantik yalnizca duz blog yazilari icin yedekte duruyor.)

       Uc kural, ucu de otomatik. Bir sayfaya affiliate ekledigin an
       yerlesim kendini yeniden hesaplar, hicbir elle ayar gerekmez:

       1) Reklam yalnizca IKI BOLUM ARASINA girer. Kartin, cipin,
          paragrafin icine asla. (Eski kusur buydu: Atina'da reklam
          ucak metni ile is ortakligi butonunun arasina giriyordu.)
       2) Icinde affiliate olan bolumun HEM ONUNE HEM ARKASINA reklam
          konmaz. Buton ile reklam komsu olunca ikisi de kaybediyor:
          reklam butonun dikkatini, buton reklamin tiklamasini caliyor.
          Iki bolum otedeki reklam ise butona zarar vermiyor.
       3) Yogunluk: iki reklam arasi en az 3 bolum, tavan 3 ara reklam.
          Bir ekran yuksekliginde iki reklam yan yana gelmez.

       Sonuc: affiliate'i bol sayfa az reklam alir (zaten kazaniyor),
       affiliate'siz sayfa (tarif, sozluk) tavana kadar doldurulur.
       ================================================================ */
    $yerlesti = false;
    if ($bolumlu) {
        $n     = count($p) - 1;
        $aff_b = array();
        for ($i = 0; $i < $n; $i++) {
            if (strpos($p[$i], 'gbc-aff') !== false) { $aff_b[] = $i; }
        }

        /* Reklamlar sayfaya ESIT DAGILIR, basa yigilmaz.
           29 kartli bir Liste sayfasinda 2-5-8. kartlara sikismasin diye
           adim = bolum sayisi / (reklam sayisi + 1). */
        $tavan = min(3, max(1, (int) floor($n / 3)));
        $adim  = max(3, (int) floor($n / ($tavan + 1)));
        $koy   = array();
        $son   = -99;
        for ($k = 1; $k <= $tavan; $k++) {
            $b = max(2, $adim * $k);
            /* Affiliate bulunan bolumun onune de arkasina da reklam konmaz;
               sinir bulunana kadar ileri kayar. */
            while ($b < $n && (in_array($b - 1, $aff_b) || in_array($b, $aff_b) || $b - $son < 3)) { $b++; }
            if ($b >= $n) { break; }
            $koy[] = $b;
            $son   = $b;
        }

        if ($koy) {
            $out = '';
            $t   = count($p);
            for ($i = 0; $i < $t; $i++) {
                $out .= $p[$i];
                /* Kapanis etiketinde reklam ETIKETTEN SONRA, acilis
                   etiketinde ETIKETTEN ONCE gelir (tr-card gibi). */
                if (in_array($i + 1, $koy, true) && $once) { $out .= $box($mid); }
                if ($i < $t - 1) { $out .= $tag; }
                if (in_array($i + 1, $koy, true) && ! $once) { $out .= $box($mid); }
            }
            $content  = $out;
            $yerlesti = true;
        }
    }
    while ($bolumlu && isset($p[$at - 1]) && strpos($p[$at - 1], 'gbc-aff') !== false && $at < count($p) - 1) { $at++; }
    /* REVIZE — belirleyici olan reklam SAYISI degil, affiliate'e UZAKLIK.
       Iki bolum otede duran reklam butonun tiklamasini calmiyor; butonun
       hemen dibindeki caliyor. O yuzden affiliate'li sayfada da ikinci ara
       reklam aciliyor, sadece affiliate bolumunun komsulugu bos birakiliyor.
       Yogunluk tavani: her ~3 bolumde 1 reklam. 7 bolumlu bir sayfada
       2 ara + 1 alt = 3 reklam. */
    $at2 = ($bolumlu && count($p) > 6) ? min($at + 3, count($p) - 1) : 0;
    while ($bolumlu && $at2 && isset($p[$at2 - 1]) && strpos($p[$at2 - 1], 'gbc-aff') !== false && $at2 < count($p) - 1) { $at2++; }
    /* Eski </p> yedegi DEVRE DISI. Korlemesine paragraf sayarak reklam
       basiyordu ve sablonla uretilen sayfalarda kartin ortasina denk
       geliyordu. Sinir bulunamayan sayfa yalnizca alt reklami alir. */
    if (false && ! $yerlesti && count($p) > ($at + 1)) {
        $bas = implode($tag, array_slice($p, 0, $at)) . $tag . $box($mid);
        if ($at2 > $at) {
            $content = $bas . implode($tag, array_slice($p, $at, $at2 - $at)) . $tag . $box($mid) . implode($tag, array_slice($p, $at2));
        } else {
            $content = $bas . implode($tag, array_slice($p, $at));
        }
    }

    /* Reklam kabi sayfanin tasarim diliyle ayni: #E8E8E8 kenarlik, 14px kose,
       ustunde kucuk "REKLAM" etiketi. Etiket hem durustluk hem AdSense'in
       "reklam icerikten ayirt edilebilir olmali" kurali icin zorunlu.
       min-height yer rezervasyonudur; CLS 0.003'u korur (kaldirma). */
    $css = '<style id="gbc-ad-css">.gbc-ad{position:relative;min-height:280px;margin:30px 0;padding:22px 14px 14px;background:#FCFCFC;border:1px solid #E8E8E8;border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;clear:both;contain:layout}.gbc-ad::before{content:"REKLAM";position:absolute;top:7px;left:14px;font-size:9.5px;font-weight:700;letter-spacing:.1em;color:#A0A0A0;line-height:1}.gbc-ad .adsbygoogle{display:block;width:100%}@media(max-width:600px){.gbc-ad{min-height:250px;margin:24px 0;padding:20px 10px 12px;border-radius:12px}}</style>';

    $js = '<script id="gbc-ad-js">(function(){var C="' . $client . '";function loader(){if(!document.querySelector(\'script[src*="adsbygoogle.js"]\')){var s=document.createElement("script");s.async=true;s.src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client="+C;s.crossOrigin="anonymous";document.head.appendChild(s);}}var ads=[].slice.call(document.querySelectorAll(".gbc-ad .adsbygoogle"));var i=0;function fill(t){var x=ads.indexOf(t);loader();while(i<=x){try{(window.adsbygoogle=window.adsbygoogle||[]).push({});}catch(e){}i++;}}if(!ads.length)return;if("IntersectionObserver" in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){fill(e.target);io.unobserve(e.target);}});},{rootMargin:"400px 0px"});ads.forEach(function(a){io.observe(a);});}else{ads.forEach(function(a){fill(a);});}})();</script>';

    return $content . $box($end) . $css . $js;
}, 15);
