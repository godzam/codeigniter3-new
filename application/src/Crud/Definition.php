<?php

namespace App\Crud;

/**
 * Validates and normalises what the generator's builder posts: one module
 * (a page that lists/creates/edits/deletes records of one table) and its
 * fields. Pure PHP; the database is only reached through the injected
 * callables, so it is unit-testable.
 *
 * The normalised definition is what is stored (as JSON) in `crud_modules`
 * and what drives the generated list, form and validation.
 */
final class Definition
{
    public const RESERVED_SLUGS = [
        'users', 'roles', 'settings', 'generator', 'dashboard', 'auth', 'login', 'logout', 'register',
        'admin', 'crud', 'example', 'console', 'health', 'welcome', 'api', 'assets', 'uploads', 'system',
    ];

    public const RESERVED_TABLES = [
        'users', 'roles', 'role_permissions', 'settings', 'migrations', 'ci_sessions', 'crud_modules',
    ];

    public const MAX_FIELDS = 40;
    public const MAX_OPTIONS = 100;

    /**
     * @param array<string, mixed>      $input    decoded builder JSON
     * @param array<string, mixed>|null $existing the stored definition when editing
     * @param array<int, string>        $taken    slugs already used by other modules or permission groups
     * @param callable                  $schema   fn(string $table, ?string $column = null): bool, whether it exists in the database
     *
     * @return array{definition: array<string, mixed>|null, errors: array<string, string>}
     */
    public static function normalize(array $input, ?array $existing, array $taken, callable $schema)
    {
        $errors = [];
        $editing = $existing !== null;

        // ---- Module ------------------------------------------------------
        $slug = $editing ? (string) $existing['slug'] : strtolower(trim((string) ($input['slug'] ?? '')));
        if (!$editing) {
            if (!preg_match('/^[a-z][a-z0-9_]{1,29}$/', $slug)) {
                $errors['slug'] = 'Key must be 2-30 characters: lowercase letters, digits and underscores, starting with a letter.';
            } elseif (in_array($slug, self::RESERVED_SLUGS, true) || in_array($slug, $taken, true)) {
                $errors['slug'] = 'That key is already used by the application. Pick another.';
            }
        }

        $table = $editing ? (string) $existing['table'] : strtolower(trim((string) ($input['table'] ?? '')));
        if (!$editing) {
            $table = $table === '' ? $slug : $table;
            if (!preg_match('/^[a-z][a-z0-9_]{1,59}$/', $table)) {
                $errors['table'] = 'Table name must be lowercase letters, digits and underscores, starting with a letter.';
            } elseif (in_array($table, self::RESERVED_TABLES, true) || $schema($table, null)) {
                $errors['table'] = 'A table with that name already exists. Pick another name.';
            }
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 60) {
            $errors['title'] = 'Enter a title of up to 60 characters.';
        }

        $icon = trim((string) ($input['icon'] ?? ''));
        $icon = $icon === '' ? 'bi-table' : $icon;
        if (!preg_match('/^bi-[a-z0-9-]{1,60}$/', $icon)) {
            $errors['icon'] = 'Icon must be a Bootstrap Icons class such as bi-box-seam.';
        }

        // ---- Fields ------------------------------------------------------
        $rawFields = isset($input['fields']) && is_array($input['fields']) ? array_values($input['fields']) : [];
        if ($rawFields === []) {
            $errors['fields'] = 'Add at least one field.';
        } elseif (count($rawFields) > self::MAX_FIELDS) {
            $errors['fields'] = 'A module can have at most '.self::MAX_FIELDS.' fields.';
        }

        $oldByName = [];
        foreach ($editing ? $existing['fields'] : [] as $old) {
            $oldByName[$old['name']] = $old;
        }

        $fields = [];
        $seen = [];
        foreach ($rawFields as $i => $raw) {
            $raw = is_array($raw) ? $raw : [];
            [$field, $fieldErrors] = self::field($raw, $oldByName, $schema);

            $name = $field['name'];
            if ($name !== '' && isset($seen[$name])) {
                $fieldErrors['name'] = 'Two fields are named "'.$name.'".';
            }
            $seen[$name] = true;

            foreach ($fieldErrors as $key => $message) {
                $errors['fields.'.$i.'.'.$key] = $message;
            }
            $fields[] = $field;
        }

        foreach ($oldByName as $name => $old) {
            if (!isset($seen[$name])) {
                $errors['fields'] = 'The existing field "'.$name.'" cannot be removed here (its column would be lost). Delete the module to start over.';
                break;
            }
        }

        if ($errors !== []) {
            return ['definition' => null, 'errors' => $errors];
        }

        return [
            'definition' => [
                'slug' => $slug,
                'table' => $table,
                'title' => $title,
                'icon' => $icon,
                'fields' => $fields,
            ],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed>                $raw
     * @param array<string, array<string, mixed>> $oldByName
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function field(array $raw, array $oldByName, callable $schema)
    {
        $e = [];
        $name = strtolower(trim((string) ($raw['name'] ?? '')));
        $type = (string) ($raw['type'] ?? '');
        $label = trim((string) ($raw['label'] ?? ''));
        $old = $oldByName[$name] ?? null;

        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name)) {
            $e['name'] = 'Column name: lowercase letters, digits and underscores, starting with a letter (max 40).';
        } elseif (in_array($name, FieldTypes::RESERVED_COLUMNS, true)) {
            $e['name'] = '"'.$name.'" is added automatically. Pick another name.';
        }

        if (!FieldTypes::exists($type)) {
            $e['type'] = 'Choose an input type.';
        } elseif ($old !== null && $old['type'] !== $type) {
            $e['type'] = 'The type of an existing field cannot be changed.';
        }

        if ($label === '' || mb_strlen($label) > 60) {
            $e['label'] = 'Enter a label of up to 60 characters.';
        }

        $isNumber = $type === FieldTypes::NUMBER;
        $field = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => !empty($raw['required']),
            'unique' => !empty($raw['unique']) && FieldTypes::canBeUnique($type),
            'list' => !empty($raw['list']) && $type !== FieldTypes::PASSWORD,
            'help' => mb_substr(trim((string) ($raw['help'] ?? '')), 0, 200),
        ];

