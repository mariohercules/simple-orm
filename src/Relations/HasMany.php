<?php

declare(strict_types=1);

namespace SimpleORM\Relations;

use SimpleORM\Model\Model;

class HasMany extends HasOneOrMany
{
    /**
     * @return array<int,Model>
     */
    public function getResults(): array
    {
        $key = $this->getParentKey();

        if ($key === null) {
            return [];
        }

        return $this->query->where($this->foreignKey, '=', $key)->get();
    }

    /**
     * @param array<int,Model> $models
     * @return array<int,Model>
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
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
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key] ?? []);
        }

        return $models;
    }
}
