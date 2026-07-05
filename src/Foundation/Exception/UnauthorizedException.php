<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class UnauthorizedException extends HttpException
{
    public function __construct(string $detail = 'Authentication is required.', ?\Throwable $previous = null)
    {
        parent::__construct(401, 'Unauthorized', $detail, type: 'https://panulat.dev/problems/unauthorized', previous: $previous);
    }
}
