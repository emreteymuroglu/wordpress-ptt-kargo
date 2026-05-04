<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$only_success = isset( $_GET['filter'] ) && $_GET['filter'] === 'success'
	? true
	: ( isset( $_GET['filter'] ) && $_GET['filter'] === 'error' ? false : null );
$operation    = isset( $_GET['op'] ) ? sanitize_key( $_GET['op'] ) : '';

$logs  = \WC_PTT_Kargo\Logs::get_recent( 200, $only_success, $operation );
$total = \WC_PTT_Kargo\Logs::count();
$nonce = wp_create_nonce( 'wc_ptt_kargo' );
?>
<div class="wrap wc-ptt-wrap wc-ptt-logs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'PTT Loglar', 'wc-ptt-kargo' ); ?></h1>
	<button type="button" class="page-title-action" id="wc-ptt-clear-logs" data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<?php esc_html_e( 'Tümünü Temizle', 'wc-ptt-kargo' ); ?>
	</button>
	<hr class="wp-header-end">

	<p class="description">
		<?php
		echo esc_html( sprintf(
			/* translators: %d: log sayısı */
			__( 'Son %d adet PTT entegrasyon kaydı (en yeni üstte). Otomatik 500 kayıtta sınırlandırılır.', 'wc-ptt-kargo' ),
			count( $logs )
		) );
		?>
		<?php if ( $total > count( $logs ) ) : ?>
			<em>(<?php echo esc_html( sprintf( __( 'Toplam %d kayıt', 'wc-ptt-kargo' ), $total ) ); ?>)</em>
		<?php endif; ?>
	</p>

	<ul class="subsubsub">
		<?php
		$base = admin_url( 'admin.php?page=' . \WC_PTT_Kargo\Admin_Page::MENU_SLUG . '-logs' );
		$filters = [
			''        => __( 'Tümü', 'wc-ptt-kargo' ),
			'success' => __( 'Başarılı', 'wc-ptt-kargo' ),
			'error'   => __( 'Hata', 'wc-ptt-kargo' ),
		];
		$current_filter = isset( $_GET['filter'] ) ? $_GET['filter'] : '';
		$last_key       = array_key_last( $filters );
		foreach ( $filters as $val => $lbl ) :
			$url = $val === '' ? $base : add_query_arg( 'filter', $val, $base );
			$cls = $current_filter === $val ? 'current' : '';
			?>
			<li><a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $lbl ); ?></a><?php echo $val !== $last_key ? ' |' : ''; ?></li>
		<?php endforeach; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped wc-ptt-logs-table">
		<thead>
			<tr>
				<th style="width:140px;"><?php esc_html_e( 'Tarih', 'wc-ptt-kargo' ); ?></th>
				<th style="width:120px;"><?php esc_html_e( 'Operasyon', 'wc-ptt-kargo' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Sipariş', 'wc-ptt-kargo' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Durum', 'wc-ptt-kargo' ); ?></th>
				<th><?php esc_html_e( 'Mesaj', 'wc-ptt-kargo' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="5" style="text-align:center; padding:2em;"><?php esc_html_e( 'Henüz log kaydı yok.', 'wc-ptt-kargo' ); ?></td></tr>
			<?php else : foreach ( $logs as $log ) :
				$success = (int) $log['success'] === 1;
				$order_link = '';
				if ( ! empty( $log['order_id'] ) ) {
					$order_link = get_edit_post_link( (int) $log['order_id'] );
					if ( ! $order_link && function_exists( 'wc_get_order' ) ) {
						$o = wc_get_order( (int) $log['order_id'] );
						if ( $o ) $order_link = $o->get_edit_order_url();
					}
				}
				?>
				<tr>
					<td><small><?php echo esc_html( $log['created_at'] ); ?></small></td>
					<td><code><?php echo esc_html( $log['operation'] ); ?></code></td>
					<td>
						<?php if ( ! empty( $log['order_id'] ) ) : ?>
							<?php if ( $order_link ) : ?>
								<a href="<?php echo esc_url( $order_link ); ?>">#<?php echo esc_html( $log['order_id'] ); ?></a>
							<?php else : ?>
								#<?php echo esc_html( $log['order_id'] ); ?>
							<?php endif; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $success ) : ?>
							<span class="status-badge status-sent">✓ OK</span>
						<?php else : ?>
							<span class="status-badge status-error">✗</span>
						<?php endif; ?>
					</td>
					<td>
						<div class="wc-ptt-log-msg"><?php echo esc_html( $log['message'] ?: '—' ); ?></div>
						<?php if ( ! empty( $log['request'] ) || ! empty( $log['response'] ) ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Ham veri', 'wc-ptt-kargo' ); ?></summary>
								<?php if ( ! empty( $log['request'] ) ) : ?>
									<strong><?php esc_html_e( 'İstek:', 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $log['request'] ); ?></pre>
								<?php endif; ?>
								<?php if ( ! empty( $log['response'] ) ) : ?>
									<strong><?php esc_html_e( 'Cevap:', 'wc-ptt-kargo' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $log['response'] ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>

<script>
(function(){
	var btn = document.getElementById('wc-ptt-clear-logs');
	if (!btn) return;
	btn.addEventListener('click', function(){
		if (!confirm('<?php echo esc_js( __( 'Tüm log kayıtları silinecek. Emin misin?', 'wc-ptt-kargo' ) ); ?>')) return;
		var fd = new FormData();
		fd.append('action', 'wc_ptt_kargo_clear_logs');
		fd.append('nonce', btn.dataset.nonce);
		fetch(ajaxurl, { method:'POST', body:fd, credentials:'same-origin' })
			.then(function(r){ return r.json(); })
			.then(function(){ location.reload(); });
	});
})();
</script>
