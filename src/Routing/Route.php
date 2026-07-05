<?php

declare(strict_types=1);

namespace Panulat\Routing;

final class Route
{
    /**
     * @param list<string> $methods
     * @param callable|array{0: class-string|string, 1: string}|class-string|string $handler
     * @param list<class-string|string|object> $middleware
     */
    public function __construct(
        private array $methods,
        private string $path,
        private mixed $handler,
        private array $middleware = [],
        private ?string $name = null,
    ) {
        $this->methods = array_values(array_unique(array_map(static fn (string $method): string => strtoupper($method), $methods)));
        $this->path = self::normalizePath($path);
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    /** @return list<class-string|string|object> */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        return new self($this->methods, $this->path, $this->handler, $this->middleware, $name);
    }

    /** @param list<class-string|string|object> $middleware */
    public function withMiddleware(array $middleware): self
    {
        return new self($this->methods, $this->path, $this->handler, array_merge($this->middleware, $middleware), $this->name);
    }

    public static function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
