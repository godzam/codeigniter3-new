<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base layout for the error pages ({status}.php, 4xx.php, 5xx.php).
 * Rendered directly for any status code without its own view.
 *
 * Available variables:
 *   $status_code      int     e.g. 404
 *   $status_text      string  e.g. 'Not Found'
 *   $message          string  message from abort()/show_error() ('' if none)
 *   $title            string  optional, set by the including view
 *   $default_message  string  optional, shown when $message is empty
 */
$title = isset($title) ? $title : $status_text;
$message = ($message !== '') ? $message : (isset($default_message) ? $default_message : '');
$home = function_exists('base_url') ? base_url() : '/';
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo (int) $status_code; ?> | <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
	<style>
		:root { --bg: #f8fafc; --fg: #1f2937; --muted: #6b7280; --line: #d1d5db; --accent: #dd4814; }
		@media (prefers-color-scheme: dark) {
			:root { --bg: #111827; --fg: #e5e7eb; --muted: #9ca3af; --line: #374151; --accent: #f07746; }
		}
		* { box-sizing: border-box; }
		html, body { height: 100%; margin: 0; }
		body {
			display: flex; align-items: center; justify-content: center;
			background: var(--bg); color: var(--fg);
			font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			padding: 16px;
		}
		.box { text-align: center; max-width: 560px; }
		.head { display: flex; align-items: center; justify-content: center; gap: 16px; }
		.code { font-size: 20px; font-weight: 600; letter-spacing: .05em; color: var(--muted); padding-right: 16px; border-right: 1px solid var(--line); }
		.title { font-size: 18px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
		.message { margin: 24px 0 0; line-height: 1.6; }
		a { display: inline-block; margin-top: 24px; color: var(--accent); text-decoration: none; font-size: 14px; }
		a:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<div class="box">
		<div class="head">
			<div class="code"><?php echo (int) $status_code; ?></div>
			<div class="title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
		</div>
		<?php if ($message !== ''): ?>
			<p class="message"><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></p>
		<?php endif; ?>
		<a href="<?php echo htmlspecialchars($home, ENT_QUOTES, 'UTF-8'); ?>">&larr; Back to home</a>
	</div>
</body>
</html>
