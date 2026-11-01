<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<?php /** @var string $token */ ?>
<h1 class="auth-title">Choose a new password</h1>
<p class="auth-sub">At least 8 characters. You will log in with it afterwards.</p>

<?php echo form_open('reset-password/'.rawurlencode((string) $token), ['class' => 'auth-form']) ?>
	<div class="mb-3">
		<label for="password" class="form-label">New password</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock"></i></span>
			<input type="password" class="form-control" name="password" id="password" placeholder="At least 8 characters" autocomplete="new-password" required minlength="8" maxlength="255" autofocus>
			<button type="button" class="btn btn-reveal" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
		</div>
	</div>

	<div class="mb-4">
		<label for="password_confirm" class="form-label">Repeat it</label>
		<div class="input-group">
			<span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
			<input type="password" class="form-control" name="password_confirm" id="password_confirm" autocomplete="new-password" required minlength="8" maxlength="255">
		</div>
	</div>

	<button type="submit" class="btn btn-primary btn-submit w-100">Change password <i class="bi bi-arrow-right ms-1"></i></button>
<?php echo form_close() ?>
