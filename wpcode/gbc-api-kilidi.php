<?php
/**
 * GBC · API kilidi (editör, API ile yazılmış ACF alanlarını ezmesin)
 *
 * Sorun: Sayfa API (Claude/MCP) ile güncellendikten sonra, daha önce açılmış
 * bir editör sekmesinden "Güncelle"ye basılınca ACF formu sekmedeki ESKİ
 * değerleri geri yazıyor (30 Eylül 2026, Strazburg 31480 iki kez ilk taslağa döndü).
 *
 * Çözüm: gz_api_kilit = 1 olan yazılarda, editör formundan gelen ACF değerleri
 * kaydedilmez. Başlık, etiket, Rank Math ve öne çıkan görsel yine kaydedilir.
 * API (REST/MCP) yazımları etkilenmez.
 *
 * Kilidi açmak (editörden ACF alanı düzenlemek) için yazıdaki gz_api_kilit
 * metasını silin ya da 0 yapın.
 *
 * WPCode: Kod türü PHP Snippet, Konum "Run Everywhere", Etkin.
 */
add_action( 'acf/save_post', function ( $post_id ) {
	if ( ! is_numeric( $post_id ) ) {
		return;
	}
	if ( '1' !== (string) get_post_meta( (int) $post_id, 'gz_api_kilit', true ) ) {
		return;
	}
	// API yazımları serbest (Claude/MCP REST üzerinden yazar).
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	// Editör formundan gelen ACF değerlerini at; ACF kendi kaydını priority 10'da yapar.
	if ( isset( $_POST['acf'] ) ) {
		$_POST['acf'] = array();
	}
}, 1 );

// Editörde kilitli yazıyı açana uyarı göster.
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( $post_id && '1' === (string) get_post_meta( $post_id, 'gz_api_kilit', true ) ) {
		echo '<div class="notice notice-warning"><p><strong>API kilidi açık:</strong> Bu rehberin içerik alanları Claude ile yönetiliyor. Buradan "Güncelle" başlık, etiket ve Rank Math ayarlarını kaydeder; içerik alanlarını değiştirmez.</p></div>';
	}
} );
