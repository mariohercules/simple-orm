<?php

declare(strict_types=1);

namespace SimpleORM\Relations;

use SimpleORM\Model\Builder;
use SimpleORM\Model\Model;

class BelongsTo extends Relation
{
    public function __construct(
        Builder $query,
        Model $child,
        protected string $foreignKey,
        protected string $ownerKey
    ) {
        parent::__construct($query, $child);
    }

    public function getResults(): ?Model
    {
        $foreign = $this->parent->getAttribute($this->foreignKey);

        if ($foreign === null) {
            return null;
        }

        return $this->query->where($this->ownerKey, '=', $foreign)->first();
    }

    /**
     * @param array<int,Model> $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->ownerKey, $this->getKeys($models, $this->foreignKey));
    }

    /**
     * @param array<int,Model> $models
     * @return array<int,Model>
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * @param array<int,Model> $models
     * @param array<int,Model> $results
     * @return array<int,Model>
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            $foreign = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$foreign] ?? null);
        }

        return $models;
    }
}
