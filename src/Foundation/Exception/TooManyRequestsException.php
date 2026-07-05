<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class TooManyRequestsException extends HttpException
{
    public function __construct(int $retryAfter, string $detail = 'Too many requests.')
    {
        parent::__construct(
            status: 429,
            title: 'Too Many Requests',
            detail: $detail,
            type: 'https://panulat.dev/problems/too-many-requests',
            headers: ['Retry-After' => (string) $retryAfter],
        );
    }
}
