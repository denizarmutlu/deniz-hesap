<?php
/**
 * Veritabanı yapısı, yardımcı fonksiyonlar ve sorgular.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hk_t( $ad ) {
	global $wpdb;
	return $wpdb->prefix . 'hk_' . $ad;
}

function hk_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . hk_t( 'musteriler' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ad varchar(190) NOT NULL,
		firma varchar(190) NOT NULL DEFAULT '',
		telefon varchar(40) NOT NULL DEFAULT '',
		eposta varchar(190) NOT NULL DEFAULT '',
		notlar text NULL,
		kendi tinyint(1) NOT NULL DEFAULT 0,
		yetkili varchar(190) NOT NULL DEFAULT '',
		telefon2 varchar(40) NOT NULL DEFAULT '',
		PRIMARY KEY  (id)
	) $cs;"
	);

	dbDelta(
		'CREATE TABLE ' . hk_t( 'hizmetler' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		musteri_id bigint(20) unsigned NOT NULL,
		tur varchar(20) NOT NULL DEFAULT 'domain',
		ad varchar(190) NOT NULL,
		firma varchar(100) NOT NULL DEFAULT '',
		bitis date NULL,
		maliyet decimal(12,2) NULL,
		fiyat decimal(12,2) NULL,
		aktif tinyint(1) NOT NULL DEFAULT 1,
		kontrol tinyint(1) NOT NULL DEFAULT 0,
		notlar text NULL,
		bagli_id bigint(20) unsigned NULL,
		pay smallint(4) NOT NULL DEFAULT 1,
		musteri_oder tinyint(1) NOT NULL DEFAULT 0,
		paket tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY musteri_id (musteri_id),
		KEY bitis (bitis)
	) $cs;"
	);

	dbDelta(
		'CREATE TABLE ' . hk_t( 'hareketler' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		musteri_id bigint(20) unsigned NOT NULL,
		hizmet_id bigint(20) unsigned NULL,
		tur varchar(10) NOT NULL DEFAULT 'gelir',
		tutar decimal(12,2) NOT NULL DEFAULT 0,
		donem smallint(4) NOT NULL,
		tarih date NULL,
		durum varchar(12) NOT NULL DEFAULT 'odendi',
		odeme_tarihi date NULL,
		hatirlatma smallint(4) NOT NULL DEFAULT 0,
		son_hatirlatma date NULL,
		aciklama text NULL,
		kontrol tinyint(1) NOT NULL DEFAULT 0,
		kategori varchar(20) NOT NULL DEFAULT 'yenileme',
		PRIMARY KEY  (id),
		KEY musteri_id (musteri_id),
		KEY donem (donem),
		KEY durum (durum)
	) $cs;"
	);

}

/** Kurulu sürüm eklenti sürümünden farklıysa tablo yapısını ve veriyi günceller. */
function hk_surum_kontrol() {
	$eski = get_option( 'hk_db_ver' );
	if ( HK_VER !== $eski ) {
		hk_install();
		hk_yukselt( $eski ? $eski : '0' );
		update_option( 'hk_db_ver', HK_VER );
	}
}

