<?php

declare(strict_types=1);

namespace SimpleORM\Relations;

use SimpleORM\Model\Model;

class HasOne extends HasOneOrMany
{
    public function getResults(): ?Model
    {
        $key = $this->getParentKey();

        if ($key === null) {
            return null;
        }

        return $this->query->where($this->foreignKey, '=', $key)->first();
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
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, isset($dictionary[$key]) ? $dictionary[$key][0] : null);
        }

        return $models;
    }
}
