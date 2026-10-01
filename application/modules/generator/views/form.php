<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<string, mixed>|null $module  stored module when editing
 * @var array<string, mixed> $values       title, slug, table, icon, fields
 * @var array<string, string> $errors      "slug", "fields.2.name", ...
 * @var array<int, string> $locked         existing column names (cannot be renamed or re-typed)
 * @var array<string, string> $types
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$editing = $module !== null;
$invalid = static function ($field) use ($errors) { return isset($errors[$field]) ? ' is-invalid' : ''; };
$config = [
	'fields' => array_values((array) ($values['fields'] ?? [])),
	'locked' => $locked,
	'types' => $types,
	'errors' => $errors,
];
?>
<?php if (isset($errors['form'])): ?><div class="alert alert-danger"><?php echo $e($errors['form']) ?></div><?php endif ?>

<?php echo form_open($editing ? 'admin/generator/edit/'.rawurlencode($module['slug']) : 'admin/generator/create', [
	'id' => 'generator-form',
	'data-config' => $e(json_encode($config, JSON_UNESCAPED_UNICODE)),
]) ?>
	<input type="hidden" name="module_json" id="module-json">

	<div class="card mb-4">
		<div class="card-body">
			<div class="row g-3">
				<div class="col-12 col-md-6">
					<label for="g-title" class="form-label">Title</label>
					<input type="text" class="form-control<?php echo $invalid('title') ?>" id="g-title" maxlength="60" value="<?php echo $e($values['title']) ?>" placeholder="Products" required>
					<?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?php echo $e($errors['title']) ?></div><?php endif ?>
				</div>
				<div class="col-12 col-md-6">
					<label for="g-icon" class="form-label">Menu icon</label>
					<div class="input-group">
						<span class="input-group-text"><i class="bi <?php echo $e($values['icon'] ?: 'bi-table') ?>" id="g-icon-preview"></i></span>
						<input type="text" class="form-control font-monospace<?php echo $invalid('icon') ?>" id="g-icon" maxlength="64" value="<?php echo $e($values['icon']) ?>" placeholder="bi-box-seam" autocomplete="off" spellcheck="false">
						<?php if (isset($errors['icon'])): ?><div class="invalid-feedback"><?php echo $e($errors['icon']) ?></div><?php endif ?>
					</div>
					<div class="form-text"><a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap Icons</a> class name.</div>
				</div>
				<div class="col-12 col-md-6">
					<label for="g-slug" class="form-label">Key</label>
					<input type="text" class="form-control font-monospace<?php echo $invalid('slug') ?>" id="g-slug" maxlength="30" value="<?php echo $e($values['slug']) ?>" placeholder="products" autocomplete="off" spellcheck="false"<?php echo $editing ? ' disabled' : '' ?>>
					<?php if (isset($errors['slug'])): ?><div class="invalid-feedback"><?php echo $e($errors['slug']) ?></div><?php endif ?>
					<div class="form-text">Used in the URL (<code>admin/c/<span id="g-slug-hint"><?php echo $e($values['slug'] ?: 'key') ?></span></code>) and as the permission prefix. <?php echo $editing ? 'Cannot be changed.' : 'Cannot be changed later.' ?></div>
				</div>
				<div class="col-12 col-md-6">
					<label for="g-table" class="form-label">Table name</label>
					<input type="text" class="form-control font-monospace<?php echo $invalid('table') ?>" id="g-table" maxlength="60" value="<?php echo $e($values['table']) ?>" placeholder="same as the key" autocomplete="off" spellcheck="false"<?php echo $editing ? ' disabled' : '' ?>>
					<?php if (isset($errors['table'])): ?><div class="invalid-feedback"><?php echo $e($errors['table']) ?></div><?php endif ?>
					<div class="form-text">Created now with an <code>id</code>, your fields, <code>created_at</code> and <code>updated_at</code>.</div>
				</div>
			</div>
		</div>
	</div>

	<div class="d-flex justify-content-between align-items-center mb-2">
		<h2 class="h5 mb-0">Fields</h2>
		<button type="button" class="btn btn-outline-primary btn-sm" id="g-add"><i class="bi bi-plus-lg me-1"></i>Add field</button>
	</div>
	<?php if (isset($errors['fields'])): ?><div class="alert alert-danger py-2"><?php echo $e($errors['fields']) ?></div><?php endif ?>
	<div id="g-fields" class="vstack gap-3 mb-4"></div>

	<?php if ($editing): ?>
		<p class="small text-body-secondary">Existing fields keep their name and type (the column already exists). New fields add columns. To remove a field or change a type, delete the module and create it again.</p>
	<?php endif ?>

	<div class="d-flex gap-2 pb-3">
		<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?php echo $editing ? 'Save module' : 'Create module' ?></button>
		<a href="<?php echo site_url('admin/generator') ?>" class="btn btn-outline-secondary">Cancel</a>
	</div>
<?php echo form_close() ?>
