<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Split-screen layout for login, register and other signed-out forms:
 * a brand panel (text from Settings → Login page) next to the form on
 * large screens, a colored header above the form on phones.
 *
 * @var string $content
 * @var string $page_title
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$headline = trim((string) app_setting('auth_headline'));
$subtext = trim((string) app_setting('auth_subtext'));
$points = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/', (string) app_setting('auth_points')) ?: []), 'strlen')), 0, 5);
$tagline = trim((string) app_setting('app_tagline'));

// Only plain paths and http(s) URLs are used as a background (it ends up inside CSS).
$image = trim((string) app_setting('auth_image'));
$image_css = '';
if ($image !== '' && preg_match('#^[\w\-./:%?=&~+@\#]+$#', $image)) {
	$image_css = "background-image:url('".$e(preg_match('#^(https?:)?//#', $image) ? $image : base_url($image))."')";
}
?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<?php $this->view('layouts/partials/head', ['page_title' => $page_title, 'extra_head' => $extra_head]) ?>
</head>
<body>
<div class="auth-shell">

	<aside class="auth-aside<?php echo $image_css !== '' ? ' has-image' : '' ?>"<?php echo $image_css !== '' ? ' style="'.$image_css.'"' : '' ?>>
		<div class="auth-aside-inner">
			<a class="brand-lockup" href="<?php echo site_url() ?>"><?php echo brand_mark() ?><span class="brand-name"><?php echo $e(app_setting('app_name')) ?></span></a>

			<h2 class="auth-headline"><?php echo $e($headline !== '' ? $headline : app_setting('app_name')) ?></h2>
			<?php if ($subtext !== ''): ?><p class="lead"><?php echo $e($subtext) ?></p><?php endif ?>

			<?php if ($points): ?>
				<ul class="auth-points">
					<?php foreach ($points as $point): ?><li><i class="bi bi-check-lg"></i><span><?php echo $e($point) ?></span></li><?php endforeach ?>
				</ul>
			<?php endif ?>

			<div class="auth-preview" aria-hidden="true">
				<div class="row-top"><span><i class="bi bi-graph-up-arrow me-1"></i>This week</span><span>+24%</span></div>
				<div class="bars"><span style="height:38%"></span><span style="height:55%"></span><span style="height:42%"></span><span style="height:70%"></span><span style="height:62%"></span><span style="height:88%"></span><span style="height:76%"></span></div>
			</div>
		</div>
	</aside>

	<div class="auth-main">
		<header class="auth-hero">
			<a class="brand-lockup" href="<?php echo site_url() ?>"><?php echo brand_mark() ?><span class="brand-name"><?php echo $e(app_setting('app_name')) ?></span></a>
			<?php if ($tagline !== ''): ?><p class="tagline"><?php echo $e($tagline) ?></p><?php endif ?>
		</header>

		<div class="auth-top"><?php echo theme_toggle() ?></div>

		<div class="auth-form-wrap">
			<?php $this->view('layouts/partials/flash_inline') ?>
			<?php echo $content ?>
		</div>

		<?php $this->view('layouts/partials/footer', ['fluid' => true, 'class' => 'auth-foot']) ?>
	</div>
</div>

<?php echo theme_foot() ?>
<?php echo $extra_foot ?>
</body>
</html>
