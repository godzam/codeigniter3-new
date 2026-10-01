<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** @var string $page_title */
$app_name = (string) app_setting('app_name');
$title = ($page_title !== '' ? $page_title.' · ' : '').$app_name;
?>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<?php echo theme_head() ?>
<?php echo $extra_head ?>
