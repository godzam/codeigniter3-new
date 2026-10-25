<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <h3>Forgot Password</h3>
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
        
        <?= form_open('auth/forgot_password') ?>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" class="form-control" id="email" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
        <?= form_close() ?>
        
        <p class="mt-3 small">
            Don't have an account? <a href="<?= site_url('auth/register') ?>">Register</a><br>
            Already logged in? <a href="<?= site_url('auth/logout') ?>">Log out</a>
        </p>
    </div>
</div>