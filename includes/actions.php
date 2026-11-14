<?php
/**
 * Form işlemleri (admin-post.php üzerinden).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'musteri_kaydet', 'musteri_sil', 'hizmet_kaydet', 'hizmet_sil', 'hizmet_yenile', 'hizmet_hizli', 'hareket_kaydet', 'hareket_sil', 'hareket_hizli', 'ayar_kaydet', 'ice_aktar', 'test_eposta', 'csv' ) as $hk_islem ) {
	add_action( 'admin_post_hk_' . $hk_islem, 'hk_do_' . $hk_islem );
}
unset( $hk_islem );

/** Yetki + nonce kontrolü. */
function hk_guard( $islem ) {
	if ( ! current_user_can( HK_CAP ) ) {
		wp_die( 'Bu işlem için yetkiniz yok.', 403 );
	}
	check_admin_referer( 'hk_' . $islem );
}

function hk_url( $sayfa, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'hk-' . $sayfa ), $args ), admin_url( 'admin.php' ) );
}

/** İşlem bağlantısı (nonce'lu GET). */
function hk_islem_url( $islem, $args = array() ) {
	$args['action'] = 'hk_' . $islem;
	if ( empty( $args['geri'] ) ) {
		$args['geri'] = rawurlencode( hk_su_an() );
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'hk_' . $islem );
}

/** Bulunulan sayfanın adresi (iç içe geri/hk_msg parametreleri olmadan). */
function hk_su_an() {
	$u = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return $u ? remove_query_arg( array( 'geri', 'hk_msg' ), $u ) : '';
}

/** Adres çubuğundaki geri parametresi (form sayfalarında gizli alana taşınır). */
function hk_geri_al() {
	return isset( $_GET['geri'] ) ? esc_url_raw( wp_unslash( $_GET['geri'] ) ) : '';
}

function hk_geri( $mesaj, $varsayilan ) {
	$geri = isset( $_REQUEST['geri'] ) ? wp_unslash( $_REQUEST['geri'] ) : '';
	$geri = $geri ? wp_validate_redirect( $geri, $varsayilan ) : $varsayilan;
	$geri = remove_query_arg( array( 'hk_msg' ), $geri );
	wp_safe_redirect( add_query_arg( 'hk_msg', $mesaj, $geri ) );
	exit;
}

function hk_post( $k, $v = '' ) {
	return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : $v;
}

/* ---------- Müşteri ---------- */

function hk_do_musteri_kaydet() {
	hk_guard( 'musteri_kaydet' );
	global $wpdb;
	$id = (int) hk_post( 'id', 0 );
	$d  = array(
		'ad'      => sanitize_text_field( hk_post( 'ad' ) ),
		'firma'   => sanitize_text_field( hk_post( 'firma' ) ),
		'telefon' => sanitize_text_field( hk_post( 'telefon' ) ),
		'eposta'  => sanitize_email( hk_post( 'eposta' ) ),
		'notlar'  => sanitize_textarea_field( hk_post( 'notlar' ) ),
		'kendi'   => hk_post( 'kendi' ) ? 1 : 0,
	);
	if ( '' === $d['ad'] ) {
		hk_geri( 'ad_gerekli', hk_url( 'musteriler' ) );
	}
	if ( $id ) {
		$wpdb->update( hk_t( 'musteriler' ), $d, array( 'id' => $id ) );
	} else {
		$wpdb->insert( hk_t( 'musteriler' ), $d );
		$id = (int) $wpdb->insert_id;
	}
	wp_safe_redirect(
		hk_url(
			'musteriler',
			array(
				'islem'  => 'goster',
				'id'     => $id,
				'hk_msg' => 'kaydedildi',
			)
		)
	);
	exit;
}

function hk_do_musteri_sil() {
	hk_guard( 'musteri_sil' );
	global $wpdb;
	$id = (int) ( isset( $_GET['id'] ) ? $_GET['id'] : 0 );
	$n  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hk_t( 'hizmetler' ) . ' WHERE musteri_id = %d', $id ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hk_t( 'hareketler' ) . ' WHERE musteri_id = %d', $id ) );
	if ( $n > 0 ) {
		wp_safe_redirect( hk_url( 'musteriler', array( 'hk_msg' => 'musteri_dolu' ) ) );
		exit;
	}
	$wpdb->delete( hk_t( 'musteriler' ), array( 'id' => $id ) );
	wp_safe_redirect( hk_url( 'musteriler', array( 'hk_msg' => 'silindi' ) ) );
	exit;
}

/* ---------- Hizmet ---------- */

