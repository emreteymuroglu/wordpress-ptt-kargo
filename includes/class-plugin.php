<?php
namespace WC_PTT_Kargo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	private $settings;
	private $orders;
	private $barcode;
	private $client;
	private $admin_page;
	private $label;
	private $wc_integration;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings       = new Settings();
		$this->barcode        = new Barcode( $this->settings );
		$this->client         = new PTT_Client( $this->settings );
		$this->orders         = new Orders( $this->settings );
		$this->label          = new Label( $this->settings );
		$this->admin_page     = new Admin_Page( $this->settings, $this->orders, $this->barcode, $this->client, $this->label );
		$this->wc_integration = new WC_Integration( $this->settings, $this->orders, $this->barcode, $this->client, $this->label );
	}

	public function boot() {
		load_plugin_textdomain( 'wc-ptt-kargo', false, dirname( plugin_basename( WC_PTT_KARGO_FILE ) ) . '/languages' );

		$this->settings->register();
		$this->admin_page->register();
		$this->label->register();
		$this->wc_integration->register();
	}

	public static function on_activation() {
		if ( get_option( Settings::OPTION_KEY, false ) === false ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}
		// Barkod cursor'unu pre-init et — ilk gönderim sırasında race condition oluşmasın.
		// add_option idempotent: cursor zaten varsa noop. Varsayılan '0', Barcode::next() ilk
		// çağrıda kullanıcının range_start'ına çekecek.
		add_option( Barcode::CURSOR_OPTION, '0', '', 'no' );
	}

	public function settings()       { return $this->settings; }
	public function barcode()        { return $this->barcode; }
	public function client()         { return $this->client; }
	public function orders()         { return $this->orders; }
	public function label()          { return $this->label; }
	public function wc_integration() { return $this->wc_integration; }
}
