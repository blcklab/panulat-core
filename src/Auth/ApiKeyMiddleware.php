<?php

declare(strict_types=1);

namespace Panulat\Auth;

use Panulat\Foundation\Exception\UnauthorizedException;
use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Middleware\MiddlewareInterface;
use Panulat\Middleware\RequestHandlerInterface;

final readonly class ApiKeyMiddleware implements MiddlewareInterface
{
    /** @param array<string, string|int> $keys */
    public function __construct(
        private array $keys,
        private string $headerName = 'X-API-Key',
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $key = $request->getHeaderLine($this->headerName);
        $user = $this->resolveUserForKey($key);

        if ($user === null) {
            throw new UnauthorizedException('Invalid API key.');
        }

        return $handler->handle($request->withAttribute('user', new TokenUser($user)));
    }

    private function resolveUserForKey(string $key): string|int|null
    {
        if ($key === '') {
            return null;
        }

        foreach ($this->keys as $configuredKey => $user) {
            if (hash_equals((string) $configuredKey, $key)) {
                return $user;
            }
        }

        return null;
    }
}
