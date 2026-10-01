<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * The add/edit form shown inside the modal (no layout around it).
 *
 * @var array<string, mixed> $module
 * @var array<string, mixed> $row                  stored record, empty for a new one
 * @var array<string, array<string, string>> $choices  field => value => label (dropdowns)
 * @var string $csrf_name
 * @var string $csrf_hash
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$editing = !empty($row['id']);
$size = static function ($kb) { return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB'; };
?>
<form id="crud-form" method="post" action="<?php echo $e(site_url('admin/c/'.$module['slug'].'/save')) ?>" enctype="multipart/form-data" data-crud-form data-title="<?php echo $e(($editing ? 'Edit ' : 'Add ').$module['title']) ?>" novalidate>
	<input type="hidden" name="<?php echo $e($csrf_name) ?>" value="<?php echo $e($csrf_hash) ?>">
	<?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int) $row['id'] ?>"><?php endif ?>

	<?php foreach ($module['fields'] as $f): ?>
		<?php
		$name = $f['name'];
		$id = 'f-'.$name;
		$value = $row[$name] ?? '';
		$required = !empty($f['required']);
		$type = $f['type'];
		?>
		<div class="mb-3" data-field="<?php echo $e($name) ?>">
			<?php if ($type !== 'radio' && $type !== 'multiselect'): ?>
				<label for="<?php echo $e($id) ?>" class="form-label"><?php echo $e($f['label']) ?><?php echo $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
			<?php else: ?>
				<div class="form-label"><?php echo $e($f['label']) ?><?php echo $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></div>
			<?php endif ?>

			<?php if ($type === 'textarea'): ?>
				<textarea class="form-control" id="<?php echo $e($id) ?>" name="<?php echo $e($name) ?>" rows="4" maxlength="<?php echo (int) $f['maxlength'] ?>"><?php echo $e($value) ?></textarea>

			<?php elseif ($type === 'select'): ?>
				<select class="form-select" id="<?php echo $e($id) ?>" name="<?php echo $e($name) ?>">
					<option value="">— Choose —</option>
					<?php foreach ($choices[$name] as $v => $l): ?>
						<option value="<?php echo $e($v) ?>"<?php echo (string) $v === (string) $value ? ' selected' : '' ?>><?php echo $e($l) ?></option>
					<?php endforeach ?>
				</select>

			<?php elseif ($type === 'radio'): ?>
				<?php foreach ($choices[$name] as $v => $l): ?>
					<div class="form-check">
						<input class="form-check-input" type="radio" name="<?php echo $e($name) ?>" id="<?php echo $e($id.'-'.md5((string) $v)) ?>" value="<?php echo $e($v) ?>"<?php echo (string) $v === (string) $value ? ' checked' : '' ?>>
						<label class="form-check-label" for="<?php echo $e($id.'-'.md5((string) $v)) ?>"><?php echo $e($l) ?></label>
					</div>
				<?php endforeach ?>

			<?php elseif ($type === 'multiselect'): ?>
				<?php $picked = array_map('strval', (array) json_decode((string) $value, true)); ?>
				<div class="border rounded p-2 crud-checklist">
					<?php foreach ($choices[$name] as $v => $l): ?>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="<?php echo $e($name) ?>[]" id="<?php echo $e($id.'-'.md5((string) $v)) ?>" value="<?php echo $e($v) ?>"<?php echo in_array((string) $v, $picked, true) ? ' checked' : '' ?>>
							<label class="form-check-label" for="<?php echo $e($id.'-'.md5((string) $v)) ?>"><?php echo $e($l) ?></label>
						</div>
					<?php endforeach ?>
					<?php if (!$choices[$name]): ?><span class="text-body-secondary small">No choices available.</span><?php endif ?>
				</div>

			<?php elseif ($type === 'image' || $type === 'file'): ?>
				<?php
				$up = $f['upload'];
				$accept = implode(',', array_map(static function ($x) { return '.'.$x; }, $up['types']));
				$has = $editing && $value !== '' && $value !== null;
				?>
				<?php if ($has): ?>
					<div class="d-flex align-items-center gap-3 mb-2">
						<?php if ($type === 'image'): ?>
							<a href="<?php echo $e(base_url('uploads/'.$value)) ?>" target="_blank" rel="noopener"><img src="<?php echo $e(base_url('uploads/'.$value)) ?>" alt="" class="crud-thumb crud-thumb-lg"></a>
						<?php else: ?>
							<a href="<?php echo $e(base_url('uploads/'.$value)) ?>" target="_blank" rel="noopener" download><i class="bi bi-paperclip me-1"></i>Current file</a>
						<?php endif ?>
						<div class="form-check mb-0">
							<input class="form-check-input" type="checkbox" name="<?php echo $e($name) ?>__remove" value="1" id="<?php echo $e($id) ?>-remove">
							<label class="form-check-label small" for="<?php echo $e($id) ?>-remove">Remove</label>
						</div>
					</div>
				<?php endif ?>
				<input class="form-control" type="file" id="<?php echo $e($id) ?>" name="<?php echo $e($name) ?>" accept="<?php echo $e($accept) ?>"<?php echo $has ? '' : '' ?>>
				<div class="form-text">Up to <?php echo $e($size($up['max_kb'])) ?> · <?php echo $e(strtoupper(implode(', ', $up['types']))) ?><?php echo $has ? ' · choosing a file replaces the current one' : '' ?><?php echo $type === 'image' && !empty($up['max_width']) ? ' · resized to '.(int) $up['max_width'].' px wide' : '' ?></div>

			<?php else: ?>
				<?php
				$htmlType = ['number' => 'number', 'email' => 'email', 'date' => 'date', 'password' => 'password'][$type] ?? 'text';
				$attrs = '';
				if ($type === 'text') { $attrs .= ' maxlength="'.(int) $f['maxlength'].'"'; }
				if ($type === 'number') {
					$attrs .= ' step="'.(!empty($f['integer']) ? '1' : '0.01').'" inputmode="'.(!empty($f['integer']) ? 'numeric' : 'decimal').'"';
					if (isset($f['min'])) { $attrs .= ' min="'.$e($f['min']).'"'; }
					if (isset($f['max'])) { $attrs .= ' max="'.$e($f['max']).'"'; }
				}
				if ($type === 'password') { $attrs .= ' autocomplete="new-password" minlength="'.(int) $f['min_length'].'"'.($editing ? ' placeholder="Leave empty to keep the current password"' : ''); }
				?>
				<input class="form-control" type="<?php echo $htmlType ?>" id="<?php echo $e($id) ?>" name="<?php echo $e($name) ?>" value="<?php echo $type === 'password' ? '' : $e($value) ?>"<?php echo $attrs ?>>
			<?php endif ?>

			<?php if (!empty($f['help'])): ?><div class="form-text"><?php echo $e($f['help']) ?></div><?php endif ?>
			<div class="invalid-feedback" data-error-for="<?php echo $e($name) ?>"></div>
		</div>
	<?php endforeach ?>
</form>
