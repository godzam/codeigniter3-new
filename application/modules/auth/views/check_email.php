<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<?php /** @var string $email */ /** @var bool $sent */ ?>
<h1 class="auth-title">Check your email</h1>
<?php if (!empty($sent)): ?>
	<p class="auth-sub">We sent a confirmation link to <strong><?php echo htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?></strong>. It works for 3 days; open it and confirm, then log in.</p>
<?php else: ?>
	<div class="alert alert-warning" role="alert">Your account was created, but the email could not be sent. Try logging in: you will be able to ask for a new link. If it keeps failing, contact the administrator.</div>
<?php endif ?>
<a href="<?php echo site_url('login') ?>" class="btn btn-primary w-100">Go to log in</a>
