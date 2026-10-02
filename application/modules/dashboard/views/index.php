<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array $user
 * @var string $greeting
 * @var bool $can_users
 * @var bool $can_roles
 * @var int $modules
 * @var int|string $permissions
 * @var int $total_users
 * @var int $new_users
 * @var int $roles_count
 * @var array<int, array<string, mixed>> $days
 * @var array<int, array<string, mixed>> $recent
 * @var array<string, string> $role_names
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$first = explode(' ', trim((string) $user['name']))[0];

// The four numbers shown on top, depending on what the user may see.
$stats = [];
if ($can_users) {
	$stats[] = ['bi-people-fill', 'tone-primary', 'Total users', number_format($total_users), 'All accounts'];
	$stats[] = ['bi-person-plus-fill', 'tone-success', 'New this week', number_format($new_users), 'Last 7 days'];
}
if ($can_roles) {
	$stats[] = ['bi-shield-lock-fill', 'tone-purple', 'Roles', number_format($roles_count), 'Who can do what'];
}
if ($modules > 0) {
	$stats[] = ['bi-grid-fill', 'tone-warning', 'Modules', number_format($modules), 'Made with the generator'];
}
$stats[] = ['bi-key-fill', 'tone-info', 'Your permissions', is_numeric($permissions) ? number_format($permissions) : $permissions, 'Role: '.get_instance()->rbac->role_label($user['role'])];
$stats = array_slice($stats, 0, 4);

$links = array_values(array_filter(menu_items(), static function ($m) { return isset($m['url']) && trim($m['url'], '/') !== 'dashboard'; }));
$max = 1;
foreach ($days as $d) { $max = max($max, $d['count']); }
?>
<div class="card hero-card mb-4">
	<div class="card-body p-4 p-lg-4">
		<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
			<div>
				<h2><?php echo $e($greeting) ?>, <?php echo $e($first) ?> 👋</h2>
				<p>Here is what is happening in <?php echo $e(app_setting('app_name')) ?> today.</p>
			</div>
			<div class="d-flex flex-wrap gap-2">
				<?php if ($can_users): ?><a class="btn btn-light" href="<?php echo site_url('admin/users') ?>"><i class="bi bi-people me-1"></i>Users</a><?php endif ?>
				<?php if (is_super_admin()): ?><a class="btn btn-glass" href="<?php echo site_url('admin/generator/create') ?>"><i class="bi bi-magic me-1"></i>New module</a>
				<?php elseif (can('settings.manage')): ?><a class="btn btn-glass" href="<?php echo site_url('admin/settings') ?>"><i class="bi bi-gear me-1"></i>Settings</a><?php endif ?>
			</div>
		</div>
	</div>
</div>

<div class="row g-3 mb-4">
	<?php foreach ($stats as [$icon, $tone, $label, $value, $note]): ?>
		<div class="col-6 col-xl-3">
			<div class="card stat-card card-hover h-100">
				<div class="card-body">
					<div class="d-flex align-items-start justify-content-between gap-2 mb-3">
						<span class="stat-icon <?php echo $tone ?>"><i class="bi <?php echo $icon ?>"></i></span>
					</div>
					<div class="stat-value"><?php echo $e($value) ?></div>
					<div class="stat-label mt-1"><?php echo $e($label) ?></div>
					<div class="stat-note text-truncate"><?php echo $e($note) ?></div>
				</div>
			</div>
		</div>
	<?php endforeach ?>
</div>

<div class="row g-3 mb-4">
	<?php if ($can_users): ?>
		<div class="col-lg-8">
			<div class="card chart-card h-100">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-start mb-3">
						<div>
							<h3 class="h6 mb-0">New users</h3>
							<div class="small text-body-secondary">Last 14 days</div>
						</div>
						<span class="pill pill-primary"><?php echo array_sum(array_column($days, 'count')) ?> total</span>
					</div>
					<div class="mini-bars" role="img" aria-label="New users per day over the last 14 days">
						<?php foreach ($days as $d): ?>
							<div class="bar<?php echo $d['count'] === 0 ? ' zero' : '' ?>" title="<?php echo $e($d['long'].': '.$d['count'].' new') ?>"><i style="height:<?php echo $d['count'] === 0 ? 4 : max(8, round($d['count'] / $max * 100)) ?>%"></i></div>
						<?php endforeach ?>
					</div>
					<div class="bar-labels" aria-hidden="true"><?php foreach ($days as $d): ?><span><?php echo $e($d['label']) ?></span><?php endforeach ?></div>
				</div>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="card h-100">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<h3 class="h6 mb-0">Recent sign-ups</h3>
						<a class="small text-decoration-none" href="<?php echo site_url('admin/users') ?>">View all</a>
					</div>
					<?php foreach ($recent as $r): ?>
						<div class="list-person">
							<?php echo user_avatar($r['name']) ?>
							<div class="meta"><strong><?php echo $e($r['name']) ?></strong><span><?php echo $e($r['email']) ?></span></div>
							<span class="pill <?php echo $r['role'] === 'super_admin' ? 'pill-danger' : 'pill-secondary' ?>"><?php echo $e($role_names[$r['role']] ?? $r['role']) ?></span>
						</div>
					<?php endforeach ?>
					<?php if (!$recent): ?><p class="text-body-secondary mt-3 mb-0">No users yet.</p><?php endif ?>
				</div>
			</div>
		</div>
	<?php endif ?>

	<?php if ($links): ?>
		<div class="<?php echo $can_users ? 'col-12' : 'col-lg-6' ?>">
			<div class="card">
				<div class="card-body">
					<h3 class="h6 mb-3">Quick access</h3>
					<div class="row g-2">
						<?php foreach ($links as $m): ?>
							<div class="col-12 col-sm-6 col-xl-4">
								<a class="quick-link" href="<?php echo site_url($m['url']) ?>">
									<span class="stat-icon tone-primary" style="width:2.25rem;height:2.25rem;font-size:1.05rem"><i class="bi <?php echo $e($m['icon'] ?? 'bi-dot') ?>"></i></span>
									<span class="fw-semibold"><?php echo $e($m['label']) ?></span>
									<i class="bi bi-chevron-right chev"></i>
								</a>
							</div>
						<?php endforeach ?>
					</div>
				</div>
			</div>
		</div>
	<?php endif ?>
</div>

<?php if (is_super_admin()): ?>
	<div class="card">
		<div class="card-body">
			<h3 class="h6">For developers</h3>
			<p class="mb-3 text-body-secondary">This page is <code>application/modules/dashboard/</code>, rendered in the admin layout; the sidebar comes from <code>application/config/menu.php</code>. Only super admins see this card.</p>
			<button type="button" class="btn btn-primary btn-sm" data-toast="success|SweetAlert2 toast works">Show a toast</button>
			<a href="#" class="btn btn-outline-danger btn-sm" data-confirm="Delete this item?" data-confirm-text="You will not be able to undo this." data-confirm-toast="info|Deleted (demo only)">Ask for confirmation</a>
		</div>
	</div>
<?php endif ?>
