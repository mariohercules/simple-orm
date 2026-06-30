<?php

declare(strict_types=1);

namespace SimpleORM\Model\Concerns;

trait HasTimestamps
{
    public bool $timestamps = true;

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestampString();

        if (!$this->isDirty(static::UPDATED_AT)) {
            $this->setAttribute(static::UPDATED_AT, $time);
        }

        if (!$this->exists && !$this->isDirty(static::CREATED_AT)) {
            $this->setAttribute(static::CREATED_AT, $time);
        }
    }

    public function freshTimestampString(): string
    {
        return date('Y-m-d H:i:s');
    }
}
