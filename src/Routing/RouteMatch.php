<?php

declare(strict_types=1);

namespace Panulat\Routing;

final readonly class RouteMatch
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public Route $route,
        public array $parameters = [],
    ) {
    }
}