function hk_do_hizmet_kaydet() {
	hk_guard( 'hizmet_kaydet' );
	global $wpdb;
	$id  = (int) hk_post( 'id', 0 );
	$tur = sanitize_key( hk_post( 'tur', 'domain' ) );
	$d   = array(
		'musteri_id' => (int) hk_post( 'musteri_id' ),
		'tur'        => array_key_exists( $tur, hk_turler() ) ? $tur : 'domain',
		'ad'         => sanitize_text_field( hk_post( 'ad' ) ),
		'firma'      => sanitize_text_field( hk_post( 'firma' ) ),
		'bitis'      => hk_tarih_oku( hk_post( 'bitis' ) ),
		'maliyet'    => hk_para_oku( hk_post( 'maliyet' ) ),
		'fiyat'      => hk_para_oku( hk_post( 'fiyat' ) ),
		'aktif'      => hk_post( 'aktif' ) ? 1 : 0,
		'kontrol'    => hk_post( 'kontrol' ) ? 1 : 0,
		'notlar'     => sanitize_textarea_field( hk_post( 'notlar' ) ),
	);
	if ( '' === $d['ad'] || ! hk_musteri( $d['musteri_id'] ) ) {
		hk_geri( 'eksik', hk_url( 'hizmetler' ) );
	}
	if ( $id ) {
		$wpdb->update( hk_t( 'hizmetler' ), $d, array( 'id' => $id ) );
	} else {
		$wpdb->insert( hk_t( 'hizmetler' ), $d );
	}
	hk_geri( 'kaydedildi', hk_url( 'hizmetler' ) );
}

function hk_do_hizmet_sil() {
	hk_guard( 'hizmet_sil' );
	global $wpdb;
	$id = (int) ( isset( $_GET['id'] ) ? $_GET['id'] : 0 );
	// Hareket kayıtları silinmez; yalnızca hizmet bağlantısı kaldırılır.
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . hk_t( 'hareketler' ) . ' SET hizmet_id = NULL WHERE hizmet_id = %d', $id ) );
	$wpdb->delete( hk_t( 'hizmetler' ), array( 'id' => $id ) );
	hk_geri( 'silindi', hk_url( 'hizmetler' ) );
}

/** Hızlı işlemler: +1 yıl ilerlet / kontrol işaretini kaldır. */
function hk_do_hizmet_hizli() {
	hk_guard( 'hizmet_hizli' );
	global $wpdb;
	$id = (int) ( isset( $_GET['id'] ) ? $_GET['id'] : 0 );
	$ne = isset( $_GET['ne'] ) ? sanitize_key( $_GET['ne'] ) : '';
	$h  = hk_hizmet( $id );
	if ( $h ) {
		if ( 'ilerlet' === $ne && $h->bitis ) {
			// Tarih bugünü geçene kadar birer yıl ilerlet.
			$yeni = hk_bir_yil_sonra( $h->bitis );
			while ( $yeni <= hk_bugun() ) {
				$yeni = hk_bir_yil_sonra( $yeni );
			}
			$wpdb->update(
				hk_t( 'hizmetler' ),
				array(
					'bitis'   => $yeni,
					'kontrol' => 0,
				),
				array( 'id' => $id )
			);
		} elseif ( 'onayla' === $ne ) {
			$wpdb->update( hk_t( 'hizmetler' ), array( 'kontrol' => 0 ), array( 'id' => $id ) );
		}
	}
	hk_geri( 'guncellendi', hk_url( 'kontrol' ) );
}

