<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Log in</title>
	<style>
		body { font: 16px/1.5 -apple-system, Segoe UI, Helvetica, Arial, sans-serif; max-width: 400px; margin: 60px auto; color: #333; }
		label { display: block; margin-top: 12px; font-weight: 600; }
		input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; }
		button { margin-top: 20px; padding: 8px 16px; }
		.error { color: #b00020; }
	</style>
</head>
<body>
	<h1>Log in</h1>

	<?php if (!empty($error)): ?>
		<p class="error"><?php echo htmlspecialchars($error) ?></p>
	<?php endif ?>
	<?php echo validation_errors('<p class="error">', '</p>') ?>

	<?php echo form_open('login') ?>
		<label for="email">Email</label>
		<input type="email" name="email" id="email" value="<?php echo set_value('email') ?>" required>

		<label for="password">Password</label>
		<input type="password" name="password" id="password" required>

		<button type="submit">Log in</button>
	<?php echo form_close() ?>

	<p>No account? <a href="<?php echo site_url('register') ?>">Register</a></p>
</body>
</html>
