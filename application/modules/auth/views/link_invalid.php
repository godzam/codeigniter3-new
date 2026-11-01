<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<?php /** @var string $what */ ?>
<h1 class="auth-title">This link does not work</h1>
<p class="auth-sub">The <?php echo htmlspecialchars((string) $what, ENT_QUOTES, 'UTF-8') ?> link is wrong, has expired, or was already used.</p>

<?php if ($what === 'password reset'): ?>
	<a href="<?php echo site_url('forgot-password') ?>" class="btn btn-primary w-100">Ask for a new link</a>
<?php else: ?>
	<p class="small text-body-secondary">Log in with your password: if your address still needs confirming, you will be offered a new link.</p>
	<a href="<?php echo site_url('login') ?>" class="btn btn-primary w-100">Go to log in</a>
<?php endif ?>
