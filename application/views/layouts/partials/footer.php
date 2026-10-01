<?php defined('BASEPATH') OR exit('No direct script access allowed');
$text = trim((string) app_setting('footer_text'));
if ($text === '') {
	$text = '© '.date('Y').' '.app_setting('app_name');
}
?>
<footer class="border-top py-3 small text-body-secondary">
	<div class="<?php echo !empty($fluid) ? 'container-fluid' : 'container' ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></div>
</footer>
