<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Log\LoggerInterface;

final readonly class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $start = microtime(true);
        $requestId = $request->getHeaderLine('X-Request-Id') ?: bin2hex(random_bytes(8));
        $request = $request->withAttribute('request_id', $requestId);

        try {
            $response = $handler->handle($request);
            $this->logger->info('request.completed', [
                'request_id' => $requestId,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 3),
            ]);

            return $response->withHeader('X-Request-Id', $requestId);
        } catch (\Throwable $throwable) {
            $this->logger->error('request.failed', [
                'request_id' => $requestId,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 3),
                'exception' => $throwable,
            ]);

            throw $throwable;
        }
    }
}
