<?php

/**
 * Loads the App\Exceptions classes without relying on Composer, so the
 * error handling (and abort()) keeps working on an install that hasn't
 * run `composer install` yet. With Composer present, PSR-4 autoloading
 * has usually loaded them already and this is a no-op.
 */
foreach (['HttpException', 'PageNotFoundException'] as $class) {
    if (!class_exists('App\\Exceptions\\'.$class, false)) {
        require_once __DIR__.'/'.$class.'.php';
    }
}
