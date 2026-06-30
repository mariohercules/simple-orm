<?php

declare(strict_types=1);

namespace SimpleORM\Exceptions;

use RuntimeException;
use Throwable;

class QueryException extends RuntimeException
{
    /**
     * @param array<int,mixed> $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        Throwable $previous
    ) {
        parent::__construct(
            sprintf('%s (SQL: %s)', $previous->getMessage(), $sql),
            0,
            $previous
        );
    }
}
