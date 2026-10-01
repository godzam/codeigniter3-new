<?php

namespace App\Settings;

use App\Theme\Color;

/**
 * Describes which settings exist (key, label, type, default, options) and
 * validates/casts raw form input against that description. Pure PHP, no
 * database — the Settings library handles storage. Definitions come from
 * application/config/app_settings.php, and a module can add its own with
 * Settings::register() without touching the starter's files.
 *
 * A definition looks like:
 *   'theme_primary' => [
 *       'label'   => 'Primary color',
 *       'type'    => 'color',            // text|textarea|color|select|switch|number|password
 *       'default' => '#0d6efd',
 *       'options' => [...],              // select only: value => label
 *       'help'    => 'Shown under the field',
 *       'public'  => true,               // may be exposed to unauthenticated pages
 *       'requires' => ['other_field'],   // switch only: these must be non-empty when it is on
 *   ]
 */
final class Schema
{
    /** @var array<string, array{label: string, icon?: string, fields: array<string, array<string, mixed>>}> */
    private $groups = [];

    /**
     * @param array<string, array<string, mixed>> $groups group key => ['label' => ..., 'fields' => [...]]
     */
    public function __construct(array $groups = [])
    {
        foreach ($groups as $key => $group) {
            $this->addGroup($key, $group);
        }
    }

    /**
     * Adds a group, or merges fields into an existing one.
     *
     * @param array<string, mixed> $group
     */
    public function addGroup($key, array $group)
    {
        if (!isset($this->groups[$key])) {
            $this->groups[$key] = ['label' => $group['label'] ?? ucfirst($key), 'icon' => $group['icon'] ?? 'bi-gear', 'fields' => []];
        }

        foreach ($group['fields'] ?? [] as $name => $field) {
            $this->groups[$key]['fields'][$name] = $field + ['type' => 'text', 'default' => '', 'label' => $name];
        }
    }

    public function groups()
    {
        return $this->groups;
    }

    /**
     * @return array<string, array<string, mixed>> field name => definition
     */
    public function fields()
    {
        $fields = [];
        foreach ($this->groups as $group) {
            $fields += $group['fields'];
        }

        return $fields;
    }

    public function has($name)
    {
        return isset($this->fields()[$name]);
    }

    /**
     * @return array<string, mixed> field name => default, already cast
     */
    public function defaults()
    {
        $defaults = [];
        foreach ($this->fields() as $name => $field) {
            $defaults[$name] = $this->cast($name, $field['default']);
        }

        return $defaults;
    }

    /**
     * Casts a stored/raw value to the field's PHP type.
     */
    public function cast($name, $value)
    {
        $field = $this->fields()[$name] ?? null;

        switch ($field['type'] ?? 'text') {
            case 'switch':
                return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
            case 'number':
                return is_numeric($value) ? $value + 0 : ($field['default'] ?? 0);
            default:
                return $value === null ? '' : (string) $value;
        }
    }

    /**
     * Validates submitted form data. Unknown keys are ignored; a missing
     * switch means "off" (browsers don't submit unchecked checkboxes); an
     * empty password keeps the stored one (returned in $keep).
     *
     * @param array<string, mixed> $input
     * @param array<string>        $only    restrict to these fields (e.g. one tab); empty = all
     * @param array<string, mixed> $current already-saved values, so 'requires' counts a kept password
     *
     * @return array{values: array<string, mixed>, errors: array<string, string>, keep: array<string>}
     */
    public function validate(array $input, array $only = [], array $current = [])
    {
        $values = $errors = $keep = [];

        foreach ($this->fields() as $name => $field) {
            if ($only && !in_array($name, $only, true)) {
                continue;
            }

            $raw = $input[$name] ?? null;
            $label = $field['label'];

            switch ($field['type']) {
                case 'switch':
                    $values[$name] = in_array($raw, ['1', 1, true, 'on', 'true'], true) ? '1' : '0';
                    break;

                case 'color':
                    $hex = Color::normalize($raw);
                    if ($hex === null) {
                        $errors[$name] = "{$label} must be a valid hex color (e.g. #0d6efd).";
                    } else {
                        $values[$name] = $hex;
                    }
                    break;

                case 'select':
                    if (!array_key_exists((string) $raw, $field['options'] ?? [])) {
                        $errors[$name] = "{$label} has an invalid choice.";
                    } else {
                        $values[$name] = (string) $raw;
                    }
                    break;

                case 'number':
                    if (!is_numeric($raw)) {
                        $errors[$name] = "{$label} must be a number.";
                    } elseif ((isset($field['min']) && $raw < $field['min']) || (isset($field['max']) && $raw > $field['max'])) {
                        $errors[$name] = "{$label} must be between ".($field['min'] ?? '-∞').' and '.($field['max'] ?? '∞').'.';
                    } else {
                        $values[$name] = (string) ($raw + 0);
                    }
                    break;

                case 'password':
                    if ($raw === null || $raw === '') {
                        $keep[] = $name;
                    } else {
                        $values[$name] = (string) $raw;
                    }
                    break;

                default: // text, textarea
                    $text = trim((string) $raw);
                    $max = $field['max_length'] ?? ($field['type'] === 'textarea' ? 1000 : 255);
                    if (!empty($field['required']) && $text === '') {
                        $errors[$name] = "{$label} is required.";
                    } elseif (mb_strlen($text) > $max) {
                        $errors[$name] = "{$label} must be at most {$max} characters.";
                    } else {
                        $values[$name] = $text;
                    }
            }
        }

        // A switch can demand that other fields be filled in while it is on
        // (e.g. Turnstile needs both keys). A password left blank keeps the
        // saved one, which then counts as filled.
        foreach ($this->fields() as $name => $field) {
            if (empty($field['requires']) || ($values[$name] ?? '0') !== '1') {
                continue;
            }

            foreach ($field['requires'] as $required) {
                if (isset($errors[$required])) {
                    continue;
                }

                $effective = $values[$required] ?? (string) ($current[$required] ?? '');
                if (trim((string) $effective) === '') {
                    $errors[$required] = ($this->fields()[$required]['label'] ?? $required).' is required while "'.$field['label'].'" is on.';
                }
            }
        }

        return ['values' => $values, 'errors' => $errors, 'keep' => $keep];
    }
}
