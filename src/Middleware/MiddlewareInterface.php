<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Http\Request;
use Panulat\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response;
}
