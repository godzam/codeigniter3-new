<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="auth-title">Create your account</h1>
<p class="auth-sub">It takes less than a minute.</p>

<?php echo form_open('register', ['class' => 'auth-form']) ?>
	<div class="mb-3">
		<label for="name" class="form-label">Name</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-person"></i></span>
			<input type="text" class="form-control" name="name" id="name" value="<?php echo set_value('name') ?>" placeholder="Your full name" autocomplete="name" required autofocus>
		</div>
	</div>

	<div class="mb-3">
		<label for="email" class="form-label">Email</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-envelope"></i></span>
			<input type="email" class="form-control" name="email" id="email" value="<?php echo set_value('email') ?>" placeholder="you@example.com" autocomplete="username" required>
		</div>
	</div>

	<div class="mb-4">
		<label for="password" class="form-label">Password</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock"></i></span>
			<input type="password" class="form-control" name="password" id="password" placeholder="At least 8 characters" autocomplete="new-password" required minlength="8">
			<button type="button" class="btn btn-reveal" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
		</div>
	</div>

	<?php echo turnstile_widget('register') ?>
	<button type="submit" class="btn btn-primary btn-submit w-100">Create account <i class="bi bi-arrow-right ms-1"></i></button>
<?php echo form_close() ?>

<p class="auth-alt">Already have an account? <a href="<?php echo site_url('login') ?>">Log in</a></p>
