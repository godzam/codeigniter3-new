/*
 * Icon picker (assets/js/icon-picker.js)
 *
 * Turns <input data-icon-picker="URL-of-icons.json"> into an input with a live preview and a
 * "Choose" button that opens a searchable grid of every Bootstrap Icons icon. Picking one writes
 * its class (bi-house) into the input and fires `input`/`change`, so other scripts react as if it
 * had been typed. The field stays a normal text input: typing a class by hand still works.
 *
 * The list (about 2,000 names) is fetched once, the first time a picker is opened, and drawn in
 * chunks of 150 as you scroll, so the page stays light.
 */
(function () {
	'use strict';

	var inputs = document.querySelectorAll('input[data-icon-picker]');
	if (!inputs.length || !window.bootstrap) { return; }

	var CHUNK = 150;
	var names = null;      // all icon names, without the "bi-" prefix
	var loading = null;    // the fetch promise
	var modalEl = null, modal = null, searchEl, gridEl, countEl, statusEl;
	var target = null;     // the input being filled
	var matches = [], shown = 0;

	function load(url) {
		if (names) { return Promise.resolve(names); }
		if (!loading) {
			loading = fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
				.then(function (list) { names = Array.isArray(list) ? list : []; return names; })
				.catch(function (e) { loading = null; throw e; });
		}
		return loading;
	}

	function current() {
		var v = target ? target.value.trim() : '';
		return /^bi-[a-z0-9-]+$/.test(v) ? v.slice(3) : '';
	}

	function draw(reset) {
		if (reset) { gridEl.innerHTML = ''; shown = 0; gridEl.scrollTop = 0; }
		var selected = current();
		var html = '';
		var end = Math.min(matches.length, shown + CHUNK);
		for (var i = shown; i < end; i++) {
			var n = matches[i];
			html += '<button type="button" class="icon-pick' + (n === selected ? ' is-selected' : '') + '" data-name="' + n + '" title="bi-' + n + '" aria-label="' + n + '">' +
				'<i class="bi bi-' + n + '" aria-hidden="true"></i><span>' + n + '</span></button>';
		}
		gridEl.insertAdjacentHTML('beforeend', html);
		shown = end;
		countEl.textContent = matches.length + (matches.length === 1 ? ' icon' : ' icons');
		statusEl.textContent = matches.length === 0 ? 'No icon matches that search.' : '';
	}

	function filter() {
		var words = searchEl.value.toLowerCase().split(/\s+/).filter(Boolean);
		matches = (names || []).filter(function (n) {
			return words.every(function (w) { return n.indexOf(w) !== -1; });
		});
		draw(true);
	}

	function build() {
		if (modalEl) { return; }
		modalEl = document.createElement('div');
		modalEl.className = 'modal fade';
		modalEl.tabIndex = -1;
		modalEl.setAttribute('aria-labelledby', 'icon-picker-title');
		modalEl.setAttribute('aria-hidden', 'true');
		modalEl.innerHTML =
			'<div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down modal-dialog-centered"><div class="modal-content">' +
			'<div class="modal-header"><h2 class="modal-title h5" id="icon-picker-title">Choose an icon</h2>' +
			'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
			'<div class="modal-body pt-2">' +
			'<div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span>' +
			'<input type="search" class="form-control" placeholder="Search: home, user, chart…" aria-label="Search icons" autocomplete="off" spellcheck="false" enterkeyhint="search"></div>' +
			'<div class="small text-body-secondary mb-2"><span class="icon-count"></span> <span class="icon-status text-danger"></span></div>' +
			'<div class="icon-grid" role="listbox" aria-label="Icons"></div></div>' +
			'<div class="modal-footer justify-content-between"><button type="button" class="btn btn-outline-secondary icon-clear">No icon</button>' +
			'<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>' +
			'</div></div>';
		document.body.appendChild(modalEl);
		modal = new bootstrap.Modal(modalEl);
		searchEl = modalEl.querySelector('input[type=search]');
		gridEl = modalEl.querySelector('.icon-grid');
		countEl = modalEl.querySelector('.icon-count');
		statusEl = modalEl.querySelector('.icon-status');

		var timer = null;
		searchEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(filter, 120); });
		gridEl.addEventListener('scroll', function () {
			if (shown < matches.length && gridEl.scrollTop + gridEl.clientHeight > gridEl.scrollHeight - 200) { draw(false); }
		});
		gridEl.addEventListener('click', function (e) {
			var b = e.target.closest('.icon-pick');
			if (b) { choose('bi-' + b.getAttribute('data-name')); }
		});
		modalEl.querySelector('.icon-clear').addEventListener('click', function () { choose(''); });
		modalEl.addEventListener('shown.bs.modal', function () { searchEl.focus(); });
	}

	function choose(value) {
		if (target) {
			target.value = value;
			target.dispatchEvent(new Event('input', { bubbles: true }));
			target.dispatchEvent(new Event('change', { bubbles: true }));
		}
		modal.hide();
		if (target) { target.focus(); }
	}

	function open(input) {
		build();
		target = input;
		searchEl.value = '';
		statusEl.textContent = '';
		countEl.textContent = 'Loading…';
		gridEl.innerHTML = '';
		modal.show();
		load(input.getAttribute('data-icon-picker')).then(function () {
			filter();
			// The icon in use may be far down the list: draw up to it so it can be shown and highlighted.
			var at = matches.indexOf(current());
			while (at >= shown && shown < matches.length) { draw(false); }
			var sel = gridEl.querySelector('.is-selected');
			if (sel) { sel.scrollIntoView({ block: 'center' }); }
		}).catch(function () {
			countEl.textContent = '';
			statusEl.textContent = 'The icon list could not be loaded. Type the class name (for example bi-house) instead.';
		});
	}

	Array.prototype.forEach.call(inputs, function (input) {
		var group = input.parentElement && input.parentElement.classList.contains('input-group') ? input.parentElement : null;
		var preview = null;

		if (!group) {
			group = document.createElement('div');
			group.className = 'input-group';
			input.parentNode.insertBefore(group, input);
			preview = document.createElement('span');
			preview.className = 'input-group-text';
			preview.innerHTML = '<i class="bi bi-image" aria-hidden="true"></i>';
			group.appendChild(preview);
			group.appendChild(input);
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'btn btn-outline-secondary';
		button.innerHTML = '<i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>Choose';
		button.addEventListener('click', function () { open(input); });
		input.insertAdjacentElement('afterend', button);

		function sync() {
			if (!preview) { return; }
			var v = input.value.trim();
			preview.firstChild.className = 'bi ' + (/^bi-[a-z0-9-]+$/.test(v) ? v : 'bi-image');
		}
		input.addEventListener('input', sync);
		sync();
	});
})();
