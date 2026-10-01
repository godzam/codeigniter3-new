<?php

namespace App\Auth;

/**
 * The list of permissions the application knows about, grouped by module.
 * A permission key is "module.action" (e.g. "users.view"); roles are granted
 * a subset of these keys on the Roles page.
 *
 * Definitions come from application/config/permissions.php and from any
 * application/modules/<module>/config/permissions.php, so a module declares
 * its own permissions without touching the starter's files:
 *
 *     $config['permissions']['blog'] = array(
 *         'label' => 'Blog',
 *         'icon' => 'bi-journal-text',
 *         'permissions' => array(
 *             'view'   => 'See posts in the admin',
 *             'edit'   => 'Create and edit posts',
 *             'delete' => 'Delete posts',
 *         ),
 *     );
 *
 * Pure PHP (no database): what a role is *granted* is stored separately.
 */
final class PermissionRegistry
{
    /** @var array<string, array{label: string, icon: string, permissions: array<string, string>}> */
    private $groups = [];

    /**
     * @param array<string, array<string, mixed>> $groups module => ['label' =>, 'icon' =>, 'permissions' => [action => label]]
     */
    public function __construct(array $groups = [])
    {
        foreach ($groups as $module => $group) {
            $this->addGroup($module, $group);
        }
    }

    /**
     * Loads every file that defines $config['permissions'] and merges them
     * (later files add to, or override, earlier ones).
     *
     * @param array<int, string> $files
     */
    public static function fromFiles(array $files)
    {
        $registry = new self();

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $config = [];
            include $file;

            foreach ((array) ($config['permissions'] ?? []) as $module => $group) {
                $registry->addGroup($module, (array) $group);
            }
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $group
     */
    public function addGroup($module, array $group)
    {
        $module = (string) $module;

        if (!self::validName($module)) {
            throw new \InvalidArgumentException("Invalid permission module name \"{$module}\" (use lowercase letters, digits and underscores).");
        }

        if (!isset($this->groups[$module])) {
            $this->groups[$module] = [
                'label' => (string) ($group['label'] ?? ucfirst(str_replace('_', ' ', $module))),
                'icon' => (string) ($group['icon'] ?? 'bi-key'),
                'permissions' => [],
            ];
        } else {
            foreach (['label', 'icon'] as $attribute) {
                if (isset($group[$attribute])) {
                    $this->groups[$module][$attribute] = (string) $group[$attribute];
                }
            }
        }

        foreach ((array) ($group['permissions'] ?? []) as $action => $label) {
            $action = (string) $action;

            if (!self::validName($action)) {
                throw new \InvalidArgumentException("Invalid permission action \"{$module}.{$action}\" (use lowercase letters, digits and underscores).");
            }

            $this->groups[$module]['permissions'][$action] = (string) $label;
        }
    }

    /**
     * @return array<string, array{label: string, icon: string, permissions: array<string, string>}>
     */
    public function groups()
    {
        return $this->groups;
    }

    /**
     * Every permission key, e.g. ["users.view", "users.assign_role", ...].
     *
     * @return array<int, string>
     */
    public function keys()
    {
        $keys = [];
        foreach ($this->groups as $module => $group) {
            foreach (array_keys($group['permissions']) as $action) {
                $keys[] = $module.'.'.$action;
            }
        }

        return $keys;
    }

    public function has($key)
    {
        return in_array((string) $key, $this->keys(), true);
    }

    /**
     * The human label of a permission key, or the key itself if unknown.
     */
    public function label($key)
    {
        [$module, $action] = array_pad(explode('.', (string) $key, 2), 2, '');

        return $this->groups[$module]['permissions'][$action] ?? (string) $key;
    }

    /**
     * Keeps only known keys (drops typos, tampered input, and permissions of
     * modules that were removed), without duplicates, in registry order.
     *
     * @param array<int, mixed> $keys
     *
     * @return array<int, string>
     */
    public function filter(array $keys)
    {
        $wanted = array_flip(array_map('strval', $keys));

        return array_values(array_filter($this->keys(), static function ($key) use ($wanted) {
            return isset($wanted[$key]);
        }));
    }

    private static function validName($name)
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $name);
    }
}
