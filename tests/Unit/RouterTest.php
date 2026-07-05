<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Routing\MethodNotAllowedException;
use Panulat\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDynamicRouteMatch(): void
    {
        $router = new Router();
        $router->group('/v1', function (Router $router): void {
            $router->get('/users/{id}', static fn (): array => ['ok' => true]);
        });

        $match = $router->match('GET', '/v1/users/123');

        self::assertSame('123', $match->parameters['id']);
    }
    public function testMethodNotAllowedIncludesAllowHeader(): void
    {
        $router = new Router();
        $router->get('/v1/users', static fn (): array => ['ok' => true]);

        try {
            $router->match('POST', '/v1/users');
            self::fail('Expected method not allowed exception.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(405, $exception->status());
            self::assertSame('GET', $exception->headers()['Allow'] ?? null);
        }
    }

}
