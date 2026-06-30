<?php

declare(strict_types=1);

namespace SimpleORM\Relations;

use SimpleORM\Model\Builder;
use SimpleORM\Model\Model;

/**
 * Base for all relations. A relation wraps a query against the *related* model
 * plus a reference to the parent. Lazy access calls getResults(); eager loading
 * uses addEagerConstraints() + match() to hydrate many parents in two queries.
 *
 * @method static where(string $column, ?string $operator = null, mixed $value = null)
 * @method static orderBy(string $column, string $direction = 'asc')
 */
abstract class Relation
{
    public function __construct(
        protected Builder $query,
        protected Model $parent
    ) {
    }

    /**
     * Resolve the relation for a single parent model.
     */
    abstract public function getResults(): mixed;

    /**
     * Constrain the related query to the set of parents being eager loaded.
     *
     * @param array<int,Model> $models
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Seed each parent with the relation's empty/default value.
     *
     * @param array<int,Model> $models
     * @return array<int,Model>
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match eager-loaded results back onto their parents.
     *
     * @param array<int,Model> $models
     * @param array<int,Model> $results
     * @return array<int,Model>
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * @return array<int,Model>
     */
    public function getEager(): array
    {
        return $this->query->get();
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Collect distinct, non-null values of $key across the given models.
     *
     * @param array<int,Model> $models
     * @return array<int,mixed>
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            $value = $model->getAttribute($key);

            if ($value !== null) {
                $keys[$value] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Forward fluent query methods (where, orderBy, ...) onto the related query.
     *
     * @param array<int,mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->{$method}(...$parameters);

        return $result === $this->query ? $this : $result;
    }
}
