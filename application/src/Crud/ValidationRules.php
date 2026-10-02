<?php

namespace App\Crud;

/**
 * The validation rules a field of a generated module can be given on top of
 * what its input type already checks.
 *
 * Three kinds, all stored by name in the module definition:
 *   - CodeIgniter's own rules (min_length, alpha_dash, valid_url, ...). They are
 *     run by the real CI_Form_validation methods, with CodeIgniter's messages.
 *   - "regex": a pattern typed in the builder.
 *   - rules registered in code (application/config/crud_rules.php), for
 *     anything the first two cannot say. No PHP is ever stored in the database.
 *
 * This class is pure PHP: it knows what exists and checks what the builder
 * posts. Running a rule happens in the Crud_rules library.
 */
final class ValidationRules
{
    public const MAX_RULES = 10;

    /** Input types that can carry extra rules. */
    public const TYPES = [FieldTypes::TEXT, FieldTypes::TEXTAREA, FieldTypes::NUMBER, FieldTypes::EMAIL, FieldTypes::DATE];

    public const GROUP_BUILTIN = 'CodeIgniter rules';
    public const GROUP_PATTERN = 'Custom pattern';
    public const GROUP_CODE = 'Custom rules from code';

    /**
     * @return array<string, array{label: string, param: string, types: array<int, string>, group: string}>
     */
    public static function builtin()
    {
        $text = [FieldTypes::TEXT, FieldTypes::TEXTAREA];
        $any = self::TYPES;
        $textOrNumber = [FieldTypes::TEXT, FieldTypes::TEXTAREA, FieldTypes::NUMBER];
        $b = self::GROUP_BUILTIN;

        return [
            'min_length' => ['label' => 'At least N characters', 'param' => 'length', 'types' => $text, 'group' => $b],
            'max_length' => ['label' => 'At most N characters', 'param' => 'length', 'types' => $text, 'group' => $b],
            'exact_length' => ['label' => 'Exactly N characters', 'param' => 'length', 'types' => $text, 'group' => $b],
            'alpha' => ['label' => 'Letters only', 'param' => 'none', 'types' => $text, 'group' => $b],
            'alpha_numeric' => ['label' => 'Letters and digits only', 'param' => 'none', 'types' => $text, 'group' => $b],
            'alpha_numeric_spaces' => ['label' => 'Letters, digits and spaces only', 'param' => 'none', 'types' => $text, 'group' => $b],
            'alpha_dash' => ['label' => 'Letters, digits, _ and - only', 'param' => 'none', 'types' => $text, 'group' => $b],
            'numeric' => ['label' => 'Numeric', 'param' => 'none', 'types' => $text, 'group' => $b],
            'integer' => ['label' => 'Integer', 'param' => 'none', 'types' => $text, 'group' => $b],
            'decimal' => ['label' => 'Decimal number (1.5)', 'param' => 'none', 'types' => $text, 'group' => $b],
            'is_natural' => ['label' => 'Digits only (0, 1, 2 ...)', 'param' => 'none', 'types' => $textOrNumber, 'group' => $b],
            'is_natural_no_zero' => ['label' => 'Digits only, above zero', 'param' => 'none', 'types' => $textOrNumber, 'group' => $b],
            'greater_than' => ['label' => 'Greater than N', 'param' => 'number', 'types' => [FieldTypes::NUMBER], 'group' => $b],
            'greater_than_equal_to' => ['label' => 'Greater than or equal to N', 'param' => 'number', 'types' => [FieldTypes::NUMBER], 'group' => $b],
            'less_than' => ['label' => 'Less than N', 'param' => 'number', 'types' => [FieldTypes::NUMBER], 'group' => $b],
            'less_than_equal_to' => ['label' => 'Less than or equal to N', 'param' => 'number', 'types' => [FieldTypes::NUMBER], 'group' => $b],
            'valid_url' => ['label' => 'Valid URL', 'param' => 'none', 'types' => $text, 'group' => $b],
            'valid_emails' => ['label' => 'Comma-separated email addresses', 'param' => 'none', 'types' => $text, 'group' => $b],
            'valid_ip' => ['label' => 'Valid IP address', 'param' => 'none', 'types' => $text, 'group' => $b],
            'valid_base64' => ['label' => 'Valid Base64', 'param' => 'none', 'types' => $text, 'group' => $b],
            'matches' => ['label' => 'Same as another field', 'param' => 'field', 'types' => $any, 'group' => $b],
            'differs' => ['label' => 'Different from another field', 'param' => 'field', 'types' => $any, 'group' => $b],
            'regex' => ['label' => 'Matches a pattern (regex)', 'param' => 'regex', 'types' => $any, 'group' => self::GROUP_PATTERN],
        ];
    }

