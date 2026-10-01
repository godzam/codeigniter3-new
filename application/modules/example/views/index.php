<?php defined('BASEPATH') OR exit('No direct script access allowed') ?>
<h1 class="h3 mb-3">This is an HMVC module</h1>
<p>Served by <code>application/modules/example/controllers/Example.php</code>, no database required, rendered in the <code>public</code> layout.</p>
<ul>
	<?php foreach ($items as $item): ?>
		<li><?php echo htmlspecialchars($item) ?></li>
	<?php endforeach ?>
</ul>
<p>Widget call: <code><?php echo htmlspecialchars($widget) ?></code></p>
<p class="text-body-secondary">Delete this module (<code>application/modules/example/</code>) once you don't need the reference anymore, or copy its layout to start a real one.</p>
