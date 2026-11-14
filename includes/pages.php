<?php
/**
 * Yönetim paneli sayfaları.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'hk_menu' );
add_action( 'admin_enqueue_scripts', 'hk_assets' );

function hk_menu() {
	$n      = hk_kontrol_sayisi();
	$balon  = $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '';
	add_menu_page( HK_AD, HK_AD, HK_CAP, 'hk-panel', 'hk_sayfa_panel', 'dashicons-money-alt', 26 );
	add_submenu_page( 'hk-panel', 'Panel', 'Panel', HK_CAP, 'hk-panel', 'hk_sayfa_panel' );
	add_submenu_page( 'hk-panel', 'Müşteriler', 'Müşteriler', HK_CAP, 'hk-musteriler', 'hk_sayfa_musteriler' );
	add_submenu_page( 'hk-panel', 'Hizmetler', 'Hizmetler', HK_CAP, 'hk-hizmetler', 'hk_sayfa_hizmetler' );
	add_submenu_page( 'hk-panel', 'Hareketler', 'Hareketler', HK_CAP, 'hk-hareketler', 'hk_sayfa_hareketler' );
	add_submenu_page( 'hk-panel', 'Raporlar', 'Raporlar', HK_CAP, 'hk-raporlar', 'hk_sayfa_raporlar' );
	add_submenu_page( 'hk-panel', 'Kontrol edilecekler', 'Kontrol' . $balon, HK_CAP, 'hk-kontrol', 'hk_sayfa_kontrol' );
	add_submenu_page( 'hk-panel', 'Ayarlar', 'Ayarlar', HK_CAP, 'hk-ayarlar', 'hk_sayfa_ayarlar' );
}

function hk_assets( $hook ) {
	if ( false !== strpos( $hook, 'hk-' ) ) {
		wp_enqueue_style( 'hk-admin', HK_URL . 'assets/admin.css', array(), HK_VER );
	}
}

/* ---------- Ortak parçalar ---------- */

function hk_get( $k, $v = '' ) {
	return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $v;
}

function hk_bildirim() {
	$m = hk_get( 'hk_msg' );
	if ( ! $m ) {
		return;
	}
	$liste = array(
		'kaydedildi'   => array( 'success', 'Kaydedildi.' ),
		'silindi'      => array( 'success', 'Silindi.' ),
		'guncellendi'  => array( 'success', 'Güncellendi.' ),
		'yenilendi'    => array( 'success', 'Hizmet yenilendi.' ),
		'toplu_ok'     => array( 'success', (int) hk_get( 'n', 0 ) . ' kayıt eklendi.' ),
		'toplu_bos'    => array( 'error', 'Kayıt eklenmedi: müşterisi ve tutarı dolu satır yok.' ),
		'eposta_ok'    => array( 'success', 'Özet e-postası gönderildi.' ),
		'eposta_hata'  => array( 'error', 'E-posta gönderilemedi. Sunucunun e-posta ayarlarını (SMTP) kontrol edin.' ),
		'musteri_dolu' => array( 'error', 'Bu müşterinin hizmet veya hareket kaydı var; önce onları silin ya da başka müşteriye taşıyın.' ),
		'ad_gerekli'   => array( 'error', 'Ad alanı boş bırakılamaz.' ),
		'eksik'        => array( 'error', 'Eksik veya geçersiz bilgi: müşteri, ad ve tutar alanlarını kontrol edin.' ),
		'tarih'        => array( 'error', 'Geçerli bir tarih girin.' ),
	);
	if ( isset( $liste[ $m ] ) ) {
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $liste[ $m ][0] ), esc_html( $liste[ $m ][1] ) );
	}
}

/** Sayfa başlığı + butonlar. $butonlar: etiket => url */
function hk_baslik( $baslik, $butonlar = array() ) {
	echo '<div class="wrap hk-wrap"><h1 class="wp-heading-inline">' . esc_html( $baslik ) . '</h1>';
	foreach ( $butonlar as $etiket => $url ) {
		printf( ' <a href="%s" class="page-title-action">%s</a>', esc_url( $url ), esc_html( $etiket ) );
	}
	echo '<hr class="wp-header-end">';
	hk_bildirim();
}

/** Form başlangıcı: action, nonce ve geri dönüş adresi. */
function hk_form_ac( $islem, $geri = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hk-form">';
	echo '<input type="hidden" name="action" value="hk_' . esc_attr( $islem ) . '">';
	echo '<input type="hidden" name="geri" value="' . esc_attr( $geri ) . '">';
	wp_nonce_field( 'hk_' . $islem );
}

function hk_kalan_rozet( $bitis ) {
	$g = hk_gun_farki( $bitis );
	if ( null === $g ) {
		return '<span class="hk-rozet hk-gri">tarih yok</span>';
	}
	if ( $g < 0 ) {
		return '<span class="hk-rozet hk-kirmizi">' . abs( $g ) . ' gün geçti</span>';
	}
	if ( 0 === $g ) {
		return '<span class="hk-rozet hk-kirmizi">bugün</span>';
	}
	$sinif = $g <= 7 ? 'hk-kirmizi' : ( $g <= 30 ? 'hk-sari' : 'hk-yesil' );
	return '<span class="hk-rozet ' . $sinif . '">' . $g . ' gün</span>';
}

function hk_durum_rozet( $x ) {
	if ( 'gider' === $x->tur ) {
		return '<span class="hk-rozet hk-gri">Gider</span>';
	}
	$d = hk_durumlar();
	$s = array(
		'bekliyor' => 'hk-sari',
		'istendi'  => 'hk-kirmizi',
		'odendi'   => 'hk-yesil',
	);
	$e = isset( $d[ $x->durum ] ) ? $d[ $x->durum ] : $x->durum;
	if ( 'istendi' === $x->durum && $x->hatirlatma > 0 ) {
		$e .= ' · ' . (int) $x->hatirlatma . ' hatırlatma';
	}
	return '<span class="hk-rozet ' . ( isset( $s[ $x->durum ] ) ? $s[ $x->durum ] : 'hk-gri' ) . '">' . esc_html( $e ) . '</span>';
}

function hk_kontrol_isareti( $kayit ) {
	return $kayit->kontrol ? ' <span class="hk-rozet hk-mor" title="Tablodan aktarılırken belirsiz kaldı; kontrol edin">kontrol</span>' : '';
}

function hk_kategori_adi( $k ) {
	$kat = hk_kategoriler();
	return isset( $kat[ $k ] ) ? $kat[ $k ] : $k;
}

function hk_kategori_select( $secili = 'yenileme', $ad = 'kategori', $bos = '' ) {
	$h = '<select name="' . esc_attr( $ad ) . '">' . ( $bos ? '<option value="">' . esc_html( $bos ) . '</option>' : '' );
	foreach ( hk_kategoriler() as $k => $e ) {
		$h .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $secili, $k, false ), esc_html( $e ) );
	}
	return $h . '</select>';
}

function hk_kart( $etiket, $deger, $sinif = '', $alt = '' ) {
	printf(
		'<div class="hk-kart %s"><div class="hk-kart-etiket">%s</div><div class="hk-kart-deger">%s</div>%s</div>',
		esc_attr( $sinif ),
		esc_html( $etiket ),
		esc_html( $deger ),
		$alt ? '<div class="hk-kart-alt">' . wp_kses_post( $alt ) . '</div>' : ''
	);
}

function hk_musteri_select( $secili = 0, $bos = '' ) {
	echo '<select name="musteri_id" id="hk_musteri_id"' . ( $bos ? '' : ' required' ) . '>';
	if ( $bos ) {
		echo '<option value="">' . esc_html( $bos ) . '</option>';
	}
	foreach ( hk_musteriler() as $m ) {
		printf( '<option value="%d"%s>%s</option>', (int) $m->id, selected( (int) $secili, (int) $m->id, false ), esc_html( $m->ad . ( $m->firma ? ' – ' . $m->firma : '' ) ) );
	}
	echo '</select>';
}

/* ---------- Tablolar (birden çok sayfada kullanılır) ---------- */