    /**
     * The registered (code) rules must not shadow these.
     *
     * @return array<int, string>
     */
    public static function reservedNames()
    {
        return array_merge(array_keys(self::builtin()), ['required', 'unique', 'callback']);
    }

    /**
     * What the builder needs to offer: built-in rules plus the registered ones.
     *
     * @param array<string, array{label: string, param: string, types: array<int, string>}> $registered
     *
     * @return array<int, array{name: string, label: string, param: string, types: array<int, string>, group: string}>
     */
    public static function catalog(array $registered)
    {
        $list = [];
        foreach (self::builtin() as $name => $rule) {
            $list[] = ['name' => $name] + $rule;
        }
        foreach ($registered as $name => $rule) {
            $list[] = ['name' => $name, 'group' => self::GROUP_CODE] + $rule;
        }

        return $list;
    }

    /**
     * Checks the rules posted for one field and returns them in the stored shape.
     *
     * @param mixed                                                                         $raw        the posted list
     * @param array<string, array{label: string, param: string, types: array<int, string>}> $registered
     * @param array<int, string>                                                            $fieldNames every column name in the module
     *
     * @return array{rules: array<int, array{rule: string, param: string, message: string}>, errors: array<string, string>}
     */
    public static function normalize($raw, $type, array $registered, array $fieldNames, $self)
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['rules' => [], 'errors' => []];
        }

        $known = self::builtin() + array_map(static function ($r) {
            return $r + ['group' => self::GROUP_CODE];
        }, $registered);

        $rules = [];
        $errors = [];
        $seen = [];

        foreach (array_values(is_array($raw) ? $raw : []) as $i => $item) {
            $item = is_array($item) ? $item : [];
            $name = (string) ($item['rule'] ?? '');
            $param = trim((string) ($item['param'] ?? ''));
            $message = trim((string) ($item['message'] ?? ''));

            if ($name === '' && $param === '' && $message === '') {
                continue; // an empty row the user never filled in
            }

            if (!isset($known[$name]) || !in_array($type, $known[$name]['types'], true)) {
                $errors[$i.'.rule'] = 'Pick a rule that fits this input type.';

                continue;
            }

            $paramError = self::paramError($known[$name]['param'], $param, $fieldNames, $self);
            if ($paramError !== null) {
                $errors[$i.'.param'] = $paramError;
            }
            if ($known[$name]['param'] === 'none') {
                $param = '';
            }

            if (mb_strlen($message) > 200 || preg_match('/[\x00-\x1f]/', $message)) {
                $errors[$i.'.message'] = 'The message can be up to 200 characters, on one line.';
            }

            if (isset($seen[$name."\0".$param])) {
                $errors[$i.'.rule'] = 'This rule is listed twice.';
            }
            $seen[$name."\0".$param] = true;

            $rules[] = ['rule' => $name, 'param' => $param, 'message' => $message];
        }

        if (count($rules) > self::MAX_RULES) {
            $errors['0.rule'] = 'At most '.self::MAX_RULES.' rules per field.';
        }

        return ['rules' => $rules, 'errors' => $errors];
    }

    /**
     * @param array<int, string> $fieldNames
     */
    private static function paramError($kind, $param, array $fieldNames, $self)
    {
        switch ($kind) {
            case 'length':
                return ctype_digit($param) && (int) $param >= 1 && (int) $param <= 10000 ? null : 'Enter a whole number from 1 to 10000.';

            case 'number':
                return preg_match('/^-?\d{1,15}(\.\d{1,6})?$/', $param) ? null : 'Enter a number.';

            case 'field':
                if ($param === $self) {
                    return 'Choose a different field.';
                }

                return in_array($param, $fieldNames, true) ? null : 'Choose one of the other fields.';

            case 'text':
                return $param !== '' && mb_strlen($param) <= 100 && !preg_match('/[\x00-\x1f]/', $param) ? null : 'Enter a value (up to 100 characters).';

            case 'regex':
                return self::patternError($param);
        }

        return null;
    }

    /**
     * A PCRE pattern with delimiters, e.g. /^[A-Z]{3}-\d{4}$/ . Null when it compiles.
     */
    public static function patternError($pattern)
    {
        if ($pattern === '' || strlen($pattern) > 200 || preg_match('/[\x00-\x1f]/', $pattern)) {
            return 'Enter a pattern up to 200 characters, with delimiters, e.g. /^[A-Z]{3}-\d{4}$/';
        }

        // Compile it; a bad pattern makes preg_match return false.
        if (@preg_match($pattern, '') === false) {
            return 'That is not a valid pattern. Include the delimiters, e.g. /^[A-Z]{3}-\d{4}$/';
        }

        return null;
    }
}
