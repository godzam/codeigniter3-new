<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<?php if (!empty($setup)): ?>
	<div class="card setup-card mb-4">
		<div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3 p-4">
			<span class="stat-icon tone-primary" style="width:3.25rem;height:3.25rem;font-size:1.5rem"><i class="bi bi-rocket-takeoff-fill"></i></span>
			<div class="flex-grow-1">
				<h2 class="h5 mb-1">Finish setting up</h2>
				<p class="mb-0 text-body-secondary">
					<?php if ($setup['db']['state'] === 'ok'): ?>
						The database is connected but empty. One click creates the tables and your administrator account.
					<?php elseif ($setup['db']['state'] === 'no_database'): ?>
						The database does not exist yet. One click creates it, the tables and your administrator account.
					<?php else: ?>
						No working database connection yet. One click saves it, creates the tables and your administrator account.
					<?php endif ?>
				</p>
			</div>
			<a href="<?php echo site_url('install') ?>" class="btn btn-primary btn-lg text-nowrap"><i class="bi bi-lightning-charge-fill me-1"></i>Install now</a>
		</div>
	</div>
<?php endif ?>

<div class="py-5 text-center">
	<h1 class="display-5 fw-bold"><?php echo htmlspecialchars((string) app_setting('app_name'), ENT_QUOTES, 'UTF-8') ?></h1>
	<p class="lead text-body-secondary mx-auto" style="max-width:40rem">
		<?php echo htmlspecialchars((string) (app_setting('app_tagline') ?: 'A CodeIgniter 3 starter'), ENT_QUOTES, 'UTF-8') ?>
	</p>
	<div class="d-flex justify-content-center gap-2 mt-4">
		<?php if (is_logged_in()): ?>
			<a href="<?php echo site_url('dashboard') ?>" class="btn btn-primary btn-lg">Go to the dashboard</a>
		<?php else: ?>
			<a href="<?php echo site_url('login') ?>" class="btn btn-primary btn-lg">Log in</a>
			<a href="<?php echo site_url('register') ?>" class="btn btn-outline-secondary btn-lg">Register</a>
		<?php endif ?>
	</div>
</div>

<div class="row g-4 mb-4">
	<?php foreach ([
		['bi-palette', 'Themeable', 'Primary and secondary colors, light/dark mode, corners and font — all from Settings, no code.'],
		['bi-boxes', 'Modular', 'HMVC modules with their own controllers, models, and views. See application/modules/example.'],
		['bi-shield-lock', 'Secure by default', 'CSRF protection, security headers, login rate limiting, and role checks out of the box.'],
	] as [$icon, $title, $text]): ?>
		<div class="col-md-4">
			<div class="card h-100">
				<div class="card-body">
					<i class="bi <?php echo $icon ?> fs-2 text-primary"></i>
					<h2 class="h5 mt-2"><?php echo $title ?></h2>
					<p class="mb-0 text-body-secondary"><?php echo $text ?></p>
				</div>
			</div>
		</div>
	<?php endforeach ?>
</div>

<p class="small text-body-secondary text-center">
	Edit this page in <code>application/views/welcome_message.php</code> · Page rendered in <strong>{elapsed_time}</strong> seconds.
	<?php echo (ENVIRONMENT === 'development') ? 'CodeIgniter Version <strong>'.CI_VERSION.'</strong>' : '' ?>
</p>
