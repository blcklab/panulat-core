<?php

declare(strict_types=1);

namespace Panulat\Cache;

interface CounterCacheInterface extends CacheInterface
{
    /**
     * Atomically increment an integer cache value and return the new value.
     */
    public function increment(string $key, int $amount = 1, int $ttl = 0): int;
}
