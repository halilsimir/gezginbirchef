<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30236 — Canlı Kur. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 62 · Canlı Kur (TL karşılığı) · v2
 * --------------------------------------------------------------
 * NEDEN: Rehberlerde fiyatlar euro ve forint cinsinden. Turk okuyucu
 * "ne kadar tutuyor" diye dusunuyor. Elle yazilan TL rakami iki ay
 * sonra yanlis bilgi haline geliyor.
 *
 * NE YAPIYOR: Metne hic dokunmadan, sayfa basilirken euro ve forint
 * fiyatlarinin yanina TL karsiligini ekliyor:
 *     25 euro (≈1.400 TL)      2.200 HUF (≈340 TL)
 *     40 lari (≈510 TL)        12.000 won (≈290 TL)
 *     50–70 € (≈2.800-3.950 TL)
 *
 * Elle kontrol gereken yerde isaret de kullanilabilir:
 *     {{eur:25}}  {{huf:2200}}  {{eur:12-18}}  {{kurnot}}
 *
 * KAYNAK: TCMB gunluk XML (resmi, anahtarsiz). Forint TCMB'de yok,
 * onu open.er-api.com tamamliyor (ucretsiz, anahtarsiz).
 * Kur gunde bir kez WP-Cron ile cekilip secenege yaziliyor; sayfa
 * basilirken AG ISTEGI YOK. Iki kaynak da dusesre en son saglam deger
 * kullanilir; hicbiri yoksa TL parantezi hic basilmaz.
 *
 * v2'DE DUZELEN HATA: aralik once dogru cevriliyor ("50–70 €"), sonra
 * tekil regex ayni metindeki "70 €" kismini tekrar yakalayip ustune
 * yaziyordu; sonuc "50–70 € (≈3.950 TL)" gibi yaniltici cikiyordu.
 * Artik aralik cevrildikten sonra yerine gecici bir isaret konuyor,
 * tekil desenler o parcayi goremiyor, en sonda geri konuyor.
 *
 * WPCode: PHP Snippet · Run Everywhere.
 */

/* ---------- 1. KUR ---------- */

/* Onbellek surumu. Yeni para birimi eklenince BIR ARTTIR: eski secenekte o
   birimin kuru yoktur, gunluk cron'u beklemeden tazelenir.
   1 = EUR + HUF · 2 = GEL ve KRW eklendi (28 Eylul 2026). */
if ( ! defined( 'GBC_KUR_SURUM' ) ) { define( 'GBC_KUR_SURUM', 2 ); }

if ( ! function_exists( 'gbc_kur_cek' ) ) {
    function gbc_kur_cek() {
        $kur = array();

        $r = wp_remote_get( 'https://www.tcmb.gov.tr/kurlar/today.xml', array( 'timeout' => 8 ) );
        if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
            $xml = @simplexml_load_string( wp_remote_retrieve_body( $r ) );
            if ( $xml && isset( $xml->Currency ) ) {
                foreach ( $xml->Currency as $c ) {
                    $kod   = (string) $c['CurrencyCode'];
                    $satis = (float) str_replace( ',', '.', trim( (string) $c->ForexSelling ) );
                    $birim = (float) $c->Unit;
                    if ( $birim <= 0 ) { $birim = 1; }
                    if ( $kod && $satis > 0 ) { $kur[ $kod ] = $satis / $birim; }
                }
            }
        }

        /* TCMB'de olmayan para birimleri: forint (HUF) ve lari (GEL).
           Won (KRW) TCMB'de var (Unit 100, yukarida boluniyor) ama
           dusme ihtimaline karsi o da yedekten tamamlaniyor. */
        foreach ( array( 'HUF', 'GEL', 'KRW' ) as $kod_ek ) {
            if ( ! empty( $kur[ $kod_ek ] ) ) { continue; }
            $r2 = wp_remote_get( 'https://open.er-api.com/v6/latest/' . $kod_ek, array( 'timeout' => 8 ) );
            if ( is_wp_error( $r2 ) || 200 !== (int) wp_remote_retrieve_response_code( $r2 ) ) { continue; }
            $j = json_decode( wp_remote_retrieve_body( $r2 ), true );
            if ( ! empty( $j['rates']['TRY'] ) ) { $kur[ $kod_ek ] = (float) $j['rates']['TRY']; }
        }

        if ( ! empty( $kur['EUR'] ) ) {
            $kur['_zaman'] = time();
            $kur['_surum'] = GBC_KUR_SURUM;
            update_option( 'gbc_kur_son', $kur, false );
            return $kur;
        }
        return array();
    }
}

