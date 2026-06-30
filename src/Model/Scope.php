<?php

declare(strict_types=1);

namespace SimpleORM\Model;

/**
 * A global scope constrains every query for a model (e.g. hiding soft-deleted
 * rows). Applied by Builder::applyScopes() just before a query executes.
 */
interface Scope
{
    public function apply(Builder $builder, Model $model): void;
}
