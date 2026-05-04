<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var array $orders */

$label = \WC_PTT_Kargo\Plugin::instance()->label();
?>
<table class="wp-list-table widefat fixed striped wc-ptt-table">
	<thead>
		<tr>
			<th class="col-order"><?php esc_html_e( 'Sipariş', 'wc-ptt-kargo' ); ?></th>
			<th class="col-date"><?php esc_html_e( 'Tarih', 'wc-ptt-kargo' ); ?></th>
			<th class="col-customer"><?php esc_html_e( 'Müşteri', 'wc-ptt-kargo' ); ?></th>
			<th class="col-address"><?php esc_html_e( 'Adres', 'wc-ptt-kargo' ); ?></th>
			<th class="col-items"><?php esc_html_e( 'Ürünler', 'wc-ptt-kargo' ); ?></th>
			<th class="col-status"><?php esc_html_e( 'PTT Durumu', 'wc-ptt-kargo' ); ?></th>
			<th class="col-actions"><?php esc_html_e( 'İşlem', 'wc-ptt-kargo' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $orders ) ) : ?>
			<tr><td colspan="7" style="text-align:center; padding:2em;"><?php esc_html_e( 'Gösterilecek sipariş yok.', 'wc-ptt-kargo' ); ?></td></tr>
		<?php else : foreach ( $orders as $order ) :
			$status   = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_STATUS );
			$barkod   = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_BARKOD );
			$takip    = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_TAKIP_URL );
			$hata     = $status === \WC_PTT_Kargo\Orders::STATUS_ERROR ? (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_LOG ) : '';
			$alici    = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ?: trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$tel      = $order->get_billing_phone();
			$adres_1  = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
			$ilce     = $order->get_shipping_city() ?: $order->get_billing_city();
			$il_kod   = $order->get_shipping_state() ?: $order->get_billing_state();
			$il       = $il_kod;
			if ( function_exists( 'WC' ) && WC()->countries ) {
				$sts = WC()->countries->get_states( 'TR' );
				if ( isset( $sts[ $il_kod ] ) ) $il = $sts[ $il_kod ];
			}
			?>
			<tr data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<td class="col-order">
					<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">#<?php echo esc_html( $order->get_order_number() ); ?></a>
				</td>
				<td class="col-date">
					<?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '' ); ?>
				</td>
				<td class="col-customer">
					<strong><?php echo esc_html( $alici ); ?></strong><br>
					<small><?php echo esc_html( $tel ); ?></small><br>
					<small><?php echo esc_html( $order->get_billing_email() ); ?></small>
				</td>
				<td class="col-address">
					<?php echo esc_html( $adres_1 ); ?><br>
					<strong><?php echo esc_html( $ilce ); ?> / <?php echo esc_html( $il ); ?></strong>
				</td>
				<td class="col-items">
					<ul class="items-mini all-items">
						<?php foreach ( $order->get_items() as $item ) : ?>
							<li><?php echo esc_html( $item->get_name() ); ?> × <?php echo esc_html( $item->get_quantity() ); ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
				<td class="col-status">
					<?php if ( $status === \WC_PTT_Kargo\Orders::STATUS_SENT ) :
						$ptt_mesaj = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_LOG );
						$raw_resp  = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_RAW );
						$raw_req   = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_REQ );
						$parca_adet      = (int) $order->get_meta( \WC_PTT_Kargo\Orders::META_PARCA_ADET );
						$parca_barkodlar = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PARCA_BARKODLAR );
						$irsaliye        = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_IRSALIYE_NO );
						?>
						<span class="status-badge status-sent"><?php esc_html_e( 'Gönderildi', 'wc-ptt-kargo' ); ?></span><br>
						<small class="barkod-mini"><?php echo esc_html( $barkod ); ?></small>
						<?php if ( $parca_adet > 1 && $parca_barkodlar !== '' ) :
							$bks = json_decode( $parca_barkodlar, true );
							if ( is_array( $bks ) ) :
								?>
								<details class="raw-toggle" style="margin-top:4px;">
									<summary><?php echo esc_html( sprintf( __( '%d parça', 'wc-ptt-kargo' ), $parca_adet ) ); ?>
										<?php if ( $irsaliye !== '' ) echo ' · İrs. ' . esc_html( $irsaliye ); ?>
									</summary>
									<ul class="items-mini" style="margin-top:4px;">
									<?php foreach ( $bks as $bk ) : ?>
										<li><code><?php echo esc_html( $bk ); ?></code></li>
									<?php endforeach; ?>
									</ul>
								</details>
							<?php endif;
						endif; ?>
						<?php if ( $ptt_mesaj !== '' ) : ?>
							<br><small class="ptt-mesaj"><?php echo esc_html( $ptt_mesaj ); ?></small>
						<?php endif; ?>
						<?php if ( $takip ) : ?>
							<br><a href="<?php echo esc_url( $takip ); ?>" target="_blank"><?php esc_html_e( 'PTT takip linki', 'wc-ptt-kargo' ); ?></a>
						<?php endif; ?>
						<?php if ( $raw_resp !== '' || $raw_req !== '' ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Ham verileri göster', 'wc-ptt-kargo' ); ?></summary>
								<?php if ( $raw_req !== '' ) : ?>
									<strong><?php esc_html_e( 'İstek (gönderilen SOAP):', 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_req ); ?></pre>
								<?php endif; ?>
								<?php if ( $raw_resp !== '' ) : ?>
									<strong><?php esc_html_e( "Cevap (PTT'den gelen):", 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_resp ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
					<?php elseif ( $status === \WC_PTT_Kargo\Orders::STATUS_CANCELED ) : ?>
						<span class="status-badge status-canceled"><?php esc_html_e( 'İptal', 'wc-ptt-kargo' ); ?></span>
						<?php $cancel_msg = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_LOG ); ?>
						<?php if ( $cancel_msg !== '' ) : ?>
							<small class="ptt-mesaj" style="color:#646970;"><?php echo esc_html( $cancel_msg ); ?></small>
						<?php endif; ?>
					<?php elseif ( $status === \WC_PTT_Kargo\Orders::STATUS_ERROR ) :
						$raw_resp = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_RAW );
						$raw_req  = (string) $order->get_meta( \WC_PTT_Kargo\Orders::META_PTT_REQ );
						?>
						<span class="status-badge status-error"><?php esc_html_e( 'Hata', 'wc-ptt-kargo' ); ?></span>
						<small class="error-msg"><?php echo esc_html( $hata ); ?></small>
						<?php if ( $raw_resp !== '' || $raw_req !== '' ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Ham verileri göster', 'wc-ptt-kargo' ); ?></summary>
								<?php if ( $raw_req !== '' ) : ?>
									<strong><?php esc_html_e( 'İstek (gönderilen SOAP):', 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_req ); ?></pre>
								<?php endif; ?>
								<?php if ( $raw_resp !== '' ) : ?>
									<strong><?php esc_html_e( "Cevap (PTT'den gelen):", 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_resp ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
					<?php else : ?>
						<span class="status-badge status-pending"><?php esc_html_e( 'Bekliyor', 'wc-ptt-kargo' ); ?></span>
					<?php endif; ?>
				</td>
				<td class="col-actions">
					<?php if ( $status !== \WC_PTT_Kargo\Orders::STATUS_SENT ) : ?>
						<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
							<?php echo $status === \WC_PTT_Kargo\Orders::STATUS_CANCELED
								? esc_html__( 'Yeniden Gönder', 'wc-ptt-kargo' )
								: esc_html__( 'Kargoya İlet', 'wc-ptt-kargo' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $barkod && $status === \WC_PTT_Kargo\Orders::STATUS_SENT ) : ?>
						<a class="button" href="<?php echo esc_url( $label->label_url( $order->get_id() ) ); ?>" target="_blank">
							<?php esc_html_e( 'Etiket Yazdır', 'wc-ptt-kargo' ); ?>
						</a>
						<button type="button" class="button js-ptt-takip" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
							<?php esc_html_e( 'Takip', 'wc-ptt-kargo' ); ?>
						</button>
						<button type="button" class="button button-link-delete js-ptt-cancel" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" title="<?php esc_attr_e( 'PTT gönderisini iptal et (sadece henüz kabul edilmemişse)', 'wc-ptt-kargo' ); ?>">
							<?php esc_html_e( 'İptal Et', 'wc-ptt-kargo' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; endif; ?>
	</tbody>
</table>
