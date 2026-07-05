<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Container\ContainerInterface;
use Panulat\Http\Request;
use Panulat\Http\Response;

final class Pipeline
{
    /** @var list<MiddlewareInterface|callable> */
    private array $middleware = [];

    /** @param list<MiddlewareInterface|class-string|string|object|callable> $middleware */
    public function __construct(
        array $middleware,
        private readonly RequestHandlerInterface $destination,
        ?ContainerInterface $container = null,
        ?MiddlewareRegistry $registry = null,
    ) {
        foreach ($middleware as $item) {
            $items = $registry?->resolve($item) ?? [$item];

            foreach ($items as $resolvedItem) {
                $this->middleware[] = $this->resolve($resolvedItem, $container);
            }
        }
    }

    public function handle(Request $request): Response
    {
        return (new PipelineHandler($this->middleware, $this->destination))->handle($request);
    }

    private function resolve(mixed $middleware, ?ContainerInterface $container): MiddlewareInterface|callable
    {
        if (is_string($middleware) && class_exists($middleware) && $container !== null) {
            return $container->get($middleware);
        }

        if ($middleware instanceof MiddlewareInterface || is_callable($middleware)) {
            return $middleware;
        }

        throw new \InvalidArgumentException('Middleware must be a class name, MiddlewareInterface, or callable.');
    }
}

final class PipelineHandler implements RequestHandlerInterface
{
    /** @param list<MiddlewareInterface|callable> $middleware */
    public function __construct(
        private readonly array $middleware,
        private readonly RequestHandlerInterface $destination,
        private int $index = 0,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (! isset($this->middleware[$this->index])) {
            return $this->destination->handle($request);
        }

        $middleware = $this->middleware[$this->index];
        $next = new self($this->middleware, $this->destination, $this->index + 1);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $next);
        }

        return $middleware($request, $next);
    }
}
