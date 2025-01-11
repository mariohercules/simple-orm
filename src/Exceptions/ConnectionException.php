<?php

namespace SimpleORM\Exceptions;

class ConnectionException extends \Exception
{
    public static function connectionFailed(string $message): self
    {
        return new self("Database connection failed: {$message}");
    }
}
