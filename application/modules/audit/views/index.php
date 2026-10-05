<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var bool $available
 * @var array<int, string> $actions
 * @var array<int, string> $entities
 */
use App\Audit\Labels;
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (!$available): ?>
	<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>The audit log table does not exist yet, so nothing is being recorded. Run <code>php index.php console migrate</code>.</div>
<?php endif ?>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<div class="row g-2 mb-3 px-1 pt-1" id="audit-filters">
			<div class="col-6 col-md-auto">
				<label class="visually-hidden" for="f_action">What happened</label>
				<select class="form-select form-select-sm" id="f_action" name="f_action">
					<option value="">Every kind of action</option>
					<?php foreach ($actions as $a): ?><option value="<?php echo $e($a) ?>"><?php echo $e(Labels::action($a)['label']) ?></option><?php endforeach ?>
				</select>
			</div>
			<div class="col-6 col-md-auto">
				<label class="visually-hidden" for="f_entity">On what</label>
				<select class="form-select form-select-sm" id="f_entity" name="f_entity">
					<option value="">Everything</option>
					<?php foreach ($entities as $en): ?><option value="<?php echo $e($en) ?>"><?php echo $e(Labels::entity($en)) ?></option><?php endforeach ?>
				</select>
			</div>
			<div class="col-6 col-md-auto">
				<label class="visually-hidden" for="f_from">From</label>
				<input type="date" class="form-control form-control-sm" id="f_from" name="f_from" aria-label="From date" title="From">
			</div>
			<div class="col-6 col-md-auto">
				<label class="visually-hidden" for="f_to">To</label>
				<input type="date" class="form-control form-control-sm" id="f_to" name="f_to" aria-label="To date" title="To">
			</div>
		</div>
		<?php echo datatable_table('audit-table', 'admin/audit/data', [
			['When', ['priority' => 2, 'orderable' => true]],
			['Who', ['priority' => 3]],
			['Action', ['priority' => 1]],
			['On', ['priority' => 5]],
			['Record', ['priority' => 1]],
			['IP', ['priority' => 6]],
			['', ['priority' => 2, 'orderable' => false, 'class' => 'text-end text-nowrap']],
		], ['filters' => '#audit-filters', 'empty' => 'Nothing recorded yet.', 'page_length' => 25]) ?>
	</div>
</div>

<div class="modal fade" id="audit-modal" tabindex="-1" aria-labelledby="audit-modal-title" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title h5" id="audit-modal-title">Details</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="audit-modal-body"></div>
		</div>
	</div>
</div>
