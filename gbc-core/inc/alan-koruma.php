<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GBC · Alan Koruma — düzenleme ekranını ölümcül hatadan korur.
 *
 * NEDEN — 30 Eylül 2026'da canlıda yaşandı
 * ----------------------------------------
 * trip_links alanı ACF'te "textarea" tipinde. Bir yazıcı (Make senaryosu
 * ya da API) oraya metin yerine yazı ID dizisi yazdı:
 *     trip_links = [31496, 31498, 31500, 16045]
 *
 * ACF textarea'yı basarken esc_textarea( $deger ) çağırıyor. PHP 8'de
 * esc_textarea() bir dizi alınca TypeError fırlatıyor. Hata ölümcül:
 * sayfa TAM O ALANDA kesiliyor. Altında kalan her şey — blok editör
 * betikleri dahil — hiç basılmıyor. Sonuç: yazıyı düzenle ekranı
 * bembeyaz. Dört yazıda aynı anda oldu, veri kaybı yoktu ama yazıya
 * hiç girilemiyordu.
 *
 * Tek tek temizlemek çözüm değil: yazıcı düzelmezse ertesi gün geri gelir
 * (nitekim geldi). Bu dosya SEBEBİ değil SONUCU kapatır — hangi yazıcı ne
 * yazarsa yazsın düzenleme ekranı bir daha bu yüzden ölmez.
 *
 * NE YAPIYOR
 * ----------
 * İki kapı:
 *   1) acf/load_value  — okurken: skaler bekleyen alana dizi/nesne gelmişse
 *                        güvenli metne çevirir. Ekran ölmez.
 *   2) acf/update_value — yazarken: aynı çeviriyi kaydetmeden önce yapar.
 *                        Bozuk değer veritabanına hiç girmez.
 *
 * Yakalanan her olay deftere düşer (gbc_ak_yakalananlar) ve sorun defterinde
 * görünür — "gizleyerek sıfıra inme" kuralı gereği sessizce yutulmaz.
 *
 * VERİ KAYBI YOK
 * --------------
 * Düz skaler dizi satır satır birleştirilir: [31496, 31498] → "31496\n31498".
 * Değer gözle görülür, silinebilir, kaybolmaz. İç içe/ilişkisel dizi metne
 * çevrilemez; o zaman alan boşaltılır ama HAM DEĞER deftere yazılır.
 *
 * ÖN YÜZE MALİYETİ
 * ----------------
 * Filtrenin ilk satırı: değer dizi/nesne değilse hiçbir şey yapmadan döner.
 * Alanların neredeyse tamamı skaler olduğu için bu bir tip kontrolünden
 * ibaret. Ölçülebilir maliyeti yok.
 *
 * 30 Eyl 2026 · v1.30.1
 */

define( 'GBC_AK_DEFTER_TAVAN', 100 );  /* defterde en fazla bu kadar olay */
define( 'GBC_AK_HAM_KES',      500 );  /* ham değerin saklanan karakter sayısı */

/**
 * Skaler (tek değer) bekleyen ACF alan tipleri.
 *
 * DİKKAT: buraya yalnızca ÇOKLU DEĞER ALAMAYAN tipler yazılır.
 * select / checkbox / radio çoklu seçimde meşru olarak dizi döner;
 * relationship / post_object / gallery / image / file / taxonomy / user /
 * link / group / repeater / flexible_content zaten dizi ya da nesnedir.
 * Onlara DOKUNULMAZ — yoksa çalışan alanları bozarız.
 */
function gbc_ak_skaler_tipler() {
	return array(
		'text', 'textarea', 'number', 'range', 'email', 'url', 'password',
		'wysiwyg', 'oembed', 'color_picker',
		'date_picker', 'date_time_picker', 'time_picker',
	);
}

/**
 * Bu alan skaler bekliyor mu?
 */
function gbc_ak_skaler_mi( $field ) {
	if ( ! is_array( $field ) || empty( $field['type'] ) ) { return false; }
	return in_array( $field['type'], gbc_ak_skaler_tipler(), true );
}

/**
 * Dizi/nesneyi güvenli metne çevirir.
 *
 * Düz skaler dizi   → satır satır birleştirilir (veri görünür kalır).
 * Onun dışındaki her şey → boş metin (çağıran taraf ham değeri deftere yazar).
 *
 * @return string|null Çevrilebildiyse metin; çevrilemediyse null.
 */
function gbc_ak_metne_cevir( $deger ) {
	if ( is_object( $deger ) ) { return null; }
	if ( ! is_array( $deger ) ) { return (string) $deger; }
	if ( array() === $deger ) { return ''; }

	foreach ( $deger as $p ) {
		if ( is_array( $p ) || is_object( $p ) ) { return null; }
		if ( is_bool( $p ) ) { return null; }
	}
	return implode( "\n", array_map( 'strval', $deger ) );
}

