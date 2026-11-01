<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="auth-title">Welcome back</h1>
<p class="auth-sub">Log in to continue to <?php echo htmlspecialchars((string) app_setting('app_name'), ENT_QUOTES, 'UTF-8') ?>.</p>

<?php echo form_open('login', ['class' => 'auth-form']) ?>
	<div class="mb-3">
		<label for="email" class="form-label">Email</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-envelope"></i></span>
			<input type="email" class="form-control" name="email" id="email" value="<?php echo set_value('email') ?>" placeholder="you@example.com" autocomplete="username" required autofocus>
		</div>
	</div>

	<div class="mb-4">
		<label for="password" class="form-label">Password</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock"></i></span>
			<input type="password" class="form-control" name="password" id="password" placeholder="Your password" autocomplete="current-password" required>
			<button type="button" class="btn btn-reveal" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
		</div>
	</div>

	<?php if (password_reset_enabled()): ?>
		<div class="text-end mb-3 mt-n2"><a href="<?php echo site_url('forgot-password') ?>" class="small">Forgot your password?</a></div>
	<?php endif ?>

	<?php echo turnstile_widget('login') ?>
	<button type="submit" class="btn btn-primary btn-submit w-100">Log in <i class="bi bi-arrow-right ms-1"></i></button>
<?php echo form_close() ?>

<?php if (!empty($unverified_email)): ?>
	<?php echo form_open('resend-verification', ['class' => 'auth-form mt-3']) ?>
		<input type="hidden" name="email" value="<?php echo htmlspecialchars((string) $unverified_email, ENT_QUOTES, 'UTF-8') ?>">
		<button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-envelope me-1"></i>Send me a new confirmation link</button>
	<?php echo form_close() ?>
<?php endif ?>

<?php echo google_login_button() ?>

<p class="auth-alt">New here? <a href="<?php echo site_url('register') ?>">Create an account</a></p>
