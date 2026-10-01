<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var bool $can_create
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<p class="text-body-secondary mb-0">Roles decide what a user can do. Give each user one role on the <a href="<?php echo site_url('admin/users') ?>">Users</a> page.</p>
	<?php if ($can_create): ?>
		<a href="<?php echo site_url('admin/roles/create') ?>" class="btn btn-primary text-nowrap ms-3"><i class="bi bi-plus-lg me-1"></i>New role</a>
	<?php endif ?>
</div>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<?php echo datatable_table('roles-table', 'admin/roles/data', [
			['Role', ['priority' => 1]],
			['Key', ['priority' => 4]],
			['Users', ['priority' => 5, 'class' => 'text-center']],
			['Permissions', ['priority' => 6, 'orderable' => false, 'class' => 'text-center']],
			['Actions', ['priority' => 2, 'orderable' => false, 'class' => 'text-end text-nowrap']],
		], ['empty' => 'No roles yet.']) ?>
	</div>
</div>
