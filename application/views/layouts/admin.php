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
		if (isset($item['section'])) {
			echo '<li class="nav-section" role="presentation">'.htmlspecialchars($item['section'], ENT_QUOTES, 'UTF-8').'</li>';
			continue;
		}

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
<?php $me = current_user() ?>
<div class="admin-shell d-flex">

	<div class="sidebar-backdrop" data-sidebar-toggle aria-hidden="true"></div>

	<aside class="admin-sidebar" id="admin-sidebar" aria-label="Main navigation">
		<div class="sb-brand">
			<a class="brand-lockup" href="<?php echo site_url('dashboard') ?>"><?php echo brand_mark() ?><span class="brand-name"><?php echo htmlspecialchars((string) app_setting('app_name'), ENT_QUOTES, 'UTF-8') ?></span></a>
		</div>
		<nav class="sb-nav">
			<ul class="nav flex-column">
				<?php $render_items(menu_items()) ?>
			</ul>
		</nav>
		<?php if ($me): ?>
			<div class="sb-user">
				<?php echo user_avatar($me['name'], '', 0) ?>
				<div class="who"><strong><?php echo htmlspecialchars((string) $me['name'], ENT_QUOTES, 'UTF-8') ?></strong><span><?php echo htmlspecialchars(get_instance()->rbac->role_label($me['role']), ENT_QUOTES, 'UTF-8') ?></span></div>
				<a class="btn btn-sm" href="<?php echo site_url('logout') ?>" title="Log out" aria-label="Log out"><i class="bi bi-box-arrow-right fs-6"></i></a>
			</div>
		<?php endif ?>
	</aside>

	<div class="flex-grow-1 d-flex flex-column" style="min-width:0">
		<header class="navbar admin-topbar px-2 px-sm-3 <?php echo $filled ? 'navbar-filled' : '' ?>">
			<div class="d-flex align-items-center gap-2 overflow-hidden" style="min-width:0">
				<button class="btn btn-link nav-link topbar-btn d-lg-none" type="button" data-sidebar-toggle aria-label="Toggle menu" aria-controls="admin-sidebar" aria-expanded="false"><i class="bi bi-list fs-3"></i></button>
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
			<div class="d-flex align-items-center gap-1 flex-shrink-0">
				<?php echo theme_toggle() ?>
				<?php $this->view('layouts/partials/user_menu') ?>
			</div>
		</header>

		<main class="flex-grow-1 admin-main">
			<div class="<?php echo $fluid ? '' : 'container-xxl px-0' ?>">
				<?php if ($page_title !== ''): ?>
					<div class="page-head">
						<h1><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
						<?php if ($page_subtitle !== ''): ?><p><?php echo htmlspecialchars($page_subtitle, ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>
					</div>
				<?php endif ?>
				<?php $this->view('layouts/partials/flash_inline') ?>
				<?php echo $content ?>
			</div>
		</main>

		<?php $this->view('layouts/partials/footer', ['fluid' => true, 'class' => 'admin-footer']) ?>
	</div>
</div>

<?php echo theme_foot() ?>
<?php echo $extra_foot ?>
</body>
</html>
