<?php

declare(strict_types=1);

namespace SimpleORM\Support;

/**
 * Minimal string helpers used for table / key / relation naming.
 * Pluralization is intentionally naive — enough for English table names.
 */
final class Str
{
    public static function snake(string $value): string
    {
        if (ctype_lower($value)) {
            return $value;
        }

        $value = preg_replace('/\s+/u', '', ucwords($value)) ?? $value;
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1_', $value) ?? $value;

        return strtolower($value);
    }

    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    public static function plural(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $lower = strtolower($value);

        if (str_ends_with($lower, 'y') && !preg_match('/[aeiou]y$/', $lower)) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $lower)) {
            return $value . 'es';
        }

        return $value . 's';
    }

    public static function singular(string $value): string
    {
        $lower = strtolower($value);

        if (str_ends_with($lower, 'ies')) {
            return substr($value, 0, -3) . 'y';
        }

        if (str_ends_with($lower, 'ses')) {
            return substr($value, 0, -2);
        }

        if (str_ends_with($lower, 's') && !str_ends_with($lower, 'ss')) {
            return substr($value, 0, -1);
        }

        return $value;
    }
}
