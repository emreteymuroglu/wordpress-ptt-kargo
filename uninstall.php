<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'wc_ptt_kargo_settings' );
delete_option( 'wc_ptt_kargo_barcode_cursor' );

// Logs tablosu
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wc_ptt_kargo_logs' );
