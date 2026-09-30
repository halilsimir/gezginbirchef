<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 31160 — Güvenlik + Şefe Sor. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 24 · Şefe Sor + Spam Kalkanı + Site Sertleştirme · PHP
 * --------------------------------------------------------------
 * Yorum alanını "Şefe Sor" soru-cevap alanına çevirir ve yalnız gerçek
 * insanın, anlamlı bir şey yazarak soru bırakabilmesini sağlar.
 *
 * KATMANLAR (hepsi sunucuda doğrulanır):
 *  1) Tek kullanımlık imzalı jeton: sayfa önbellekte olsa da jeton, kişi
 *     yazmaya başlayınca JS ile alınır. JS çalıştırmayan bot jeton alamaz.
 *  2) Süre kontrolü: jeton alındıktan sonra en az 6 sn geçmeli (bot hızı elenir).
 *  3) Görünmez tuzak alan (honeypot) + tuş vuruşu sayacı.
 *  4) Hız sınırı: IP başına 10 dakikada 3, günde 8 soru.
 *  5) İçerik kalitesi: link / alan adı / e-posta yok; Kiril, Çince, Arapça yok;
 *     İngilizce kalıp spam yok; bahis / kumar / yetişkin / SEO kelimeleri yok;
 *     en az 3 kelime, 12–1500 karakter.
 *  6) İsim ve e-posta kontrolü: isimde link yok; tek kullanımlık e-posta
 *     servisleri ve .ru yok; e-posta alan adının gerçekten posta alması şart (MX).
 *  7) Her soru ONAYA düşer. Onaysız hiçbir şey yayınlanmaz.
 *  8) Pingback / trackback / XML-RPC kapalı, "Web sitesi" alanı yok.
 * Yönetici ve editörler (ve MCP yanıtları) bu kontrollerden muaftır.
 */

add_action( 'wp_head', function () { echo '<meta name="gbc-sor-canli" content="' . ( defined( 'GBC_SOR_V' ) ? GBC_SOR_V : '0' ) . '">'; }, 1 );

