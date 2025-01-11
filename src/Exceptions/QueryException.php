<?php

namespace SimpleORM\Exceptions;

class QueryException extends \Exception
{
    public static function queryFailed(string $query, string $message): self
    {
        return new self("Query execution failed for '{$query}': {$message}");
    }
}
