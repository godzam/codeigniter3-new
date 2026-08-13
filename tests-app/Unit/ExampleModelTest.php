<?php

namespace AppTests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A pure unit test — no CI3 bootstrap, no HTTP request, no database.
 * Example_model deliberately doesn't touch $this->db, so CI_Model's
 * (effectively empty) constructor is all it needs to be instantiated
 * directly. Most CI3 models/controllers *do* need the full framework
 * bootstrap; for those, write a Feature test instead (see
 * tests-app/Feature/HttpSmokeTest.php).
 */
final class ExampleModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('BASEPATH')) {
            define('BASEPATH', dirname(__DIR__, 2).'/system/');
        }

        require_once dirname(__DIR__, 2).'/system/core/Model.php';
        require_once dirname(__DIR__, 2).'/application/modules/example/models/Example_model.php';
    }

    public function testGetSampleItemsReturnsANonEmptyList(): void
    {
        $model = new \Example_model();

        $items = $model->get_sample_items();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertContainsOnly('string', $items);
    }
}
