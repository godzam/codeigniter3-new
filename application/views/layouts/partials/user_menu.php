<?php defined('BASEPATH') OR exit('No direct script access allowed');
$user = current_user();
?>
<?php if ($user): ?>
	<div class="dropdown">
		<button class="btn btn-link nav-link d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
			<i class="bi bi-person-circle fs-5"></i>
			<span class="d-none d-md-inline"><?php echo htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></span>
		</button>
		<ul class="dropdown-menu dropdown-menu-end">
			<li><h6 class="dropdown-header"><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></h6></li>
			<li><a class="dropdown-item" href="<?php echo site_url('dashboard') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="<?php echo site_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
		</ul>
	</div>
<?php else: ?>
	<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('login') ?>">Log in</a>
	<a class="btn btn-sm btn-primary" href="<?php echo site_url('register') ?>">Register</a>
<?php endif ?>
