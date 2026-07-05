<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Config\ConfigRepository;
use Panulat\Container\Container;
use Panulat\Foundation\ProductionSafety;
use PHPUnit\Framework\TestCase;

final class ProductionOptimizationTest extends TestCase
{
    public function testProductionRejectsDebugMode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false');

        (new ProductionSafety(sys_get_temp_dir(), new ConfigRepository([
            'app' => ['env' => 'production', 'debug' => true],
            'cors' => ['credentials' => false, 'allowed_origins' => ['https://example.com']],
        ])))->assertSafe();
    }

    public function testContainerCanWarmReflectionMetadataWithoutResolvingServices(): void
    {
        $container = new Container();
        $container->warm(WarmableService::class);

        self::assertArrayHasKey(WarmableService::class, $container->exportMetadata());
        self::assertArrayHasKey(WarmableDependency::class, $container->exportMetadata());
    }
}

final readonly class WarmableDependency
{
}

final readonly class WarmableService
{
    public function __construct(public WarmableDependency $dependency)
    {
    }
}