        if ($type === FieldTypes::TEXT || $type === FieldTypes::TEXTAREA) {
            $max = $type === FieldTypes::TEXT ? 255 : 10000;
            $length = (int) ($raw['maxlength'] ?? $max);
            $field['maxlength'] = $length >= 1 && $length <= $max ? $length : $max;
        }

        if ($isNumber) {
            $field['integer'] = !empty($raw['integer']);
            if ($old !== null && !empty($old['integer']) !== $field['integer']) {
                $e['integer'] = 'Whole number / decimal cannot be changed on an existing field.';
            }
            foreach (['min', 'max'] as $bound) {
                $v = $raw[$bound] ?? '';
                $field[$bound] = $v === '' || $v === null ? null : (is_numeric($v) ? $v + 0 : null);
            }
            if ($field['min'] !== null && $field['max'] !== null && $field['min'] > $field['max']) {
                $e['min'] = 'Minimum is greater than the maximum.';
            }
        }

        if ($type === FieldTypes::PASSWORD) {
            $min = (int) ($raw['min_length'] ?? 8);
            $field['min_length'] = max(4, min(64, $min));
        }

        if (FieldTypes::hasOptions($type)) {
            [$options, $optionErrors] = self::options((array) ($raw['options'] ?? []), $schema);
            $field['options'] = $options;
            foreach ($optionErrors as $k => $m) {
                $e['options.'.$k] = $m;
            }
        }

        if (FieldTypes::isUpload($type)) {
            [$upload, $uploadErrors] = self::upload($type, (array) ($raw['upload'] ?? []));
            $field['upload'] = $upload;
            foreach ($uploadErrors as $k => $m) {
                $e['upload.'.$k] = $m;
            }
        }

        $field['search'] = $field['list'] && FieldTypes::isSearchable($type);

