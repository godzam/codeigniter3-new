<?php

namespace App\Auth;

/**
 * The business rules around roles, as pure functions so they can be tested
 * without a database. Each "...Blocker"/"...Error" method returns a message
 * explaining what's wrong, or null when the action is allowed.
 *
 *  - `super_admin` is the top role: it implicitly holds every permission and
 *    is created by the migration. It cannot be added, edited or deleted.
 *  - Anyone else may only hand out permissions they hold themselves, and only
 *    a super admin can grant, take away, or change the role of a super admin.
 */
final class RoleRules
{
    public const SUPER_ADMIN = 'super_admin';

    public static function isSuperAdmin($slug)
    {
        return (string) $slug === self::SUPER_ADMIN;
    }

    /**
     * Validation for the slug of a new role. (The slug of an existing role
     * never changes, because users reference it.)
     *
     * @return string|null
     */
    public static function slugError($slug)
    {
        $slug = (string) $slug;

        if ($slug === '') {
            return 'The role key is required.';
        }
        if (self::isSuperAdmin($slug)) {
            return 'The "super_admin" role is built in and cannot be created again.';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $slug)) {
            return 'The role key must be 2-50 characters: lowercase letters, digits and underscores, starting with a letter.';
        }

        return null;
    }

    /**
     * A key suggested from a role's display name ("Content Editor" -> "content_editor").
     */
    public static function slugFromName($name)
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) $name), '_'));

        return preg_match('/^[a-z]/', $slug) ? substr($slug, 0, 50) : ($slug === '' ? '' : substr('role_'.$slug, 0, 50));
    }

    /**
     * @return string|null
     */
    public static function nameError($name)
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'The role name is required.';
        }
        if (mb_strlen($name) > 100) {
            return 'The role name must be at most 100 characters.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $role a roles row (needs 'slug')
     *
     * @return string|null
     */
    public static function editBlocker(array $role)
    {
        return self::isSuperAdmin($role['slug'] ?? '')
            ? 'The super_admin role always has every permission and cannot be changed.'
            : null;
    }

    /**
     * @param array<string, mixed> $role      a roles row (needs 'slug', optional 'is_default')
     * @param int                  $userCount how many users currently have this role
     *
     * @return string|null
     */
    public static function deleteBlocker(array $role, $userCount)
    {
        if (self::isSuperAdmin($role['slug'] ?? '')) {
            return 'The super_admin role cannot be deleted.';
        }
        if (!empty($role['is_default'])) {
            return 'This is the default role for new sign-ups. Make another role the default first.';
        }
        if ($userCount > 0) {
            return $userCount === 1
                ? '1 user still has this role. Move them to another role first.'
                : "{$userCount} users still have this role. Move them to another role first.";
        }

        return null;
    }

    /**
     * Permissions the actor is trying to add or remove (the difference
     * between the old and new sets) that they don't hold themselves. A super
     * admin may change anything. Stops an admin from escalating their own
     * power, or someone else's, past what they have.
     *
     * @param array<int, string> $old
     * @param array<int, string> $new
     * @param array<int, string> $actorPermissions
     *
     * @return array<int, string>
     */
    public static function disallowedChanges(array $old, array $new, array $actorPermissions, $actorIsSuperAdmin)
    {
        if ($actorIsSuperAdmin) {
            return [];
        }

        $changed = array_merge(array_diff($new, $old), array_diff($old, $new));

        return array_values(array_diff(array_unique($changed), $actorPermissions));
    }

    /**
     * Can the actor give this user a new role?
     *
     * @param array{id: int, is_super_admin: bool} $actor
     * @param array{id: int, role: string}         $target
     * @param int                                  $superAdminCount how many users hold super_admin right now
     *
     * @return string|null
     */
    public static function assignmentBlocker(array $actor, array $target, $newRole, $superAdminCount)
    {
        $targetIsSuper = self::isSuperAdmin($target['role']);
        $newIsSuper = self::isSuperAdmin($newRole);

        if ($newRole === $target['role']) {
            return null;
        }
        if (($newIsSuper || $targetIsSuper) && !$actor['is_super_admin']) {
            return 'Only a super admin can grant or change the super_admin role.';
        }
        if ($actor['id'] === $target['id'] && !$actor['is_super_admin']) {
            return 'You cannot change your own role.';
        }
        if ($targetIsSuper && !$newIsSuper && $superAdminCount <= 1) {
            return 'There must always be at least one super admin.';
        }

        return null;
    }
}
