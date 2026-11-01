/*
 * Admin -> Menu builder, the add/edit form (assets/js/menu-form.js)
 *
 * Shows only the fields that matter for the chosen kind: a link has an address, a dropdown has
 * none (it only opens its items), a section heading has neither an address, an icon nor a parent.
 */
(function () {
	'use strict';

	var kind = document.getElementById('m_type');
	if (!kind) { return; }

	function update() {
		var current = kind.value;
		document.querySelectorAll('[data-show-for]').forEach(function (el) {
			var visible = el.getAttribute('data-show-for').split(/\s+/).indexOf(current) !== -1;
			el.hidden = !visible;
		});
	}

	kind.addEventListener('change', update);
	update();
})();
