<?php

namespace AppTests\Unit;

use App\Crud\Definition;
use PHPUnit\Framework\TestCase;

final class CrudDefinitionTest extends TestCase
{
    /** @var array<string, array<int, string>> tables that "exist" in the fake database */
    private $tables = ['categories' => ['id', 'name']];

    private function normalize(array $input, ?array $existing = null, array $taken = [])
    {
        return Definition::normalize($input, $existing, $taken, function ($table, $column = null) {
            return isset($this->tables[$table]) && ($column === null || in_array($column, $this->tables[$table], true));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function module(array $fields = null)
    {
        return [
            'title' => 'Products',
            'slug' => 'products',
            'fields' => $fields ?? [['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'list' => true]],
        ];
    }

    public function testNormalisesAMinimalModule(): void
    {
        $r = $this->normalize($this->module());

        $this->assertSame([], $r['errors']);
        $d = $r['definition'];
        $this->assertSame('products', $d['table'], 'table defaults to the key');
        $this->assertSame('bi-table', $d['icon']);
        $this->assertSame('name', $d['fields'][0]['name']);
        $this->assertTrue($d['fields'][0]['required']);
        $this->assertTrue($d['fields'][0]['search'], 'listed text fields are searchable');
        $this->assertSame(255, $d['fields'][0]['maxlength']);
    }

    public function testKeyRules(): void
    {
        foreach (['', 'a', '1abc', 'has space', 'users', 'roles', 'generator', str_repeat('a', 31)] as $slug) {
            $r = $this->normalize(['slug' => $slug] + $this->module());
            $this->assertArrayHasKey('slug', $r['errors'], var_export($slug, true));
        }

        $this->assertArrayHasKey('slug', $this->normalize($this->module(), null, ['products'])['errors'], 'taken by another module');
    }

    public function testKeysAndColumnNamesAreLowercased(): void
    {
        $r = $this->normalize(['slug' => ' Products '] + $this->module([['name' => 'Name', 'label' => 'N', 'type' => 'text']]));

        $this->assertSame('products', $r['definition']['slug']);
        $this->assertSame('name', $r['definition']['fields'][0]['name']);
    }

    public function testTableMustBeNewAndNotASystemTable(): void
    {
        $this->assertArrayHasKey('table', $this->normalize(['table' => 'categories'] + $this->module())['errors']);
        $this->assertArrayHasKey('table', $this->normalize(['table' => 'users'] + $this->module())['errors']);
        $this->assertArrayHasKey('table', $this->normalize(['table' => 'Bad Name'] + $this->module())['errors']);
        $this->assertSame('items', $this->normalize(['table' => 'items'] + $this->module())['definition']['table']);
    }

    public function testFieldNamesAreChecked(): void
    {
        $bad = ['id', 'created_at', '1x', 'a-b', '', str_repeat('a', 41)];
        foreach ($bad as $name) {
            $r = $this->normalize($this->module([['name' => $name, 'label' => 'X', 'type' => 'text']]));
            $this->assertArrayHasKey('fields.0.name', $r['errors'], var_export($name, true));
        }

        $dupes = $this->normalize($this->module([
            ['name' => 'a', 'label' => 'A', 'type' => 'text'],
            ['name' => 'a', 'label' => 'B', 'type' => 'text'],
        ]));
        $this->assertArrayHasKey('fields.1.name', $dupes['errors']);
    }

    public function testAtLeastOneFieldAndNotTooMany(): void
    {
        $this->assertArrayHasKey('fields', $this->normalize($this->module([]))['errors']);

        $many = [];
        for ($i = 0; $i <= Definition::MAX_FIELDS; ++$i) {
            $many[] = ['name' => 'f'.$i, 'label' => 'F', 'type' => 'text'];
        }
        $this->assertArrayHasKey('fields', $this->normalize($this->module($many))['errors']);
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->assertArrayHasKey('fields.0.type', $this->normalize($this->module([['name' => 'a', 'label' => 'A', 'type' => 'wysiwyg']]))['errors']);
    }

    public function testPasswordIsNeverListedAndUniqueOnlyAppliesToSingleValues(): void
    {
        $r = $this->normalize($this->module([
            ['name' => 'secret', 'label' => 'S', 'type' => 'password', 'list' => true, 'unique' => true],
            ['name' => 'tags', 'label' => 'T', 'type' => 'multiselect', 'unique' => true, 'options' => ['mode' => 'static', 'items' => [['value' => 'a', 'label' => 'A']]]],
        ]));

        $this->assertFalse($r['definition']['fields'][0]['list']);
        $this->assertFalse($r['definition']['fields'][0]['unique']);
        $this->assertFalse($r['definition']['fields'][1]['unique']);
        $this->assertSame(8, $r['definition']['fields'][0]['min_length']);
    }

    public function testStaticOptions(): void
    {
        $r = $this->normalize($this->module([['name' => 's', 'label' => 'S', 'type' => 'select', 'options' => ['mode' => 'static', 'items' => [
            ['value' => 'draft', 'label' => ''],
            ['value' => '', 'label' => 'Live'],
            ['value' => '', 'label' => ''],
        ]]]]));

        $this->assertSame([['value' => 'draft', 'label' => 'draft'], ['value' => 'Live', 'label' => 'Live']], $r['definition']['fields'][0]['options']['items']);

        $dup = $this->normalize($this->module([['name' => 's', 'label' => 'S', 'type' => 'radio', 'options' => ['mode' => 'static', 'items' => [['value' => 'a'], ['value' => 'a']]]]]));
        $this->assertArrayHasKey('fields.0.options.items', $dup['errors']);

        $none = $this->normalize($this->module([['name' => 's', 'label' => 'S', 'type' => 'select', 'options' => ['mode' => 'static', 'items' => []]]]));
        $this->assertArrayHasKey('fields.0.options.items', $none['errors']);
    }

    public function testOptionsFromAnotherTableAreCheckedAgainstTheSchema(): void
    {
        $field = static function ($table, $value, $label) {
            return [['name' => 'c', 'label' => 'C', 'type' => 'select', 'options' => ['mode' => 'table', 'table' => $table, 'value_column' => $value, 'label_column' => $label]]];
        };

        $this->assertSame([], $this->normalize($this->module($field('categories', 'id', 'name')))['errors']);
        $this->assertArrayHasKey('fields.0.options.table', $this->normalize($this->module($field('nope', 'id', 'name')))['errors']);
        $this->assertArrayHasKey('fields.0.options.label_column', $this->normalize($this->module($field('categories', 'id', 'title')))['errors']);
        $this->assertArrayHasKey('fields.0.options.table', $this->normalize($this->module($field('cat; DROP TABLE x', 'id', 'name')))['errors']);
    }

    public function testUploadRules(): void
    {
        $image = $this->normalize($this->module([['name' => 'p', 'label' => 'P', 'type' => 'image']]));
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp'], $image['definition']['fields'][0]['upload']['types']);
        $this->assertSame(1600, $image['definition']['fields'][0]['upload']['max_width']);

        $custom = $this->normalize($this->module([['name' => 'f', 'label' => 'F', 'type' => 'file', 'upload' => ['max_kb' => 100, 'types' => '.PDF, docx ;zip']]]));
        $this->assertSame(['pdf', 'docx', 'zip'], $custom['definition']['fields'][0]['upload']['types']);

        foreach (['php', 'phtml', 'html', 'svg', 'js', 'exe', 'htaccess'] as $ext) {
            $r = $this->normalize($this->module([['name' => 'f', 'label' => 'F', 'type' => 'file', 'upload' => ['types' => ['pdf', $ext]]]]));
            $this->assertArrayHasKey('fields.0.upload.types', $r['errors'], $ext);
        }

        $svg = $this->normalize($this->module([['name' => 'i', 'label' => 'I', 'type' => 'image', 'upload' => ['types' => ['jpg', 'bmp']]]]));
        $this->assertArrayHasKey('fields.0.upload.types', $svg['errors'], 'images are limited to formats GD can re-encode');

        $big = $this->normalize($this->module([['name' => 'f', 'label' => 'F', 'type' => 'file', 'upload' => ['max_kb' => 999999]]]));
        $this->assertArrayHasKey('fields.0.upload.max_kb', $big['errors']);
    }

    public function testEditingKeepsExistingFieldsLocked(): void
    {
        $existing = $this->normalize($this->module([
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'qty', 'label' => 'Qty', 'type' => 'number', 'integer' => true],
        ]))['definition'];

        // Relabelling and adding a field is fine.
        $ok = $this->normalize([
            'title' => 'Items',
            'fields' => [
                ['name' => 'name', 'label' => 'Product name', 'type' => 'text', 'required' => true],
                ['name' => 'qty', 'label' => 'Quantity', 'type' => 'number', 'integer' => true],
                ['name' => 'note', 'label' => 'Note', 'type' => 'textarea'],
            ],
        ], $existing);
        $this->assertSame([], $ok['errors']);
        $this->assertSame('products', $ok['definition']['slug'], 'key cannot change');
        $this->assertSame('products', $ok['definition']['table'], 'table cannot change');

        // Removing, re-typing or changing integer/decimal is not.
        $removed = $this->normalize(['title' => 'Items', 'fields' => [['name' => 'name', 'label' => 'N', 'type' => 'text']]], $existing);
        $this->assertArrayHasKey('fields', $removed['errors']);

        $retyped = $this->normalize(['title' => 'Items', 'fields' => [
            ['name' => 'name', 'label' => 'N', 'type' => 'textarea'],
            ['name' => 'qty', 'label' => 'Q', 'type' => 'number', 'integer' => true],
        ]], $existing);
        $this->assertArrayHasKey('fields.0.type', $retyped['errors']);

        $decimal = $this->normalize(['title' => 'Items', 'fields' => [
            ['name' => 'name', 'label' => 'N', 'type' => 'text'],
            ['name' => 'qty', 'label' => 'Q', 'type' => 'number', 'integer' => false],
        ]], $existing);
        $this->assertArrayHasKey('fields.1.integer', $decimal['errors']);
    }
}
