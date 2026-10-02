<?php defined('BASEPATH') OR exit('No direct script access allowed');
$user = current_user();
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if ($user): ?>
	<div class="dropdown">
		<button class="btn btn-link nav-link topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
			<?php echo user_avatar($user['name'], 'avatar-sm', 0) ?>
			<span class="d-none d-md-inline fw-semibold small"><?php echo $e($user['name']) ?></span>
			<i class="bi bi-chevron-down d-none d-md-inline small opacity-50"></i>
		</button>
		<ul class="dropdown-menu dropdown-menu-end" style="min-width:15rem">
			<li class="px-2 py-2 d-flex align-items-center gap-2">
				<?php echo user_avatar($user['name'], '', 0) ?>
				<div class="min-w-0" style="min-width:0">
					<div class="fw-semibold text-truncate"><?php echo $e($user['name']) ?></div>
					<div class="small text-body-secondary text-truncate"><?php echo $e($user['email']) ?></div>
				</div>
			</li>
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="<?php echo site_url('dashboard') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
			<?php if (can('settings.manage')): ?>
				<li><a class="dropdown-item" href="<?php echo site_url('admin/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
			<?php endif ?>
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item text-danger" href="<?php echo site_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
		</ul>
	</div>
<?php else: ?>
	<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('login') ?>">Log in</a>
	<a class="btn btn-sm btn-primary" href="<?php echo site_url('register') ?>">Register</a>
<?php endif ?>
