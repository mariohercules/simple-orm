<?php

declare(strict_types=1);

namespace SimpleORM\Model\Concerns;

use Closure;
use SimpleORM\Model\Scope;

/**
 * Registry of global scopes, keyed per concrete model class.
 */
trait HasGlobalScopes
{
    /** @var array<class-string,array<string,Scope|Closure>> */
    protected static array $globalScopes = [];

    public static function addGlobalScope(string $name, Scope|Closure $scope): void
    {
        static::$globalScopes[static::class][$name] = $scope;
    }

    public static function hasGlobalScope(string $name): bool
    {
        return isset(static::$globalScopes[static::class][$name]);
    }

    /**
     * @return array<string,Scope|Closure>
     */
    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }
}
