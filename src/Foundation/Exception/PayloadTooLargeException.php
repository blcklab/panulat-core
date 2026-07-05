<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class PayloadTooLargeException extends HttpException
{
    public function __construct(int $maxBytes)
    {
        parent::__construct(
            status: 413,
            title: 'Payload Too Large',
            detail: 'The request body exceeds the maximum allowed size of ' . $maxBytes . ' bytes.',
            type: 'https://panulat.dev/problems/payload-too-large',
        );
    }
}
