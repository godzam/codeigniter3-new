<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @var bool $fluid
 * @var string $class  replaces the default footer styling (used by the auth layout)
 */
$text = trim((string) app_setting('footer_text'));
if ($text === '') {
	$text = '© '.date('Y').' '.app_setting('app_name');
}
?>
<?php if (!empty($class)): ?>
	<footer class="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></footer>
<?php else: ?>
	<footer class="border-top py-3 small text-body-secondary">
		<div class="<?php echo !empty($fluid) ? 'container-fluid' : 'container' ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></div>
	</footer>
<?php endif ?>
