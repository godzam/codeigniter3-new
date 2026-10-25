<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * New / edit page: the meta fields plus the section layout.
 *
 * Sections are posted as indexed fields (sections[0][settings][heading]);
 * assets/js/pages.js adds, moves and renumbers them, and keeps everything
 * working as a plain list of fields without JavaScript.
 *
 * @var array<string, mixed>|null $page
 * @var array<string, mixed>      $values
 * @var array<string, string>     $errors
 * @var array{labels: array<string,string>, fields: array<string, array<int, array<string,mixed>>>} $catalog
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$invalid = static function ($key) use ($errors) { return isset($errors[$key]) ? ' is-invalid' : ''; };
$error = static function ($key) use ($errors, $e) { return isset($errors[$key]) ? '<div class="invalid-feedback d-block">'.$e($errors[$key]).'</div>' : ''; };

/** One setting as a form field. */
$field = static function (array $f, $index, $value, $e) {
	$name = 'sections['.(int) $index.'][settings]['.$f['name'].']';
	$help = !empty($f['help']) ? '<div class="form-text">'.$e($f['help']).'</div>' : '';
	$label = '<label class="form-label small mb-1">'.$e($f['label']).'</label>';

	if ($f['type'] === 'textarea') {
		$rows = (int) ($f['rows'] ?? 4);

		return '<div class="col-12 mb-2">'.$label
			.'<textarea class="form-control" name="'.$e($name).'" rows="'.$rows.'" data-field="'.$e($f['name']).'">'.$e($value).'</textarea>'.$help.'</div>';
	}
	if ($f['type'] === 'color') {
		$v = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $value) ? (string) $value : '';

		return '<div class="col-12 col-sm-6 mb-2">'.$label
			.'<input type="color" class="form-control form-control-color" name="'.$e($name).'" value="'.$e($v !== '' ? $v : '#ffffff').'" data-field="'.$e($f['name']).'">'.$help.'</div>';
	}

	return '<div class="col-12 mb-2">'.$label
		.'<input type="text" class="form-control" name="'.$e($name).'" value="'.$e($value).'" maxlength="2000" data-field="'.$e($f['name']).'">'.$help.'</div>';
};

$section_card = static function ($index, $type, array $settings, $labels, $fields, $field, $e) {
	$out = '<div class="card mb-2 page-section" data-index="'.(int) $index.'" data-type="'.$e($type).'">';
	$out .= '<div class="card-header d-flex align-items-center gap-2 py-2">';
	$out .= '<i class="bi bi-grip-vertical text-body-secondary" aria-hidden="true"></i>';
	$out .= '<span class="fw-semibold small">'.$e(isset($labels[$type]) ? $labels[$type] : $type).'</span>';
	$out .= '<input type="hidden" name="sections['.(int) $index.'][type]" value="'.$e($type).'" data-role="type">';
	$out .= '<span class="ms-auto d-flex gap-1">';
	$out .= '<button type="button" class="btn btn-sm btn-outline-secondary section-up" aria-label="Move up" title="Move up"><i class="bi bi-arrow-up"></i></button>';
	$out .= '<button type="button" class="btn btn-sm btn-outline-secondary section-down" aria-label="Move down" title="Move down"><i class="bi bi-arrow-down"></i></button>';
	$out .= '<button type="button" class="btn btn-sm btn-outline-danger section-remove" aria-label="Remove" title="Remove"><i class="bi bi-trash"></i></button>';
	$out .= '</span></div><div class="card-body"><div class="row">';
	foreach ($fields[$type] ?? [] as $f) {
		$out .= $field($f, $index, (string) ($settings[$f['name']] ?? ''), $e);
	}
	$out .= '</div></div></div>';

	return $out;
};
?>
<?php if ($errors !== []): ?>
	<div class="alert alert-danger"><strong>Please fix the following:</strong>
		<ul class="mb-0"><?php foreach ($errors as $msg): ?><li><?php echo $e($msg) ?></li><?php endforeach ?></ul>
	</div>
<?php endif ?>

