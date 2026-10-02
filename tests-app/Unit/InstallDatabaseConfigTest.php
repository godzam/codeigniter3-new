<?php

namespace AppTests\Unit;

use App\Install\DatabaseConfig;
use App\Install\Requirements;
use PHPUnit\Framework\TestCase;

final class InstallDatabaseConfigTest extends TestCase
{
    /**
     * Writes the rendered file and loads it the way CodeIgniter does.
     *
     * @param array{host: string, user: string, pass: string, name: string} $db
     *
     * @return array<string, mixed>
     */
    private function load(array $db): array
    {
        $file = tempnam(sys_get_temp_dir(), 'dbcfg');
        file_put_contents($file, DatabaseConfig::render($db));

        $loaded = (static function ($file) {
            $db = [];
            $active_group = null;
            include $file;

            return ['db' => $db, 'group' => $active_group];
        })($file);
        unlink($file);

        $this->assertSame('default', $loaded['group']);

        return $loaded['db']['default'];
    }

    public function testRenderedFileIsValidAndMatchesParams(): void
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', __DIR__);
        }
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'testing');
        }

        $db = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'my_app'];
        $config = $this->load($db);
        $expected = DatabaseConfig::params($db);

        // render() leaves db_debug to the environment; everything else must agree with params().
        unset($config['db_debug'], $expected['db_debug']);
        $this->assertSame($expected, $config);
    }

    public function testValuesCanNeverBecomeCode(): void
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', __DIR__);
        }
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'testing');
        }

        $evil = "pa'ss\"; system('id'); // \\ \$x {\$y} \n<?php echo 1; ?>";
        $config = $this->load(['host' => "h'ost", 'user' => "u\\ser", 'pass' => $evil, 'name' => 'db']);

        $this->assertSame($evil, $config['password']);
        $this->assertSame("h'ost", $config['hostname']);
        $this->assertSame("u\\ser", $config['username']);
    }

    public function testCreateStatementEscapesTheName(): void
    {
        $this->assertSame('CREATE DATABASE IF NOT EXISTS `my_app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', DatabaseConfig::createStatement('my_app'));
        $this->assertStringContainsString('`a``b`', DatabaseConfig::createStatement('a`b'));
    }

    public function testRequirementsJudgeTheFacts(): void
    {
        $facts = [
            'php' => '8.3.1',
            'extensions' => ['mbstring' => true, 'json' => true, 'mysqli' => true, 'fileinfo' => false, 'gd' => true],
            'writable' => ['application/logs' => true, 'uploads' => false],
            'composer' => true,
        ];
        $checks = Requirements::evaluate($facts);

        $this->assertTrue(Requirements::canInstall($checks), 'warnings alone do not block');
        $byLabel = array_column($checks, null, 'label');
        $this->assertFalse($byLabel['PHP extension fileinfo']['ok']);
        $this->assertFalse($byLabel['PHP extension fileinfo']['fatal']);
        $this->assertFalse($byLabel['uploads is writable']['ok']);

        $this->assertFalse(Requirements::canInstall(Requirements::evaluate(['php' => '7.4.0'] + $facts)), 'old PHP blocks');
        $noMb = $facts;
        $noMb['extensions']['mbstring'] = false;
        $this->assertFalse(Requirements::canInstall(Requirements::evaluate($noMb)), 'mbstring is required');
    }
}
