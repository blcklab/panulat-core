<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Cache\ArrayCache;
use Panulat\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase
{
    public function testFixedWindowLimit(): void
    {
        $limiter = new RateLimiter(new ArrayCache());

        self::assertTrue($limiter->hit('ip', 1, 60)->allowed);
        self::assertFalse($limiter->hit('ip', 1, 60)->allowed);
    }
}
