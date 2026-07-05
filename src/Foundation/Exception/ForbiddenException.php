<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class ForbiddenException extends HttpException
{
    public function __construct(string $detail = 'You are not allowed to perform this action.')
    {
        parent::__construct(403, 'Forbidden', $detail, type: 'https://panulat.dev/problems/forbidden');
    }
}
