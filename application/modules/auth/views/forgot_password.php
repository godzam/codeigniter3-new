<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="auth-title">Forgot your password?</h1>
<p class="auth-sub">Enter your email address and we will send you a link to choose a new one.</p>

<?php if (!empty($msg)): ?>
	<div class="alert alert-success" role="status"><?php echo htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<?php echo form_open('forgot-password', ['class' => 'auth-form']) ?>
	<div class="mb-4">
		<label for="email" class="form-label">Email</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-envelope"></i></span>
			<input type="email" class="form-control" name="email" id="email" value="<?php echo set_value('email') ?>" placeholder="you@example.com" autocomplete="username" maxlength="190" required autofocus>
		</div>
	</div>

	<?php echo turnstile_widget('forgot') ?>
	<button type="submit" class="btn btn-primary btn-submit w-100">Send the link <i class="bi bi-arrow-right ms-1"></i></button>
<?php echo form_close() ?>

<p class="auth-alt"><a href="<?php echo site_url('login') ?>">Back to log in</a></p>
