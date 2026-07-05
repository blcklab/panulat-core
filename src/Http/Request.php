<?php

declare(strict_types=1);

namespace Panulat\Http;

use Panulat\Foundation\Exception\BadRequestException;
use Panulat\Foundation\Exception\PayloadTooLargeException;

final class Request
{
    private const DEFAULT_MAX_BODY_BYTES = 1048576;

    /** @var array<string, mixed>|null */
    private ?array $decodedJson = null;

    private bool $jsonDecoded = false;

    private bool $parsedBodyDecoded = false;

    private ?\Closure $bodyFactory = null;

    /**
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $serverParams
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $parsedBody
     * @param array<string, UploadedFile|array<int|string, mixed>> $uploadedFiles
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private string $method,
        private Uri $uri,
        private array $headers = [],
        private ?Stream $body = null,
        private array $serverParams = [],
        private array $queryParams = [],
        private array $parsedBody = [],
        private array $uploadedFiles = [],
        private array $attributes = [],
        private int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES,
        ?callable $bodyFactory = null,
    ) {
        $this->method = strtoupper($method);
        $this->headers = self::normalizeHeaders($headers);
        $this->queryParams = $queryParams !== [] ? $queryParams : $uri->getQueryParams();
        $this->bodyFactory = $bodyFactory instanceof \Closure ? $bodyFactory : ($bodyFactory !== null ? \Closure::fromCallable($bodyFactory) : null);
        $this->maxBodyBytes = max(0, $this->maxBodyBytes);
        $this->parsedBodyDecoded = $this->parsedBody !== [];
    }

    public static function fromGlobals(int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES): self
    {
        return self::fromServer(
            server: $_SERVER,
            body: null,
            maxBodyBytes: $maxBodyBytes,
            bodyFactory: static fn (): string => file_get_contents('php://input') ?: '',
            parsedBody: $_POST,
            files: $_FILES,
        );
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $files
     */
    public static function fromServer(
        array $server,
        ?string $body = '',
        int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES,
        ?callable $bodyFactory = null,
        array $parsedBody = [],
        array $files = [],
    ): self {
        if ($body !== null && strlen($body) > $maxBodyBytes) {
            throw new PayloadTooLargeException($maxBodyBytes);
        }

        return new self(
            method: (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            uri: Uri::fromServer($server),
            headers: self::headersFromServer($server),
            body: $body === null ? null : Stream::fromString($body),
            serverParams: $server,
            parsedBody: self::normalizeParsedBody($parsedBody),
            uploadedFiles: UploadedFileNormalizer::normalize($files),
            maxBodyBytes: $maxBodyBytes,
            bodyFactory: $bodyFactory,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): Uri
    {
        return $this->uri;
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
        $request = clone $this;
        $request->headers[strtolower($name)] = is_array($value) ? array_map('strval', $value) : [(string) $value];

        return $request;
    }

    public function getBody(): Stream
    {
        return $this->body ??= Stream::fromString($this->readBody());
    }

    public function body(): string
    {
        return $this->getBody()->getContents();
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->jsonDecoded) {
            return $this->decodedJson ?? [];
        }

        $this->jsonDecoded = true;

        if ($this->getBody()->isEmpty()) {
            return $this->decodedJson = [];
        }

        try {
            $decoded = json_decode($this->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestException('Malformed JSON request body.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new BadRequestException('JSON request body must decode to an object or array.');
        }

        return $this->decodedJson = $decoded;
    }

    /** @return array<string, mixed> */
    public function getParsedBody(): array
    {
        $this->parseUrlEncodedBodyIfNeeded();

        return $this->parsedBody;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        $parsedBody = $this->getParsedBody();

        return $parsedBody[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = null;
        $contentType = strtolower($this->getHeaderLine('content-type'));

        if ($this->jsonDecoded || str_contains($contentType, 'application/json')) {
            $json = $this->json();
        }

        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }

        $parsedBody = $this->getParsedBody();

        if (array_key_exists($key, $parsedBody)) {
            return $parsedBody[$key];
        }

        return $this->query($key, $default);
    }

    /** @return array<string, UploadedFile|array<int|string, mixed>> */
    public function files(): array
    {
        return $this->uploadedFiles;
    }

    public function file(string $key): ?UploadedFile
    {
        $value = $this->fileValue($key);

        if ($value instanceof UploadedFile) {
            return $value;
        }

        return UploadedFileNormalizer::first($value);
    }

    /** @return list<UploadedFile> */
    public function fileList(string $key): array
    {
        $value = $this->fileValue($key);

        return UploadedFileNormalizer::flatten($value);
    }

    public function hasFile(string $key): bool
    {
        foreach ($this->fileList($key) as $file) {
            if ($file->isValid()) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return $this->withAttributes($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function withAttributes(array $attributes): self
    {
        $request = clone $this;
        $request->attributes = $attributes;

        return $request;
    }

    public function getClientIp(): string
    {
        $remoteAddress = $this->serverParams['REMOTE_ADDR'] ?? '127.0.0.1';

        return is_string($remoteAddress) && trim($remoteAddress) !== ''
            ? trim($remoteAddress)
            : '127.0.0.1';
    }

    private function parseUrlEncodedBodyIfNeeded(): void
    {
        if ($this->parsedBodyDecoded) {
            return;
        }

        $this->parsedBodyDecoded = true;

        if ($this->parsedBody !== []) {
            return;
        }

        if (! str_contains(strtolower($this->getHeaderLine('content-type')), 'application/x-www-form-urlencoded')) {
            return;
        }

        $parsed = [];
        parse_str($this->body(), $parsed);
        $this->parsedBody = self::normalizeParsedBody($parsed);
    }

    private function readBody(): string
    {
        $contentLength = (int) $this->getHeaderLine('Content-Length');

        if ($this->maxBodyBytes > 0 && $contentLength > $this->maxBodyBytes) {
            throw new PayloadTooLargeException($this->maxBodyBytes);
        }

        $contents = $this->bodyFactory !== null ? (string) ($this->bodyFactory)() : '';

        if ($this->maxBodyBytes > 0 && strlen($contents) > $this->maxBodyBytes) {
            throw new PayloadTooLargeException($this->maxBodyBytes);
        }

        return $contents;
    }

    private function fileValue(string $key): mixed
    {
        $value = $this->uploadedFiles;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, list<string>>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr((string) $key, 5)));
                $headers[$name] = [(string) $value];
            }
        }

        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            if (isset($server[$key])) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = [(string) $server[$key]];
            }
        }

        return $headers;
    }


    /**
     * @param array<mixed> $body
     * @return array<string, mixed>
     */
    private static function normalizeParsedBody(array $body): array
    {
        $normalized = [];

        foreach ($body as $key => $value) {
            $normalized[(string) $key] = self::normalizeParsedValue($value);
        }

        return $normalized;
    }

    private static function normalizeParsedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $child) {
            $normalized[is_int($key) ? $key : (string) $key] = self::normalizeParsedValue($child);
        }

        return $normalized;
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
