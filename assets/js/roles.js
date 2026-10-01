/*
 * Roles form: "select all" per module and overall, a role key suggested from
 * the name (mirrors RoleRules::slugFromName in application/src/Auth/), and
 * the per-group checkbox staying in step with its permissions.
 */
(function () {
	'use strict';

	var form = document.getElementById('role-form');
	if (!form) { return; }

	function boxes(group) {
		return form.querySelectorAll('input[data-perm="' + group + '"]:not(:disabled)');
	}

	function syncGroup(group) {
		var toggle = form.querySelector('input[data-perm-group="' + group + '"]');
		if (!toggle) { return; }
		var all = boxes(group);
		var ticked = Array.prototype.filter.call(all, function (b) { return b.checked; }).length;
		toggle.disabled = all.length === 0;
		toggle.checked = all.length > 0 && ticked === all.length;
		toggle.indeterminate = ticked > 0 && ticked < all.length;
	}

	form.querySelectorAll('input[data-perm-group]').forEach(function (toggle) {
		var group = toggle.getAttribute('data-perm-group');
		syncGroup(group);
		toggle.addEventListener('change', function () {
			boxes(group).forEach(function (b) { b.checked = toggle.checked; });
			syncGroup(group);
		});
	});

	form.querySelectorAll('input[data-perm]').forEach(function (box) {
		box.addEventListener('change', function () { syncGroup(box.getAttribute('data-perm')); });
	});

	form.querySelectorAll('[data-perm-all]').forEach(function (button) {
		button.addEventListener('click', function () {
			var on = button.getAttribute('data-perm-all') === '1';
			form.querySelectorAll('input[data-perm]:not(:disabled)').forEach(function (b) { b.checked = on; });
			form.querySelectorAll('input[data-perm-group]').forEach(function (t) { syncGroup(t.getAttribute('data-perm-group')); });
		});
	});

	// Suggest the key from the name until the user types in the key field.
	var name = document.getElementById('role-name');
	var slug = document.getElementById('role-slug');
	if (name && slug && !slug.disabled) {
		var touched = slug.value !== '';
		slug.addEventListener('input', function () { touched = slug.value !== ''; });
		name.addEventListener('input', function () {
			if (touched) { return; }
			var s = name.value.trim().replace(/[^A-Za-z0-9]+/g, '_').replace(/^_+|_+$/g, '').toLowerCase();
			if (s !== '' && !/^[a-z]/.test(s)) { s = 'role_' + s; }
			slug.value = s.slice(0, 50);
		});
	}
})();
