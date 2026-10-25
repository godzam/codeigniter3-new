<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <h3>Reset Password</h3>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($msg)): ?>
        <div class="alert alert-success">
            <?= $msg ?>
        </div>
        <?php endif; ?>
        
        <?= form_open('auth/do_reset_password/' . $user->id . '/' . $user->password_reset_token) ?>
        <input type="hidden" name="id" value="<?= $user->id ?>">
        <input type="hidden" name="token" value="<?= $user->password_reset_token ?>">
        
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" name="password" class="form-control" id="password" required minlength="8">
            <small class="form-text text-body-secondary">Minimum 8 characters</small>
        </div>
        
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control" id="password_confirm" required>
        </div>
        
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        <?= form_close() ?>
        
        <p class="mt-3 small">
            <a href="<?= site_url('auth/login') ?>">Back to login</a><br>
            Don't have an account? <a href="<?= site_url('auth/register') ?>">Register</a>
        </p>
    </div>
</div>