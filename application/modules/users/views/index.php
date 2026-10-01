<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** @var bool $can_assign */
?>
<div class="card">
	<div class="card-body p-2 p-sm-3">
		<?php echo datatable_table('users-table', 'admin/users/data', [
			['Name', ['priority' => 1]],
			['Email', ['priority' => 3]],
			['Role', ['priority' => 2, 'class' => 'users-role-cell']],
			['Joined', ['priority' => 4, 'class' => 'text-nowrap']],
		], ['empty' => 'No users yet.']) ?>
	</div>
</div>
<?php if (!$can_assign): ?>
	<p class="small text-body-secondary mt-2">You can see users but not change their roles.</p>
<?php endif ?>