/** Yenileme: bitişi ilerletir; isteğe bağlı gider ve alacak kaydı açar. */
function hk_do_hizmet_yenile() {
	hk_guard( 'hizmet_yenile' );
	global $wpdb;
	$id = (int) hk_post( 'id', 0 );
	$h  = hk_hizmet( $id );
	if ( ! $h ) {
		hk_geri( 'eksik', hk_url( 'hizmetler' ) );
	}
	$yeni = hk_tarih_oku( hk_post( 'yeni_bitis' ) );
	if ( ! $yeni ) {
		hk_geri( 'tarih', hk_url( 'hizmetler' ) );
	}
	$guncelle = array(
		'bitis'   => $yeni,
		'kontrol' => 0,
	);
	$bugun    = hk_bugun();
	$turler   = hk_turler();
	$etiket   = $h->ad . ' – ' . ( isset( $turler[ $h->tur ] ) ? $turler[ $h->tur ] : 'Hizmet' ) . ' yenileme';

	if ( hk_post( 'gider_ac' ) ) {
		$tutar = hk_para_oku( hk_post( 'gider_tutar' ) );
		$tarih = hk_tarih_oku( hk_post( 'gider_tarih' ) );
		$tarih = $tarih ? $tarih : $bugun;
		if ( null !== $tutar ) {
			$wpdb->insert(
				hk_t( 'hareketler' ),
				array(
					'musteri_id'   => $h->musteri_id,
					'hizmet_id'    => $h->id,
					'tur'          => 'gider',
					'tutar'        => $tutar,
					'donem'        => (int) substr( $tarih, 0, 4 ),
					'tarih'        => $tarih,
					'durum'        => 'odendi',
					'odeme_tarihi' => $tarih,
					'aciklama'     => $etiket,
				)
			);
			$guncelle['maliyet'] = $tutar;
		}
	}

	if ( hk_post( 'alacak_ac' ) ) {
		$tutar = hk_para_oku( hk_post( 'alacak_tutar' ) );
		$durum = sanitize_key( hk_post( 'alacak_durum', 'bekliyor' ) );
		$durum = in_array( $durum, array( 'bekliyor', 'istendi', 'odendi' ), true ) ? $durum : 'bekliyor';
		if ( null !== $tutar ) {
			$wpdb->insert(
				hk_t( 'hareketler' ),
				array(
					'musteri_id'   => $h->musteri_id,
					'hizmet_id'    => $h->id,
					'tur'          => 'gelir',
					'tutar'        => $tutar,
					'donem'        => (int) substr( $bugun, 0, 4 ),
					'tarih'        => $bugun,
					'durum'        => $durum,
					'odeme_tarihi' => 'odendi' === $durum ? $bugun : null,
					'aciklama'     => $etiket,
				)
			);
			$guncelle['fiyat'] = $tutar;
		}
	}

	$wpdb->update( hk_t( 'hizmetler' ), $guncelle, array( 'id' => $id ) );
	hk_geri( 'yenilendi', hk_url( 'hizmetler' ) );
}

/* ---------- Hareket ---------- */

function hk_do_hareket_kaydet() {
	hk_guard( 'hareket_kaydet' );
	global $wpdb;
	$id    = (int) hk_post( 'id', 0 );
	$tur   = 'gider' === hk_post( 'tur' ) ? 'gider' : 'gelir';
	$durum = sanitize_key( hk_post( 'durum', 'odendi' ) );
	$durum = ( 'gider' === $tur || ! array_key_exists( $durum, hk_durumlar() ) ) ? 'odendi' : $durum;
	$tarih = hk_tarih_oku( hk_post( 'tarih' ) );
	$odeme = hk_tarih_oku( hk_post( 'odeme_tarihi' ) );
	$tutar = hk_para_oku( hk_post( 'tutar' ) );
	$donem = (int) hk_post( 'donem' );
	if ( $donem < 2000 || $donem > 2100 ) {
		$donem = (int) substr( $tarih ? $tarih : hk_bugun(), 0, 4 );
	}
	if ( 'gider' === $tur && ! $odeme ) {
		$odeme = $tarih;
	}
	if ( 'odendi' !== $durum ) {
		$odeme = null;
	} elseif ( ! $odeme && ! $id ) {
		$odeme = $tarih ? $tarih : hk_bugun();
	}
	$hizmet_id = (int) hk_post( 'hizmet_id', 0 );
	$d         = array(
		'musteri_id'   => (int) hk_post( 'musteri_id' ),
		'hizmet_id'    => $hizmet_id ? $hizmet_id : null,
		'tur'          => $tur,
		'tutar'        => $tutar,
		'donem'        => $donem,
		'tarih'        => $tarih,
		'durum'        => $durum,
		'odeme_tarihi' => $odeme,
		'hatirlatma'   => max( 0, (int) hk_post( 'hatirlatma', 0 ) ),
		'aciklama'     => sanitize_textarea_field( hk_post( 'aciklama' ) ),
		'kontrol'      => hk_post( 'kontrol' ) ? 1 : 0,
	);
	if ( null === $tutar || ! hk_musteri( $d['musteri_id'] ) ) {
		hk_geri( 'eksik', hk_url( 'hareketler' ) );
	}
	if ( $id ) {
		$wpdb->update( hk_t( 'hareketler' ), $d, array( 'id' => $id ) );
	} else {
		$wpdb->insert( hk_t( 'hareketler' ), $d );
	}
	hk_geri( 'kaydedildi', hk_url( 'hareketler' ) );
}

function hk_do_hareket_sil() {
	hk_guard( 'hareket_sil' );
	global $wpdb;
	$wpdb->delete( hk_t( 'hareketler' ), array( 'id' => (int) ( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) ) );
	hk_geri( 'silindi', hk_url( 'hareketler' ) );
}