/** Hizmet tablosu. $musteri_goster: müşteri sütunu. */
function hk_hizmet_tablosu( $hizmetler, $musteri_goster = true, $bos = 'Kayıt yok.' ) {
	$turler = hk_turler();
	echo '<table class="wp-list-table widefat striped hk-tablo"><thead><tr><th>Hizmet</th>';
	if ( $musteri_goster ) {
		echo '<th>Müşteri</th>';
	}
	echo '<th>Kayıt firması</th><th>Bitiş</th><th>Kalan</th><th class="hk-sag">Maliyet</th><th class="hk-sag">Müşteri fiyatı</th><th>İşlem</th></tr></thead><tbody>';
	if ( ! $hizmetler ) {
		echo '<tr><td colspan="8">' . esc_html( $bos ) . '</td></tr>';
	}
	foreach ( $hizmetler as $h ) {
		echo '<tr class="' . ( $h->aktif ? '' : 'hk-pasif' ) . '">';
		echo '<td><strong>' . esc_html( $h->ad ) . '</strong> <span class="hk-tur">' . esc_html( isset( $turler[ $h->tur ] ) ? $turler[ $h->tur ] : $h->tur ) . '</span>' . ( $h->aktif ? '' : ' <span class="hk-rozet hk-gri">pasif</span>' ) . hk_kontrol_isareti( $h );
		$ek = array();
		if ( $h->paket ) {
			$ek[] = 'hosting paketi – maliyeti müşterilere dağıtılır';
		} elseif ( 'hosting' === $h->tur ) {
			if ( $h->musteri_oder ) {
				$ek[] = 'ücretini müşteri ödüyor';
			} elseif ( $h->pay > 1 ) {
				$ek[] = (int) $h->pay . ' pay';
			}
			if ( $h->bagli_id ) {
				$ek[] = 'tarihi alan adına bağlı';
			}
		}
		if ( $ek ) {
			echo '<div class="hk-not">' . esc_html( implode( ' · ', $ek ) ) . '</div>';
		}
		if ( $h->notlar ) {
			echo '<div class="hk-not">' . esc_html( $h->notlar ) . '</div>';
		}
		echo '</td>';
		if ( $musteri_goster ) {
			printf(
				'<td><a href="%s">%s</a></td>',
				esc_url(
					hk_url(
						'musteriler',
						array(
							'islem' => 'goster',
							'id'    => $h->musteri_id,
						)
					)
				),
				esc_html( $h->musteri_ad )
			);
		}
		echo '<td>' . esc_html( $h->firma ? $h->firma : '—' ) . '</td>';
		echo '<td>' . esc_html( hk_tarih( $h->bitis ) ) . '</td>';
		echo '<td>' . ( $h->aktif ? hk_kalan_rozet( $h->bitis ) : '—' ) . '</td>';
		$pb = ( 'hosting' === $h->tur && ! $h->paket && ! $h->musteri_oder && ! $h->kendi && null === $h->maliyet ) ? hk_pay_basi( $h->firma ) : null;
		if ( $pb ) {
			echo '<td class="hk-sag" title="Hosting paketinden bu kayda düşen pay' . ( $pb['tahmini'] ? ' (bu yılın ödemesi yapılmadığı için tahmini)' : '' ) . '">~ ' . esc_html( hk_para( round( $pb['pay_basi'] * max( 1, (int) $h->pay ) ) ) ) . '</td>';
		} else {
			echo '<td class="hk-sag">' . esc_html( hk_para( $h->maliyet ) ) . '</td>';
		}
		echo '<td class="hk-sag">' . esc_html( $h->kendi ? '—' : hk_para( $h->fiyat ) ) . '</td>';
		echo '<td class="hk-islem">';
		printf(
			'<a class="button button-small button-primary" href="%s">Yenile</a> ',
			esc_url(
				hk_url(
					'hizmetler',
					array(
						'islem' => 'yenile',
						'id'    => $h->id,
						'geri'  => rawurlencode( hk_su_an() ),
					)
				)
			)
		);
		printf(
			'<a class="button button-small" href="%s">Düzenle</a>',
			esc_url(
				hk_url(
					'hizmetler',
					array(
						'islem' => 'duzenle',
						'id'    => $h->id,
						'geri'  => rawurlencode( hk_su_an() ),
					)
				)
			)
		);
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function hk_hareket_tablosu( $hareketler, $musteri_goster = true, $bos = 'Kayıt yok.' ) {
	echo '<table class="wp-list-table widefat striped hk-tablo"><thead><tr><th>Dönem</th><th>Tarih</th>';
	if ( $musteri_goster ) {
		echo '<th>Müşteri</th>';
	}
	echo '<th>Açıklama</th><th class="hk-sag">Gelir</th><th class="hk-sag">Gider</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
	if ( ! $hareketler ) {
		echo '<tr><td colspan="8">' . esc_html( $bos ) . '</td></tr>';
	}
	foreach ( $hareketler as $x ) {
		$acik = ( 'gelir' === $x->tur && 'odendi' !== $x->durum );
		echo '<tr>';
		echo '<td>' . (int) $x->donem . '</td>';
		echo '<td>' . esc_html( hk_tarih( $x->tarih ) );
		if ( 'gelir' === $x->tur && 'odendi' === $x->durum && $x->odeme_tarihi && $x->odeme_tarihi !== $x->tarih ) {
			echo '<div class="hk-not">ödeme: ' . esc_html( hk_tarih( $x->odeme_tarihi ) ) . '</div>';
		}
		echo '</td>';
		if ( $musteri_goster ) {
			printf(
				'<td><a href="%s">%s</a></td>',
				esc_url(
					hk_url(
						'musteriler',
						array(
							'islem' => 'goster',
							'id'    => $x->musteri_id,
						)
					)
				),
				esc_html( $x->musteri_ad )
			);
		}
		echo '<td>' . ( $x->hizmet_ad ? '<strong>' . esc_html( $x->hizmet_ad ) . '</strong> ' : '' ) . ( 'yenileme' !== $x->kategori ? '<span class="hk-tur">' . esc_html( hk_kategori_adi( $x->kategori ) ) . '</span> ' : '' ) . hk_kontrol_isareti( $x ) . '<div class="hk-not">' . esc_html( (string) $x->aciklama ) . '</div></td>';
		echo '<td class="hk-sag">' . ( 'gelir' === $x->tur ? esc_html( hk_para( $x->tutar ) ) : '' ) . '</td>';
		echo '<td class="hk-sag">' . ( 'gider' === $x->tur ? esc_html( hk_para( $x->tutar ) ) : '' ) . '</td>';
		echo '<td>' . hk_durum_rozet( $x ) . '</td>';
		echo '<td class="hk-islem">';
		if ( $acik ) {
			echo hk_dugme(
				'hareket_hizli',
				array(
					'id' => $x->id,
					'ne' => 'odendi',
				),
				'Ödendi',
				'button button-small button-primary'
			) . ' ';
			printf(
				'<a class="button button-small" href="%s">Mesaj</a> ',
				esc_url(
					hk_url(
						'musteriler',
						array(
							'islem'   => 'mesaj',
							'id'      => $x->musteri_id,
							'hareket' => $x->id,
						)
					)
				)
			);
		}
		printf(
			'<a class="button button-small" href="%s">Düzenle</a>',
			esc_url(
				hk_url(
					'hareketler',
					array(
						'islem' => 'duzenle',
						'id'    => $x->id,
						'geri'  => rawurlencode( hk_su_an() ),
					)
				)
			)
		);
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/* ---------- Panel ---------- */

function hk_sayfa_panel() {
	$yil       = (int) hk_get( 'donem', current_time( 'Y' ) );
	$musteri   = hk_musteriler();
	$panel_gun = (int) hk_ayar( 'panel_gun' );

	hk_baslik(
		HK_AD,
		array(
			'Hareket ekle' => hk_url( 'hareketler', array( 'islem' => 'yeni' ) ),
			'Hizmet ekle'  => hk_url( 'hizmetler', array( 'islem' => 'yeni' ) ),
		)
	);

	if ( ! $musteri ) {
		echo '<div class="hk-bos"><h2>Henüz kayıt yok</h2><p>Önce bir müşteri ekleyin; ardından alan adı ve hosting kayıtlarını girebilirsiniz.</p>';
		printf( '<p><a class="button button-primary button-hero" href="%s">Müşteri ekle</a></p></div></div>', esc_url( hk_url( 'musteriler', array( 'islem' => 'yeni' ) ) ) );
		return;
	}

	$ozet      = hk_ozet( $yil );
	$tum       = hk_ozet();
	$t_tahsil  = array_sum( wp_list_pluck( $ozet, 'tahsil' ) );
	$t_gider   = array_sum( wp_list_pluck( $ozet, 'gider' ) );
	$t_bekle   = array_sum( wp_list_pluck( $tum, 'bekleyen' ) );
	$yaklasan  = hk_yaklasanlar( $panel_gun );
	$alacaklar = hk_hareketler( array( 'acik' => 1 ) );
	$kontrol   = hk_kontrol_sayisi();

	echo '<form method="get" class="hk-filtre"><input type="hidden" name="page" value="hk-panel"><label>Dönem: <select name="donem" onchange="this.form.submit()">';
	foreach ( hk_donemler() as $d ) {
		printf( '<option value="%d"%s>%d</option>', $d, selected( $yil, $d, false ), $d );
	}
	echo '</select></label></form>';

	echo '<div class="hk-kartlar">';
	hk_kart( $yil . ' tahsil edilen', hk_para( $t_tahsil ), 'hk-k-yesil' );
	hk_kart( $yil . ' gider', hk_para( $t_gider ), 'hk-k-gri' );
	hk_kart( $yil . ' net', hk_para( $t_tahsil - $t_gider ), ( $t_tahsil - $t_gider ) >= 0 ? 'hk-k-yesil' : 'hk-k-kirmizi' );
	hk_kart( 'Bekleyen alacak (tüm yıllar)', hk_para( $t_bekle ), $t_bekle > 0 ? 'hk-k-kirmizi' : 'hk-k-gri', count( $alacaklar ) . ' kayıt' );
	hk_kart( 'Kontrol bekleyen', (string) $kontrol, $kontrol ? 'hk-k-mor' : 'hk-k-gri', $kontrol ? '<a href="' . esc_url( hk_url( 'kontrol' ) ) . '">Listeyi aç</a>' : '' );
	echo '</div>';
	$dagitim = hk_hosting_dagitim( $yil );
	if ( $dagitim['tahmini'] > 0 ) {
		printf(
			'<p class="hk-alt">%d hosting paketi ödemesi henüz yapılmadı; tahmini %s gider yukarıdaki rakamlara dahil değil. Ayrıntı için <a href="%s">Raporlar</a>.</p>',
			(int) $yil,
			esc_html( hk_para( round( $dagitim['tahmini'] ) ) ),
			esc_url( hk_url( 'raporlar' ) )
		);
	}

	echo '<h2>Yaklaşan yenilemeler <span class="hk-alt">süresi geçmiş ve ' . (int) $panel_gun . ' gün içinde bitecek aktif hizmetler</span></h2>';
	hk_hizmet_tablosu( $yaklasan, true, 'Bu aralıkta bitecek hizmet yok.' );
	$eski = hk_eski_tarihli_sayisi();
	if ( $eski ) {
		printf( '<p class="hk-alt">Ayrıca %d hizmetin tablodaki tarihi geçmişte kalmış ve güncellenmeyi bekliyor; bunlar yukarıda listelenmez. <a href="%s">Kontrol sayfasında düzeltin</a>.</p>', (int) $eski, esc_url( hk_url( 'kontrol' ) ) );
	}

	echo '<h2>Bekleyen alacaklar</h2>';
	hk_hareket_tablosu( $alacaklar, true, 'Bekleyen alacak yok.' );

	echo '<h2>' . (int) $yil . ' özeti <span class="hk-alt">müşteri bazında</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Müşteri</th><th class="hk-sag">Tahsil edilen</th><th class="hk-sag">Bekleyen</th><th class="hk-sag">Gider</th><th class="hk-sag">Net</th></tr></thead><tbody>';
	foreach ( $musteri as $m ) {
		$o = isset( $ozet[ $m->id ] ) ? $ozet[ $m->id ] : array(
			'tahsil'   => 0,
			'bekleyen' => 0,
			'gider'    => 0,
		);
		if ( ! $o['tahsil'] && ! $o['bekleyen'] && ! $o['gider'] ) {
			continue;
		}
		$net = $o['tahsil'] - $o['gider'];
		printf(
			'<tr><td><a href="%s">%s</a>%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag %s"><strong>%s</strong></td></tr>',
			esc_url(
				hk_url(
					'musteriler',
					array(
						'islem' => 'goster',
						'id'    => $m->id,
					)
				)
			),
			esc_html( $m->ad ),
			$m->firma ? ' <span class="hk-alt">' . esc_html( $m->firma ) . '</span>' : '',
			esc_html( hk_para( $o['tahsil'] ) ),
			esc_html( $o['bekleyen'] ? hk_para( $o['bekleyen'] ) : '—' ),
			esc_html( hk_para( $o['gider'] ) ),
			$net < 0 ? 'hk-eksi' : '',
			esc_html( hk_para( $net ) )
		);
	}
	printf(
		'</tbody><tfoot><tr><th>Toplam</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th></tr></tfoot></table>',
		esc_html( hk_para( $t_tahsil ) ),
		esc_html( hk_para( array_sum( wp_list_pluck( $ozet, 'bekleyen' ) ) ) ),
		esc_html( hk_para( $t_gider ) ),
		esc_html( hk_para( $t_tahsil - $t_gider ) )
	);
	echo '</div>';
}

/* ---------- Müşteriler ---------- */

function hk_sayfa_musteriler() {
	$islem = hk_get( 'islem' );
	$id    = (int) hk_get( 'id', 0 );
	if ( 'yeni' === $islem || 'duzenle' === $islem ) {
		hk_musteri_formu( $id );
	} elseif ( 'goster' === $islem && $id ) {
		hk_musteri_detay( $id );
	} elseif ( 'mesaj' === $islem && $id ) {
		hk_musteri_mesaj( $id );
	} else {
		hk_musteri_listesi();
	}
}

function hk_musteri_listesi() {
	global $wpdb;
	hk_baslik( 'Müşteriler', array( 'Yeni müşteri' => hk_url( 'musteriler', array( 'islem' => 'yeni' ) ) ) );
	$ozet  = hk_ozet();
	$sayim = array();
	foreach ( $wpdb->get_results( 'SELECT musteri_id, COUNT(*) AS n, MIN(CASE WHEN aktif = 1 THEN bitis END) AS ilk FROM ' . hk_t( 'hizmetler' ) . ' WHERE aktif = 1 GROUP BY musteri_id' ) as $r ) {
		$sayim[ (int) $r->musteri_id ] = $r;
	}
	echo '<table class="wp-list-table widefat striped hk-tablo"><thead><tr><th>Müşteri</th><th>İletişim</th><th>Aktif hizmet</th><th>En yakın bitiş</th><th class="hk-sag">Toplam tahsil</th><th class="hk-sag">Toplam gider</th><th class="hk-sag">Bekleyen alacak</th><th>İşlem</th></tr></thead><tbody>';
	foreach ( hk_musteriler() as $m ) {
		$o = isset( $ozet[ $m->id ] ) ? $ozet[ $m->id ] : array(
			'tahsil'   => 0,
			'bekleyen' => 0,
			'gider'    => 0,
		);
		$s   = isset( $sayim[ $m->id ] ) ? $sayim[ $m->id ] : null;
		$url = hk_url(
			'musteriler',
			array(
				'islem' => 'goster',
				'id'    => $m->id,
			)
		);
		echo '<tr><td><strong><a href="' . esc_url( $url ) . '">' . esc_html( $m->ad ) . '</a></strong>' . ( $m->kendi ? ' <span class="hk-rozet hk-gri">kendi</span>' : '' ) . '<div class="hk-not">' . esc_html( $m->firma ) . '</div></td>';
		echo '<td>' . esc_html( $m->telefon ) . ( $m->telefon && $m->eposta ? '<br>' : '' ) . esc_html( $m->eposta ) . ( ! $m->telefon && ! $m->eposta ? '—' : '' ) . '</td>';
		echo '<td>' . ( $s ? (int) $s->n : 0 ) . '</td>';
		echo '<td>' . ( $s && $s->ilk ? esc_html( hk_tarih( $s->ilk ) ) . ' ' . hk_kalan_rozet( $s->ilk ) : '—' ) . '</td>';
		echo '<td class="hk-sag">' . esc_html( $m->kendi ? '—' : hk_para( $o['tahsil'] ) ) . '</td>';
		echo '<td class="hk-sag">' . esc_html( hk_para( $o['gider'] ) ) . '</td>';
		echo '<td class="hk-sag">' . ( $o['bekleyen'] > 0 ? '<strong class="hk-eksi">' . esc_html( hk_para( $o['bekleyen'] ) ) . '</strong>' : '—' ) . '</td>';
		echo '<td class="hk-islem"><a class="button button-small" href="' . esc_url( $url ) . '">Aç</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

function hk_musteri_formu( $id ) {
	$m = $id ? hk_musteri( $id ) : null;
	hk_baslik( $m ? 'Müşteriyi düzenle' : 'Yeni müşteri' );
	hk_form_ac( 'musteri_kaydet' );
	echo '<input type="hidden" name="id" value="' . (int) $id . '"><table class="form-table"><tbody>';
	printf( '<tr><th><label for="hk_ad">Ad</label></th><td><input type="text" id="hk_ad" name="ad" class="regular-text" required value="%s"></td></tr>', esc_attr( $m ? $m->ad : '' ) );
	printf( '<tr><th><label for="hk_firma">Firma</label></th><td><input type="text" id="hk_firma" name="firma" class="regular-text" value="%s"></td></tr>', esc_attr( $m ? $m->firma : '' ) );
	printf( '<tr><th><label for="hk_yetkili">Yetkili kişi</label></th><td><input type="text" id="hk_yetkili" name="yetkili" class="regular-text" value="%s"><p class="description">Firmada muhatap olduğunuz kişi farklıysa.</p></td></tr>', esc_attr( $m ? $m->yetkili : '' ) );
	printf( '<tr><th><label for="hk_telefon">Telefon</label></th><td><input type="text" id="hk_telefon" name="telefon" class="regular-text" placeholder="05xx xxx xx xx" value="%s"><p class="description">WhatsApp mesaj bağlantısı için kullanılır.</p></td></tr>', esc_attr( $m ? $m->telefon : '' ) );
	printf( '<tr><th><label for="hk_telefon2">İkinci telefon</label></th><td><input type="text" id="hk_telefon2" name="telefon2" class="regular-text" value="%s"></td></tr>', esc_attr( $m ? $m->telefon2 : '' ) );
	printf( '<tr><th><label for="hk_eposta">E-posta</label></th><td><input type="email" id="hk_eposta" name="eposta" class="regular-text" value="%s"></td></tr>', esc_attr( $m ? $m->eposta : '' ) );
	printf( '<tr><th><label for="hk_notlar">Notlar</label></th><td><textarea id="hk_notlar" name="notlar" rows="5" class="large-text">%s</textarea></td></tr>', esc_textarea( $m ? $m->notlar : '' ) );
	printf( '<tr><th>Kendi hesabım</th><td><label><input type="checkbox" name="kendi" value="1"%s> Bu hesap kendi alan adlarım için (gelir beklenmez, yalnızca gider izlenir)</label></td></tr>', checked( $m ? (int) $m->kendi : 0, 1, false ) );
	echo '</tbody></table>';
	submit_button( 'Kaydet' );
	echo '</form>';
	if ( $m ) {
		echo '<p>' . hk_dugme( 'musteri_sil', array( 'id' => $m->id ), 'Müşteriyi sil', 'button-link hk-sil', 'Müşteri silinsin mi?' ) . '</p>';
	}
	echo '</div>';
}

function hk_musteri_detay( $id ) {
	$m = hk_musteri( $id );
	if ( ! $m ) {
		hk_baslik( 'Müşteri bulunamadı' );
		echo '</div>';
		return;
	}
	hk_baslik(
		$m->ad . ( $m->firma ? ' – ' . $m->firma : '' ),
		array(
			'Düzenle'             => hk_url(
				'musteriler',
				array(
					'islem' => 'duzenle',
					'id'    => $id,
				)
			),
			'Hizmet ekle'         => hk_url(
				'hizmetler',
				array(
					'islem'      => 'yeni',
					'musteri_id' => $id,
					'geri'       => rawurlencode( hk_su_an() ),
				)
			),
			'Hareket ekle'        => hk_url(
				'hareketler',
				array(
					'islem'      => 'yeni',
					'musteri_id' => $id,
					'geri'       => rawurlencode( hk_su_an() ),
				)
			),
			'Ödeme talebi mesajı' => hk_url(
				'musteriler',
				array(
					'islem' => 'mesaj',
					'id'    => $id,
				)
			),
		)
	);
	$tum = hk_ozet();
	$o   = isset( $tum[ $id ] ) ? $tum[ $id ] : array(
		'tahsil'   => 0,
		'bekleyen' => 0,
		'gider'    => 0,
	);

	echo '<p class="hk-iletisim">' . esc_html( implode( '  ·  ', array_filter( array( $m->yetkili, $m->telefon, $m->telefon2, $m->eposta ) ) ) ) . '</p>';
	if ( $m->notlar ) {
		echo '<div class="hk-notkutu">' . nl2br( esc_html( $m->notlar ) ) . '</div>';
	}
	echo '<div class="hk-kartlar">';
	if ( ! $m->kendi ) {
		hk_kart( 'Toplam tahsil edilen', hk_para( $o['tahsil'] ), 'hk-k-yesil' );
		hk_kart( 'Bekleyen alacak', hk_para( $o['bekleyen'] ), $o['bekleyen'] > 0 ? 'hk-k-kirmizi' : 'hk-k-gri' );
	}
	hk_kart( 'Toplam gider', hk_para( $o['gider'] ), 'hk-k-gri' );
	if ( ! $m->kendi ) {
		hk_kart( 'Net', hk_para( $o['tahsil'] - $o['gider'] ), ( $o['tahsil'] - $o['gider'] ) >= 0 ? 'hk-k-yesil' : 'hk-k-kirmizi' );
	}
	echo '</div>';

	echo '<h2>Hizmetler</h2>';
	hk_hizmet_tablosu( hk_hizmetler( array( 'musteri_id' => $id ) ), false );
	echo '<h2>Hareketler</h2>';
	hk_hareket_tablosu( hk_hareketler( array( 'musteri_id' => $id ) ), false );
	echo '</div>';
}

function hk_wa_numara( $tel ) {
	$n = preg_replace( '/\D+/', '', (string) $tel );
	if ( '' === $n ) {
		return '';
	}
	if ( 0 === strpos( $n, '00' ) ) {
		return substr( $n, 2 );
	}
	if ( 11 === strlen( $n ) && '0' === $n[0] ) {
		return '9' . $n;
	}
	if ( 10 === strlen( $n ) && '5' === $n[0] ) {
		return '90' . $n;
	}
	return $n;
}

function hk_musteri_mesaj( $id ) {
	$m = hk_musteri( $id );
	$x = hk_get( 'hareket' ) ? hk_hareket( (int) hk_get( 'hareket' ) ) : null;
	if ( $x && (int) $x->musteri_id !== (int) $id ) {
		$x = null;
	}
	if ( ! $m ) {
		hk_baslik( 'Müşteri bulunamadı' );
		echo '</div>';
		return;
	}
	hk_baslik( 'Ödeme talebi mesajı – ' . $m->ad );
	$metin = hk_mesaj_uret( $m, $x );
	$wa    = hk_wa_numara( $m->telefon );
	$geri  = hk_url(
		'musteriler',
		array(
			'islem' => 'goster',
			'id'    => $id,
		)
	);

	echo '<p>Metni düzenleyip kopyalayabilir veya doğrudan WhatsApp / e-posta ile açabilirsiniz. Şablonu <a href="' . esc_url( hk_url( 'ayarlar' ) ) . '">Ayarlar</a> sayfasından değiştirebilirsiniz.</p>';
	echo '<textarea id="hk_mesaj" rows="10" class="large-text hk-mesaj">' . esc_textarea( $metin ) . '</textarea>';
	echo '<p class="hk-mesaj-btn">';
	echo '<button type="button" class="button button-primary" id="hk_kopyala">Kopyala</button> ';
	$wa2 = hk_wa_numara( $m->telefon2 );
	if ( $wa ) {
		echo '<a class="button hk-wa" target="_blank" rel="noopener" href="#" data-no="' . esc_attr( $wa ) . '">WhatsApp ile aç</a> ';
	}
	if ( $wa2 ) {
		echo '<a class="button hk-wa" target="_blank" rel="noopener" href="#" data-no="' . esc_attr( $wa2 ) . '">WhatsApp (2. numara)</a> ';
	}
	if ( ! $wa && ! $wa2 ) {
		echo '<span class="hk-alt">WhatsApp bağlantısı için müşteriye telefon ekleyin.</span> ';
	}
	if ( $m->eposta ) {
		echo '<a class="button" id="hk_mail" href="#" data-to="' . esc_attr( $m->eposta ) . '">E-posta ile aç</a> ';
	}
	if ( $x && 'odendi' !== $x->durum ) {
		echo hk_dugme(
			'hareket_hizli',
			array(
				'id'   => $x->id,
				'ne'   => 'hatirlat',
				'geri' => $geri,
			),
			'Hatırlatıldı olarak işaretle',
			'button'
		) . ' ';
	}
	echo '<a href="' . esc_url( $geri ) . '">Müşteriye dön</a></p>';
	?>
	<script>
	(function () {
		var t = document.getElementById('hk_mesaj');
		var k = document.getElementById('hk_kopyala');
		k.addEventListener('click', function () {
			t.select();
			if (navigator.clipboard) { navigator.clipboard.writeText(t.value); } else { document.execCommand('copy'); }
			k.textContent = 'Kopyalandı';
			setTimeout(function () { k.textContent = 'Kopyala'; }, 1500);
		});
		document.querySelectorAll('.hk-wa').forEach(function (wa) {
			wa.addEventListener('click', function () { wa.href = 'https://wa.me/' + wa.dataset.no + '?text=' + encodeURIComponent(t.value); });
		});
		var ml = document.getElementById('hk_mail');
		if (ml) { ml.addEventListener('click', function () { ml.href = 'mailto:' + ml.dataset.to + '?subject=' + encodeURIComponent('Alan adı / hosting yenileme') + '&body=' + encodeURIComponent(t.value); }); }
	})();
	</script>
	<?php
	echo '</div>';
}

/* ---------- Hizmetler ---------- */

function hk_sayfa_hizmetler() {
	$islem = hk_get( 'islem' );
	$id    = (int) hk_get( 'id', 0 );
	if ( 'yeni' === $islem || 'duzenle' === $islem ) {
		hk_hizmet_formu( $id );
		return;
	}
	if ( 'yenile' === $islem && $id ) {
		hk_hizmet_yenile_formu( $id );
		return;
	}
	hk_baslik( 'Hizmetler', array( 'Yeni hizmet' => hk_url( 'hizmetler', array( 'islem' => 'yeni' ) ) ) );
	$f = array(
		'musteri_id' => (int) hk_get( 'musteri_id', 0 ),
		'tur'        => hk_get( 'tur' ),
		'aktif'      => hk_get( 'aktif', '1' ),
	);
	if ( 'hepsi' === $f['aktif'] ) {
		$f['aktif'] = '';
	}
	echo '<form method="get" class="hk-filtre"><input type="hidden" name="page" value="hk-hizmetler">';
	hk_musteri_select( $f['musteri_id'], 'Tüm müşteriler' );
	echo ' <select name="tur"><option value="">Tüm türler</option>';
	foreach ( hk_turler() as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $f['tur'], $k, false ), esc_html( $e ) );
	}
	echo '</select> <select name="aktif">';
	foreach ( array(
		'1'     => 'Aktif',
		'0'     => 'Pasif',
		'hepsi' => 'Hepsi',
	) as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( hk_get( 'aktif', '1' ), (string) $k, false ), esc_html( $e ) );
	}
	echo '</select> <button class="button">Filtrele</button></form>';
	$liste = hk_hizmetler( $f );
	hk_hizmet_tablosu( $liste );
	$m = array_sum( array_map( 'floatval', wp_list_pluck( $liste, 'maliyet' ) ) );
	echo '<p class="hk-alt">' . count( $liste ) . ' hizmet · yıllık toplam maliyet (girilmiş olanlar): ' . esc_html( hk_para( $m ) ) . '</p></div>';
}

function hk_hizmet_formu( $id ) {
	$h = $id ? hk_hizmet( $id ) : null;
	hk_baslik( $h ? 'Hizmeti düzenle' : 'Yeni hizmet' );
	hk_form_ac( 'hizmet_kaydet', hk_geri_al() );
	echo '<input type="hidden" name="id" value="' . (int) $id . '"><table class="form-table"><tbody>';
	echo '<tr><th><label for="hk_musteri_id">Müşteri</label></th><td>';
	hk_musteri_select( $h ? $h->musteri_id : (int) hk_get( 'musteri_id', 0 ) );
	echo '</td></tr><tr><th><label for="hk_tur">Tür</label></th><td><select name="tur" id="hk_tur">';
	foreach ( hk_turler() as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $h ? $h->tur : 'domain', $k, false ), esc_html( $e ) );
	}
	echo '</select></td></tr>';
	printf( '<tr><th><label for="hk_ad">Ad</label></th><td><input type="text" id="hk_ad" name="ad" class="regular-text" required placeholder="ornek.com" value="%s"></td></tr>', esc_attr( $h ? $h->ad : '' ) );
	printf( '<tr><th><label for="hk_firma">Kayıt firması / sağlayıcı</label></th><td><input type="text" id="hk_firma" name="firma" class="regular-text" list="hk_firmalar" value="%s"><datalist id="hk_firmalar"><option value="Metunic"><option value="GoDaddy"><option value="Güzel Hosting"><option value="Doruk"><option value="Veridyen"></datalist></td></tr>', esc_attr( $h ? $h->firma : '' ) );
	printf( '<tr><th><label for="hk_bitis">Bitiş tarihi</label></th><td><input type="date" id="hk_bitis" name="bitis" value="%s"></td></tr>', esc_attr( $h && $h->bitis ? $h->bitis : '' ) );
	printf( '<tr><th><label for="hk_maliyet">Yıllık maliyet (₺)</label></th><td><input type="text" id="hk_maliyet" name="maliyet" class="small-text hk-para" inputmode="decimal" value="%s"><p class="description">Kayıt firmasına ödediğiniz tutar.</p></td></tr>', esc_attr( $h && null !== $h->maliyet ? number_format( (float) $h->maliyet, 2, ',', '' ) : '' ) );
	printf( '<tr><th><label for="hk_fiyat">Müşteri fiyatı (₺)</label></th><td><input type="text" id="hk_fiyat" name="fiyat" class="small-text hk-para" inputmode="decimal" value="%s"><p class="description">Müşteriden yıllık aldığınız tutar. Kendi alan adlarınız için boş bırakın.</p></td></tr>', esc_attr( $h && null !== $h->fiyat ? number_format( (float) $h->fiyat, 2, ',', '' ) : '' ) );
	echo '<tr class="hk-hosting-alani"><th><label for="hk_bagli">Bağlı alan adı</label></th><td><select name="bagli_id" id="hk_bagli"><option value="0">— bağlı değil —</option>';
	$grup = null;
	foreach ( hk_hizmetler( array( 'tur' => 'domain' ) ) as $d ) {
		if ( $grup !== $d->musteri_ad ) {
			echo ( null !== $grup ? '</optgroup>' : '' ) . '<optgroup label="' . esc_attr( $d->musteri_ad ) . '">';
			$grup = $d->musteri_ad;
		}
		printf( '<option value="%d"%s>%s</option>', (int) $d->id, selected( $h ? (int) $h->bagli_id : 0, (int) $d->id, false ), esc_html( $d->ad ) );
	}
	echo ( null !== $grup ? '</optgroup>' : '' ) . '</select><p class="description">Seçerseniz alan adı yenilendiğinde hostingin bitiş tarihi de aynı güne taşınır. Tarihi yine elle değiştirebilirsiniz.</p></td></tr>';
	printf( '<tr class="hk-hosting-alani"><th><label for="hk_pay">Pay sayısı</label></th><td><input type="number" id="hk_pay" name="pay" class="small-text" min="1" max="99" value="%d"><p class="description">Bu kayıt kaç hosting hesabını temsil ediyor? Hosting paketi maliyeti pay sayısına göre dağıtılır.</p></td></tr>', $h ? max( 1, (int) $h->pay ) : 1 );
	printf(
		'<tr class="hk-hosting-alani"><th>Hosting seçenekleri</th><td><label><input type="checkbox" name="musteri_oder" value="1"%s> Ücretini müşteri sağlayıcıya kendisi ödüyor (bana maliyeti yok, paket dağıtımına girmez)</label><br><label><input type="checkbox" name="paket" value="1"%s> Bu bir hosting paketi (ör. bayi paketi): gideri, aynı sağlayıcıda hostingi olan müşterilere dağıtılır</label></td></tr>',
		checked( $h ? (int) $h->musteri_oder : 0, 1, false ),
		checked( $h ? (int) $h->paket : 0, 1, false )
	);
	printf( '<tr><th>Durum</th><td><label><input type="checkbox" name="aktif" value="1"%s> Aktif</label><br><label><input type="checkbox" name="kontrol" value="1"%s> Kontrol edilecek olarak işaretle</label></td></tr>', checked( $h ? (int) $h->aktif : 1, 1, false ), checked( $h ? (int) $h->kontrol : 0, 1, false ) );
	printf( '<tr><th><label for="hk_notlar">Notlar</label></th><td><textarea id="hk_notlar" name="notlar" rows="3" class="large-text">%s</textarea></td></tr>', esc_textarea( $h ? $h->notlar : '' ) );
	echo '</tbody></table>';
	submit_button( 'Kaydet' );
	echo '</form>';
	if ( $h ) {
		echo '<p>' . hk_dugme( 'hizmet_sil', array( 'id' => $h->id, 'geri' => hk_url( 'hizmetler' ) ), 'Hizmeti sil', 'button-link hk-sil', 'Hizmet silinsin mi? Hareket kayıtları korunur.' ) . '</p>';
	}
	?>
	<script>
	(function () {
		var t = document.getElementById('hk_tur');
		function g() { document.querySelectorAll('.hk-hosting-alani').forEach(function (e) { e.style.display = t.value === 'hosting' ? '' : 'none'; }); }
		t.addEventListener('change', g); g();
	})();
	</script>
	<?php
	echo '</div>';
}

function hk_hizmet_yenile_formu( $id ) {
	$h = hk_hizmet( $id );
	if ( ! $h ) {
		hk_baslik( 'Hizmet bulunamadı' );
		echo '</div>';
		return;
	}
	$zam   = (float) hk_ayar( 'zam' );
	$bugun = hk_bugun();
	$yeni  = $h->bitis ? hk_bir_yil_sonra( $h->bitis ) : hk_bir_yil_sonra( $bugun );
	while ( $yeni <= $bugun ) {
		$yeni = hk_bir_yil_sonra( $yeni );
	}
	$onceki = null !== $h->fiyat ? (float) $h->fiyat : null;

	hk_baslik( 'Yenile – ' . $h->ad );
	echo '<p>' . esc_html( $h->musteri_ad ) . ' · mevcut bitiş: <strong>' . esc_html( hk_tarih( $h->bitis ) ) . '</strong> ' . hk_kalan_rozet( $h->bitis ) . '</p>';
	if ( $h->paket ) {
		echo '<div class="hk-notkutu">Bu bir hosting paketi. Ödediğiniz tutar gider olarak kaydedilir ve raporlarda aynı sağlayıcıdaki müşterilere dağıtılır; bu yılın tahmini gideri yerine gerçek tutar geçer.</div>';
	} elseif ( 'hosting' === $h->tur && ! $h->musteri_oder && ! $h->kendi ) {
		$pb = hk_pay_basi( $h->firma );
		if ( $pb ) {
			echo '<div class="hk-notkutu">Bu hostingin size maliyeti yaklaşık <strong>' . esc_html( hk_para( round( $pb['pay_basi'] * max( 1, (int) $h->pay ) ) ) ) . '</strong> (' . (int) $h->pay . ' pay' . ( $pb['tahmini'] ? ', bu yılın paket ödemesi yapılmadığı için tahmini' : '' ) . '). Fiyat verirken bunu taban alın. Bu sayfada gider girmeyin; maliyet paketten dağıtılıyor.</div>';
		}
	} elseif ( 'domain' === $h->tur ) {
		global $wpdb;
		$bagli = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hk_t( 'hizmetler' ) . ' WHERE bagli_id = %d', $h->id ) );
		if ( $bagli ) {
			echo '<div class="hk-notkutu">Bu alan adına bağlı ' . (int) $bagli . ' hosting kaydının bitiş tarihi de aynı güne taşınacak.</div>';
		}
	}
	$paylasimli = ( 'hosting' === $h->tur && ! $h->paket && ! $h->musteri_oder );
	hk_form_ac( 'hizmet_yenile', hk_geri_al() );
	echo '<input type="hidden" name="id" value="' . (int) $id . '"><table class="form-table"><tbody>';
	printf( '<tr><th><label for="hk_yeni_bitis">Yeni bitiş tarihi</label></th><td><input type="date" id="hk_yeni_bitis" name="yeni_bitis" required value="%s"></td></tr>', esc_attr( $yeni ) );

	echo '<tr><th>Gider</th><td><label><input type="checkbox" name="gider_ac" value="1"' . ( $paylasimli ? '' : ' checked' ) . '> Firmaya ödediğim tutarı gider olarak kaydet</label><br>';
	printf( '<input type="text" name="gider_tutar" class="small-text hk-para" inputmode="decimal" value="%s"> ₺ &nbsp; ödeme tarihi <input type="date" name="gider_tarih" value="%s">', esc_attr( null !== $h->maliyet ? number_format( (float) $h->maliyet, 2, ',', '' ) : '' ), esc_attr( $bugun ) );
	echo '<p class="description">Tutar boşsa gider kaydı açılmaz. Girdiğiniz tutar hizmetin yeni yıllık maliyeti olur.</p></td></tr>';

	echo '<tr><th>Müşteriden alacak</th><td>';
	printf( '<label><input type="checkbox" name="alacak_ac" value="1"%s> Müşteri için alacak kaydı aç</label><br>', checked( ! $h->kendi && ! $h->paket, true, false ) );
	printf(
		'<input type="text" name="alacak_tutar" id="hk_alacak" class="small-text hk-para" inputmode="decimal" value="%s"> ₺ &nbsp; ',
		esc_attr( null !== $onceki ? number_format( round( $onceki * ( 1 + $zam / 100 ) ), 0, ',', '' ) : '' )
	);
	echo '<select name="alacak_durum"><option value="bekliyor">İstenecek</option><option value="istendi">İstendi (bugün)</option><option value="odendi">Ödendi (bugün)</option></select>';
	if ( null !== $onceki ) {
		printf(
			'<p class="description">Önceki fiyat %s · zam oranı <input type="number" id="hk_zam" class="small-text" min="0" max="500" step="1" value="%s" data-onceki="%s"> %%</p>',
			esc_html( hk_para( $onceki ) ),
			esc_attr( $zam ),
			esc_attr( $onceki )
		);
	} else {
		echo '<p class="description">Bu hizmet için kayıtlı müşteri fiyatı yok; tutarı elle girin. Girdiğiniz tutar hizmetin yeni fiyatı olur.</p>';
	}
	echo '</td></tr></tbody></table>';
	submit_button( 'Yenilemeyi kaydet' );
	echo '</form>';
	?>
	<script>
	(function () {
		var z = document.getElementById('hk_zam'), a = document.getElementById('hk_alacak');
		if (z && a) { z.addEventListener('input', function () { a.value = Math.round(parseFloat(z.dataset.onceki) * (1 + (parseFloat(z.value) || 0) / 100)); }); }
	})();
	</script>
	<?php
	echo '</div>';
}