<?php echo form_open($page === null ? 'admin/pages/create' : 'admin/pages/edit/'.(int) $page['id'], ['id' => 'page-form']) ?>

	<div class="card mb-3">
		<div class="card-body p-3 p-sm-4">
			<div class="row">
				<div class="col-12 col-sm-8 mb-3">
					<label for="p_title" class="form-label">Title</label>
					<input type="text" class="form-control<?php echo $invalid('title') ?>" id="p_title" name="title" value="<?php echo $e($values['title'] ?? '') ?>" maxlength="190" required>
					<?php echo $error('title') ?>
				</div>
				<div class="col-12 col-sm-4 mb-3">
					<label for="p_status" class="form-label">Status</label>
					<select class="form-select" id="p_status" name="status">
						<option value="draft"<?php echo ($values['status'] ?? 'draft') === 'draft' ? ' selected' : '' ?>>Draft (only editors see it)</option>
						<option value="published"<?php echo ($values['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
					</select>
				</div>
				<div class="col-12 col-sm-6 mb-3">
					<label for="p_slug" class="form-label">Address</label>
					<div class="input-group">
						<span class="input-group-text">/page/</span>
						<input type="text" class="form-control<?php echo $invalid('slug') ?>" id="p_slug" name="slug" value="<?php echo $e($values['slug'] ?? '') ?>" maxlength="150" placeholder="from the title" spellcheck="false">
					</div>
					<?php echo $error('slug') ?>
					<div class="form-text">Leave empty to build one from the title.</div>
				</div>
				<div class="col-12 col-sm-6 mb-3">
					<label for="p_meta" class="form-label">Search description</label>
					<input type="text" class="form-control<?php echo $invalid('meta_description') ?>" id="p_meta" name="meta_description" value="<?php echo $e($values['meta_description'] ?? '') ?>" maxlength="300">
					<?php echo $error('meta_description') ?>
					<div class="form-text">Shown by Google below the link. Optional.</div>
				</div>
			</div>
		</div>
	</div>

	<div class="card mb-3">
		<div class="card-header d-flex flex-wrap align-items-center gap-2 py-2">
			<strong>Layout</strong>
			<span class="text-body-secondary small">Sections run top to bottom.</span>
			<span class="ms-auto d-flex gap-2 align-items-center">
				<select class="form-select form-select-sm w-auto" id="page-add-type" aria-label="Section type">
					<?php foreach ($catalog['labels'] as $type => $label): ?>
						<option value="<?php echo $e($type) ?>"><?php echo $e($label) ?></option>
					<?php endforeach ?>
				</select>
				<button type="button" class="btn btn-sm btn-primary" id="page-add"><i class="bi bi-plus-lg me-1"></i>Add section</button>
			</span>
		</div>
		<div class="card-body p-3 p-sm-4">
			<?php echo $error('sections') ?>
			<div id="page-sections">
				<?php
				$sections = $values['sections'] ?? [];
				if ($sections === [] && isset($errors['sections'])) {
					$sections = [];
				}
				foreach (array_values($sections) as $i => $section) {
					echo $section_card($i, (string) ($section['type'] ?? ''), (array) ($section['settings'] ?? []), $catalog['labels'], $catalog['fields'], $field, $e);
				}
				?>
			</div>
			<p class="text-body-secondary small mb-0" id="page-sections-empty" <?php echo ($values['sections'] ?? []) !== [] ? 'hidden' : '' ?>>
				No sections yet — pick a type above and press “Add section”.
			</p>
			<noscript><p class="alert alert-warning small mt-3 mb-0">Adding, moving and removing sections needs JavaScript; the fields above still save as they are.</p></noscript>
		</div>
	</div>

	<div class="d-flex gap-2 mb-4">
		<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
		<a href="<?php echo site_url('admin/pages') ?>" class="btn btn-outline-secondary">Cancel</a>
		<?php if (!empty($page['slug'])): ?>
			<a class="btn btn-outline-secondary ms-auto" href="<?php echo site_url('page/'.$page['slug']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open page</a>
		<?php endif ?>
	</div>
<?php echo form_close() ?>

<script type="application/json" id="page-catalog"><?php echo json_encode(['labels' => $catalog['labels'], 'fields' => $catalog['fields']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
