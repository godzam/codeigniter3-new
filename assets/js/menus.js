/*
 * Admin -> Menus (assets/js/menus.js)
 *
 * Drags the sidebar rows into a new order, including nesting: dropping in the
 * middle of a row makes the dragged item its child, dropping on the top or
 * bottom third inserts it before/after that row at the same level. The ↑/↓
 * buttons do the same without dragging. Either way the finished order is sent
 * as [[id, depth], ...] to admin/menus/reorder and saved server-side.
 */
(function () {
	'use strict';

	var list = document.getElementById('menu-builder');
	if (!list) return;

	var status = document.getElementById('menu-builder-status');
	var readonly = list.hasAttribute('data-readonly');
	var indent = 24;
	var maxDepth = 2;
	var dragged = null;
	var saved = null;

	function rows() {
		return Array.prototype.slice.call(list.querySelectorAll('.menu-row'));
	}

	function depthOf(row) {
		return parseInt(row.getAttribute('data-depth') || '0', 10);
	}

	function setDepth(row, depth) {
		depth = Math.max(0, Math.min(maxDepth, depth));
		row.setAttribute('data-depth', depth);
		row.style.marginInlineStart = (depth * indent) + 'px';
	}

	function paint() {
		rows().forEach(function (row) {
			setDepth(row, depthOf(row));
		});
	}

	/** The row plus everything nested under it. */
	function subtree(row) {
		var out = [row];
		var depth = depthOf(row);
		var next = row.nextElementSibling;
		while (next && next.classList.contains('menu-row') && depthOf(next) > depth) {
			out.push(next);
			next = next.nextElementSibling;
		}
		return out;
	}

	/** Whether `ancestor` contains `row` (dropping a parent into its own child). */
	function isAncestor(ancestor, row) {
		var depth = depthOf(row);
		var node = row;
		while (node && node !== list) {
			if (node === ancestor) return true;
			node = node.previousElementSibling;
			while (node && depthOf(node) >= depth && node !== ancestor) {
				node = node.previousElementSibling;
			}
			depth = node ? depthOf(node) : -1;
			if (node === ancestor) return true;
			if (!node || depth < 0) return false;
		}
		return false;
	}

	function message(text, isError) {
		if (!status) return;
		status.textContent = text;
		status.classList.toggle('text-danger', !!isError);
		status.classList.toggle('text-body-secondary', !isError);
	}

	function order() {
		return rows().map(function (row) {
			return [parseInt(row.getAttribute('data-id'), 10), depthOf(row)];
		});
	}

	function save() {
		var next = JSON.stringify(order());
		if (next === saved) return;
		if (readonly) return;
		saved = next;

		var token = document.getElementById('menu-csrf-hash');
		var name = document.getElementById('menu-csrf-name');
		var body = new URLSearchParams();
		body.set(name ? name.value : 'csrf_token', token ? token.value : '');
		body.set('order', next);

		message('Saving…');
		fetch(list.getAttribute('data-reorder-url'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
			body: body.toString()
		}).then(function (r) {
			return r.json().then(function (data) { return { ok: r.ok && data.ok, data: data }; });
		}).then(function (res) {
			message(res.data.message || (res.ok ? 'Menu saved.' : 'Could not save.'), !res.ok);
			if (res.ok) saved = JSON.stringify(order());
		}).catch(function () {
			message('Could not save the menu (network error).', true);
			saved = null;
		});
	}

	// ---------------------------------------------------------------- Drag
	rows().forEach(function (row) {
		row.setAttribute('draggable', readonly ? 'false' : 'true');

		row.addEventListener('dragstart', function (e) {
			dragged = row;
			row.classList.add('dragging');
			if (e.dataTransfer) {
				e.dataTransfer.effectAllowed = 'move';
				try { e.dataTransfer.setData('text/plain', row.getAttribute('data-id')); } catch (err) {}
			}
		});

		row.addEventListener('dragend', function () {
			row.classList.remove('dragging');
			dragged = null;
			save();
		});

		row.addEventListener('dragover', function (e) {
			if (!dragged || dragged === row || isAncestor(dragged, row)) return;
			e.preventDefault();
			if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';

			var box = row.getBoundingClientRect();
			var y = (e.clientY - box.top) / box.height;
			var depth = depthOf(row);

			if (y > 0.33 && y < 0.67) {
				// Onto the row: it becomes the parent.
				row.after(dragged);
				setDepth(dragged, depth + 1);
			} else if (y <= 0.33) {
				row.before(dragged);
				setDepth(dragged, depth);
			} else {
				var block = subtree(row);
				block[block.length - 1].after(dragged);
				setDepth(dragged, depth);
			}
		});

		row.addEventListener('drop', function (e) {
			e.preventDefault();
		});
	});

	list.addEventListener('dragover', function (e) {
		if (dragged && e.target === list) e.preventDefault();
	});
	list.addEventListener('drop', function (e) {
		if (dragged && e.target === list) {
			e.preventDefault();
			list.appendChild(dragged);
			setDepth(dragged, 0);
			save();
		}
	});

	// ------------------------------------------------------------- Buttons
	list.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.menu-up, .menu-down') : null;
		if (!btn || readonly) return;

		var row = btn.closest('.menu-row');
		if (!row) return;

		if (btn.classList.contains('menu-up')) {
			var prev = row.previousElementSibling;
			while (prev && !prev.classList.contains('menu-row')) prev = prev.previousElementSibling;
			if (prev) {
				var block = subtree(prev);
				block[0].before.apply(block[0], subtree(row));
				if (depthOf(prev) !== depthOf(row)) setDepth(row, depthOf(prev));
			}
		} else {
			var next = row.nextElementSibling;
			while (next && !next.classList.contains('menu-row')) next = next.nextElementSibling;
			if (next) {
				var mine = subtree(row);
				// Nesting deeper with ↓ when the next row is deeper already.
				if (depthOf(next) > depthOf(row)) {
					setDepth(row, depthOf(next));
					next.after(mine[0]);
				} else {
					subtree(next)[subtree(next).length - 1].after.apply(mine[0], mine);
					setDepth(row, depthOf(next));
				}
			}
		}

		paint();
		save();
	});

	paint();
	saved = JSON.stringify(order());
})();
