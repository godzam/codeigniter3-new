/*
 * CRUD generator UI (Admin → Generator).
 *  - index: typed confirmation before deleting a module.
 *  - builder: add/remove/reorder fields, show the settings that belong to the
 *    chosen input type, and send everything as JSON in #module-json.
 * Validation is on the server (application/src/Crud/Definition.php); the
 * errors it returns are shown next to the inputs they belong to.
 */
(function () {
	'use strict';

	// ---- Index: delete a module -------------------------------------
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-generator-delete]');
		if (!btn || !window.Swal) { return; }
		var slug = btn.getAttribute('data-generator-delete');
		var form = btn.closest('form');
		window.App.confirm({
			icon: 'warning',
			titleText: 'Delete "' + btn.getAttribute('data-title') + '"?',
			html: 'This drops the table and <strong>all its records</strong>, deletes uploaded files, and removes its permissions. This cannot be undone.<br><br>Type <code>' + slug + '</code> to confirm.',
			input: 'text',
			inputAttributes: { autocapitalize: 'off', autocomplete: 'off', spellcheck: 'false' },
			confirmButtonText: 'Delete everything',
			confirmButtonColor: '#dc3545',
			preConfirm: function (v) {
				if (v !== slug) { Swal.showValidationMessage('That is not the module key.'); return false; }
				return v;
			}
		}).then(function (r) {
			if (r.isConfirmed) { form.querySelector('[name=confirm_slug]').value = slug; form.submit(); }
		});
	});

	var form = document.getElementById('generator-form');
	if (!form) { return; }

	var config = JSON.parse(form.getAttribute('data-config'));
	var holder = document.getElementById('g-fields');
	var locked = config.locked || [];
	var typeLabels = config.types;

	function el(tag, attrs, children) {
		var n = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { n.textContent = attrs[k]; }
			else if (k === 'class') { n.className = attrs[k]; }
			else if (attrs[k] !== false && attrs[k] !== null && attrs[k] !== undefined) { n.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
		return n;
	}

	function snake(text) {
		return text.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1').slice(0, 40);
	}

	// A labelled input. `prop` is the path used to read the value back and to attach server errors.
	function group(label, input, prop, col, help) {
		input.setAttribute('data-prop', prop);
		return el('div', { class: col || 'col-12 col-sm-6 col-lg-4' }, [
			el('label', { class: 'form-label small mb-1', text: label }), input,
			el('div', { class: 'invalid-feedback' }),
			help ? el('div', { class: 'form-text', text: help }) : null
		]);
	}
	function text(prop, value, extra) { return el('input', Object.assign({ type: 'text', class: 'form-control form-control-sm', value: value == null ? '' : value, autocomplete: 'off' }, extra || {})); }
	function num(value, extra) { return el('input', Object.assign({ type: 'number', class: 'form-control form-control-sm', value: value == null ? '' : value }, extra || {})); }
	function check(label, prop, on, extra) {
		var input = el('input', Object.assign({ type: 'checkbox', class: 'form-check-input', 'data-prop': prop }, extra || {}));
		input.checked = !!on;
		return el('div', { class: 'form-check form-check-inline' }, [input, el('label', { class: 'form-check-label small', text: label })]);
	}

	var optionsToLines = function (items) { return (items || []).map(function (i) { return i.value === i.label ? i.value : i.value + '|' + i.label; }).join('\n'); };
	var linesToOptions = function (s) {
		return s.split('\n').map(function (l) { return l.trim(); }).filter(Boolean).map(function (l) {
			var at = l.indexOf('|');
			return at < 0 ? { value: l, label: l } : { value: l.slice(0, at).trim(), label: l.slice(at + 1).trim() };
		});
	};

	function buildCard(f, index) {
		var isLocked = f.name && locked.indexOf(f.name) !== -1;
		var o = f.options || {};
		var u = f.upload || {};

		var typeSel = el('select', { class: 'form-select form-select-sm', disabled: isLocked });
		Object.keys(typeLabels).forEach(function (t) {
			var opt = el('option', { value: t, text: typeLabels[t] });
			if (t === (f.type || 'text')) { opt.selected = true; }
			typeSel.appendChild(opt);
		});

		var head = el('div', { class: 'd-flex align-items-center gap-2 card-header py-2' }, [
			el('span', { class: 'badge text-bg-secondary', 'data-role': 'num', text: '#' + (index + 1) }),
			el('span', { class: 'fw-semibold text-truncate flex-grow-1', 'data-role': 'title', text: f.label || 'New field' }),
			isLocked ? el('span', { class: 'badge text-bg-light border', text: 'existing column' }) : null,
			el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-act': 'up', 'aria-label': 'Move up' }, [el('i', { class: 'bi bi-arrow-up' })]),
			el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', 'data-act': 'down', 'aria-label': 'Move down' }, [el('i', { class: 'bi bi-arrow-down' })]),
			isLocked ? null : el('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', 'data-act': 'remove', 'aria-label': 'Remove field' }, [el('i', { class: 'bi bi-x-lg' })])
		]);

		var base = el('div', { class: 'row g-2' }, [
			group('Label', text('label', f.label, { maxlength: 60, placeholder: 'Product name' }), 'label'),
			group('Column name', text('name', f.name, { maxlength: 40, placeholder: 'product_name', disabled: isLocked, spellcheck: 'false', class: 'form-control form-control-sm font-monospace' }), 'name'),
			group('Input type', typeSel, 'type'),
			el('div', { class: 'col-12' }, [
				check('Required', 'required', f.required),
				check('Show in list', 'list', f.list === undefined ? true : f.list, { 'data-hide-for': 'password' }),
				check('Unique', 'unique', f.unique, { 'data-only-for': 'text,number,email' })
			]),
			group('Help text (optional)', text('help', f.help, { maxlength: 200 }), 'help', 'col-12')
		]);

		var panels = el('div', { class: 'mt-2' }, [
			el('div', { class: 'row g-2', 'data-for': 'text,textarea' }, [
				group('Max length', num(f.maxlength, { min: 1, max: 10000, placeholder: 'default' }), 'maxlength')
			]),
			el('div', { class: 'row g-2', 'data-for': 'number' }, [
				el('div', { class: 'col-12 col-sm-6 col-lg-4 pt-3' }, [check('Whole numbers only', 'integer', f.integer, { disabled: isLocked })]),
				group('Minimum', num(f.min, { step: 'any' }), 'min'),
				group('Maximum', num(f.max, { step: 'any' }), 'max')
			]),
			el('div', { class: 'row g-2', 'data-for': 'password' }, [
				group('Minimum length', num(f.min_length || 8, { min: 4, max: 64 }), 'min_length'),
				el('div', { class: 'col-12 form-text', text: 'Stored hashed. When editing, leaving it empty keeps the current password. Never shown in the list.' })
			]),
			el('div', { class: 'row g-2', 'data-for': 'select,radio,multiselect' }, [
				group('Where do the choices come from?', (function () {
					var s = el('select', { class: 'form-select form-select-sm' }, [
						el('option', { value: 'static', text: 'A fixed list I type here' }),
						el('option', { value: 'table', text: 'Another table' })
					]);
					s.value = o.mode === 'table' ? 'table' : 'static';
					return s;
				})(), 'options.mode', 'col-12 col-lg-4'),
				el('div', { class: 'col-12 col-lg-8', 'data-source': 'static' }, [
					el('label', { class: 'form-label small mb-1', text: 'Choices (one per line, or value|label)' }),
					(function () { var t = el('textarea', { class: 'form-control form-control-sm font-monospace', rows: 4, 'data-prop': 'options.items', placeholder: 'draft|Draft\npublished|Published' }); t.value = optionsToLines(o.items); return t; })(),
					el('div', { class: 'invalid-feedback' })
				]),
				el('div', { class: 'col-12 col-lg-8', 'data-source': 'table' }, [el('div', { class: 'row g-2' }, [
					group('Table', text('options.table', o.table, { placeholder: 'categories', spellcheck: 'false' }), 'options.table', 'col-12 col-sm-4'),
					group('Value column', text('options.value_column', o.value_column || 'id', { spellcheck: 'false' }), 'options.value_column', 'col-12 col-sm-4'),
					group('Label column', text('options.label_column', o.label_column || 'name', { spellcheck: 'false' }), 'options.label_column', 'col-12 col-sm-4')
				])])
			]),
			el('div', { class: 'row g-2', 'data-for': 'image,file' }, [
				group('Max size (KB)', num(u.max_kb || '', { min: 1, max: 51200, placeholder: 'image 2048 / file 5120' }), 'upload.max_kb'),
				group('Allowed types', text('upload.types', [].concat(u.types || []).join(', '), { placeholder: 'image: jpg, png, webp / file: pdf, docx, zip', spellcheck: 'false' }), 'upload.types', 'col-12 col-sm-6 col-lg-8')
			]),
			el('div', { class: 'row g-2', 'data-for': 'image' }, [
				group('Resize to max width (px)', num(u.max_width === undefined ? 1600 : u.max_width, { min: 0, max: 8000 }), 'upload.max_width', null, '0 keeps the original size'),
				group('Quality (30-100)', num(u.quality || 80, { min: 30, max: 100 }), 'upload.quality', null, 'JPEG/WebP; PNG is lossless')
			])
		]);

		var card = el('div', { class: 'card g-field' }, [head, el('div', { class: 'card-body' }, [base, panels])]);
		card._touchedName = !!f.name;
		return card;
	}

	function applyType(card) {
		var type = card.querySelector('[data-prop=type]').value;
		card.querySelectorAll('[data-for]').forEach(function (p) { p.classList.toggle('d-none', p.getAttribute('data-for').split(',').indexOf(type) === -1); });
		card.querySelectorAll('[data-only-for]').forEach(function (p) {
			var ok = p.getAttribute('data-only-for').split(',').indexOf(type) !== -1;
			p.closest('.form-check').classList.toggle('d-none', !ok);
		});
		card.querySelectorAll('[data-hide-for]').forEach(function (p) {
			p.closest('.form-check').classList.toggle('d-none', p.getAttribute('data-hide-for').split(',').indexOf(type) !== -1);
		});
		var mode = card.querySelector('[data-prop="options.mode"]').value;
		card.querySelectorAll('[data-source]').forEach(function (p) { p.classList.toggle('d-none', p.getAttribute('data-source') !== mode); });
	}

	function renumber() {
		holder.querySelectorAll('.g-field').forEach(function (c, i) { c.querySelector('[data-role=num]').textContent = '#' + (i + 1); });
	}

	function add(f) {
		var card = buildCard(f || {}, holder.children.length);
		holder.appendChild(card);
		applyType(card);
		return card;
	}

	function read(card) {
		var v = function (p) { var i = card.querySelector('[data-prop="' + p + '"]'); return i ? (i.type === 'checkbox' ? i.checked : i.value) : ''; };
		var type = v('type');
		var f = {
			label: v('label').trim(), name: v('name').trim(), type: type,
			required: v('required'), list: v('list'), unique: v('unique'), help: v('help').trim()
		};
		if (type === 'text' || type === 'textarea') { f.maxlength = v('maxlength'); }
		if (type === 'number') { f.integer = v('integer'); f.min = v('min'); f.max = v('max'); }
		if (type === 'password') { f.min_length = v('min_length'); }
		if (['select', 'radio', 'multiselect'].indexOf(type) !== -1) {
			f.options = v('options.mode') === 'table'
				? { mode: 'table', table: v('options.table'), value_column: v('options.value_column'), label_column: v('options.label_column') }
				: { mode: 'static', items: linesToOptions(v('options.items')) };
		}
		if (type === 'image' || type === 'file') {
			f.upload = { max_kb: v('upload.max_kb'), types: v('upload.types') };
			if (type === 'image') { f.upload.max_width = v('upload.max_width'); f.upload.quality = v('upload.quality'); }
		}
		return f;
	}

	// Existing fields keep their order; the posted order is just the card order.
	form.addEventListener('submit', function () {
		document.getElementById('module-json').value = JSON.stringify({
			title: document.getElementById('g-title').value,
			slug: document.getElementById('g-slug').value,
			table: document.getElementById('g-table').value,
			icon: document.getElementById('g-icon').value,
			fields: Array.prototype.map.call(holder.querySelectorAll('.g-field'), read)
		});
	});

	holder.addEventListener('input', function (e) {
		var card = e.target.closest('.g-field');
		if (!card) { return; }
		var prop = e.target.getAttribute('data-prop');
		if (prop === 'label') {
			card.querySelector('[data-role=title]').textContent = e.target.value || 'New field';
			var name = card.querySelector('[data-prop=name]');
			if (!card._touchedName && !name.disabled) { name.value = snake(e.target.value); }
		} else if (prop === 'name') {
			card._touchedName = true;
		}
		e.target.classList.remove('is-invalid');
	});
	holder.addEventListener('change', function (e) {
		var card = e.target.closest('.g-field');
		if (card && (e.target.getAttribute('data-prop') === 'type' || e.target.getAttribute('data-prop') === 'options.mode')) { applyType(card); }
	});
	holder.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b) { return; }
		var card = b.closest('.g-field');
		var act = b.getAttribute('data-act');
		if (act === 'remove') { card.remove(); }
		else if (act === 'up' && card.previousElementSibling) { holder.insertBefore(card, card.previousElementSibling); }
		else if (act === 'down' && card.nextElementSibling) { holder.insertBefore(card.nextElementSibling, card); }
		renumber();
	});
	document.getElementById('g-add').addEventListener('click', function () {
		var card = add({ type: 'text', list: true });
		card.scrollIntoView({ behavior: 'smooth', block: 'center' });
		card.querySelector('[data-prop=label]').focus({ preventScroll: true });
	});

	// Module-level conveniences.
	var title = document.getElementById('g-title'), slug = document.getElementById('g-slug'), table = document.getElementById('g-table'), icon = document.getElementById('g-icon');
	var slugTouched = !!slug.value;
	title.addEventListener('input', function () {
		if (!slugTouched && !slug.disabled) { slug.value = snake(title.value).slice(0, 30); document.getElementById('g-slug-hint').textContent = slug.value || 'key'; }
	});
	slug.addEventListener('input', function () { slugTouched = true; document.getElementById('g-slug-hint').textContent = slug.value || 'key'; });
	icon.addEventListener('input', function () { document.getElementById('g-icon-preview').className = 'bi ' + (/^bi-[a-z0-9-]+$/.test(icon.value) ? icon.value : 'bi-table'); });
	table.placeholder = 'same as the key';

	// Render the fields, then show the server's validation errors next to their inputs.
	(config.fields.length ? config.fields : [{ type: 'text', list: true }]).forEach(add);
	Object.keys(config.errors || {}).forEach(function (key) {
		var m = key.match(/^fields\.(\d+)\.(.+)$/);
		if (!m) { return; }
		var card = holder.children[parseInt(m[1], 10)];
		var input = card && card.querySelector('[data-prop="' + m[2] + '"]');
		if (!input) { return; }
		input.classList.add('is-invalid');
		var fb = input.parentNode.querySelector('.invalid-feedback');
		if (fb) { fb.textContent = config.errors[key]; }
		var p = input.closest('[data-for],[data-source]');
		if (p) { p.classList.remove('d-none'); }
	});
	var first = holder.querySelector('.is-invalid');
	if (first) { first.scrollIntoView({ block: 'center' }); }
})();
