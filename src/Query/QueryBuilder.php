<?php

declare(strict_types=1);

namespace SimpleORM\Query;

use InvalidArgumentException;
use SimpleORM\Connection\Connection;

/**
 * Fluent builder for all four verbs. SQL is produced by Grammar; this class only
 * collects state and binds values. Operators are whitelisted and identifiers are
 * never interpolated raw, so the only values that reach SQL are bound parameters.
 */
class QueryBuilder
{
    /** @var array<int,string> */
    public array $columns = ['*'];

    public bool $distinct = false;

    public string $from = '';

    /** @var array<int,array<string,mixed>> */
    public array $joins = [];

    /** @var array<int,array<string,mixed>> */
    public array $wheres = [];

    /** @var array<int,string> */
    public array $groups = [];

    /** @var array<int,array<string,mixed>> */
    public array $havings = [];

    /** @var array<int,array<string,mixed>> */
    public array $orders = [];

    public ?int $limit = null;

    public ?int $offset = null;

    /** @var array{function:string,columns:array<int,string>}|null */
    public ?array $aggregate = null;

    /** @var array{where:list<mixed>,having:list<mixed>} */
    public array $bindings = [
        'where' => [],
        'having' => [],
    ];

    public const OPERATORS = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        'in', 'not in', 'is', 'is not',
    ];

    public function __construct(
        public Connection $connection,
        public Grammar $grammar = new Grammar(),
    ) {
    }

    public function from(string $table): static
    {
        $this->from = $table;

        return $this;
    }

    /**
     * @param array<int,string>|string $columns
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function distinct(bool $value = true): static
    {
        $this->distinct = $value;

        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        // where('col', $value) shorthand for where('col', '=', $value).
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        $operator = strtolower((string) $operator);
        $this->assertValidOperator($operator);

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => $boolean,
        ];
        $this->addBinding($value, 'where');

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * @param array<int,mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $values = array_values($values);

        $this->wheres[] = [
            'type' => $not ? 'NotIn' : 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * @param array<int,mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => $not ? 'NotNull' : 'Null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->assertValidOperator(strtolower($operator));

        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->orders[] = compact('column', 'direction');

        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function groupBy(string ...$groups): static
    {
        $this->groups = array_merge($this->groups, $groups);

        return $this;
    }

    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $operator = strtolower($operator);
        $this->assertValidOperator($operator);

        $this->havings[] = compact('column', 'operator', 'boolean');
        $this->addBinding($value, 'having');

        return $this;
    }

    public function limit(int $value): static
    {
        $this->limit = max(0, $value);

        return $this;
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * @param array<int,string>|string $columns
     * @return array<int,array<string,mixed>>
     */
    public function get(array|string $columns = ['*']): array
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        return $this->connection->select(
            $this->grammar->compileSelect($this),
            $this->getBindings()
        );
    }

    /**
     * @param array<int,string>|string $columns
     * @return array<string,mixed>|null
     */
    public function first(array|string $columns = ['*']): ?array
    {
        return $this->limit(1)->get($columns)[0] ?? null;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->runAggregate('count', [$column]);
    }

    public function max(string $column): mixed
    {
        return $this->runAggregate('max', [$column]);
    }

    public function min(string $column): mixed
    {
        return $this->runAggregate('min', [$column]);
    }

    public function sum(string $column): mixed
    {
        return $this->runAggregate('sum', [$column]) ?? 0;
    }

    public function avg(string $column): mixed
    {
        return $this->runAggregate('avg', [$column]);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function value(string $column): mixed
    {
        return $this->first([$column])[$column] ?? null;
    }

    /**
     * @return array<int,mixed>
     */
    public function pluck(string $column): array
    {
        return array_map(
            static fn (array $row): mixed => $row[$column] ?? null,
            $this->get([$column])
        );
    }

    /**
     * @param array<string,mixed>|list<array<string,mixed>> $values
     */
    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->flattenInsertBindings($values)
        );
    }

    /**
     * @param array<string,mixed> $values
     */
    public function insertGetId(array $values): string
    {
        $this->insert($values);

        return $this->connection->lastInsertId();
    }

    /**
     * @param array<string,mixed> $values
     */
    public function update(array $values): int
    {
        return $this->connection->update(
            $this->grammar->compileUpdate($this, $values),
            array_merge(array_values($values), $this->getBindings())
        );
    }

    public function delete(): int
    {
        return $this->connection->delete(
            $this->grammar->compileDelete($this),
            $this->getBindings()
        );
    }

    public function addBinding(mixed $value, string $type = 'where'): static
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type][] = $value;

        return $this;
    }

    /**
     * @return array<int,mixed>
     */
    public function getBindings(): array
    {
        return array_merge($this->bindings['where'], $this->bindings['having']);
    }

    /**
     * @param array<int,string> $columns
     */
    private function runAggregate(string $function, array $columns): mixed
    {
        $previous = $this->aggregate;
        $this->aggregate = ['function' => $function, 'columns' => $columns];

        $result = $this->connection->select(
            $this->grammar->compileSelect($this),
            $this->getBindings()
        );

        $this->aggregate = $previous;

        return $result[0]['aggregate'] ?? null;
    }

    /**
     * @param array<string,mixed>|list<array<string,mixed>> $values
     * @return array<int,mixed>
     */
    private function flattenInsertBindings(array $values): array
    {
        if (!is_array(reset($values))) {
            return array_values($values);
        }

        $bindings = [];

        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    private function assertValidOperator(string $operator): void
    {
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported operator: {$operator}.");
        }
    }
}
