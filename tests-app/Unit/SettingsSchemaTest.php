<?php

namespace AppTests\Unit;

use App\Settings\Schema;
use PHPUnit\Framework\TestCase;

final class SettingsSchemaTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            'general' => [
                'label' => 'General',
                'fields' => [
                    'app_name' => ['label' => 'Name', 'type' => 'text', 'default' => 'My App', 'required' => true, 'max_length' => 10],
                    'api_secret' => ['label' => 'Secret', 'type' => 'password', 'default' => ''],
                ],
            ],
            'appearance' => [
                'label' => 'Appearance',
                'fields' => [
                    'color' => ['label' => 'Color', 'type' => 'color', 'default' => '#0d6efd'],
                    'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'auto', 'options' => ['auto' => 'Auto', 'dark' => 'Dark']],
                    'toggle' => ['label' => 'Toggle', 'type' => 'switch', 'default' => true],
                    'size' => ['label' => 'Size', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 10],
                ],
            ],
        ]);
    }

    public function testDefaultsAreCastToTheirTypes(): void
    {
        $this->assertSame(
            ['app_name' => 'My App', 'api_secret' => '', 'color' => '#0d6efd', 'mode' => 'auto', 'toggle' => true, 'size' => 5],
            $this->schema()->defaults()
        );
    }

    public function testCastHandlesStoredStrings(): void
    {
        $schema = $this->schema();

        $this->assertTrue($schema->cast('toggle', '1'));
        $this->assertFalse($schema->cast('toggle', '0'));
        $this->assertSame(7, $schema->cast('size', '7'));
        $this->assertSame('x', $schema->cast('app_name', 'x'));
    }

    public function testValidInputProducesNormalizedValues(): void
    {
        $result = $this->schema()->validate([
            'app_name' => '  Acme  ', 'api_secret' => 's3cret', 'color' => '#ABC', 'mode' => 'dark', 'toggle' => '1', 'size' => '3',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            ['app_name' => 'Acme', 'api_secret' => 's3cret', 'color' => '#aabbcc', 'mode' => 'dark', 'toggle' => '1', 'size' => '3'],
            $result['values']
        );
    }

    public function testInvalidInputIsReportedPerField(): void
    {
        $result = $this->schema()->validate([
            'app_name' => '', 'color' => 'nope', 'mode' => 'hacker', 'size' => '99',
        ]);

        $this->assertSame(['app_name', 'color', 'mode', 'size'], array_keys($result['errors']));
        $this->assertArrayNotHasKey('color', $result['values']);
    }

    public function testTooLongTextIsRejected(): void
    {
        $result = $this->schema()->validate(['app_name' => str_repeat('a', 11), 'color' => '#fff', 'mode' => 'auto', 'size' => 1]);

        $this->assertArrayHasKey('app_name', $result['errors']);
    }

    public function testMissingSwitchMeansOff(): void
    {
        $result = $this->schema()->validate(['app_name' => 'A', 'color' => '#fff', 'mode' => 'auto', 'size' => 1]);

        $this->assertSame('0', $result['values']['toggle']);
    }

    public function testBlankPasswordKeepsTheStoredOne(): void
    {
        $result = $this->schema()->validate(['app_name' => 'A', 'api_secret' => '', 'color' => '#fff', 'mode' => 'auto', 'size' => 1]);

        $this->assertSame(['api_secret'], $result['keep']);
        $this->assertArrayNotHasKey('api_secret', $result['values']);
    }

    public function testUnknownKeysAreIgnored(): void
    {
        $result = $this->schema()->validate(['app_name' => 'A', 'color' => '#fff', 'mode' => 'auto', 'size' => 1, 'is_admin' => '1']);

        $this->assertArrayNotHasKey('is_admin', $result['values']);
    }

    public function testValidationCanBeLimitedToSomeFields(): void
    {
        $result = $this->schema()->validate(['color' => '#fff'], ['color']);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['color' => '#ffffff'], $result['values']);
    }

    public function testAddGroupMergesFieldsIntoAnExistingGroup(): void
    {
        $schema = $this->schema();
        $schema->addGroup('general', ['fields' => ['tagline' => ['label' => 'Tagline', 'default' => 'hi']]]);

        $this->assertTrue($schema->has('tagline'));
        $this->assertTrue($schema->has('app_name'));
        $this->assertSame('General', $schema->groups()['general']['label']);
    }

    private function schemaWithDependency(): Schema
    {
        return new Schema(['security' => ['label' => 'Security', 'fields' => [
            'captcha' => ['label' => 'Captcha', 'type' => 'switch', 'default' => false, 'requires' => ['site_key', 'secret']],
            'site_key' => ['label' => 'Site key', 'type' => 'text', 'default' => ''],
            'secret' => ['label' => 'Secret', 'type' => 'password', 'default' => ''],
        ]]]);
    }

    public function testASwitchCanRequireOtherFieldsWhileItIsOn(): void
    {
        $result = $this->schemaWithDependency()->validate(['captcha' => '1', 'site_key' => '', 'secret' => '']);

        $this->assertSame(['site_key', 'secret'], array_keys($result['errors']));
        $this->assertStringContainsString('Captcha', $result['errors']['secret']);
    }

    public function testRequiredFieldsAreNotCheckedWhileTheSwitchIsOff(): void
    {
        $result = $this->schemaWithDependency()->validate(['site_key' => '', 'secret' => '']);

        $this->assertSame([], $result['errors']);
    }

    public function testASavedSecretSatisfiesTheRequirementWhenLeftBlank(): void
    {
        $result = $this->schemaWithDependency()->validate(
            ['captcha' => '1', 'site_key' => 'abc', 'secret' => ''],
            [],
            ['secret' => 'stored-secret']
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(['secret'], $result['keep']);
    }

    public function testANewlyTypedSecretSatisfiesTheRequirement(): void
    {
        $result = $this->schemaWithDependency()->validate(['captcha' => '1', 'site_key' => 'abc', 'secret' => 'new']);

        $this->assertSame([], $result['errors']);
    }
}