if ( ! defined( 'GBC_SOR_V' ) ) {
define( 'GBC_SOR_V', '2.0' );

/* ══ YARDIMCILAR ═════════════════════════════════════════════ */
function gbc_sor_ip() {
    return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:\.]/', '', $_SERVER['REMOTE_ADDR'] ) : '0';
}
function gbc_sor_ua() {
    return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 300 ) : '';
}
function gbc_sor_sig( $ts, $post_id ) {
    return hash_hmac( 'sha256', $ts . '|' . (int) $post_id . '|' . md5( gbc_sor_ua() ), wp_salt( 'nonce' ) . 'gbc-sor' );
}
function gbc_sor_is_staff() {
    return is_user_logged_in() && current_user_can( 'moderate_comments' );
}
/* Yazının türü: tarif / lezzet / gezi */
function gbc_sor_mode( $post_id = 0 ) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $cats    = wp_get_post_categories( $post_id );
    $mode    = 'gezi';
    foreach ( $cats as $c ) {
        $tree = array_merge( array( (int) $c ), array_map( 'intval', get_ancestors( $c, 'category' ) ) );
        if ( in_array( 913, $tree, true ) || in_array( 497, $tree, true ) ) { return 'tarif'; }
        if ( in_array( 751, $tree, true ) ) { $mode = 'lezzet'; }
    }
    return $mode;
}
function gbc_sor_texts( $mode ) {
    $t = array(
        'tarif'  => array(
            'kicker' => 'Şefe Sor',
            'title'  => 'Tarifi denedin mi? Aklına takılanı sor',
            'sub'    => 'Malzeme değişimi, pişirme süresi, kıvam… Takıldığın yeri yaz, Gezginbirchef mutfağından yanıtlayalım.',
            'ph'     => 'Örn: Taze peynir yerine labne kullanabilir miyim?',
            'chips'  => array( 'Malzemeyi değiştirebilir miyim?', 'Fırın derecesi ve süresi ne olmalı?', 'Önceden hazırlayıp saklayabilir miyim?', 'Denedim, sonucum şöyle oldu:' ),
            'empty'  => 'Bu tarif için henüz soru yok. İlk soruyu sen sor.',
        ),
        'lezzet' => array(
            'kicker' => 'Bize Sor',
            'title'  => 'Gitmeden önce merak ettiğin bir şey mi var?',
            'sub'    => 'Ne sipariş etmeli, rezervasyon gerekir mi, fiyatlar nasıl… Sor, bizzat yediğimiz yerden cevaplayalım.',
            'ph'     => 'Örn: Akşam rezervasyonsuz gidersek yer bulur muyuz?',
            'chips'  => array( 'Ne sipariş etmeliyim?', 'Rezervasyon gerekir mi?', 'Kişi başı ne kadar tutar?', 'Yakınında başka ne önerirsiniz?' ),
            'empty'  => 'Henüz soru yok. İlk soruyu sen sor.',
        ),
        'gezi'   => array(
            'kicker' => 'Bize Sor',
            'title'  => 'Planını birlikte yapalım: aklındakini sor',
            'sub'    => 'Rota, konaklama, bilet, ulaşım… Sorunu yaz, gezip gördüğümüz yerden cevaplayalım.',
            'ph'     => 'Örn: Ekimde 3 gün kalacağım, hangi semtte kalmalıyım?',
            'chips'  => array( 'Kaç gün yeterli?', 'Hangi bölgede kalmalıyım?', 'Hangi tur ya da bileti almalıyım?', 'Havalimanından nasıl giderim?' ),
            'empty'  => 'Henüz soru yok. İlk soruyu sen sor.',
        ),
    );
    return isset( $t[ $mode ] ) ? $t[ $mode ] : $t['gezi'];
}
function gbc_sor_die( $msg ) {
    $back = wp_get_referer();
    $html = '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:40px auto;text-align:center">'
          . '<div style="font-size:18px;font-weight:800;color:#1f1f1f;margin-bottom:10px">Sorun gönderilemedi</div>'
          . '<div style="font-size:15px;line-height:1.6;color:#444">' . esc_html( $msg ) . '</div>'
          . ( $back ? '<p style="margin-top:22px"><a href="' . esc_url( $back ) . '#sefe-sor" style="display:inline-block;background:#e65100;color:#fff;padding:12px 26px;border-radius:12px;text-decoration:none;font-weight:700">Geri dön ve düzelt</a></p>' : '' )
          . '</div>';
    wp_die( $html, 'Sorun gönderilemedi', array( 'response' => 403 ) );
}

/* ══ 1) JETON UCU (önbellekli sayfada da çalışır) ═══════════ */
function gbc_sor_token_ajax() {
    nocache_headers();
    $ip  = gbc_sor_ip();
    $key = 'gbc_sor_tk_' . md5( $ip );
    $n   = (int) get_transient( $key );
    if ( $n > 40 ) { wp_send_json_error( 'limit', 429 ); }
    set_transient( $key, $n + 1, HOUR_IN_SECONDS );
    $post_id = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 0;
    if ( ! $post_id || ! comments_open( $post_id ) ) { wp_send_json_error( 'kapali', 400 ); }
    $ts = time();
    wp_send_json_success( array( 't' => $ts, 's' => gbc_sor_sig( $ts, $post_id ) ) );
}
add_action( 'wp_ajax_nopriv_gbc_sor_token', 'gbc_sor_token_ajax' );
add_action( 'wp_ajax_gbc_sor_token', 'gbc_sor_token_ajax' );

