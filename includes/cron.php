<?php
/**
 * Günlük e-posta hatırlatması.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'hk_gunluk', 'hk_gunluk_calis' );

function hk_gunluk_calis() {
	hk_ozet_eposta( false );
}

/**
 * Özet e-postasını hazırlar ve gönderir.
 * $zorla = false iken yalnızca gerekli günlerde gönderir:
 *  - bir hizmetin bitişine ayarlardaki gün sayılarından biri kaldıysa (ör. 30, 7, 1), veya
 *  - haftalık özet açıksa ve bugün pazartesiyse (yaklaşan / geciken bir şey varsa).
 * Dönüş: true (gönderildi), false (gönderilemedi), null (bugün gönderilecek bir şey yok).
 */
function hk_ozet_eposta( $zorla = false ) {
	$a         = hk_ayar();
	$gunler    = array_filter( array_map( 'intval', explode( ',', $a['gunler'] ) ) );
	$yaklasan  = hk_yaklasanlar( max( 30, $gunler ? max( $gunler ) : 30 ) );
	$alacaklar = hk_hareketler( array( 'acik' => 1 ) );

	$tetik = array();
	foreach ( $yaklasan as $h ) {
		if ( in_array( hk_gun_farki( $h->bitis ), $gunler, true ) ) {
			$tetik[] = $h->ad;
		}
	}
	$pazartesi = ( '1' === current_time( 'N' ) );
	$haftalik  = ! empty( $a['haftalik'] ) && $pazartesi && ( $yaklasan || $alacaklar );

	if ( ! $zorla && ! $tetik && ! $haftalik ) {
		return null;
	}

	$td = 'style="padding:6px 10px;border-bottom:1px solid #e5e5e5;text-align:left"';
	$h  = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2327">';
	$h .= '<h2 style="margin:0 0 12px">Hesap Kitap – ' . esc_html( hk_tarih( hk_bugun() ) ) . '</h2>';

	$h .= '<h3 style="margin:18px 0 6px">Yaklaşan ve süresi geçmiş yenilemeler</h3>';
	if ( $yaklasan ) {
		$h .= '<table cellspacing="0" style="border-collapse:collapse"><tr><th ' . $td . '>Hizmet</th><th ' . $td . '>Müşteri</th><th ' . $td . '>Bitiş</th><th ' . $td . '>Kalan</th><th ' . $td . '>Maliyet</th></tr>';
		foreach ( $yaklasan as $s ) {
			$g     = hk_gun_farki( $s->bitis );
			$kalan = $g < 0 ? '<strong style="color:#b32d2e">' . abs( $g ) . ' gün geçti</strong>' : ( 0 === $g ? '<strong style="color:#b32d2e">bugün</strong>' : $g . ' gün' );
			$h    .= '<tr><td ' . $td . '>' . esc_html( $s->ad ) . '</td><td ' . $td . '>' . esc_html( $s->musteri_ad ) . '</td><td ' . $td . '>' . esc_html( hk_tarih( $s->bitis ) ) . '</td><td ' . $td . '>' . $kalan . '</td><td ' . $td . '>' . esc_html( hk_para( $s->maliyet ) ) . '</td></tr>';
		}
		$h .= '</table>';
	} else {
		$h .= '<p>Önümüzdeki günlerde bitecek hizmet yok.</p>';
	}

	$eski = hk_eski_tarihli_sayisi();
	if ( $eski ) {
		$h .= '<p style="color:#646970">Ayrıca ' . (int) $eski . ' hizmetin kayıtlı tarihi geçmişte kalmış ve güncellenmeyi bekliyor (Kontrol sayfası).</p>';
	}

	$h .= '<h3 style="margin:18px 0 6px">Bekleyen alacaklar</h3>';
	if ( $alacaklar ) {
		$toplam = 0;
		$h     .= '<table cellspacing="0" style="border-collapse:collapse"><tr><th ' . $td . '>Müşteri</th><th ' . $td . '>Tutar</th><th ' . $td . '>İstenme tarihi</th><th ' . $td . '>Açıklama</th></tr>';
		foreach ( $alacaklar as $x ) {
			$toplam += (float) $x->tutar;
			$h      .= '<tr><td ' . $td . '>' . esc_html( $x->musteri_ad ) . '</td><td ' . $td . '>' . esc_html( hk_para( $x->tutar ) ) . '</td><td ' . $td . '>' . esc_html( hk_tarih( $x->tarih ) ) . '</td><td ' . $td . '>' . esc_html( wp_trim_words( (string) $x->aciklama, 18 ) ) . '</td></tr>';
		}
		$h .= '<tr><td ' . $td . '><strong>Toplam</strong></td><td ' . $td . ' colspan="3"><strong>' . esc_html( hk_para( $toplam ) ) . '</strong></td></tr></table>';
	} else {
		$h .= '<p>Bekleyen alacak yok.</p>';
	}

	$h .= '<p style="margin-top:18px"><a href="' . esc_url( admin_url( 'admin.php?page=hk-panel' ) ) . '">Paneli aç</a></p></div>';

	if ( $tetik ) {
		$konu = '[Hesap Kitap] Yenileme yaklaşıyor: ' . implode( ', ', array_slice( $tetik, 0, 3 ) ) . ( count( $tetik ) > 3 ? ' +' . ( count( $tetik ) - 3 ) : '' );
	} else {
		$konu = '[Hesap Kitap] Özet: ' . count( $yaklasan ) . ' yenileme, ' . count( $alacaklar ) . ' bekleyen alacak';
	}

	return (bool) wp_mail( $a['eposta'], $konu, $h, array( 'Content-Type: text/html; charset=UTF-8' ) );
}