/* ---------- Hareketler ---------- */

function hk_sayfa_hareketler() {
	$islem = hk_get( 'islem' );
	if ( 'yeni' === $islem || 'duzenle' === $islem ) {
		hk_hareket_formu( (int) hk_get( 'id', 0 ) );
		return;
	}
	if ( 'toplu' === $islem ) {
		hk_hareket_toplu_formu();
		return;
	}
	$f = array(
		'musteri_id' => (int) hk_get( 'musteri_id', 0 ),
		'tur'        => hk_get( 'tur' ),
		'durum'      => hk_get( 'durum' ),
		'donem'      => (int) hk_get( 'donem', 0 ),
		'kategori'   => hk_get( 'kategori' ),
	);
	hk_baslik(
		'Hareketler',
		array(
			'Yeni hareket' => hk_url( 'hareketler', array( 'islem' => 'yeni' ) ),
			'Toplu giriş'  => hk_url( 'hareketler', array( 'islem' => 'toplu' ) ),
			'CSV indir'    => hk_islem_url( 'csv', array_filter( $f ) ),
		)
	);
	echo '<form method="get" class="hk-filtre"><input type="hidden" name="page" value="hk-hareketler">';
	echo '<select name="donem"><option value="">Tüm dönemler</option>';
	foreach ( hk_donemler() as $d ) {
		printf( '<option value="%d"%s>%d</option>', $d, selected( $f['donem'], $d, false ), $d );
	}
	echo '</select> ';
	hk_musteri_select( $f['musteri_id'], 'Tüm müşteriler' );
	echo ' <select name="tur"><option value="">Gelir + gider</option><option value="gelir"' . selected( $f['tur'], 'gelir', false ) . '>Gelir</option><option value="gider"' . selected( $f['tur'], 'gider', false ) . '>Gider</option></select>';
	echo ' <select name="durum"><option value="">Tüm durumlar</option>';
	foreach ( hk_durumlar() as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $f['durum'], $k, false ), esc_html( $e ) );
	}
	echo '</select> ' . hk_kategori_select( $f['kategori'], 'kategori', 'Tüm kategoriler' ) . ' <button class="button">Filtrele</button></form>';

	$liste  = hk_hareketler( $f );
	$tahsil = 0;
	$bekle  = 0;
	$gider  = 0;
	foreach ( $liste as $x ) {
		if ( 'gider' === $x->tur ) {
			$gider += (float) $x->tutar;
		} elseif ( 'odendi' === $x->durum ) {
			$tahsil += (float) $x->tutar;
		} else {
			$bekle += (float) $x->tutar;
		}
	}
	echo '<div class="hk-kartlar">';
	hk_kart( 'Tahsil edilen', hk_para( $tahsil ), 'hk-k-yesil' );
	hk_kart( 'Bekleyen', hk_para( $bekle ), $bekle > 0 ? 'hk-k-kirmizi' : 'hk-k-gri' );
	hk_kart( 'Gider', hk_para( $gider ), 'hk-k-gri' );
	hk_kart( 'Net', hk_para( $tahsil - $gider ), ( $tahsil - $gider ) >= 0 ? 'hk-k-yesil' : 'hk-k-kirmizi' );
	echo '</div>';
	hk_hareket_tablosu( $liste );
	echo '</div>';
}

