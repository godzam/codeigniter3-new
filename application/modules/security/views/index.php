<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var bool $available
 * @var App\Security\LoginLockPolicy $policy
 * @var Loginguard $guard
 * @var array<string, int> $blocked
 * @var bool $can_unblock
 * @var bool $can_settings
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (!$available): ?>
	<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>The login security tables do not exist yet, so nobody is being blocked. Run <code>php index.php console migrate</code>.</div>
<?php endif ?>

<div class="row g-3 mb-4">
	<div class="col-6 col-lg-3">
		<div class="card stat-card h-100"><div class="card-body">
			<span class="stat-icon tone-danger mb-3" style="background:var(--bs-danger-bg-subtle);color:var(--bs-danger-text-emphasis)"><i class="bi bi-globe2"></i></span>
			<div class="stat-value"><?php echo (int) $blocked['ip'] ?></div>
			<div class="stat-label mt-1">IP addresses blocked</div>
			<div class="stat-note">Right now</div>
		</div></div>
	</div>
	<div class="col-6 col-lg-3">
		<div class="card stat-card h-100"><div class="card-body">
			<span class="stat-icon mb-3" style="background:var(--bs-danger-bg-subtle);color:var(--bs-danger-text-emphasis)"><i class="bi bi-person-lock"></i></span>
			<div class="stat-value"><?php echo (int) $blocked['user'] ?></div>
			<div class="stat-label mt-1">Accounts blocked</div>
			<div class="stat-note">Right now</div>
		</div></div>
	</div>
	<div class="col-12 col-lg-6">
		<div class="card h-100"><div class="card-body">
			<h2 class="h6 mb-2"><i class="bi bi-shield-check me-1"></i>The rule</h2>
			<p class="mb-1"><strong><?php echo (int) $policy->maxAttempts() ?> wrong passwords</strong> block the IP address and the account for <strong><?php echo $e($guard->human($policy->blockSeconds())) ?></strong>.</p>
			<p class="mb-2">After <strong><?php echo (int) $policy->strikesForLongBlock() ?> blocks in a row</strong> (no successful login in between) the block lasts <strong><?php echo $e($guard->human($policy->longBlockSeconds())) ?></strong>. A correct password clears the count.</p>
			<?php if ($can_settings): ?><a class="small text-decoration-none" href="<?php echo site_url('admin/settings') ?>">Change these numbers in Settings → Security</a><?php endif ?>
		</div></div>
	</div>
</div>

<div class="card">
	<div class="card-body p-2 p-sm-3">
		<div class="row g-2 mb-3 px-1 pt-1" id="lock-filters">
			<div class="col-12 col-sm-auto">
				<label class="visually-hidden" for="f_status">Show</label>
				<select class="form-select form-select-sm" id="f_status" name="f_status">
					<option value="blocked">Blocked now</option>
					<option value="watching">Being watched (wrong passwords, not blocked)</option>
					<option value="all">Everything on record</option>
				</select>
			</div>
			<div class="col-12 col-sm-auto">
				<label class="visually-hidden" for="f_type">Type</label>
				<select class="form-select form-select-sm" id="f_type" name="f_type">
					<option value="">IP addresses and users</option>
					<option value="ip">IP addresses only</option>
					<option value="user">Users only</option>
				</select>
			</div>
		</div>
		<?php echo datatable_table('locks-table', 'admin/security/data', [
			['Type', ['priority' => 3, 'orderable' => false]],
			['Who', ['priority' => 1]],
			['Status', ['priority' => 2, 'orderable' => false]],
			['Blocks in a row', ['priority' => 5, 'orderable' => false, 'class' => 'text-nowrap']],
			['Last wrong password', ['priority' => 6, 'orderable' => false]],
			['', ['priority' => 2, 'orderable' => false, 'class' => 'text-end text-nowrap']],
		], ['filters' => '#lock-filters', 'empty' => 'Nobody matches. Nice and quiet.']) ?>
	</div>
</div>
