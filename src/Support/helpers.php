<?php

declare(strict_types=1);

use Panulat\Support\AsciiBanner;

if (! function_exists('panulat_env')) {
    function panulat_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false ? $default : $value;
    }
}

if (! function_exists('panulat_bool')) {
    function panulat_bool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

if (! function_exists('panulat_int')) {
    function panulat_int(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int) $value;
    }
}

if (! function_exists('panulat_string')) {
    function panulat_string(mixed $value, string $default = ''): string
    {
        if ($value === null || $value === false) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }
}

if (! function_exists('panulat_path')) {
    function panulat_path(string $path = '', ?string $basePath = null): string
    {
        $basePath ??= getcwd() ?: '';

        return rtrim($basePath, DIRECTORY_SEPARATOR) . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    }
}

if (! function_exists('panulat_ascii_lines')) {
    /** @return list<string> */
    function panulat_ascii_lines(string $text, int $spacing = 1): array
    {
        return AsciiBanner::render($text, $spacing);
    }
}

if (! function_exists('panulat_ascii')) {
    function panulat_ascii(string $text, int $spacing = 1): string
    {
        return AsciiBanner::text($text, $spacing);
    }
}
