<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * New / edit menu item.
 *
 * @var array<string, mixed>|null $item
 * @var array<string, mixed>      $values
 * @var array<string, string>     $errors
 * @var array<int, array<string, mixed>> $parents
 * @var array<int, string>        $permissions
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$invalid = static function ($key) use ($errors) { return isset($errors[$key]) ? ' is-invalid' : ''; };
$error = static function ($key) use ($errors, $e) { return isset($errors[$key]) ? '<div class="invalid-feedback d-block">'.$e($errors[$key]).'</div>' : ''; };
$type = (string) ($values['type'] ?? 'link');
?>
<div class="row justify-content-center">
	<div class="col-12 col-lg-8">
		<div class="card">
			<div class="card-body p-3 p-sm-4">
				<?php echo form_open($item === null ? 'admin/menus/create' : 'admin/menus/edit/'.(int) $item['id']) ?>

					<div class="mb-3">
						<label for="m_type" class="form-label">Kind</label>
						<select class="form-select<?php echo $invalid('type') ?>" id="m_type" name="type">
							<option value="link"<?php echo !in_array($type, ['section', 'dropdown'], true) ? ' selected' : '' ?>>Link (goes somewhere)</option>
							<option value="dropdown"<?php echo $type === 'dropdown' ? ' selected' : '' ?>>Dropdown (opens a list of links, has no address of its own)</option>
							<option value="section"<?php echo $type === 'section' ? ' selected' : '' ?>>Section heading (a label above items)</option>
						</select>
						<?php echo $error('type') ?>
						<div class="form-text" id="m_type_help">To fill a dropdown, add links and choose the dropdown under "Inside". A link that has items inside it also opens them, and then its own address is not used.</div>
					</div>

					<div class="mb-3">
						<label for="m_label" class="form-label">Label</label>
						<input type="text" class="form-control<?php echo $invalid('label') ?>" id="m_label" name="label" value="<?php echo $e($values['label'] ?? '') ?>" maxlength="120" required>
						<?php echo $error('label') ?>
					</div>

					<div class="row">
						<div class="col-sm-6 mb-3" data-show-for="link">
							<label for="m_url" class="form-label">URL</label>
							<input type="text" class="form-control<?php echo $invalid('url') ?>" id="m_url" name="url" value="<?php echo $e($values['url'] ?? '') ?>" maxlength="255" placeholder="dashboard or https://…">
							<?php echo $error('url') ?>
							<div class="form-text">Relative URLs stay on this site; <code>page/your-slug</code> opens a page made in Admin → Pages.</div>
						</div>
						<div class="col-sm-6 mb-3" data-show-for="link dropdown">
							<label for="m_icon" class="form-label">Icon</label>
							<input type="text" class="form-control<?php echo $invalid('icon') ?>" id="m_icon" name="icon" value="<?php echo $e($values['icon'] ?? '') ?>" maxlength="60" placeholder="bi-star" autocomplete="off" spellcheck="false" data-icon-picker="<?php echo $e(asset_url('vendor/bootstrap-icons/icons.json')) ?>">
							<div class="form-text">Press <strong>Choose</strong> to pick from all Bootstrap Icons, or type a class such as <code>bi-star</code>.</div>
						</div>
					</div>

					<div class="row" data-show-for="link dropdown">
						<div class="col-sm-6 mb-3">
							<label for="m_parent" class="form-label">Inside</label>
							<select class="form-select<?php echo $invalid('parent_id') ?>" id="m_parent" name="parent_id">
								<option value="0">— Top level —</option>
							<?php foreach ($parents as $p): ?>
								<option value="<?php echo (int) $p['id'] ?>"<?php echo (int) ($values['parent_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>>
										<?php echo str_repeat('— ', (int) $p['depth']).$e($p['label']) ?>
									</option>
								<?php endforeach ?>
							</select>
							<?php echo $error('parent_id') ?>
							<div class="form-text">Up to three levels deep. A heading (section) is always top level.</div>
						</div>
						<div class="col-sm-6 mb-3">
							<label for="m_permission" class="form-label">Needs permission</label>
							<select class="form-select<?php echo $invalid('permission') ?>" id="m_permission" name="permission">
								<option value="">— Anyone signed in —</option>
								<?php foreach ($permissions as $key): ?>
									<option value="<?php echo $e($key) ?>"<?php echo (string) ($values['permission'] ?? '') === $key ? ' selected' : '' ?>><?php echo $e($key) ?></option>
								<?php endforeach ?>
								<?php if (isset($values['permission']) && $values['permission'] !== '' && !in_array($values['permission'], $permissions, true)): ?>
									<option value="<?php echo $e($values['permission']) ?>" selected><?php echo $e($values['permission']) ?> (unknown)</option>
								<?php endif ?>
							</select>
							<?php echo $error('permission') ?>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-6 mb-3" data-show-for="link dropdown">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" value="1" id="m_super" name="super_admin"<?php echo !empty($values['super_admin']) ? ' checked' : '' ?>>
								<label class="form-check-label" for="m_super">Only for super admins</label>
							</div>
						</div>
						<div class="col-sm-6 mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" value="1" id="m_active" name="is_active"<?php echo !empty($values['is_active']) || !isset($values['is_active']) ? ' checked' : '' ?>>
								<label class="form-check-label" for="m_active">Show in the sidebar</label>
							</div>
						</div>
					</div>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
						<a href="<?php echo site_url('admin/menus') ?>" class="btn btn-outline-secondary">Cancel</a>
					</div>
				<?php echo form_close() ?>
			</div>
		</div>
	</div>
</div>
