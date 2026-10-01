<?php

namespace AppTests\Unit;

use App\Auth\PermissionRegistry;
use PHPUnit\Framework\TestCase;

final class PermissionRegistryTest extends TestCase
{
    private function registry(): PermissionRegistry
    {
        return new PermissionRegistry([
            'users' => ['label' => 'Users', 'icon' => 'bi-people', 'permissions' => ['view' => 'View users', 'assign_role' => 'Change roles']],
            'blog' => ['permissions' => ['edit' => 'Edit posts']],
        ]);
    }

    public function testKeysAreModuleDotAction(): void
    {
        $this->assertSame(['users.view', 'users.assign_role', 'blog.edit'], $this->registry()->keys());
    }

    public function testHasOnlyKnownKeys(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('users.view'));
        $this->assertFalse($registry->has('users.delete'));
        $this->assertFalse($registry->has('nope.view'));
        $this->assertFalse($registry->has('users'));
    }

    public function testGroupsDefaultTheirLabelAndIcon(): void
    {
        $groups = $this->registry()->groups();

        $this->assertSame('Users', $groups['users']['label']);
        $this->assertSame('Blog', $groups['blog']['label']);
        $this->assertSame('bi-key', $groups['blog']['icon']);
    }

    public function testLabelFallsBackToTheKey(): void
    {
        $this->assertSame('Change roles', $this->registry()->label('users.assign_role'));
        $this->assertSame('mystery.thing', $this->registry()->label('mystery.thing'));
    }

    public function testFilterDropsUnknownAndDuplicateKeysInRegistryOrder(): void
    {
        $filtered = $this->registry()->filter(['blog.edit', 'bogus.key', 'users.view', 'users.view', 'users.delete']);

        $this->assertSame(['users.view', 'blog.edit'], $filtered);
    }

    public function testAddGroupMergesIntoAnExistingModule(): void
    {
        $registry = $this->registry();
        $registry->addGroup('users', ['permissions' => ['export' => 'Export users']]);

        $this->assertTrue($registry->has('users.export'));
        $this->assertTrue($registry->has('users.view'));
        $this->assertSame('Users', $registry->groups()['users']['label']);
    }

    public function testInvalidNamesAreRejected(): void
    {
        foreach ([['Bad Module', ['permissions' => ['a' => 'x']]], ['ok', ['permissions' => ['Bad-Action' => 'x']]]] as [$module, $group]) {
            try {
                (new PermissionRegistry())->addGroup($module, $group);
                $this->fail('Expected an InvalidArgumentException for '.$module);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid permission', $e->getMessage());
            }
        }
    }

    public function testFromFilesMergesDefinitionsAndSkipsMissingFiles(): void
    {
        $dir = sys_get_temp_dir().'/perm-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/a.php', "<?php \$config['permissions']['users'] = ['label' => 'Users', 'permissions' => ['view' => 'View']];");
        file_put_contents($dir.'/b.php', "<?php \$config['permissions']['users'] = ['permissions' => ['edit' => 'Edit']]; \$config['permissions']['blog'] = ['permissions' => ['view' => 'View']];");

        $registry = PermissionRegistry::fromFiles([$dir.'/a.php', $dir.'/missing.php', $dir.'/b.php']);

        $this->assertSame(['users.view', 'users.edit', 'blog.view'], $registry->keys());

        unlink($dir.'/a.php');
        unlink($dir.'/b.php');
        rmdir($dir);
    }
}
