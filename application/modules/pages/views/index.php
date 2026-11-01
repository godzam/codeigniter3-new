<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * Admin -> Pages: the list (a server-side DataTable, see Pages::data()).
 *
 * @var bool $available the pages table exists (migrations run)
 * @var bool $can_create
 */
$columns = [
	['Title', ['priority' => 1]],
	['Address', ['priority' => 3]],
	['Sections', ['priority' => 5, 'orderable' => false, 'class' => 'text-center']],
	['Status', ['priority' => 2]],
	['Updated', ['priority' => 4, 'orderable' => true]],
	['', ['priority' => 1, 'orderable' => false, 'class' => 'text-end text-nowrap']],
];
?>
<?php if (!$available): ?>
	<div class="alert alert-info">
		<i class="bi bi-info-circle me-1"></i>The pages table does not exist yet. Run <code>php index.php console migrate</code> to build it.
	</div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
	<p class="text-body-secondary small mb-0">Every page lives at <code>/page/{address}</code> and can be linked from Admin → Menu builder.</p>
	<?php if ($can_create && $available): ?>
		<a href="<?php echo site_url('admin/pages/create') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>New page</a>
	<?php endif ?>
</div>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<?php echo datatable_table('pages-table', 'admin/pages/data', $columns, ['order' => [4, 'desc'], 'empty' => 'No pages yet.']) ?>
	</div>
</div>
