<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Http\Request;
use Panulat\Http\Response;

final readonly class CallableRequestHandler implements RequestHandlerInterface
{
    /** @param callable(Request): Response $handler */
    public function __construct(private mixed $handler)
    {
    }

    public function handle(Request $request): Response
    {
        return ($this->handler)($request);
    }
}