function hk_hareket_formu( $id ) {
	$x   = $id ? hk_hareket( $id ) : null;
	$tur = $x ? $x->tur : ( 'gider' === hk_get( 'tur' ) ? 'gider' : 'gelir' );
	hk_baslik( $x ? 'Hareketi düzenle' : 'Yeni hareket' );
	hk_form_ac( 'hareket_kaydet', hk_geri_al() );
	echo '<input type="hidden" name="id" value="' . (int) $id . '"><table class="form-table"><tbody>';
	printf(
		'<tr><th>Tür</th><td><label><input type="radio" name="tur" value="gelir"%s> Gelir (müşteriden alınan / alınacak)</label><br><label><input type="radio" name="tur" value="gider"%s> Gider (firmaya ödediğim)</label></td></tr>',
		checked( $tur, 'gelir', false ),
		checked( $tur, 'gider', false )
	);
	echo '<tr><th><label for="hk_musteri_id">Müşteri</label></th><td>';
	hk_musteri_select( $x ? $x->musteri_id : (int) hk_get( 'musteri_id', 0 ) );
	echo '</td></tr><tr><th><label for="hk_hizmet_id">Hizmet</label></th><td><select name="hizmet_id" id="hk_hizmet_id"><option value="0">— genel / birden çok hizmet —</option>';
	$grup = null;
	foreach ( hk_hizmetler() as $h ) {
		if ( $grup !== $h->musteri_ad ) {
			echo ( null !== $grup ? '</optgroup>' : '' ) . '<optgroup label="' . esc_attr( $h->musteri_ad ) . '">';
			$grup = $h->musteri_ad;
		}
		printf( '<option value="%d"%s>%s</option>', (int) $h->id, selected( $x ? (int) $x->hizmet_id : 0, (int) $h->id, false ), esc_html( $h->ad ) );
	}
	echo ( null !== $grup ? '</optgroup>' : '' ) . '</select></td></tr>';
	echo '<tr><th>Kategori</th><td>' . hk_kategori_select( $x ? $x->kategori : 'yenileme' ) . '<p class="description">Yeni site veya site yenileme gelirlerini ayrı işaretlerseniz raporlarda ayrı görünür. Hosting paketi ödemeleri için "Hosting paketi" seçin.</p></td></tr>';
	printf( '<tr><th><label for="hk_tutar">Tutar (₺)</label></th><td><input type="text" id="hk_tutar" name="tutar" class="small-text hk-para" inputmode="decimal" required value="%s"></td></tr>', esc_attr( $x ? number_format( (float) $x->tutar, 2, ',', '' ) : '' ) );
	printf( '<tr><th><label for="hk_tarih">Tarih</label></th><td><input type="date" id="hk_tarih" name="tarih" value="%s"><p class="description">Gelirde: ödemenin istendiği tarih. Giderde: ödediğiniz tarih.</p></td></tr>', esc_attr( $x ? (string) $x->tarih : hk_bugun() ) );
	printf( '<tr><th><label for="hk_donem">Dönem (yıl)</label></th><td><input type="number" id="hk_donem" name="donem" class="small-text" min="2000" max="2100" value="%d"><p class="description">Kaydın hangi yılın hesabına yazılacağı.</p></td></tr>', $x ? (int) $x->donem : (int) current_time( 'Y' ) );
	echo '<tr class="hk-gelir-alani"><th><label for="hk_durum">Durum</label></th><td><select name="durum" id="hk_durum">';
	foreach ( hk_durumlar() as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $x ? $x->durum : 'istendi', $k, false ), esc_html( $e ) );
	}
	echo '</select></td></tr>';
	printf( '<tr class="hk-gelir-alani"><th><label for="hk_odeme">Ödeme tarihi</label></th><td><input type="date" id="hk_odeme" name="odeme_tarihi" value="%s"><p class="description">Durum "Ödendi" ise doldurun.</p></td></tr>', esc_attr( $x ? (string) $x->odeme_tarihi : '' ) );
	printf( '<tr class="hk-gelir-alani"><th><label for="hk_hat">Hatırlatma sayısı</label></th><td><input type="number" id="hk_hat" name="hatirlatma" class="small-text" min="0" value="%d">%s</td></tr>', $x ? (int) $x->hatirlatma : 0, $x && $x->son_hatirlatma ? ' <span class="hk-alt">son: ' . esc_html( hk_tarih( $x->son_hatirlatma ) ) . '</span>' : '' );
	printf( '<tr><th><label for="hk_aciklama">Açıklama</label></th><td><textarea id="hk_aciklama" name="aciklama" rows="3" class="large-text">%s</textarea></td></tr>', esc_textarea( $x ? $x->aciklama : '' ) );
	printf( '<tr><th>Kontrol</th><td><label><input type="checkbox" name="kontrol" value="1"%s> Kontrol edilecek olarak işaretle</label></td></tr>', checked( $x ? (int) $x->kontrol : 0, 1, false ) );
	echo '</tbody></table>';
	submit_button( 'Kaydet' );
	echo '</form>';
	if ( $x ) {
		echo '<p>' . hk_dugme( 'hareket_sil', array( 'id' => $x->id, 'geri' => hk_geri_al() ? hk_geri_al() : hk_url( 'hareketler' ) ), 'Hareketi sil', 'button-link hk-sil', 'Hareket silinsin mi?' ) . '</p>';
	}
	?>
	<script>
	(function () {
		var r = document.querySelectorAll('input[name="tur"]');
		function g() {
			var gelir = document.querySelector('input[name="tur"]:checked').value === 'gelir';
			document.querySelectorAll('.hk-gelir-alani').forEach(function (e) { e.style.display = gelir ? '' : 'none'; });
		}
		r.forEach(function (e) { e.addEventListener('change', g); });
		g();
	})();
	</script>
	<?php
	echo '</div>';
}

