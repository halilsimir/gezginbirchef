<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 27100 — İlgili Yazılar. GBC Core'a taşındı, 28 Eylül 2026. */

/**
 * GBC · 25 · İlgili Yazılar · v2
 * --------------------------------------------------------------
 * Tekil yazilarin sonuna ayni kategoriden en yeni 3 yaziyi kart grid
 * olarak ekler.
 *
 * v2 (2026-08) DEGISENLER:
 * 1) SILO SAYFALARINDA KAPALI. Gezi rehberlerinde snippet 61 zaten
 *    "X'da daha fazla rehber" blogu basiyordu; sayfanin dibinde iki
 *    ayri tasarimda, iki ayri "ilgili" blogu ust uste geliyordu
 *    (olculdu: 403px + 353px = 756px). Artik yazi bir silo
 *    kategorisindeyse bu blok hic basilmiyor, tek blok kaliyor.
 *    Tarif, blog, sozluk gibi silo disi yazilarda aynen calisiyor.
 * 2) Gorseli olmayan yazidaki emoji yer tutucu kaldirildi (emoji ikon
 *    olarak kullanilmiyor); yerine notr bir zemin var.
 *
 * Kurulum: WPCode → PHP Snippet → Auto Insert → Run Everywhere.
 * Siralama: priority 20 (yazar kutusu 10) → ilgili yazilar en altta.
 */
if ( ! function_exists( 'gbc_related_posts' ) ) {

    function gbc_related_posts( $content ) {

        if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();

        /* Silo blogu (snippet 65 / 28213) bu sayfada GERCEKTEN basiyorsa cekil.
           28 Eylul 2026 duzeltmesi: eskiden yalnizca "sayfa silo kategorisinde mi"
           diye bakiliyordu. Silo blogu kardes yaziyla ORTAK KONU ETIKETI
           bulamayinca hicbir sey basmiyor; biz de cekildigimiz icin sayfada iki
           blok da olmuyordu (Ibiza'da olculdu). Artik silonun secimi bos donerse
           bu blok devreye giriyor. */
        if ( function_exists( 'gbc_silo_kategori' ) ) {
            $silo = gbc_silo_kategori( $post_id );
            if ( $silo ) {
                $silo_basacak = true;
                if ( function_exists( 'gbc_silo_secim' ) ) {
                    $adet = (int) apply_filters( 'gbc_silo_adet', 3 );
                    $sec  = gbc_silo_secim( $post_id, $silo['term']->term_id, $adet, $content );
                    $silo_basacak = ! empty( $sec );
                }
                if ( $silo_basacak ) { return $content; }
            }
        }

        $cats = wp_get_post_categories( $post_id );
        if ( empty( $cats ) ) {
            return $content;
        }

        $secilen = get_posts( array(
            'post_type'              => 'post',
            'posts_per_page'         => 3,
            'post__not_in'           => array( $post_id ),
            'category__in'           => $cats,
            'post_status'            => 'publish',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) );

        if ( empty( $secilen ) ) {
            return $content;
        }

        return $content . gbc_related_kart_grid( $secilen, 'İlgili Yazılar' );
    }

    add_filter( 'the_content', 'gbc_related_posts', 20 );
}

/**
 * Kart ızgarası — hem "İlgili Yazılar" hem "İç link garantisi" bunu kullanır.
 * İşaretleme eskisiyle birebir aynı; 27101 numaralı CSS aynen çalışıyor.
 */
if ( ! function_exists( 'gbc_related_kart_grid' ) ) {
    function gbc_related_kart_grid( $idler, $baslik ) {
        $idler = array_values( array_filter( array_map( 'intval', (array) $idler ) ) );
        if ( ! $idler ) { return ''; }

        $h  = '<section class="gbc-related" aria-label="' . esc_attr( $baslik ) . '">';
        $h .= '<h2 class="gbc-related-title">' . esc_html( $baslik ) . '</h2>';
        $h .= '<div class="gbc-related-grid">';
        foreach ( $idler as $id ) {
            $h .= '<a class="gbc-related-card" href="' . esc_url( get_permalink( $id ) ) . '">';
            $h .= '<span class="gbc-related-thumb">';
            if ( has_post_thumbnail( $id ) ) {
                $h .= get_the_post_thumbnail( $id, 'large', array(
                    'class'    => 'gbc-related-img',
                    'alt'      => esc_attr( get_the_title( $id ) ),
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ) );
            } else {
                $h .= '<span class="gbc-related-ph" aria-hidden="true"></span>';
            }
            $h .= '</span>';
            $h .= '<span class="gbc-related-name">' . esc_html( get_the_title( $id ) ) . '</span>';
            $h .= '</a>';
        }
        $h .= '</div></section>';
        return $h;
    }
}