/* ══ 2) FORM ═════════════════════════════════════════════════ */
add_filter( 'comment_form_default_fields', function ( $fields ) {
    unset( $fields['url'] );
    $req = ' required';
    $fields['author'] = '<p class="comment-form-author gbc-f"><label for="author">Adın</label>'
        . '<input id="author" name="author" type="text" value="" maxlength="40" autocomplete="name" placeholder="Adın"' . $req . '></p>';
    $fields['email']  = '<p class="comment-form-email gbc-f"><label for="email">E-postan <span>(yayınlanmaz, yanıt gelince haber veririz)</span></label>'
        . '<input id="email" name="email" type="email" value="" maxlength="100" autocomplete="email" placeholder="ornek@mail.com"' . $req . '></p>';
    return $fields;
}, 99 );
add_filter( 'pre_comment_author_url', '__return_empty_string' );

add_filter( 'comment_form_defaults', function ( $d ) {
    if ( ! is_singular() ) { return $d; }
    $tx = gbc_sor_texts( gbc_sor_mode() );
    $d['title_reply']          = 'Sorunu yaz';
    $d['title_reply_to']       = '%s kişisine yanıt yaz';
    $d['title_reply_before']   = '<h3 id="reply-title" class="comment-reply-title gbc-reply-title">';
    $d['title_reply_after']    = '</h3>';
    $d['comment_notes_before'] = '';
    $d['comment_notes_after']  = '';
    $d['label_submit']         = 'Soruyu gönder';
    $d['class_submit']         = 'submit gbc-send';
    $d['comment_field']        = '<p class="comment-form-comment gbc-f gbc-msg"><label for="comment" class="screen-reader-text">Sorun</label>'
        . '<textarea id="comment" name="comment" rows="4" maxlength="1500" required placeholder="' . esc_attr( $tx['ph'] ) . '"></textarea>'
        . '<span class="gbc-count" aria-live="polite"></span></p>';
    return $d;
}, 99 );

/* Soru baloncuklarının üstündeki hızlı soru çipleri */
add_action( 'comment_form_top', function () {
    if ( ! is_singular() ) { return; }
    $tx = gbc_sor_texts( gbc_sor_mode() );
    echo '<div class="gbc-chips" role="list" aria-label="Hazır sorular">';
    foreach ( $tx['chips'] as $c ) {
        echo '<button type="button" role="listitem" class="gbc-chip" data-q="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</button>';
    }
    echo '</div>';
} );

/* Gizli alanlar: tuzak + jeton + tuş sayacı */
add_action( 'comment_form', function () {
    echo '<div class="gbc-hp" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden"><label>Bu alanı boş bırakın<input type="text" name="gbc_hp" tabindex="-1" autocomplete="off" value=""></label></div>'
       . '<input type="hidden" name="gbc_t" value=""><input type="hidden" name="gbc_s" value=""><input type="hidden" name="gbc_k" value="0">'
       . '<p class="gbc-privacy">Adın ve e-postan yalnızca sana yanıt vermek için kullanılır. Sorular ekibimiz onayladıktan sonra yayınlanır.'
       . ( get_privacy_policy_url() ? ' <a href="' . esc_url( get_privacy_policy_url() ) . '">Gizlilik</a>' : '' ) . '</p>';
} );

