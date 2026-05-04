<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @var \WC_Order              $order
 * @var string                 $status
 * @var string                 $barkod
 * @var string                 $takip
 * @var string                 $mesaj
 * @var \WC_PTT_Kargo\Label    $label
 * @var \WC_PTT_Kargo\Orders   $orders
 */
?>
<div class="wc-ptt-metabox" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
	<?php if ( $status === \WC_PTT_Kargo\Orders::STATUS_SENT ) : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-sent">✓ <?php esc_html_e( 'Gönderildi', 'wc-ptt-kargo' ); ?></span>
		</p>
		<p class="wc-ptt-mb-barkod">
			<strong><?php esc_html_e( 'Barkod:', 'wc-ptt-kargo' ); ?></strong><br>
			<code style="font-size:13px;"><?php echo esc_html( $barkod ); ?></code>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<?php if ( $takip !== '' ) : ?>
			<p><a href="<?php echo esc_url( $takip ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PTT takip linki', 'wc-ptt-kargo' ); ?> ↗</a></p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<a class="button button-primary" href="<?php echo esc_url( $label->label_url( $order->get_id() ) ); ?>" target="_blank">
				<span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Etiket Yazdır', 'wc-ptt-kargo' ); ?>
			</a>
			<button type="button" class="button js-ptt-takip" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Takip', 'wc-ptt-kargo' ); ?>
			</button>
		</p>
		<p class="wc-ptt-mb-cancel">
			<button type="button" class="button-link js-ptt-cancel" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" style="color:#a00;">
				<span class="dashicons dashicons-no-alt" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'PTT Gönderisini İptal Et', 'wc-ptt-kargo' ); ?>
			</button>
			<br><small style="color:#646970;">
				<?php esc_html_e( 'Sadece PTT henüz gönderiyi kabul etmediyse mümkün.', 'wc-ptt-kargo' ); ?>
			</small>
		</p>
	<?php elseif ( $status === \WC_PTT_Kargo\Orders::STATUS_CANCELED ) : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-canceled">⊘ <?php esc_html_e( 'İptal edildi', 'wc-ptt-kargo' ); ?></span>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<p><small style="color:#646970;">
			<?php esc_html_e( 'Bu sipariş PTT\'den iptal edildi. Yeniden gönderim yeni bir barkod tüketir.', 'wc-ptt-kargo' ); ?>
		</small></p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Yeniden Gönder', 'wc-ptt-kargo' ); ?>
			</button>
		</p>
	<?php elseif ( $status === \WC_PTT_Kargo\Orders::STATUS_ERROR ) :
		$pending = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PENDING_BARKOD );
		?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-error">! <?php esc_html_e( 'Hata', 'wc-ptt-kargo' ); ?></span>
		</p>
		<p><small style="color:#a00;"><?php echo esc_html( $mesaj ?: __( 'Bilinmeyen hata', 'wc-ptt-kargo' ) ); ?></small></p>
		<?php if ( $pending !== '' ) : ?>
			<p style="font-size:11px; color:#0c63e4;">
				🔁 <?php
				/* translators: %s: pending barkod */
				echo esc_html( sprintf( __( 'Tüketilmiş barkod: %s — yeniden denemede tekrar kullanılacak.', 'wc-ptt-kargo' ), $pending ) );
				?>
			</p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Tekrar Dene', 'wc-ptt-kargo' ); ?>
			</button>
		</p>
	<?php else : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-pending"><?php esc_html_e( 'Henüz gönderilmedi', 'wc-ptt-kargo' ); ?></span>
		</p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary button-large js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<span class="dashicons dashicons-airplane" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'PTT Kargoya Gönder', 'wc-ptt-kargo' ); ?>
			</button>
		</p>
	<?php endif; ?>
</div>
