<?php

namespace App\Crud;

/**
 * Checks one submitted record against a module definition and returns the
 * values to store. Uploads are handled by Uploader; this only needs to know
 * whether each upload field gets a new file or is cleared.
 *
 * The database is reached through callables so this stays unit-testable:
 *
 *   $lookup(array $field, array $values): array  the subset of $values that exist in a table-backed field
 *   $isTaken(string $column, string $value, ?int $ignoreId): bool  unique check
 */
final class RecordValidator
{
    /**
     * @param array<string, mixed>      $definition
     * @param array<string, mixed>      $input       posted fields (strings/arrays)
     * @param array<string, mixed>|null $existing    the stored row when editing
     * @param array<string, string>     $uploads     field => 'new' | 'remove' for upload fields touched in this request
     *
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $definition, array $input, ?array $existing, array $uploads, callable $lookup, callable $isTaken)
    {
        $values = [];
        $errors = [];
        $id = $existing !== null ? (int) $existing['id'] : null;

        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'];
            $raw = $input[$name] ?? null;

            if (FieldTypes::isUpload($type)) {
                $hasOld = $existing !== null && ($existing[$name] ?? '') !== '' && ($existing[$name] ?? null) !== null;
                $change = $uploads[$name] ?? null;
                $willHave = $change === 'new' || ($hasOld && $change !== 'remove');
                if ($field['required'] && !$willHave) {
                    $errors[$name] = $label.' is required.';
                }

                continue;
            }

            if ($type === FieldTypes::MULTISELECT) {
                $list = is_array($raw) ? array_values(array_unique(array_map('strval', $raw))) : [];
                [$ok, $message] = self::checkMulti($field, $list, $lookup);
                if (!$ok) {
                    $errors[$name] = $message;
                } elseif ($list === []) {
                    if ($field['required']) {
                        $errors[$name] = $label.' needs at least one choice.';
                    }
                    $values[$name] = null;
                } else {
                    $values[$name] = json_encode($list, JSON_UNESCAPED_UNICODE);
                }

                continue;
            }

            $value = is_string($raw) ? trim($raw) : ($raw === null ? '' : null);

            if ($value === null) {
                $errors[$name] = $label.' is not valid.';

                continue;
            }

            if ($type === FieldTypes::PASSWORD) {
                if ($value === '') {
                    if ($existing === null && $field['required']) {
                        $errors[$name] = $label.' is required.';
                    }
                    // Editing with the box empty keeps the current password.
                    continue;
                }
                $raw = (string) $input[$name]; // do not trim passwords
                if (mb_strlen($raw) < $field['min_length']) {
                    $errors[$name] = $label.' must be at least '.$field['min_length'].' characters.';
                } elseif (strlen($raw) > 72) {
                    $errors[$name] = $label.' can be at most 72 characters.';
                } else {
                    $values[$name] = password_hash($raw, PASSWORD_DEFAULT);
                }

                continue;
            }

            if ($value === '') {
                if ($field['required']) {
                    $errors[$name] = $label.' is required.';
                }
                $values[$name] = null;

                continue;
            }

            $message = self::checkScalar($field, $value, $lookup);
            if ($message !== null) {
                $errors[$name] = $message;

                continue;
            }

            if (!empty($field['unique']) && $isTaken($name, $value, $id)) {
                $errors[$name] = $label.' "'.$value.'" is already taken.';

                continue;
            }

            $values[$name] = $type === FieldTypes::NUMBER && !empty($field['integer']) ? (int) $value : $value;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function checkScalar(array $field, $value, callable $lookup)
    {
        $label = $field['label'];

        switch ($field['type']) {
            case FieldTypes::TEXT:
            case FieldTypes::TEXTAREA:
                if (mb_strlen($value) > $field['maxlength']) {
                    return $label.' can be at most '.$field['maxlength'].' characters.';
                }
                if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) {
                    return $label.' contains invalid characters.';
                }

                return null;

            case FieldTypes::EMAIL:
                return filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 190 ? null : $label.' must be a valid email address.';

            case FieldTypes::DATE:
                $d = \DateTime::createFromFormat('!Y-m-d', $value);

                return $d && $d->format('Y-m-d') === $value ? null : $label.' must be a date (YYYY-MM-DD).';

            case FieldTypes::NUMBER:
                $pattern = !empty($field['integer']) ? '/^-?\d{1,18}$/' : '/^-?\d{1,13}(\.\d{1,2})?$/';
                if (!preg_match($pattern, $value)) {
                    return $label.(!empty($field['integer']) ? ' must be a whole number.' : ' must be a number with up to 2 decimals.');
                }
                if (isset($field['min']) && $value + 0 < $field['min']) {
                    return $label.' must be at least '.$field['min'].'.';
                }
                if (isset($field['max']) && $value + 0 > $field['max']) {
                    return $label.' must be at most '.$field['max'].'.';
                }

                return null;

            case FieldTypes::SELECT:
            case FieldTypes::RADIO:
                [$ok, $message] = self::checkMulti($field, [$value], $lookup);

                return $ok ? null : $message;
        }

        return null;
    }

    /**
     * Every chosen value must be one of the field's options.
     *
     * @param array<string, mixed> $field
     * @param array<int, string>   $chosen
     *
     * @return array{0: bool, 1: string}
     */
    private static function checkMulti(array $field, array $chosen, callable $lookup)
    {
        if ($chosen === []) {
            return [true, ''];
        }

        $options = $field['options'];
        if ($options['mode'] === 'static') {
            $allowed = array_column($options['items'], 'value');
            $known = array_values(array_intersect($chosen, array_map('strval', $allowed)));
        } else {
            $known = array_map('strval', $lookup($field, $chosen));
        }

        return count(array_diff($chosen, $known)) === 0
            ? [true, '']
            : [false, $field['label'].' has a choice that is not available.'];
    }
}