/* Başlık kartı: yorum listesinin en üstüne */
function gbc_sor_header() {
    static $done = false;
    if ( $done || ! is_singular() || ! comments_open() ) { return; }
    $done  = true;
    $tx    = gbc_sor_texts( gbc_sor_mode() );
    $count = (int) get_comments_number();
    $logo  = get_site_icon_url( 96 );
    echo '<div id="sefe-sor" class="gbc-sor-head" data-mode="' . esc_attr( gbc_sor_mode() ) . '">'
       . '<div class="gbc-sor-top">'
       . '<span class="gbc-sor-av">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="Gezginbirchef logosu" width="48" height="48" loading="lazy">' : 'GB' ) . '<i class="gbc-dot"></i></span>'
       . '<div class="gbc-sor-id"><span class="gbc-sor-kicker">' . esc_html( $tx['kicker'] ) . '</span>'
       . '<strong>Gezginbirchef Ekibi</strong><span class="gbc-sor-rt">Genelde 24–48 saat içinde yanıt</span></div>'
       . '</div>'
       . '<h2 class="gbc-sor-title">' . esc_html( $tx['title'] ) . '</h2>'
       . '<p class="gbc-sor-sub">' . esc_html( $tx['sub'] ) . '</p>'
       . '<ul class="gbc-sor-trust"><li>Gerçek kişiler yanıtlar</li><li>Yanıt gelince e-postana haber</li><li>Reklam ve link yok</li></ul>'
       . '<div class="gbc-sales" hidden><span class="gbc-sales-lbl">Bu rehberde hazır:</span><div class="gbc-sales-row"></div></div>'
       . ( $count ? '<div class="gbc-sor-count">' . $count . ' soru ve yanıt</div>' : '<div class="gbc-sor-empty">' . esc_html( $tx['empty'] ) . '</div>' )
       . '</div>';
}
add_action( 'astra_comments_before', 'gbc_sor_header' );
add_action( 'comment_form_before', 'gbc_sor_header' ); /* Astra kancası yoksa yedek */

/* Şef yanıtlarını işaretle */
add_filter( 'comment_class', function ( $classes, $class, $comment_id, $comment ) {
    if ( $comment && $comment->user_id && user_can( $comment->user_id, 'edit_posts' ) ) {
        $classes[] = 'gbc-sef';
    }
    return $classes;
}, 10, 4 );

/* Ziyaretçiler başkasının sorusuna yanıt açamasın (sohbeti ekip yönetir) */
add_filter( 'comment_reply_link', function ( $link ) {
    return gbc_sor_is_staff() ? $link : '';
}, 99 );

/* Gönderim sonrası: sayfaya "sorun alındı" işaretiyle dön */
add_filter( 'comment_post_redirect', function ( $location, $comment ) {
    if ( gbc_sor_is_staff() ) { return $location; }
    $hash = '';
    if ( false !== strpos( $location, '#' ) ) { list( $location, $hash ) = explode( '#', $location, 2 ); }
    return add_query_arg( 'soru', 'alindi', $location ) . '#sefe-sor';
}, 20, 2 );

