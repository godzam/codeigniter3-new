<?php

namespace AppTests\Unit;

use App\DataTables\Params;
use PHPUnit\Framework\TestCase;

final class DataTablesParamsTest extends TestCase
{
    private const ORDERABLE = [0 => true, 1 => true, 2 => false];

    public function testDefaultsForAnEmptyRequest(): void
    {
        $p = Params::fromRequest([], self::ORDERABLE);

        $this->assertSame(0, $p->draw);
        $this->assertSame(0, $p->start);
        $this->assertSame(Params::DEFAULT_LENGTH, $p->length);
        $this->assertSame('', $p->search);
        $this->assertSame([], $p->order);
        $this->assertSame([], $p->terms());
    }

    public function testPageSizeIsCapped(): void
    {
        $this->assertSame(Params::MAX_LENGTH, Params::fromRequest(['length' => '100000'], self::ORDERABLE)->length);
        // DataTables sends -1 for "all"; we never allow unbounded reads.
        $this->assertSame(Params::DEFAULT_LENGTH, Params::fromRequest(['length' => '-1'], self::ORDERABLE)->length);
        $this->assertSame(25, Params::fromRequest(['length' => '25'], self::ORDERABLE)->length);
    }

    public function testNegativeOffsetsAreClamped(): void
    {
        $p = Params::fromRequest(['start' => '-50', 'draw' => '-3'], self::ORDERABLE);

        $this->assertSame(0, $p->start);
        $this->assertSame(0, $p->draw);
    }

    public function testOnlyDeclaredOrderableColumnsCanBeSorted(): void
    {
        $p = Params::fromRequest(['order' => [
            ['column' => '1', 'dir' => 'DESC'],
            ['column' => '2', 'dir' => 'asc'],      // not orderable
            ['column' => '9', 'dir' => 'asc'],      // does not exist
            ['column' => 'name; DROP TABLE', 'dir' => 'asc'],
            ['column' => '0', 'dir' => 'sideways'], // unknown direction
            'junk',
        ]], self::ORDERABLE);

        $this->assertSame([['column' => 1, 'dir' => 'desc'], ['column' => 0, 'dir' => 'asc']], $p->order);
    }

    public function testSearchIsSplitIntoTerms(): void
    {
        $p = Params::fromRequest(['search' => ['value' => "  ann   admin\t"]], self::ORDERABLE);

        $this->assertSame('ann   admin', $p->search);
        $this->assertSame(['ann', 'admin'], $p->terms());
    }

    public function testBadShapesDoNotBlowUp(): void
    {
        $p = Params::fromRequest(['search' => 'plain', 'order' => 'x', 'length' => ['a']], self::ORDERABLE);

        $this->assertSame('', $p->search);
        $this->assertSame([], $p->order);
        $this->assertSame(Params::DEFAULT_LENGTH, $p->length);
    }

    public function testLongSearchIsTruncated(): void
    {
        $p = Params::fromRequest(['search' => ['value' => str_repeat('a', 500)]], self::ORDERABLE);

        $this->assertSame(100, mb_strlen($p->search));
    }
}
