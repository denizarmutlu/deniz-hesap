<?php
/**
 * Hosting paketi maliyetinin dağıtımı ve rapor ekranı.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bir yılın hosting paketi maliyetini, o sağlayıcıda hostingi olan müşterilere pay sayısına göre dağıtır.
 *
 * - Paket: "paket" işaretli hizmet (ör. bayi hosting paketi). Maliyeti, o hizmete bağlı gider kayıtlarının toplamıdır.
 * - İçinde bulunulan yılda henüz ödeme yoksa tahmin kullanılır: önceki yılın toplamı × (1 + beklenen artış).
 * - Pay: müşterilerin aynı sağlayıcıdaki aktif hosting kayıtlarının "pay" toplamı. Kendi hesabım ve
 *   ücretini müşterinin ödediği hostingler pay almaz.
 * - Pay sayıları bugünkü kayıtlardan alınır; geçmiş yıllar için ayrı pay geçmişi tutulmaz.
 *
 * Dönüş: saglayici[firma] = maliyet, tahmini, pay_toplam, pay_basi, sahip
 *        musteri[musteri_id] = o yıl payına düşen tutar
 *        sahip[musteri_id]   = paket sahibinin giderinden müşterilere aktarılan gerçek tutar
 *        tahmini             = henüz ödenmemiş, tahmin edilen toplam
 */
function hk_hosting_dagitim( $yil ) {
	global $wpdb;
	static $onbellek = array();
	$yil = (int) $yil;
	if ( isset( $onbellek[ $yil ] ) ) {
		return $onbellek[ $yil ];
	}
	$hz = hk_t( 'hizmetler' );
	$hr = hk_t( 'hareketler' );
	$ms = hk_t( 'musteriler' );

	$maliyet_sorgu = "SELECT h.firma, MIN(h.musteri_id) AS sahip, SUM(x.tutar) AS m FROM $hr x INNER JOIN $hz h ON h.id = x.hizmet_id
		WHERE h.paket = 1 AND x.tur = 'gider' AND x.donem = %d GROUP BY h.firma";

	$sag = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( $maliyet_sorgu, $yil ) ) as $r ) {
		$sag[ $r->firma ] = array(
			'maliyet' => (float) $r->m,
			'tahmini' => false,
			'sahip'   => (int) $r->sahip,
		);
	}

	if ( (int) current_time( 'Y' ) === $yil ) {
		$onceki = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( $maliyet_sorgu, $yil - 1 ) ) as $r ) {
			$onceki[ $r->firma ] = (float) $r->m;
		}
		$zam = (float) hk_ayar( 'hosting_zam' );
		foreach ( $wpdb->get_results( "SELECT firma, MIN(musteri_id) AS sahip FROM $hz WHERE paket = 1 AND aktif = 1 GROUP BY firma" ) as $r ) {
			if ( ! isset( $sag[ $r->firma ] ) && ! empty( $onceki[ $r->firma ] ) ) {
				$sag[ $r->firma ] = array(
					'maliyet' => round( $onceki[ $r->firma ] * ( 1 + $zam / 100 ), 2 ),
					'tahmini' => true,
					'sahip'   => (int) $r->sahip,
				);
			}
		}
	}

	$paylar = array();
	foreach ( $wpdb->get_results(
		"SELECT h.firma, h.musteri_id, SUM(h.pay) AS p FROM $hz h INNER JOIN $ms m ON m.id = h.musteri_id
		WHERE h.tur = 'hosting' AND h.paket = 0 AND h.musteri_oder = 0 AND h.aktif = 1 AND m.kendi = 0
		GROUP BY h.firma, h.musteri_id"
	) as $r ) {
		$paylar[ $r->firma ][ (int) $r->musteri_id ] = (int) $r->p;
	}

	$sonuc = array(
		'saglayici' => array(),
		'musteri'   => array(),
		'sahip'     => array(),
		'tahmini'   => 0.0,
	);
	foreach ( $sag as $firma => $s ) {
		$toplam_pay        = isset( $paylar[ $firma ] ) ? array_sum( $paylar[ $firma ] ) : 0;
		$s['pay_toplam']   = $toplam_pay;
		$s['pay_basi']     = $toplam_pay > 0 ? $s['maliyet'] / $toplam_pay : 0;
		$sonuc['saglayici'][ $firma ] = $s;
		if ( $s['tahmini'] ) {
			$sonuc['tahmini'] += $s['maliyet'];
		}
		if ( $toplam_pay <= 0 ) {
			continue;
		}
		foreach ( $paylar[ $firma ] as $mid => $p ) {
			$sonuc['musteri'][ $mid ] = ( isset( $sonuc['musteri'][ $mid ] ) ? $sonuc['musteri'][ $mid ] : 0 ) + $s['pay_basi'] * $p;
		}
		if ( ! $s['tahmini'] ) {
			$sonuc['sahip'][ $s['sahip'] ] = ( isset( $sonuc['sahip'][ $s['sahip'] ] ) ? $sonuc['sahip'][ $s['sahip'] ] : 0 ) + $s['maliyet'];
		}
	}
	$onbellek[ $yil ] = $sonuc;
	return $sonuc;
}