/** Sürüm yükseltmelerinde bir kez çalışan veri düzenlemeleri. Kayıt silmez, üzerine yazmaz. */
function hk_yukselt( $eski ) {
	global $wpdb;
	if ( version_compare( $eski, '1.1.0', '<' ) ) {
		// "ornek.com hosting" adlı hosting kayıtlarını aynı müşterinin "ornek.com" alan adına bağla.
		$t = hk_t( 'hizmetler' );
		foreach ( $wpdb->get_results( "SELECT id, musteri_id, ad, bitis FROM $t WHERE tur = 'hosting' AND bagli_id IS NULL" ) as $h ) {
			if ( ! preg_match( '/^(\S+\.\S+)\s+hosting$/iu', $h->ad, $m ) ) {
				continue;
			}
			$d = $wpdb->get_row( $wpdb->prepare( "SELECT id, bitis FROM $t WHERE tur = 'domain' AND musteri_id = %d AND ad = %s", $h->musteri_id, $m[1] ) );
			if ( $d ) {
				$g = array( 'bagli_id' => (int) $d->id );
				if ( ! $h->bitis && $d->bitis ) {
					$g['bitis'] = $d->bitis;
				}
				$wpdb->update( $t, $g, array( 'id' => $h->id ) );
			}
		}
		// Zamanlanmış görev eklenti klasörü değişse de sürsün.
		if ( ! wp_next_scheduled( 'hk_gunluk' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hk_gunluk' );
		}
	}
}

/* ---------- Sabitler ---------- */

function hk_turler() {
	return array(
		'domain'  => 'Alan adı',
		'hosting' => 'Hosting',
		'ssl'     => 'SSL',
		'diger'   => 'Diğer',
	);
}

function hk_kategoriler() {
	return array(
		'yenileme'       => 'Yenileme',
		'yeni_site'      => 'Yeni web sitesi',
		'site_yenileme'  => 'Site yenileme',
		'hosting_paketi' => 'Hosting paketi',
		'diger'          => 'Diğer',
	);
}

function hk_durumlar() {
	return array(
		'bekliyor' => 'İstenecek',
		'istendi'  => 'İstendi',
		'odendi'   => 'Ödendi',
	);
}

/* ---------- Ayarlar ---------- */

function hk_varsayilan_sablon() {
	return "Merhaba {musteri},\n\n{hizmetler} için yıllık yenileme ücreti {tutar}.\n\nÖdemeyi yaptığınızda haber verirseniz kaydı güncellerim. Teşekkürler.\n\nDeniz Armutlu";
}

function hk_ayar( $anahtar = null ) {
	$v = array(
		'eposta'    => get_option( 'admin_email' ),
		'gunler'    => '30,7,1',
		'panel_gun' => 60,
		'zam'       => 40,
		'hosting_zam' => 40,
		'haftalik'  => 1,
		'sablon'    => hk_varsayilan_sablon(),
		'yillar'    => array(),
	);
	$a = wp_parse_args( get_option( 'hk_ayarlar', array() ), $v );
	return null === $anahtar ? $a : ( isset( $a[ $anahtar ] ) ? $a[ $anahtar ] : null );
}

/**
 * Yıllık ortalama dolar kuru (TCMB) ve on iki aylık ortalamalara göre TÜFE değişimi (TÜİK, %).
 * Ayarlar sayfasından değiştirilebilir. 2026 değerleri yıl bitmediği için geçicidir.
 */
function hk_yillar_varsayilan() {
	return array(
		2018 => array( 'kur' => 4.8284, 'tufe' => 16.33 ),
		2019 => array( 'kur' => 5.6738, 'tufe' => 15.18 ),
		2020 => array( 'kur' => 7.0086, 'tufe' => 12.28 ),
		2021 => array( 'kur' => 8.8504, 'tufe' => 19.60 ),
		2022 => array( 'kur' => 16.5489, 'tufe' => 72.31 ),
		2023 => array( 'kur' => 23.7386, 'tufe' => 53.86 ),
		2024 => array( 'kur' => 32.8059, 'tufe' => 58.51 ),
		2025 => array( 'kur' => 39.4548, 'tufe' => 34.88 ),
		2026 => array( 'kur' => 45.9587, 'tufe' => 31.00 ),
	);
}

function hk_yillar() {
	$y = hk_yillar_varsayilan();
	foreach ( (array) hk_ayar( 'yillar' ) as $yil => $v ) {
		$yil = (int) $yil;
		if ( $yil < 2000 || ! is_array( $v ) ) {
			continue;
		}
		$y[ $yil ] = array(
			'kur'  => isset( $v['kur'] ) && $v['kur'] > 0 ? (float) $v['kur'] : ( isset( $y[ $yil ] ) ? $y[ $yil ]['kur'] : 0 ),
			'tufe' => isset( $v['tufe'] ) && '' !== $v['tufe'] ? (float) $v['tufe'] : ( isset( $y[ $yil ] ) ? $y[ $yil ]['tufe'] : 0 ),
		);
	}
	ksort( $y );
	return $y;
}

/**
 * Bir yılın TL tutarını istenen birime çevirir.
 * $birim: 'tl' (olduğu gibi), 'usd' (o yılın ortalama kuruyla dolar), 'reel' (bugünkü fiyatlarla TL).
 * Gerekli kur / TÜFE değeri yoksa null döner.
 */
function hk_cevir( $tutar, $yil, $birim ) {
	if ( 'tl' === $birim ) {
		return (float) $tutar;
	}
	static $y = null;
	if ( null === $y ) {
		$y = hk_yillar();
	}
	$yil = (int) $yil;
	if ( 'usd' === $birim ) {
		return ( isset( $y[ $yil ] ) && $y[ $yil ]['kur'] > 0 ) ? (float) $tutar / $y[ $yil ]['kur'] : null;
	}
	$hedef  = (int) current_time( 'Y' );
	$carpan = 1.0;
	for ( $i = $yil + 1; $i <= $hedef; $i++ ) {
		if ( ! isset( $y[ $i ] ) ) {
			return null;
		}
		$carpan *= 1 + $y[ $i ]['tufe'] / 100;
	}
	return (float) $tutar * $carpan;
}

function hk_para_birim( $n, $birim, $bos = '—' ) {
	if ( null === $n ) {
		return $bos;
	}
	if ( 'usd' === $birim ) {
		return '$' . number_format( (float) $n, 0, ',', '.' );
	}
	return hk_para( round( (float) $n ), $bos );
}

/* ---------- Biçimlendirme ---------- */

function hk_bugun() {
	return current_time( 'Y-m-d' );
}

function hk_para( $n, $bos = '—' ) {
	if ( null === $n || '' === $n ) {
		return $bos;
	}
	$n   = (float) $n;
	$ond = ( abs( $n - round( $n ) ) < 0.005 ) ? 0 : 2;
	return number_format( $n, $ond, ',', '.' ) . ' ₺';
}

function hk_tarih( $ymd, $bos = '—' ) {
	if ( empty( $ymd ) || '0000-00-00' === $ymd ) {
		return $bos;
	}
	$t = strtotime( $ymd );
	return $t ? gmdate( 'd.m.Y', $t ) : $bos;
}

/** "1.234,56", "1234.56", "2.000 TL", "730,8" gibi girişleri sayıya çevirir. Boşsa null. */
function hk_para_oku( $s ) {
	$s = trim( str_replace( array( 'TL', 'TRY', '₺', ' ', "\xc2\xa0" ), '', (string) $s ) );
	if ( '' === $s ) {
		return null;
	}
	if ( false !== strpos( $s, ',' ) ) {
		$s = str_replace( '.', '', $s );
		$s = str_replace( ',', '.', $s );
	} elseif ( preg_match( '/^-?\d{1,3}(\.\d{3})+$/', $s ) ) {
		$s = str_replace( '.', '', $s );
	}
	return is_numeric( $s ) ? round( (float) $s, 2 ) : null;
}

/** YYYY-AA-GG veya GG.AA.YYYY kabul eder. Geçersizse null. */
function hk_tarih_oku( $s ) {
	$s = trim( (string) $s );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return $s;
	}
	if ( preg_match( '#^(\d{1,2})[./](\d{1,2})[./](\d{4})$#', $s, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
	}
	return null;
}

function hk_gun_farki( $ymd ) {
	if ( empty( $ymd ) ) {
		return null;
	}
	return (int) round( ( strtotime( $ymd ) - strtotime( hk_bugun() ) ) / DAY_IN_SECONDS );
}

function hk_bir_yil_sonra( $ymd ) {
	$t = strtotime( $ymd ? $ymd : hk_bugun() );
	return gmdate( 'Y-m-d', strtotime( '+1 year', $t ) );
}

/* ---------- Sorgular ---------- */

function hk_musteriler() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . hk_t( 'musteriler' ) . ' ORDER BY kendi ASC, ad ASC' );
}

