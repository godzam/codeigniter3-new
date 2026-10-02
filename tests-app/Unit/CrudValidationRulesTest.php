<?php

namespace AppTests\Unit;

use App\Crud\Definition;
use App\Crud\ValidationRules;
use PHPUnit\Framework\TestCase;

final class CrudValidationRulesTest extends TestCase
{
    private const REGISTERED = [
        'phone_id' => ['label' => 'Phone', 'param' => 'none', 'types' => ['text']],
        'starts_with' => ['label' => 'Starts with', 'param' => 'text', 'types' => ['text', 'textarea']],
    ];

    private function normalize(string $type, array $rules, array $names = ['a', 'b'], string $self = 'a')
    {
        return ValidationRules::normalize($rules, $type, self::REGISTERED, $names, $self);
    }

    public function testBuiltInRulesAreKeptWithTheirParameters(): void
    {
        $r = $this->normalize('text', [
            ['rule' => 'min_length', 'param' => ' 3 ', 'message' => ''],
            ['rule' => 'alpha_dash', 'param' => 'ignored', 'message' => ' Letters please '],
        ]);

        $this->assertSame([], $r['errors']);
        $this->assertSame([
            ['rule' => 'min_length', 'param' => '3', 'message' => ''],
            ['rule' => 'alpha_dash', 'param' => '', 'message' => 'Letters please'],
        ], $r['rules'], 'a rule without a parameter drops whatever was typed');
    }

    public function testEmptyRowsAreIgnored(): void
    {
        $r = $this->normalize('text', [['rule' => '', 'param' => '', 'message' => ''], 'junk']);

        $this->assertSame([], $r['rules']);
        $this->assertSame([], $r['errors'], 'a malformed row is just ignored');
    }

    public function testRulesMustFitTheInputType(): void
    {
        $this->assertArrayHasKey('0.rule', $this->normalize('number', [['rule' => 'alpha']])['errors']);
        $this->assertArrayHasKey('0.rule', $this->normalize('text', [['rule' => 'greater_than', 'param' => '1']])['errors']);
        $this->assertArrayHasKey('0.rule', $this->normalize('date', [['rule' => 'min_length', 'param' => '3']])['errors']);
        $this->assertArrayHasKey('0.rule', $this->normalize('text', [['rule' => 'nope']])['errors']);
        $this->assertArrayHasKey('0.rule', $this->normalize('textarea', [['rule' => 'phone_id']])['errors'], 'registered rule limited to text');
        $this->assertSame([], $this->normalize('text', [['rule' => 'phone_id']])['errors']);
        $this->assertSame([], $this->normalize('number', [['rule' => 'is_natural_no_zero']])['errors']);
    }

    public function testNothingToValidateForOtherTypes(): void
    {
        foreach (['password', 'select', 'radio', 'multiselect', 'image', 'file'] as $type) {
            $this->assertSame(['rules' => [], 'errors' => []], $this->normalize($type, [['rule' => 'alpha']]), $type);
        }
    }

