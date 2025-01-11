<?php

namespace SimpleORM\Contracts;

interface QueryBuilderInterface
{
    public function select(array $columns = ['*']): self;
    public function where(string $column, string $operator, $value): self;
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function limit(int $limit): self;
    public function get(): array;
}