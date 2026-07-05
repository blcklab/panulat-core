<?php

declare(strict_types=1);

namespace Panulat\Routing;

use Panulat\Foundation\Exception\NotFoundException;

final class Router
{
    /** @var array<string, array<string, Route>> */
    private array $staticRoutes = [];

    /** @var array<string, list<Route>> */
    private array $dynamicRoutes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var list<array{prefix: string, middleware: list<class-string|string|object>}> */
    private array $groupStack = [];

    /** @var array<string, array{regex: string, routes: array<string, array{route: Route, params: list<string>}>}> */
    private array $compiledDynamic = [];

    /**
     * @param list<string> $methods
     * @param callable|array{0: class-string|string, 1: string}|class-string|string $handler
     * @param list<class-string|string|object> $middleware
     */
    public function add(array $methods, string $path, mixed $handler, array $middleware = []): Route
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'], '/');
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        /** @var list<class-string|string|object> $mergedMiddleware */
        $mergedMiddleware = [...$groupMiddleware, ...$middleware];
        $route = new Route($methods, Route::normalizePath($prefix . '/' . trim($path, '/')), $handler, $mergedMiddleware);
        $this->storeRoute($route);

        return $route;
    }

    /** @param list<class-string|string|object> $middleware */
    public function get(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function post(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function put(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function patch(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function delete(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function options(string $path, mixed $handler, array $middleware = []): Route
    {
        return $this->add(['OPTIONS'], $path, $handler, $middleware);
    }

    /** @param list<class-string|string|object> $middleware */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $this->groupStack[] = ['prefix' => Route::normalizePath($prefix), 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groupStack);
    }

    public function name(Route $route, string $name): Route
    {
        $named = $route->withName($name);
        $this->removeRoute($route);
        $this->storeRoute($named);
        $this->namedRoutes[$name] = $named;

        return $named;
    }

    /** @param array<string, string|int> $parameters */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;

        if (! $route instanceof Route) {
            throw new \InvalidArgumentException(sprintf('Route [%s] is not defined.', $name));
        }

        $path = $route->path();

        foreach ($parameters as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(?::[^}]+)?\}/', rawurlencode((string) $value), $path) ?? $path;
        }

        return $path;
    }

    public function match(string $method, string $path): RouteMatch
    {
        $method = strtoupper($method);
        $path = Route::normalizePath($path);

        if (isset($this->staticRoutes[$method][$path])) {
            return new RouteMatch($this->staticRoutes[$method][$path]);
        }

        $compiled = $this->compiledDynamic[$method] ?? $this->compileDynamicForMethod($method);

        if ($compiled !== null && preg_match($compiled['regex'], $path, $matches) === 1) {
            foreach ($compiled['routes'] as $key => $data) {
                if (($matches[$key] ?? '') !== '') {
                    $parameters = [];
                    foreach ($data['params'] as $param) {
                        $capture = $key . '_' . $param;
                        if (isset($matches[$capture])) {
                            $parameters[$param] = rawurldecode((string) $matches[$capture]);
                        }
                    }

                    return new RouteMatch($data['route'], $parameters);
                }
            }
        }

        $allowed = $this->allowedMethodsForPath($path);
        if ($allowed !== []) {
            throw new MethodNotAllowedException($allowed);
        }

        throw new NotFoundException();
    }

    public function compile(): void
    {
        foreach (array_keys($this->dynamicRoutes) as $method) {
            $this->compileDynamicForMethod($method);
        }
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        $routes = [];
        $seen = [];

        foreach ([$this->staticRoutes, $this->dynamicRoutes] as $bucket) {
            foreach ($bucket as $methodRoutes) {
                foreach ($methodRoutes as $route) {
                    $key = implode('|', $route->methods()) . ' ' . $route->path();
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $routes[] = [
                        'methods' => $route->methods(),
                        'path' => $route->path(),
                        'handler' => $route->handler(),
                        'middleware' => $route->middleware(),
                        'name' => $route->name(),
                    ];
                }
            }
        }

        return ['routes' => $routes];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $router = new self();

        foreach (($data['routes'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $route = new Route(
                methods: array_values(array_map('strval', (array) ($row['methods'] ?? ['GET']))),
                path: (string) ($row['path'] ?? '/'),
                handler: $row['handler'] ?? static fn (): array => ['ok' => true],
                middleware: self::middlewareList((array) ($row['middleware'] ?? [])),
                name: isset($row['name']) ? (string) $row['name'] : null,
            );

            $router->storeRoute($route);
            if ($route->name() !== null) {
                $router->namedRoutes[$route->name()] = $route;
            }
        }

        $router->compile();

        return $router;
    }

    /**
     * @param array<mixed> $middleware
     * @return list<class-string|string|object>
     */
    private static function middlewareList(array $middleware): array
    {
        return array_values(array_filter(
            $middleware,
            static fn (mixed $item): bool => is_string($item) || is_object($item),
        ));
    }

    private function storeRoute(Route $route): void
    {
        foreach ($route->methods() as $method) {
            if ($this->isDynamic($route->path())) {
                $this->dynamicRoutes[$method][] = $route;
            } else {
                $this->staticRoutes[$method][$route->path()] = $route;
            }
        }

        if ($route->name() !== null) {
            $this->namedRoutes[$route->name()] = $route;
        }

        $this->compiledDynamic = [];
    }

    private function removeRoute(Route $route): void
    {
        foreach ($route->methods() as $method) {
            unset($this->staticRoutes[$method][$route->path()]);
            if (isset($this->dynamicRoutes[$method])) {
                $this->dynamicRoutes[$method] = array_values(array_filter(
                    $this->dynamicRoutes[$method],
                    static fn (Route $candidate): bool => $candidate !== $route,
                ));
            }
        }

        $this->compiledDynamic = [];
    }

    /** @return array{regex: string, routes: array<string, array{route: Route, params: list<string>}>}|null */
    private function compileDynamicForMethod(string $method): ?array
    {
        $routes = $this->dynamicRoutes[$method] ?? [];

        if ($routes === []) {
            return null;
        }

        $branches = [];
        $map = [];

        foreach ($routes as $index => $route) {
            $key = 'r' . $index;
            [$pattern, $params] = $this->compilePath($route->path(), $key);
            $branches[] = '(?P<' . $key . '>' . $pattern . ')';
            $map[$key] = ['route' => $route, 'params' => $params];
        }

        $compiled = ['regex' => '~^(?:' . implode('|', $branches) . ')$~', 'routes' => $map];
        $this->compiledDynamic[$method] = $compiled;

        return $compiled;
    }

    /** @return array{0: string, 1: list<string>} */
    private function compilePath(string $path, string $routeKey): array
    {
        $segments = explode('/', trim($path, '/'));
        $compiledSegments = [];
        $params = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $match) === 1) {
                $name = (string) $match[1];
                $params[] = $name;
                $compiledSegments[] = '(?P<' . $routeKey . '_' . $name . '>' . ($match[2] ?? '[^/]+') . ')';
                continue;
            }

            $compiledSegments[] = preg_quote($segment, '~');
        }

        return ['/' . implode('/', $compiledSegments), $params];
    }

    private function isDynamic(string $path): bool
    {
        return str_contains($path, '{');
    }

    /** @return list<string> */
    private function allowedMethodsForPath(string $path): array
    {
        $allowed = [];
        $methods = array_unique(array_merge(array_keys($this->staticRoutes), array_keys($this->dynamicRoutes)));

        foreach ($methods as $method) {
            if (isset($this->staticRoutes[$method][$path])) {
                $allowed[] = $method;
                continue;
            }

            foreach ($this->dynamicRoutes[$method] ?? [] as $route) {
                [$pattern] = $this->compilePath($route->path(), 'probe');
                if (preg_match('~^' . $pattern . '$~', $path) === 1) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return array_values(array_unique($allowed));
    }
}
