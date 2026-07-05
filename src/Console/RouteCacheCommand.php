<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Foundation\Application;
use Panulat\Routing\RouteCache;

final readonly class RouteCacheCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.route_cache', 'Compile routes into bootstrap/cache/routes.php.');
    }

    public function execute(array $arguments): int
    {
        $app = Application::boot($this->basePath, ignoreCaches: true);
        (new RouteCache($this->basePath . '/bootstrap/cache/routes.php'))->write($app->router());

        echo CommandTranslator::text($this->basePath, 'console.routes_cached', 'Routes cached.') . PHP_EOL;

        return 0;
    }
}
