/*
 * Renders Cloudflare Turnstile widgets (<div data-turnstile-widget ...>).
 * Loaded before Cloudflare's api.js, which calls onTurnstileLoad when ready.
 * Explicit rendering lets "auto" follow the site's current light/dark mode
 * rather than only the operating system's.
 */
window.onTurnstileLoad = function () {
	'use strict';
	document.querySelectorAll('[data-turnstile-widget]').forEach(function (el) {
		if (el.getAttribute('data-rendered')) { return; }
		el.setAttribute('data-rendered', '1');

		var theme = el.getAttribute('data-theme');
		if (theme !== 'light' && theme !== 'dark') {
			theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
		}

		window.turnstile.render(el, {
			sitekey: el.getAttribute('data-sitekey'),
			theme: theme,
			size: el.getAttribute('data-size') || 'normal'
		});
	});
};
