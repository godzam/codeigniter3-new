<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<int, array<string, mixed>> $roles
 * @var bool $can_create
 * @var bool $can_edit
 * @var bool $can_delete
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<p class="text-body-secondary mb-0">Roles decide what a user can do. Give each user one role on the <a href="<?php echo site_url('admin/users') ?>">Users</a> page.</p>
	<?php if ($can_create): ?>
		<a href="<?php echo site_url('admin/roles/create') ?>" class="btn btn-primary text-nowrap ms-3"><i class="bi bi-plus-lg me-1"></i>New role</a>
	<?php endif ?>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead>
				<tr><th>Role</th><th>Key</th><th class="text-center">Users</th><th class="text-center">Permissions</th><th class="text-end">Actions</th></tr>
			</thead>
			<tbody>
			<?php foreach ($roles as $r): ?>
				<?php $locked = (bool) $r['is_system']; ?>
				<tr>
					<td>
						<span class="fw-semibold"><?php echo $e($r['name']) ?></span>
						<?php if ($locked): ?><span class="badge text-bg-danger ms-1"><i class="bi bi-lock-fill"></i> Built in</span><?php endif ?>
						<?php if ($r['is_default']): ?><span class="badge text-bg-primary ms-1">Default for sign-ups</span><?php endif ?>
						<?php if (!empty($r['description'])): ?><div class="small text-body-secondary"><?php echo $e($r['description']) ?></div><?php endif ?>
					</td>
					<td><code><?php echo $e($r['slug']) ?></code></td>
					<td class="text-center"><?php echo (int) $r['users_count'] ?></td>
					<td class="text-center"><?php echo $locked ? 'All' : (int) $r['permissions_count'] ?></td>
					<td class="text-end text-nowrap">
						<a href="<?php echo site_url('admin/roles/edit/'.rawurlencode($r['slug'])) ?>" class="btn btn-sm btn-outline-secondary">
							<?php echo ($locked || !$can_edit) ? 'View' : 'Edit' ?>
						</a>
						<?php if ($can_delete && !$locked): ?>
							<?php echo form_open('admin/roles/delete/'.rawurlencode($r['slug']), ['class' => 'd-inline', 'data-confirm' => 'Delete the role "'.$r['name'].'"?']) ?>
								<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
							<?php echo form_close() ?>
						<?php endif ?>
					</td>
				</tr>
			<?php endforeach ?>
			</tbody>
		</table>
	</div>
</div>
