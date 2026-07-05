<?php

declare(strict_types=1);

namespace Panulat\Cors;

use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Middleware\MiddlewareInterface;
use Panulat\Middleware\RequestHandlerInterface;

final readonly class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-API-Key'],
        private bool $credentials = false,
    ) {
        if ($this->credentials && in_array('*', $this->allowedOrigins, true)) {
            throw new \InvalidArgumentException('CORS credentials cannot be enabled with a wildcard origin.');
        }
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->withHeaders(Response::noContent(), $request);
        }

        return $this->withHeaders($handler->handle($request), $request);
    }

    private function withHeaders(Response $response, Request $request): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->resolveAllowedOrigin($origin);

        if ($allowedOrigin !== '') {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));

        if ($this->credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function resolveAllowedOrigin(string $origin): string
    {
        if ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return in_array('*', $this->allowedOrigins, true) ? '*' : '';
    }
}
