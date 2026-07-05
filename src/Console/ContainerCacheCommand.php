<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Container\Container;
use Panulat\Container\MetadataCache;
use Panulat\Foundation\Application;

final readonly class ContainerCacheCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'container:cache';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.container_cache', 'Warm and cache container reflection metadata.');
    }

    public function execute(array $arguments): int
    {
        $app = Application::boot($this->basePath, ignoreCaches: true);
        $container = $app->container();

        foreach ($app->globalMiddleware() as $middleware) {
            if (is_string($middleware) && class_exists($middleware)) {
                $container->warm($middleware);
            }
        }

        foreach (($app->router()->export()['routes'] ?? []) as $route) {
            if (! is_array($route)) {
                continue;
            }

            $this->warmHandler($container, $route['handler'] ?? null);

            foreach ((array) ($route['middleware'] ?? []) as $middleware) {
                if (is_string($middleware) && class_exists($middleware)) {
                    $container->warm($middleware);
                }
            }
        }

        (new MetadataCache($this->basePath . '/bootstrap/cache/container.php'))->write($container);

        echo CommandTranslator::text($this->basePath, 'console.container_cached', 'Container metadata cached.') . PHP_EOL;

        return 0;
    }

    private function warmHandler(Container $container, mixed $handler): void
    {
        if (is_string($handler) && class_exists($handler)) {
            $container->warm($handler);
            return;
        }

        if (is_array($handler) && isset($handler[0]) && is_string($handler[0]) && class_exists($handler[0])) {
            $container->warm($handler[0]);
        }
    }
}
