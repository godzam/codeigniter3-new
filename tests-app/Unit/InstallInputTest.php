<?php

namespace AppTests\Unit;

use App\Install\InstallInput;
use PHPUnit\Framework\TestCase;

final class InstallInputTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function valid(array $override = [])
    {
        return $override + [
            'app_name' => ' My Shop ', 'admin_name' => 'Ada Lovelace', 'admin_email' => 'ada@example.com',
            'admin_password' => 'longenough', 'admin_password_confirm' => 'longenough',
            'db_host' => 'localhost', 'db_user' => 'root', 'db_pass' => '', 'db_name' => 'my_shop',
        ];
    }

    public function testValidInputIsTrimmedAndKept(): void
    {
        $r = InstallInput::validate($this->valid(), true);

        $this->assertSame([], $r['errors']);
        $this->assertSame('My Shop', $r['values']['app_name']);
        $this->assertSame('longenough', $r['values']['admin_password']);
        $this->assertSame('my_shop', $r['values']['db_name']);
    }

    public function testDatabaseFieldsAreOnlyRequiredWhenNeeded(): void
    {
        $input = $this->valid(['db_host' => '', 'db_user' => '', 'db_name' => '']);

        $this->assertSame([], InstallInput::validate($input, false)['errors']);
        $this->assertArrayNotHasKey('db_name', InstallInput::validate($input, false)['values']);
        $this->assertEqualsCanonicalizing(['db_host', 'db_user', 'db_name'], array_keys(InstallInput::validate($input, true)['errors']));
    }

    public function testRequiredFields(): void
    {
        $r = InstallInput::validate([], false);

        $this->assertEqualsCanonicalizing(['app_name', 'admin_name', 'admin_email', 'admin_password'], array_keys($r['errors']));
    }

    public function testPasswordRules(): void
    {
        $this->assertArrayHasKey('admin_password', InstallInput::validate($this->valid(['admin_password' => 'short', 'admin_password_confirm' => 'short']), false)['errors']);
        $this->assertArrayHasKey('admin_password', InstallInput::validate($this->valid(['admin_password' => str_repeat('a', 73), 'admin_password_confirm' => str_repeat('a', 73)]), false)['errors']);
        $this->assertArrayHasKey('admin_password_confirm', InstallInput::validate($this->valid(['admin_password_confirm' => 'different1']), false)['errors']);

        $spaces = InstallInput::validate($this->valid(['admin_password' => '  spaced out  ', 'admin_password_confirm' => '  spaced out  ']), false);
        $this->assertSame([], $spaces['errors']);
        $this->assertSame('  spaced out  ', $spaces['values']['admin_password'], 'passwords are not trimmed');
    }

    public function testEmailAndNames(): void
    {
        foreach (['nope', 'a@', 'a b@c.de', ''] as $bad) {
            $this->assertArrayHasKey('admin_email', InstallInput::validate($this->valid(['admin_email' => $bad]), false)['errors'], $bad);
        }
        $this->assertArrayHasKey('app_name', InstallInput::validate($this->valid(['app_name' => str_repeat('x', 61)]), false)['errors']);
        $this->assertArrayHasKey('admin_name', InstallInput::validate($this->valid(['admin_name' => str_repeat('x', 101)]), false)['errors']);
    }

    public function testDatabaseValuesCannotCarryAnythingOdd(): void
    {
        foreach (['bad name', 'a;b', 'x`y', "a\nb", '../x', str_repeat('a', 65), 'ünï'] as $name) {
            $this->assertArrayHasKey('db_name', InstallInput::validate($this->valid(['db_name' => $name]), true)['errors'], var_export($name, true));
        }
        foreach (['bad host', "h'ost", 'a;b', 'ho/st'] as $host) {
            $this->assertArrayHasKey('db_host', InstallInput::validate($this->valid(['db_host' => $host]), true)['errors'], $host);
        }
        $this->assertSame([], InstallInput::validate($this->valid(['db_host' => '127.0.0.1', 'db_name' => 'app-1$']), true)['errors']);
        $this->assertArrayHasKey('db_pass', InstallInput::validate($this->valid(['db_pass' => "a\0b"]), true)['errors']);
    }

    public function testTurnstileIsOffUnlessTheBoxIsTicked(): void
    {
        $r = InstallInput::validate($this->valid(), false);

        $this->assertSame([], $r['errors']);
        $this->assertSame('0', $r['values']['turnstile_enabled']);

        // Keys posted without the box are ignored, never half-saved.
        $r = InstallInput::validate($this->valid(['turnstile_site_key' => '1x00000000000000000000AA']), false);
        $this->assertSame([], $r['errors']);
        $this->assertSame('0', $r['values']['turnstile_enabled']);
        $this->assertSame('', $r['values']['turnstile_site_key']);
        $this->assertSame('', $r['values']['turnstile_secret_key']);
    }

    public function testTurnstileNeedsBothKeysWhenTicked(): void
    {
        $r = InstallInput::validate($this->valid(['turnstile_enabled' => '1']), false);
        $this->assertEqualsCanonicalizing(['turnstile_site_key', 'turnstile_secret_key'], array_keys($r['errors']));

        $r = InstallInput::validate($this->valid(['turnstile_enabled' => '1', 'turnstile_site_key' => '1x00000000000000000000AA']), false);
        $this->assertSame(['turnstile_secret_key'], array_keys($r['errors']));
    }

    public function testTurnstileKeysAreTrimmedAndChecked(): void
    {
        $r = InstallInput::validate($this->valid([
            'turnstile_enabled' => '1',
            'turnstile_site_key' => " 1x00000000000000000000AA\n",
            'turnstile_secret_key' => ' 1x0000000000000000000000000000000AA ',
        ]), false);

        $this->assertSame([], $r['errors']);
        $this->assertSame('1', $r['values']['turnstile_enabled']);
        $this->assertSame('1x00000000000000000000AA', $r['values']['turnstile_site_key']);
        $this->assertSame('1x0000000000000000000000000000000AA', $r['values']['turnstile_secret_key']);

        foreach (['bad key', "a\tb", '<script>', str_repeat('a', 201)] as $bad) {
            $r = InstallInput::validate($this->valid(['turnstile_enabled' => '1', 'turnstile_site_key' => $bad, 'turnstile_secret_key' => 'secret']), false);
            $this->assertArrayHasKey('turnstile_site_key', $r['errors'], var_export($bad, true));
        }
    }

    public function testNonStringInputIsTreatedAsEmpty(): void
    {
        $r = InstallInput::validate(['app_name' => ['x'], 'admin_email' => 5] + $this->valid(), false);

        $this->assertArrayHasKey('app_name', $r['errors']);
        $this->assertArrayHasKey('admin_email', $r['errors']);
    }
}