/* ══ 3) GÖNDERİM DENETİMİ ═══════════════════════════════════ */
add_filter( 'preprocess_comment', function ( $cd ) {
    if ( gbc_sor_is_staff() ) { return $cd; }

    $type = isset( $cd['comment_type'] ) ? $cd['comment_type'] : '';
    if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) { gbc_sor_die( 'Bu sitede geri bildirim bağlantıları kapalıdır.' ); }

    /* Tuzak alan */
    if ( ! empty( $_POST['gbc_hp'] ) ) { gbc_sor_die( 'İsteğiniz doğrulanamadı.' ); }

    /* Jeton + süre + tek kullanım */
    $post_id = isset( $cd['comment_post_ID'] ) ? (int) $cd['comment_post_ID'] : 0;
    $ts      = isset( $_POST['gbc_t'] ) ? (int) $_POST['gbc_t'] : 0;
    $sig     = isset( $_POST['gbc_s'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_POST['gbc_s'] ) : '';
    if ( ! $ts || strlen( $sig ) !== 64 || ! hash_equals( gbc_sor_sig( $ts, $post_id ), $sig ) ) {
        gbc_sor_die( 'Güvenlik doğrulaması yapılamadı. Sayfayı yenileyip sorunu tekrar yazar mısın? (Tarayıcında JavaScript açık olmalı.)' );
    }
    $age = time() - $ts;
    if ( $age < 6 ) { gbc_sor_die( 'Çok hızlı gönderildi. Birkaç saniye bekleyip tekrar dene.' ); }
    if ( $age > 3 * HOUR_IN_SECONDS ) { gbc_sor_die( 'Sayfa çok uzun süre açık kaldı. Yenileyip tekrar gönder.' ); }
    $used = 'gbc_sor_u_' . substr( $sig, 0, 32 );
    if ( get_transient( $used ) ) { gbc_sor_die( 'Bu form zaten gönderildi. Yeni soru için sayfayı yenile.' ); }
    set_transient( $used, 1, 4 * HOUR_IN_SECONDS );

    /* Tuş vuruşu: gerçekten yazılmış olmalı */
    $keys = isset( $_POST['gbc_k'] ) ? (int) $_POST['gbc_k'] : 0;
    if ( $keys < 3 ) { gbc_sor_die( 'Sorunu klavyeyle yazman gerekiyor.' ); }

    /* Hız sınırı */
    $ip = md5( gbc_sor_ip() );
    $k1 = 'gbc_sor_r10_' . $ip; $k2 = 'gbc_sor_rd_' . $ip;
    $n1 = (int) get_transient( $k1 ); $n2 = (int) get_transient( $k2 );
    if ( $n1 >= 3 || $n2 >= 8 ) { gbc_sor_die( 'Kısa sürede çok fazla soru gönderildi. Biraz sonra tekrar dene.' ); }

    /* İçerik */
    $txt  = trim( wp_strip_all_tags( isset( $cd['comment_content'] ) ? $cd['comment_content'] : '' ) );
    $low  = mb_strtolower( $txt, 'UTF-8' );
    $len  = mb_strlen( $txt, 'UTF-8' );
    $words = preg_split( '/\s+/u', $txt, -1, PREG_SPLIT_NO_EMPTY );
    if ( $len < 12 || count( $words ) < 3 ) { gbc_sor_die( 'Sorunu biraz daha açık yazar mısın? En az üç kelime olsun.' ); }
    if ( $len > 1500 ) { gbc_sor_die( 'Mesajın çok uzun. 1500 karakteri geçmesin.' ); }
    if ( preg_match( '~(https?:|www\.|<a\s|\[url|\[link|\b[a-z0-9-]{2,}\.(com|net|org|info|biz|ru|xyz|top|site|online|shop|io|co|me|pro|club|vip|live|app|link|bet|casino)\b)~i', $txt ) ) {
        gbc_sor_die( 'Mesajlara link veya site adresi eklenemez. Kaldırıp tekrar gönder.' );
    }
    if ( preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $txt ) ) { gbc_sor_die( 'E-posta adresini mesaja değil, e-posta alanına yaz.' ); }
    if ( preg_match( '/[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Thai}]/u', $txt ) ) { gbc_sor_die( 'Lütfen sorunu Türkçe yaz.' ); }
    $spam = '/\b(casino|kumarhane|bahis|iddaa|bet|betting|slot|poker|rulet|porn|porno|sex|seks|escort|viagra|cialis|crypto|kripto|bitcoin|forex|loan|payday|seo|backlink|predictions?|essay|onlyfans|hack|telegram|replica|pharmacy)\b/iu';
    if ( preg_match( $spam, $low ) ) { gbc_sor_die( 'Mesajın konu dışı ifadeler içeriyor.' ); }
    if ( ! preg_match( '/[çğıöşüÇĞİÖŞÜ]/u', $txt ) ) {
        preg_match_all( '/\b(the|and|your|you|i|i\'m|my|me|we|it|is|are|was|to|of|for|with|from|at|so|this|that|blog|post|article|website|site|thanks|thank|great|nice|really|information|visit|share|sharing|keep|writing|read|very|just|about)\b/i', $txt, $m );
        if ( count( $m[0] ) >= 3 ) { gbc_sor_die( 'Lütfen sorunu Türkçe yaz.' ); }
    }
    if ( preg_match( '/(.)\1{7,}/u', $txt ) ) { gbc_sor_die( 'Mesajın anlamlı görünmüyor. Sorunu yazıp tekrar gönder.' ); }

    /* İsim */
    $name = trim( isset( $cd['comment_author'] ) ? $cd['comment_author'] : '' );
    if ( mb_strlen( $name, 'UTF-8' ) < 2 || mb_strlen( $name, 'UTF-8' ) > 40 ) { gbc_sor_die( 'Adını 2–40 karakter arasında yaz.' ); }
    if ( preg_match( '~(https?:|www\.|\.[a-z]{2,6}\b|@|\d{3,})~i', $name ) || preg_match( '/[\p{Cyrillic}\p{Han}\p{Arabic}]/u', $name ) || preg_match( $spam, mb_strtolower( $name, 'UTF-8' ) ) || preg_match( '/\b(website|admin|webmaster|plataforma|shop|store|official)\b/i', $name ) ) {
        gbc_sor_die( 'Ad alanına yalnızca adını yaz.' );
    }

    /* E-posta */
    $email = strtolower( trim( isset( $cd['comment_author_email'] ) ? $cd['comment_author_email'] : '' ) );
    if ( ! is_email( $email ) ) { gbc_sor_die( 'Geçerli bir e-posta adresi yaz.' ); }
    $dom = substr( strrchr( $email, '@' ), 1 );
    $bad = array( 'uberip.com', 'mailinator.com', 'guerrillamail.com', 'sharklasers.com', '10minutemail.com', 'temp-mail.org', 'tempmail.com', 'yopmail.com', 'trashmail.com', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com', 'throwawaymail.com', 'mintemail.com', 'emailondeck.com', 'mohmal.com', 'tempail.com', 'moakt.com', 'spamgourmet.com' );
    if ( in_array( $dom, $bad, true ) || preg_match( '/\.(ru|su|xyz|top|click|buzz)$/', $dom ) ) { gbc_sor_die( 'Bu e-posta adresi kabul edilmiyor. Kendi e-postanı yaz.' ); }
    if ( function_exists( 'checkdnsrr' ) && ! checkdnsrr( $dom, 'MX' ) && ! checkdnsrr( $dom, 'A' ) ) { gbc_sor_die( 'E-posta adresinin alan adı bulunamadı. Kontrol eder misin?' ); }

    /* Tüm kontroller geçti: hız sayacını şimdi artır (yazım hatası yapan gerçek kişi cezalanmasın) */
    set_transient( $k1, $n1 + 1, 10 * MINUTE_IN_SECONDS );
    set_transient( $k2, $n2 + 1, DAY_IN_SECONDS );
    $cd['comment_author_url'] = '';
    return $cd;
}, 1 );

