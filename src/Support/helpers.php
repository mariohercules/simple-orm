<?php

declare(strict_types=1);

if (!function_exists('class_basename')) {
    /**
     * Return the class "basename" of the given object / class.
     */
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * Return all traits used by a trait and its traits.
     *
     * @return array<string,string>
     */
    function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $used) {
            $traits += trait_uses_recursive($used);
        }

        return $traits;
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Return all traits used by a class, its parents and their traits.
     *
     * @return array<string,string>
     */
    function class_uses_recursive(string|object $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $name) {
            $results += trait_uses_recursive($name);
        }

        return array_unique($results);
    }
}
