<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Development exception page (only rendered when debug is on — see
 * application/config/errors.php). Self-contained: no external assets.
 *
 * Available variables: $status_code, $status_text, $exceptions (the
 * exception followed by its previous exceptions, each with 'class',
 * 'message', 'code', 'file', 'line', 'frames'), $trace_text, $request,
 * $app, $queries, $handler (the MY_Exceptions instance).
 */
$e = static function ($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$dump = static function ($value) use ($e) {
	if (is_string($value))
	{
		return $e($value);
	}
	if (is_scalar($value) || $value === NULL)
	{
		return $e(var_export($value, TRUE));
	}
	$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
	return '<pre>'.$e($json !== FALSE ? $json : print_r($value, TRUE)).'</pre>';
};
$table = static function (array $rows, $empty = 'No data.') use ($e, $dump) {
	if (empty($rows))
	{
		return '<p class="empty">'.$e($empty).'</p>';
	}
	$html = '<table class="kv">';
	foreach ($rows as $key => $value)
	{
		$html .= '<tr><th>'.$e($key).'</th><td>'.$dump($value).'</td></tr>';
	}
	return $html.'</table>';
};
$split_class = static function ($class) {
	$pos = strrpos($class, '\\');
	return $pos === FALSE ? array('', $class) : array(substr($class, 0, $pos + 1), substr($class, $pos + 1));
};

$main = $exceptions[0];
list($namespace, $short_class) = $split_class($main['class']);
$main_url = $handler->editor_url($main['file'], $main['line']);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo $e($short_class); ?>: <?php echo $e(strlen($main['message']) > 80 ? substr($main['message'], 0, 80).'…' : $main['message']); ?></title>
	<style>
		:root {
			--bg: #f3f4f6; --panel: #ffffff; --fg: #111827; --muted: #6b7280; --line: #e5e7eb;
			--accent: #dd4814; --accent-soft: #fdece5; --code-bg: #f9fafb; --hl: #fde8df; --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
		}
		@media (prefers-color-scheme: dark) {
			:root { --bg: #0b1120; --panel: #111827; --fg: #e5e7eb; --muted: #9ca3af; --line: #1f2937; --accent: #f07746; --accent-soft: #2a1811; --code-bg: #0f172a; --hl: #3b1d12; }
		}
		* { box-sizing: border-box; }
		body { margin: 0; background: var(--bg); color: var(--fg); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
		a { color: var(--accent); text-decoration: none; }
		a:hover { text-decoration: underline; }
		.wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 64px; }
		.topbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px; }
		.badge { font-size: 12px; padding: 2px 10px; border-radius: 999px; background: var(--panel); border: 1px solid var(--line); color: var(--muted); }
		.badge.status { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
		.panel { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; }
		.header { padding: 24px; border-left: 4px solid var(--accent); }
		.header .ns { color: var(--muted); }
		.header .class { font-size: 15px; font-weight: 600; }
		.header h1 { font-size: 24px; line-height: 1.35; margin: 8px 0 12px; word-break: break-word; font-weight: 600; }
		.location { font-family: var(--mono); font-size: 13px; color: var(--muted); word-break: break-all; }
		.previous { margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--line); }
		.previous div { font-size: 13px; margin-top: 4px; }
		.tabs { display: flex; flex-wrap: wrap; gap: 4px; margin: 24px 0 0; border-bottom: 1px solid var(--line); }
		.tabs button { font: inherit; background: none; border: 0; border-bottom: 2px solid transparent; padding: 8px 14px; color: var(--muted); cursor: pointer; }
		.tabs button.active { color: var(--fg); border-bottom-color: var(--accent); font-weight: 600; }
		.tab { display: none; padding-top: 16px; }
		.tab.active { display: block; }
		.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; color: var(--muted); font-size: 13px; }
		h2 { font-size: 15px; margin: 24px 0 8px; }
		h2:first-child { margin-top: 0; }
		details.frame { margin-bottom: 6px; }
		details.frame > summary { list-style: none; cursor: pointer; padding: 10px 14px; display: flex; gap: 12px; align-items: baseline; }
		details.frame > summary::-webkit-details-marker { display: none; }
		details.frame[open] > summary { border-bottom: 1px solid var(--line); }
		.frame .idx { color: var(--muted); font-family: var(--mono); font-size: 12px; min-width: 24px; }
		.frame .fn { font-family: var(--mono); font-size: 13px; word-break: break-all; }
		.frame .file { font-family: var(--mono); font-size: 12px; color: var(--muted); margin-left: auto; text-align: right; word-break: break-all; }
		.frame.vendor .fn { color: var(--muted); }
		.frame.app > summary { border-left: 3px solid var(--accent); border-radius: 10px 0 0 10px; }
		.hide-vendor .frame.vendor:not(.first) { display: none; }
		.code { margin: 0; overflow-x: auto; background: var(--code-bg); border-radius: 0 0 10px 10px; font: 12.5px/1.6 var(--mono); padding: 8px 0; }
		.code div { white-space: pre; padding: 0 14px; }
		.code div.hl { background: var(--hl); }
		.code .ln { display: inline-block; width: 48px; color: var(--muted); user-select: none; text-align: right; margin-right: 16px; }
		table.kv { width: 100%; border-collapse: collapse; }
		table.kv th, table.kv td { text-align: left; vertical-align: top; padding: 8px 14px; border-bottom: 1px solid var(--line); }
		table.kv tr:last-child th, table.kv tr:last-child td { border-bottom: 0; }
		table.kv th { width: 220px; color: var(--muted); font-weight: 500; word-break: break-all; }
		table.kv td { font-family: var(--mono); font-size: 13px; word-break: break-all; }
		table.kv pre { margin: 0; white-space: pre-wrap; }
		.empty { color: var(--muted); padding: 10px 14px; margin: 0; }
		pre.raw { margin: 0; padding: 14px; overflow-x: auto; font: 12.5px/1.6 var(--mono); white-space: pre-wrap; word-break: break-all; }
		ol.queries { margin: 0; padding: 8px 14px 8px 44px; font: 12.5px/1.6 var(--mono); }
		ol.queries li { padding: 6px 0; border-bottom: 1px solid var(--line); white-space: pre-wrap; word-break: break-all; }
		ol.queries li:last-child { border-bottom: 0; }
		label.toggle { cursor: pointer; user-select: none; }
		.footer { margin-top: 32px; color: var(--muted); font-size: 12px; text-align: center; }
	</style>
