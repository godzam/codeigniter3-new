<?php

namespace App\Export;

use App\Crud\FieldTypes;

/**
 * What a stored value looks like in an export: plain text or a number, never
 * HTML, never a secret.
 */
final class Cell
{
    /**
     * @param array<string, mixed>  $field
     * @param mixed                 $value  as stored in the table
     * @param array<string, string> $labels value => label for dropdown fields
     *
     * @return int|float|string|null numbers stay numbers (so Excel can sum them)
     */
    public static function value(array $field, $value, array $labels = [])
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($field['type']) {
            case FieldTypes::NUMBER:
                $text = (string) $value;
                if (!is_numeric($text) || preg_match('/^-?0\d/', $text)) {
                    return $text;
                }
                if (preg_match('/^-?\d+$/', $text)) {
                    // Excel keeps 15 digits; longer whole numbers (card or phone numbers) stay text.
                    return strlen(ltrim($text, '-')) <= 15 ? (int) $text : $text;
                }

                return (float) $text;

            case FieldTypes::SELECT:
            case FieldTypes::RADIO:
                return (string) ($labels[$value] ?? $value);

            case FieldTypes::MULTISELECT:
                $out = [];
                foreach ((array) json_decode((string) $value, true) as $v) {
                    $out[] = (string) ($labels[$v] ?? $v);
                }

                return implode(', ', $out);

            case FieldTypes::IMAGE:
            case FieldTypes::FILE:
                return basename((string) $value);

            case FieldTypes::PASSWORD:
                return null;

            default:
                return (string) $value;
        }
    }
}