/* Onaysız hiçbir şey yayınlanmaz */
add_filter( 'pre_comment_approved', function ( $approved ) {
    if ( gbc_sor_is_staff() ) { return $approved; }
    if ( 'spam' === $approved || 'trash' === $approved ) { return $approved; }
    return 0;
}, 99 );

/* ══ 4) YANIT GELİNCE SORU SAHİBİNE E-POSTA ═════════════════ */
function gbc_sor_notify_parent( $comment ) {
    $comment = get_comment( $comment );
    if ( ! $comment || '1' !== (string) $comment->comment_approved || ! $comment->comment_parent ) { return; }
    if ( ! $comment->user_id || ! user_can( $comment->user_id, 'edit_posts' ) ) { return; }
    if ( get_comment_meta( $comment->comment_ID, '_gbc_sor_mailed', true ) ) { return; }
    $parent = get_comment( $comment->comment_parent );
    if ( ! $parent || ! is_email( $parent->comment_author_email ) || '1' !== (string) $parent->comment_approved ) { return; }
    $link = get_comment_link( $comment );
    $subj = 'Sorun yanıtlandı: ' . wp_strip_all_tags( get_the_title( $comment->comment_post_ID ) );
    $body = 'Merhaba ' . $parent->comment_author . ",\n\n"
          . "Gezginbirchef'e sorduğun soruya yanıt verdik:\n\n"
          . '"' . wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 60 ) . "\"\n\n"
          . "Yanıtın tamamı ve rehberin geri kalanı için:\n" . $link . "\n\n"
          . "Sevgiyle,\nGezginbirchef Ekibi";
    wp_mail( $parent->comment_author_email, $subj, $body );
    update_comment_meta( $comment->comment_ID, '_gbc_sor_mailed', 1 );
}
add_action( 'comment_post', function ( $id, $approved ) { if ( 1 === $approved || '1' === $approved ) { gbc_sor_notify_parent( $id ); } }, 20, 2 );
add_action( 'wp_set_comment_status', function ( $id, $status ) { if ( 'approve' === $status ) { gbc_sor_notify_parent( $id ); } }, 20, 2 );

