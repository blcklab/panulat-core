<?php

declare(strict_types=1);

namespace Panulat\RateLimit;

use Panulat\Foundation\Exception\TooManyRequestsException;
use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Middleware\MiddlewareInterface;
use Panulat\Middleware\RequestHandlerInterface;

final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiter $limiter,
        private int $maxAttempts = 60,
        private int $windowSeconds = 60,
        private string $name = 'default',
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $key = $this->name . ':' . $request->getClientIp() . ':' . $request->getUri()->getPath();
        $result = $this->limiter->hit($key, $this->maxAttempts, $this->windowSeconds);

        if (! $result->allowed) {
            throw new TooManyRequestsException($result->retryAfter);
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining)
            ->withHeader('X-RateLimit-Reset', (string) $result->resetAt);
    }
}
