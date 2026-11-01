<?php

namespace App\Menus;

/**
 * Turns the order the menu builder dragged things into — a flat list of
 * [id, depth] pairs — into a parent for every item, without touching the
 * database. Pure PHP, unit-tested (tests-app/Unit/MenuTreeTest.php).
 *
 * Depth 0 is the top level; an item at depth d becomes a child of the last
 * item seen at depth d-1. The caller writes the result back as
 * parent_id + position.
 */
final class MenuTree
{
    /**
     * @param array<int, array{0: int, 1: int}> $order posted as [id, depth]
     * @param array<int, int>                   $known id => current parent_id, i.e. every row that must appear
     * @param int                               $maxDepth deepest nesting allowed
     *
     * @return array<int, int>|false id => parent id, or false when the order is unusable
     *                               (unknown/duplicate id, missing row, impossible depth)
     */
    public static function parents($order, array $known, $maxDepth = 2)
    {
        if (!is_array($order) || $order === [] || $known === []) {
            return false;
        }

        $parents = [];
        $stack = []; // depth => id: the ancestor on duty at each level

        foreach ($order as $pair) {
            if (!is_array($pair) || !isset($pair[0], $pair[1])) {
                return false;
            }

            $id = (int) $pair[0];
            $depth = (int) $pair[1];

            if (!isset($known[$id]) || array_key_exists($id, $parents)) {
                return false; // unknown or repeated
            }
            if ($depth < 0 || $depth > $maxDepth) {
                return false;
            }

            $parent = 0;
            for ($d = $depth - 1; $d >= 0; $d--) {
                if (isset($stack[$d])) {
                    $parent = (int) $stack[$d];
                    break;
                }
            }
            if ($parent === $id) {
                return false;
            }

            $parents[$id] = $parent;
            $stack[$depth] = $id;
            unset($stack[$depth + 1], $stack[$depth + 2]);
        }

        if (count($parents) !== count($known)) {
            return false; // only part of the menu was sent
        }

        // No item may end up inside its own subtree (a cycle would hide it).
        foreach ($parents as $id => $parent) {
            $seen = [$id];
            while ($parent > 0) {
                if (in_array($parent, $seen, true)) {
                    return false;
                }
                $seen[] = $parent;
                $parent = (int) ($parents[$parent] ?? 0);
            }
        }

        return $parents;
    }

    /**
     * Whether an item may be given a new parent without breaking the menu.
     *
     * Refused when the parent is the item itself or one of its own descendants (a loop
     * would hide them), does not exist, or would push the item's subtree below $maxDepth.
     *
     * @param array<int, int> $parents   id => parent id (0 = top level) of every item
     * @param int             $id        the item being moved/edited
     * @param int             $newParent 0 for the top level
     */
    public static function canNest(array $parents, $id, $newParent, $maxDepth = 2)
    {
        $id = (int) $id;
        $newParent = (int) $newParent;

        if ($newParent === 0) {
            return true;
        }
        if ($newParent === $id || !isset($parents[$newParent])) {
            return false;
        }

        // Depth of the new parent (top level = 0); a loop in the stored data counts as unusable.
        $depth = 0;
        $seen = [$newParent];
        for ($p = (int) $parents[$newParent]; $p > 0; $p = (int) ($parents[$p] ?? 0)) {
            if ($p === $id || in_array($p, $seen, true)) {
                return false; // the parent sits inside the item's own subtree
            }
            $seen[] = $p;
            ++$depth;
        }

        // Levels below the item itself (a new item, id 0, has none; top-level rows have parent 0 too).
        $height = 0;
        $level = $id > 0 ? [$id] : [];
        while ($level !== []) {
            $next = [];
            foreach ($parents as $child => $parent) {
                if (in_array((int) $parent, $level, true) && (int) $child !== $id) {
                    $next[] = (int) $child;
                }
            }
            if ($next !== []) {
                ++$height;
            }
            $level = count($next) > 50 ? [] : $next; // a menu is small; stop on nonsense
        }

        return ($depth + 1) + $height <= $maxDepth;
    }
}
