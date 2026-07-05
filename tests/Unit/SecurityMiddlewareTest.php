<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Auth\ApiKeyMiddleware;
use Panulat\Auth\TokenUser;
use Panulat\Cors\CorsMiddleware;
use Panulat\Foundation\Exception\UnauthorizedException;
use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Middleware\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;

final class SecurityMiddlewareTest extends TestCase
{
    public function testCorsRejectsWildcardOriginWithCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CORS credentials cannot be enabled');

        new CorsMiddleware(credentials: true);
    }

    public function testCorsReflectsOnlyExplicitAllowedCredentialedOrigin(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: ['https://app.example.test'],
            credentials: true,
        );

        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'api.example.test',
            'REQUEST_URI' => '/v1/health',
            'HTTP_ORIGIN' => 'https://app.example.test',
        ]);

        $response = $middleware->process($request, new StaticResponseHandler());

        self::assertSame('https://app.example.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }


    public function testCorsPreflightReturnsNoContent(): void
    {
        $middleware = new CorsMiddleware();
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_HOST' => 'api.example.test',
            'REQUEST_URI' => '/v1/health',
            'HTTP_ORIGIN' => 'https://app.example.test',
        ]);

        $response = $middleware->process($request, new StaticResponseHandler());

        self::assertSame(204, $response->getStatusCode());
        self::assertTrue($response->getBody()->isEmpty());
    }

    public function testApiKeyMiddlewareUsesConfiguredKey(): void
    {
        $middleware = new ApiKeyMiddleware(['secret-key' => 'user-1']);
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'api.example.test',
            'REQUEST_URI' => '/v1/me',
            'HTTP_X_API_KEY' => 'secret-key',
        ]);

        $handler = new class implements RequestHandlerInterface {
            public ?TokenUser $user = null;

            public function handle(Request $request): Response
            {
                $user = $request->getAttribute('user');
                TestCase::assertInstanceOf(TokenUser::class, $user);
                $this->user = $user;

                return Response::json(['data' => ['ok' => true]]);
            }
        };

        $middleware->process($request, $handler);

        self::assertSame('user-1', $handler->user?->getAuthIdentifier());
    }

    public function testApiKeyMiddlewareRejectsInvalidKey(): void
    {
        $middleware = new ApiKeyMiddleware(['secret-key' => 'user-1']);
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'api.example.test',
            'REQUEST_URI' => '/v1/me',
            'HTTP_X_API_KEY' => 'wrong',
        ]);

        $this->expectException(UnauthorizedException::class);

        $middleware->process($request, new StaticResponseHandler());
    }
}

final class StaticResponseHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return Response::json(['data' => ['ok' => true]]);
    }
}
