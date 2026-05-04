<?php
/**
 * Plugin Name:       WC PTT Kargo
 * Plugin URI:        https://github.com/
 * Description:       WooCommerce siparişlerini PTT Kargo SOAP API'si ile işler, barkod üretir ve termal etiket basar.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Emre Teymuroglu
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-ptt-kargo
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_PTT_KARGO_VERSION', '2.0.1' );
define( 'WC_PTT_KARGO_FILE', __FILE__ );
define( 'WC_PTT_KARGO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PTT_KARGO_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_PTT_KARGO_SLUG', 'wc-ptt-kargo' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p>';
		echo esc_html( sprintf( 'WC PTT Kargo en az PHP 7.4 gerektirir. Sunucunuz: %s', PHP_VERSION ) );
		echo '</p></div>';
	} );
	return;
}

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			WC_PTT_KARGO_FILE,
			true
		);
	}
} );

function wc_ptt_kargo_load_classes() {
	$files = [
		'class-logs.php',
		'class-settings.php',
		'class-barcode.php',
		'class-ptt-client.php',
		'class-orders.php',
		'class-label.php',
		'class-admin-page.php',
		'class-wc-integration.php',
		'class-plugin.php',
	];
	foreach ( $files as $f ) {
		$path = WC_PTT_KARGO_DIR . 'includes/' . $f;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'WC PTT Kargo eklentisi için WooCommerce gereklidir.', 'wc-ptt-kargo' );
			echo '</p></div>';
		} );
		return;
	}

	wc_ptt_kargo_load_classes();

	if ( class_exists( '\WC_PTT_Kargo\Plugin' ) ) {
		\WC_PTT_Kargo\Plugin::instance()->boot();
	}
} );

register_activation_hook( __FILE__, static function () {
	wc_ptt_kargo_load_classes();
	if ( class_exists( '\WC_PTT_Kargo\Logs' ) ) {
		\WC_PTT_Kargo\Logs::install_table();
	}
	if ( class_exists( '\WC_PTT_Kargo\Plugin' ) ) {
		\WC_PTT_Kargo\Plugin::on_activation();
	}
} );


