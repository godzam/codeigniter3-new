/*
 * Live preview for Settings → Appearance. Mirrors App\Theme\Color
 * (application/src/Theme/Color.php) so the preview card matches what the
 * server will generate once the settings are saved. Only #theme-preview is
 * touched; the rest of the page keeps the saved theme until you save.
 */
(function () {
	'use strict';

	var preview = document.getElementById('theme-preview');
	if (!preview) { return; }

	function normalize(hex) {
		hex = String(hex || '').trim().replace(/^#/, '').toLowerCase();
		if (/^[0-9a-f]{3}$/.test(hex)) { hex = hex.replace(/(.)/g, '$1$1'); }
		return /^[0-9a-f]{6}$/.test(hex) ? '#' + hex : null;
	}
	function rgb(hex) { var n = parseInt(hex.slice(1), 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; }
	function toHex(c) { return '#' + c.map(function (v) { v = Math.max(0, Math.min(255, Math.round(v))); return (v < 16 ? '0' : '') + v.toString(16); }).join(''); }
	function mix(a, b, w) { var x = rgb(a), y = rgb(b); return toHex([0, 1, 2].map(function (i) { return x[i] + (y[i] - x[i]) * w; })); }
	function shade(h, w) { return mix(h, '#000000', w); }
	function tint(h, w) { return mix(h, '#ffffff', w); }
	function lum(h) { var c = rgb(h).map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; }
	function ratio(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
	function contrast(h) { var w = ratio(h, '#ffffff'); return w >= 4.5 ? '#ffffff' : (ratio(h, '#000000') > w ? '#000000' : '#ffffff'); }

	function palette(name, hex, dark) {
		var base = hex;
		if (dark) { while (ratio(base, '#212529') < 4.5 && base !== '#ffffff') { base = tint(base, 0.15); } }
		var v = {};
		v['--bs-' + name] = base;
		v['--bs-' + name + '-rgb'] = rgb(base).join(', ');
		v['--bs-' + name + '-text-emphasis'] = dark ? tint(base, 0.4) : shade(base, 0.6);
		v['--bs-' + name + '-bg-subtle'] = dark ? shade(base, 0.8) : tint(base, 0.8);
		v['--bs-' + name + '-border-subtle'] = dark ? shade(base, 0.6) : tint(base, 0.6);
		var hover = dark ? tint(base, 0.15) : shade(base, 0.15);
		v['--app-' + name + '-hover'] = hover;
		v['--app-' + name + '-active'] = dark ? tint(base, 0.2) : shade(base, 0.2);
		v['--app-' + name + '-contrast'] = contrast(base);
		v['--app-' + name + '-hover-contrast'] = contrast(hover);
		return v;
	}

	var radii = {
		none: ['0', '0', '0', '0', '0'],
		sm: ['.2rem', '.15rem', '.3rem', '.4rem', '.6rem'],
		md: ['.375rem', '.25rem', '.5rem', '1rem', '2rem'],
		lg: ['.6rem', '.4rem', '.9rem', '1.3rem', '2rem']
	};
	var fonts = {
		system: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif',
		serif: 'Georgia, Cambria, "Times New Roman", Times, serif',
		mono: 'SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace'
	};

	function field(name) { return document.querySelector('[name="settings[' + name + ']"]'); }

	function update() {
		var mode = field('theme_mode') ? field('theme_mode').value : 'auto';
		var dark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
		preview.setAttribute('data-bs-theme', dark ? 'dark' : 'light');

		var vars = {};
		['primary', 'secondary'].forEach(function (name) {
			var f = field('theme_' + name);
			var hex = f && normalize(f.value);
			if (hex) { Object.assign(vars, palette(name, hex, dark)); }
		});
		if (vars['--bs-primary']) {
			vars['--bs-link-color'] = vars['--bs-primary'];
			vars['--bs-link-color-rgb'] = vars['--bs-primary-rgb'];
			vars['--bs-link-hover-color'] = vars['--app-primary-hover'];
		}

		var r = radii[field('theme_radius') ? field('theme_radius').value : 'md'] || radii.md;
		['', '-sm', '-lg', '-xl', '-xxl'].forEach(function (suffix, i) { vars['--bs-border-radius' + suffix] = r[i]; });
		vars['--bs-body-font-family'] = fonts[field('theme_font') ? field('theme_font').value : 'system'] || fonts.system;

		Object.keys(vars).forEach(function (k) { preview.style.setProperty(k, vars[k]); });
		preview.style.fontFamily = vars['--bs-body-font-family'];
	}

	// Keep each color picker and its hex text box in sync.
	document.querySelectorAll('[data-color-picker]').forEach(function (picker) {
		var text = document.querySelector('[data-color-text="' + picker.getAttribute('data-color-picker') + '"]');
		picker.addEventListener('input', function () { text.value = picker.value; update(); });
		text.addEventListener('input', function () {
			var hex = normalize(text.value);
			if (hex) { picker.value = hex; }
			update();
		});
	});
	document.getElementById('settings-form').addEventListener('input', update);
	document.getElementById('settings-form').addEventListener('change', update);
	update();
})();