function hk_musteri( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hk_t( 'musteriler' ) . ' WHERE id = %d', $id ) );
}

function hk_hizmet( $id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT h.*, m.ad AS musteri_ad, m.kendi FROM ' . hk_t( 'hizmetler' ) . ' h LEFT JOIN ' . hk_t( 'musteriler' ) . ' m ON m.id = h.musteri_id WHERE h.id = %d',
			$id
		)
	);
}

function hk_hareket( $id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT x.*, m.ad AS musteri_ad, h.ad AS hizmet_ad FROM ' . hk_t( 'hareketler' ) . ' x
			LEFT JOIN ' . hk_t( 'musteriler' ) . ' m ON m.id = x.musteri_id
			LEFT JOIN ' . hk_t( 'hizmetler' ) . ' h ON h.id = x.hizmet_id WHERE x.id = %d',
			$id
		)
	);
}

/**
 * Hizmet listesi.
 * $f: musteri_id, tur, aktif (1/0/''), kontrol (1), en_gec (Y-m-d: bitişi bu tarihe kadar olanlar)
 */
function hk_hizmetler( $f = array() ) {
	global $wpdb;
	$w = array( '1=1' );
	$p = array();
	if ( ! empty( $f['musteri_id'] ) ) {
		$w[] = 'h.musteri_id = %d';
		$p[] = (int) $f['musteri_id'];
	}
	if ( ! empty( $f['tur'] ) ) {
		$w[] = 'h.tur = %s';
		$p[] = $f['tur'];
	}
	if ( isset( $f['aktif'] ) && '' !== $f['aktif'] ) {
		$w[] = 'h.aktif = %d';
		$p[] = (int) $f['aktif'];
	}
	if ( ! empty( $f['kontrol'] ) ) {
		$w[] = 'h.kontrol = 1';
	}
	if ( ! empty( $f['en_gec'] ) ) {
		$w[] = 'h.bitis IS NOT NULL AND h.bitis <= %s';
		$p[] = $f['en_gec'];
	}
	$sql = 'SELECT h.*, m.ad AS musteri_ad, m.kendi FROM ' . hk_t( 'hizmetler' ) . ' h
		LEFT JOIN ' . hk_t( 'musteriler' ) . ' m ON m.id = h.musteri_id
		WHERE ' . implode( ' AND ', $w ) . '
		ORDER BY h.aktif DESC, (h.bitis IS NULL) ASC, h.bitis ASC, h.ad ASC';
	return $p ? $wpdb->get_results( $wpdb->prepare( $sql, $p ) ) : $wpdb->get_results( $sql );
}

