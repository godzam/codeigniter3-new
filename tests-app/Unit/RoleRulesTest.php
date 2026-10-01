<?php

namespace AppTests\Unit;

use App\Auth\RoleRules;
use PHPUnit\Framework\TestCase;

final class RoleRulesTest extends TestCase
{
    public function testSuperAdminCannotBeCreatedAgain(): void
    {
        $this->assertStringContainsString('built in', RoleRules::slugError('super_admin'));
    }

    public function testSlugValidation(): void
    {
        $this->assertNull(RoleRules::slugError('editor'));
        $this->assertNull(RoleRules::slugError('content_editor_2'));

        foreach (['', 'a', 'Editor', '1editor', 'has space', 'dash-ed', 'ünï', str_repeat('a', 51)] as $bad) {
            $this->assertNotNull(RoleRules::slugError($bad), var_export($bad, true));
        }
    }

    public function testSlugIsSuggestedFromTheName(): void
    {
        $this->assertSame('content_editor', RoleRules::slugFromName('Content Editor'));
        $this->assertSame('hr_manager', RoleRules::slugFromName('  HR / Manager!  '));
        $this->assertSame('role_2fa_admins', RoleRules::slugFromName('2FA admins'));
        $this->assertSame('', RoleRules::slugFromName('!!!'));
        $this->assertNull(RoleRules::slugError(RoleRules::slugFromName('Content Editor')));
    }

    public function testNameValidation(): void
    {
        $this->assertNull(RoleRules::nameError('Editor'));
        $this->assertNotNull(RoleRules::nameError('   '));
        $this->assertNotNull(RoleRules::nameError(str_repeat('x', 101)));
    }

    public function testSuperAdminCannotBeEditedButOthersCan(): void
    {
        $this->assertNotNull(RoleRules::editBlocker(['slug' => 'super_admin']));
        $this->assertNull(RoleRules::editBlocker(['slug' => 'editor']));
    }

    public function testDeletionRules(): void
    {
        $this->assertStringContainsString('cannot be deleted', RoleRules::deleteBlocker(['slug' => 'super_admin'], 0));
        $this->assertStringContainsString('default role', RoleRules::deleteBlocker(['slug' => 'user', 'is_default' => 1], 0));
        $this->assertStringContainsString('1 user still has', RoleRules::deleteBlocker(['slug' => 'editor'], 1));
        $this->assertStringContainsString('3 users still have', RoleRules::deleteBlocker(['slug' => 'editor'], 3));
        $this->assertNull(RoleRules::deleteBlocker(['slug' => 'editor', 'is_default' => 0], 0));
    }

    public function testSuperAdminMayChangeAnyPermission(): void
    {
        $this->assertSame([], RoleRules::disallowedChanges([], ['a.x', 'b.y'], [], true));
    }

    public function testOthersMayOnlyChangePermissionsTheyHold(): void
    {
        $actor = ['users.view', 'roles.view'];

        // adding one they hold + one they don't
        $this->assertSame(['settings.manage'], RoleRules::disallowedChanges([], ['users.view', 'settings.manage'], $actor, false));
        // removing one they don't hold is also a change they can't make
        $this->assertSame(['settings.manage'], RoleRules::disallowedChanges(['settings.manage', 'users.view'], ['users.view'], $actor, false));
        // untouched permissions they don't hold are fine
        $this->assertSame([], RoleRules::disallowedChanges(['settings.manage'], ['settings.manage', 'users.view'], $actor, false));
    }

    public function testRoleAssignmentRules(): void
    {
        $admin = ['id' => 2, 'is_super_admin' => false];
        $boss = ['id' => 1, 'is_super_admin' => true];
        $user = ['id' => 5, 'role' => 'user'];

        $this->assertNull(RoleRules::assignmentBlocker($admin, $user, 'editor', 1));
        $this->assertNull(RoleRules::assignmentBlocker($admin, $user, 'user', 1), 'unchanged is always fine');
        $this->assertStringContainsString('Only a super admin', RoleRules::assignmentBlocker($admin, $user, 'super_admin', 1));
        $this->assertStringContainsString('Only a super admin', RoleRules::assignmentBlocker($admin, ['id' => 9, 'role' => 'super_admin'], 'user', 2));
        $this->assertStringContainsString('your own role', RoleRules::assignmentBlocker($admin, ['id' => 2, 'role' => 'admin'], 'user', 1));
        $this->assertNull(RoleRules::assignmentBlocker($boss, $user, 'super_admin', 1));
    }

    public function testThereIsAlwaysOneSuperAdmin(): void
    {
        $boss = ['id' => 1, 'is_super_admin' => true];

        $this->assertStringContainsString('at least one super admin', RoleRules::assignmentBlocker($boss, ['id' => 1, 'role' => 'super_admin'], 'admin', 1));
        $this->assertNull(RoleRules::assignmentBlocker($boss, ['id' => 1, 'role' => 'super_admin'], 'admin', 2));
    }
}