/* ══ 5) SİTE SERTLEŞTİRME ═══════════════════════════════════ */
/* XML-RPC ve pingback tamamen kapalı */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () { return array(); }, 999 );
add_filter( 'pings_open', '__return_false', 999 );
add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );
add_action( 'init', function () {
    if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) { status_header( 403 ); exit; }
}, 0 );
/* Ek dosya sayfalarında soru kapalı */
add_filter( 'comments_open', function ( $open, $post_id ) {
    return ( 'attachment' === get_post_type( $post_id ) ) ? false : $open;
}, 10, 2 );
/* Kullanıcı listesini dışarıya kapat (giriş yapmış MCP/Make bağlantıları etkilenmez) */
add_filter( 'rest_endpoints', function ( $e ) {
    if ( is_user_logged_in() ) { return $e; }
    foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $r ) { unset( $e[ $r ] ); }
    return $e;
} );
/* ?author=1 ile kullanıcı adı avını engelle */
add_action( 'template_redirect', function () {
    if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
}, 1 );
/* Giriş hatasında kullanıcı adının var olup olmadığını söyleme */
add_filter( 'login_errors', function () { return 'Giriş bilgileri hatalı.'; } );
/* WordPress sürümünü gizle */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
/* Panelden tema/eklenti dosyası düzenlemeyi kapat */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) { define( 'DISALLOW_FILE_EDIT', true ); }
/* Güvenlik başlıkları */
add_action( 'send_headers', function () {
    if ( headers_sent() ) { return; }
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
    if ( is_ssl() ) { header( 'Strict-Transport-Security: max-age=31536000' ); }
} );

