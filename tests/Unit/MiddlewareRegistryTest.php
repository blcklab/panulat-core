<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Cache\ArrayCache;
use Panulat\Cache\CacheInterface;
use Panulat\Container\Container;
use Panulat\Middleware\MiddlewareRegistry;
use Panulat\RateLimit\RateLimiter;
use Panulat\RateLimit\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;

final class MiddlewareRegistryTest extends TestCase
{
    public function testAliasesAndGroupsAreExpanded(): void
    {
        $registry = new MiddlewareRegistry(new Container());
        $registry->alias('auth', ExampleMiddleware::class);
        $registry->group('protected', ['auth']);

        self::assertSame([ExampleMiddleware::class], $registry->resolve('protected'));
    }

    public function testNamedThrottleReturnsMiddleware(): void
    {
        $container = new Container();
        $container->instance(CacheInterface::class, new ArrayCache());
        $container->singleton(RateLimiter::class, static function (Container $container): RateLimiter {
            $cache = $container->get(CacheInterface::class);

            if (! $cache instanceof CacheInterface) {
                throw new \RuntimeException('Cache service is not available.');
            }

            return new RateLimiter($cache);
        });

        $registry = new MiddlewareRegistry($container);
        $registry->throttle('login', 5, 60);

        self::assertContainsOnlyInstancesOf(RateLimitMiddleware::class, $registry->resolve('throttle:login'));
    }
}

final class ExampleMiddleware
{
}
