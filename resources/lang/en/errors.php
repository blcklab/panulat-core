<?php

declare(strict_types=1);

return [
    'bad-request' => [
        'title' => 'Bad Request',
        'detail' => 'The request could not be understood.',
    ],
    'not-found' => [
        'title' => 'Not Found',
        'detail' => 'The requested resource was not found.',
    ],
    'unauthorized' => [
        'title' => 'Unauthorized',
        'detail' => 'Authentication is required.',
    ],
    'forbidden' => [
        'title' => 'Forbidden',
        'detail' => 'You are not allowed to perform this action.',
    ],
    'too-many-requests' => [
        'title' => 'Too Many Requests',
        'detail' => 'Too many requests.',
    ],
    'payload-too-large' => [
        'title' => 'Payload Too Large',
        'detail' => 'The request body is too large.',
    ],
    'validation-error' => [
        'title' => 'Validation Failed',
        'detail' => 'The given data was invalid.',
    ],
    'internal-server-error' => [
        'title' => 'Internal Server Error',
        'detail' => 'An unexpected error occurred.',
    ],
];