if ( ! function_exists( 'gbc_kur' ) ) {
    function gbc_kur() {
        $kur = get_option( 'gbc_kur_son' );

        /* Kayitli kur eski surumdense (yeni birim eklenmis) bir kez tazele.
           Kilit: ayni anda gelen ziyaretciler pespese istek atmasin; tazeleme
           basarisiz olursa eldeki kurla devam edilir, sayfa hic beklemez. */
        if ( is_array( $kur ) && ! empty( $kur['EUR'] )
            && (int) ( isset( $kur['_surum'] ) ? $kur['_surum'] : 1 ) < GBC_KUR_SURUM
            && ! get_transient( 'gbc_kur_tazele_kilit' ) ) {
            set_transient( 'gbc_kur_tazele_kilit', 1, 10 * MINUTE_IN_SECONDS );
            $yeni = gbc_kur_cek();
            if ( ! empty( $yeni['EUR'] ) ) { return $yeni; }
        }

        if ( is_array( $kur ) && ! empty( $kur['EUR'] ) ) { return $kur; }
        return gbc_kur_cek();
    }
}

if ( ! function_exists( 'gbc_kur_zamanla' ) ) {
function gbc_kur_zamanla() {
    if ( ! wp_next_scheduled( 'gbc_kur_gunluk' ) ) {
        wp_schedule_event( time() + 300, 'daily', 'gbc_kur_gunluk' );
    }
}
add_action( 'init', 'gbc_kur_zamanla' );
add_action( 'gbc_kur_gunluk', 'gbc_kur_cek' );
}

/* ---------- 2. SAYI ---------- */

if ( ! function_exists( 'gbc_kur_yuvarla' ) ) {
    function gbc_kur_yuvarla( $tl ) {
        if ( $tl < 100 )       { $y = round( $tl ); }
        elseif ( $tl < 1000 )  { $y = round( $tl / 10 ) * 10; }
        elseif ( $tl < 10000 ) { $y = round( $tl / 50 ) * 50; }
        else                   { $y = round( $tl / 500 ) * 500; }
        return number_format( $y, 0, ',', '.' );
    }
}

