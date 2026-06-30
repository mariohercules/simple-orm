<?php

declare(strict_types=1);

namespace SimpleORM\Model\Concerns;

use ReflectionMethod;
use SimpleORM\Relations\Relation;

/**
 * Attribute storage, casting and dirty tracking.
 *
 * Values are stored in $attributes in their *database* form (json-cast columns
 * hold the encoded string), cast to PHP types on read, and compared against
 * $original to compute the dirty set for partial UPDATEs.
 */
trait HasAttributes
{
    /** @var array<string,mixed> */
    protected array $attributes = [];

    /** @var array<string,mixed> */
    protected array $original = [];

    /** @var array<string,mixed> */
    protected array $relations = [];

    /** @var array<string,string> */
    protected array $casts = [];

    public function getAttribute(string $key): mixed
    {
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->attributes) || $this->hasCast($key)) {
            return $this->getAttributeValue($key);
        }

        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key) && $this->isRelationMethod($key)) {
            return $this->getRelationshipValue($key);
        }

        return null;
    }

    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if ($this->hasCast($key)) {
            $value = $this->castAttributeForStorage($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?? []),
            'object' => is_string($value) ? json_decode($value) : $value,
            default => $value,
        };
    }

    protected function castAttributeForStorage(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->casts[$key]) {
            'array', 'json', 'object' => is_string($value) ? $value : json_encode($value),
            'bool', 'boolean' => (int) (bool) $value,
            default => $value,
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function getOriginal(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();

        return $key === null ? $dirty !== [] : array_key_exists($key, $dirty);
    }

    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    /**
     * @return array<string,mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    public function setRelation(string $key, mixed $value): static
    {
        $this->relations[$key] = $value;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    protected function getRelationshipValue(string $method): mixed
    {
        /** @var Relation $relation */
        $relation = $this->{$method}();
        $results = $relation->getResults();

        $this->setRelation($method, $results);

        return $results;
    }

    /**
     * A method is treated as a relationship only when it is declared on a user
     * model (outside the SimpleORM namespace), which keeps property access from
     * accidentally invoking framework methods like save()/delete().
     */
    protected function isRelationMethod(string $method): bool
    {
        $declaring = (new ReflectionMethod($this, $method))->getDeclaringClass()->getName();

        return !str_starts_with($declaring, 'SimpleORM\\');
    }
}
