/**
 * WC PTT Kargo — Ayarlar sayfası enhancement'ları:
 *  - Canlı etiket önizleme iframe'i (form değiştikçe yeniden render)
 *  - WP Media Library logo seçici
 *  - "Bağlantıyı Test Et" butonu (kaydetmeden önce de çalışır)
 */
(function ($) {
	'use strict';

	// ---------------- Live Preview ----------------

	var $previewForm  = $('#wc-ptt-preview-form');
	var $mainForm     = $('#wc-ptt-settings-form');
	var $previewFrame = $('#wc-ptt-preview-iframe');
	var debounceTimer = null;

	function syncPreview() {
		if (!$previewForm.length || !$mainForm.length) return;

		// Daha önce eklenmiş preview[*] hidden input'ları temizle
		$previewForm.find('input[name^="preview["]').remove();

		// Tüm preview-key'li elementlerin değerini hidden olarak preview form'a ekle
		$mainForm.find('[data-preview-key]').each(function () {
			var $el = $(this);
			var key = $el.data('preview-key');
			var val;
			if ($el.attr('type') === 'checkbox') {
				val = $el.is(':checked') ? '1' : '0';
			} else {
				val = $el.val() || '';
			}
			$('<input type="hidden">').attr('name', 'preview[' + key + ']').val(val).appendTo($previewForm);
		});

		// Native submit() target attribute'una saygı duyar
		$previewForm[0].submit();
	}

	function debouncedSync() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(syncPreview, 350);
	}

	if ($previewForm.length) {
		// İlk yüklemede önizlemeyi doldur
		syncPreview();

		// Form değişikliklerini izle
		$mainForm.on('input change', '[data-preview-key]', debouncedSync);
	}

	// ---------------- WP Media Library logo seçici ----------------

	var mediaFrame = null;

	$(document).on('click', '#wc-ptt-logo-pick', function (e) {
		e.preventDefault();

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title: 'Logo seç',
			button: { text: 'Bu görseli kullan' },
			library: { type: 'image' },
			multiple: false
		});

		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			$('#label_logo_url').val(url).trigger('input');
			$('#wc-ptt-logo-preview')
				.html('<img src="' + url + '" alt="">')
				.show();
		});

		mediaFrame.open();
	});

	$(document).on('click', '#wc-ptt-logo-clear', function (e) {
		e.preventDefault();
		$('#label_logo_url').val('').trigger('input');
		$('#wc-ptt-logo-preview').empty().hide();
	});

	// ---------------- Test Connection ----------------

	$(document).on('click', '#wc-ptt-test-conn-btn', function (e) {
		e.preventDefault();
		var $btn  = $(this);
		var $out  = $('#wc-ptt-test-result');
		var nonce = (window.WcPttKargo && window.WcPttKargo.nonce) || '';

		$out.removeClass('is-success is-error').addClass('is-loading')
			.html('<span class="dashicons dashicons-update spin"></span> ' +
				((window.WcPttKargo && window.WcPttKargo.i18n && window.WcPttKargo.i18n.testing) || 'Test ediliyor...'))
			.show();
		$btn.prop('disabled', true);

		var data = {
			action: 'wc_ptt_kargo_test_conn',
			nonce: nonce,
			environment: $('#environment').val(),
			musteri_id: $('#musteri_id').val(),
			sifre: $('#sifre').val()
		};

		$.post((window.WcPttKargo && window.WcPttKargo.ajaxUrl) || ajaxurl, data)
			.done(function (res) {
				$out.removeClass('is-loading');
				if (res && res.success) {
					$out.addClass('is-success').text(res.data.message);
				} else {
					$out.addClass('is-error').text((res && res.data && res.data.message) || 'Hata');
				}
			})
			.fail(function (xhr) {
				$out.removeClass('is-loading').addClass('is-error')
					.text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Sunucu hatası');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

})(jQuery);
