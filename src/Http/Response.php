<?php

declare(strict_types=1);

namespace Panulat\Http;

final class Response
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        private int $status = 200,
        private array $headers = [],
        private Stream $body = new Stream(),
    ) {
        $this->headers = self::normalizeHeaders($headers);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            status: $status,
            headers: ['content-type' => ['application/json; charset=utf-8']],
            body: Stream::fromString(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self(
            status: $status,
            headers: ['content-type' => ['text/plain; charset=utf-8']],
            body: Stream::fromString($body),
        );
    }

    public static function noContent(int $status = 204): self
    {
        return new self(status: $status);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /** @return array<string, list<string>> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return list<string> */
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /** @param string|list<string> $value */
    public function withHeader(string $name, string|array $value): self
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = is_array($value) ? array_map('strval', $value) : [(string) $value];

        return new self($this->status, $headers, $this->body);
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->headers, $this->body);
    }

    public function getBody(): Stream
    {
        return $this->body;
    }

    /**
     * @param array<string, list<string>|string> $headers
     * @return array<string, list<string>>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower((string) $name)] = is_array($values)
                ? array_map('strval', $values)
                : [(string) $values];
        }

        return $normalized;
    }
}
