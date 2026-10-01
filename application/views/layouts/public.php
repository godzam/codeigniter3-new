<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Public site layout: top navbar, centered content, footer. Pages render
 * into it with $this->template->render('view', $data, 'public').
 *
 * @var string $content   the page's own HTML
 * @var string $page_title
 */
$fluid = app_setting('layout_fluid');
$filled = app_setting('layout_navbar') === 'primary';
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<?php $this->view('layouts/partials/head', ['page_title' => $page_title, 'extra_head' => $extra_head]) ?>
</head>
<body class="d-flex flex-column min-vh-100">

	<nav class="navbar navbar-expand-md border-bottom <?php echo $filled ? 'navbar-filled' : 'bg-body-tertiary' ?>">
		<div class="<?php echo $fluid ? 'container-fluid' : 'container' ?>">
			<a class="navbar-brand d-flex align-items-center" href="<?php echo site_url() ?>"><?php echo app_brand() ?></a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#public-nav" aria-controls="public-nav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="public-nav">
				<ul class="navbar-nav me-auto">
					<li class="nav-item"><a class="nav-link" href="<?php echo site_url() ?>">Home</a></li>
					<?php if (is_logged_in()): ?>
						<li class="nav-item"><a class="nav-link" href="<?php echo site_url('dashboard') ?>">Dashboard</a></li>
					<?php endif ?>
				</ul>
				<div class="d-flex align-items-center gap-2">
					<?php echo theme_toggle() ?>
					<?php $this->view('layouts/partials/user_menu') ?>
				</div>
			</div>
		</div>
	</nav>

	<main class="flex-grow-1 py-4">
		<div class="<?php echo $fluid ? 'container-fluid' : 'container' ?>">
			<?php $this->view('layouts/partials/flash_inline') ?>
			<?php echo $content ?>
		</div>
	</main>

	<?php $this->view('layouts/partials/footer', ['fluid' => $fluid]) ?>

	<?php echo theme_foot() ?>
	<?php echo $extra_foot ?>
</body>
</html>
