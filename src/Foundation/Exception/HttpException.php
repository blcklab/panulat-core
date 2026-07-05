<?php

declare(strict_types=1);

namespace Panulat\Foundation\Exception;

class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $errors
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $title,
        string $detail = '',
        private readonly array $errors = [],
        private readonly string $type = 'about:blank',
        private readonly array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? $detail : $title, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function detail(): string
    {
        return $this->getMessage();
    }

    /** @return array<string, mixed> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