/**
 * Hareket listesi.
 * $f: musteri_id, tur, durum, donem, kontrol, acik (1: ödenmemiş gelirler)
 */
function hk_hareketler( $f = array() ) {
	global $wpdb;
	$w = array( '1=1' );
	$p = array();
	if ( ! empty( $f['musteri_id'] ) ) {
		$w[] = 'x.musteri_id = %d';
		$p[] = (int) $f['musteri_id'];
	}
	if ( ! empty( $f['tur'] ) ) {
		$w[] = 'x.tur = %s';
		$p[] = $f['tur'];
	}
	if ( ! empty( $f['durum'] ) ) {
		$w[] = 'x.durum = %s';
		$p[] = $f['durum'];
	}
	if ( ! empty( $f['donem'] ) ) {
		$w[] = 'x.donem = %d';
		$p[] = (int) $f['donem'];
	}
	if ( ! empty( $f['kontrol'] ) ) {
		$w[] = 'x.kontrol = 1';
	}
	if ( ! empty( $f['kategori'] ) ) {
		$w[] = 'x.kategori = %s';
		$p[] = $f['kategori'];
	}
	if ( ! empty( $f['acik'] ) ) {
		$w[] = "x.tur = 'gelir' AND x.durum <> 'odendi'";
	}
	$sql = 'SELECT x.*, m.ad AS musteri_ad, h.ad AS hizmet_ad FROM ' . hk_t( 'hareketler' ) . ' x
		LEFT JOIN ' . hk_t( 'musteriler' ) . ' m ON m.id = x.musteri_id
		LEFT JOIN ' . hk_t( 'hizmetler' ) . ' h ON h.id = x.hizmet_id
		WHERE ' . implode( ' AND ', $w ) . '
		ORDER BY x.donem DESC, (x.tarih IS NULL) ASC, x.tarih DESC, x.id DESC';
	return $p ? $wpdb->get_results( $wpdb->prepare( $sql, $p ) ) : $wpdb->get_results( $sql );
}

function hk_donemler() {
	global $wpdb;
	$d = array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT donem FROM ' . hk_t( 'hareketler' ) . ' ORDER BY donem DESC' ) );
	$y = (int) current_time( 'Y' );
	if ( ! in_array( $y, $d, true ) ) {
		array_unshift( $d, $y );
	}
	rsort( $d );
	return $d;
}

/**
 * Müşteri bazında özet. $donem null ise tüm yıllar.
 * Dönüş: musteri_id => [tahsil, bekleyen, gider]
 */
function hk_ozet( $donem = null ) {
	global $wpdb;
	$sql = "SELECT musteri_id,
			SUM(CASE WHEN tur = 'gelir' AND durum = 'odendi' THEN tutar ELSE 0 END) AS tahsil,
			SUM(CASE WHEN tur = 'gelir' AND durum <> 'odendi' THEN tutar ELSE 0 END) AS bekleyen,
			SUM(CASE WHEN tur = 'gider' THEN tutar ELSE 0 END) AS gider
		FROM " . hk_t( 'hareketler' );
	if ( $donem ) {
		$sql = $wpdb->prepare( $sql . ' WHERE donem = %d', $donem );
	}
	$out = array();
	foreach ( $wpdb->get_results( $sql . ' GROUP BY musteri_id' ) as $r ) {
		$out[ (int) $r->musteri_id ] = array(
			'tahsil'   => (float) $r->tahsil,
			'bekleyen' => (float) $r->bekleyen,
			'gider'    => (float) $r->gider,
		);
	}
	return $out;
}

