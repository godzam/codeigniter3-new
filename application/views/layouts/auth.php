<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Centered card layout for login, register, and other signed-out forms.
 *
 * @var string $content
 * @var string $page_title
 */
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<?php $this->view('layouts/partials/head', ['page_title' => $page_title, 'extra_head' => $extra_head]) ?>
</head>
<body class="min-vh-100 d-flex flex-column bg-body-tertiary">

	<div class="position-absolute top-0 end-0 p-3"><?php echo theme_toggle() ?></div>

	<main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
		<div class="auth-card">
			<div class="text-center mb-4">
				<a href="<?php echo site_url() ?>" class="d-inline-flex align-items-center text-decoration-none fs-4 text-body"><?php echo app_brand() ?></a>
				<?php if (app_setting('app_tagline') !== ''): ?>
					<div class="small text-body-secondary"><?php echo htmlspecialchars((string) app_setting('app_tagline'), ENT_QUOTES, 'UTF-8') ?></div>
				<?php endif ?>
			</div>
			<div class="card shadow-sm">
				<div class="card-body p-4">
					<?php $this->view('layouts/partials/flash_inline') ?>
					<?php echo $content ?>
				</div>
			</div>
		</div>
	</main>

	<?php echo theme_foot() ?>
	<?php echo $extra_foot ?>
</body>
</html>
