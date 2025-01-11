<?php

namespace SimpleORM\Contracts;

interface ConnectionInterface
{
    public function connect(): \PDO;
    public function disconnect(): void;
    public function getConnection(): ?\PDO;
}