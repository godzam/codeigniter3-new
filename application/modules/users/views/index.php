<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<int, array<string, mixed>> $users
 * @var array<int, array<string, mixed>> $roles      roles the current user may hand out
 * @var bool $can_assign
 * @var int $my_id
 * @var bool $viewer_is_super
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$names = [];
foreach ($roles as $r) { $names[$r['slug']] = $r['name']; }
?>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead>
				<tr><th>Name</th><th>Email</th><th style="min-width:16rem">Role</th><th class="text-end">Joined</th></tr>
			</thead>
			<tbody>
			<?php foreach ($users as $u): ?>
				<?php
				$is_super = $u['role'] === 'super_admin';
				// Only a super admin can change a super admin's role, or their own.
				$row_locked = !$viewer_is_super && ($is_super || (int) $u['id'] === $my_id);
				?>
				<tr>
					<td>
						<?php echo $e($u['name']) ?>
						<?php if ((int) $u['id'] === $my_id): ?><span class="badge text-bg-secondary ms-1">you</span><?php endif ?>
					</td>
					<td class="text-body-secondary"><?php echo $e($u['email']) ?></td>
					<td>
						<?php if ($can_assign && !$row_locked): ?>
							<?php echo form_open('admin/users/role/'.(int) $u['id'], ['class' => 'd-flex gap-2']) ?>
								<select name="role" class="form-select form-select-sm" aria-label="Role for <?php echo $e($u['name']) ?>">
									<?php if (!isset($names[$u['role']])): ?>
										<option value="<?php echo $e($u['role']) ?>" selected><?php echo $e($u['role']) ?></option>
									<?php endif ?>
									<?php foreach ($roles as $r): ?>
										<option value="<?php echo $e($r['slug']) ?>"<?php echo $r['slug'] === $u['role'] ? ' selected' : '' ?>><?php echo $e($r['name']) ?></option>
									<?php endforeach ?>
								</select>
								<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
							<?php echo form_close() ?>
						<?php else: ?>
							<span class="badge text-bg-<?php echo $is_super ? 'danger' : 'secondary' ?>"><?php echo $e($names[$u['role']] ?? $u['role']) ?></span>
							<?php if ($can_assign && $row_locked): ?><i class="bi bi-lock-fill text-body-secondary ms-1" title="You cannot change this role"></i><?php endif ?>
						<?php endif ?>
					</td>
					<td class="text-end text-body-secondary small"><?php echo $e(substr((string) $u['created_at'], 0, 10)) ?></td>
				</tr>
			<?php endforeach ?>
			</tbody>
		</table>
	</div>
</div>
<?php if (!$can_assign): ?>
	<p class="small text-body-secondary mt-2">You can see users but not change their roles.</p>
<?php endif ?>
