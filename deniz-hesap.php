<?php
/**
 * Plugin Name:       Deniz Hesap – Domain & Hosting Takip
 * Description:       Müşterilerin alan adı, hosting ve SSL yenilemelerini, ödenen giderleri ve müşterilerden alınan ödemeleri takip eder. Yaklaşan yenilemeler ve bekleyen alacaklar için e-posta hatırlatması gönderir.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Deniz Armutlu
 * Text Domain:       deniz-hesap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HK_VER', '1.1.0' );
define( 'HK_AD', 'Deniz Hesap' );
define( 'HK_DIR', plugin_dir_path( __FILE__ ) );
define( 'HK_URL', plugin_dir_url( __FILE__ ) );
define( 'HK_CAP', 'manage_options' );

require_once HK_DIR . 'includes/db.php';
require_once HK_DIR . 'includes/raporlar.php';
require_once HK_DIR . 'includes/cron.php';

if ( is_admin() ) {
	require_once HK_DIR . 'includes/actions.php';
	require_once HK_DIR . 'includes/pages.php';
}

register_activation_hook( __FILE__, 'hk_activate' );
register_deactivation_hook( __FILE__, 'hk_deactivate' );

function hk_activate() {
	hk_surum_kontrol();
	if ( ! wp_next_scheduled( 'hk_gunluk' ) ) {
		// Her gün sabah 08:00 (site saat dilimi) civarında çalışır.
		$ilk = strtotime( 'tomorrow 08:00', current_time( 'timestamp' ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		wp_schedule_event( $ilk, 'daily', 'hk_gunluk' );
	}
}

function hk_deactivate() {
	wp_clear_scheduled_hook( 'hk_gunluk' );
}

// Sürüm değişince tablo yapısını ve veriyi güncelle.
add_action( 'plugins_loaded', 'hk_surum_kontrol' );
