<?php

declare(strict_types=1);

namespace Panulat\RateLimit;

use Panulat\Cache\CacheInterface;
use Panulat\Cache\CounterCacheInterface;

final readonly class RateLimiter
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function hit(string $key, int $maxAttempts, int $windowSeconds): RateLimitResult
    {
        $now = time();
        $window = (int) floor($now / $windowSeconds);
        $cacheKey = 'rate:' . $key . ':' . $window;
        $resetAt = ($window + 1) * $windowSeconds;
        $ttl = $resetAt - $now + 1;

        $attempts = $this->cache instanceof CounterCacheInterface
            ? $this->cache->increment($cacheKey, 1, $ttl)
            : $this->fallbackHit($cacheKey, $ttl);

        $remaining = max(0, $maxAttempts - $attempts);
        $retryAfter = max(1, $resetAt - $now);

        return new RateLimitResult($attempts <= $maxAttempts, $remaining, $retryAfter, $resetAt);
    }

    private function fallbackHit(string $cacheKey, int $ttl): int
    {
        $attempts = (int) $this->cache->get($cacheKey, 0) + 1;
        $this->cache->set($cacheKey, $attempts, $ttl);

        return $attempts;
    }
}