/** Bir sağlayıcının içinde bulunulan yıldaki pay başı maliyeti (tahmin dahil). Yoksa null. */
function hk_pay_basi( $firma ) {
	$d = hk_hosting_dagitim( (int) current_time( 'Y' ) );
	if ( isset( $d['saglayici'][ $firma ] ) && $d['saglayici'][ $firma ]['pay_basi'] > 0 ) {
		return $d['saglayici'][ $firma ];
	}
	return null;
}

/** Yıl ve müşteri bazında ham toplamlar. */
function hk_ozet_yillik() {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT donem, musteri_id,
			SUM(CASE WHEN tur = 'gelir' AND durum = 'odendi' THEN tutar ELSE 0 END) AS tahsil,
			SUM(CASE WHEN tur = 'gelir' AND durum <> 'odendi' THEN tutar ELSE 0 END) AS bekleyen,
			SUM(CASE WHEN tur = 'gider' THEN tutar ELSE 0 END) AS gider
		FROM " . hk_t( 'hareketler' ) . ' GROUP BY donem, musteri_id ORDER BY donem ASC'
	);
}

/**
 * Rapor verisini hesaplar (ekrandan bağımsız; testlerde de kullanılır).
 * Dönüş: yil[yıl] = tahsil, bekleyen, gider, tahmini, net, net_tahmini (seçilen birimde; çevrilemiyorsa null)
 *        musteri[id] = tahsil, gider, pay, bekleyen, net
 *        eksik = kur / TÜFE değeri bulunmadığı için çevrilemeyen yıllar
 */
function hk_rapor_verisi( $bas, $bit, $birim = 'tl' ) {
	$yil     = array();
	$musteri = array();
	$eksik   = array();
	for ( $y = $bas; $y <= $bit; $y++ ) {
		$yil[ $y ] = array(
			'tahsil'   => 0.0,
			'bekleyen' => 0.0,
			'gider'    => 0.0,
			'tahmini'  => 0.0,
		);
	}
	$bos = array(
		'tahsil'   => 0.0,
		'gider'    => 0.0,
		'pay'      => 0.0,
		'bekleyen' => 0.0,
	);

	foreach ( hk_ozet_yillik() as $r ) {
		$y = (int) $r->donem;
		if ( $y < $bas || $y > $bit ) {
			continue;
		}
		$mid = (int) $r->musteri_id;
		if ( ! isset( $musteri[ $mid ] ) ) {
			$musteri[ $mid ] = $bos;
		}
		foreach ( array( 'tahsil', 'bekleyen', 'gider' ) as $k ) {
			$v = hk_cevir( (float) $r->$k, $y, $birim );
			if ( null === $v ) {
				$eksik[ $y ] = true;
				continue;
			}
			$yil[ $y ][ $k ]       += $v;
			$musteri[ $mid ][ $k ] += $v;
		}
	}

	for ( $y = $bas; $y <= $bit; $y++ ) {
		$d = hk_hosting_dagitim( $y );
		foreach ( $d['musteri'] as $mid => $tutar ) {
			$v = hk_cevir( $tutar, $y, $birim );
			if ( null === $v ) {
				continue;
			}
			if ( ! isset( $musteri[ $mid ] ) ) {
				$musteri[ $mid ] = $bos;
			}
			$musteri[ $mid ]['pay'] += $v;
		}
		// Gerçek paket ödemesi sahibinin giderinde zaten kayıtlı; müşterilere aktarılan kısmı oradan düş.
		foreach ( $d['sahip'] as $mid => $tutar ) {
			$v = hk_cevir( $tutar, $y, $birim );
			if ( null === $v ) {
				continue;
			}
			if ( ! isset( $musteri[ $mid ] ) ) {
				$musteri[ $mid ] = $bos;
			}
			$musteri[ $mid ]['pay'] -= $v;
		}
		if ( $d['tahmini'] > 0 ) {
			$v = hk_cevir( $d['tahmini'], $y, $birim );
			if ( null !== $v ) {
				$yil[ $y ]['tahmini'] = $v;
			}
		}
	}

	foreach ( $yil as $y => $v ) {
		$yil[ $y ]['net']         = $v['tahsil'] - $v['gider'];
		$yil[ $y ]['net_tahmini'] = $v['tahsil'] - $v['gider'] - $v['tahmini'];
	}
	foreach ( $musteri as $mid => $v ) {
		$musteri[ $mid ]['net'] = $v['tahsil'] - $v['gider'] - $v['pay'];
	}
	return array(
		'yil'     => $yil,
		'musteri' => $musteri,
		'eksik'   => array_keys( $eksik ),
	);
}

