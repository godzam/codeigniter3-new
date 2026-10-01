<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
	<p class="text-body-secondary mb-0">Define a table and its fields; get a list, an add/edit dialog, delete, permissions and a menu entry. Developers only.</p>
	<a href="<?php echo site_url('admin/generator/create') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>New module</a>
</div>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<?php echo datatable_table('generator-table', 'admin/generator/data', [
			['Module', ['priority' => 1]],
			['Key', ['priority' => 4]],
			['Table', ['priority' => 5]],
			['Fields', ['priority' => 6, 'orderable' => false, 'class' => 'text-center']],
			['', ['priority' => 2, 'orderable' => false, 'class' => 'text-end text-nowrap']],
		], ['empty' => 'No modules yet. Create one to get started.']) ?>
	</div>
</div>
