/*
 * Admin -> Pages (assets/js/pages.js)
 *
 * Adds, moves and removes the section cards in the layout editor. Every card
 * is plain form fields (sections[0][settings][heading]); after any change the
 * indexes are renumbered so the server receives an ordered list. The markup
 * mirrors the server-rendered cards in application/modules/pages/views/form.php.
 */
(function () {
	'use strict';

	var host = document.getElementById('page-sections');
	var form = document.getElementById('page-form');
	if (!host || !form) return;

	var catalogEl = document.getElementById('page-catalog');
	var catalog = { labels: {}, fields: {} };
	try { catalog = JSON.parse(catalogEl.textContent); } catch (e) {}

	var empty = document.getElementById('page-sections-empty');

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function fieldHtml(f, index, value) {
		var name = 'sections[' + index + '][settings][' + f.name + ']';
		var help = f.help ? '<div class="form-text">' + esc(f.help) + '</div>' : '';
		var label = '<label class="form-label small mb-1">' + esc(f.label) + '</label>';

		if (f.type === 'textarea') {
			return '<div class="col-12 mb-2">' + label +
				'<textarea class="form-control" name="' + esc(name) + '" rows="' + (f.rows || 4) + '" data-field="' + esc(f.name) + '">' + esc(value) + '</textarea>' + help + '</div>';
		}
		if (f.type === 'color') {
			return '<div class="col-12 col-sm-6 mb-2">' + label +
				'<input type="color" class="form-control form-control-color" name="' + esc(name) + '" value="#ffffff" data-field="' + esc(f.name) + '">' + help + '</div>';
		}
		return '<div class="col-12 mb-2">' + label +
			'<input type="text" class="form-control" name="' + esc(name) + '" value="' + esc(value) + '" maxlength="2000" data-field="' + esc(f.name) + '">' + help + '</div>';
	}

	function cardHtml(type, index) {
		var label = catalog.labels[type] || type;
		var fields = (catalog.fields && catalog.fields[type]) || [];

		var html = '<div class="card mb-2 page-section" data-index="' + index + '" data-type="' + esc(type) + '">' +
			'<div class="card-header d-flex align-items-center gap-2 py-2">' +
			'<i class="bi bi-grip-vertical text-body-secondary" aria-hidden="true"></i>' +
			'<span class="fw-semibold small">' + esc(label) + '</span>' +
			'<input type="hidden" name="sections[' + index + '][type]" value="' + esc(type) + '" data-role="type">' +
			'<span class="ms-auto d-flex gap-1">' +
			'<button type="button" class="btn btn-sm btn-outline-secondary section-up" aria-label="Move up" title="Move up"><i class="bi bi-arrow-up"></i></button>' +
			'<button type="button" class="btn btn-sm btn-outline-secondary section-down" aria-label="Move down" title="Move down"><i class="bi bi-arrow-down"></i></button>' +
			'<button type="button" class="btn btn-sm btn-outline-danger section-remove" aria-label="Remove" title="Remove"><i class="bi bi-trash"></i></button>' +
			'</span></div>' +
			'<div class="card-body"><div class="row">';

		fields.forEach(function (f) {
			html += fieldHtml(f, index, '');
		});

		return html + '</div></div></div>';
	}

	function cards() {
		return Array.prototype.slice.call(host.querySelectorAll('.page-section'));
	}

	function renumber() {
		cards().forEach(function (card, i) {
			card.setAttribute('data-index', i);
			var type = card.querySelector('[data-role="type"]');
			if (type) type.name = 'sections[' + i + '][type]';
			card.querySelectorAll('[data-field]').forEach(function (input) {
				input.name = 'sections[' + i + '][settings][' + input.getAttribute('data-field') + ']';
			});
		});
		if (empty) empty.hidden = cards().length > 0;
	}

	var add = document.getElementById('page-add');
	var typeSelect = document.getElementById('page-add-type');
	if (add && typeSelect) {
		add.addEventListener('click', function () {
			var type = typeSelect.value;
			host.insertAdjacentHTML('beforeend', cardHtml(type, cards().length));
			renumber();
			var last = host.lastElementChild;
			if (last) {
				var first = last.querySelector('input, textarea');
				if (first) first.focus();
			}
		});
	}

	host.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.section-up, .section-down, .section-remove') : null;
		if (!btn) return;
		var card = btn.closest('.page-section');
		if (!card) return;

		if (btn.classList.contains('section-remove')) {
			card.remove();
		} else if (btn.classList.contains('section-up') && card.previousElementSibling) {
			host.insertBefore(card, card.previousElementSibling);
		} else if (btn.classList.contains('section-down') && card.nextElementSibling) {
			host.insertBefore(card.nextElementSibling, card);
		}

		renumber();
	});

	form.addEventListener('submit', renumber);
	renumber();
})();
