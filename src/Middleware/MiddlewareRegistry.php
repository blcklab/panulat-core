<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Container\ContainerInterface;
use Panulat\RateLimit\RateLimiter;
use Panulat\RateLimit\RateLimitMiddleware;

final class MiddlewareRegistry
{
    /** @var array<string, mixed> */
    private array $aliases = [];

    /** @var array<string, list<mixed>> */
    private array $groups = [];

    /** @var array<string, array{max_attempts: int, window_seconds: int}> */
    private array $throttles = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function alias(string $name, mixed $middleware): void
    {
        $this->aliases[$this->normalizeName($name)] = $middleware;
    }

    /** @param array<string, mixed> $aliases */
    public function aliases(array $aliases): void
    {
        foreach ($aliases as $name => $middleware) {
            $this->alias((string) $name, $middleware);
        }
    }

    /** @param list<mixed> $middleware */
    public function group(string $name, array $middleware): void
    {
        $this->groups[$this->normalizeName($name)] = $middleware;
    }

    /** @param array<string, list<mixed>> $groups */
    public function groups(array $groups): void
    {
        foreach ($groups as $name => $middleware) {
            $this->group((string) $name, $middleware);
        }
    }

    public function throttle(string $name, int $maxAttempts, int $windowSeconds = 60): void
    {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('Rate limit max attempts must be at least 1.');
        }

        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit window seconds must be at least 1.');
        }

        $this->throttles[$this->normalizeName($name)] = [
            'max_attempts' => $maxAttempts,
            'window_seconds' => $windowSeconds,
        ];
    }

    /** @param array<string, array<string, mixed>> $profiles */
    public function throttles(array $profiles): void
    {
        foreach ($profiles as $name => $profile) {
            $this->throttle(
                (string) $name,
                $this->positiveInt($profile['max_attempts'] ?? null, 60),
                $this->positiveInt($profile['window_seconds'] ?? null, 60),
            );
        }
    }

    /** @return list<mixed> */
    public function resolve(mixed $middleware): array
    {
        return $this->resolveOne($middleware, []);
    }

    /**
     * @param array<string, true> $seenGroups
     * @return list<mixed>
     */
    private function resolveOne(mixed $middleware, array $seenGroups): array
    {
        if (! is_string($middleware)) {
            return [$middleware];
        }

        $name = $this->normalizeName($middleware);

        if ($name === 'throttle') {
            return [$this->makeThrottle('default')];
        }

        if (str_starts_with($name, 'throttle:')) {
            $profile = $this->normalizeName(substr($name, strlen('throttle:')));

            if ($profile === '') {
                throw new \InvalidArgumentException('Throttle middleware requires a profile name, for example throttle:api.');
            }

            return [$this->makeThrottle($profile)];
        }

        if (isset($this->groups[$name])) {
            if (isset($seenGroups[$name])) {
                throw new \InvalidArgumentException(sprintf('Middleware group [%s] contains a circular reference.', $name));
            }

            $seenGroups[$name] = true;
            $resolved = [];

            foreach ($this->groups[$name] as $groupMiddleware) {
                foreach ($this->resolveOne($groupMiddleware, $seenGroups) as $item) {
                    $resolved[] = $item;
                }
            }

            return $resolved;
        }

        if (array_key_exists($name, $this->aliases)) {
            return $this->resolveOne($this->aliases[$name], $seenGroups);
        }

        return [$middleware];
    }

    private function makeThrottle(string $profile): RateLimitMiddleware
    {
        $settings = $this->throttles[$profile] ?? null;

        if ($settings === null) {
            throw new \InvalidArgumentException(sprintf('Rate limit profile [%s] is not defined.', $profile));
        }

        $limiter = $this->container->get(RateLimiter::class);

        if (! $limiter instanceof RateLimiter) {
            throw new \RuntimeException('Rate limiter service is not available.');
        }

        return new RateLimitMiddleware(
            limiter: $limiter,
            maxAttempts: $settings['max_attempts'],
            windowSeconds: $settings['window_seconds'],
            name: $profile,
        );
    }

    private function normalizeName(string $name): string
    {
        return trim($name);
    }

    private function positiveInt(mixed $value, int $default): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return max(1, (int) $value);
    }
}
