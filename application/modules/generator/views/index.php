<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-end mb-3">
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
