<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="h4 mb-4 text-center">Log in</h1>

<?php echo form_open('login') ?>
	<div class="mb-3">
		<label for="email" class="form-label">Email</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-envelope"></i></span>
			<input type="email" class="form-control" name="email" id="email" value="<?php echo set_value('email') ?>" autocomplete="username" required autofocus>
		</div>
	</div>

	<div class="mb-4">
		<label for="password" class="form-label">Password</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock"></i></span>
			<input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required>
		</div>
	</div>

	<button type="submit" class="btn btn-primary w-100">Log in</button>
<?php echo form_close() ?>

<p class="text-center small mt-4 mb-0">No account? <a href="<?php echo site_url('register') ?>">Register</a></p>