function hk_hareket_toplu_formu() {
	hk_baslik( 'Toplu giriş' );
	echo '<p>Geçmiş yılların kayıtlarını hızlı girmek için. Tutarı boş bıraktığınız satırlar atlanır. Kayıtlar "ödendi" olarak eklenir; dönem boşsa tarihin yılı kullanılır. Tarihini bilmediğiniz kayıtlarda yalnızca dönemi yazmanız yeterli.</p>';
	hk_form_ac( 'hareket_toplu' );
	$musteriler = hk_musteriler();
	echo '<table class="wp-list-table widefat striped hk-tablo hk-toplu"><thead><tr><th>Tarih</th><th>Dönem</th><th>Müşteri</th><th>Tür</th><th>Kategori</th><th>Tutar (₺)</th><th>Açıklama</th></tr></thead><tbody>';
	for ( $i = 0; $i < 12; $i++ ) {
		echo '<tr><td><input type="date" name="s[' . $i . '][tarih]"></td>';
		echo '<td><input type="number" name="s[' . $i . '][donem]" class="small-text" min="2000" max="2100" placeholder="yıl"></td>';
		echo '<td><select name="s[' . $i . '][musteri_id]"><option value="0">—</option>';
		foreach ( $musteriler as $m ) {
			printf( '<option value="%d">%s</option>', (int) $m->id, esc_html( $m->ad ) );
		}
		echo '</select></td>';
		echo '<td><select name="s[' . $i . '][tur]"><option value="gelir">Gelir</option><option value="gider">Gider</option></select></td>';
		echo '<td>' . hk_kategori_select( 'yenileme', 's[' . $i . '][kategori]' ) . '</td>';
		echo '<td><input type="text" name="s[' . $i . '][tutar]" class="hk-para" inputmode="decimal"></td>';
		echo '<td><input type="text" name="s[' . $i . '][aciklama]" class="regular-text"></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Kayıtları ekle' );
	echo '</form></div>';
}

