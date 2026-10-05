<?php

namespace App\Audit;

/**
 * Turns "what was saved" into what the audit log stores: a flat, readable
 * list of values with secrets hidden, long text cut, and the total size kept
 * inside what a TEXT column holds. Pure PHP, unit-tested.
 *
 *   snapshot($row)          every value of a new or deleted record
 *   diff($before, $after)   only what changed, as from -> to (or added / removed for lists)
 */
final class Changes
{
    public const HIDDEN = '[hidden]';

    /** A value longer than this is cut in the log. */
    public const MAX_VALUE = 500;

    /** The most the whole JSON may take (a MySQL TEXT column holds 65535 bytes). */
    public const MAX_BYTES = 60000;

    private const MAX_DEPTH = 6;

    /**
     * Whether the value under this key must never be written to the log.
     *
     * @param array<int, string> $hidden extra exact keys (e.g. the password fields of a generated module)
     */
    public static function isSensitive($key, array $hidden = [])
    {
        $last = (string) (strrpos((string) $key, '.') === false ? $key : substr((string) $key, (int) strrpos((string) $key, '.') + 1));

        return in_array((string) $key, $hidden, true)
            || in_array($last, $hidden, true)
            || (bool) preg_match('/(pass(word|wd)?$|^pass|secret|token|hash|api[_-]?key|private[_-]?key)/i', $last);
    }

    /**
     * Nested data as one level of "a.b.c" => value. Lists of plain values
     * stay together as one array value; everything else is expanded.
     *
     * @param array<mixed>|object $data
     *
     * @return array<string, mixed>
     */
    public static function flatten($data, $prefix = '', $depth = 0)
    {
        $out = [];

        foreach ((array) $data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_object($value)) {
                $value = (array) $value;
            }

            if (!is_array($value)) {
                $out[$path] = $value;
            } elseif ($depth >= self::MAX_DEPTH || self::isPlainList($value)) {
                $out[$path] = $depth >= self::MAX_DEPTH && !self::isPlainList($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            } else {
                $out += self::flatten($value, $path, $depth + 1);
            }
        }

        return $out;
    }

    /**
     * Every value of a record, secrets hidden, text cut.
     *
     * @param array<mixed>|object $data
     * @param array<int, string>  $hidden
     *
     * @return array<string, mixed>
     */
    public static function snapshot($data, array $hidden = [])
    {
        $out = [];

        foreach (self::flatten($data) as $key => $value) {
            if (self::isEmpty($value)) {
                continue;
            }
            $out[$key] = self::isSensitive($key, $hidden) ? self::HIDDEN : self::shorten($value);
        }

        return $out;
    }

    /**
     * Only what differs between two versions of a record.
     *
     * @param array<mixed>|object $before
     * @param array<mixed>|object $after
     * @param array<int, string>  $hidden
     *
     * @return array<string, array<string, mixed>> key => ['from' =>, 'to' =>] or ['added' =>, 'removed' =>]
     */
    public static function diff($before, $after, array $hidden = [])
    {
        $a = self::flatten($before);
        $b = self::flatten($after);
        $changes = [];

        foreach (array_unique(array_merge(array_keys($b), array_keys($a))) as $key) {
            $from = $a[$key] ?? null;
            $to = $b[$key] ?? null;

            if (is_array($from) || is_array($to)) {
                $old = array_map('strval', (array) $from);
                $new = array_map('strval', (array) $to);
                $added = array_values(array_diff($new, $old));
                $removed = array_values(array_diff($old, $new));
                if ($added !== [] || $removed !== []) {
                    $changes[$key] = ['added' => $added, 'removed' => $removed];
                }

                continue;
            }

            if (self::same($from, $to)) {
                continue;
            }

            $changes[$key] = self::isSensitive($key, $hidden)
                ? ['from' => self::HIDDEN, 'to' => self::HIDDEN]
                : ['from' => self::shorten($from), 'to' => self::shorten($to)];
        }

        return $changes;
    }

    /**
     * JSON for the log column, never longer than $max bytes: very long values
     * are cut harder, and as a last resort only a note is kept.
     *
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, $max = self::MAX_BYTES)
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $json = (string) json_encode($payload, $flags);

        foreach ([200, 60] as $limit) {
            if (strlen($json) <= $max) {
                return $json;
            }
            $payload = self::cut($payload, $limit);
            $json = (string) json_encode($payload, $flags);
        }

        return strlen($json) <= $max ? $json : (string) json_encode(['truncated' => true, 'note' => 'Too large to store in full.'], $flags);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function shorten($value)
    {
        if (is_string($value) && mb_strlen($value) > self::MAX_VALUE) {
            return mb_substr($value, 0, self::MAX_VALUE).'… (+'.(mb_strlen($value) - self::MAX_VALUE).' characters)';
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isPlainList(array $value)
    {
        if ($value === [] || array_keys($value) !== range(0, count($value) - 1)) {
            return $value === [];
        }

        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    private static function isEmpty($value)
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * null and '' count as the same "nothing"; numbers and their text too.
     *
     * @param mixed $a
     * @param mixed $b
     */
    private static function same($a, $b)
    {
        if (self::isEmpty($a) && self::isEmpty($b)) {
            return true;
        }

        return is_scalar($a) && is_scalar($b) ? (string) $a === (string) $b : $a === $b;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    private static function cut($data, $limit)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::cut($v, $limit);
            }

            return $data;
        }

        return is_string($data) && mb_strlen($data) > $limit ? mb_substr($data, 0, $limit).'…' : $data;
    }
}
