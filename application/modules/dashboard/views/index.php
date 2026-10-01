<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** @var array $user */
$name = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
?>
<div class="row g-3 mb-4">
	<?php foreach ([
		['bi-people', 'Users', '—', 'primary'],
		['bi-activity', 'Activity', '—', 'secondary'],
		['bi-graph-up-arrow', 'Growth', '—', 'success'],
		['bi-bell', 'Alerts', '—', 'warning'],
	] as [$icon, $label, $value, $color]): ?>
		<div class="col-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body d-flex align-items-center gap-3">
					<div class="rounded-3 p-3 text-bg-<?php echo $color ?>"><i class="bi <?php echo $icon ?> fs-4"></i></div>
					<div>
						<div class="small text-body-secondary"><?php echo $label ?></div>
						<div class="fs-4 fw-semibold"><?php echo $value ?></div>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach ?>
</div>

<div class="card">
	<div class="card-body">
		<h2 class="h5">Welcome, <?php echo $name ?></h2>
		<p class="mb-3 text-body-secondary">This page is <code>application/modules/dashboard/</code>, rendered in the admin layout. The sidebar comes from <code>application/config/menu.php</code>.</p>
		<button type="button" class="btn btn-primary" onclick="App.toast('success', 'SweetAlert2 toast works')">Show a toast</button>
		<button type="button" class="btn btn-outline-danger" onclick="App.confirm({title: 'Delete this item?', text: 'You will not be able to undo this.', icon: 'warning'}).then(function (r) { if (r.isConfirmed) App.toast('info', 'Deleted (demo only)'); })">Ask for confirmation</button>
	</div>
</div>
