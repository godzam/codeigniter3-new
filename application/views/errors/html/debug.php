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
			--bg: #0f1117; --panel: #181b24; --panel2: #1e222d; --line: #2a2f3c; --fg: #e5e7eb; --muted: #8b93a7;
			--red: #f43f5e; --red-soft: rgba(244,63,94,.16); --accent: #fb7185; --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
		}
		* { box-sizing: border-box; }
		body { margin: 0; background: var(--bg); color: var(--fg); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
		a { color: var(--accent); text-decoration: none; }
		a:hover { text-decoration: underline; }
		.wrap { max-width: 1400px; margin: 0 auto; padding: 24px 20px 64px; }
		.meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px; font-size: 12px; color: var(--muted); }
		.chip { padding: 2px 10px; border-radius: 999px; background: var(--panel); border: 1px solid var(--line); }
		.chip.status { background: var(--red); border-color: var(--red); color: #fff; font-weight: 600; }
		.card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
		.hero { padding: 28px 28px 22px; border-top: 3px solid var(--red); background: linear-gradient(180deg, var(--red-soft), transparent 70%), var(--panel); }
		.hero .cls { font-size: 14px; color: var(--accent); font-weight: 600; }
		.hero .cls .ns { color: var(--muted); font-weight: 400; }
		.hero h1 { margin: 10px 0 14px; font-size: 26px; line-height: 1.3; font-weight: 600; word-break: break-word; }
		.hero .loc { font: 13px var(--mono); color: var(--muted); word-break: break-all; }
		.hero .caused { margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--line); font-size: 13px; color: var(--muted); }
		.hero .caused code { color: var(--fg); font-family: var(--mono); }
		.tabs { display: flex; flex-wrap: wrap; gap: 2px; margin: 20px 0 0; border-bottom: 1px solid var(--line); }
		.tabs button { font: inherit; background: none; border: 0; border-bottom: 2px solid transparent; padding: 9px 16px; color: var(--muted); cursor: pointer; }
		.tabs button:hover { color: var(--fg); }
		.tabs button.active { color: #fff; border-bottom-color: var(--red); font-weight: 600; }
		.tab { display: none; padding-top: 16px; }
		.tab.active { display: block; }
		.trace { display: grid; grid-template-columns: minmax(280px, 38%) 1fr; gap: 16px; align-items: start; }
		@media (max-width: 900px) { .trace { grid-template-columns: 1fr; } }
		.stack { max-height: 640px; overflow-y: auto; }
		.stack .head { display: flex; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 12px; position: sticky; top: 0; background: var(--panel); }
		.stack label { cursor: pointer; user-select: none; }
		.frame { display: block; width: 100%; text-align: left; font: inherit; color: inherit; background: none; border: 0; border-bottom: 1px solid var(--line); border-left: 3px solid transparent; padding: 10px 14px; cursor: pointer; }
		.frame:hover { background: var(--panel2); }
		.frame.active { background: var(--red-soft); border-left-color: var(--red); }
		.frame .fn { display: block; font: 13px var(--mono); word-break: break-all; }
		.frame .fl { display: block; font: 12px var(--mono); color: var(--muted); word-break: break-all; margin-top: 2px; }
		.frame.vendor .fn { color: var(--muted); }
		.hide-vendor .frame.vendor:not(.active) { display: none; }
		.snippet { display: none; }
		.snippet.active { display: block; }
		.snippet .bar { display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--line); font: 12.5px var(--mono); color: var(--muted); word-break: break-all; }
		.snippet .bar b { color: var(--fg); font-weight: 600; }
		.code { margin: 0; overflow-x: auto; background: #0b0d12; font: 13px/1.7 var(--mono); padding: 10px 0; }
		.code div { white-space: pre; padding: 0 16px 0 0; min-width: max-content; }
		.code div.hl { background: var(--red-soft); box-shadow: inset 3px 0 0 var(--red); color: #fff; }
		.code .ln { display: inline-block; width: 56px; padding-right: 16px; text-align: right; color: #566074; user-select: none; }
		.code div.hl .ln { color: var(--accent); }
		.nosrc { padding: 24px 16px; color: var(--muted); margin: 0; }
		h2 { font-size: 14px; margin: 22px 0 8px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
		h2:first-child { margin-top: 0; }
		table.kv { width: 100%; border-collapse: collapse; }
		table.kv th, table.kv td { text-align: left; vertical-align: top; padding: 8px 14px; border-bottom: 1px solid var(--line); }
		table.kv tr:last-child th, table.kv tr:last-child td { border-bottom: 0; }
		table.kv th { width: 220px; color: var(--muted); font-weight: 500; word-break: break-all; }
		table.kv td { font: 13px var(--mono); word-break: break-all; }
		table.kv pre { margin: 0; white-space: pre-wrap; }
		.empty { color: var(--muted); padding: 10px 14px; margin: 0; }
		pre.raw { margin: 0; padding: 16px; overflow-x: auto; font: 12.5px/1.6 var(--mono); white-space: pre-wrap; word-break: break-all; }
		ol.queries { margin: 0; padding: 8px 14px 8px 44px; font: 12.5px/1.6 var(--mono); }
		ol.queries li { padding: 6px 0; border-bottom: 1px solid var(--line); white-space: pre-wrap; word-break: break-all; }
		ol.queries li:last-child { border-bottom: 0; }
		.footer { margin-top: 32px; color: var(--muted); font-size: 12px; text-align: center; }
	</style>
</head>
<body>
<div class="wrap">

	<div class="meta">
		<span class="chip status"><?php echo (int) $status_code; ?> <?php echo $e($status_text); ?></span>
		<span class="chip">PHP <?php echo $e(isset($app['PHP version']) ? $app['PHP version'] : ''); ?></span>
		<span class="chip">CodeIgniter <?php echo $e(isset($app['CodeIgniter version']) ? $app['CodeIgniter version'] : ''); ?></span>
		<span class="chip"><?php echo $e(isset($app['Environment']) ? $app['Environment'] : ''); ?></span>
		<span class="chip"><?php echo $e($request['method']); ?> <?php echo $e($request['url']); ?></span>
	</div>

	<div class="card hero">
		<div class="cls"><span class="ns"><?php echo $e($namespace); ?></span><?php echo $e($short_class); ?><?php if ($main['code']): ?> <span class="ns">(code <?php echo $e($main['code']); ?>)</span><?php endif; ?></div>
		<h1><?php echo $main['message'] !== '' ? nl2br($e($main['message'])) : '<span style="color:var(--muted)">(no message)</span>'; ?></h1>
		<div class="loc">
			<?php if ($main_url): ?><a href="<?php echo $e($main_url); ?>"><?php endif; ?><?php echo $e($handler->relative_path($main['file'])); ?>:<?php echo (int) $main['line']; ?><?php if ($main_url): ?></a><?php endif; ?>
		</div>
		<?php if (count($exceptions) > 1): ?>
			<div class="caused">
				<strong>Caused by</strong>
				<?php foreach (array_slice($exceptions, 1) as $prev): ?>
					<div><code><?php echo $e($prev['class']); ?></code>: <?php echo $e($prev['message']); ?> — <?php echo $e($handler->relative_path($prev['file'])); ?>:<?php echo (int) $prev['line']; ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="tabs" role="tablist">
		<button class="active" data-tab="trace">Stack trace</button>
		<button data-tab="request">Request</button>
		<button data-tab="app">App</button>
		<?php if ( ! empty($request['session'])): ?><button data-tab="session">Session</button><?php endif; ?>
		<?php if ( ! empty($queries)): ?><button data-tab="queries">Queries (<?php echo count($queries); ?>)</button><?php endif; ?>
		<button data-tab="raw">Raw</button>
	</div>

	<div class="tab active" id="tab-trace">
		<?php $frames = $exceptions[0]['frames']; ?>
		<div class="trace">
			<div class="card stack hide-vendor" id="stack">
				<div class="head">
					<span><?php echo count($frames); ?> frames</span>
					<label><input type="checkbox" id="vendor-toggle"> Vendor frames</label>
				</div>
				<?php foreach ($frames as $i => $frame): ?>
					<button type="button" class="frame <?php echo $frame['vendor'] ? 'vendor' : 'app'; ?><?php echo $i === 0 ? ' active' : ''; ?>" data-frame="<?php echo $i; ?>">
						<span class="fn"><?php echo $e($frame['function'] !== NULL ? $frame['function'] : '{main}'); ?></span>
						<span class="fl"><?php echo $e($frame['relative']); ?>:<?php echo (int) $frame['line']; ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="card">
				<?php foreach ($frames as $i => $frame): ?>
					<div class="snippet<?php echo $i === 0 ? ' active' : ''; ?>" id="frame-<?php echo $i; ?>">
						<div class="bar">
							<span><b><?php echo $e($frame['relative']); ?></b>:<?php echo (int) $frame['line']; ?></span>
							<?php if ($frame['editor_url']): ?><a href="<?php echo $e($frame['editor_url']); ?>">Open in editor</a><?php endif; ?>
						</div>
						<?php if ( ! empty($frame['snippet'])): ?>
							<div class="code"><?php foreach ($frame['snippet'] as $number => $code): ?><div<?php echo $number === (int) $frame['line'] ? ' class="hl"' : ''; ?>><span class="ln"><?php echo $number; ?></span><?php echo $e($code); ?></div><?php endforeach; ?></div>
						<?php else: ?>
							<p class="nosrc">Source not available.</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="tab" id="tab-request">
		<h2>Request</h2>
		<div class="card"><?php echo $table(array('Method' => $request['method'], 'URL' => $request['url'], 'IP address' => $request['ip'])); ?></div>
		<h2>Headers</h2>
		<div class="card"><?php echo $table($request['headers']); ?></div>
		<h2>Query string ($_GET)</h2>
		<div class="card"><?php echo $table($request['get']); ?></div>
		<h2>Body ($_POST)</h2>
		<div class="card"><?php echo $table($request['post']); ?></div>
		<?php if ( ! empty($request['files'])): ?>
			<h2>Files</h2>
			<div class="card"><?php echo $table($request['files']); ?></div>
		<?php endif; ?>
		<h2>Cookies</h2>
		<div class="card"><?php echo $table($request['cookies']); ?></div>
	</div>

	<div class="tab" id="tab-app">
		<div class="card"><?php echo $table($app); ?></div>
	</div>

	<?php if ( ! empty($request['session'])): ?>
		<div class="tab" id="tab-session"><div class="card"><?php echo $table($request['session']); ?></div></div>
	<?php endif; ?>

	<?php if ( ! empty($queries)): ?>
		<div class="tab" id="tab-queries">
			<div class="card"><ol class="queries"><?php foreach ($queries as $query): ?><li><?php echo $e($query); ?></li><?php endforeach; ?></ol></div>
		</div>
	<?php endif; ?>

	<div class="tab" id="tab-raw">
		<div class="card"><pre class="raw"><?php echo $e($trace_text); ?></pre></div>
	</div>

	<div class="footer">Shown because debug mode is on (application/config/errors.php). Never enable it in production.</div>
</div>

<script>
	document.querySelectorAll('.tabs button').forEach(function (button) {
		button.addEventListener('click', function () {
			document.querySelectorAll('.tabs button, .tab').forEach(function (el) { el.classList.remove('active'); });
			button.classList.add('active');
			document.getElementById('tab-' + button.dataset.tab).classList.add('active');
		});
	});
	document.querySelectorAll('.frame').forEach(function (frame) {
		frame.addEventListener('click', function () {
			document.querySelectorAll('.frame, .snippet').forEach(function (el) { el.classList.remove('active'); });
			frame.classList.add('active');
			document.getElementById('frame-' + frame.dataset.frame).classList.add('active');
		});
	});
	document.getElementById('vendor-toggle').addEventListener('change', function () {
		document.getElementById('stack').classList.toggle('hide-vendor', ! this.checked);
	});
</script>
</body>
</html>