/**
 * Olayı deftere ve sorun listesine yazar.
 *
 * Sessizce yutmuyoruz: hangi yazıda hangi alana ne yazıldığı kayda geçer,
 * yoksa yazıcıyı bulmak imkânsız olur.
 */
function gbc_ak_kaydet( $pid, $ad, $tip, $deger, $kurtarildi ) {
	$defter = get_option( 'gbc_ak_yakalananlar', array() );
	if ( ! is_array( $defter ) ) { $defter = array(); }

	$ham = wp_json_encode( $deger );
	if ( ! is_string( $ham ) ) { $ham = '(kodlanamadı)'; }
	if ( strlen( $ham ) > GBC_AK_HAM_KES ) { $ham = substr( $ham, 0, GBC_AK_HAM_KES ) . '…'; }

	$defter[] = array(
		'pid'   => (int) $pid,
		'alan'  => (string) $ad,
		'tip'   => (string) $tip,
		'ham'   => $ham,
		'kurt'  => (bool) $kurtarildi,
		'zaman' => time(),
	);
	if ( count( $defter ) > GBC_AK_DEFTER_TAVAN ) {
		$defter = array_slice( $defter, -GBC_AK_DEFTER_TAVAN );
	}
	update_option( 'gbc_ak_yakalananlar', $defter, false );

	gbc_ak_sorun_bildir( count( $defter ) );
}

/**
 * Sorun defterine yazar — VARSA.
 *
 * 30 Eyl 2026, canli testte bulundu: bozuk deger cogunlukla REST API
 * uzerinden geliyor. REST isteginde is_admin() FALSE oldugu icin
 * inc/gunluk.php hic yuklenmiyor ve gbc_sorun_ac() tanimsiz kaliyor.
 * O anda yazamiyorsak kayit yine de secenekte duruyor; asagidaki
 * admin_init kancasi bir sonraki yonetici sayfasinda deftere dusuruyor.
 * Boylece olay hicbir yolda kaybolmuyor.
 */
function gbc_ak_sorun_bildir( $adet ) {
	if ( ! $adet || ! function_exists( 'gbc_sorun_ac' ) ) { return false; }
	gbc_sorun_ac(
		'alan-tip-uyusmazligi', 'icerik',
		sprintf( '%d alanda tip uyuşmazlığı yakalandı', (int) $adet ),
		'Metin bekleyen ACF alanına dizi yazılmış. Düzenleme ekranı korundu, '
			. 'ama YAZICI hâlâ bozuk değer gönderiyor. Ayrıntı: gbc_ak_yakalananlar seçeneği.',
		'yuksek', admin_url( 'admin.php?page=gbc-icerik' ), (int) $adet
	);
	return true;
}

/* Yonetici ekraninda: REST sirasinda yazilamayan kayitlari deftere dusur. */
add_action( 'admin_init', 'gbc_ak_gec_bildir', 50 );
function gbc_ak_gec_bildir() {
	$defter = get_option( 'gbc_ak_yakalananlar', array() );
	if ( is_array( $defter ) && $defter ) { gbc_ak_sorun_bildir( count( $defter ) ); }
}

/**
 * Ortak kapı — okuma ve yazma aynı mantığı kullanır.
 */
function gbc_ak_suz( $deger, $post_id, $field ) {
	/* Sıcak yol: değer zaten skaler → tek kontrol, çık. */
	if ( ! is_array( $deger ) && ! is_object( $deger ) ) { return $deger; }
	if ( ! gbc_ak_skaler_mi( $field ) ) { return $deger; }

	$metin = gbc_ak_metne_cevir( $deger );
	$kurtarildi = ( null !== $metin );

	gbc_ak_kaydet(
		$post_id,
		isset( $field['name'] ) ? $field['name'] : '?',
		isset( $field['type'] ) ? $field['type'] : '?',
		$deger,
		$kurtarildi
	);

	return $kurtarildi ? $metin : '';
}

/* Okurken: düzenleme ekranı ve ön yüz. Öncelik 20 — ACF kendi
   biçimlendirmesini yaptıktan SONRA devreye girer. */
add_filter( 'acf/load_value', 'gbc_ak_yukle', 20, 3 );
function gbc_ak_yukle( $deger, $post_id, $field ) {
	return gbc_ak_suz( $deger, $post_id, $field );
}

/* Yazarken: bozuk değer veritabanına hiç girmesin. */
add_filter( 'acf/update_value', 'gbc_ak_guncelle', 20, 3 );
function gbc_ak_guncelle( $deger, $post_id, $field ) {
	return gbc_ak_suz( $deger, $post_id, $field );
}
