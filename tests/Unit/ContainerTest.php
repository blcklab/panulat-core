<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Container\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testAutowiring(): void
    {
        $container = new Container();
        $service = $container->get(ExampleService::class);

        self::assertInstanceOf(ExampleDependency::class, $service->dependency);
    }

    public function testBindClearsResolvedInstance(): void
    {
        $container = new Container();

        $container->instance('service', new ExampleOriginalService());
        $container->bind('service', ExampleReplacementService::class);

        self::assertInstanceOf(ExampleReplacementService::class, $container->get('service'));
    }

    public function testSingletonRebindClearsPreviousSingletonInstance(): void
    {
        $container = new Container();

        $container->singleton('service', ExampleOriginalService::class);
        $first = $container->get('service');

        $container->singleton('service', ExampleReplacementService::class);
        $second = $container->get('service');

        self::assertInstanceOf(ExampleOriginalService::class, $first);
        self::assertInstanceOf(ExampleReplacementService::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testFactoryRebindClearsPreviousSingletonInstance(): void
    {
        $container = new Container();

        $container->singleton('service', ExampleOriginalService::class);
        $container->get('service');

        $container->factory('service', static fn (): ExampleReplacementService => new ExampleReplacementService());

        self::assertInstanceOf(ExampleReplacementService::class, $container->get('service'));
    }
}

final readonly class ExampleDependency
{
}

final readonly class ExampleService
{
    public function __construct(public ExampleDependency $dependency)
    {
    }
}

final readonly class ExampleOriginalService
{
}

final readonly class ExampleReplacementService
{
}
