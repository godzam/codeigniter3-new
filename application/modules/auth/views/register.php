<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="h4 mb-4 text-center">Create an account</h1>

<?php echo form_open('register') ?>
	<div class="mb-3">
		<label for="name" class="form-label">Name</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-person"></i></span>
			<input type="text" class="form-control" name="name" id="name" value="<?php echo set_value('name') ?>" autocomplete="name" required autofocus>
		</div>
	</div>

	<div class="mb-3">
		<label for="email" class="form-label">Email</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-envelope"></i></span>
			<input type="email" class="form-control" name="email" id="email" value="<?php echo set_value('email') ?>" autocomplete="username" required>
		</div>
	</div>

	<div class="mb-4">
		<label for="password" class="form-label">Password</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock"></i></span>
			<input type="password" class="form-control" name="password" id="password" autocomplete="new-password" required minlength="8">
		</div>
		<div class="form-text">At least 8 characters.</div>
	</div>

	<button type="submit" class="btn btn-primary w-100">Register</button>
<?php echo form_close() ?>

<p class="text-center small mt-4 mb-0">Already have an account? <a href="<?php echo site_url('login') ?>">Log in</a></p>
