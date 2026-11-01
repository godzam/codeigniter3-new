<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<?php /** @var string $token */ ?>
<h1 class="auth-title">Confirm your email</h1>
<p class="auth-sub">One more click to finish setting up your account.</p>

<?php echo form_open('verify-email/'.rawurlencode((string) $token), ['class' => 'auth-form']) ?>
	<button type="submit" class="btn btn-primary btn-submit w-100"><i class="bi bi-check-lg me-1"></i>Confirm my email address</button>
<?php echo form_close() ?>
