<?php
/**
 * WordPress başlangıç ekranı (Dashboard) widget'ı: "Bugün ne yapmalıyım?"
 * Yalnızca gösterir; işlem için ilgili sayfaya götürür.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', 'hk_widget_kaydet' );
add_action( 'admin_enqueue_scripts', 'hk_widget_stil' );

function hk_widget_kaydet() {
	global $wp_meta_boxes;
	if ( ! current_user_can( HK_CAP ) ) {
		return;
	}
	wp_add_dashboard_widget( 'hk_widget', HK_AD . ' – Bugün ne yapmalıyım?', 'hk_widget_icerik' );

	// Varsayılan sırada en üste al. Kutuları kendiniz sürüklediyseniz WordPress sizin sıranızı kullanır.
	if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['hk_widget'] ) ) {
		$bizim = array( 'hk_widget' => $wp_meta_boxes['dashboard']['normal']['core']['hk_widget'] );
		unset( $wp_meta_boxes['dashboard']['normal']['core']['hk_widget'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = $bizim + $wp_meta_boxes['dashboard']['normal']['core'];
	}
}

function hk_widget_stil( $hook ) {
	if ( 'index.php' === $hook && current_user_can( HK_CAP ) ) {
		wp_enqueue_style( 'hk-admin', HK_URL . 'assets/admin.css', array(), HK_VER );
	}
}

function hk_widget_icerik() {
	$yil       = (int) current_time( 'Y' );
	$gun       = (int) hk_ayar( 'panel_gun' );
	$yaklasan  = hk_yaklasanlar( $gun );
	$alacaklar = hk_hareketler( array( 'acik' => 1 ) );
	$kontrol   = hk_kontrol_sayisi();

	echo '<div class="hk-widget">';

	/* 1. Yaklaşan yenilemeler */
	echo '<h3>Yaklaşan yenilemeler <span class="hk-alt">' . (int) $gun . ' gün içinde</span></h3>';
	if ( $yaklasan ) {
		echo '<table class="hk-w-tablo"><tbody>';
		foreach ( array_slice( $yaklasan, 0, 5 ) as $h ) {
			printf(
				'<tr><td><a href="%s"><strong>%s</strong></a><div class="hk-not">%s</div></td><td class="hk-sag">%s<div class="hk-not">%s</div></td></tr>',
				esc_url(
					hk_url(
						'hizmetler',
						array(
							'islem' => 'yenile',
							'id'    => $h->id,
						)
					)
				),
				esc_html( $h->ad ),
				esc_html( $h->musteri_ad ),
				hk_kalan_rozet( $h->bitis ),
				esc_html( hk_tarih( $h->bitis ) )
			);
		}
		echo '</tbody></table>';
		if ( count( $yaklasan ) > 5 ) {
			printf( '<p class="hk-alt">ve %d hizmet daha · <a href="%s">tümünü gör</a></p>', count( $yaklasan ) - 5, esc_url( hk_url( 'panel' ) ) );
		}
	} else {
		echo '<p class="hk-alt">Bu aralıkta bitecek hizmet yok.</p>';
	}

	/* 2. Bekleyen alacaklar */
	echo '<h3>Bekleyen alacaklar</h3>';
	if ( $alacaklar ) {
		$toplam = array_sum( array_map( 'floatval', wp_list_pluck( $alacaklar, 'tutar' ) ) );
		echo '<p><strong class="hk-eksi">' . esc_html( hk_para( $toplam ) ) . '</strong> <span class="hk-alt">' . count( $alacaklar ) . ' kayıt</span></p>';
		// En eskiden yeniye; tarihi olmayanlar sona.
		usort(
			$alacaklar,
			function ( $a, $b ) {
				return array( empty( $a->tarih ), (string) $a->tarih ) <=> array( empty( $b->tarih ), (string) $b->tarih );
			}
		);
		echo '<table class="hk-w-tablo"><tbody>';
		foreach ( array_slice( $alacaklar, 0, 3 ) as $x ) {
			$bekleme = $x->tarih ? -1 * hk_gun_farki( $x->tarih ) : null;
			printf(
				'<tr><td><a href="%s"><strong>%s</strong></a><div class="hk-not">%s</div></td><td class="hk-sag">%s<div class="hk-not">%s</div></td></tr>',
				esc_url(
					hk_url(
						'musteriler',
						array(
							'islem' => 'goster',
							'id'    => $x->musteri_id,
						)
					)
				),
				esc_html( $x->musteri_ad ),
				esc_html( $x->hizmet_ad ? $x->hizmet_ad : wp_trim_words( (string) $x->aciklama, 8 ) ),
				esc_html( hk_para( $x->tutar ) ),
				null !== $bekleme && $bekleme >= 0 ? esc_html( $bekleme . ' gündür bekliyor' ) : 'tarih yok'
			);
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="hk-alt">Bekleyen alacak yok.</p>';
	}

	/* 3. Bu yılın özeti */
	$v = hk_rapor_verisi( $yil, $yil, 'tl' );
	$r = $v['yil'][ $yil ];
	echo '<h3>' . (int) $yil . ' özeti</h3>';
	printf(
		'<p class="hk-w-ozet">Tahsil edilen <strong>%s</strong> · Gider <strong>%s</strong> · Net <strong class="%s">%s</strong></p>',
		esc_html( hk_para( round( $r['tahsil'] ) ) ),
		esc_html( hk_para( round( $r['gider'] ) ) ),
		$r['net'] < 0 ? 'hk-eksi' : '',
		esc_html( ( $r['net'] < 0 ? '−' : '' ) . hk_para( abs( round( $r['net'] ) ) ) )
	);
	if ( $r['tahmini'] > 0 ) {
		printf(
			'<p class="hk-alt">Tahmini hosting gideri ~ %s dahil edilirse net <strong class="%s">%s</strong>.</p>',
			esc_html( hk_para( round( $r['tahmini'] ) ) ),
			$r['net_tahmini'] < 0 ? 'hk-eksi' : '',
			esc_html( ( $r['net_tahmini'] < 0 ? '−' : '' ) . hk_para( abs( round( $r['net_tahmini'] ) ) ) )
		);
	}

	/* 4. Kontrol bekleyenler */
	if ( $kontrol ) {
		printf( '<p><span class="hk-rozet hk-mor">%d kayıt kontrol bekliyor</span> <a href="%s">Listeyi aç</a></p>', (int) $kontrol, esc_url( hk_url( 'kontrol' ) ) );
	}

	/* 5. Kısayollar */
	printf(
		'<p class="hk-w-kisayol"><a class="button button-primary" href="%s">Hareket ekle</a> <a class="button" href="%s">Hizmetler</a> <a class="button" href="%s">Raporlar</a> <a class="button-link" href="%s">Yardım</a></p>',
		esc_url( hk_url( 'hareketler', array( 'islem' => 'yeni' ) ) ),
		esc_url( hk_url( 'hizmetler' ) ),
		esc_url( hk_url( 'raporlar' ) ),
		esc_url( hk_url( 'yardim' ) )
	);
	echo '</div>';
}
