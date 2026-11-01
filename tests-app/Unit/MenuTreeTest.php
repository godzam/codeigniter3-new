<?php

namespace AppTests\Unit;

use App\Menus\MenuTree;
use PHPUnit\Framework\TestCase;

final class MenuTreeTest extends TestCase
{
    /** id => current parent, i.e. rows 1..4 exist. */
    private function known()
    {
        return [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    }

    public function testFlatOrderStaysFlat(): void
    {
        $parents = MenuTree::parents([[1, 0], [2, 0], [3, 0]], [1 => 0, 2 => 0, 3 => 0]);

        $this->assertSame([1 => 0, 2 => 0, 3 => 0], $parents);
    }

    public function testDepthBecomesAParent(): void
    {
        $parents = MenuTree::parents([[1, 0], [2, 1], [3, 1], [4, 0]], $this->known());

        $this->assertSame([1 => 0, 2 => 1, 3 => 1, 4 => 0], $parents);
    }

    public function testSecondLevelNestsUnderTheItemAboveIt(): void
    {
        $parents = MenuTree::parents([[1, 0], [2, 1], [3, 2], [4, 2]], $this->known());

        $this->assertSame([1 => 0, 2 => 1, 3 => 2, 4 => 2], $parents);
    }

    public function testARowThatSkipsALevelFallsBackToTheNearestAncestor(): void
    {
        // Depth 2 straight after a depth 0 item: the missing level is skipped.
        $parents = MenuTree::parents([[1, 0], [2, 0], [3, 2]], [1 => 0, 2 => 0, 3 => 0]);

        $this->assertSame([1 => 0, 2 => 0, 3 => 2], $parents);
    }

    public function testUnknownAndDuplicateIdsAreRejected(): void
    {
        $this->assertFalse(MenuTree::parents([[1, 0], [99, 0]], $this->known()));
        $this->assertFalse(MenuTree::parents([[1, 0], [1, 1], [2, 0], [3, 0], [4, 0]], $this->known()));
    }

    public function testAPartialOrderIsRejected(): void
    {
        $this->assertFalse(MenuTree::parents([[1, 0], [2, 0]], $this->known()), 'rows were left out');
        $this->assertFalse(MenuTree::parents([], $this->known()));
    }

    public function testDepthsBeyondTheLimitAreRejected(): void
    {
        $this->assertFalse(MenuTree::parents([[1, 0], [2, 1], [3, 4]], [1 => 0, 2 => 0, 3 => 0]));
        $this->assertFalse(MenuTree::parents([[1, -1], [2, 0]], [1 => 0, 2 => 0]));
    }

    public function testCanNestRules(): void
    {
        // 1 (top) > 2 > 3, and 4 alone.   ids => parent
        $tree = [1 => 0, 2 => 1, 3 => 2, 4 => 0];

        $this->assertTrue(MenuTree::canNest($tree, 4, 0), 'top level is always fine');
        $this->assertTrue(MenuTree::canNest($tree, 4, 1), 'a lone item under a top-level one');
        $this->assertFalse(MenuTree::canNest($tree, 4, 3), 'under the third level would be level four');
        $this->assertFalse(MenuTree::canNest($tree, 1, 1), 'never its own parent');
        $this->assertFalse(MenuTree::canNest($tree, 1, 3), 'never inside its own subtree');
        $this->assertFalse(MenuTree::canNest($tree, 2, 3), 'a child cannot become its own parent\'s child');
        $this->assertFalse(MenuTree::canNest($tree, 4, 99), 'the parent must exist');
        $this->assertFalse(MenuTree::canNest($tree, 1, 4), 'a subtree that is already three levels deep cannot move down');
        $this->assertTrue(MenuTree::canNest([1 => 0, 2 => 0, 3 => 2], 2, 1), 'a two-level subtree may go under a top-level item');
    }

    public function testCanNestForAnItemThatDoesNotExistYet(): void
    {
        // 1 (top) > 2 > 3 : a new item (id 0) has no subtree of its own
        $tree = [1 => 0, 2 => 1, 3 => 2, 4 => 0];

        $this->assertTrue(MenuTree::canNest($tree, 0, 0));
        $this->assertTrue(MenuTree::canNest($tree, 0, 1), 'a new item under a top-level one');
        $this->assertTrue(MenuTree::canNest($tree, 0, 2), 'a new item may be the third level');
        $this->assertFalse(MenuTree::canNest($tree, 0, 3), 'but not the fourth');
        $this->assertFalse(MenuTree::canNest($tree, 0, 99), 'and its parent must exist');
    }

    public function testCanNestSurvivesLoopsInStoredData(): void
    {
        $this->assertFalse(MenuTree::canNest([1 => 2, 2 => 1, 3 => 0], 3, 1), 'a loop above the new parent is unusable');
    }
}

