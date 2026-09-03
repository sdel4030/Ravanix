(function ($) {
	'use strict';

	/* ---------------- Color picker and featured image selector ---------------- */

	$(document).ready(function () {
		if ($.fn.wpColorPicker) {
			$('.rs-color-field').wpColorPicker();
		}
	});

	$(document).ready(function () {
		var frame;

		$('#rs-select-featured-image').on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: ravanixAdminL10n.chooseImageTitle,
				button: { text: ravanixAdminL10n.useImageButton },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#featured_image_id').val(attachment.id);
				var previewUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
				$('#rs-featured-image-preview').html('<img src="' + previewUrl + '" style="max-width:260px;height:auto;display:block;margin-bottom:8px;">');
				$('#rs-remove-featured-image').show();
			});

			frame.open();
		});

		$('#rs-remove-featured-image').on('click', function (e) {
			e.preventDefault();
			$('#featured_image_id').val('');
			$('#rs-featured-image-preview').html('');
			$(this).hide();
		});
	});

	/* ---------------- Import/export page ---------------- */

	$(document).on('change', 'input[name="export_scope"]', function () {
		var isAll = $('input[name="export_scope"]:checked').val() === 'all';
		$('.rs-category-checklist input[type="checkbox"]').prop('disabled', isAll);
	});
	$('input[name="export_scope"]:checked').trigger('change');

	/* ---------------- Dimensions tab: show/hide validity threshold ---------------- */

	function toggleValidityThreshold() {
		$('#rs-validity-threshold-wrap').toggle($('#rs-is-validity-scale').is(':checked'));
	}
	$(document).on('change', '#rs-is-validity-scale', toggleValidityThreshold);
	toggleValidityThreshold();

	/* ---------------- Interpretation ranges tab: score-basis hint ---------------- */

	function updateBasisHint() {
		var $hint = $('#rs-interp-basis-hint');
		if (!$hint.length) { return; }
		var basis = $('#rs-interp-dimension option:selected').data('basis');
		$hint.text(basis === 't_score' ? ravanixAdminL10n.basisHintTScore : ravanixAdminL10n.basisHintRaw);
	}
	$(document).on('change', '#rs-interp-dimension', updateBasisHint);
	updateBasisHint();

	/* ---------------- Questions tab ---------------- */

	function toggleBulkSharedOptions() {
		$('#rs-bulk-shared-options').toggle($('#rs-bulk-question-type').val() === 'multiple');
	}
	$(document).on('change', '#rs-bulk-question-type', toggleBulkSharedOptions);
	toggleBulkSharedOptions();

	function toggleQuestionOptions() {
		var type = $('#rs-question-type').val();
		$('#rs-custom-options').toggle(type === 'multiple' || type === 'forced_choice');
		$('#rs-dimension-forced-choice-note').toggle(type === 'forced_choice');
		$('#rs-options-forced-choice-note').toggle(type === 'forced_choice');
		$('#rs-extra-dimensions-block').toggle(type !== 'forced_choice');
	}
	$(document).on('change', '#rs-question-type', toggleQuestionOptions);
	toggleQuestionOptions();

	function buildBareDimensionSelect(namePrefix, index) {
		// A new dimension selector is built by cloning the main "dimension" selector
		// (always present in the form), so it automatically carries the test's dimension list.
		var $clone = $('select[name="dimension_id"]').first().clone();
		$clone.attr('name', namePrefix + '[' + index + ']');
		$clone.find('option').first().text(ravanixAdminL10n.chooseDimensionPlaceholder || $clone.find('option').first().text());
		$clone.val('');
		return $clone;
	}

	$(document).on('click', '#rs-add-extra-dimension', function () {
		var index = $('#rs-extra-dimensions-wrap .rs-extra-dimension-row').length;
		var $row = $('<p class="rs-extra-dimension-row"></p>');
		$row.append(buildBareDimensionSelect('extra_dimension_id', index));
		var $label = $('<label></label>').append(
			$('<input type="checkbox" value="1">').attr('name', 'extra_dimension_reverse[' + index + ']'),
			' ' + ravanixAdminL10n.reverseLabel
		);
		$row.append($label);
		$row.append($('<input type="number" step="any" style="width:80px;">').attr('name', 'extra_dimension_weight[' + index + ']').attr('placeholder', ravanixAdminL10n.weightPlaceholder).val('1'));
		$('#rs-extra-dimensions-wrap').append($row);
	});

	function buildOptionDimensionSelect() {
		var $existing = $('#rs-options-wrap .rs-option-dimension').first();
		if ($existing.length) {
			var $clone = $existing.clone();
			$clone.val('');
			return $clone;
		}
		// No row available to clone (a very rare case): return an empty selector
		return $('<select name="option_dimension_id[]" class="rs-option-dimension"></select>');
	}

	$(document).on('click', '#rs-add-option', function () {
		var $row = $('<p class="rs-option-row"></p>');
		$row.append($('<input type="text" name="option_text[]">').attr('placeholder', ravanixAdminL10n.optionTextPlaceholder));
		$row.append($('<input type="number" step="any" name="option_value[]" style="width:80px;">').attr('placeholder', ravanixAdminL10n.optionValuePlaceholder));
		$row.append(buildOptionDimensionSelect());
		$('#rs-options-wrap').append($row);
	});

	$(document).on('click', '#rs-parse-bulk-options', function () {
		var raw = $('#rs-bulk-options-textarea').val();
		if (!raw.trim()) { return; }
		var lines = raw.split(/\r\n|\r|\n/);
		lines.forEach(function (line) {
			line = line.trim();
			if (!line) { return; }
			var parts = line.split('|');
			var text  = (parts[0] || '').trim();
			var value = (parts[1] || '').trim();
			if (!text) { return; }
			var $row = $('<p class="rs-option-row"></p>');
			$row.append($('<input type="text" name="option_text[]">').val(text));
			$row.append($('<input type="number" step="any" name="option_value[]" style="width:80px;">').val(value));
			$row.append(buildOptionDimensionSelect());
			$('#rs-options-wrap').append($row);
		});
		$('#rs-bulk-options-textarea').val('');
	});

	/* ---------------- General Info tab ---------------- */

	$(document).on('click', '#rs-toggle-new-product', function (e) {
		e.preventDefault();
		$('#rs-new-product-form').toggle();
	});

	function toggleCooldown() {
		$('#rs-cooldown-wrap').toggle($('#execution_limit').val() === 'cooldown');
	}
	$(document).on('change', '#execution_limit', toggleCooldown);
	toggleCooldown();

	// Ensure the classic editor's (TinyMCE) content is synced to the real field
	// before the form submits; otherwise description text changes aren't saved.
	$(document).on('submit', '#rs-general-form', function () {
		if (typeof tinymce !== 'undefined' && tinymce.get('ravanix_description')) {
			tinymce.triggerSave();
		}
	});

	/* ---------------- Tests list / results list: select-all + bulk-action validation ---------------- */

	function wireBulkList(selectAllId, formId) {
		var $selectAll = $(selectAllId);
		var $form      = $(formId);

		$(document).on('change', selectAllId, function () {
			$form.find('.rs-row-checkbox').prop('checked', $selectAll.prop('checked'));
		});

		$(document).on('submit', formId, function (e) {
			var action  = $form.find('select[name="bulk_action"]').val();
			var checked = $form.find('.rs-row-checkbox:checked').length;
			if (!action || checked === 0) {
				e.preventDefault();
			}
		});
	}
	wireBulkList('#rs-tests-select-all', '#rs-tests-bulk-form');
	wireBulkList('#rs-results-select-all', '#rs-results-bulk-form');

	/* ---------------- Profile chart on the result view page ---------------- */

	$(document).ready(function () {
		var $wrap = $('#rs-admin-chart-data');
		if (!$wrap.length || typeof Chart === 'undefined') { return; }

		var labels = $wrap.data('labels') || [];
		var values = $wrap.data('values') || [];
		var colors = $wrap.data('colors') || [];
		var charts = {};

		var barCtx = document.getElementById('rs-admin-bar');
		if (barCtx) {
			barCtx.closest('.rs-chart-wrap').style.height = Math.max(340, labels.length * 42) + 'px';
			charts.bar = new Chart(barCtx, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{ label: ravanixAdminL10n.chartProfileLabel, data: values, backgroundColor: colors }]
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					scales: { x: { min: 0, max: 100 } },
					plugins: { legend: { display: false } }
				}
			});
		}

		$(document).on('click', '.rs-chart-tab', function () {
			var $btn  = $(this);
			var type  = $btn.data('chart');

			$('.rs-chart-tab').removeClass('active');
			$btn.addClass('active');
			$('.rs-chart-wrap').hide();
			$('.rs-chart-wrap[data-chart-view="' + type + '"]').show();

			if (type === 'radar' && !charts.radar) {
				var radarCtx = document.getElementById('rs-admin-radar');
				if (radarCtx) {
					charts.radar = new Chart(radarCtx, {
						type: 'radar',
						data: {
							labels: labels,
							datasets: [{
								label: ravanixAdminL10n.chartProfileLabel,
								data: values,
								backgroundColor: 'rgba(74,144,217,0.25)',
								borderColor: 'rgba(74,144,217,1)',
								pointBackgroundColor: 'rgba(74,144,217,1)'
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: { r: { min: 0, max: 100 } },
							plugins: { legend: { display: false } }
						}
					});
				}
			}
		});
	});

	/**
	 * "Delete data on uninstall" (Danger zone, Settings page): requires an
	 * explicit confirmation click at the exact moment the admin turns this on,
	 * in addition to the warning text already printed next to the checkbox —
	 * so the consequence is seen and acknowledged, not just theoretically readable.
	 */
	$(function () {
		var $deleteCheckbox = $('input[name="delete_data_on_uninstall"]');
		if (!$deleteCheckbox.length) {
			return;
		}
		$deleteCheckbox.on('change', function () {
			if (this.checked && !window.confirm(ravanixAdminL10n.confirmDeleteOnUninstall)) {
				this.checked = false;
			}
		});
	});

})(jQuery);
