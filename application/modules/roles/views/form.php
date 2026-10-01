<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<string, mixed>|null $role      the stored role, null when creating
 * @var array<string, mixed> $values         what the fields show
 * @var array<int, string> $granted          permission keys currently ticked
 * @var array<string, string> $errors
 * @var bool $locked                         built-in role: shown read-only
 * @var array<string, array> $groups         permission registry groups
 * @var array<int, string> $all_keys         every permission key
 * @var array<int, string> $changeable       keys the signed-in user may tick or untick
 * @var int $users_count
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$creating = $role === null;
$action = $creating ? 'admin/roles/create' : 'admin/roles/edit/'.rawurlencode($role['slug']);
$invalid = static function ($field) use ($errors) { return isset($errors[$field]) ? ' is-invalid' : ''; };
$can_save = !$locked && (($creating && can('roles.create')) || (!$creating && can('roles.edit')));
?>
<?php if ($locked): ?>
	<div class="alert alert-info"><i class="bi bi-lock-fill me-1"></i>The <code>super_admin</code> role is built in. It always has every permission and cannot be edited, deleted or created again.</div>
<?php endif ?>

<?php echo form_open($action, ['id' => 'role-form']) ?>
	<div class="row g-4">
		<div class="col-lg-4">
			<div class="card">
				<div class="card-body">
					<div class="mb-3">
						<label for="role-name" class="form-label">Name</label>
						<input type="text" class="form-control<?php echo $invalid('name') ?>" id="role-name" name="name" maxlength="100" value="<?php echo $e($values['name']) ?>" required<?php echo $can_save ? '' : ' disabled' ?>>
						<?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?php echo $e($errors['name']) ?></div><?php endif ?>
					</div>

					<div class="mb-3">
						<label for="role-slug" class="form-label">Key</label>
						<?php if ($creating): ?>
							<input type="text" class="form-control font-monospace<?php echo $invalid('slug') ?>" id="role-slug" name="slug" maxlength="50" value="<?php echo $e($values['slug']) ?>" placeholder="content_editor" autocomplete="off" spellcheck="false">
							<?php if (isset($errors['slug'])): ?><div class="invalid-feedback"><?php echo $e($errors['slug']) ?></div><?php endif ?>
							<div class="form-text">Lowercase letters, digits and underscores. Filled in from the name; <strong>cannot be changed later</strong> because users are linked to it.</div>
						<?php else: ?>
							<input type="text" class="form-control font-monospace" id="role-slug" value="<?php echo $e($role['slug']) ?>" disabled>
						<?php endif ?>
					</div>

					<div class="mb-3">
						<label for="role-description" class="form-label">Description <span class="text-body-secondary">(optional)</span></label>
						<textarea class="form-control<?php echo $invalid('description') ?>" id="role-description" name="description" rows="3" maxlength="255"<?php echo $can_save ? '' : ' disabled' ?>><?php echo $e($values['description']) ?></textarea>
						<?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?php echo $e($errors['description']) ?></div><?php endif ?>
					</div>

					<?php if (!$locked): ?>
						<?php $is_default = !empty($values['is_default']); ?>
						<div class="form-check form-switch mb-1">
							<input class="form-check-input" type="checkbox" role="switch" id="role-default" name="is_default" value="1"<?php echo $is_default ? ' checked' : '' ?><?php echo ($can_save && !($role !== null && !empty($role['is_default']))) ? '' : ' disabled' ?>>
							<label class="form-check-label" for="role-default">Default role for new sign-ups</label>
						</div>
						<div class="form-text">Only one role is the default. To change it, turn this on for another role.</div>
					<?php endif ?>

					<?php if (!$creating): ?>
						<hr>
						<div class="small text-body-secondary"><?php echo (int) $users_count ?> user<?php echo $users_count === 1 ? '' : 's' ?> with this role.</div>
					<?php endif ?>
				</div>
			</div>
		</div>

		<div class="col-lg-8">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h2 class="h5 mb-0">Permissions</h2>
				<?php if (!$locked): ?>
					<div class="small">
						<button type="button" class="btn btn-link btn-sm p-0" data-perm-all="1">Select all</button> ·
						<button type="button" class="btn btn-link btn-sm p-0" data-perm-all="0">Clear</button>
					</div>
				<?php endif ?>
			</div>
			<?php if (isset($errors['permissions'])): ?><div class="alert alert-danger"><?php echo $e($errors['permissions']) ?></div><?php endif ?>

			<div class="row g-3">
			<?php foreach ($groups as $module => $group): ?>
				<div class="col-md-6">
					<div class="card h-100">
						<div class="card-header d-flex justify-content-between align-items-center">
							<span><i class="bi <?php echo $e($group['icon']) ?> me-1"></i><strong><?php echo $e($group['label']) ?></strong></span>
							<?php if (!$locked): ?>
								<div class="form-check mb-0" title="Select every permission in this group">
									<input class="form-check-input" type="checkbox" data-perm-group="<?php echo $e($module) ?>" id="group-<?php echo $e($module) ?>" aria-label="Select all <?php echo $e($group['label']) ?> permissions">
								</div>
							<?php endif ?>
						</div>
						<ul class="list-group list-group-flush">
						<?php foreach ($group['permissions'] as $action_key => $label): ?>
							<?php
							$key = $module.'.'.$action_key;
							$checked = in_array($key, $granted, true);
							$editable = !$locked && $can_save && in_array($key, $changeable, true);
							?>
							<li class="list-group-item">
								<div class="form-check mb-0">
									<input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $e($key) ?>" id="perm-<?php echo $e($module.'-'.$action_key) ?>" data-perm="<?php echo $e($module) ?>"<?php echo $checked ? ' checked' : '' ?><?php echo $editable ? '' : ' disabled' ?>>
									<label class="form-check-label" for="perm-<?php echo $e($module.'-'.$action_key) ?>">
										<?php echo $e($label) ?>
										<span class="d-block small text-body-secondary font-monospace"><?php echo $e($key) ?></span>
									</label>
								</div>
							</li>
						<?php endforeach ?>
						</ul>
					</div>
				</div>
			<?php endforeach ?>
			</div>

			<?php if (!$locked && $can_save && array_diff($all_keys, $changeable)): ?>
				<p class="small text-body-secondary mt-3"><i class="bi bi-info-circle me-1"></i>Greyed-out permissions are ones you do not have yourself, so you cannot grant or revoke them.</p>
			<?php endif ?>
		</div>
	</div>

	<div class="d-flex gap-2 mt-4">
		<?php if ($can_save): ?>
			<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?php echo $creating ? 'Create role' : 'Save role' ?></button>
		<?php endif ?>
		<a href="<?php echo site_url('admin/roles') ?>" class="btn btn-outline-secondary"><?php echo $can_save ? 'Cancel' : 'Back' ?></a>
	</div>
<?php echo form_close() ?>
