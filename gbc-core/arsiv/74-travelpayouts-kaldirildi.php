<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Kaynak: WPCode snippet 30243 — GBC · 54 · Travelpayouts Link Üretici.
   GBC Core'a taşındı, 28 Eylül 2026. Snippet'i WPCode'da pasife al.

   TAŞIRKEN DEĞİŞEN TEK ŞEY — ANAHTARLAR:
   Snippet anahtarları kendi seçeneklerinde tutuyordu (gbc_tp_token,
   gbc_ip_sid, gbc_ip_token, gbc_tp_marker). Aynı anahtarlar API
   Merkezi'nde de duruyordu; ikisi birbirinden habersizdi. API
   Merkezi'nde belirteci değiştirince link üretici hâlâ eskisini
   kullanıyordu. Artık anahtarlar ÖNCE API Merkezi'nden okunuyor,
   yoksa eski seçeneğe düşülüyor. Ekrandaki anahtar kutuları
   kaldırıldı — tek adres API Merkezi.

   Belirteç ön yüze asla basılmaz. Link üretimi yalnız yönetici
   panelinden olur; ön yüz sadece kayıttan okur. */

/* ------------------------------------------------ anahtar okuma */

if ( ! function_exists( 'gbc_tp_anahtar' ) ) {
	/**
	 * Önce API Merkezi, sonra eski seçenek.
	 *
	 * @param string $api_id  API Merkezi kimliği (tp_token, tp_marker, impact_sid, impact_token).
	 * @param string $eski    Eski seçenek adı.
	 */
	function gbc_tp_anahtar( $api_id, $eski ) {
		if ( function_exists( 'gbc_api' ) ) {
			$d = trim( (string) gbc_api( $api_id ) );
			if ( '' !== $d ) { return $d; }
		}
		return trim( (string) get_option( $eski, '' ) );
	}
}

/* ------------------------------------------------ ön yüz okuyucu */

if ( ! function_exists( 'gbc_tp_get' ) ) {
	function gbc_tp_get( $slot ) {
		$m = get_option( 'gbc_tp_links', array() );
		return ( isset( $m[ $slot ]['aff'] ) && $m[ $slot ]['aff'] ) ? $m[ $slot ]['aff'] : '';
	}
}

/* ------------------------------------------------ Impact programları (sadece okur) */

if ( ! function_exists( 'gbc_ip_programlari_cek' ) ) {
/* Impact (Skyscanner) Partner API — SADECE OKUR. Katılınan programları,
   CampaignId'lerini, hazır tracking linklerini ve izin verilen deeplink
   alan adlarını çeker. Impact'te subId1 linke sonradan eklenebilen bir
   sorgu parametresi olduğu için her hedef için ayrı link üretmeye gerek
   yok; tek program linki + subId1 yeter. */
function gbc_ip_programlari_cek() {
	$sid = gbc_tp_anahtar( 'impact_sid', 'gbc_ip_sid' );
	$tok = gbc_tp_anahtar( 'impact_token', 'gbc_ip_token' );
	if ( '' === $sid || '' === $tok ) { return 'Impact: SID veya token eksik (API Merkezi).'; }

	$ayar = array(
		'timeout' => 25,
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $tok ),
		),
	);

	$res = wp_remote_get( 'https://api.impact.com/Mediapartners/' . rawurlencode( $sid ) . '/Campaigns?PageSize=100', $ayar );
	if ( is_wp_error( $res ) ) { return 'Impact hata: ' . $res->get_error_message(); }
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== $code ) { return 'Impact HTTP ' . $code . ' | ' . esc_html( mb_substr( $raw, 0, 300 ) ); }

	$j   = json_decode( $raw, true );
	$ham = array();
	if ( isset( $j['Campaigns'] ) && is_array( $j['Campaigns'] ) ) { $ham = $j['Campaigns']; }
	elseif ( isset( $j['Programs'] ) && is_array( $j['Programs'] ) ) { $ham = $j['Programs']; }
	elseif ( is_array( $j ) ) { $ham = $j; }

	$liste = array();
	foreach ( $ham as $p ) {
		if ( ! is_array( $p ) || empty( $p['CampaignId'] ) ) { continue; }
		$dom = isset( $p['DeeplinkDomains'] ) ? $p['DeeplinkDomains'] : '';
		$liste[] = array(
			'id'   => (string) $p['CampaignId'],
			'ad'   => isset( $p['CampaignName'] ) ? (string) $p['CampaignName'] : '',
			'link' => isset( $p['TrackingLink'] ) ? (string) $p['TrackingLink'] : '',
			'dom'  => is_array( $dom ) ? implode( ', ', $dom ) : (string) $dom,
			'dur'  => isset( $p['ContractStatus'] ) ? (string) $p['ContractStatus'] : '',
		);
	}

	/* Var olan reklamları ve üretilmiş tracking linkleri de çek: elimizdeki
	   kısa linklerin genel program linki mi yoksa derin bağlantı mı olduğunu
	   tıklamadan anlamak için. Bulunamazsa sessizce atlanır. */
	$kok    = 'https://api.impact.com/Mediapartners/' . rawurlencode( $sid ) . '/';
	$ekstra = array();
	foreach ( array( 'Ads', 'TrackingLinks' ) as $uc ) {
		$r2 = wp_remote_get( $kok . $uc . '?PageSize=100', $ayar );
		if ( is_wp_error( $r2 ) ) { $ekstra[ $uc ] = 'hata: ' . $r2->get_error_message(); continue; }
		$c2 = (int) wp_remote_retrieve_response_code( $r2 );
		$b2 = (string) wp_remote_retrieve_body( $r2 );
		if ( 200 !== $c2 ) { $ekstra[ $uc ] = 'HTTP ' . $c2 . ' | ' . mb_substr( $b2, 0, 160 ); continue; }
		$ekstra[ $uc ] = mb_substr( $b2, 0, 6000 );
	}
	update_option( 'gbc_ip_ekstra', $ekstra, false );
	update_option( 'gbc_ip_programs', $liste, false );
	return 'Impact: ' . count( $liste ) . ' program çekildi.';
}
}