/* ---------- Kontrol edilecekler ---------- */

function hk_sayfa_kontrol() {
	hk_baslik( 'Kontrol edilecekler' );
	$hz = hk_hizmetler( array( 'kontrol' => 1 ) );
	$hr = hk_hareketler( array( 'kontrol' => 1 ) );
	if ( ! $hz && ! $hr ) {
		echo '<div class="hk-bos"><h2>Kontrol bekleyen kayıt yok</h2><p>Tüm kayıtlar onaylanmış.</p></div></div>';
		return;
	}
	echo '<p>Bu kayıtlar, tarih, tutar ya da durumu belirsiz olduğu için işaretlendi. Doğruysa <strong>Onayla</strong>, değilse <strong>Düzenle</strong> ile düzeltin. Yenilenmiş alan adlarının tarihini <strong>+1 yıl</strong> ile ilerletebilirsiniz (gider kaydı açmaz).</p>';

	echo '<h2>Hizmetler <span class="hk-alt">' . count( $hz ) . ' kayıt</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo"><thead><tr><th>Hizmet</th><th>Müşteri</th><th>Tablodaki tarih</th><th>Neden işaretli</th><th>İşlem</th></tr></thead><tbody>';
	foreach ( $hz as $h ) {
		$geri = rawurlencode( hk_su_an() );
		echo '<tr><td><strong>' . esc_html( $h->ad ) . '</strong></td><td>' . esc_html( $h->musteri_ad ) . '</td><td>' . esc_html( hk_tarih( $h->bitis ) ) . ' ' . hk_kalan_rozet( $h->bitis ) . '</td><td>' . esc_html( (string) $h->notlar ) . '</td><td class="hk-islem">';
		if ( $h->bitis ) {
			echo hk_dugme( 'hizmet_hizli', array( 'id' => $h->id, 'ne' => 'ilerlet' ), '+1 yıl', 'button button-small button-primary' ) . ' ';
		}
		echo hk_dugme( 'hizmet_hizli', array( 'id' => $h->id, 'ne' => 'onayla' ), 'Onayla' ) . ' ';
		printf( '<a class="button button-small" href="%s">Düzenle</a>', esc_url( hk_url( 'hizmetler', array( 'islem' => 'duzenle', 'id' => $h->id, 'geri' => $geri ) ) ) );
		echo '</td></tr>';
	}
	if ( ! $hz ) {
		echo '<tr><td colspan="5">Kontrol bekleyen hizmet yok.</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Hareketler <span class="hk-alt">' . count( $hr ) . ' kayıt</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo"><thead><tr><th>Dönem</th><th>Tarih</th><th>Müşteri</th><th>Açıklama</th><th class="hk-sag">Gelir</th><th class="hk-sag">Gider</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
	foreach ( $hr as $x ) {
		$geri = rawurlencode( hk_su_an() );
		echo '<tr><td>' . (int) $x->donem . '</td><td>' . esc_html( hk_tarih( $x->tarih ) ) . '</td><td>' . esc_html( $x->musteri_ad ) . '</td>';
		echo '<td>' . ( $x->hizmet_ad ? '<strong>' . esc_html( $x->hizmet_ad ) . '</strong>' : '' ) . '<div class="hk-not">' . esc_html( (string) $x->aciklama ) . '</div></td>';
		echo '<td class="hk-sag">' . ( 'gelir' === $x->tur ? esc_html( hk_para( $x->tutar ) ) : '' ) . '</td><td class="hk-sag">' . ( 'gider' === $x->tur ? esc_html( hk_para( $x->tutar ) ) : '' ) . '</td><td>' . hk_durum_rozet( $x ) . '</td><td class="hk-islem">';
		echo hk_dugme( 'hareket_hizli', array( 'id' => $x->id, 'ne' => 'onayla' ), 'Onayla', 'button button-small button-primary' ) . ' ';
		printf( '<a class="button button-small" href="%s">Düzenle</a>', esc_url( hk_url( 'hareketler', array( 'islem' => 'duzenle', 'id' => $x->id, 'geri' => $geri ) ) ) );
		echo '</td></tr>';
	}
	if ( ! $hr ) {
		echo '<tr><td colspan="8">Kontrol bekleyen hareket yok.</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ---------- Ayarlar ---------- */

function hk_sayfa_ayarlar() {
	$a = hk_ayar();
	hk_baslik( 'Ayarlar' );
	hk_form_ac( 'ayar_kaydet' );
	echo '<h2>Hatırlatma e-postası</h2><table class="form-table"><tbody>';
	printf( '<tr><th><label for="hk_eposta">E-posta adresi</label></th><td><input type="email" id="hk_eposta" name="eposta" class="regular-text" value="%s"></td></tr>', esc_attr( $a['eposta'] ) );
	printf( '<tr><th><label for="hk_gunler">Bitişe kaç gün kala</label></th><td><input type="text" id="hk_gunler" name="gunler" class="regular-text" value="%s"><p class="description">Virgülle ayırın. Örnek: 30,7,1 → bitişe 30, 7 ve 1 gün kala e-posta gelir.</p></td></tr>', esc_attr( $a['gunler'] ) );
	printf( '<tr><th>Haftalık özet</th><td><label><input type="checkbox" name="haftalik" value="1"%s> Her pazartesi yaklaşan yenilemeler ve bekleyen alacakların özetini gönder</label></td></tr>', checked( (int) $a['haftalik'], 1, false ) );
	echo '</tbody></table><h2>Panel ve yenileme</h2><table class="form-table"><tbody>';
	printf( '<tr><th><label for="hk_panel_gun">Panelde gösterilecek aralık</label></th><td><input type="number" id="hk_panel_gun" name="panel_gun" class="small-text" min="7" max="365" value="%d"> gün</td></tr>', (int) $a['panel_gun'] );
	printf( '<tr><th><label for="hk_zam_a">Varsayılan yıllık zam oranı</label></th><td><input type="number" id="hk_zam_a" name="zam" class="small-text" min="0" max="500" step="1" value="%s"> %%<p class="description">Yenilemede müşteri fiyatı önerisi: önceki fiyat × (1 + oran). Her yenilemede değiştirebilirsiniz.</p></td></tr>', esc_attr( $a['zam'] ) );
	printf( '<tr><th><label for="hk_hzam">Beklenen hosting paketi artışı</label></th><td><input type="number" id="hk_hzam" name="hosting_zam" class="small-text" min="0" max="500" step="1" value="%s"> %%<p class="description">İçinde bulunulan yılın paket ödemesi yapılana kadar tahmini gider: önceki yılın paket gideri × (1 + oran).</p></td></tr>', esc_attr( $a['hosting_zam'] ) );
	echo '</tbody></table><h2>Kur ve enflasyon</h2><p class="description">Raporlardaki "dolar karşılığı" ve "bugünkü fiyatlarla" görünümleri bu değerleri kullanır. Kur: yıllık ortalama dolar kuru (TCMB). TÜFE: on iki aylık ortalamalara göre değişim (TÜİK). İçinde bulunulan yılın değerleri geçicidir; yıl bitince güncelleyin.</p>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-yillar"><thead><tr><th>Yıl</th><th>Ortalama dolar kuru (₺)</th><th>TÜFE (%)</th></tr></thead><tbody>';
	$yillar = hk_yillar();
	$son    = $yillar ? max( array_keys( $yillar ) ) : (int) current_time( 'Y' );
	for ( $yil = max( $son, (int) current_time( 'Y' ) ) + 1; $yil > $son; $yil-- ) {
		$yillar[ $yil ] = array(
			'kur'  => '',
			'tufe' => '',
		);
	}
	ksort( $yillar );
	foreach ( $yillar as $yil => $v ) {
		printf(
			'<tr><td><strong>%d</strong></td><td><input type="text" name="yillar[%d][kur]" class="hk-para" inputmode="decimal" value="%s"></td><td><input type="text" name="yillar[%d][tufe]" class="hk-para" inputmode="decimal" value="%s"></td></tr>',
			(int) $yil,
			(int) $yil,
			esc_attr( '' === $v['kur'] ? '' : str_replace( '.', ',', (string) $v['kur'] ) ),
			(int) $yil,
			esc_attr( '' === $v['tufe'] ? '' : str_replace( '.', ',', (string) $v['tufe'] ) )
		);
	}
	echo '</tbody></table><table class="form-table"><tbody>';
	echo '</tbody></table><h2>Müşteri mesaj şablonu</h2><table class="form-table"><tbody>';
	printf( '<tr><th><label for="hk_sablon">Şablon</label></th><td><textarea id="hk_sablon" name="sablon" rows="9" class="large-text">%s</textarea><p class="description">Kullanılabilir alanlar: <code>{musteri}</code> <code>{firma}</code> <code>{hizmetler}</code> <code>{tutar}</code> <code>{aciklama}</code> <code>{tarih}</code>. Banka / IBAN bilginizi buraya ekleyebilirsiniz.</p></td></tr>', esc_textarea( $a['sablon'] ) );
	echo '</tbody></table>';
	submit_button( 'Ayarları kaydet' );
	echo '</form>';

	echo '<h2>Araçlar</h2>';
	$sonraki = wp_next_scheduled( 'hk_gunluk' );
	echo '<p>Günlük kontrol: ' . ( $sonraki ? 'sıradaki çalışma <strong>' . esc_html( wp_date( 'd.m.Y H:i', $sonraki ) ) . '</strong>' : '<strong class="hk-eksi">zamanlanmamış</strong> – eklentiyi devre dışı bırakıp yeniden etkinleştirin' ) . '.</p>';
	echo '<p class="description">WordPress zamanlanmış görevleri siteye ziyaret geldikçe çalışır. Siteniz az ziyaret alıyorsa cPanel → Cron Jobs bölümünden günde bir kez <code>wp-cron.php</code> çağrısı ekleyin: <code>wget -q -O - ' . esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' &gt;/dev/null 2&gt;&amp;1</code></p>';
	echo '<p>' . hk_dugme( 'test_eposta', array(), 'Özet e-postasını şimdi gönder (test)', 'button' ) . '</p>';

	echo '<h2>Sürüm</h2><p>Kurulu sürüm: <strong>' . esc_html( HK_VER ) . '</strong></p>';
	$gunluk = HK_DIR . 'CHANGELOG.md';
	if ( file_exists( $gunluk ) ) {
		echo '<pre class="hk-gunluk">' . esc_html( file_get_contents( $gunluk ) ) . '</pre>';
	}
	echo '</div>';
}
