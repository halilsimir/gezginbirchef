<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 24462 — Font & Emoji Temizliği. GBC Core'a taşındı, 28 Eylül 2026. */

// ═══════════════════════════════════════════════════════════
//  FONT OPTİMİZASYON KODU - GezginbirChef
//  Sürüm 2.0 - Temiz, Hızlı, SEO Uyumlu
// ═══════════════════════════════════════════════════════════

// ── 1. FONT AWESOME'I TAMAMEN KALDIR ─────────────────────────
add_action( 'wp_enqueue_scripts', function() {
    $fa_handles = array(
        'font-awesome',
        'fontawesome',
        'font-awesome-5-all',
        'font-awesome-5-solid',
        'font-awesome-5-regular',
        'font-awesome-5-brands',
        'fa',
        'astra-font-awesome',
        'astra-fontawesome',
    );
    
    foreach ( $fa_handles as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }
}, 100 );

// ── 2. GOOGLE FONTS ASYNC YÜKLE (Render Blocking Önle) ───────
add_filter( 'style_loader_tag', function( $html, $handle, $href, $media ) {
    
    // Sadece Google Fonts'u async yap
    if ( strpos( $href, 'fonts.googleapis.com' ) !== false ||
         strpos( $href, 'fonts.gstatic.com' ) !== false ) {
        
        $html = str_replace(
            "rel='stylesheet'",
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
            $html
        );
        
        // Noscript fallback ekle (JS kapalı kullanıcılar için)
        $html .= '<noscript>' . str_replace(
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
            "rel='stylesheet'",
            $html
        ) . '</noscript>';
    }
    
    return $html;
}, 10, 4 );

// ── 3. FONT PRECONNECT (DNS Hızlandır) ───────────────────────
add_filter( 'wp_resource_hints', function( $hints, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $hints[] = array(
            'href'        => 'https://fonts.googleapis.com',
            'crossorigin' => 'anonymous',
        );
        $hints[] = array(
            'href'        => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        );
    }
    return $hints;
}, 10, 2 );

// ── 4. WORDPRESS EMOJI'Yİ KALDIR (Hız İçin) ──────────────────
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );