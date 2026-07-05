<?php

declare(strict_types=1);

namespace Panulat\Cache;

final class ArrayCache implements CounterCacheInterface
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->items[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return true;
    }

    public function increment(string $key, int $amount = 1, int $ttl = 0): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function has(string $key): bool
    {
        if (! isset($this->items[$key])) {
            return false;
        }

        if ($this->items[$key]['expires'] !== 0 && $this->items[$key]['expires'] < time()) {
            unset($this->items[$key]);
            return false;
        }

        return true;
    }
}
