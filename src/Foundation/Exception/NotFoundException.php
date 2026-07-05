<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class NotFoundException extends HttpException
{
    public function __construct(string $detail = 'The requested resource was not found.')
    {
        parent::__construct(404, 'Not Found', $detail, type: 'https://panulat.dev/problems/not-found');
    }
}
