<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<string, mixed> $module
 * @var bool $can_create
 * @var bool $can_edit
 * @var bool $can_delete
 * @var string $csrf_name
 * @var string $csrf_hash
 */
$slug = $module['slug'];
$columns = [];
$priority = 2;
foreach ($module['fields'] as $f) {
	if (empty($f['list'])) { continue; }
	$plain = !in_array($f['type'], ['image', 'file', 'multiselect'], true);
	$columns[] = [$f['label'], [
		'priority' => count($columns) === 0 ? 1 : ++$priority,
		'orderable' => $plain,
		'class' => $f['type'] === 'number' ? 'text-end' : '',
	]];
}
if (!$columns) { $columns[] = ['ID', ['priority' => 1, 'orderable' => false]]; }
$columns[] = ['', ['priority' => 2, 'orderable' => false, 'class' => 'text-end text-nowrap']];
?>
<div id="crud-root"
	data-base="<?php echo htmlspecialchars(site_url('admin/c/'.$slug), ENT_QUOTES, 'UTF-8') ?>"
	data-csrf-name="<?php echo htmlspecialchars($csrf_name, ENT_QUOTES, 'UTF-8') ?>"
	data-csrf-hash="<?php echo htmlspecialchars($csrf_hash, ENT_QUOTES, 'UTF-8') ?>"
	data-table="crud-table"
	data-singular="<?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') ?>">

	<?php if ($can_create): ?>
		<div class="d-flex justify-content-end mb-3">
			<button type="button" class="btn btn-primary" data-crud-add><i class="bi bi-plus-lg me-1"></i>Add</button>
		</div>
	<?php endif ?>

	<div class="card">
		<div class="card-body p-2 p-sm-3">
			<?php echo datatable_table('crud-table', 'admin/c/'.$slug.'/data', $columns, ['empty' => 'No records yet.']) ?>
		</div>
	</div>
</div>

<div class="modal fade" id="crud-modal" tabindex="-1" aria-labelledby="crud-modal-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title h5" id="crud-modal-title"></h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="crud-modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="submit" form="crud-form" class="btn btn-primary" id="crud-submit"><i class="bi bi-check-lg me-1"></i>Save</button>
			</div>
		</div>
	</div>
</div>