/* ------------------------------------------------ link üretimi */

if ( ! function_exists( 'gbc_tp_create_link' ) ) {
function gbc_tp_create_link( $url, $sub_id = '', $shorten = true ) {
	$token  = gbc_tp_anahtar( 'tp_token', 'gbc_tp_token' );
	$trs    = (int) get_option( 'gbc_tp_trs' );
	$marker = (int) gbc_tp_anahtar( 'tp_marker', 'gbc_tp_marker' );

	if ( '' === $token || ! $trs || ! $marker ) {
		return array( 'ok' => false, 'msg' => 'Belirteç, trs ya da marker eksik.' );
	}

	$body = wp_json_encode( array(
		'trs'     => $trs,
		'marker'  => $marker,
		'shorten' => (bool) $shorten,
		'links'   => array( array( 'url' => $url, 'sub_id' => $sub_id ) ),
	) );

	/* Travelpayouts belirteci farklı başlık adlarıyla kabul ediyor;
	   hangisi geçerse o kullanılır. */
	$sets = array(
		array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Access-Token' => $token ),
		array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => 'Token ' . $token ),
		array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Api-Token' => $token ),
		array( 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Auth-Token' => $token ),
		array( 'Content-Type' => 'application/json', 'X-Access-Token' => $token ),
		array( 'Content-Type' => 'application/json', 'Authorization'  => 'Bearer ' . $token ),
	);

	$last = 'Bilinmeyen hata.';
	foreach ( $sets as $headers ) {
		$res = wp_remote_post( 'https://api.travelpayouts.com/links/v1/create', array(
			'timeout' => 20,
			'headers' => $headers,
			'body'    => $body,
		) );
		if ( is_wp_error( $res ) ) { $last = $res->get_error_message(); continue; }

		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$j    = json_decode( $raw, true );

		if ( 200 === $code && ! empty( $j['result']['links'][0]['partner_url'] ) ) {
			return array( 'ok' => true, 'url' => $j['result']['links'][0]['partner_url'] );
		}
		$last = 'HTTP ' . $code . ' | ' . mb_substr( $raw, 0, 500 );
	}
	return array( 'ok' => false, 'msg' => $last );
}
}

/* ------------------------------------------------ defter ayrıştırma */