/** Hızlı işlemler: ödendi / istendi / hatırlatıldı / onayla. */
function hk_do_hareket_hizli() {
	hk_guard( 'hareket_hizli' );
	global $wpdb;
	$id = (int) ( isset( $_GET['id'] ) ? $_GET['id'] : 0 );
	$ne = isset( $_GET['ne'] ) ? sanitize_key( $_GET['ne'] ) : '';
	$x  = hk_hareket( $id );
	if ( $x ) {
		$bugun = hk_bugun();
		$d     = array();
		if ( 'odendi' === $ne ) {
			$d = array(
				'durum'        => 'odendi',
				'odeme_tarihi' => $bugun,
			);
		} elseif ( 'istendi' === $ne ) {
			$d = array(
				'durum' => 'istendi',
				'tarih' => $x->tarih ? $x->tarih : $bugun,
			);
		} elseif ( 'hatirlat' === $ne ) {
			$d = array(
				'hatirlatma'     => (int) $x->hatirlatma + 1,
				'son_hatirlatma' => $bugun,
			);
			if ( 'bekliyor' === $x->durum ) {
				$d['durum'] = 'istendi';
				$d['tarih'] = $x->tarih ? $x->tarih : $bugun;
			}
		} elseif ( 'onayla' === $ne ) {
			$d = array( 'kontrol' => 0 );
		}
		if ( $d ) {
			$wpdb->update( hk_t( 'hareketler' ), $d, array( 'id' => $id ) );
		}
	}
	hk_geri( 'guncellendi', hk_url( 'hareketler' ) );
}

/* ---------- Ayarlar / araçlar ---------- */

function hk_do_ayar_kaydet() {
	hk_guard( 'ayar_kaydet' );
	$gunler = array_unique( array_filter( array_map( 'absint', explode( ',', hk_post( 'gunler' ) ) ) ) );
	rsort( $gunler );
	$eposta = sanitize_email( hk_post( 'eposta' ) );
	$sablon = sanitize_textarea_field( hk_post( 'sablon' ) );
	update_option(
		'hk_ayarlar',
		array(
			'eposta'    => $eposta ? $eposta : get_option( 'admin_email' ),
			'gunler'    => $gunler ? implode( ',', $gunler ) : '30,7,1',
			'panel_gun' => max( 7, min( 365, absint( hk_post( 'panel_gun', 60 ) ) ) ),
			'zam'       => max( 0, min( 500, (float) str_replace( ',', '.', hk_post( 'zam', 0 ) ) ) ),
			'haftalik'  => hk_post( 'haftalik' ) ? 1 : 0,
			'sablon'    => '' !== trim( $sablon ) ? $sablon : hk_varsayilan_sablon(),
		)
	);
	hk_geri( 'kaydedildi', hk_url( 'ayarlar' ) );
}

function hk_do_ice_aktar() {
	hk_guard( 'ice_aktar' );
	$s = hk_ice_aktar();
	wp_safe_redirect( hk_url( is_wp_error( $s ) ? 'ayarlar' : 'kontrol', array( 'hk_msg' => is_wp_error( $s ) ? 'aktarim_hata' : 'aktarildi' ) ) );
	exit;
}

function hk_do_test_eposta() {
	hk_guard( 'test_eposta' );
	hk_geri( hk_ozet_eposta( true ) ? 'eposta_ok' : 'eposta_hata', hk_url( 'ayarlar' ) );
}

/** Hareketleri CSV olarak indirir (Excel'in Türkçe ayarlarıyla uyumlu: noktalı virgül, virgüllü ondalık). */
function hk_do_csv() {
	hk_guard( 'csv' );
	$f = array();
	foreach ( array( 'musteri_id', 'tur', 'durum', 'donem' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) {
			$f[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
		}
	}
	$durumlar = hk_durumlar();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="hesap-kitap-' . hk_bugun() . '.csv"' );
	$o = fopen( 'php://output', 'w' );
	fwrite( $o, "\xEF\xBB\xBF" );
	fputcsv( $o, array( 'Dönem', 'Tarih', 'Müşteri', 'Hizmet', 'Tür', 'Tutar', 'Durum', 'Ödeme tarihi', 'Hatırlatma', 'Açıklama' ), ';', '"', '\\' );
	foreach ( hk_hareketler( $f ) as $x ) {
		fputcsv(
			$o,
			array(
				$x->donem,
				hk_tarih( $x->tarih, '' ),
				$x->musteri_ad,
				(string) $x->hizmet_ad,
				'gider' === $x->tur ? 'Gider' : 'Gelir',
				number_format( (float) $x->tutar, 2, ',', '' ),
				isset( $durumlar[ $x->durum ] ) ? $durumlar[ $x->durum ] : $x->durum,
				hk_tarih( $x->odeme_tarihi, '' ),
				$x->hatirlatma,
				(string) $x->aciklama,
			),
			';',
			'"',
			'\\'
		);
	}
	fclose( $o );
	exit;
}
