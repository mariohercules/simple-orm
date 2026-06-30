<?php

declare(strict_types=1);

namespace SimpleORM\Model;

/**
 * Opt-in soft deletes. `use SoftDeletes` on a model whose table has a nullable
 * `deleted_at` column. delete() then stamps deleted_at instead of removing the
 * row, queries hide trashed rows by default, and restore()/forceDelete() are
 * available. Override the column with a `DELETED_AT` constant if needed.
 */
trait SoftDeletes
{
    public bool $forceDeleting = false;

    /**
     * Booted automatically (boot{TraitBasename}) the first time the model is used.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(SoftDeletingScope::NAME, new SoftDeletingScope());
    }

    public function getDeletedAtColumn(): string
    {
        return defined(static::class . '::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    protected function performDeleteOnModel(): void
    {
        if ($this->forceDeleting) {
            $this->newQueryBuilder()
                ->where($this->getKeyName(), '=', $this->getKey())
                ->delete();
            $this->exists = false;

            return;
        }

        $this->runSoftDelete();
    }

    protected function runSoftDelete(): void
    {
        $column = $this->getDeletedAtColumn();
        $time = $this->freshTimestampString();

        $this->setAttribute($column, $time);

        if ($this->usesTimestamps()) {
            $this->setAttribute(static::UPDATED_AT, $time);
        }

        $this->newQueryBuilder()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->update($this->getDirty());

        $this->syncOriginal();
    }

    public function restore(): bool
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);
        $saved = $this->save();

        if ($saved) {
            $this->fireModelEvent('restored', false);
        }

        return $saved;
    }

    public function trashed(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    public function forceDelete(): bool
    {
        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }
}
