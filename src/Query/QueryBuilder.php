<?php

namespace SimpleORM\Query;

use SimpleORM\Contracts\QueryBuilderInterface;
use SimpleORM\Exceptions\QueryException;

class QueryBuilder implements QueryBuilderInterface
{
    protected \PDO $connection;
    protected string $table;
    protected array $columns = ['*'];
    protected array $where = [];
    protected array $orderBy = [];
    protected ?int $limitValue = null;
    protected array $params = [];
    protected array $joins = [];
    
    public function __construct(\PDO $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }
    
    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    public function where(string $column, string $operator, $value): self
    {
        $this->where[] = [$column, $operator, $value];
        $this->params[] = $value;
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [$column, strtoupper($direction)];
        return $this;
    }
    
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }
    
    public function get(): array
    {
        try {
            $query = $this->buildQuery();
            $stmt = $this->connection->prepare($query);
            $stmt->execute($this->params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw QueryException::queryFailed($query, $e->getMessage());
        }
    }
    
    protected function buildQuery(): string
    {
        $query = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";
        
        // Add joins
        foreach ($this->joins as $join) {
            $query .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // Add where conditions
        if (!empty($this->where)) {
            $whereClauses = array_map(function($condition) {
                return "{$condition[0]} {$condition[1]} ?";
            }, $this->where);
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        // Add order by
        if (!empty($this->orderBy)) {
            $orderClauses = array_map(function($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy);
            $query .= " ORDER BY " . implode(', ', $orderClauses);
        }
        
        // Add limit
        if ($this->limitValue !== null) {
            $query .= " LIMIT {$this->limitValue}";
        }
        
        return $query;
    }
}