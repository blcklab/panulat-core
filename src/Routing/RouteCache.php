<?php

declare(strict_types=1);

namespace Panulat\Routing;

final readonly class RouteCache
{
    public function __construct(private string $path)
    {
    }

    public function write(Router $router): void
    {
        $data = $router->export();
        $this->assertCacheable($data);
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->path, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    public function read(): Router
    {
        $data = is_file($this->path) ? require $this->path : ['routes' => []];

        return Router::fromArray(is_array($data) ? $data : ['routes' => []]);
    }

    /** @param array<string, mixed> $data */
    private function assertCacheable(array $data): void
    {
        foreach ($data['routes'] ?? [] as $route) {
            if (! is_array($route)) {
                continue;
            }

            if ($route['handler'] instanceof \Closure) {
                throw new \LogicException('Closure routes cannot be cached. Use controller classes in production.');
            }

            foreach ((array) ($route['middleware'] ?? []) as $middleware) {
                if ($middleware instanceof \Closure || is_object($middleware)) {
                    throw new \LogicException('Object or closure route middleware cannot be cached. Use middleware class names in production.');
                }
            }
        }
    }
}
