<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<string, array> $groups  from the settings schema
 * @var array<string, mixed> $values  current (or just-submitted) values
 * @var array<string, string> $errors field => message
 * @var bool $available               whether the settings table exists
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$first_error_group = null;
foreach ($groups as $key => $group) {
	if (array_intersect_key($errors, $group['fields'])) { $first_error_group = $key; break; }
}
$active = $first_error_group ?: array_key_first($groups);
?>
<?php if (!$available): ?>
	<div class="alert alert-warning">
		<i class="bi bi-exclamation-triangle me-1"></i>
		The <code>settings</code> table isn't available, so changes can't be saved yet. Configure the database, then run
		<code>php index.php console migrate</code>.
	</div>
<?php endif ?>

<div class="row g-4">
	<div class="col-lg-8">
		<?php echo form_open('admin/settings', ['id' => 'settings-form']) ?>
			<ul class="nav nav-tabs" role="tablist">
				<?php foreach ($groups as $key => $group): ?>
					<li class="nav-item" role="presentation">
						<button type="button" class="nav-link<?php echo $key === $active ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?php echo $e($key) ?>" role="tab">
							<i class="bi <?php echo $e($group['icon'] ?? 'bi-gear') ?> me-1"></i><?php echo $e($group['label']) ?>
							<?php if (array_intersect_key($errors, $group['fields'])): ?><span class="badge text-bg-danger ms-1">!</span><?php endif ?>
						</button>
					</li>
				<?php endforeach ?>
			</ul>

			<div class="tab-content border border-top-0 rounded-bottom p-4 bg-body">
				<?php foreach ($groups as $key => $group): ?>
					<div class="tab-pane fade<?php echo $key === $active ? ' show active' : '' ?>" id="tab-<?php echo $e($key) ?>" role="tabpanel">
						<?php foreach ($group['fields'] as $name => $field): ?>
							<?php
							$id = 'f-'.$name;
							$input_name = 'settings['.$name.']';
							$value = $values[$name] ?? $field['default'];
							$error = $errors[$name] ?? null;
							$invalid = $error ? ' is-invalid' : '';
							?>
							<div class="mb-3">
								<?php if ($field['type'] === 'switch'): ?>
									<div class="form-check form-switch">
										<input class="form-check-input<?php echo $invalid ?>" type="checkbox" role="switch" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" value="1"<?php echo $value ? ' checked' : '' ?>>
										<label class="form-check-label" for="<?php echo $e($id) ?>"><?php echo $e($field['label']) ?></label>
									</div>
								<?php else: ?>
									<label class="form-label" for="<?php echo $e($id) ?>"><?php echo $e($field['label']) ?></label>

									<?php if ($field['type'] === 'color'): ?>
										<div class="settings-color">
											<input type="color" class="form-control form-control-color" id="<?php echo $e($id) ?>" value="<?php echo $e($value) ?>" data-color-picker="<?php echo $e($name) ?>" aria-label="<?php echo $e($field['label']) ?>">
											<input type="text" class="form-control font-monospace<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" value="<?php echo $e($value) ?>" maxlength="7" data-color-text="<?php echo $e($name) ?>" style="max-width:9rem" spellcheck="false">
										</div>
									<?php elseif ($field['type'] === 'select'): ?>
										<select class="form-select<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" data-preview="<?php echo $e($name) ?>">
											<?php foreach ($field['options'] as $opt => $label): ?>
												<option value="<?php echo $e($opt) ?>"<?php echo (string) $value === (string) $opt ? ' selected' : '' ?>><?php echo $e($label) ?></option>
											<?php endforeach ?>
										</select>
									<?php elseif ($field['type'] === 'textarea'): ?>
										<textarea class="form-control<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" rows="3"><?php echo $e($value) ?></textarea>
									<?php elseif ($field['type'] === 'password'): ?>
										<input type="password" class="form-control<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" autocomplete="new-password" placeholder="<?php echo $value !== '' ? '•••••••• (leave blank to keep)' : '' ?>">
									<?php elseif ($field['type'] === 'number'): ?>
										<input type="number" class="form-control<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" value="<?php echo $e($value) ?>"<?php echo isset($field['min']) ? ' min="'.$e($field['min']).'"' : '' ?><?php echo isset($field['max']) ? ' max="'.$e($field['max']).'"' : '' ?>>
									<?php else: ?>
										<input type="text" class="form-control<?php echo $invalid ?>" name="<?php echo $e($input_name) ?>" id="<?php echo $e($id) ?>" value="<?php echo $e($value) ?>">
									<?php endif ?>
								<?php endif ?>

								<?php if ($error): ?><div class="invalid-feedback d-block"><?php echo $e($error) ?></div><?php endif ?>
								<?php if (!empty($field['help'])): ?><div class="form-text"><?php echo $e($field['help']) ?></div><?php endif ?>
							</div>
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
			</div>

		<?php echo form_close() ?>

		<div class="d-flex gap-2 mt-3">
			<button type="submit" form="settings-form" class="btn btn-primary"<?php echo $available ? '' : ' disabled' ?>><i class="bi bi-check-lg me-1"></i>Save settings</button>
			<?php echo form_open('admin/settings/reset', ['data-confirm' => 'Reset every setting to its default?']) ?>
				<button type="submit" class="btn btn-outline-secondary"<?php echo $available ? '' : ' disabled' ?>>Reset to defaults</button>
			<?php echo form_close() ?>
		</div>
	</div>

	<div class="col-lg-4">
		<div class="card position-sticky" style="top:1rem">
			<div class="card-header">Live preview</div>
			<div class="card-body theme-preview" id="theme-preview" data-bs-theme="light">
				<div class="bg-body text-body rounded p-3 border">
					<div class="mb-3">
						<button type="button" class="btn btn-primary">Primary</button>
						<button type="button" class="btn btn-secondary">Secondary</button>
						<button type="button" class="btn btn-outline-primary">Outline</button>
					</div>
					<div class="mb-3">
						<span class="badge text-bg-primary">Primary</span>
						<span class="badge text-bg-secondary">Secondary</span>
						<a href="#">A link</a>
					</div>
					<div class="alert alert-primary py-2 mb-3">A primary alert</div>
					<input type="text" class="form-control mb-3" placeholder="Focus me to see the ring">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" checked id="preview-switch">
						<label class="form-check-label" for="preview-switch">A switch</label>
					</div>
				</div>
				<p class="small text-body-secondary mt-3 mb-0">Updates as you change the colors, mode, corners, and font. Nothing is saved until you press <strong>Save settings</strong>.</p>
			</div>
		</div>
	</div>
</div>