function hk_kontrol_sayisi() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . hk_t( 'hizmetler' ) . ' WHERE kontrol = 1' )
		+ (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . hk_t( 'hareketler' ) . ' WHERE kontrol = 1' );
}

/**
 * Yaklaşan ve süresi geçmiş aktif hizmetler.
 * Tarihi geçmişte kalmış ama "kontrol" işaretli kayıtlar (tablodan eski tarihle aktarılanlar)
 * gerçek bir gecikme olmayabileceği için burada listelenmez; Kontrol sayfasında görünür.
 */
function hk_yaklasanlar( $gun ) {
	$bugun  = hk_bugun();
	$en_gec = gmdate( 'Y-m-d', strtotime( '+' . (int) $gun . ' days', strtotime( $bugun ) ) );
	$liste  = hk_hizmetler(
		array(
			'aktif'  => 1,
			'en_gec' => $en_gec,
		)
	);
	return array_values(
		array_filter(
			$liste,
			function ( $h ) use ( $bugun ) {
				return ! ( $h->kontrol && $h->bitis < $bugun );
			}
		)
	);
}

/** Tarihi geçmişte kalmış ve kontrol bekleyen aktif hizmet sayısı. */
function hk_eski_tarihli_sayisi() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hk_t( 'hizmetler' ) . ' WHERE aktif = 1 AND kontrol = 1 AND bitis IS NOT NULL AND bitis < %s', hk_bugun() ) );
}

/* ---------- Mesaj şablonu ---------- */

/** Müşteriye gönderilecek ödeme talebi metnini üretir. $hareket verilmezse müşterinin tüm açık alacakları kullanılır. */
function hk_mesaj_uret( $musteri, $hareket = null ) {
	$hizmet_adlari = array();
	$aciklama      = '';
	if ( $hareket ) {
		$tutar    = (float) $hareket->tutar;
		$aciklama = (string) $hareket->aciklama;
		if ( ! empty( $hareket->hizmet_ad ) ) {
			$hizmet_adlari[] = $hareket->hizmet_ad;
		}
	} else {
		$tutar = 0;
		foreach ( hk_hareketler(
			array(
				'musteri_id' => $musteri->id,
				'acik'       => 1,
			)
		) as $x ) {
			$tutar += (float) $x->tutar;
			if ( ! empty( $x->hizmet_ad ) ) {
				$hizmet_adlari[] = $x->hizmet_ad;
			}
		}
	}
	if ( ! $hizmet_adlari ) {
		// Yakında bitecek (90 gün) veya süresi geçmiş hizmetler; yoksa tüm aktif hizmetler.
		$aktif = hk_hizmetler(
			array(
				'musteri_id' => $musteri->id,
				'aktif'      => 1,
			)
		);
		foreach ( $aktif as $h ) {
			$g = hk_gun_farki( $h->bitis );
			if ( null !== $g && $g <= 90 ) {
				$hizmet_adlari[] = $h->ad;
			}
		}
		if ( ! $hizmet_adlari ) {
			$hizmet_adlari = wp_list_pluck( $aktif, 'ad' );
		}
	}
	$hizmet_adlari = array_values( array_unique( $hizmet_adlari ) );

	// Açık alacak yoksa tutarı, adı geçen hizmetlerin kayıtlı müşteri fiyatlarından öner.
	if ( ! $hareket && $tutar <= 0 ) {
		foreach ( hk_hizmetler(
			array(
				'musteri_id' => $musteri->id,
				'aktif'      => 1,
			)
		) as $h ) {
			if ( in_array( $h->ad, $hizmet_adlari, true ) ) {
				$tutar += (float) $h->fiyat;
			}
		}
	}

	return strtr(
		hk_ayar( 'sablon' ),
		array(
			'{musteri}'   => $musteri->ad,
			'{firma}'     => $musteri->firma,
			'{tutar}'     => $tutar > 0 ? hk_para( $tutar ) : '........ ₺',
			'{hizmetler}' => $hizmet_adlari ? implode( ', ', $hizmet_adlari ) : 'Alan adı / hosting',
			'{aciklama}'  => $aciklama,
			'{tarih}'     => hk_tarih( hk_bugun() ),
		)
	);
}
