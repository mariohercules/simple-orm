<?php

declare(strict_types=1);

namespace SimpleORM\Query;

/**
 * Compiles a QueryBuilder's state into SQL. All identifiers (tables, columns,
 * aliases) are wrapped in backticks here, which is the single place that closes
 * the identifier-injection surface — builders never concatenate raw names.
 *
 * Backtick quoting is MySQL flavoured; SQLite accepts it too for compatibility.
 */
class Grammar
{
    public function compileSelect(QueryBuilder $query): string
    {
        $parts = [
            'select ' . $this->compileColumns($query),
            'from ' . $this->wrapTable($query->from),
            $this->compileJoins($query),
            $this->compileWheres($query),
            $this->compileGroups($query),
            $this->compileHavings($query),
            $this->compileOrders($query),
            $this->compileLimit($query),
            $this->compileOffset($query),
        ];

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param array<string,mixed>|list<array<string,mixed>> $values
     */
    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if ($values === []) {
            return "insert into {$table} default values";
        }

        // Normalize a single associative row into a list of rows.
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        /** @var list<array<string,mixed>> $values */
        $columns = $this->columnize(array_keys(reset($values)));

        $placeholders = implode(', ', array_map(
            fn (array $record): string => '(' . $this->parameterize($record) . ')',
            $values
        ));

        return "insert into {$table} ({$columns}) values {$placeholders}";
    }

    /**
     * @param array<string,mixed> $values
     */
    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = implode(', ', array_map(
            fn (string $column): string => $this->wrap($column) . ' = ?',
            array_keys($values)
        ));

        return trim("update {$table} set {$columns} " . $this->compileWheres($query));
    }

    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->wrapTable($query->from);

        return trim("delete from {$table} " . $this->compileWheres($query));
    }

    private function compileColumns(QueryBuilder $query): string
    {
        if ($query->aggregate !== null) {
            $columns = $query->aggregate['columns'];
            $inner = $columns === ['*'] ? '*' : $this->columnize($columns);
            $distinct = $query->distinct && $inner !== '*' ? 'distinct ' : '';

            return $query->aggregate['function'] . '(' . $distinct . $inner . ') as `aggregate`';
        }

        return ($query->distinct ? 'distinct ' : '') . $this->columnize($query->columns);
    }

    private function compileJoins(QueryBuilder $query): string
    {
        if ($query->joins === []) {
            return '';
        }

        return implode(' ', array_map(function (array $join): string {
            return strtolower($join['type']) . ' join ' . $this->wrapTable($join['table'])
                . ' on ' . $this->wrap($join['first'])
                . ' ' . $join['operator'] . ' '
                . $this->wrap($join['second']);
        }, $query->joins));
    }

    private function compileWheres(QueryBuilder $query): string
    {
        if ($query->wheres === []) {
            return '';
        }

        $sql = [];

        foreach ($query->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : $where['boolean'] . ' ';
            $sql[] = $boolean . $this->compileWhere($where);
        }

        return 'where ' . implode(' ', $sql);
    }

    /**
     * @param array<string,mixed> $where
     */
    private function compileWhere(array $where): string
    {
        $column = $this->wrap($where['column']);

        return match ($where['type']) {
            'Basic' => $column . ' ' . $where['operator'] . ' ?',
            'In' => $where['values'] === []
                ? '0 = 1'
                : $column . ' in (' . $this->parameterize($where['values']) . ')',
            'NotIn' => $where['values'] === []
                ? '1 = 1'
                : $column . ' not in (' . $this->parameterize($where['values']) . ')',
            'Null' => $column . ' is null',
            'NotNull' => $column . ' is not null',
            default => '',
        };
    }

    private function compileGroups(QueryBuilder $query): string
    {
        if ($query->groups === []) {
            return '';
        }

        return 'group by ' . $this->columnize($query->groups);
    }

    private function compileHavings(QueryBuilder $query): string
    {
        if ($query->havings === []) {
            return '';
        }

        $sql = [];

        foreach ($query->havings as $i => $having) {
            $boolean = $i === 0 ? '' : $having['boolean'] . ' ';
            $sql[] = $boolean . $this->wrap($having['column']) . ' ' . $having['operator'] . ' ?';
        }

        return 'having ' . implode(' ', $sql);
    }

    private function compileOrders(QueryBuilder $query): string
    {
        if ($query->orders === []) {
            return '';
        }

        return 'order by ' . implode(', ', array_map(
            fn (array $order): string => $this->wrap($order['column']) . ' ' . $order['direction'],
            $query->orders
        ));
    }

    private function compileLimit(QueryBuilder $query): string
    {
        return $query->limit !== null ? 'limit ' . (int) $query->limit : '';
    }

    private function compileOffset(QueryBuilder $query): string
    {
        return $query->offset !== null ? 'offset ' . (int) $query->offset : '';
    }

    /**
     * @param array<int,string> $columns
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Wrap an identifier, handling "table.column", "col as alias" and "*".
     */
    public function wrap(string $value): string
    {
        if (preg_match('/\s+as\s+/i', $value) === 1) {
            $segments = preg_split('/\s+as\s+/i', $value, 2) ?: [$value];

            return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
        }

        if (str_contains($value, '.')) {
            return implode('.', array_map([$this, 'wrapValue'], explode('.', $value)));
        }

        return $this->wrapValue($value);
    }

    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    private function wrapValue(string $value): string
    {
        $value = trim($value);

        if ($value === '*') {
            return $value;
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * @param array<int|string,mixed> $values
     */
    private function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
