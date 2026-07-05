<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(array $errors, string $detail = 'The given data was invalid.')
    {
        parent::__construct(422, 'Validation Failed', $detail, $errors, 'https://panulat.dev/problems/validation-error');
    }
}
