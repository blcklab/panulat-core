<?php

declare(strict_types=1);

namespace Panulat\Middleware;

use Panulat\Http\Request;
use Panulat\Http\Response;

interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
