<?php

declare(strict_types=1);

namespace SimpleORM\Exceptions;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    protected string $model = '';

    /**
     * @param int|string|array<int,int|string> $ids
     */
    public function setModel(string $model, int|string|array $ids = []): static
    {
        $this->model = $model;
        $ids = is_array($ids) ? $ids : [$ids];

        $this->message = "No query results for model [{$model}]";

        if (count($ids) > 0) {
            $this->message .= ' ' . implode(', ', $ids);
        }

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
