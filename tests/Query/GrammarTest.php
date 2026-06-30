<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Query;

use PHPUnit\Framework\TestCase;
use SimpleORM\Connection\Connection;
use SimpleORM\Query\QueryBuilder;

/**
 * Pure SQL-compilation tests — no database connection is opened.
 */
final class GrammarTest extends TestCase
{
    private function builder(): QueryBuilder
    {
        return (new QueryBuilder(new Connection([])))->from('users');
    }

    private function compile(QueryBuilder $query): string
    {
        return $query->grammar->compileSelect($query);
    }

    public function testBasicSelect(): void
    {
        self::assertSame('select * from `users`', $this->compile($this->builder()));
    }

    public function testSelectColumnsWithAliasAndDottedNames(): void
    {
        $query = $this->builder()->select(['users.*', 'roles.name as role_name']);

        self::assertSame(
            'select `users`.*, `roles`.`name` as `role_name` from `users`',
            $this->compile($query)
        );
    }

    public function testBasicWhereWithBinding(): void
    {
        $query = $this->builder()->where('age', '>', 18);

        self::assertSame('select * from `users` where `age` > ?', $this->compile($query));
        self::assertSame([18], $query->getBindings());
    }

    public function testWhereShorthandEqualsOperator(): void
    {
        $query = $this->builder()->where('active', 1);

        self::assertSame('select * from `users` where `active` = ?', $this->compile($query));
        self::assertSame([1], $query->getBindings());
    }

    public function testWhereInAndEmptyWhereIn(): void
    {
        $query = $this->builder()->whereIn('id', [1, 2, 3]);
        self::assertSame('select * from `users` where `id` in (?, ?, ?)', $this->compile($query));

        $empty = $this->builder()->whereIn('id', []);
        self::assertSame('select * from `users` where 0 = 1', $this->compile($empty));
    }

    public function testWhereNull(): void
    {
        $query = $this->builder()->whereNull('deleted_at');

        self::assertSame('select * from `users` where `deleted_at` is null', $this->compile($query));
    }

    public function testMultipleWheresWithBooleans(): void
    {
        $query = $this->builder()->where('a', '=', 1)->orWhere('b', '=', 2);

        self::assertSame('select * from `users` where `a` = ? or `b` = ?', $this->compile($query));
    }

    public function testOrderLimitOffset(): void
    {
        $query = $this->builder()->orderBy('name')->limit(10)->offset(5);

        self::assertSame('select * from `users` order by `name` asc limit 10 offset 5', $this->compile($query));
    }

    public function testJoin(): void
    {
        $query = $this->builder()->join('roles', 'users.role_id', '=', 'roles.id');

        self::assertSame(
            'select * from `users` inner join `roles` on `users`.`role_id` = `roles`.`id`',
            $this->compile($query)
        );
    }

    public function testCompileInsert(): void
    {
        $query = $this->builder();

        self::assertSame(
            'insert into `users` (`name`, `email`) values (?, ?)',
            $query->grammar->compileInsert($query, ['name' => 'A', 'email' => 'b'])
        );
    }

    public function testCompileUpdate(): void
    {
        $query = $this->builder()->where('id', '=', 1);

        self::assertSame(
            'update `users` set `name` = ? where `id` = ?',
            $query->grammar->compileUpdate($query, ['name' => 'A'])
        );
    }

    public function testCompileDelete(): void
    {
        $query = $this->builder()->where('id', '=', 1);

        self::assertSame('delete from `users` where `id` = ?', $query->grammar->compileDelete($query));
    }

    public function testIdentifierWithBacktickIsEscapedNotInjected(): void
    {
        $query = $this->builder()->where('a`b', '=', 1);

        // The stray backtick is doubled, never breaking out of the quoted identifier.
        self::assertSame('select * from `users` where `a``b` = ?', $this->compile($query));
    }

    public function testCountAggregateCompilation(): void
    {
        $query = $this->builder();
        $query->aggregate = ['function' => 'count', 'columns' => ['*']];

        self::assertSame('select count(*) as `aggregate` from `users`', $this->compile($query));
    }
}
