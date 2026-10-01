<?php

namespace App\DataTables;

/**
 * Reads the query string DataTables sends in server-side mode
 * (draw, start, length, search[value], order[n][column|dir]) and turns it
 * into values that are safe to hand to the query builder.
 *
 * Nothing from the request reaches SQL as-is: columns are chosen by index
 * from the list the server declared, direction is asc/desc, and the page
 * size is capped.
 */
final class Params
{
    public const MAX_LENGTH = 100;
    public const DEFAULT_LENGTH = 10;

    /** @var int */
    public $draw;

    /** @var int */
    public $start;

    /** @var int */
    public $length;

    /** @var string */
    public $search;

    /** @var array<int, array{column: int, dir: string}> */
    public $order;

    /**
     * @param array<string, mixed> $request usually $_GET
     * @param array<int, bool> $orderable column index => may be sorted
     */
    public static function fromRequest(array $request, array $orderable)
    {
        $p = new self();
        $int = static function ($key, $default) use ($request) {
            return isset($request[$key]) && is_numeric($request[$key]) ? (int) $request[$key] : $default;
        };

        $p->draw = max(0, $int('draw', 0));
        $p->start = max(0, $int('start', 0));

        $length = $int('length', self::DEFAULT_LENGTH);
        $p->length = $length < 1 ? self::DEFAULT_LENGTH : min($length, self::MAX_LENGTH);

        $search = $request['search']['value'] ?? '';
        $p->search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';

        $p->order = [];
        $order = $request['order'] ?? [];
        foreach (is_array($order) ? $order : [] as $o) {
            if (!is_array($o)) {
                continue;
            }
            $col = isset($o['column']) && is_numeric($o['column']) ? (int) $o['column'] : -1;
            if (empty($orderable[$col])) {
                continue;
            }
            $p->order[] = [
                'column' => $col,
                'dir' => strtolower((string) ($o['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ];
        }

        return $p;
    }

    /**
     * Words of the search box. Each word must match somewhere in the row,
     * so "ann admin" finds Ann with the admin role.
     *
     * @return array<int, string>
     */
    public function terms()
    {
        return $this->search === '' ? [] : array_values(array_filter(preg_split('/\s+/u', $this->search) ?: [], 'strlen'));
    }
}