</head>
<body>
<div class="wrap">

	<div class="topbar">
		<span class="badge status"><?php echo (int) $status_code; ?> <?php echo $e($status_text); ?></span>
		<?php foreach ($app as $label => $value): ?>
			<?php if (in_array($label, array('Environment', 'PHP version', 'CodeIgniter version'), TRUE)): ?>
				<span class="badge"><?php echo $e($label === 'Environment' ? $value : $label.' '.$value); ?></span>
			<?php endif; ?>
		<?php endforeach; ?>
		<span class="badge"><?php echo $e($request['method']); ?> <?php echo $e($request['url']); ?></span>
	</div>

	<div class="panel header">
		<div class="class"><span class="ns"><?php echo $e($namespace); ?></span><?php echo $e($short_class); ?><?php if ($main['code']): ?> <span class="ns">(code <?php echo $e($main['code']); ?>)</span><?php endif; ?></div>
		<h1><?php echo $main['message'] !== '' ? nl2br($e($main['message'])) : '<span class="ns">(no message)</span>'; ?></h1>
		<div class="location">
			<?php if ($main_url): ?><a href="<?php echo $e($main_url); ?>"><?php endif; ?>
			<?php echo $e($handler->relative_path($main['file'])); ?>:<?php echo (int) $main['line']; ?>
			<?php if ($main_url): ?></a><?php endif; ?>
		</div>

		<?php if (count($exceptions) > 1): ?>
			<div class="previous">
				<strong>Caused by</strong>
				<?php foreach (array_slice($exceptions, 1) as $prev): ?>
					<div><code><?php echo $e($prev['class']); ?></code>: <?php echo $e($prev['message']); ?>
						<span class="location">— <?php echo $e($handler->relative_path($prev['file'])); ?>:<?php echo (int) $prev['line']; ?></span></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="tabs" role="tablist">
		<button class="active" data-tab="trace">Backtrace</button>
		<button data-tab="request">Request</button>
		<button data-tab="app">App</button>
		<?php if ( ! empty($request['session'])): ?><button data-tab="session">Session</button><?php endif; ?>
		<?php if ( ! empty($queries)): ?><button data-tab="queries">Queries (<?php echo count($queries); ?>)</button><?php endif; ?>
		<button data-tab="raw">Raw</button>
	</div>

	<div class="tab active" id="tab-trace">
		<?php foreach ($exceptions as $x => $exception): ?>
			<?php if ($x > 0): ?>
				<h2>Caused by <?php echo $e($exception['class']); ?>: <?php echo $e($exception['message']); ?></h2>
			<?php endif; ?>
			<div class="toolbar">
				<span><?php echo count($exception['frames']); ?> frames</span>
				<label class="toggle"><input type="checkbox" class="vendor-toggle"> Show framework / vendor frames</label>
			</div>
			<div class="frames hide-vendor">
				<?php $opened = FALSE; ?>
				<?php foreach ($exception['frames'] as $i => $frame): ?>
					<?php
					$open = ! $opened && ($i === 0 || ! $frame['vendor']);
					$opened = $opened || $open;
					$classes = 'panel frame '.($frame['vendor'] ? 'vendor' : 'app').($i === 0 ? ' first' : '');
					?>
					<details class="<?php echo $classes; ?>"<?php echo $open ? ' open' : ''; ?>>
						<summary>
							<span class="idx">#<?php echo $i; ?></span>
							<span class="fn"><?php echo $e($frame['function'] !== NULL ? $frame['function'] : '{main}'); ?></span>
							<span class="file">
								<?php if ($frame['editor_url']): ?><a href="<?php echo $e($frame['editor_url']); ?>" onclick="event.stopPropagation()"><?php endif; ?>
								<?php echo $e($frame['relative']); ?>:<?php echo (int) $frame['line']; ?>
								<?php if ($frame['editor_url']): ?></a><?php endif; ?>
							</span>
						</summary>
						<?php if ( ! empty($frame['snippet'])): ?>
							<div class="code"><?php foreach ($frame['snippet'] as $number => $code): ?><div<?php echo $number === (int) $frame['line'] ? ' class="hl"' : ''; ?>><span class="ln"><?php echo $number; ?></span><?php echo $e($code); ?></div><?php endforeach; ?></div>
						<?php else: ?>
							<p class="empty">Source not available.</p>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="tab" id="tab-request">
		<h2>Request</h2>
		<div class="panel"><?php echo $table(array('Method' => $request['method'], 'URL' => $request['url'], 'IP address' => $request['ip'])); ?></div>
		<h2>Headers</h2>
		<div class="panel"><?php echo $table($request['headers']); ?></div>
		<h2>Query string ($_GET)</h2>
		<div class="panel"><?php echo $table($request['get']); ?></div>
		<h2>Body ($_POST)</h2>
		<div class="panel"><?php echo $table($request['post']); ?></div>
		<?php if ( ! empty($request['files'])): ?>
			<h2>Files</h2>
			<div class="panel"><?php echo $table($request['files']); ?></div>
		<?php endif; ?>
		<h2>Cookies</h2>
		<div class="panel"><?php echo $table($request['cookies']); ?></div>
	</div>

	<div class="tab" id="tab-app">
		<div class="panel"><?php echo $table($app); ?></div>
	</div>

	<?php if ( ! empty($request['session'])): ?>
		<div class="tab" id="tab-session">
			<div class="panel"><?php echo $table($request['session']); ?></div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty($queries)): ?>
		<div class="tab" id="tab-queries">
			<div class="panel"><ol class="queries"><?php foreach ($queries as $query): ?><li><?php echo $e($query); ?></li><?php endforeach; ?></ol></div>
		</div>
	<?php endif; ?>

	<div class="tab" id="tab-raw">
		<div class="panel"><pre class="raw"><?php echo $e($trace_text); ?></pre></div>
	</div>

	<div class="footer">
		Shown because debug mode is on (application/config/errors.php). Never enable it in production.
	</div>
</div>

<script>
	document.querySelectorAll('.tabs button').forEach(function (button) {
		button.addEventListener('click', function () {
			document.querySelectorAll('.tabs button, .tab').forEach(function (el) { el.classList.remove('active'); });
			button.classList.add('active');
			document.getElementById('tab-' + button.dataset.tab).classList.add('active');
		});
	});
	document.querySelectorAll('.vendor-toggle').forEach(function (toggle) {
		toggle.addEventListener('change', function () {
			toggle.closest('.toolbar').nextElementSibling.classList.toggle('hide-vendor', ! toggle.checked);
		});
	});
</script>
</body>
</html>
