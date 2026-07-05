<?php

declare(strict_types=1);

namespace Panulat\Config;

final class Env
{
    /** @return array<string, string> */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $_SERVER[$key] ?? $value;
            putenv($key . '=' . $value);
        }

        return $values;
    }
}