    public function testParameterRules(): void
    {
        foreach (['', '0', '-1', 'abc', '1.5', '10001'] as $bad) {
            $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'min_length', 'param' => $bad]])['errors'], 'length '.$bad);
        }
        foreach (['', 'x', '1e3', '1,5'] as $bad) {
            $this->assertArrayHasKey('0.param', $this->normalize('number', [['rule' => 'less_than', 'param' => $bad]])['errors'], 'number '.$bad);
        }
        $this->assertSame([], $this->normalize('number', [['rule' => 'greater_than', 'param' => '-2.5']])['errors']);
        $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'starts_with', 'param' => '']])['errors']);
        $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'starts_with', 'param' => "a\nb"]])['errors']);
        $this->assertSame([], $this->normalize('text', [['rule' => 'starts_with', 'param' => 'INV-']])['errors']);
    }

    public function testFieldComparisonsNeedAnotherExistingField(): void
    {
        $this->assertSame([], $this->normalize('text', [['rule' => 'matches', 'param' => 'b']])['errors']);
        $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'matches', 'param' => 'a']])['errors'], 'itself');
        $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'differs', 'param' => 'ghost']])['errors']);
    }

    public function testRegexPatterns(): void
    {
        $this->assertSame([], $this->normalize('text', [['rule' => 'regex', 'param' => '/^[A-Z]{3}-\d{4}$/']])['errors']);
        $this->assertSame([], $this->normalize('text', [['rule' => 'regex', 'param' => '#^\d+$#i']])['errors']);

        foreach (['', 'no delimiters', '/unclosed', '/(/', '/a/zzz', str_repeat('a', 201), "/a\n/"] as $bad) {
            $this->assertArrayHasKey('0.param', $this->normalize('text', [['rule' => 'regex', 'param' => $bad]])['errors'], var_export($bad, true));
        }
    }

    public function testMessagesAndDuplicatesAndLimit(): void
    {
        $this->assertArrayHasKey('0.message', $this->normalize('text', [['rule' => 'alpha', 'message' => str_repeat('x', 201)]])['errors']);
        $this->assertArrayHasKey('0.message', $this->normalize('text', [['rule' => 'alpha', 'message' => "two\nlines"]])['errors']);

        $this->assertArrayHasKey('1.rule', $this->normalize('text', [['rule' => 'alpha'], ['rule' => 'alpha']])['errors']);
        $this->assertSame([], $this->normalize('text', [
            ['rule' => 'regex', 'param' => '/a/'], ['rule' => 'regex', 'param' => '/b/'],
        ])['errors'], 'two different patterns are fine');

        $many = [];
        foreach (['alpha', 'alpha_numeric', 'alpha_dash', 'numeric', 'integer', 'decimal', 'is_natural', 'valid_url', 'valid_ip', 'valid_base64', 'valid_emails'] as $name) {
            $many[] = ['rule' => $name];
        }
        $this->assertArrayHasKey('0.rule', $this->normalize('text', $many)['errors']);
    }

    public function testCatalogListsBuiltInAndRegisteredRules(): void
    {
        $catalog = ValidationRules::catalog(self::REGISTERED);
        $byName = array_column($catalog, null, 'name');

        $this->assertSame(ValidationRules::GROUP_BUILTIN, $byName['alpha_dash']['group']);
        $this->assertSame(ValidationRules::GROUP_PATTERN, $byName['regex']['group']);
        $this->assertSame(ValidationRules::GROUP_CODE, $byName['phone_id']['group']);
        $this->assertSame('text', $byName['starts_with']['param']);
        $this->assertContains('required', ValidationRules::reservedNames(), 'required is a checkbox, not a rule');
        $this->assertContains('alpha', ValidationRules::reservedNames());
    }

    public function testDefinitionStoresRulesPerFieldAndChecksFieldReferences(): void
    {
        $schema = static function () {
            return false;
        };
        $module = static function (array $fields) {
            return ['title' => 'T', 'slug' => 'things', 'fields' => $fields];
        };

        $ok = Definition::normalize($module([
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'rules' => [['rule' => 'regex', 'param' => '/^[A-Z]+$/']]],
            ['name' => 'code2', 'label' => 'Again', 'type' => 'text', 'rules' => [['rule' => 'matches', 'param' => 'code']]],
            ['name' => 'secret', 'label' => 'S', 'type' => 'password', 'rules' => [['rule' => 'alpha']]],
        ]), null, [], $schema, self::REGISTERED);

        $this->assertSame([], $ok['errors']);
        $fields = $ok['definition']['fields'];
        $this->assertSame('regex', $fields[0]['rules'][0]['rule']);
        $this->assertSame('code', $fields[1]['rules'][0]['param']);
        $this->assertSame([], $fields[2]['rules'], 'a password field takes no extra rules');

        $bad = Definition::normalize($module([
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'rules' => [['rule' => 'matches', 'param' => 'ghost']]],
        ]), null, [], $schema, self::REGISTERED);
        $this->assertArrayHasKey('fields.0.rules.0.param', $bad['errors']);

        $unknown = Definition::normalize($module([
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'rules' => [['rule' => 'phone_id']]],
        ]), null, [], $schema, []);
        $this->assertArrayHasKey('fields.0.rules.0.rule', $unknown['errors'], 'a rule that is not registered cannot be used');
    }
}
