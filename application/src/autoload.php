<?php

/**
 * Minimal PSR-4 autoloader for the App\ namespace (application/src/), so
 * the classes there work even before `composer install` has run. When
 * Composer's autoloader is present it has usually loaded them already and
 * this is a no-op.
 */
spl_autoload_register(static function ($class) {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require_once $file;
    }
});