/* ---------- Rapor ekranı ---------- */

function hk_sayfa_raporlar() {
	global $wpdb;
	$simdi  = (int) current_time( 'Y' );
	$ilk    = (int) $wpdb->get_var( 'SELECT MIN(donem) FROM ' . hk_t( 'hareketler' ) );
	$taban  = min( 2018, $ilk ? $ilk : 2018 );
	$bas    = max( $taban, min( $simdi, (int) hk_get( 'bas', $taban ) ) );
	$bit    = max( $bas, min( $simdi, (int) hk_get( 'bit', $simdi ) ) );
	$birim  = hk_get( 'birim', 'tl' );
	$birim  = in_array( $birim, array( 'tl', 'usd', 'reel' ), true ) ? $birim : 'tl';
	$veri   = hk_rapor_verisi( $bas, $bit, $birim );
	$birimler = array(
		'tl'   => 'TL (olduğu gibi)',
		'usd'  => 'Dolar karşılığı',
		'reel' => $simdi . ' fiyatlarıyla TL',
	);

	hk_baslik( 'Raporlar' );

	echo '<form method="get" class="hk-filtre"><input type="hidden" name="page" value="hk-raporlar">';
	echo '<label>Başlangıç <select name="bas">';
	for ( $y = $taban; $y <= $simdi; $y++ ) {
		printf( '<option value="%d"%s>%d</option>', $y, selected( $bas, $y, false ), $y );
	}
	echo '</select></label> <label>Bitiş <select name="bit">';
	for ( $y = $taban; $y <= $simdi; $y++ ) {
		printf( '<option value="%d"%s>%d</option>', $y, selected( $bit, $y, false ), $y );
	}
	echo '</select></label> <label>Birim <select name="birim">';
	foreach ( $birimler as $k => $e ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $birim, $k, false ), esc_html( $e ) );
	}
	echo '</select></label> <button class="button">Göster</button></form>';

	if ( 'usd' === $birim ) {
		echo '<p class="hk-alt">Her yılın tutarı, o yılın ortalama dolar kuruyla çevrilir. Kurları Ayarlar sayfasından değiştirebilirsiniz.</p>';
	} elseif ( 'reel' === $birim ) {
		echo '<p class="hk-alt">Geçmiş yılların tutarları, aradaki yılların TÜFE oranlarıyla ' . (int) $simdi . ' fiyatlarına taşınır. ' . (int) $simdi . ' oranı yıl bitmediği için geçicidir; Ayarlar sayfasından güncelleyebilirsiniz.</p>';
	}
	if ( $veri['eksik'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( implode( ', ', $veri['eksik'] ) ) . ' için kur / TÜFE değeri girilmediğinden bu yıllar çevrilemedi ve toplamlara katılmadı. Ayarlar → Kur ve enflasyon bölümünden ekleyin.</p></div>';
	}

	/* 1. Yıllara göre özet */
	$enb = 1;
	foreach ( $veri['yil'] as $v ) {
		$enb = max( $enb, $v['tahsil'], $v['gider'] + $v['tahmini'] );
	}
	$top = array(
		'tahsil'   => 0,
		'gider'    => 0,
		'tahmini'  => 0,
		'bekleyen' => 0,
	);

	echo '<h2>Yıllara göre gelir, gider ve kâr</h2>';
	echo '<div class="hk-grafik">';
	foreach ( $veri['yil'] as $y => $v ) {
		printf(
			'<div class="hk-g-yil"><div class="hk-g-cubuklar"><div class="hk-g-c hk-g-gelir" style="height:%.1f%%" title="Gelir: %s"></div><div class="hk-g-c hk-g-gider" style="height:%.1f%%" title="Gider: %s"><div class="hk-g-tahmini" style="height:%.1f%%"></div></div></div><div class="hk-g-etiket">%d</div></div>',
			100 * $v['tahsil'] / $enb,
			esc_attr( hk_para_birim( $v['tahsil'], $birim ) ),
			100 * ( $v['gider'] + $v['tahmini'] ) / $enb,
			esc_attr( hk_para_birim( $v['gider'] + $v['tahmini'], $birim ) ),
			( $v['gider'] + $v['tahmini'] ) > 0 ? 100 * $v['tahmini'] / ( $v['gider'] + $v['tahmini'] ) : 0,
			(int) $y
		);
	}
	echo '</div><p class="hk-g-aciklama"><span class="hk-g-kutu hk-g-gelir"></span> Tahsil edilen gelir &nbsp; <span class="hk-g-kutu hk-g-gider"></span> Gider &nbsp; <span class="hk-g-kutu hk-g-tahmini"></span> Tahmini gider</p>';

	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Yıl</th><th class="hk-sag">Tahsil edilen</th><th class="hk-sag">Gider</th><th class="hk-sag">Net kâr</th><th class="hk-sag">Marj</th><th class="hk-sag">Önceki yıla göre</th><th class="hk-sag">Tahmini gider</th><th class="hk-sag">Tahmin dahil net</th><th class="hk-sag">Bekleyen alacak</th></tr></thead><tbody>';
	$onceki = null;
	foreach ( $veri['yil'] as $y => $v ) {
		foreach ( $top as $k => $x ) {
			$top[ $k ] += $v[ $k ];
		}
		$degisim = ( null !== $onceki && abs( $onceki ) > 0.005 ) ? 100 * ( $v['net'] - $onceki ) / abs( $onceki ) : null;
		printf(
			'<tr><td><strong>%d</strong></td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag %s"><strong>%s</strong></td><td class="hk-sag">%s</td><td class="hk-sag %s">%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td></tr>',
			(int) $y,
			esc_html( hk_para_birim( $v['tahsil'], $birim ) ),
			esc_html( hk_para_birim( $v['gider'], $birim ) ),
			$v['net'] < 0 ? 'hk-eksi' : '',
			esc_html( hk_para_birim( $v['net'], $birim ) ),
			$v['tahsil'] > 0 ? esc_html( '%' . number_format( 100 * $v['net'] / $v['tahsil'], 0, ',', '.' ) ) : '—',
			( null !== $degisim && $degisim < 0 ) ? 'hk-eksi' : '',
			null !== $degisim ? esc_html( ( $degisim >= 0 ? '+' : '−' ) . '%' . number_format( abs( $degisim ), 0, ',', '.' ) ) : '—',
			$v['tahmini'] > 0 ? '<span class="hk-rozet hk-sari">~ ' . esc_html( hk_para_birim( $v['tahmini'], $birim ) ) . '</span>' : '—',
			$v['tahmini'] > 0 ? esc_html( hk_para_birim( $v['net_tahmini'], $birim ) ) : '—',
			$v['bekleyen'] > 0 ? esc_html( hk_para_birim( $v['bekleyen'], $birim ) ) : '—'
		);
		$onceki = $v['net'];
	}
	printf(
		'</tbody><tfoot><tr><th>Toplam</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th></th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th></tr></tfoot></table>',
		esc_html( hk_para_birim( $top['tahsil'], $birim ) ),
		esc_html( hk_para_birim( $top['gider'], $birim ) ),
		esc_html( hk_para_birim( $top['tahsil'] - $top['gider'], $birim ) ),
		$top['tahsil'] > 0 ? esc_html( '%' . number_format( 100 * ( $top['tahsil'] - $top['gider'] ) / $top['tahsil'], 0, ',', '.' ) ) : '—',
		$top['tahmini'] > 0 ? esc_html( '~ ' . hk_para_birim( $top['tahmini'], $birim ) ) : '—',
		$top['tahmini'] > 0 ? esc_html( hk_para_birim( $top['tahsil'] - $top['gider'] - $top['tahmini'], $birim ) ) : '—',
		esc_html( hk_para_birim( $top['bekleyen'], $birim ) )
	);
	echo '<p class="hk-alt">Gelir, tahsil edilen ödemelerdir ve kaydın "dönem" yılına yazılır. Gider, ödeme tarihinin yılına yazılır. Kendi alan adlarınız ve hosting paketi gidere dahildir.</p>';

	/* 2. Kategoriye göre gelir */
	$kat   = hk_kategoriler();
	$pivot = array();
	foreach ( $wpdb->get_results(
		$wpdb->prepare(
			'SELECT donem, kategori, SUM(tutar) AS t FROM ' . hk_t( 'hareketler' ) . " WHERE tur = 'gelir' AND durum = 'odendi' AND donem BETWEEN %d AND %d GROUP BY donem, kategori",
			$bas,
			$bit
		)
	) as $r ) {
		$v = hk_cevir( (float) $r->t, (int) $r->donem, $birim );
		if ( null !== $v ) {
			$k = isset( $kat[ $r->kategori ] ) ? $r->kategori : 'diger';
			$pivot[ (int) $r->donem ][ $k ] = ( isset( $pivot[ (int) $r->donem ][ $k ] ) ? $pivot[ (int) $r->donem ][ $k ] : 0 ) + $v;
		}
	}
	$gelir_kat = array_diff_key( $kat, array( 'hosting_paketi' => 1 ) );
	echo '<h2>Gelirin kaynağı <span class="hk-alt">kategoriye göre tahsil edilen</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Yıl</th>';
	foreach ( $gelir_kat as $e ) {
		echo '<th class="hk-sag">' . esc_html( $e ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	for ( $y = $bas; $y <= $bit; $y++ ) {
		echo '<tr><td><strong>' . (int) $y . '</strong></td>';
		foreach ( $gelir_kat as $k => $e ) {
			echo '<td class="hk-sag">' . ( ! empty( $pivot[ $y ][ $k ] ) ? esc_html( hk_para_birim( $pivot[ $y ][ $k ], $birim ) ) : '—' ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';

	/* 3. Müşteri kârlılığı */
	$adlar = array();
	foreach ( hk_musteriler() as $m ) {
		$adlar[ (int) $m->id ] = $m;
	}
	$sirali = $veri['musteri'];
	uasort(
		$sirali,
		function ( $a, $b ) {
			return $b['net'] <=> $a['net'];
		}
	);
	echo '<h2>Müşteri kârlılığı <span class="hk-alt">' . (int) $bas . '–' . (int) $bit . '</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Müşteri</th><th class="hk-sag">Tahsil edilen</th><th class="hk-sag">Doğrudan gider</th><th class="hk-sag">Hosting payı</th><th class="hk-sag">Net kâr</th><th class="hk-sag">Bekleyen alacak</th></tr></thead><tbody>';
	$t = array(
		'tahsil'   => 0,
		'gider'    => 0,
		'pay'      => 0,
		'net'      => 0,
		'bekleyen' => 0,
	);
	foreach ( $sirali as $mid => $v ) {
		if ( ! isset( $adlar[ $mid ] ) ) {
			continue;
		}
		foreach ( $t as $k => $x ) {
			$t[ $k ] += $v[ $k ];
		}
		$m = $adlar[ $mid ];
		printf(
			'<tr><td><a href="%s">%s</a>%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag %s"><strong>%s</strong></td><td class="hk-sag">%s</td></tr>',
			esc_url(
				hk_url(
					'musteriler',
					array(
						'islem' => 'goster',
						'id'    => $mid,
					)
				)
			),
			esc_html( $m->ad ),
			$m->kendi ? ' <span class="hk-rozet hk-gri">kendi</span>' : ( $m->firma ? ' <span class="hk-alt">' . esc_html( $m->firma ) . '</span>' : '' ),
			esc_html( $m->kendi ? '—' : hk_para_birim( $v['tahsil'], $birim ) ),
			esc_html( hk_para_birim( $v['gider'], $birim ) ),
			abs( $v['pay'] ) > 0.005 ? esc_html( ( $v['pay'] < 0 ? '−' : '' ) . hk_para_birim( abs( $v['pay'] ), $birim ) ) : '—',
			$v['net'] < 0 ? 'hk-eksi' : '',
			esc_html( ( $v['net'] < 0 ? '−' : '' ) . hk_para_birim( abs( $v['net'] ), $birim ) ),
			$v['bekleyen'] > 0 ? esc_html( hk_para_birim( $v['bekleyen'], $birim ) ) : '—'
		);
	}
	printf(
		'</tbody><tfoot><tr><th>Toplam</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th></tr></tfoot></table>',
		esc_html( hk_para_birim( $t['tahsil'], $birim ) ),
		esc_html( hk_para_birim( $t['gider'], $birim ) ),
		abs( $t['pay'] ) > 0.5 ? esc_html( '~ ' . hk_para_birim( $t['pay'], $birim ) ) : '—',
		esc_html( ( $t['net'] < 0 ? '−' : '' ) . hk_para_birim( abs( $t['net'] ), $birim ) ),
		esc_html( hk_para_birim( $t['bekleyen'], $birim ) )
	);
	echo '<p class="hk-alt">Hosting payı: hosting paketi maliyetinin müşteriye düşen kısmı. Ödenmiş paketlerde tutar sizin giderinizden düşülüp müşterilere aktarılır (toplam değişmez); henüz ödenmemiş yılın tahmini payı toplama eklenir.</p>';

	/* 4. Hosting paketi dağıtımı */
	echo '<h2>Hosting paketi dağıtımı</h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Yıl</th><th>Sağlayıcı</th><th class="hk-sag">Paket maliyeti</th><th class="hk-sag">Pay sayısı</th><th class="hk-sag">Pay başı maliyet</th></tr></thead><tbody>';
	$var = false;
	for ( $y = $bit; $y >= $bas; $y-- ) {
		$d = hk_hosting_dagitim( $y );
		foreach ( $d['saglayici'] as $firma => $s ) {
			$var = true;
			printf(
				'<tr><td><strong>%d</strong></td><td>%s%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td></tr>',
				(int) $y,
				esc_html( $firma ? $firma : '(sağlayıcı yazılmamış)' ),
				$s['tahmini'] ? ' <span class="hk-rozet hk-sari">tahmini</span>' : '',
				esc_html( hk_para( $s['maliyet'] ) ),
				$s['pay_toplam'] ? (int) $s['pay_toplam'] : '<span class="hk-eksi">0 – dağıtılamadı</span>',
				$s['pay_toplam'] ? esc_html( hk_para( round( $s['pay_basi'] ) ) ) : '—'
			);
		}
	}
	if ( ! $var ) {
		echo '<tr><td colspan="5">Bu aralıkta hosting paketi gideri yok. Paketi "Hizmetler" sayfasında kendi hesabınıza ekleyip "Hosting paketi" olarak işaretleyin.</td></tr>';
	}
	echo '</tbody></table><p class="hk-alt">Bu tablo her zaman TL gösterir. Pay sayısı bugünkü hosting kayıtlarından alınır; geçmiş yıllarda müşteri sayınız farklıysa o yılların pay başı maliyeti yaklaşıktır. Tahmin = önceki yılın paket gideri × (1 + %' . esc_html( (string) (float) hk_ayar( 'hosting_zam' ) ) . ').</p>';

	/* 5. Hizmet bazında maliyet ve fiyat */
	echo '<h2>Hizmet bazında maliyet ve fiyat <span class="hk-alt">aktif müşteri hizmetleri, bugünkü değerler (TL)</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Müşteri</th><th>Hizmet</th><th class="hk-sag">Yıllık maliyet</th><th class="hk-sag">Müşteri fiyatı</th><th class="hk-sag">Fark</th></tr></thead><tbody>';
	$tm = 0;
	$tf = 0;
	$hz = hk_hizmetler( array( 'aktif' => 1 ) );
	usort(
		$hz,
		function ( $a, $b ) {
			return array( $a->musteri_ad, $a->tur, $a->ad ) <=> array( $b->musteri_ad, $b->tur, $b->ad );
		}
	);
	foreach ( $hz as $h ) {
		if ( $h->kendi || $h->paket ) {
			continue;
		}
		$maliyet = null !== $h->maliyet ? (float) $h->maliyet : null;
		$not     = '';
		if ( 'hosting' === $h->tur && ! $h->musteri_oder && null === $maliyet ) {
			$pb = hk_pay_basi( $h->firma );
			if ( $pb ) {
				$maliyet = $pb['pay_basi'] * max( 1, (int) $h->pay );
				$not     = ' <span class="hk-alt">' . (int) $h->pay . ' pay' . ( $pb['tahmini'] ? ', tahmini' : '' ) . '</span>';
			}
		}
		if ( $h->musteri_oder ) {
			$not = ' <span class="hk-alt">müşteri öder</span>';
		}
		$fiyat = null !== $h->fiyat ? (float) $h->fiyat : null;
		$fark  = ( null !== $maliyet && null !== $fiyat ) ? $fiyat - $maliyet : null;
		$tm   += (float) $maliyet;
		$tf   += (float) $fiyat;
		printf(
			'<tr><td>%s</td><td>%s%s</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag %s">%s</td></tr>',
			esc_html( $h->musteri_ad ),
			esc_html( $h->ad ),
			$not,
			esc_html( hk_para( null !== $maliyet ? round( $maliyet ) : null ) ),
			esc_html( hk_para( $fiyat ) ),
			( null !== $fark && $fark < 0 ) ? 'hk-eksi' : '',
			null !== $fark ? esc_html( ( $fark < 0 ? '−' : '' ) . hk_para( abs( round( $fark ) ) ) ) : '—'
		);
	}
	printf( '</tbody><tfoot><tr><th colspan="2">Toplam (girilmiş olanlar)</th><th class="hk-sag">%s</th><th class="hk-sag">%s</th><th></th></tr></tfoot></table>', esc_html( hk_para( round( $tm ) ) ), esc_html( hk_para( round( $tf ) ) ) );
	echo '<p class="hk-alt">Fark yalnızca hem maliyeti hem fiyatı girilmiş hizmetlerde hesaplanır. Hostingde maliyet, pay başı maliyet × pay sayısıdır.</p>';

	/* 6. Tahsilat performansı */
	$perf = array();
	foreach ( $wpdb->get_results(
		$wpdb->prepare(
			'SELECT musteri_id, tarih, odeme_tarihi, durum, hatirlatma FROM ' . hk_t( 'hareketler' ) . " WHERE tur = 'gelir' AND donem BETWEEN %d AND %d",
			$bas,
			$bit
		)
	) as $r ) {
		$mid = (int) $r->musteri_id;
		if ( ! isset( $perf[ $mid ] ) ) {
			$perf[ $mid ] = array(
				'odenen' => 0,
				'gun'    => 0,
				'olcum'  => 0,
				'hat'    => 0,
				'acik'   => 0,
			);
		}
		$perf[ $mid ]['hat'] += (int) $r->hatirlatma;
		if ( 'odendi' === $r->durum ) {
			$perf[ $mid ]['odenen']++;
			if ( $r->tarih && $r->odeme_tarihi ) {
				$perf[ $mid ]['gun']   += max( 0, ( strtotime( $r->odeme_tarihi ) - strtotime( $r->tarih ) ) / DAY_IN_SECONDS );
				$perf[ $mid ]['olcum']++;
			}
		} else {
			$perf[ $mid ]['acik']++;
		}
	}
	echo '<h2>Tahsilat performansı <span class="hk-alt">' . (int) $bas . '–' . (int) $bit . '</span></h2>';
	echo '<table class="wp-list-table widefat striped hk-tablo hk-dar"><thead><tr><th>Müşteri</th><th class="hk-sag">Ödenen kayıt</th><th class="hk-sag">Ortalama ödeme süresi</th><th class="hk-sag">Hatırlatma</th><th class="hk-sag">Açık kayıt</th></tr></thead><tbody>';
	foreach ( $adlar as $mid => $m ) {
		if ( $m->kendi || ! isset( $perf[ $mid ] ) ) {
			continue;
		}
		$p = $perf[ $mid ];
		printf(
			'<tr><td>%s</td><td class="hk-sag">%d</td><td class="hk-sag">%s</td><td class="hk-sag">%s</td><td class="hk-sag %s">%s</td></tr>',
			esc_html( $m->ad ),
			(int) $p['odenen'],
			$p['olcum'] ? esc_html( number_format( $p['gun'] / $p['olcum'], 0 ) . ' gün' ) : '—',
			$p['hat'] ? (int) $p['hat'] : '—',
			$p['acik'] ? 'hk-eksi' : '',
			$p['acik'] ? (int) $p['acik'] : '—'
		);
	}
	echo '</tbody></table><p class="hk-alt">Ödeme süresi = istenme tarihi ile ödeme tarihi arasındaki gün; iki tarihi de girilmiş kayıtlardan hesaplanır.</p>';
	echo '</div>';
}
