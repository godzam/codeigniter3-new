<?php

namespace App\Audit;

/**
 * How the audit log names things on screen. Pure PHP (unit-tested).
 */
final class Labels
{
    /**
     * @return array{label: string, tone: string} tone is a pill-* suffix
     */
    public static function action($action)
    {
        $known = [
            'create' => ['Added', 'success'],
            'update' => ['Changed', 'primary'],
            'delete' => ['Deleted', 'danger'],
            'login' => ['Signed in', 'secondary'],
            'logout' => ['Signed out', 'secondary'],
            'login.locked' => ['Blocked', 'danger'],
            'login.unblocked' => ['Unblocked', 'success'],
            'install' => ['Installed', 'primary'],
        ];

        if (isset($known[$action])) {
            return ['label' => $known[$action][0], 'tone' => $known[$action][1]];
        }

        return ['label' => ucfirst(str_replace(['.', '_'], ' ', (string) $action)), 'tone' => 'secondary'];
    }

    /**
     * "users" => "User"; a generated module's key is shown as it is.
     */
    public static function entity($entity)
    {
        $known = [
            'users' => 'User',
            'roles' => 'Role',
            'settings' => 'Settings',
            'generator' => 'Module',
            'auth' => 'Sign-in',
            'security' => 'Login security',
            'installer' => 'Installer',
        ];

        return $known[$entity] ?? (string) $entity;
    }

    /**
     * Which blocks of the stored JSON to show, and under which heading.
     *
     * @return array<string, string> key in the JSON => heading
     */
    public static function sections()
    {
        return ['new' => 'What was added', 'changes' => 'What changed', 'old' => 'What was removed', 'data' => 'Details'];
    }
}
