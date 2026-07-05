<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Cache\ArrayCache;
use Panulat\Cache\CacheInterface;
use Panulat\Config\ConfigRepository;
use Panulat\Container\Container;
use Panulat\Foundation\FrameworkServiceProvider;
use Panulat\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class CacheAndEventsTest extends TestCase
{
    public function testArrayCacheAndEvents(): void
    {
        $cache = new ArrayCache();
        self::assertTrue($cache->set('key', 'value'));
        self::assertSame('value', $cache->get('key'));

        $dispatcher = new EventDispatcher();
        $called = false;
        $dispatcher->listen(ExampleEvent::class, static function () use (&$called): void {
            $called = true;
        });
        $dispatcher->dispatch(new ExampleEvent());

        self::assertTrue($called);
    }


    public function testUnsupportedCacheDriverFailsClearlyWhenNoAdapterOverridesIt(): void
    {
        $container = new Container();
        $container->instance(ConfigRepository::class, new ConfigRepository([
            'cache' => ['default' => 'redis'],
        ]));

        (new FrameworkServiceProvider(sys_get_temp_dir()))->register($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache driver [redis] is not supported by panulat-core.');

        $container->get(CacheInterface::class);
    }

    public function testAdapterCanOverrideCoreCacheBinding(): void
    {
        $container = new Container();
        $container->instance(ConfigRepository::class, new ConfigRepository([
            'cache' => ['default' => 'redis'],
        ]));

        (new FrameworkServiceProvider(sys_get_temp_dir()))->register($container);
        $container->singleton(CacheInterface::class, ArrayCache::class);

        self::assertInstanceOf(ArrayCache::class, $container->get(CacheInterface::class));
    }
}

final readonly class ExampleEvent
{
}
