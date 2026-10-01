<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Admin layout: sidebar (items from application/config/menu.php), top bar
 * with breadcrumbs, theme toggle and user menu, and the content area.
 *
 * @var string $content
 * @var string $page_title
 * @var array  $breadcrumbs label => url|null
 */
$fluid = app_setting('layout_fluid');
$filled = app_setting('layout_navbar') === 'primary';
$render_items = static function (array $items) use (&$render_items) {
	foreach ($items as $item) {
		$icon = '<i class="bi '.htmlspecialchars($item['icon'] ?? 'bi-dot', ENT_QUOTES, 'UTF-8').'"></i>';
		$label = '<span class="nav-label">'.htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8').'</span>';

		if (!empty($item['children'])) {
			$id = 'menu-'.substr(md5($item['label']), 0, 8);
			echo '<li class="nav-item"><a class="nav-link'.(!empty($item['active']) ? ' active' : '').'" data-bs-toggle="collapse" href="#'.$id.'" role="button" aria-expanded="'.(!empty($item['active']) ? 'true' : 'false').'">'
				.$icon.$label.'<i class="bi bi-chevron-down ms-auto nav-label small"></i></a>'
				.'<ul class="nav flex-column ms-3 collapse'.(!empty($item['active']) ? ' show' : '').'" id="'.$id.'">';
			$render_items($item['children']);
			echo '</ul></li>';
			continue;
		}

		echo '<li class="nav-item"><a class="nav-link'.(!empty($item['active']) ? ' active' : '').'" href="'.site_url($item['url'] ?? '').'" title="'.htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8').'">'.$icon.$label.'</a></li>';
	}
};
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<?php $this->view('layouts/partials/head', ['page_title' => $page_title, 'extra_head' => $extra_head]) ?>
</head>
<body data-sidebar="<?php echo htmlspecialchars((string) app_setting('layout_sidebar'), ENT_QUOTES, 'UTF-8') ?>">
<div class="admin-shell d-flex">

	<aside class="admin-sidebar d-flex flex-column p-2" id="admin-sidebar">
		<a class="d-flex align-items-center text-decoration-none text-body px-2 py-3 fs-5" href="<?php echo site_url('dashboard') ?>"><?php echo app_brand() ?></a>
		<ul class="nav flex-column gap-1">
			<?php $render_items(menu_items()) ?>
		</ul>
	</aside>

	<div class="flex-grow-1 d-flex flex-column min-vw-0" style="min-width:0">
		<header class="navbar border-bottom px-3 <?php echo $filled ? 'navbar-filled' : 'bg-body-tertiary' ?>">
			<div class="d-flex align-items-center gap-2">
				<button class="btn btn-link nav-link d-lg-none px-1" type="button" data-sidebar-toggle aria-label="Toggle menu"><i class="bi bi-list fs-3"></i></button>
				<?php if ($breadcrumbs): ?>
					<nav aria-label="breadcrumb">
						<ol class="breadcrumb mb-0">
							<?php foreach ($breadcrumbs as $label => $url): ?>
								<?php if ($url === null): ?>
									<li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
								<?php else: ?>
									<li class="breadcrumb-item"><a href="<?php echo site_url($url) ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
								<?php endif ?>
							<?php endforeach ?>
						</ol>
					</nav>
				<?php endif ?>
			</div>
			<div class="d-flex align-items-center gap-2">
				<?php echo theme_toggle() ?>
				<?php $this->view('layouts/partials/user_menu') ?>
			</div>
		</header>

		<main class="flex-grow-1 p-3 p-lg-4">
			<div class="<?php echo $fluid ? '' : 'container-xxl px-0' ?>">
				<?php if ($page_title !== ''): ?><h1 class="h3 mb-4"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1><?php endif ?>
				<?php $this->view('layouts/partials/flash_inline') ?>
				<?php echo $content ?>
			</div>
		</main>

		<?php $this->view('layouts/partials/footer', ['fluid' => true]) ?>
	</div>
</div>

<?php echo theme_foot() ?>
<?php echo $extra_foot ?>
</body>
</html>
