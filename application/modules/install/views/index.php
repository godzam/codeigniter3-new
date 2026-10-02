<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var array<string, mixed> $status    Installer::status()
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var array<string, mixed> $failure   a failed Installer::run(): error, steps, manual_config
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$db = $status['db'];
$problems = count(array_filter($status['checks'], static function ($c) { return !$c['ok']; }));
$invalid = static function ($key) use ($errors) { return isset($errors[$key]) ? ' is-invalid' : ''; };
$error = static function ($key) use ($errors, $e) { return isset($errors[$key]) ? '<div class="invalid-feedback d-block">'.$e($errors[$key]).'</div>' : ''; };
?>
<h1 class="auth-title">Set up <?php echo $e(app_setting('app_name')) ?></h1>
<p class="auth-sub">One click: the database, the tables and your administrator account.</p>

<?php if (!empty($failure['error'])): ?>
	<div class="alert alert-danger" role="alert">
		<strong>Installation stopped.</strong> <?php echo $e($failure['error']) ?>
		<?php if (!empty($failure['steps'])): ?>
			<div class="small mt-2">Done before that: <?php echo $e(implode(', ', $failure['steps'])) ?>.</div>
		<?php endif ?>
	</div>
	<?php if (!empty($failure['manual_config'])): ?>
		<pre class="small border rounded p-2 bg-body-tertiary" style="max-height:14rem;overflow:auto"><?php echo $e($failure['manual_config']) ?></pre>
	<?php endif ?>
<?php endif ?>

<details class="setup-checks mb-4"<?php echo $problems ? ' open' : '' ?>>
	<summary>
		Server checks
		<span class="pill <?php echo $problems ? 'pill-warning' : 'pill-success' ?> ms-1"><?php echo $problems ? $problems.' to look at' : 'all good' ?></span>
	</summary>
	<ul>
		<?php foreach ($status['checks'] as $c): ?>
			<li>
				<?php if ($c['ok']): ?><i class="bi bi-check-circle-fill text-success"></i>
				<?php elseif ($c['fatal']): ?><i class="bi bi-x-circle-fill text-danger"></i>
				<?php else: ?><i class="bi bi-exclamation-triangle-fill text-warning"></i><?php endif ?>
				<span><?php echo $e($c['label']) ?><?php if (!$c['ok']): ?><small class="d-block text-body-secondary"><?php echo $e($c['hint']) ?></small><?php endif ?></span>
			</li>
		<?php endforeach ?>
	</ul>
</details>

<?php echo form_open('install', ['class' => 'auth-form', 'novalidate' => 'novalidate']) ?>

	<?php if ($status['needs_database']): ?>
		<h2 class="setup-h">Database</h2>
		<p class="small text-body-secondary">
			<?php if ($db['state'] === 'cannot_connect'): ?>
				The current settings do not work (<?php echo $e($db['error']) ?>). Enter the right ones and they are saved for you.
			<?php else: ?>
				No database is set up yet. In Laragon the defaults below work as they are. The database is created if it does not exist.
			<?php endif ?>
		</p>
		<div class="row g-2 mb-1">
			<div class="col-7">
				<label for="db_host" class="form-label">Server</label>
				<input type="text" class="form-control<?php echo $invalid('db_host') ?>" id="db_host" name="db_host" value="<?php echo $e($values['db_host']) ?>" autocomplete="off" spellcheck="false">
				<?php echo $error('db_host') ?>
			</div>
			<div class="col-5">
				<label for="db_user" class="form-label">User</label>
				<input type="text" class="form-control<?php echo $invalid('db_user') ?>" id="db_user" name="db_user" value="<?php echo $e($values['db_user']) ?>" autocomplete="off" spellcheck="false">
				<?php echo $error('db_user') ?>
			</div>
		</div>
		<div class="row g-2 mb-3">
			<div class="col-6">
				<label for="db_pass" class="form-label">Password</label>
				<input type="password" class="form-control<?php echo $invalid('db_pass') ?>" id="db_pass" name="db_pass" value="<?php echo $e($values['db_pass']) ?>" autocomplete="off" placeholder="(empty in Laragon)">
				<?php echo $error('db_pass') ?>
			</div>
			<div class="col-6">
				<label for="db_name" class="form-label">Database name</label>
				<input type="text" class="form-control<?php echo $invalid('db_name') ?>" id="db_name" name="db_name" value="<?php echo $e($values['db_name']) ?>" autocomplete="off" spellcheck="false">
				<?php echo $error('db_name') ?>
			</div>
		</div>
	<?php elseif ($db['state'] === 'no_database'): ?>
		<div class="alert alert-info py-2 small"><i class="bi bi-database-add me-1"></i>The database <strong><?php echo $e($db['params']['database'] ?? '') ?></strong> does not exist yet. It will be created.</div>
	<?php else: ?>
		<p class="small text-success mb-3"><i class="bi bi-check-circle-fill me-1"></i>Connected to the database.</p>
	<?php endif ?>

	<h2 class="setup-h">Application</h2>
	<div class="mb-3">
		<label for="app_name" class="form-label">Name</label>
		<input type="text" class="form-control<?php echo $invalid('app_name') ?>" id="app_name" name="app_name" value="<?php echo $e($values['app_name']) ?>" maxlength="60" autocomplete="off">
		<?php echo $error('app_name') ?>
	</div>

	<h2 class="setup-h">Administrator</h2>
	<p class="small text-body-secondary">The first account is a <strong>super admin</strong>, the developer role with access to everything. Create day-to-day admins later on the Users page.</p>
	<div class="mb-3">
		<label for="admin_name" class="form-label">Your name</label>
		<input type="text" class="form-control<?php echo $invalid('admin_name') ?>" id="admin_name" name="admin_name" value="<?php echo $e($values['admin_name']) ?>" autocomplete="name">
		<?php echo $error('admin_name') ?>
	</div>
	<div class="mb-3">
		<label for="admin_email" class="form-label">Email</label>
		<input type="email" class="form-control<?php echo $invalid('admin_email') ?>" id="admin_email" name="admin_email" value="<?php echo $e($values['admin_email']) ?>" autocomplete="username" placeholder="you@example.com">
		<?php echo $error('admin_email') ?>
	</div>
	<div class="mb-3">
		<label for="admin_password" class="form-label">Password</label>
		<div class="input-group">
			<input type="password" class="form-control<?php echo $invalid('admin_password') ?>" id="admin_password" name="admin_password" autocomplete="new-password" placeholder="At least 8 characters">
			<button type="button" class="btn btn-reveal" data-toggle-password="admin_password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
		</div>
		<?php echo $error('admin_password') ?>
	</div>
	<div class="mb-4">
		<label for="admin_password_confirm" class="form-label">Repeat the password</label>
		<input type="password" class="form-control<?php echo $invalid('admin_password_confirm') ?>" id="admin_password_confirm" name="admin_password_confirm" autocomplete="new-password">
		<?php echo $error('admin_password_confirm') ?>
	</div>

	<button type="submit" class="btn btn-primary btn-submit w-100"<?php echo $status['can_install'] ? '' : ' disabled' ?>><i class="bi bi-rocket-takeoff me-1"></i>Install now</button>
	<?php if (!$status['can_install']): ?><p class="small text-danger mt-2 mb-0">Fix the red items under "Server checks" first.</p><?php endif ?>
<?php echo form_close() ?>

<p class="auth-alt"><a href="<?php echo site_url() ?>"><i class="bi bi-arrow-left me-1"></i>Back to the welcome page</a></p>
