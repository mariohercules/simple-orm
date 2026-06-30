<?php

declare(strict_types=1);

namespace SimpleORM\Model\Concerns;

use SimpleORM\Relations\BelongsTo;
use SimpleORM\Relations\HasMany;
use SimpleORM\Relations\HasOne;
use SimpleORM\Support\Str;

trait HasRelationships
{
    /**
     * @param class-string<\SimpleORM\Model\Model> $related
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related();
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @param class-string<\SimpleORM\Model\Model> $related
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @param class-string<\SimpleORM\Model\Model> $related
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey ??= Str::snake(class_basename($related)) . '_' . $instance->getKeyName();
        $ownerKey ??= $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey);
    }
}
