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
}
