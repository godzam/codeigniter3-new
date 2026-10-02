/*
 * App behavior: flash messages and confirm dialogs (SweetAlert2), the
 * light/dark toggle, and the admin sidebar. Plain JS, no build step.
 */
(function () {
	'use strict';

	// ---- SweetAlert2 helpers, available to every page as App.* --------
	var Toast = window.Swal ? Swal.mixin({
		toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true,
		didOpen: function (el) { el.addEventListener('mouseenter', Swal.stopTimer); el.addEventListener('mouseleave', Swal.resumeTimer); }
	}) : null;

	function themed() {
		// SweetAlert2 has no dark mode of its own; follow the page.
		var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
		var style = getComputedStyle(document.documentElement);
		return {
			background: dark ? '#212529' : '#fff',
			color: dark ? '#dee2e6' : '#212529',
			confirmButtonColor: style.getPropertyValue('--bs-primary').trim() || '#0d6efd',
			cancelButtonColor: style.getPropertyValue('--bs-secondary').trim() || '#6c757d'
		};
	}

	window.App = {
		toast: function (type, message) {
			if (Toast) { Toast.fire(Object.assign({ icon: type, title: message }, themed())); }
		},
		confirm: function (options) {
			return Swal.fire(Object.assign({
				icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'Cancel', reverseButtons: true
			}, themed(), options || {}));
		}
	};

	// Messages queued server-side with flash('success', '...').
	(window.AppFlash || []).forEach(function (m) {
		window.App.toast(m.type === 'error' ? 'error' : m.type, m.message);
	});

	// <a href="..." data-confirm="Delete this?"> and
	// <form data-confirm="Save changes?"> ask first, then continue.
	// data-confirm-toast="type|message" shows a toast instead of following the link.
	document.addEventListener('click', function (e) {
		var el = e.target.closest('a[data-confirm], button[data-confirm]');
		if (!el || !window.Swal) { return; }
		e.preventDefault();
		window.App.confirm({ title: el.getAttribute('data-confirm'), text: el.getAttribute('data-confirm-text') || undefined }).then(function (r) {
			if (!r.isConfirmed) { return; }
			var toast = el.getAttribute('data-confirm-toast');
			if (toast) { var t = toast.split('|'); window.App.toast(t[0], t.slice(1).join('|')); }
			else if (el.tagName === 'A') { window.location.href = el.href; }
			else if (el.form) { el.form.removeAttribute('data-confirm'); el.form.requestSubmit ? el.form.requestSubmit(el) : el.form.submit(); }
		});
	});
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.hasAttribute || !form.hasAttribute('data-confirm') || !window.Swal) { return; }
		e.preventDefault();
		window.App.confirm({ title: form.getAttribute('data-confirm') }).then(function (r) {
			if (r.isConfirmed) { form.removeAttribute('data-confirm'); form.submit(); }
		});
	});

	// <button data-toast="success|Saved!"> shows a toast on click.
	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-toast]');
		if (!el) { return; }
		var t = el.getAttribute('data-toast').split('|');
		window.App.toast(t[0], t.slice(1).join('|'));
	});

	// ---- Light / dark / auto toggle ----------------------------------
	var icons = { light: 'bi-sun-fill', dark: 'bi-moon-stars-fill', auto: 'bi-circle-half' };

	function currentChoice() {
		try { return localStorage.getItem('app-theme') || 'default'; } catch (e) { return 'default'; }
	}

	function syncToggle() {
		var active = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
		var choice = currentChoice();
		document.querySelectorAll('.theme-icon-active').forEach(function (i) {
			i.className = 'bi theme-icon-active ' + icons[choice === 'default' ? active : choice];
		});
		document.querySelectorAll('[data-theme-choice]').forEach(function (b) {
			b.classList.toggle('active', b.getAttribute('data-theme-choice') === choice);
		});
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-theme-choice]');
		if (!b) { return; }
		var choice = b.getAttribute('data-theme-choice');
		try { localStorage.setItem('app-theme', choice); } catch (err) { /* private mode: applies for this page only */ }
		var dark = choice === 'dark' || (choice === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
		document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
		syncToggle();
	});
	syncToggle();

	// ---- Show / hide a password: <button data-toggle-password="input-id"> ---
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-toggle-password]');
		if (!btn) { return; }
		var input = document.getElementById(btn.getAttribute('data-toggle-password'));
		if (!input) { return; }
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		btn.setAttribute('aria-pressed', show ? 'true' : 'false');
		btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
		var icon = btn.querySelector('i');
		if (icon) { icon.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye'); }
		input.focus();
	});

	// ---- Admin sidebar (phones and tablets: off-canvas drawer) ---------
	function setSidebar(open) {
		var sidebar = document.getElementById('admin-sidebar');
		if (!sidebar) { return; }
		sidebar.classList.toggle('show', open);
		document.body.classList.toggle('sidebar-open', open);
		var backdrop = document.querySelector('.sidebar-backdrop');
		if (backdrop) { backdrop.classList.toggle('show', open); }
		document.querySelectorAll('button[data-sidebar-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
	}
	document.addEventListener('click', function (e) {
		var sidebar = document.getElementById('admin-sidebar');
		if (!sidebar) { return; }
		if (e.target.closest('[data-sidebar-toggle]')) { setSidebar(!sidebar.classList.contains('show')); }
		else if (sidebar.classList.contains('show') && (e.target.closest('#admin-sidebar a[href]:not([data-bs-toggle])') || !e.target.closest('#admin-sidebar'))) { setSidebar(false); }
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setSidebar(false); } });
	// Resizing up to desktop closes the drawer so it can't stay "open" off screen.
	window.matchMedia('(min-width: 992px)').addEventListener('change', function (e) { if (e.matches) { setSidebar(false); } });
})();