if ( ! function_exists( 'gbc_tp_parse_defter' ) ) {
function gbc_tp_parse_defter( $text ) {
	$out   = array();
	$nl    = chr( 10 );
	$text  = str_replace( chr( 13 ) . $nl, $nl, (string) $text );
	$text  = str_replace( chr( 13 ), $nl, $text );
	$lines = explode( $nl, $text );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) { continue; }
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) < 3 ) { continue; }
		$slot = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $p[0] ) );
		if ( '' === $slot ) { continue; }
		$out[ $slot ] = array( 'sub' => $p[1], 'url' => $p[2], 'hazir' => isset( $p[3] ) ? trim( $p[3] ) : '' );
	}
	return $out;
}
}

/* --------------------------------- durum okuma izni (belirteç HARİÇ) */

add_filter( 'royal_mcp_readable_options', 'gbc_tp_okunabilir' );
if ( ! function_exists( 'gbc_tp_okunabilir' ) ) {
function gbc_tp_okunabilir( $keys ) {
	foreach ( array( 'gbc_tp_trs', 'gbc_tp_links', 'gbc_ip_programs', 'gbc_ip_ekstra' ) as $k ) {
		$keys[] = $k;
	}
	return $keys;
}
}

/* ------------------------------------------------ trs otomatik bulucu */

if ( ! function_exists( 'gbc_tp_trs_bul' ) ) {
function gbc_tp_trs_bul( $kisa ) {
	$url = trim( (string) $kisa );
	if ( '' === $url ) { return 0; }
	for ( $i = 0; $i < 6; $i++ ) {
		$q = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $q ) {
			$a = array();
			parse_str( $q, $a );
			if ( ! empty( $a['trs'] ) ) { return (int) $a['trs']; }
		}
		$res = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0 ) );
		if ( is_wp_error( $res ) ) { return 0; }
		$loc = wp_remote_retrieve_header( $res, 'location' );
		if ( ! $loc ) { return 0; }
		$url = is_array( $loc ) ? end( $loc ) : $loc;
	}
	return 0;
}
}

/* ------------------------------------------------ yönetici ekranı */

