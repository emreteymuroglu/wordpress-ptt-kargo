(function ($) {
	'use strict';

	if (typeof WcPttKargo === 'undefined') return;

	var $modal = null;
	var currentOrder = null;

	function toast(message, type) {
		$('.wc-ptt-toast').remove();
		var $t = $('<div class="wc-ptt-toast"></div>').text(message);
		if (type === 'success') $t.addClass('is-success');
		if (type === 'error')   $t.addClass('is-error');
		$('body').append($t);
		setTimeout(function () { $t.fadeOut(400, function () { $(this).remove(); }); }, 4500);
	}

	function ajax(action, data) {
		return $.post(WcPttKargo.ajaxUrl, $.extend({ action: action, nonce: WcPttKargo.nonce }, data));
	}

	function refreshList() {
		var show = $('#wc-ptt-refresh').data('show') || 'pending';
		$('#wc-ptt-refresh').prop('disabled', true);
		ajax(WcPttKargo.actions.refresh, { show: show })
			.done(function (res) {
				if (res && res.success) {
					$('#wc-ptt-orders-container').html(res.data.html);
					$('.wc-ptt-count').text(res.data.count + ' ' + WcPttKargo.i18n.orderWord);
				}
			})
			.always(function () { $('#wc-ptt-refresh').prop('disabled', false); });
	}

	var FIELD_LABELS = {
		aliciAdi: 'Alıcı Ad Soyad',
		aAdres: 'Adres',
		aliciIlAdi: 'İl',
		aliciIlceAdi: 'İlçe',
		aliciSms: 'Telefon (10 hane, başında 0 yok)',
		aliciEmail: 'E-posta'
	};

	// Sipariş bazında override edilebilir kargo alanları. Boş bırakılırsa auto-compute değer kalır.
	var SHIPPING_FIELDS = [
		{ key: 'agirlik',   label: 'Ağırlık (g)',   type: 'number', step: '1',   min: '1' },
		{ key: 'desi',      label: 'Desi',          type: 'number', step: '1',   min: '1' },
		{ key: 'en',        label: 'En (cm)',       type: 'number', step: '1',   min: '0' },
		{ key: 'boy',       label: 'Boy (cm)',      type: 'number', step: '1',   min: '0' },
		{ key: 'yukseklik', label: 'Yükseklik (cm)', type: 'number', step: '1',   min: '0' }
	];

	function openSummaryModal(data) {
		currentOrder = data;
		$modal = $('#wc-ptt-modal');
		$modal.find('.wc-ptt-modal-title').text(WcPttKargo.i18n.summaryTitle + ' — #' + (data.order_no || ''));

		var $cust = $modal.find('.wc-ptt-customer').empty();
		var fields = data.fields || {};
		var missing = data.missing || {};

		$.each(FIELD_LABELS, function (key, label) {
			var val = fields[key] || '';
			var isMissing = !!missing[key];
			var $row = $('<div class="field-row"></div>');
			$row.append($('<label></label>').attr('for', 'field-' + key).text(label + (isMissing ? ' (eksik)' : '')));
			var $input = $('<input type="text" />')
				.attr({ id: 'field-' + key, 'data-key': key, value: val })
				.addClass('field-input' + (isMissing ? ' is-missing' : ''));
			$row.append($input);
			$cust.append($row);
		});

		// Kargo detayları: auto-compute değerleri placeholder olarak gösterilir.
		// Kullanıcı bir alanı doldurursa o alan override olur; boş kalan alanlar settings/auto-compute kullanır.
		var $ship = $modal.find('.wc-ptt-shipping').empty();
		SHIPPING_FIELDS.forEach(function (f) {
			var current = fields[f.key];
			var $row = $('<div class="field-row"></div>');
			$row.append($('<label></label>').attr('for', 'field-' + f.key).text(f.label));
			var $input = $('<input />')
				.attr({
					id: 'field-' + f.key,
					'data-key': f.key,
					'data-shipping': '1',
					type: f.type,
					step: f.step,
					min: f.min,
					placeholder: (current && Number(current) > 0) ? String(current) : '—'
				})
				.addClass('field-input shipping-input');
			$row.append($input);
			$ship.append($row);
		});

		// COD info kutusu: sipariş kapıda ödemeli ise PTT'ye otomatik UA + odeme_sart_ucreti gider.
		var $payInfo = $modal.find('.wc-ptt-payment-info').empty();
		if (data.is_cod) {
			$payInfo.show().html(
				'<div style="padding:8px 12px; background:#fff3cd; border-left:4px solid #856404; border-radius:3px; font-size:12px; margin:8px 0;">' +
				'<strong>' + WcPttKargo.i18n.codInfo + '</strong> ' +
				escapeHtml(data.payment_method || '') +
				' &middot; <code>odemesekli=UA</code> &middot; <code>odeme_sart_ucreti=' + escapeHtml(data.cod_amount || '0') + '</code>' +
				'</div>'
			);
		} else {
			$payInfo.hide();
		}

		// Sigorta toggle: settings'e göre default açık/kapalı; kullanıcı popup'tan değiştirebilir.
		var $ins = $modal.find('.wc-ptt-insurance').empty();
		var insDefault = !!data.insurance_default;
		var insAmount = data.insurance_amount || (fields.deger_ucreti || '0');
		var $insWrap = $('<div></div>');
		var $insLabel = $('<label style="display:block; margin-bottom:8px;"></label>');
		var $insChk = $('<input type="checkbox" data-key="__insurance" data-shipping="0" />').prop('checked', insDefault);
		$insLabel.append($insChk).append(' ').append($('<strong></strong>').text(WcPttKargo.i18n.insuranceLabel));
		$insWrap.append($insLabel);

		var $amountRow = $('<div class="field-row"></div>');
		$amountRow.append($('<label></label>').attr('for', 'field-deger_ucreti').text(WcPttKargo.i18n.insuranceAmount));
		var $amountInput = $('<input type="number" step="0.01" min="0" />')
			.attr({
				id: 'field-deger_ucreti',
				'data-key': 'deger_ucreti',
				'data-shipping': '1',
				placeholder: insAmount,
				value: insDefault ? insAmount : ''
			})
			.addClass('field-input shipping-input');
		$amountRow.append($amountInput);
		$insWrap.append($amountRow);

		// Toggle değişince amount input enable/disable.
		$insChk.on('change', function () {
			var on = $(this).is(':checked');
			$amountInput.prop('disabled', !on);
			if (on && !$amountInput.val()) $amountInput.val(insAmount);
			if (!on) $amountInput.val('');
		});
		$amountInput.prop('disabled', !insDefault);

		$ins.append($insWrap);

		// Retry bilgilendirme: önceki başarısız denemede tüketilmiş barkod varsa göster.
		var $pendingInfo = $modal.find('.wc-ptt-pending-info').empty();
		if (data.pending_barkod) {
			$pendingInfo.show().html(
				'<div style="padding:8px 12px; background:#cce5ff; border-left:4px solid #0c63e4; border-radius:3px; font-size:12px; margin:8px 0;">' +
				'🔁 <strong>Yeniden deneme:</strong> Önceki denemede tüketilmiş barkod <code>' +
				escapeHtml(data.pending_barkod) +
				'</code> tekrar kullanılacak — yeni barkod yakılmayacak.' +
				'</div>'
			);
		} else {
			$pendingInfo.hide();
		}

		// Çoklu paket: kullanıcı parça sayısı + irsaliye no girer. Default 1 (mevcut tek-paket akışı).
		var $mp = $modal.find('.wc-ptt-multipackage').empty();
		var $mpRow = $('<div class="field-row"></div>');
		$mpRow.append($('<label></label>').attr('for', 'field-parca_adet').text('Parça Adedi'));
		var $mpInput = $('<input type="number" min="1" max="99" value="1" />')
			.attr({ id: 'field-parca_adet', 'data-key': 'parca_adet', 'data-multipackage': '1' })
			.addClass('field-input multi-input');
		$mpRow.append($mpInput);
		$mp.append($mpRow);

		var $irsRow = $('<div class="field-row"></div>').css('margin-top', '8px');
		$irsRow.append($('<label></label>').attr('for', 'field-irsaliye_no').text('İrsaliye No (opsiyonel)'));
		var $irsInput = $('<input type="text" maxlength="30" />')
			.attr({ id: 'field-irsaliye_no', 'data-key': 'irsaliye_no', 'data-multipackage': '1' })
			.addClass('field-input multi-input');
		$irsRow.append($irsInput);
		$mp.append($irsRow);

		var $mpHint = $('<p style="font-size:11px; color:#646970; margin:4px 0 0;"></p>')
			.text('Adet > 1 ise PTT\'nin kabulEkleParcaliBarkod servisine geçilir. Her parça aynı barkod aralığından bir barkod tüketir.');
		$mp.append($mpHint);

		$modal.find('.missing-warn').toggle(Object.keys(missing).length > 0);
		$modal.show();
	}

	function closeModal() {
		if ($modal) $modal.hide();
		currentOrder = null;
	}

	function sendCurrent() {
		if (!currentOrder) return;
		var orderId = currentOrder.order_id;
		var $btn = $('tr[data-order-id="' + orderId + '"]').find('.js-ptt-send');
		$btn.addClass('is-loading').text(WcPttKargo.i18n.sending);

		var override = {};
		var multipack = { parca_adet: '1', irsaliye_no: '' };

		$modal.find('.field-input').each(function () {
			var $el = $(this);
			var key = $el.data('key');
			if ($el.prop('disabled')) return; // disable edilmiş input'u override'a koyma
			var val = ($el.val() || '').trim();
			// Multi-package alanları override'a değil, ayrı POST parametresine gider.
			if ($el.data('multipackage')) {
				multipack[key] = val;
				return;
			}
			override[key] = val;
		});

		// Sigorta toggle (checkbox); on/off bayrağını backend whitelist'i tanır.
		var $insChk = $modal.find('input[data-key="__insurance"]');
		if ($insChk.length) {
			override['__insurance'] = $insChk.is(':checked') ? 'on' : 'off';
		}

		closeModal();

		ajax(WcPttKargo.actions.send, {
			order_id: orderId,
			override: override,
			parca_adet: multipack.parca_adet || '1',
			irsaliye_no: multipack.irsaliye_no || ''
		})
			.done(function (res) {
				if (res && res.success) {
					var msg = WcPttKargo.i18n.success + res.data.barkod;
					if (res.data.barkodlar && res.data.barkodlar.length > 1) {
						msg += ' (+' + (res.data.barkodlar.length - 1) + ' ek parça)';
					}
					toast(msg, 'success');
					if (res.data.label_url) window.open(res.data.label_url, '_blank');
					refreshList();
				} else {
					toast(WcPttKargo.i18n.error + ((res && res.data && res.data.message) || WcPttKargo.i18n.unknownErr), 'error');
					refreshList();
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || WcPttKargo.i18n.serverErr;
				toast(WcPttKargo.i18n.error + msg, 'error');
				refreshList();
			})
			.always(function () {
				$btn.removeClass('is-loading').text(WcPttKargo.i18n.shipBtn);
			});
	}

	function prepareAndOpen(orderId) {
		ajax(WcPttKargo.actions.prepare, { order_id: orderId })
			.done(function (res) {
				if (res && res.success) {
					openSummaryModal(res.data);
				} else {
					toast(WcPttKargo.i18n.error + ((res && res.data && res.data.message) || WcPttKargo.i18n.prepareErr), 'error');
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || WcPttKargo.i18n.serverErr;
				toast(WcPttKargo.i18n.error + msg, 'error');
			});
	}

	// ---- Event delegation ----

	$(document).on('click', '#wc-ptt-refresh', function (e) {
		e.preventDefault();
		refreshList();
	});

	$(document).on('click', '.js-ptt-send', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;
		prepareAndOpen(orderId);
	});

	$(document).on('click', '.js-ptt-takip', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;
		ajax(WcPttKargo.actions.takip, { order_id: orderId }).done(function (res) {
			if (res && res.success) {
				openTakipModal(res.data);
			} else {
				toast(WcPttKargo.i18n.trackErr, 'error');
			}
		}).fail(function () {
			toast(WcPttKargo.i18n.trackErr, 'error');
		});
	});

	// PTT gönderisini iptal et: barkodVeriSil → fallback referansVeriSil.
	// Sadece PTT henüz kabul etmediyse başarılı olur.
	$(document).on('click', '.js-ptt-cancel', function (e) {
		e.preventDefault();
		var orderId = parseInt($(this).data('order-id'), 10);
		if (!orderId) return;

		if (!window.confirm(WcPttKargo.i18n.cancelConfirm)) return;

		var $btn = $(this);
		var originalText = $btn.text();
		$btn.prop('disabled', true).text(WcPttKargo.i18n.canceling);

		ajax(WcPttKargo.actions.cancelKargo, { order_id: orderId })
			.done(function (res) {
				if (res && res.success) {
					var msg = WcPttKargo.i18n.cancelOk;
					if (res.data && res.data.message) msg += ' (' + res.data.message + ')';
					toast(msg, 'success');
					refreshList();
					// Order edit sayfasındayken metabox'ı yenilemek için sayfa reload.
					if (window.location.href.indexOf('action=edit') !== -1 ||
						window.location.href.indexOf('post.php') !== -1) {
						setTimeout(function () { window.location.reload(); }, 800);
					}
				} else {
					var err = (res && res.data && res.data.message) || WcPttKargo.i18n.unknownErr;
					toast(WcPttKargo.i18n.cancelErr + err, 'error');
				}
			})
			.fail(function (xhr) {
				var err = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					|| WcPttKargo.i18n.serverErr;
				toast(WcPttKargo.i18n.cancelErr + err, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false).text(originalText);
			});
	});

	function openTakipModal(d) {
		$('#wc-ptt-takip-modal').remove();
		var pttSuccess = !!d.success;
		var hasEvents  = d.dongu && d.dongu.length > 0;

		var html = '<div id="wc-ptt-takip-modal" class="wc-ptt-modal">'
			+ '<div class="wc-ptt-modal-overlay"></div>'
			+ '<div class="wc-ptt-modal-box wc-ptt-takip-box">'
			+ '<h2>Gönderi Takibi — <code>' + escapeHtml(d.barkod || '-') + '</code></h2>';

		if (d.ref_fallback) {
			html += '<div style="padding:6px 10px; background:#fff3cd; border-left:4px solid #856404; border-radius:3px; font-size:11px; margin-bottom:10px;">'
				+ '⚠ Barkod sorgusu sonuç vermedi; <strong>referans numarası</strong> ile bulundu.'
				+ '</div>';
		} else if (d.used_method === 'gonderiSorgu_referansNo') {
			html += '<div style="padding:6px 10px; background:#d1ecf1; border-left:4px solid #0c5460; border-radius:3px; font-size:11px; margin-bottom:10px;">'
				+ 'ℹ Referans numarası ile sorgulandı.'
				+ '</div>';
		}

		if (!pttSuccess) {
			html += '<div class="wc-ptt-takip-warn is-error">⚠ ' + escapeHtml(d.mesaj || WcPttKargo.i18n.trackErr) + '</div>';
		} else if (!hasEvents) {
			html += '<div class="wc-ptt-takip-warn is-info">ℹ ' + escapeHtml(d.mesaj || 'Henüz hareket yok.') + '</div>';
		} else {
			html += '<div class="wc-ptt-takip-meta">';
			if (d.alici)    html += '<p><strong>Alıcı:</strong> ' + escapeHtml(d.alici) + '</p>';
			if (d.gonderen) html += '<p><strong>Gönderen:</strong> ' + escapeHtml(d.gonderen) + '</p>';
			if (d.mesaj)    html += '<p><strong>' + escapeHtml(WcPttKargo.i18n.trackStatus) + '</strong> ' + escapeHtml(d.mesaj) + '</p>';
			html += '</div>';
			html += '<h3>' + escapeHtml(WcPttKargo.i18n.trackEvents) + '</h3>';
			html += '<table class="wc-ptt-takip-events"><thead><tr><th>Tarih / Saat</th><th>İşlem</th><th>Merkez</th></tr></thead><tbody>';
			d.dongu.forEach(function (s) {
				var tarih = (s.ITARIH || '') + (s.ISAAT ? ' ' + s.ISAAT : '');
				html += '<tr>'
					+ '<td>' + escapeHtml(tarih) + '</td>'
					+ '<td>' + escapeHtml(s.ISLEM || '') + '</td>'
					+ '<td>' + escapeHtml(s.IMERK || '') + '</td>'
					+ '</tr>';
			});
			html += '</tbody></table>';
		}

		// Drop point bilgisi (ajax_takip otomatik fetch ediyor; success ise göster)
		if (d.drop_point && d.drop_point.success) {
			var dp = d.drop_point;
			html += '<h3 style="margin-top:18px;">📍 ' + escapeHtml(WcPttKargo.i18n.dropPointTitle) + '</h3>';
			html += '<div class="wc-ptt-drop-point">';
			if (dp.dropPointName)        html += '<p><strong>' + escapeHtml(dp.dropPointName) + '</strong>';
			if (dp.dropPointCode)        html += ' <code>(' + escapeHtml(dp.dropPointCode) + ')</code>';
			html += '</p>';
			if (dp.dropPointFullAddress) html += '<p>' + escapeHtml(dp.dropPointFullAddress) + '</p>';
			if (dp.dropPointProvince)    html += '<p>' + escapeHtml(dp.dropPointProvince) + ' · ' + escapeHtml(dp.dropPointZipCode || '') + '</p>';
			if (dp.dropPointPhoneNumber) html += '<p>📞 ' + escapeHtml(dp.dropPointPhoneNumber) + '</p>';
			if (dp.dropPointEmail)       html += '<p>✉ ' + escapeHtml(dp.dropPointEmail) + '</p>';
			if (dp.dropPointWorkHours)   html += '<p>🕒 ' + escapeHtml(dp.dropPointWorkHours) + '</p>';
			if (dp.dropPointDeadLine)    html += '<p><strong>Son teslim alınma:</strong> ' + escapeHtml(dp.dropPointDeadLine) + '</p>';
			if (dp.dropPointLatitude && dp.dropPointLongitude) {
				html += '<p><a href="https://www.google.com/maps/search/?api=1&query='
					+ encodeURIComponent(dp.dropPointLatitude + ',' + dp.dropPointLongitude)
					+ '" target="_blank">📍 Haritada göster</a></p>';
			}
			html += '</div>';
		} else if (d.drop_point_error) {
			// İsteğe bağlı bilgi; hata mesajını sadece small olarak ipucu şeklinde göster.
			html += '<p style="margin-top:14px; font-size:11px; color:#646970;">'
				+ '<em>Drop point bilgisi alınamadı: ' + escapeHtml(d.drop_point_error) + '</em></p>';
		}

		if (d.raw) {
			html += '<details class="raw-toggle" style="margin-top:14px;"><summary>Ham PTT cevabı</summary>'
				+ '<pre class="raw-dump">' + escapeHtml(d.raw) + '</pre></details>';
		}
		if (d.drop_point && d.drop_point.raw) {
			html += '<details class="raw-toggle" style="margin-top:6px;"><summary>Drop point ham cevabı</summary>'
				+ '<pre class="raw-dump">' + escapeHtml(d.drop_point.raw) + '</pre></details>';
		}

		html += '<div class="wc-ptt-modal-actions">'
			+ '<button type="button" class="button" data-action="cancel">Kapat</button>'
			+ '</div></div></div>';

		$('body').append(html);
	}

	// ---------- Kurye Çağırma (admin sayfasından) ----------

	$(document).on('click', '#wc-ptt-courier-submit', function (e) {
		e.preventDefault();
		var $btn   = $(this);
		var $form  = $('#wc-ptt-courier-form');
		var $out   = $('#wc-ptt-courier-result');
		var origText = $btn.html();

		var data = { action: WcPttKargo.actions.courier, nonce: WcPttKargo.nonce };
		$form.find('input').each(function () {
			var name = $(this).attr('name');
			if (!name) return;
			data[name] = ($(this).val() || '').trim();
		});

		if (!data.adet || parseInt(data.adet, 10) <= 0) {
			$out.removeClass('is-success').addClass('is-error').show()
				.text('Geçerli bir paket sayısı gir.');
			return;
		}

		$btn.prop('disabled', true).text(WcPttKargo.i18n.courierSending);
		$out.removeClass('is-success is-error').show().text(WcPttKargo.i18n.courierSending);

		$.post(WcPttKargo.ajaxUrl, data)
			.done(function (res) {
				if (res && res.success) {
					var msg = WcPttKargo.i18n.courierOk;
					if (res.data && res.data.message) msg += ' — ' + res.data.message;
					if (res.data && res.data.siparis_id) msg += ' (Sipariş ID: ' + res.data.siparis_id + ')';
					$out.removeClass('is-error').addClass('is-success').text(msg);
				} else {
					var err = (res && res.data && res.data.message) || WcPttKargo.i18n.unknownErr;
					$out.removeClass('is-success').addClass('is-error').text(WcPttKargo.i18n.courierErr + err);
				}
			})
			.fail(function (xhr) {
				var err = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || WcPttKargo.i18n.serverErr;
				$out.removeClass('is-success').addClass('is-error').text(WcPttKargo.i18n.courierErr + err);
			})
			.always(function () {
				$btn.prop('disabled', false).html(origText);
			});
	});

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	$(document).on('click', '#wc-ptt-takip-modal .wc-ptt-modal-overlay, #wc-ptt-takip-modal [data-action="cancel"]', function () {
		$('#wc-ptt-takip-modal').remove();
	});

	$(document).on('click', '.wc-ptt-modal-overlay, [data-action="cancel"]', function () {
		closeModal();
	});

	$(document).on('click', '[data-action="confirm"]', function (e) {
		e.preventDefault();
		sendCurrent();
	});

})(jQuery);