if ( ! function_exists( 'gbc_kur_sayi' ) ) {
    /**
     * "2.100", "2.100,50", "2,5", "2.10", "70" → sayı.
     *
     * 28 Eylül 2026'da düzeltildi: eskiden bütün noktalar binlik ayracı
     * sayılıp siliniyordu, "2.10 €" → 210 oluyordu ve zaten desene hiç
     * girmiyordu. Detay şablonundaki "~2.10 €" bu yüzden çevrilmiyordu.
     * Kural: noktadan sonra ÜÇ rakam varsa binlik, BİR-İKİ rakam varsa
     * ondalık. Virgül için de aynısı.
     */
    function gbc_kur_sayi( $ham ) {
        $h = trim( str_replace( array( ' ', "\xc2\xa0" ), '', (string) $ham ) );

        if ( preg_match( '/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $h ) ) {   /* 2.100 · 2.100,50 */
            return (float) str_replace( ',', '.', str_replace( '.', '', $h ) );
        }
        if ( preg_match( '/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $h ) ) {   /* 2,100 · 2,100.50 */
            return (float) str_replace( ',', '', $h );
        }
        if ( preg_match( '/^\d+,\d{1,2}$/', $h ) ) {                     /* 2,5 · 12,50 */
            return (float) str_replace( ',', '.', $h );
        }
        if ( preg_match( '/^\d+\.\d{1,2}$/', $h ) ) {                    /* 2.10 · 2.5 */
            return (float) $h;
        }
        return (float) preg_replace( '/[^\d]/', '', $h );
    }
}

if ( ! function_exists( 'gbc_kur_rozet' ) ) {
    function gbc_kur_rozet( $ic ) {
        return ' <span class="gbc-tl">(≈' . $ic . ' TL)</span>';
    }
}

if ( ! function_exists( 'gbc_kur_oran' ) ) {
    function gbc_kur_oran( $birim, $kur ) {
        $b = strtolower( trim( (string) $birim ) );
        if ( '€' === $b || 'euro' === $b || 'eur' === $b ) { return isset( $kur['EUR'] ) ? (float) $kur['EUR'] : 0; }
        if ( 'huf' === $b || 'forint' === $b || 'forinti' === $b ) { return isset( $kur['HUF'] ) ? (float) $kur['HUF'] : 0; }
        /* 28 Eylul 2026: Tiflis ve Seul rehberleri icin eklendi. */
        if ( '₾' === $b || 'gel' === $b || 'lari' === $b || 'larisi' === $b ) { return isset( $kur['GEL'] ) ? (float) $kur['GEL'] : 0; }
        if ( '₩' === $b || 'krw' === $b || 'won' === $b || 'wonu' === $b ) { return isset( $kur['KRW'] ) ? (float) $kur['KRW'] : 0; }
        return 0;
    }
}

/* ---------- 3. METIN CEVIRICI ---------- */

if ( ! function_exists( 'gbc_kur_metin_cevir' ) ) {
    function gbc_kur_metin_cevir( $metin, $kur ) {

        /* Binlik ayraci nokta ya da virgul olabilir; ondalik bir-iki rakam.
           Sira onemli: once binlik kaliplari, sonra duz sayi. */
        $bir = '\d{1,3}(?:\.\d{3})+(?:,\d+)?'
             . '|\d{1,3}(?:,\d{3})+(?:\.\d+)?'
             . '|\d+(?:[.,]\d{1,2})?';
        /* Uzun yazim once gelmeli: "larisi" -> "lari" sirasi onemli.
           Buyuk harfli kodlar ayri yazili; desen /i degil, o yuzden
           "gel" fiili ("5 gel") yanlislikla eslesmiyor. */
        $par = '€|₾|₩|euro|EUR|forinti|forint|HUF|larisi|lari|Lari|GEL|wonu|won|Won|KRW';
        $sim = '[€₾₩]';

        /* Cevrilen araliklar gecici isarete alinir; tekil desenler
           bu parcalari goremesin diye. Sonda geri konuyor. */
        $sakli = array();

        $kaydet = function ( $cikti ) use ( &$sakli ) {
            $sakli[] = $cikti;
            return "\x02" . ( count( $sakli ) - 1 ) . "\x03";
        };

        /* A) "50–70 €", "12€ - 18€", "12 - 18 euro" */
        $metin = preg_replace_callback(
            '/(' . $bir . ')\s*(' . $par . ')?\s*[-–—]\s*(' . $bir . ')\s*(' . $par . ')/u',
            function ( $m ) use ( $kur, $kaydet ) {
                $o = gbc_kur_oran( $m[4], $kur );
                $a = gbc_kur_sayi( $m[1] );
                $b = gbc_kur_sayi( $m[3] );
                if ( $o <= 0 || $a <= 0 || $b < $a ) { return $m[0]; }
                return $kaydet( $m[0] . gbc_kur_rozet( gbc_kur_yuvarla( $a * $o ) . '-' . gbc_kur_yuvarla( $b * $o ) ) );
            },
            $metin
        );

        /* A2) "€10 - €15" (simge onde aralik) */
        $metin = preg_replace_callback(
            '/(' . $sim . ')\s*(' . $bir . ')\s*[-–—]\s*\1\s*(' . $bir . ')/u',
            function ( $m ) use ( $kur, $kaydet ) {
                $o = gbc_kur_oran( $m[1], $kur );
                $a = gbc_kur_sayi( $m[2] );
                $b = gbc_kur_sayi( $m[3] );
                if ( $o <= 0 || $a <= 0 || $b < $a ) { return $m[0]; }
                return $kaydet( $m[0] . gbc_kur_rozet( gbc_kur_yuvarla( $a * $o ) . '-' . gbc_kur_yuvarla( $b * $o ) ) );
            },
            $metin
        );

        /* A3) "€3-5", "€25-40" (simge yalniz onde, aralikta ikinci simge yok).
           BU DESEN OLMADIGINDA: tekil desen (C) araligin ilk yarisini yakalayip
           rozeti aralik ortasina sokuyordu: "€3 (≈170 TL)-5". Atina 22690
           budget_note'ta bes yerde birden goruldu (2026-09-09). */
        $metin = preg_replace_callback(
            '/(' . $sim . ')\s*(' . $bir . ')\s*[-–—]\s*(' . $bir . ')(?![\d.,])(?!\s*(?:' . $par . '))/u',
            function ( $m ) use ( $kur, $kaydet ) {
                $o = gbc_kur_oran( $m[1], $kur );
                $a = gbc_kur_sayi( $m[2] );
                $b = gbc_kur_sayi( $m[3] );
                if ( $o <= 0 || $a <= 0 || $b < $a ) { return $m[0]; }
                return $kaydet( $m[0] . gbc_kur_rozet( gbc_kur_yuvarla( $a * $o ) . '-' . gbc_kur_yuvarla( $b * $o ) ) );
            },
            $metin
        );

        /* B) Tekil: "25 euro", "2.200 HUF", "18€" */
        $metin = preg_replace_callback(
            '/(?<![\d.,])(' . $bir . ')\s*(' . $par . ')(\+)?(?![\wğüşıöçĞÜŞİÖÇ])/u',
            function ( $m ) use ( $kur ) {
                $o = gbc_kur_oran( $m[2], $kur );
                $a = gbc_kur_sayi( $m[1] );
                if ( $o <= 0 || $a <= 0 ) { return $m[0]; }
                return $m[0] . gbc_kur_rozet( gbc_kur_yuvarla( $a * $o ) );
            },
            $metin
        );

        /* C) Simge onde tekil: "€60", "₾25", "₩12.000" */
        $metin = preg_replace_callback(
            '/(' . $sim . ')\s*(' . $bir . ')(?![\d.,])/u',
            function ( $m ) use ( $kur ) {
                $o = gbc_kur_oran( $m[1], $kur );
                $a = gbc_kur_sayi( $m[2] );
                if ( $o <= 0 || $a <= 0 ) { return $m[0]; }
                return $m[0] . gbc_kur_rozet( gbc_kur_yuvarla( $a * $o ) );
            },
            $metin
        );

        /* Saklananlari geri koy. */
        if ( $sakli ) {
            $metin = preg_replace_callback(
                '/\x02(\d+)\x03/',
                function ( $m ) use ( $sakli ) {
                    $i = (int) $m[1];
                    return isset( $sakli[ $i ] ) ? $sakli[ $i ] : '';
                },
                $metin
            );
        }

        return $metin;
    }
}

/* ---------- 4. HTML UZERINDE GUVENLI GEZINME ----------
 * Yalnizca ETIKET DISINDAKI metin parcalarinda calisir; href, alt,
 * title gibi nitelikler <...> parcasinin icinde kaldigi icin hic
 * gorulmez. script, style, code, pre atlanir. Icinde zaten TL gecen
 * parcaya dokunulmaz (kelime siniriyla: "katlaniyor" yanlis eslesmesin).
 */

if ( ! function_exists( 'gbc_kur_otomatik' ) ) {
    function gbc_kur_otomatik( $html ) {

        if ( is_admin() || is_feed() ) { return $html; }
        if ( ! preg_match( '/€|₾|₩|euro|EUR|HUF|forint|GEL|lari|KRW|won/iu', $html ) ) { return $html; }

        $kur = gbc_kur();
        if ( empty( $kur['EUR'] ) && empty( $kur['HUF'] )
            && empty( $kur['GEL'] ) && empty( $kur['KRW'] ) ) { return $html; }

        $parca  = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
        $kapali = 0;

        foreach ( $parca as $i => $p ) {
            if ( '' === $p ) { continue; }
            if ( '<' === $p[0] ) {
                if ( preg_match( '/^<\s*(script|style|textarea|code|pre)\b/i', $p ) ) { $kapali++; }
                if ( preg_match( '/^<\s*\/\s*(script|style|textarea|code|pre)/i', $p ) ) { $kapali = max( 0, $kapali - 1 ); }
                continue;
            }
            if ( $kapali > 0 ) { continue; }
            if ( false === strpos( $p, '€' ) && false === strpos( $p, '₾' ) && false === strpos( $p, '₩' )
                && ! preg_match( '/euro|EUR|HUF|forint|GEL|lari|KRW|won/iu', $p ) ) { continue; }
            if ( preg_match( '/(?<![A-Za-zğüşıöçĞÜŞİÖÇ])TL(?![A-Za-zğüşıöçĞÜŞİÖÇ])/u', $p ) ) { continue; }
            $parca[ $i ] = gbc_kur_metin_cevir( $p, $kur );
        }

        $html = implode( '', $parca );

        /* Emniyet agi: {{...}} isareti ile otomatik ceviri ayni fiyata
           pespese iki rozet basabiliyor (budget_note'ta oldu). Ayni
           yerde iki rozet varsa ikincisi silinir. */
        $rz   = '<span class="gbc-tl">\(≈[^<]{1,40}\)<\/span>';
        $html = preg_replace( '/(' . $rz . ')\s*' . $rz . '/u', '$1', $html );

        return $html;
    }
}

/* ---------- 5. ELLE ISARETLER ---------- */

if ( ! function_exists( 'gbc_kur_degistir' ) ) {
add_filter( 'the_content', 'gbc_kur_degistir', 998 );

function gbc_kur_degistir( $icerik ) {

    if ( false === strpos( $icerik, '{{' ) ) { return gbc_kur_otomatik( $icerik ); }

    $kur    = gbc_kur();
    $etiket = array( 'eur' => 'euro', 'usd' => 'dolar', 'huf' => 'HUF', 'gel' => 'lari', 'krw' => 'won' );

    $icerik = preg_replace_callback(
        '/\{\{(eur|usd|huf|gel|krw):\s*([0-9][0-9.,]*)\s*(?:[-–]\s*([0-9][0-9.,]*)\s*)?\}\}/u',
        function ( $m ) use ( $kur, $etiket ) {

            $tip = strtolower( $m[1] );
            $a   = gbc_kur_sayi( $m[2] );
            $b   = isset( $m[3] ) && '' !== $m[3] ? gbc_kur_sayi( $m[3] ) : null;

            $yaz = number_format( $a, 0, ',', '.' );
            if ( null !== $b ) { $yaz .= '-' . number_format( $b, 0, ',', '.' ); }
            $yaz .= ' ' . $etiket[ $tip ];

            $kod  = strtoupper( $tip );
            $oran = isset( $kur[ $kod ] ) ? (float) $kur[ $kod ] : 0;
            if ( $oran <= 0 ) { return $yaz; }

            $tl = gbc_kur_yuvarla( $a * $oran );
            if ( null !== $b ) { $tl .= '-' . gbc_kur_yuvarla( $b * $oran ); }

            return $yaz . gbc_kur_rozet( $tl );
        },
        $icerik
    );

    if ( false !== strpos( $icerik, '{{kurnot}}' ) ) {
        $not = '';
        if ( ! empty( $kur['EUR'] ) ) {
            $tarih = ! empty( $kur['_zaman'] ) ? date_i18n( 'j F Y', (int) $kur['_zaman'] ) : date_i18n( 'j F Y' );
            $not   = 'TL karşılıkları günlük TCMB kuruyla otomatik hesaplanıyor. ' . esc_html( $tarih )
                   . ' itibarıyla 1 euro ' . number_format( (float) $kur['EUR'], 2, ',', '.' ) . ' TL.';
        }
        $icerik = str_replace( '{{kurnot}}', $not, $icerik );
    }

    return gbc_kur_otomatik( $icerik );
}
}

/* ---------- 6. GORUNUM ----------
 * TL karsiligi asil fiyati bastirmamali: bir tik kucuk ve soluk,
 * satir ortasinda bolunmesin diye nowrap. Butce sutununda dev rakamin
 * altina ayri satira duser, yoksa sutuna sigmiyor. */

if ( ! function_exists( 'gbc_kur_stil' ) ) {
add_action( 'wp_head', 'gbc_kur_stil', 30 );
function gbc_kur_stil() {
    echo '<style id="gbc-kur-stil">'
       . '.gbc-tl{font-size:.78em;font-weight:400;opacity:.7;white-space:nowrap}'
       . '.gz-bg-price .gbc-tl{display:block;font-size:.42em;opacity:.8;margin-top:4px;letter-spacing:0}'
       . '.gz-inner-tags .gbc-tl,.gz-hb-list .gbc-tl{font-size:.85em}'
       . '</style>';
}
}
