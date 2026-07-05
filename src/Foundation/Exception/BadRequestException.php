<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class BadRequestException extends HttpException
{
    public function __construct(string $detail = 'The request could not be understood.', ?\Throwable $previous = null)
    {
        parent::__construct(400, 'Bad Request', $detail, type: 'https://panulat.dev/problems/bad-request', previous: $previous);
    }
}
