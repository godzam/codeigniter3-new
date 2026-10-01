<?php

namespace AppTests\Unit;

use App\Crud\Definition;
use App\Crud\RecordValidator;
use PHPUnit\Framework\TestCase;

final class CrudRecordValidatorTest extends TestCase
{
    /** @var array<string, mixed> */
    private $definition;

    /** @var array<int, string> values the fake "categories" table holds */
    private $categories = ['1', '2'];

    /** @var array<string, array<int, array{0: string, 1: int|null}>> */
    private $taken = [];

    protected function setUp(): void
    {
        $result = Definition::normalize([
            'title' => 'Products', 'slug' => 'products',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'maxlength' => 10],
                ['name' => 'note', 'label' => 'Note', 'type' => 'textarea'],
                ['name' => 'qty', 'label' => 'Qty', 'type' => 'number', 'integer' => true, 'min' => 1, 'max' => 100],
                ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'min' => 0],
                ['name' => 'mail', 'label' => 'Mail', 'type' => 'email'],
                ['name' => 'day', 'label' => 'Day', 'type' => 'date'],
                ['name' => 'pass', 'label' => 'Password', 'type' => 'password', 'required' => true, 'min_length' => 8],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['mode' => 'static', 'items' => [['value' => 'draft'], ['value' => 'live']]]],
                ['name' => 'tags', 'label' => 'Tags', 'type' => 'multiselect', 'options' => ['mode' => 'static', 'items' => [['value' => 'a'], ['value' => 'b']]]],
                ['name' => 'cat', 'label' => 'Category', 'type' => 'select', 'options' => ['mode' => 'table', 'table' => 'categories', 'value_column' => 'id', 'label_column' => 'name']],
                ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => true],
            ],
        ], null, [], static function ($table, $column = null) {
            return $table === 'categories' && ($column === null || in_array($column, ['id', 'name'], true));
        });
        $this->assertSame([], $result['errors']);
        $this->definition = $result['definition'];
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $existing
     * @param array<string, string>     $uploads
     */
    private function validate(array $input, ?array $existing = null, array $uploads = ['photo' => 'new'])
    {
        return RecordValidator::validate(
            $this->definition,
            $input,
            $existing,
            $uploads,
            function ($field, $values) {
                return array_values(array_intersect($values, $this->categories));
            },
            function ($column, $value, $ignoreId) {
                foreach ($this->taken[$column] ?? [] as [$v, $id]) {
                    if ($v === $value && $id !== $ignoreId) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(array $override = [])
    {
        return $override + ['name' => 'Widget', 'pass' => 'longenough', 'status' => 'live'];
    }

    public function testAValidRecordProducesStoredValues(): void
    {
        $r = $this->validate($this->valid([
            'note' => ' hi ', 'qty' => '5', 'price' => '9.5', 'mail' => 'a@b.co', 'day' => '2026-02-28',
            'tags' => ['a', 'b', 'a'], 'cat' => '2',
        ]));

        $this->assertSame([], $r['errors']);
        $v = $r['values'];
        $this->assertSame('Widget', $v['name']);
        $this->assertSame('hi', $v['note']);
        $this->assertSame(5, $v['qty']);
        $this->assertSame('9.5', $v['price']);
        $this->assertSame('["a","b"]', $v['tags']);
        $this->assertSame('2', $v['cat']);
        $this->assertTrue(password_verify('longenough', $v['pass']));
        $this->assertArrayNotHasKey('photo', $v, 'uploads are stored by Uploader');
    }

    public function testRequiredFields(): void
    {
        $r = $this->validate([], null, []);

        $this->assertEqualsCanonicalizing(['name', 'pass', 'status', 'photo'], array_keys($r['errors']));
    }

    public function testRequiredUploadIsSatisfiedByTheExistingFileUnlessRemoved(): void
    {
        $existing = ['id' => 7, 'photo' => 'crud/products/'.str_repeat('a', 32).'.jpg'];

        $this->assertArrayNotHasKey('photo', $this->validate($this->valid(['pass' => '']), $existing, [])['errors']);
        $this->assertArrayHasKey('photo', $this->validate($this->valid(['pass' => '']), $existing, ['photo' => 'remove'])['errors']);
        $this->assertArrayNotHasKey('photo', $this->validate($this->valid(['pass' => '']), $existing, ['photo' => 'new'])['errors']);
    }

    public function testNumbersEmailsAndDates(): void
    {
        $bad = [
            'qty' => ['1.5', 'abc', '0', '101', '-3'],
            'price' => ['-1', '1.234', 'x', '1e3'],
            'mail' => ['nope', 'a@', 'a b@c.d'],
            'day' => ['2026-02-30', '28/02/2026', '2026-2-3', 'tomorrow'],
        ];
        foreach ($bad as $field => $values) {
            foreach ($values as $value) {
                $this->assertArrayHasKey($field, $this->validate($this->valid([$field => $value]))['errors'], $field.'='.$value);
            }
        }
    }

    public function testTextLengthAndControlCharacters(): void
    {
        $this->assertArrayHasKey('name', $this->validate($this->valid(['name' => str_repeat('x', 11)]))['errors']);
        $this->assertArrayHasKey('note', $this->validate($this->valid(['note' => "a\x00b"]))['errors']);
        $this->assertArrayNotHasKey('note', $this->validate($this->valid(['note' => "line1\nline2\ttab"]))['errors']);
    }

    public function testChoicesMustBeAmongTheOptions(): void
    {
        $this->assertArrayHasKey('status', $this->validate($this->valid(['status' => 'hacked']))['errors']);
        $this->assertArrayHasKey('tags', $this->validate($this->valid(['tags' => ['a', 'zzz']]))['errors']);
        $this->assertArrayHasKey('cat', $this->validate($this->valid(['cat' => '99']))['errors'], 'table-backed values are looked up');
        $this->assertArrayHasKey('status', $this->validate($this->valid(['status' => ['live']]))['errors'], 'an array where a scalar belongs');
    }

    public function testUniqueIgnoresTheRecordBeingEdited(): void
    {
        $this->taken['name'] = [['Widget', 7]];

        $this->assertArrayHasKey('name', $this->validate($this->valid())['errors']);
        $this->assertArrayNotHasKey('name', $this->validate($this->valid(), ['id' => 7, 'photo' => 'x'])['errors']);
        $this->assertArrayHasKey('name', $this->validate($this->valid(), ['id' => 8, 'photo' => 'x'])['errors']);
    }

    public function testPasswordRules(): void
    {
        $this->assertArrayHasKey('pass', $this->validate($this->valid(['pass' => 'short']))['errors']);
        $this->assertArrayHasKey('pass', $this->validate($this->valid(['pass' => str_repeat('a', 73)]))['errors']);

        $edit = $this->validate($this->valid(['pass' => '']), ['id' => 1, 'photo' => 'x'], []);
        $this->assertArrayNotHasKey('pass', $edit['errors'], 'empty on edit keeps the current one');
        $this->assertArrayNotHasKey('pass', $edit['values']);

        $spaces = $this->validate($this->valid(['pass' => '  spaced out  ']));
        $this->assertTrue(password_verify('  spaced out  ', $spaces['values']['pass']), 'passwords are not trimmed');
    }

    public function testOptionalEmptyValuesBecomeNull(): void
    {
        $v = $this->validate($this->valid(['note' => '', 'qty' => '', 'tags' => []]))['values'];

        $this->assertNull($v['note']);
        $this->assertNull($v['qty']);
        $this->assertNull($v['tags']);
    }
}
