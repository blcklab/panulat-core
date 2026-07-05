<?php

declare(strict_types=1);

namespace Panulat\Foundation;

use Panulat\Foundation\Exception\HttpException;
use Panulat\Http\Response;
use Panulat\Support\Translator;

final readonly class ErrorHandler
{
    /** @var array<string, string> */
    private const DEFAULT_DETAILS = [
        'bad-request' => 'The request could not be understood.',
        'not-found' => 'The requested resource was not found.',
        'unauthorized' => 'Authentication is required.',
        'forbidden' => 'You are not allowed to perform this action.',
        'too-many-requests' => 'Too many requests.',
        'payload-too-large' => 'The request body is too large.',
        'validation-error' => 'The given data was invalid.',
    ];

    public function __construct(
        private bool $debug = false,
        private ?Translator $translator = null,
    ) {
    }

    public function render(\Throwable $throwable): Response
    {
        if ($throwable instanceof HttpException) {
            $problemKey = $this->problemKey($throwable->type());
            $payload = [
                'type' => $throwable->type(),
                'title' => $this->title($problemKey, $throwable->title()),
                'status' => $throwable->status(),
                'detail' => $this->detail($problemKey, $throwable->detail()),
            ];

            if ($throwable->errors() !== []) {
                $payload['errors'] = $throwable->errors();
            }

            if ($this->debug) {
                $payload['debug'] = $this->debugPayload($throwable);
            }

            $response = Response::json($payload, $throwable->status());

            foreach ($throwable->headers() as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        }

        $payload = [
            'type' => 'https://panulat.dev/problems/internal-server-error',
            'title' => $this->translator?->get('errors.internal-server-error.title', default: 'Internal Server Error') ?? 'Internal Server Error',
            'status' => 500,
            'detail' => $this->translator?->get('errors.internal-server-error.detail', default: 'An unexpected error occurred.') ?? 'An unexpected error occurred.',
        ];

        if ($this->debug) {
            $payload['debug'] = $this->debugPayload($throwable);
        }

        return Response::json($payload, 500);
    }

    private function title(string $problemKey, string $default): string
    {
        return $this->translator?->get('errors.' . $problemKey . '.title', default: $default) ?? $default;
    }

    private function detail(string $problemKey, string $default): string
    {
        if ((self::DEFAULT_DETAILS[$problemKey] ?? null) !== $default) {
            return $default;
        }

        return $this->translator?->get('errors.' . $problemKey . '.detail', default: $default) ?? $default;
    }

    private function problemKey(string $type): string
    {
        if ($type === 'about:blank') {
            return 'internal-server-error';
        }

        $key = basename(parse_url($type, PHP_URL_PATH) ?: 'internal-server-error');

        return $key !== '' ? $key : 'internal-server-error';
    }

    /** @return array<string, mixed> */
    private function debugPayload(\Throwable $throwable): array
    {
        return [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => array_slice($throwable->getTrace(), 0, 10),
        ];
    }
}
