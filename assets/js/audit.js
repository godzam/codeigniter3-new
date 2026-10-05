/*
 * Audit log page: open one entry's details in a dialog. The details are an
 * HTML fragment rendered by the server (admin/audit/<id>); the content of the
 * entry is escaped there, never built from JSON in the browser.
 */
(function () {
	'use strict';

	var modalEl = document.getElementById('audit-modal');
	if (!modalEl || !window.bootstrap) { return; }

	var modal = new bootstrap.Modal(modalEl);
	var body = document.getElementById('audit-modal-body');

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-audit-show]');
		if (!btn) { return; }

		body.innerHTML = '<div class="text-center py-5"><span class="spinner-border" role="status" aria-label="Loading"></span></div>';
		modal.show();

		fetch(window.location.pathname.replace(/\/$/, '') + '/' + btn.getAttribute('data-audit-show'), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
			.then(function (html) { body.innerHTML = html; })
			.catch(function () { modal.hide(); window.App.toast('error', 'Could not load the details. Reload the page and try again.'); });
	});
})();
