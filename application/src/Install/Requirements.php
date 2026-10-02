<?php

namespace App\Install;

/**
 * Turns facts about the server into the checklist shown by the installer.
 * The facts are gathered by the Installer library; this class only judges
 * them, so it is unit-tested.
 */
final class Requirements
{
    public const MIN_PHP = '8.1.0';

    /**
     * @param array{php: string, extensions: array<string, bool>, writable: array<string, bool>, composer: bool} $facts
     *
     * @return array<int, array{label: string, ok: bool, fatal: bool, hint: string}>
     */
    public static function evaluate(array $facts)
    {
        $list = [];
        $add = static function ($label, $ok, $fatal, $hint = '') use (&$list) {
            $list[] = ['label' => $label, 'ok' => (bool) $ok, 'fatal' => (bool) $fatal, 'hint' => $hint];
        };

        $add('PHP '.$facts['php'], version_compare($facts['php'], self::MIN_PHP, '>='), true, 'PHP '.self::MIN_PHP.' or newer is required.');
        $add('Composer packages installed', $facts['composer'], false, 'Run "composer install" in the project folder.');

        $extensions = [
            'mbstring' => [true, 'Required: enable the mbstring extension in php.ini.'],
            'json' => [true, 'Required: enable the json extension in php.ini.'],
            'mysqli' => [false, 'Needed for MySQL / MariaDB (Laragon): enable mysqli in php.ini.'],
            'fileinfo' => [false, 'Used to check uploaded files: enable fileinfo in php.ini.'],
            'gd' => [false, 'Used to compress uploaded images: enable gd in php.ini.'],
        ];
        foreach ($extensions as $name => [$fatal, $hint]) {
            $add('PHP extension '.$name, $facts['extensions'][$name] ?? false, $fatal, $hint);
        }

        foreach ($facts['writable'] as $path => $writable) {
            $add($path.' is writable', $writable, false, 'Make this folder writable by the web server.');
        }

        return $list;
    }

    /**
     * @param array<int, array{ok: bool, fatal: bool}> $checks
     */
    public static function canInstall(array $checks)
    {
        foreach ($checks as $check) {
            if (!$check['ok'] && $check['fatal']) {
                return false;
            }
        }

        return true;
    }
}
