<?php defined('BASEPATH') OR exit('No direct script access allowed');/**
 * A built page as the public sees it: the sections' HTML, nothing else.
 *
 * @var array<string, mixed> $page
 * @var string $content      SectionTypes::renderAll()
 * @var bool   $preview      a draft being previewed by an editor
 * @var string|null $edit_url
 */
$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<?php if (!empty($preview)): ?>
	<div class="alert alert-warning py-2 small">
		<i class="bi bi-eye me-1"></i>Draft preview — only editors can see this.
		<?php if (!empty($edit_url)): ?><a href="<?php echo $e($edit_url) ?>" class="alert-link ms-1">Edit this page</a><?php endif ?>
	</div>
<?php endif ?>

<div class="page-builder-page">
	<?php echo $content ?>
</div>

<?php if (trim($content) === ''): ?>
	<p class="text-body-secondary">This page has no sections yet.</p>
<?php endif ?>
