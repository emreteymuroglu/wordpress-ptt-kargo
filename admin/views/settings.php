<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = \WC_PTT_Kargo\Plugin::instance()->settings();
$opts     = $settings->all();
$opt_key  = \WC_PTT_Kargo\Settings::OPTION_KEY;

$tabs = [
	'connection' => [ 'label' => __( 'PTT Bağlantı', 'wc-ptt-kargo' ),    'icon' => 'admin-network' ],
	'barcode'    => [ 'label' => __( 'Barkod', 'wc-ptt-kargo' ),         'icon' => 'tickets-alt' ],
	'sender'     => [ 'label' => __( 'Gönderici', 'wc-ptt-kargo' ),      'icon' => 'businessperson' ],
	'label'      => [ 'label' => __( 'Etiket', 'wc-ptt-kargo' ),         'icon' => 'media-document' ],
	'products'   => [ 'label' => __( 'Ürün & Filtreler', 'wc-ptt-kargo' ), 'icon' => 'filter' ],
	'defaults'   => [ 'label' => __( 'Gönderi Varsayılanları', 'wc-ptt-kargo' ), 'icon' => 'archive' ],
	'payment'    => [ 'label' => __( 'Ödeme', 'wc-ptt-kargo' ),          'icon' => 'money-alt' ],
];

$current_tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'connection';
$base_url    = admin_url( 'admin.php?page=' . \WC_PTT_Kargo\Admin_Page::MENU_SLUG . '-ayarlar' );

// Önizleme paneli yalnızca etikete etki eden tab'larda görünsün.
$preview_tabs = [ 'connection', 'barcode', 'sender', 'label', 'products', 'defaults', 'payment' ];
$show_preview = in_array( $current_tab, $preview_tabs, true );
?>
<div class="wrap wc-ptt-wrap wc-ptt-settings-wrap">
	<h1><?php esc_html_e( 'WC PTT Kargo — Ayarlar', 'wc-ptt-kargo' ); ?></h1>
	<?php settings_errors( \WC_PTT_Kargo\Settings::OPTION_KEY ); ?>

	<nav class="nav-tab-wrapper wc-ptt-tab-nav">
		<?php foreach ( $tabs as $slug => $info ) :
			$url = add_query_arg( 'tab', $slug, $base_url );
			$cls = 'nav-tab' . ( $slug === $current_tab ? ' nav-tab-active' : '' );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>">
				<span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"></span>
				<?php echo esc_html( $info['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wc-ptt-settings-grid <?php echo $show_preview ? 'has-preview' : ''; ?>">
		<div class="wc-ptt-settings-main">
			<form id="wc-ptt-settings-form" method="post" action="options.php">
				<?php settings_fields( 'wc_ptt_kargo_settings_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $opt_key ); ?>[__tab]" value="<?php echo esc_attr( $current_tab ); ?>">

				<?php
				$tab_file = WC_PTT_KARGO_DIR . 'admin/views/settings/' . $current_tab . '.php';
				if ( file_exists( $tab_file ) ) {
					include $tab_file;
				}
				?>

				<?php submit_button( __( 'Ayarları Kaydet', 'wc-ptt-kargo' ) ); ?>
			</form>
		</div>

		<?php if ( $show_preview ) : ?>
		<aside class="wc-ptt-settings-preview" aria-label="<?php esc_attr_e( 'Etiket Önizleme', 'wc-ptt-kargo' ); ?>">
			<div class="wc-ptt-preview-card">
				<h3>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Canlı Etiket Önizleme', 'wc-ptt-kargo' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Form alanları değiştikçe önizleme otomatik yenilenir.', 'wc-ptt-kargo' ); ?></p>

				<form id="wc-ptt-preview-form"
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					target="wc-ptt-preview-iframe"
					style="display:none;">
					<input type="hidden" name="action" value="wc_ptt_kargo_preview">
					<?php wp_nonce_field( 'wc_ptt_kargo_preview' ); ?>
				</form>

				<div class="wc-ptt-preview-frame-wrap">
					<iframe id="wc-ptt-preview-iframe"
						name="wc-ptt-preview-iframe"
						src="about:blank"
						title="<?php esc_attr_e( 'Etiket önizleme', 'wc-ptt-kargo' ); ?>"></iframe>
				</div>

				<p class="wc-ptt-preview-meta">
					<small><?php esc_html_e( '⚠ Örnek veriyle render — sipariş bilgileri yer tutucudur.', 'wc-ptt-kargo' ); ?></small>
				</p>
			</div>
		</aside>
		<?php endif; ?>
	</div>
</div>
