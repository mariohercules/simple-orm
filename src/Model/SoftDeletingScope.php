<?php

declare(strict_types=1);

namespace SimpleORM\Model;

/**
 * Global scope that hides soft-deleted rows by constraining every query with
 * `deleted_at is null`. Removed by Builder::withTrashed()/onlyTrashed().
 */
class SoftDeletingScope implements Scope
{
    public const NAME = 'soft_deletes';

    public function apply(Builder $builder, Model $model): void
    {
        $column = method_exists($model, 'getDeletedAtColumn')
            ? $model->getDeletedAtColumn()
            : 'deleted_at';

        $builder->getQuery()->whereNull($column);
    }
}
