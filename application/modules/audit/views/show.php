<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * One audit entry, for the dialog (no layout around it).
 *
 * @var array<string, mixed> $row
 * @var array<string, mixed> $payload   the stored JSON: new / changes / old / data
 */
use App\Audit\Changes;
use App\Audit\Labels;

$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$action = Labels::action($row['action']);

// A stored value as text: nothing, true/false, a list, or the string itself.
$show = static function ($v) use ($e) {
	if ($v === null || $v === '') { return '<span class="text-body-secondary">—</span>'; }
	if (is_bool($v)) { return $v ? 'true' : 'false'; }
	if (is_array($v)) { return $v === [] ? '<span class="text-body-secondary">—</span>' : $e(implode(', ', array_map('strval', $v))); }
	if ($v === Changes::HIDDEN) { return '<span class="pill pill-secondary"><i class="bi bi-eye-slash"></i>hidden</span>'; }

	return '<span class="text-break" style="white-space:pre-wrap">'.$e($v).'</span>';
};
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<span class="pill pill-<?php echo $e($action['tone']) ?>"><?php echo $e($action['label']) ?></span>
	<span class="fw-semibold"><?php echo $e(Labels::entity($row['entity'])) ?></span>
	<?php if ($row['label'] !== null): ?><span class="text-body-secondary">·</span><span class="fw-semibold"><?php echo $e($row['label']) ?></span><?php endif ?>
	<?php if ($row['entity_id'] !== null): ?><code class="small">#<?php echo $e($row['entity_id']) ?></code><?php endif ?>
</div>

<dl class="row small mb-4">
	<dt class="col-4 col-sm-3 text-body-secondary fw-normal">When</dt>
	<dd class="col-8 col-sm-9"><?php echo $e($row['created_at']) ?></dd>
	<dt class="col-4 col-sm-3 text-body-secondary fw-normal">Who</dt>
	<dd class="col-8 col-sm-9">
		<?php if ($row['actor_name'] !== null || $row['actor_email'] !== null): ?>
			<?php echo $e($row['actor_name'] ?? '') ?> <span class="text-body-secondary"><?php echo $e($row['actor_email'] ?? '') ?></span>
		<?php else: ?><span class="text-body-secondary">System / not signed in</span><?php endif ?>
	</dd>
	<dt class="col-4 col-sm-3 text-body-secondary fw-normal">IP address</dt>
	<dd class="col-8 col-sm-9"><code><?php echo $e($row['ip'] ?? '') ?></code></dd>
</dl>

<?php if (!empty($payload['truncated'])): ?>
	<div class="alert alert-warning py-2 small">This entry was too large to store in full.</div>
<?php endif ?>

<?php foreach (Labels::sections() as $key => $heading): ?>
	<?php if (empty($payload[$key]) || !is_array($payload[$key])) { continue; } ?>
	<h3 class="h6 mb-2"><?php echo $e($heading) ?></h3>
	<div class="table-responsive mb-3">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th style="width:34%">Field</th>
					<?php if ($key === 'changes'): ?><th>Before</th><th>After</th><?php else: ?><th>Value</th><?php endif ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($payload[$key] as $field => $value): ?>
				<tr>
					<td><code class="small text-break"><?php echo $e($field) ?></code></td>
					<?php if ($key === 'changes' && is_array($value) && array_key_exists('added', $value)): ?>
						<td colspan="2">
							<?php foreach ((array) $value['added'] as $item): ?><span class="pill pill-success me-1 mb-1"><i class="bi bi-plus"></i><?php echo $e($item) ?></span><?php endforeach ?>
							<?php foreach ((array) $value['removed'] as $item): ?><span class="pill pill-danger me-1 mb-1"><i class="bi bi-dash"></i><?php echo $e($item) ?></span><?php endforeach ?>
						</td>
					<?php elseif ($key === 'changes' && is_array($value)): ?>
						<td><?php echo $show($value['from'] ?? null) ?></td>
						<td><?php echo $show($value['to'] ?? null) ?></td>
					<?php else: ?>
						<td><?php echo $show($value) ?></td>
					<?php endif ?>
				</tr>
			<?php endforeach ?>
			</tbody>
		</table>
	</div>
<?php endforeach ?>

<?php if ($row['changes'] === null): ?>
	<p class="text-body-secondary mb-0">No further details were recorded.</p>
<?php endif ?>
