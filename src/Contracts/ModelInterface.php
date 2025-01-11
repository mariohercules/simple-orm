<?php

namespace SimpleORM\Contracts;

interface ModelInterface
{
    public static function find(int $id): ?self;
    public static function all(): array;
    public function save(): bool;
    public function delete(): bool;
}
