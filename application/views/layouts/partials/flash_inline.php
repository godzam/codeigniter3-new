<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Server-side form errors (validation + an optional $error string) as
 * Bootstrap alerts. Success/info messages use flash() and show up as
 * SweetAlert2 toasts instead — see assets/js/app.js.
 */
?>
<?php if (!empty($error)): ?>
	<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>
<?php if (function_exists('validation_errors') && validation_errors()): ?>
	<div class="alert alert-danger" role="alert"><?php echo validation_errors('<div>', '</div>') ?></div>
<?php endif ?>
