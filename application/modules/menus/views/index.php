<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * Admin -> Menus: the tree, dragged into shape with assets/js/menus.js.
 *
 * @var array<int, array<string, mixed>> $items   flat rows, each with 'depth'
 * @var bool   $available    the menus table exists (migrations run)
 * @var bool   $can_create, $can_edit, $can_delete
 * @var string $csrf_name, $csrf_hash
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (!$available): ?>
	<div class="alert alert-info">
		<i class="bi bi-info-circle me-1"></i>The menu table does not exist yet. Run <code>php index.php console migrate</code> to build it — the menu below is the file-based one.
	</div>
<?php endif ?>

<?php if ($can_create && $available): ?>
	<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
		<p class="text-body-secondary small mb-0">Items from <code>application/config/menu.php</code> are always shown; these are added on top.</p>
		<a href="<?php echo site_url('admin/menus/create') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>New item</a>
	</div>
<?php endif ?>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<?php if ($items === []): ?>
			<p class="text-body-secondary mb-0">No menu items yet<?php echo $available ? ' — add the first one.' : '.' ?></p>
		<?php else: ?>
			<ul class="list-unstyled mb-0 menu-builder" id="menu-builder"
				data-reorder-url="<?php echo site_url('admin/menus/reorder') ?>"
				<?php echo $can_edit ? '' : 'data-readonly="1"' ?>>
				<?php foreach ($items as $item): $depth = (int) $item['depth']; ?>
					<li class="menu-row" data-id="<?php echo (int) $item['id'] ?>" data-depth="<?php echo $depth ?>"
						data-type="<?php echo $e($item['type']) ?>">
						<div class="d-flex align-items-center gap-2 flex-wrap py-2">
							<?php if ($can_edit): ?><span class="menu-handle text-body-secondary" title="Drag to reorder" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span><?php endif ?>
							<i class="bi <?php echo $e($item['type'] === 'section' ? 'bi-dash' : ($item['icon'] ?: 'bi-link-45deg')) ?> text-body-secondary"></i>
							<?php if ($item['type'] === 'section'): ?>
								<span class="fw-semibold text-uppercase small text-body-secondary"><?php echo $e($item['label']) ?></span>
							<?php else: ?>
								<span class="fw-semibold"><?php echo $e($item['label']) ?></span>
								<code class="small text-body-secondary"><?php echo $e($item['url']) ?></code>
							<?php endif ?>
							<?php if (!empty($item['permission'])): ?><span class="pill pill-secondary small"><?php echo $e($item['permission']) ?></span><?php endif ?>
							<?php if (!empty($item['super_admin'])): ?><span class="pill pill-warning small">super admin</span><?php endif ?>
							<?php if (empty($item['is_active'])): ?><span class="pill pill-danger small">off</span><?php endif ?>
							<span class="ms-auto d-flex gap-1 align-items-center">
								<?php if ($can_edit): ?>
									<button type="button" class="btn btn-sm btn-outline-secondary menu-up" aria-label="Move up" title="Move up"><i class="bi bi-arrow-up"></i></button>
									<button type="button" class="btn btn-sm btn-outline-secondary menu-down" aria-label="Move down" title="Move down"><i class="bi bi-arrow-down"></i></button>
								<?php endif ?>
								<?php if ($can_edit): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/menus/edit/'.(int) $item['id']) ?>" aria-label="Edit" title="Edit"><i class="bi bi-pencil"></i></a><?php endif ?>
								<?php if ($can_delete): ?>
									<?php echo form_open('admin/menus/delete/'.(int) $item['id'], ['class' => 'd-inline']) ?>
									<button type="button" class="btn btn-sm btn-outline-danger" data-confirm="Delete this menu item? Its children move up one level." aria-label="Delete" title="Delete"><i class="bi bi-trash"></i></button>
									<?php echo form_close() ?>
								<?php endif ?>
							</span>
						</div>
					</li>
				<?php endforeach ?>
			</ul>
		<?php endif ?>
		<p class="small text-body-secondary mt-3 mb-0" id="menu-builder-status" role="status" aria-live="polite"></p>
	</div>
</div>
<input type="hidden" id="menu-csrf-name" value="<?php echo $e($csrf_name) ?>">
<input type="hidden" id="menu-csrf-hash" value="<?php echo $e($csrf_hash) ?>">