/* ============================================================
   İÇ LİNK GARANTİSİ  (28 Eylül 2026)

   ÖLÇÜLEN SORUN: /erzincan-gezi-rehberi/ ve /ibiza-gezi-rehberi/
   sayfalarının dibinde HİÇBİR iç link kutusu yoktu. Canlı sayfada
   ölçüldü: .gbc-silo 0, section.gbc-related 0, .gz-rel-list 0,
   .gz2-alakali 0. Üç sebep üst üste gelmişti:

   1) Silo bloğu (WPCode 28213) yalnız dört silo kategorisinde çalışıyor
      ve kardeş yazıyla ORTAK KONU ETİKETİ yoksa hiçbir şey basmıyor —
      ama "İlgili Yazılar" bloğu, sayfa silo kategorisinde diye çoktan
      çekilmiş oluyordu. (Yukarıda düzeltildi.)
   2) Gezi şablonunun kenar kartları ("X Rehberleri", "Sonra Nereye?",
      "Alakalı Yazılar") ACF alanlarından besleniyor; alan boşsa kart yok.
      Erzincan'da üçü de boştu.
   3) Sayfalar (post değil page) hiçbir zaman kutu almıyordu —
      /esim-nedir-yurt-disinda-internet/ böyle.

   ÇÖZÜM: bu süzgeç EN SON çalışır (öncelik 30; silo ve ilgili yazılar 20).
   İçerikte dört kutudan biri bile varsa hiç karışmaz. Hiçbiri yoksa kendi
   kutusunu basar — her yazının ve sayfanın dibinde en az bir iç link
   kutusu olması artık garanti.
   ============================================================ */
if ( ! function_exists( 'gbc_ic_link_garanti' ) ) {

    function gbc_ic_link_garanti( $content ) {

        if ( is_admin() || is_feed() ) { return $content; }
        if ( ! is_singular( array( 'post', 'page' ) ) ) { return $content; }
        if ( ! in_the_loop() || ! is_main_query() ) { return $content; }
        if ( is_front_page() ) { return $content; }

        foreach ( array( 'gbc-silo', 'gbc-related', 'gz-rel-list', 'gz2-alakali' ) as $iz ) {
            if ( false !== strpos( $content, $iz ) ) { return $content; }
        }

        $pid = get_the_ID();
        if ( ! $pid ) { return $content; }

        $ortak = array(
            'post_type'              => 'post',
            'posts_per_page'         => 3,
            'post__not_in'           => array( $pid ),
            'post_status'            => 'publish',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        /* 1) Aynı kategori → 2) aynı etiket → 3) en yeni yazılar. */
        $secilen = array();

        $kat = wp_get_post_categories( $pid );
        if ( $kat ) {
            $secilen = get_posts( array_merge( $ortak, array( 'category__in' => $kat ) ) );
        }
        if ( empty( $secilen ) ) {
            $etiket = wp_get_post_terms( $pid, 'post_tag', array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $etiket ) && $etiket ) {
                $secilen = get_posts( array_merge( $ortak, array( 'tag__in' => $etiket ) ) );
            }
        }
        if ( empty( $secilen ) ) {
            $secilen = get_posts( $ortak );
        }
        if ( empty( $secilen ) ) { return $content; }

        return $content . gbc_related_kart_grid( $secilen, 'Bunlar da ilgini çekebilir' );
    }

    add_filter( 'the_content', 'gbc_ic_link_garanti', 30 );
}