if ( ! function_exists( 'gbc_tp_admin_page' ) ) {
function gbc_tp_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$notice = '';

	if ( isset( $_POST['gbc_tp_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gbc_tp_nonce'] ) ), 'gbc_tp_save' ) ) {

		if ( isset( $_POST['gbc_tp_trs'] ) )    { update_option( 'gbc_tp_trs', (int) $_POST['gbc_tp_trs'], false ); }
		if ( isset( $_POST['gbc_tp_defter'] ) ) { update_option( 'gbc_tp_defter', wp_unslash( $_POST['gbc_tp_defter'] ), false ); }
		$notice = __( 'Kaydedildi.', 'gbc-core' );

		if ( isset( $_POST['gbc_ip_cek'] ) ) { $notice .= ' ' . gbc_ip_programlari_cek(); }

		if ( ! empty( $_POST['gbc_tp_kisa'] ) ) {
			$bulunan = gbc_tp_trs_bul( esc_url_raw( wp_unslash( $_POST['gbc_tp_kisa'] ) ) );
			if ( $bulunan ) {
				update_option( 'gbc_tp_trs', $bulunan, false );
				$notice = __( 'Kaydedildi. trs bulundu: ', 'gbc-core' ) . $bulunan;
			} else {
				$notice = __( 'Kaydedildi. trs bulunamadı; kısa linki kontrol et ya da elle yaz.', 'gbc-core' );
			}
		}

		if ( isset( $_POST['gbc_tp_uret'] ) ) {
			$rows  = gbc_tp_parse_defter( get_option( 'gbc_tp_defter', '' ) );
			$store = get_option( 'gbc_tp_links', array() );
			$ok    = 0;
			$err   = array();
			foreach ( $rows as $slot => $row ) {
				if ( ! empty( $row['hazir'] ) ) {
					$store[ $slot ] = array( 'url' => $row['url'], 'sub' => $row['sub'], 'aff' => $row['hazir'], 'ts' => current_time( 'mysql' ) );
					$ok++;
					continue;
				}
				$fresh = ( isset( $store[ $slot ]['url'] ) && $store[ $slot ]['url'] === $row['url'] && ! empty( $store[ $slot ]['aff'] ) );
				if ( $fresh && empty( $_POST['gbc_tp_force'] ) ) { continue; }
				$r  = gbc_tp_create_link( $row['url'], $row['sub'], true );
				$ru = gbc_tp_create_link( $row['url'], $row['sub'], false );
				if ( $r['ok'] ) {
					$store[ $slot ] = array(
						'url'  => $row['url'],
						'sub'  => $row['sub'],
						'aff'  => $r['url'],
						'uzun' => ( ! empty( $ru['ok'] ) ? $ru['url'] : '' ),
						'ts'   => current_time( 'mysql' ),
					);
					$ok++;
				} else {
					$err[] = $slot . ': ' . $r['msg'];
				}
				usleep( 400000 );
			}
			update_option( 'gbc_tp_links', $store, false );
			$notice = $ok . __( ' link üretildi.', 'gbc-core' ) . ( $err ? __( ' Hatalar: ', 'gbc-core' ) . esc_html( implode( ' // ', $err ) ) : '' );
		}
	}

	$trs    = (int) get_option( 'gbc_tp_trs', 0 );
	$token  = gbc_tp_anahtar( 'tp_token', 'gbc_tp_token' );
	$marker = gbc_tp_anahtar( 'tp_marker', 'gbc_tp_marker' );
	$ip_sid = gbc_tp_anahtar( 'impact_sid', 'gbc_ip_sid' );
	$ip_tok = gbc_tp_anahtar( 'impact_token', 'gbc_ip_token' );
	$defter = (string) get_option( 'gbc_tp_defter', '' );
	if ( '' === $defter ) {
		$defter = implode( chr( 10 ), array(
			'# slot | sub_id | booking url',
			'rodos_oldtown | rodos-oldtown | https://www.booking.com/searchresults.tr.html?ss=Rodos+Old+Town%2C+Yunanistan',
		) );
	}
	$store = get_option( 'gbc_tp_links', array() );

	echo '<div class="wrap"><h1>' . esc_html__( 'GBC · Travelpayouts Link Üretici', 'gbc-core' ) . '</h1>';
	if ( function_exists( 'gbc_tasarim_yol' ) ) { gbc_tasarim_yol( 'gbc-tp' ); }
	if ( $notice ) { echo '<div class="notice notice-info"><p>' . wp_kses_post( $notice ) . '</p></div>'; }

	/* Anahtarlar burada DEĞİL — tek adres API Merkezi. */
	$eksik = array();
	if ( '' === $token )  { $eksik[] = 'Travelpayouts API belirteci'; }
	if ( '' === $marker ) { $eksik[] = 'Travelpayouts marker'; }
	if ( '' === $ip_sid ) { $eksik[] = 'Impact SID'; }
	if ( '' === $ip_tok ) { $eksik[] = 'Impact Auth Token'; }

	echo '<div style="background:#fff;border:1px solid #E3E5E9;border-radius:12px;padding:13px 16px;max-width:1000px;margin:12px 0">';
	echo '<strong>' . esc_html__( 'Anahtarlar API Merkezi’nde', 'gbc-core' ) . '</strong> — ';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=gbc-api' ) ) . '">' . esc_html__( 'API Merkezi’ni aç', 'gbc-core' ) . '</a>';
	if ( $eksik ) {
		echo '<div style="color:#B3261E;font-weight:600;margin-top:6px">'
			. esc_html( sprintf( __( 'Eksik: %s', 'gbc-core' ), implode( ', ', $eksik ) ) ) . '</div>';
	} else {
		echo '<div style="color:#1A7F37;margin-top:6px">' . esc_html__( 'Dördü de dolu.', 'gbc-core' ) . '</div>';
	}
	echo '</div>';

	echo '<form method="post">';
	wp_nonce_field( 'gbc_tp_save', 'gbc_tp_nonce' );

	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'trs (proje no)', 'gbc-core' ) . '</th><td>'
		. '<input type="number" name="gbc_tp_trs" value="' . esc_attr( $trs ) . '" class="small-text">'
		. '<p class="description">' . esc_html__( 'Yalnız link üretiminde kullanılır; API Merkezi’nde karşılığı yok.', 'gbc-core' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'trs otomatik bul', 'gbc-core' ) . '</th><td>'
		. '<input type="url" name="gbc_tp_kisa" class="regular-text" placeholder="' . esc_attr__( 'elindeki bir Travelpayouts kısa linki', 'gbc-core' ) . '">'
		. '<p class="description">' . esc_html__( 'Var olan bir kısa linki (örn. booking.tpx.li/xxxx) yapıştırıp Kaydet dersen trs numarasını bulup yukarıdaki kutuya yazar.', 'gbc-core' ) . '</p></td></tr>';
	echo '</tbody></table>';

	$ip_prg = get_option( 'gbc_ip_programs', array() );
	if ( is_array( $ip_prg ) && $ip_prg ) {
		echo '<h2>' . esc_html__( 'Impact programları', 'gbc-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Impact tarafında link üretmeye gerek yok: hazır linke ?subId1=defter_id eklemek yeter, rapora ayrı sütun olarak düşer.', 'gbc-core' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>CampaignId</th><th>' . esc_html__( 'program', 'gbc-core' ) . '</th><th>'
			. esc_html__( 'durum', 'gbc-core' ) . '</th><th>' . esc_html__( 'hazır link', 'gbc-core' ) . '</th><th>'
			. esc_html__( 'deeplink alan adları', 'gbc-core' ) . '</th></tr></thead><tbody>';
		foreach ( $ip_prg as $p ) {
			echo '<tr><td><code>' . esc_html( $p['id'] ) . '</code></td>';
			echo '<td>' . esc_html( $p['ad'] ) . '</td>';
			echo '<td>' . esc_html( $p['dur'] ) . '</td>';
			echo '<td style="max-width:380px;word-break:break-all"><code>' . esc_html( $p['link'] ) . '</code></td>';
			echo '<td style="max-width:260px;word-break:break-all"><small>' . esc_html( $p['dom'] ) . '</small></td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2>' . esc_html__( 'Defter', 'gbc-core' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Her satır: slot | sub_id | booking url. Tarih, kişi sayısı ve oturum etiketi taşıyan URL yazma.', 'gbc-core' ) . '</p>';
	echo '<textarea name="gbc_tp_defter" rows="12" style="width:100%;font-family:monospace;font-size:13px">' . esc_textarea( $defter ) . '</textarea>';

	echo '<p><label><input type="checkbox" name="gbc_tp_force" value="1"> ' . esc_html__( 'var olan linkleri de yeniden üret', 'gbc-core' ) . '</label></p>';
	echo '<p>';
	submit_button( __( 'Kaydet', 'gbc-core' ), 'secondary', 'gbc_tp_kaydet', false );
	echo ' ';
	submit_button( __( 'Kaydet ve linkleri üret', 'gbc-core' ), 'primary', 'gbc_tp_uret', false );
	echo ' ';
	submit_button( __( 'Impact programlarını çek', 'gbc-core' ), 'secondary', 'gbc_ip_cek', false );
	echo '</p></form>';

	echo '<h2>' . esc_html__( 'Üretilen linkler', 'gbc-core' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr><th>slot</th><th>' . esc_html__( 'ortaklık linki (kısa)', 'gbc-core' )
		. '</th><th>' . esc_html__( 'uzun link (denetim)', 'gbc-core' ) . '</th><th>' . esc_html__( 'hedef', 'gbc-core' )
		. '</th><th>' . esc_html__( 'tarih', 'gbc-core' ) . '</th></tr></thead><tbody>';
	if ( ! $store ) {
		echo '<tr><td colspan="5">' . esc_html__( 'Henüz link üretilmedi.', 'gbc-core' ) . '</td></tr>';
	} else {
		foreach ( $store as $slot => $row ) {
			echo '<tr><td><code>' . esc_html( $slot ) . '</code></td>';
			echo '<td style="max-width:340px;word-break:break-all"><code>' . esc_html( $row['aff'] ) . '</code></td>';
			echo '<td style="max-width:460px;word-break:break-all"><small>' . esc_html( isset( $row['uzun'] ) ? $row['uzun'] : '' ) . '</small></td>';
			echo '<td style="max-width:380px;word-break:break-all"><small>' . esc_html( $row['url'] ) . '</small></td>';
			echo '<td>' . esc_html( isset( $row['ts'] ) ? $row['ts'] : '' ) . '</td></tr>';
		}
	}
	echo '</tbody></table></div>';
}
}
