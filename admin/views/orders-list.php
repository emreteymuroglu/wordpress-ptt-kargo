<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array $orders */
/** @var string $show */
?>
<div class="wrap wc-ptt-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'PTT Kargo Siparişleri', 'wc-ptt-kargo' ); ?></h1>
	<hr class="wp-header-end">

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \WC_PTT_Kargo\Admin_Page::MENU_SLUG . '&show=pending' ) ); ?>" class="<?php echo $show === 'pending' ? 'current' : ''; ?>"><?php esc_html_e( 'Bekleyenler', 'wc-ptt-kargo' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \WC_PTT_Kargo\Admin_Page::MENU_SLUG . '&show=sent' ) ); ?>" class="<?php echo $show === 'sent' ? 'current' : ''; ?>"><?php esc_html_e( 'Gönderilenler', 'wc-ptt-kargo' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \WC_PTT_Kargo\Admin_Page::MENU_SLUG . '&show=all' ) ); ?>" class="<?php echo $show === 'all' ? 'current' : ''; ?>"><?php esc_html_e( 'Tümü', 'wc-ptt-kargo' ); ?></a></li>
	</ul>

	<p class="wc-ptt-toolbar">
		<button type="button" class="button button-secondary" id="wc-ptt-refresh" data-show="<?php echo esc_attr( $show ); ?>">
			<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Yenile', 'wc-ptt-kargo' ); ?>
		</button>
		<?php
		// Mevcut görünen siparişlerden barkodu olanlar için toplu etiket linki.
		$printable_ids = [];
		foreach ( $orders as $o ) {
			if ( (string) $o->get_meta( \WC_PTT_Kargo\Orders::META_BARKOD ) !== '' ) {
				$printable_ids[] = $o->get_id();
			}
		}
		if ( ! empty( $printable_ids ) ) :
			$bulk_url = \WC_PTT_Kargo\Plugin::instance()->label()->bulk_url( $printable_ids );
			?>
			<a class="button button-secondary" href="<?php echo esc_url( $bulk_url ); ?>" target="_blank">
				<span class="dashicons dashicons-printer"></span>
				<?php
				/* translators: %d: kaç adet etiket */
				echo esc_html( sprintf( __( 'Toplu Etiket Bas (%d)', 'wc-ptt-kargo' ), count( $printable_ids ) ) );
				?>
			</a>
		<?php endif; ?>
		<span class="wc-ptt-count"><?php echo esc_html( count( $orders ) ); ?> <?php esc_html_e( 'sipariş', 'wc-ptt-kargo' ); ?></span>
	</p>

	<div id="wc-ptt-orders-container">
		<?php include WC_PTT_KARGO_DIR . 'admin/views/orders-table.php'; ?>
	</div>
</div>

<!-- Kargo özeti popup -->
<div id="wc-ptt-modal" class="wc-ptt-modal" style="display:none;">
	<div class="wc-ptt-modal-overlay"></div>
	<div class="wc-ptt-modal-box">
		<h2 class="wc-ptt-modal-title"><?php esc_html_e( 'Kargo Özeti', 'wc-ptt-kargo' ); ?></h2>

		<div class="missing-warn" style="display:none;">
			⚠️ <?php esc_html_e( 'Bazı alanlar eksik — kırmızı kenarlıklı olanlar. Boş bırakabilir ya da doldurabilirsin; popup onayı gönderir.', 'wc-ptt-kargo' ); ?>
		</div>

		<div class="wc-ptt-pending-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Müşteri Bilgileri', 'wc-ptt-kargo' ); ?></h3>
		<div class="wc-ptt-customer"></div>

		<h3><?php esc_html_e( 'Kargo Detayları', 'wc-ptt-kargo' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(boş bırakırsan auto-compute değer kullanılır)', 'wc-ptt-kargo' ); ?></small>
		</h3>
		<div class="wc-ptt-shipping"></div>

		<div class="wc-ptt-payment-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Sigorta (Değerli Kargo)', 'wc-ptt-kargo' ); ?></h3>
		<div class="wc-ptt-insurance"></div>

		<h3><?php esc_html_e( 'Çoklu Paket', 'wc-ptt-kargo' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(birden fazla parça halinde gönderilecekse)', 'wc-ptt-kargo' ); ?></small>
		</h3>
		<div class="wc-ptt-multipackage"></div>

		<div class="wc-ptt-modal-actions">
			<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'İptal', 'wc-ptt-kargo' ); ?></button>
			<button type="button" class="button button-primary" data-action="confirm"><?php esc_html_e( 'Onayla ve Gönder', 'wc-ptt-kargo' ); ?></button>
		</div>
	</div>
</div>
