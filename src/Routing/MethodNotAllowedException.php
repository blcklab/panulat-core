<?php

declare(strict_types=1);

namespace Panulat\Routing;

use Panulat\Foundation\Exception\HttpException;

final class MethodNotAllowedException extends HttpException
{
    /** @param list<string> $allowedMethods */
    public function __construct(array $allowedMethods)
    {
        parent::__construct(
            status: 405,
            title: 'Method Not Allowed',
            detail: 'The HTTP method is not allowed for this route.',
            type: 'https://panulat.dev/problems/method-not-allowed',
            headers: ['Allow' => implode(', ', $allowedMethods)],
        );
    }
}
