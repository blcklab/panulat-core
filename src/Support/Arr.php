<?php

declare(strict_types=1);

namespace Panulat\Support;

final class Arr
{
    /** @param array<string, mixed> $array */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
