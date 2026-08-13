<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Example HMVC module</title>
	<style>
		body { font: 16px/1.5 -apple-system, Segoe UI, Helvetica, Arial, sans-serif; max-width: 640px; margin: 60px auto; color: #333; }
		code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
		ul { padding-left: 20px; }
	</style>
</head>
<body>
	<h1>This is an HMVC module</h1>
	<p>Served by <code>application/modules/example/controllers/Example.php</code>, no database required.</p>
	<ul>
		<?php foreach ($items as $item): ?>
			<li><?php echo htmlspecialchars($item) ?></li>
		<?php endforeach ?>
	</ul>
	<p>Widget call: <code><?php echo htmlspecialchars($widget) ?></code></p>
	<p>Delete this module (<code>application/modules/example/</code>) once you don't need the reference anymore, or copy its layout to start a real one.</p>
</body>
</html>