/* ══ 6) ÖN YÜZ: jeton, çipler, sayaç, satış kısayolları ══════ */
add_action( 'wp_footer', function () {
    if ( ! is_singular() || ! comments_open() ) { return; }
    $ajax = admin_url( 'admin-ajax.php' );
    $pid  = (int) get_the_ID();
    ?>
<script id="gbc-sor-js">
(function(){
  var f=document.getElementById('ast-commentform')||document.getElementById('commentform');
  if(!f) return;
  var ta=f.querySelector('#comment'), got=false, keys=0;
  var T=f.querySelector('[name=gbc_t]'), S=f.querySelector('[name=gbc_s]'), K=f.querySelector('[name=gbc_k]');
  function tok(){ if(got) return; got=true;
    fetch(<?php echo wp_json_encode( $ajax ); ?>+'?action=gbc_sor_token&p=<?php echo $pid; ?>&_='+Date.now(),{credentials:'same-origin',cache:'no-store'})
      .then(function(r){return r.json();}).then(function(j){ if(j&&j.success){T.value=j.data.t;S.value=j.data.s;} else {got=false;} })
      .catch(function(){got=false;}); }
  f.addEventListener('focusin',tok);
  function kc(){ keys++; if(K) K.value=keys; } f.addEventListener('keydown',kc); ta.addEventListener('input',kc);
  /* Çipler: soruyu başlat */
  f.querySelectorAll('.gbc-chip').forEach(function(b){ b.addEventListener('click',function(){
    tok(); ta.value=b.getAttribute('data-q')+' '; ta.focus(); ta.setSelectionRange(ta.value.length,ta.value.length); cnt(); }); });
  /* Karakter sayacı + istemci ön kontrolü */
  var c=f.querySelector('.gbc-count');
  function cnt(){ if(c) c.textContent=ta.value.length? ta.value.length+' / 1500':''; }
  ta.addEventListener('input',cnt);
  var err=document.createElement('div'); err.className='gbc-err'; err.setAttribute('role','alert'); f.insertBefore(err,f.querySelector('.form-submit'));
  f.addEventListener('submit',function(e){
    var v=ta.value.trim(), m='';
    if(v.split(/\s+/).length<3||v.length<12) m='Sorunu biraz daha açık yaz (en az üç kelime).';
    else if(/(https?:|www\.|\.(com|net|org|ru|xyz)\b)/i.test(v)) m='Mesaja link veya site adresi eklenemez.';
    else if(!S.value) { m='Güvenlik kontrolü hazırlanıyor, 2 saniye sonra tekrar bas.'; got=false; tok(); }
    if(m){ e.preventDefault(); err.textContent=m; err.style.display='block'; return; }
    var btn=f.querySelector('.gbc-send'); if(btn){ btn.disabled=true; btn.value='Gönderiliyor…'; }
  });
  /* Gönderildi bildirimi */
  if(/[?&]soru=alindi/.test(location.search)){
    var h=document.getElementById('sefe-sor');
    if(h){ var ok=document.createElement('div'); ok.className='gbc-ok'; ok.setAttribute('role','status');
      ok.innerHTML='<strong>Sorun bize ulaştı.</strong> Ekibimiz inceleyip yanıtlayacak; yanıt gelince e-postana haber vereceğiz.';
      h.appendChild(ok); setTimeout(function(){ window.scrollTo({top:h.getBoundingClientRect().top+window.scrollY-20}); },400); }
  }
  /* Satış kısayolları: bu sayfadaki hazır ortaklık bağlantılarına götürür (bağlantıyı kopyalamaz) */
  var head=document.getElementById('sefe-sor'); if(!head||head.getAttribute('data-mode')==='tarif') return;
  var box=head.querySelector('.gbc-sales'), row=head.querySelector('.gbc-sales-row');
  var kinds=[['booking','Konaklama'],['getyourguide','Tur ve bilet'],['ferryhopper','Feribot'],['omio','Tren ve otobüs'],['skyscanner','Uçuş'],['discovercars','Araç kirala'],['yesim','eSIM']];
  var root=document.querySelector('.entry-content')||document.body, found={};
  root.querySelectorAll('a[href]').forEach(function(a){
    if(head.contains(a)) return; var h=''; try{h=decodeURIComponent(a.href).toLowerCase();}catch(x){h=a.href.toLowerCase();}
    if(!/tp\.media|emrldtp|ferryhopper|tpx\.li|pxf\.io|gbc_aff|sub_id/.test(h)&&!a.classList.contains('gbc-in')) return;
    for(var i=0;i<kinds.length;i++){ if(h.indexOf(kinds[i][0])>-1&&!found[kinds[i][0]]){ found[kinds[i][0]]=a; break; } }
  });
  var n=0; kinds.forEach(function(k){ var a=found[k[0]]; if(!a) return; n++;
    var b=document.createElement('button'); b.type='button'; b.className='gbc-sale'; b.textContent=k[1];
    b.addEventListener('click',function(){ var t=a.closest('.gz-place-card,.v1-card-item,li,p,div')||a;
      t.scrollIntoView({behavior:'smooth',block:'center'}); t.classList.add('gbc-flash'); setTimeout(function(){t.classList.remove('gbc-flash');},2400); });
    row.appendChild(b); });
  if(n) box.hidden=false;
})();
</script>
    <?php
}, 99 );

} /* GBC_SOR_V */
