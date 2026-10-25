<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * Admin -> Pages: the list.
 *
 * @var array<int, array<string, mixed>> $pages
 * @var bool $available, $can_create, $can_edit, $can_delete
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (!$available): ?>
	<div class="alert alert-info">
		<i class="bi bi-info-circle me-1"></i>The pages table does not exist yet. Run <code>php index.php console migrate</code> to build it.
	</div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
	<p class="text-body-secondary small mb-0">Every page lives at <code>/page/{address}</code> and can be linked from Admin → Menus.</p>
	<?php if ($can_create && $available): ?>
		<a href="<?php echo site_url('admin/pages/create') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i>New page</a>
	<?php endif ?>
</div>

<div class="card">
	<div class="card-body p-0">
		<?php if ($pages === []): ?>
			<p class="text-body-secondary p-3 mb-0">No pages yet<?php echo $available ? ' — build the first one.' : '.' ?></p>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead>
						<tr>
							<th>Title</th>
							<th>Address</th>
							<th class="text-center">Sections</th>
							<th class="text-center">Status</th>
							<th class="text-end text-nowrap">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($pages as $p): ?>
							<tr>
								<td><span class="fw-semibold"><?php echo $e($p['title']) ?></span>
									<div class="small text-body-secondary"><?php echo $e($p['meta_description'] ?? '') ?></div></td>
								<td><code class="small">/page/<?php echo $e($p['slug']) ?></code></td>
								<td class="text-center"><?php echo (int) ($p['section_count'] ?? 0) ?></td>
								<td class="text-center">
									<?php if ($p['status'] === 'published'): ?>
										<span class="pill pill-success">published</span>
									<?php else: ?>
										<span class="pill pill-warning">draft</span>
									<?php endif ?>
								</td>
								<td class="text-end text-nowrap">
									<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('page/'.$p['slug']) ?>" target="_blank" rel="noopener" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
									<?php if ($can_edit): ?>
										<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/pages/edit/'.(int) $p['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
									<?php endif ?>
									<?php if ($can_delete): ?>
										<?php echo form_open('admin/pages/delete/'.(int) $p['id'], ['class' => 'd-inline']) ?>
										<button type="button" class="btn btn-sm btn-outline-danger" data-confirm="Delete this page?" title="Delete"><i class="bi bi-trash"></i></button>
										<?php echo form_close() ?>
									<?php endif ?>
								</td>
							</tr>
						<?php endforeach ?>
					</tbody>
				</table>
			</div>
		<?php endif ?>
	</div>
</div>
