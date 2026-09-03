(function ($) {
	'use strict';

	/* ---------------- Branching / skip logic ---------------- */

	/**
	 * Shows/hides each question that has a "show only if" condition
	 * (data-branch-question / data-branch-value, set by the admin in the
	 * question's settings), based on the currently selected answer to the
	 * question it depends on. Always re-evaluates every conditional question
	 * from scratch (not just the one that just changed), since one answer can
	 * be the branch source for several other questions at once. Server-side,
	 * Ravanix_Ajax::submit_test() independently re-derives the exact same
	 * active/inactive set from the submitted answers -- this client-side pass
	 * only controls what the participant sees and can be required to answer;
	 * it is never trusted as the actual security/scoring boundary.
	 */
	function applyBranching($container) {
		var $form = $container.find('.rs-test-form');
		var changed = false;

		$form.find('.rs-question[data-branch-question]').each(function () {
			var $q = $(this);
			var depId = $q.data('branch-question');
			var expected = String($q.data('branch-value'));
			var actual = $form.find('input[name="answers[' + depId + ']"]:checked').val();
			var isActive = (typeof actual !== 'undefined') && (String(actual) === expected);
			var wasHidden = $q.hasClass('rs-question-hidden');

			if (isActive === wasHidden) { changed = true; }
			$q.toggleClass('rs-question-hidden', !isActive);
		});

		if (changed) {
			renumberQuestions($container);
			// The active-question set changed, so the progress denominator did too.
			getState($container).totalQuestions = null;
			updateProgress($container);
		}
	}

	/**
	 * Renumbers the "1.", "2.", ... prefix on each visible question in display
	 * order, so hidden (skipped) questions never leave a gap in the numbering
	 * the participant sees.
	 */
	function renumberQuestions($container) {
		var n = 0;
		$container.find('.rs-question:not(.rs-question-hidden) .rs-q-num-live').each(function () {
			n++;
			$(this).text(n + '.');
		});
	}

	/* ---------------- Per-form state cache (avoids a full DOM scan on every click, important for long questionnaires like the 240-item NEO) ---------------- */

	function getState($container) {
		var state = $container.data('rsState');
		if (!state) {
			state = { answeredIds: {}, answeredCount: 0, totalQuestions: null };
			$container.data('rsState', state);
		}
		if (null === state.totalQuestions) {
			state.totalQuestions = $container.find('.rs-question:not(.rs-question-hidden)').length;
		}
		return state;
	}

	function markAnswered($container, qid) {
		var state = getState($container);
		if (!state.answeredIds[qid]) {
			state.answeredIds[qid] = true;
			state.answeredCount++;
		}
	}

	function recomputeAnsweredState($container) {
		// Used only once (e.g. after restoring a draft) to fully resync the cache with the DOM
		var state = getState($container);
		state.answeredIds = {};
		state.answeredCount = 0;
		$container.find('.rs-question').each(function () {
			var qid = $(this).data('question-id');
			if ($(this).find('input[type="radio"]:checked').length) {
				state.answeredIds[qid] = true;
				state.answeredCount++;
			}
		});
	}

	/**
	 * Debounces a function: merges several rapid, consecutive calls into a single
	 * run (e.g. when answering several questions quickly in a row, autosave only runs once).
	 */
	function debounce(fn, wait) {
		var timer = null;
		return function () {
			var args = arguments;
			var ctx  = this;
			clearTimeout(timer);
			timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
		};
	}

	/* ---------------- Autosave (localStorage) helpers ---------------- */

	function draftKey(testId) {
		return 'ravanix_draft_' + testId;
	}

	function saveDraft($container) {
		try {
			var testId = $container.data('test-id');
			var $form  = $container.find('.rs-test-form');
			var answers = {};
			var participant = {};

			$form.find('.rs-question').each(function () {
				var qid = $(this).data('question-id');
				var val = $form.find('input[name="answers[' + qid + ']"]:checked').val();
				if (typeof val !== 'undefined') { answers[qid] = val; }
			});
			$form.find('.rs-participant-field').each(function () {
				var key = $(this).data('field-key');
				var val = $(this).find('input, select').val();
				if (val) { participant[key] = val; }
			});

			var currentPage = parseInt($form.data('current-page') || 0, 10);

			localStorage.setItem(draftKey(testId), JSON.stringify({
				answers: answers,
				participant: participant,
				page: currentPage,
				guest_name: $form.find('input[name="guest_name"]').val() || '',
				saved_at: Date.now()
			}));
		} catch (e) { /* localStorage is not available; safely ignored */ }
	}

	function loadDraft(testId) {
		try {
			var raw = localStorage.getItem(draftKey(testId));
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function clearDraft(testId) {
		try { localStorage.removeItem(draftKey(testId)); } catch (e) { /* safe to ignore */ }
	}

	var scheduleSaveDraft = debounce(function ($container) { saveDraft($container); }, 500);

	function restoreDraft($container, draft) {
		var $form = $container.find('.rs-test-form');

		// Comparing name/value via JS equality inside .filter(), rather than
		// interpolating the saved value into a CSS attribute selector string,
		// means a tampered/unusual localStorage value (someone editing their
		// own browser storage, deliberately or by accident) can only fail to
		// match -- it can never break the selector itself and abort the whole restore.
		var $allRadios = $form.find('input[type="radio"]');
		Object.keys(draft.answers || {}).forEach(function (qid) {
			var name = 'answers[' + qid + ']';
			var wanted = String(draft.answers[qid]);
			$allRadios.filter(function () {
				return $(this).attr('name') === name && String(this.value) === wanted;
			}).prop('checked', true);
		});
		var $allParticipantFields = $form.find('.rs-participant-field');
		Object.keys(draft.participant || {}).forEach(function (key) {
			$allParticipantFields.filter(function () {
				return $(this).data('field-key') === key;
			}).find('input, select').val(draft.participant[key]);
		});
		if (draft.guest_name) {
			$form.find('input[name="guest_name"]').val(draft.guest_name);
		}

		applyBranching($container);
		recomputeAnsweredState($container);
		updateProgress($container);
		goToPage($container, draft.page || 0);
	}

	/* ---------------- Pagination ---------------- */

	function totalPages($container) {
		return parseInt($container.data('total-pages') || 1, 10);
	}

	function goToPage($container, pageIndex) {
		var $form  = $container.find('.rs-test-form');
		var total  = totalPages($container);

		pageIndex = Math.max(0, Math.min(pageIndex, total - 1));

		$form.find('.rs-page').hide();
		$form.find('.rs-page[data-page="' + pageIndex + '"]').show();
		$form.data('current-page', pageIndex);

		$form.find('.rs-btn-prev').toggle(pageIndex > 0);
		$form.find('.rs-btn-next').toggle(pageIndex < total - 1);
		$form.find('.rs-btn-submit').toggle(pageIndex === total - 1);

		$('html, body').animate({ scrollTop: $form.offset().top - 80 }, 250);
	}

	function updateProgress($container) {
		var $form  = $container.find('.rs-test-form');
		var state  = getState($container);
		var total  = state.totalQuestions;
		var answered = state.answeredCount;
		var pct    = total > 0 ? Math.round((answered / total) * 100) : 0;

		$form.find('.rs-progress-bar-fill').css('width', pct + '%');
		$form.find('.rs-progress-text').text(
			Ravanix_Frontend.i18n.progress_text.replace('%1$d', answered).replace('%2$d', total)
		);
	}

	/**
	 * Validates the questions (and participant fields, if present) within a given
	 * scope (the whole form or just one page), without clearing previously entered answers.
	 */
	function validateScope($scope, $form) {
		var missing = [];

		$scope.find('.rs-participant-field').each(function () {
			var $f  = $(this);
			var val = $f.find('input, select').val() || '';
			if ($f.data('required') == 1 && val.trim() === '') {
				missing.push($f);
				$f.addClass('rs-question-missing');
			} else {
				$f.removeClass('rs-question-missing');
			}
		});

		$scope.find('.rs-question:not(.rs-question-hidden)').each(function () {
			var $q  = $(this);
			var qid = $q.data('question-id');
			var val = $form.find('input[name="answers[' + qid + ']"]:checked').val();
			if (typeof val === 'undefined') {
				missing.push($q);
				$q.addClass('rs-question-missing');
			} else {
				$q.removeClass('rs-question-missing');
			}
		});

		return missing;
	}

	/* ---------------- Events ---------------- */

	function startTest($container) {
		$container.data('start-time', Date.now());
		$container.find('.rs-test-intro').hide();
		$container.find('.rs-test-form').show();
		applyBranching($container);
		goToPage($container, 0);
	}

	$(document).on('click', '.rs-btn-start:not(.rs-btn-start-with-code):not(.rs-btn-resume)', function () {
		var $container = $(this).closest('.rs-test-container');
		startTest($container);
	});

	/* ---------------- Informed consent ---------------- */

	$(document).on('change', '.rs-consent-checkbox input[type="checkbox"]', function () {
		var $container = $(this).closest('.rs-test-container');
		var agreed = $(this).is(':checked');
		$container.find('.rs-consent-agreed-field').val(agreed ? '1' : '0');
		$container.find('.rs-btn-start').prop('disabled', !agreed);
		if (agreed) {
			$container.find('.rs-consent-required-note').hide();
		}
	});

	$(document).on('click', '.rs-btn-start-with-code', function () {
		var $btn       = $(this);
		var $container = $btn.closest('.rs-test-container');
		var testId     = $container.data('test-id');
		var $gate      = $btn.closest('.rs-access-code-gate');
		var $input     = $gate.find('.rs-access-code-input');
		var $err       = $gate.find('.rs-access-code-error');
		var code       = ($input.val() || '').trim();

		$err.hide().text('');

		if (!code) {
			$err.text(Ravanix_Frontend.i18n.required).show();
			return;
		}

		$btn.prop('disabled', true);

		$.ajax({
			url: Ravanix_Frontend.ajax_url,
			method: 'POST',
			data: { action: 'ravanix_verify_access_code', nonce: Ravanix_Frontend.nonce, test_id: testId, code: code }
		}).done(function (res) {
			if (res.success) {
				$container.find('.rs-hidden-access-code').val(code);
				startTest($container);
			} else {
				$err.text((res.data && res.data.message) || Ravanix_Frontend.i18n.error).show();
			}
		}).fail(function () {
			$err.text(Ravanix_Frontend.i18n.error).show();
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$(document).ready(function () {
		$('.rs-test-container').each(function () {
			var $container = $(this);
			var testId = $container.data('test-id');
			var localDraft  = loadDraft(testId);
			var serverDraft = $container.attr('data-server-draft');
			serverDraft = serverDraft ? JSON.parse(serverDraft) : null;

			// Prefer whichever draft is more recent; a server draft only exists
			// at all for a logged-in participant on a Save & Resume-enabled test
			// (see the PHP template), so this never overrides a same-device
			// localStorage draft with something older.
			var draft = localDraft;
			if (serverDraft && (!localDraft || serverDraft.saved_at >= localDraft.saved_at)) {
				draft = serverDraft;
			}

			if (draft && (Object.keys(draft.answers || {}).length || Object.keys(draft.participant || {}).length)) {
				$container.data('rsPendingDraft', draft);
				$container.find('.rs-resume-banner').show();
			}
		});
	});

	$(document).on('click', '.rs-btn-resume', function () {
		var $container = $(this).closest('.rs-test-container');
		var draft = $container.data('rsPendingDraft') || loadDraft($container.data('test-id'));

		startTest($container);

		if (draft) { restoreDraft($container, draft); }
	});

	$(document).on('click', '.rs-btn-restart', function () {
		var $container = $(this).closest('.rs-test-container');
		var testId = $container.data('test-id');
		clearDraft(testId);
		$container.find('.rs-resume-banner').hide();

		if (Ravanix_Frontend.is_logged_in && '1' === String($container.data('save-resume'))) {
			$.ajax({
				url: Ravanix_Frontend.ajax_url,
				method: 'POST',
				data: { action: 'ravanix_delete_draft', nonce: Ravanix_Frontend.nonce, test_id: testId }
			});
		}
	});

	/* ---------------- Explicit "Save my progress" button ---------------- */

	$(document).on('click', '.rs-btn-save-progress', function () {
		var $btn       = $(this);
		var $container = $btn.closest('.rs-test-container');
		var testId     = $container.data('test-id');
		var $status    = $container.find('.rs-save-progress-status');

		saveDraft($container); // Always: the existing, immediate browser-local save.

		if (!Ravanix_Frontend.is_logged_in) {
			$status.text(Ravanix_Frontend.i18n.progress_saved);
			return;
		}

		var $form = $container.find('.rs-test-form');
		var answers = {};
		var participant = {};
		$form.find('.rs-question').each(function () {
			var qid = $(this).data('question-id');
			var val = $form.find('input[name="answers[' + qid + ']"]:checked').val();
			if (typeof val !== 'undefined') { answers[qid] = val; }
		});
		$form.find('.rs-participant-field').each(function () {
			var key = $(this).data('field-key');
			var val = $(this).find('input, select').val();
			if (val) { participant[key] = val; }
		});

		var payload = {
			action: 'ravanix_save_draft',
			nonce: Ravanix_Frontend.nonce,
			test_id: testId,
			page: parseInt($form.data('current-page') || 0, 10)
		};
		Object.keys(answers).forEach(function (qid) { payload['answers[' + qid + ']'] = answers[qid]; });
		Object.keys(participant).forEach(function (key) { payload['participant[' + key + ']'] = participant[key]; });

		$btn.prop('disabled', true);
		$status.text(Ravanix_Frontend.i18n.saving_progress);

		$.ajax({
			url: Ravanix_Frontend.ajax_url,
			method: 'POST',
			traditional: true,
			data: payload
		}).done(function (res) {
			$status.text(res.success ? Ravanix_Frontend.i18n.progress_saved : Ravanix_Frontend.i18n.progress_save_error);
		}).fail(function () {
			$status.text(Ravanix_Frontend.i18n.progress_save_error);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$(document).on('change', '.rs-question input[type="radio"]', function () {
		var $container = $(this).closest('.rs-test-container');
		var $question  = $(this).closest('.rs-question');
		$question.removeClass('rs-question-missing');
		markAnswered($container, $question.data('question-id'));
		applyBranching($container);
		updateProgress($container);
		scheduleSaveDraft($container);
	});

	$(document).on('input change', '.rs-participant-field input, .rs-participant-field select', function () {
		$(this).closest('.rs-participant-field').removeClass('rs-question-missing');
		saveDraft($(this).closest('.rs-test-container'));
	});

	$(document).on('click', '.rs-btn-next', function () {
		var $container = $(this).closest('.rs-test-container');
		var $form      = $container.find('.rs-test-form');
		var $error     = $form.find('.rs-form-error');
		var current    = parseInt($form.data('current-page') || 0, 10);
		var $page      = $form.find('.rs-page[data-page="' + current + '"]');

		$error.hide().text('');

		var missing = validateScope($page, $form);
		if (missing.length > 0) {
			$error.text(Ravanix_Frontend.i18n.required).show();
			$('html, body').animate({ scrollTop: missing[0].offset().top - 80 }, 300);
			return;
		}

		saveDraft($container);
		goToPage($container, current + 1);
	});

	$(document).on('click', '.rs-btn-prev', function () {
		var $container = $(this).closest('.rs-test-container');
		var $form      = $container.find('.rs-test-form');
		var current    = parseInt($form.data('current-page') || 0, 10);
		goToPage($container, current - 1);
	});

	$(document).on('submit', '.rs-test-form', function (e) {
		e.preventDefault();

		var $form      = $(this);
		var $container = $form.closest('.rs-test-container');
		var testId     = $container.data('test-id');
		var $error     = $form.find('.rs-form-error');
		var $submitBtn = $form.find('.rs-btn-submit');

		$error.hide().text('');

		var missing = validateScope($form, $form);
		if (missing.length > 0) {
			$error.text(Ravanix_Frontend.i18n.required).show();
			$('html, body').animate({ scrollTop: missing[0].offset().top - 80 }, 300);
			return;
		}

		var answers = {};
		var participant = {};
		var guestName = $form.find('input[name="guest_name"]').val() || '';

		$form.find('.rs-question:not(.rs-question-hidden)').each(function () {
			var qid = $(this).data('question-id');
			var val = $form.find('input[name="answers[' + qid + ']"]:checked').val();
			if (typeof val !== 'undefined') { answers[qid] = val; }
		});
		$form.find('.rs-participant-field').each(function () {
			var key = $(this).data('field-key');
			participant[key] = $(this).find('input, select').val() || '';
		});

		var payload = {
			action: 'ravanix_submit_test',
			nonce: Ravanix_Frontend.nonce,
			test_id: testId,
			guest_name: guestName,
			ravanix_hp: $form.find('input[name="ravanix_hp"]').val() || '',
			access_code: $form.find('.rs-hidden-access-code').val() || '',
			consent_agreed: $form.find('.rs-consent-agreed-field').val() || '0',
			elapsed_ms: $container.data('start-time') ? (Date.now() - $container.data('start-time')) : ''
		};
		// Question answers and participant fields are sent flat (not nested) so we
		// don't depend on jQuery correctly serializing nested objects (which other
		// plugins could disrupt by setting the global "traditional" option).
		Object.keys(answers).forEach(function (qid) {
			payload['answers[' + qid + ']'] = answers[qid];
		});
		Object.keys(participant).forEach(function (key) {
			payload['participant[' + key + ']'] = participant[key];
		});

		$submitBtn.prop('disabled', true).text(Ravanix_Frontend.i18n.submitting);

		$.ajax({
			url: Ravanix_Frontend.ajax_url,
			method: 'POST',
			traditional: true,
			data: payload
		}).done(function (res) {
			if (res.success) {
				clearDraft(testId);
				renderResult($container, res.data);
			} else {
				$error.text((res.data && res.data.message) || Ravanix_Frontend.i18n.error).show();
			}
		}).fail(function () {
			$error.text(Ravanix_Frontend.i18n.error).show();
		}).always(function () {
			$submitBtn.prop('disabled', false).text(Ravanix_Frontend.i18n.submit_btn);
		});
	});

	function renderResult($container, data) {
		var $result = $container.find('.rs-test-result');
		$container.find('.rs-test-form').hide();

		var canRadar = data.scores.length >= 3;
		// Off unless the admin explicitly opted in (Ravanix Settings -> Branding);
		// see Guideline 10 -- credit links/displays must be optional and default
		// to not showing.
		var html = Ravanix_Frontend.show_branding
			? '<div class="rs-branding-banner"><a href="https://psykey.ir" target="_blank" rel="noopener">' + escapeHtml(Ravanix_Frontend.i18n.branding_tagline) + '</a></div>'
			: '';
		html += '<h2 class="rs-result-title">' + Ravanix_Frontend.i18n.your_profile + ' ' + escapeHtml(data.test_title) + '</h2>';

		if (data.pdf_url) {
			html += '<p class="rs-pdf-download-row"><a class="rs-btn rs-btn-secondary" href="' + escapeAttrUrl(data.pdf_url) + '" target="_blank" rel="noopener">' + Ravanix_Frontend.i18n.download_pdf + '</a></p>';
		}

		if (data.validity && data.validity.flagged) {
			html += '<div class="rs-validity-warning">' + escapeHtml(Ravanix_Frontend.i18n.validity_warning) + '</div>';
		}

		html += '<div class="rs-chart-tabs">';
		html += '<button type="button" class="rs-chart-tab active" data-chart="bar">' + Ravanix_Frontend.i18n.chart_bar + '</button>';
		if (canRadar) {
			html += '<button type="button" class="rs-chart-tab" data-chart="radar">' + Ravanix_Frontend.i18n.chart_radar + '</button>';
		}
		html += '</div>';

		function renderScoreCard(s) {
			var scoreLine = '  <p class="rs-score-percentage">' + s.percentage + '% — ' + Ravanix_Frontend.i18n.raw_score_of + ' ' + s.raw_score + ' ' + Ravanix_Frontend.i18n.of_word + ' ' + s.max_score + '</p>';
			if (s.t_score !== null && typeof s.t_score !== 'undefined') {
				scoreLine += '  <p class="rs-score-tscore">' + Ravanix_Frontend.i18n.t_score_label + ': <strong>' + s.t_score + '</strong> &nbsp; ' + Ravanix_Frontend.i18n.percentile_label + ': <strong>' + s.percentile + '</strong>' + (s.norm_group_label ? ' <span class="rs-norm-group">(' + escapeHtml(s.norm_group_label) + ')</span>' : '') + '</p>';
			}
			return ''
				+ '<div class="rs-score-card" style="border-inline-start-color:' + escapeAttr(s.level_color) + '">'
				+ '  <div class="rs-score-card-head">'
				+ '    <span class="rs-score-name">' + escapeHtml(s.name) + '</span>'
				+ '    <span class="rs-score-level" style="background:' + escapeAttr(s.level_color) + '">' + escapeHtml(s.level_label) + '</span>'
				+ '  </div>'
				+ '  <div class="rs-score-bar-bg"><div class="rs-score-bar-fill" style="width:' + s.percentage + '%;background:' + escapeAttr(s.level_color) + '"></div></div>'
				+ scoreLine
				+ '  <p class="rs-score-desc">' + s.description + '</p>'
				+ '</div>';
		}

		function renderCompositeTable(composites) {
			var rows = composites.map(function (c) {
				return ''
					+ '<tr>'
					+ '<td><strong>' + escapeHtml(c.name) + '</strong></td>'
					+ '<td>' + c.raw_score + ' ' + Ravanix_Frontend.i18n.of_word + ' ' + c.max_score + '</td>'
					+ '<td>' + c.percentage + '%</td>'
					+ '<td>' + ((c.t_score !== null && typeof c.t_score !== 'undefined') ? c.t_score : '—') + '</td>'
					+ '<td>' + ((c.percentile !== null && typeof c.percentile !== 'undefined') ? c.percentile + '%' : '—') + '</td>'
					+ '<td><span class="rs-score-level" style="background:' + escapeAttr(c.level_color) + '">' + escapeHtml(c.level_label) + '</span></td>'
					+ '</tr>';
			}).join('');
			return ''
				+ '<table class="rs-composite-table">'
				+ '<thead><tr>'
				+ '<th>' + Ravanix_Frontend.i18n.table_factor + '</th>'
				+ '<th>' + Ravanix_Frontend.i18n.table_raw_score + '</th>'
				+ '<th>' + Ravanix_Frontend.i18n.table_percentage + '</th>'
				+ '<th>' + Ravanix_Frontend.i18n.t_score_label + '</th>'
				+ '<th>' + Ravanix_Frontend.i18n.percentile_label + '</th>'
				+ '<th>' + Ravanix_Frontend.i18n.table_level + '</th>'
				+ '</tr></thead>'
				+ '<tbody>' + rows + '</tbody>'
				+ '</table>';
		}

		if (data.composites && data.composites.length > 0) {
			html += '<h3 class="rs-composite-title">' + Ravanix_Frontend.i18n.composite_scores_title + '</h3>';
			html += '<div class="rs-score-cards rs-composite-cards">';
			data.composites.forEach(function (c) { html += renderScoreCard(c); });
			html += '</div>';
			html += renderCompositeTable(data.composites);
			html += '<h3 class="rs-facet-title">' + Ravanix_Frontend.i18n.facet_scores_title + '</h3>';
		}

		var barHeight = Math.max(320, data.scores.length * 42);
		html += '<div class="rs-chart-wrap" data-chart-view="bar" style="height:' + barHeight + 'px;"><canvas id="rs-bar-' + data.result_id + '"></canvas></div>';
		if (canRadar) {
			html += '<div class="rs-chart-wrap" data-chart-view="radar" style="display:none;"><canvas id="rs-radar-' + data.result_id + '"></canvas></div>';
		}

		html += '<div class="rs-score-cards">';
		data.scores.forEach(function (s) { html += renderScoreCard(s); });
		html += '</div>';

		$result.html(html).show();
		$('html, body').animate({ scrollTop: $result.offset().top - 50 }, 300);

		if (typeof Chart === 'undefined') {
			return;
		}

		var charts = {};

		function buildBarChart() {
			var ctx = document.getElementById('rs-bar-' + data.result_id);
			if (!ctx) { return; }
			var dynamicHeight = Math.max(320, data.scores.length * 42);
			ctx.closest('.rs-chart-wrap').style.height = dynamicHeight + 'px';
			charts.bar = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.scores.map(function (s) { return s.name; }),
					datasets: [{
						label: Ravanix_Frontend.i18n.your_score,
						data: data.scores.map(function (s) { return s.percentage; }),
						backgroundColor: data.scores.map(function (s) { return s.level_color; })
					}]
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

		function buildRadarChart() {
			var ctx = document.getElementById('rs-radar-' + data.result_id);
			if (!ctx) { return; }
			charts.radar = new Chart(ctx, {
				type: 'radar',
				data: {
					labels: data.scores.map(function (s) { return s.name; }),
					datasets: [{
						label: Ravanix_Frontend.i18n.your_score,
						data: data.scores.map(function (s) { return s.percentage; }),
						backgroundColor: 'rgba(74,144,217,0.25)',
						borderColor: 'rgba(74,144,217,1)',
						pointBackgroundColor: 'rgba(74,144,217,1)'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: { r: { min: 0, max: 100, ticks: { showLabelBackdrop: false } } },
					plugins: { legend: { display: false } }
				}
			});
		}

		// The default (bar) chart is always built as soon as its container becomes visible
		buildBarChart();

		$result.find('.rs-chart-tab').on('click', function () {
			var type = $(this).data('chart');
			$result.find('.rs-chart-tab').removeClass('active');
			$(this).addClass('active');
			$result.find('.rs-chart-wrap').hide();
			$result.find('.rs-chart-wrap[data-chart-view="' + type + '"]').show();

			// The radar chart is only built on first click (once its container has become visible)
			if (type === 'radar' && !charts.radar) {
				buildRadarChart();
			}
		});
	}

	function escapeHtml(str) {
		return $('<div>').text(str == null ? '' : str).html();
	}
	function escapeAttr(str) {
		return (str || '').replace(/[^#a-zA-Z0-9(),.% ]/g, '');
	}

	function escapeAttrUrl(url) {
		// Safely inserts a URL into an HTML attribute: encodes dangerous HTML characters
		// without breaking the URL's structure (parameters and &).
		return (url || '').replace(/"/g, '%22').replace(/</g, '%3C').replace(/>/g, '%3E');
	}

})(jQuery);
