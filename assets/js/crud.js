/*
 * Pages made by the CRUD generator: add/edit in a modal, delete with a
 * confirmation, and reloading the DataTable afterwards. The form HTML comes
 * from the server (admin/c/<slug>/form[/<id>]) and is posted with fetch()
 * so file uploads work; validation errors come back as JSON.
 *
 * CodeIgniter rotates the CSRF token after every POST, so each JSON answer
 * carries the next one and it is kept in `csrf` here.
 */
(function () {
	'use strict';

	var root = document.getElementById('crud-root');
	var modalEl = document.getElementById('crud-modal');
	if (!root || !modalEl || !window.bootstrap) { return; }

	var base = root.getAttribute('data-base');
	var csrfName = root.getAttribute('data-csrf-name');
	var csrf = root.getAttribute('data-csrf-hash');
	var modal = new bootstrap.Modal(modalEl);
	var body = document.getElementById('crud-modal-body');
	var title = document.getElementById('crud-modal-title');
	var submit = document.getElementById('crud-submit');
	var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, text/html' };

	function reload() {
		if (window.jQuery) { window.jQuery('#' + root.getAttribute('data-table')).DataTable().ajax.reload(null, false); }
	}

	function open(id) {
		title.textContent = id ? 'Edit' : 'Add';
		body.innerHTML = '<div class="text-center py-5"><span class="spinner-border" role="status" aria-label="Loading"></span></div>';
		submit.disabled = true;
		modal.show();

		fetch(base + '/form' + (id ? '/' + id : ''), { headers: headers, credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
			.then(function (html) {
				body.innerHTML = html;
				var form = body.querySelector('form');
				title.textContent = form.getAttribute('data-title');
				submit.disabled = false;
				var first = form.querySelector('input:not([type=hidden]), select, textarea');
				if (first) { setTimeout(function () { first.focus(); }, 300); }
			})
			.catch(function () {
				modal.hide();
				window.App.toast('error', 'Could not open the form. Reload the page and try again.');
			});
	}

	function clearErrors(form) {
		form.querySelectorAll('.is-invalid').forEach(function (i) { i.classList.remove('is-invalid'); });
		form.querySelectorAll('[data-error-for]').forEach(function (d) { d.textContent = ''; d.style.display = ''; });
	}

	function showErrors(form, errors) {
		var first = null;
		Object.keys(errors || {}).forEach(function (name) {
			var wrap = form.querySelector('[data-field="' + name + '"]');
			if (!wrap) { return; }
			var input = wrap.querySelector('input:not([type=hidden]), select, textarea');
			if (input) { input.classList.add('is-invalid'); }
			var msg = wrap.querySelector('[data-error-for]');
			msg.textContent = errors[name];
			msg.style.display = 'block'; // radio/checkbox groups have no .is-invalid sibling to reveal it
			if (!first) { first = wrap; }
		});
		if (first) {
			// Keep focus inside the dialog (Esc to close, screen readers) and on the first problem.
			var target = first.querySelector('input:not([type=hidden]), select, textarea');
			if (target) { target.focus({ preventScroll: true }); }
			first.scrollIntoView({ block: 'center', behavior: 'smooth' });
		}
	}

	function post(url, data) {
		data.set(csrfName, csrf);
		return fetch(url, { method: 'POST', body: data, headers: headers, credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
			.then(function (res) { if (res.json && res.json.csrf) { csrf = res.json.csrf; } return res; });
	}

	// Add / edit / delete buttons.
	document.addEventListener('click', function (e) {
		var add = e.target.closest('[data-crud-add]');
		var edit = e.target.closest('[data-crud-edit]');
		var del = e.target.closest('[data-crud-delete]');
		if (add) { open(null); }
		else if (edit) { open(edit.getAttribute('data-crud-edit')); }
		else if (del) {
			var id = del.getAttribute('data-crud-delete');
			window.App.confirm({ title: 'Delete this record?', text: 'This cannot be undone.', confirmButtonText: 'Delete', confirmButtonColor: '#dc3545' }).then(function (r) {
				if (!r.isConfirmed) { return; }
				post(base + '/delete/' + id, new FormData()).then(function (res) {
					window.App.toast(res.json.ok ? 'success' : 'error', res.json.message || 'Could not delete.');
					if (res.json.ok) { reload(); }
				}).catch(function () { window.App.toast('error', 'Could not delete. Reload the page and try again.'); });
			});
		}
	});

	// Save.
	body.addEventListener('submit', function (e) {
		var form = e.target.closest('form[data-crud-form]');
		if (!form) { return; }
		e.preventDefault();
		clearErrors(form);
		submit.disabled = true;
		var label = submit.innerHTML;
		submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving…';

		post(form.getAttribute('action'), new FormData(form)).then(function (res) {
			if (res.json.ok) {
				modal.hide();
				window.App.toast('success', res.json.message);
				reload();
			} else {
				showErrors(form, res.json.errors);
				if (!res.json.errors) { window.App.toast('error', res.json.message || 'Could not save.'); }
			}
		}).catch(function () {
			window.App.toast('error', 'Could not save. Check your connection and try again.');
		}).then(function () {
			submit.disabled = false;
			submit.innerHTML = label;
		});
	});

	// Clear a field's error as soon as it is edited.
	body.addEventListener('input', function (e) {
		var wrap = e.target.closest('[data-field]');
		if (!wrap) { return; }
		e.target.classList.remove('is-invalid');
		var msg = wrap.querySelector('[data-error-for]');
		if (msg) { msg.textContent = ''; msg.style.display = ''; }
	});
})();
