<?php

namespace App\Install;

/**
 * Checks what the installer form posts. Pure PHP, so it is unit-tested.
 *
 * Database fields are only required (and only returned) when the installer
 * has to write a database connection itself.
 */
final class InstallInput
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public static function validate(array $input, $needsDatabase)
    {
        $get = static function ($key) use ($input) {
            return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
        };

        $errors = [];
        $values = [
            'app_name' => trim($get('app_name')),
            'admin_name' => trim($get('admin_name')),
            'admin_email' => trim($get('admin_email')),
            'admin_password' => $get('admin_password'),
        ];

        if ($values['app_name'] === '' || mb_strlen($values['app_name']) > 60) {
            $errors['app_name'] = 'Enter a name of up to 60 characters.';
        }
        if ($values['admin_name'] === '' || mb_strlen($values['admin_name']) > 100) {
            $errors['admin_name'] = 'Enter your name (up to 100 characters).';
        }
        if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($values['admin_email']) > 190) {
            $errors['admin_email'] = 'Enter a valid email address.';
        }

        $password = $values['admin_password'];
        if (strlen($password) < 8) {
            $errors['admin_password'] = 'Use at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors['admin_password'] = 'Use at most 72 characters.';
        } elseif ($password !== $get('admin_password_confirm')) {
            $errors['admin_password_confirm'] = 'The two passwords are not the same.';
        }

        if ($needsDatabase) {
            $values['db_host'] = trim($get('db_host'));
            $values['db_user'] = trim($get('db_user'));
            $values['db_pass'] = $get('db_pass');
            $values['db_name'] = trim($get('db_name'));

            if (!preg_match('/^[A-Za-z0-9._\-]{1,255}$/', $values['db_host'])) {
                $errors['db_host'] = 'Enter the server name, e.g. localhost or 127.0.0.1.';
            }
            if ($values['db_user'] === '' || strlen($values['db_user']) > 80 || preg_match('/[\x00-\x1f]/', $values['db_user'])) {
                $errors['db_user'] = 'Enter the database user (Laragon: root).';
            }
            if (strlen($values['db_pass']) > 255 || strpos($values['db_pass'], "\0") !== false) {
                $errors['db_pass'] = 'That password is not usable.';
            }
            if (!preg_match('/^[A-Za-z0-9_$\-]{1,64}$/', $values['db_name'])) {
                $errors['db_name'] = 'Use letters, digits, _ and - only (up to 64 characters).';
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }
}