        return [$field, $e];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function options(array $raw, callable $schema)
    {
        $e = [];
        $mode = ($raw['mode'] ?? 'static') === 'table' ? 'table' : 'static';

        if ($mode === 'table') {
            $table = strtolower(trim((string) ($raw['table'] ?? '')));
            $value = strtolower(trim((string) ($raw['value_column'] ?? '')));
            $label = strtolower(trim((string) ($raw['label_column'] ?? '')));

            foreach (['table' => $table, 'value_column' => $value, 'label_column' => $label] as $k => $v) {
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $v)) {
                    $e[$k] = 'Enter a valid '.str_replace('_', ' ', $k).'.';
                }
            }
            if ($e === []) {
                if (!$schema($table, null)) {
                    $e['table'] = 'Table "'.$table.'" does not exist.';
                } else {
                    foreach (['value_column' => $value, 'label_column' => $label] as $k => $v) {
                        if (!$schema($table, $v)) {
                            $e[$k] = 'Column "'.$v.'" does not exist in "'.$table.'".';
                        }
                    }
                }
            }

            return [['mode' => 'table', 'table' => $table, 'value_column' => $value, 'label_column' => $label], $e];
        }

        $items = [];
        $values = [];
        foreach (array_values((array) ($raw['items'] ?? [])) as $item) {
            $item = is_array($item) ? $item : [];
            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($value === '' && $label === '') {
                continue;
            }
            $value = $value === '' ? $label : $value;
            $label = $label === '' ? $value : $label;

            if (mb_strlen($value) > 100 || mb_strlen($label) > 100 || preg_match('/[\x00-\x1f]/', $value.$label)) {
                $e['items'] = 'Option values and labels must be up to 100 characters, without control characters.';
            }
            if (isset($values[$value])) {
                $e['items'] = 'Option value "'.$value.'" is listed twice.';
            }
            $values[$value] = true;
            $items[] = ['value' => $value, 'label' => $label];
        }

        if ($items === []) {
            $e['items'] = 'Add at least one option.';
        } elseif (count($items) > self::MAX_OPTIONS) {
            $e['items'] = 'At most '.self::MAX_OPTIONS.' fixed options.';
        }

        return [['mode' => 'static', 'items' => $items], $e];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function upload($type, array $raw)
    {
        $e = [];
        $isImage = $type === FieldTypes::IMAGE;

        $maxKb = (int) ($raw['max_kb'] ?? ($isImage ? 2048 : 5120));
        if ($maxKb < 1 || $maxKb > 51200) {
            $e['max_kb'] = 'Maximum size must be between 1 KB and 51200 KB (50 MB).';
            $maxKb = $isImage ? 2048 : 5120;
        }

        $types = $raw['types'] ?? ($isImage ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip']);
        if (is_string($types)) {
            $types = preg_split('/[\s,;]+/', strtolower($types)) ?: [];
        }
        $clean = [];
        foreach ((array) $types as $ext) {
            $ext = ltrim(strtolower(trim((string) $ext)), '.');
            if ($ext === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
                $e['types'] = 'Extensions are letters/digits only, e.g. pdf, docx.';
            } elseif (in_array($ext, FieldTypes::DANGEROUS_EXTENSIONS, true)) {
                $e['types'] = '".'.$ext.'" files can run code in the browser or on the server and cannot be allowed.';
            } elseif ($isImage && !in_array($ext, FieldTypes::IMAGE_EXTENSIONS, true)) {
                $e['types'] = 'Images can be: '.implode(', ', FieldTypes::IMAGE_EXTENSIONS).'.';
            }
            $clean[$ext] = true;
        }
        $clean = array_keys($clean);
        if ($clean === []) {
            $e['types'] = 'Allow at least one file type.';
        }

        $upload = ['max_kb' => $maxKb, 'types' => $clean];

        if ($isImage) {
            $width = (int) ($raw['max_width'] ?? 1600);
            if ($width !== 0 && ($width < 100 || $width > 8000)) {
                $e['max_width'] = 'Max width is 100-8000 px (0 keeps the original size).';
                $width = 1600;
            }
            $quality = (int) ($raw['quality'] ?? 80);
            if ($quality < 30 || $quality > 100) {
                $e['quality'] = 'Quality is 30-100.';
                $quality = 80;
            }
            $upload['max_width'] = $width;
            $upload['quality'] = $quality;
        }

        return [$upload, $e];
    }
}
