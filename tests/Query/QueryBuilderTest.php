<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Query;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SimpleORM\Connection\Connection;
use SimpleORM\Query\QueryBuilder;

final class QueryBuilderTest extends TestCase
{
    private function builder(): QueryBuilder
    {
        return (new QueryBuilder(new Connection([])))->from('users');
    }

    public function testBindingsAreOrderedWhereThenHaving(): void
    {
        $query = $this->builder()
            ->where('a', '=', 1)
            ->groupBy('a')
            ->having('total', '>', 5);

        self::assertSame([1, 5], $query->getBindings());
    }

    public function testWhereInAddsAllValuesAsBindings(): void
    {
        $query = $this->builder()->whereIn('id', [10, 20, 30]);

        self::assertSame([10, 20, 30], $query->getBindings());
    }

    public function testUnsupportedOperatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->where('a', 'DROP', 1);
    }

    public function testInvalidOrderDirectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->orderBy('a', 'sideways');
    }

    public function testForPageComputesLimitAndOffset(): void
    {
        $query = $this->builder()->forPage(3, 10);

        self::assertSame(10, $query->limit);
        self::assertSame(20, $query->offset);
    }

    public function testNegativeLimitIsClampedToZero(): void
    {
        $query = $this->builder()->limit(-5);

        self::assertSame(0, $query->limit);
    }
}
