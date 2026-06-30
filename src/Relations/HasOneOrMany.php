<?php

declare(strict_types=1);

namespace SimpleORM\Relations;

use SimpleORM\Model\Builder;
use SimpleORM\Model\Model;

abstract class HasOneOrMany extends Relation
{
    public function __construct(
        Builder $query,
        Model $parent,
        protected string $foreignKey,
        protected string $localKey
    ) {
        parent::__construct($query, $parent);
    }

    protected function getParentKey(): mixed
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * @param array<int,Model> $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
    }

    /**
     * Index eager results by their foreign key for O(1) matching.
     *
     * @param array<int,Model> $results
     * @return array<int|string,array<int,Model>>
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->foreignKey)][] = $result;
        }

        return $dictionary;
    }
}
