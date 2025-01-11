<?php

namespace SimpleORM\Traits;

trait AttributeHandler
{
    protected array $attributes = [];
    
    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }
    
    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }
    
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}