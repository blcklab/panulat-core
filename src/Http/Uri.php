<?php

declare(strict_types=1);

namespace Panulat\Http;

final readonly class Uri
{
    public function __construct(
        private string $scheme = 'http',
        private string $host = 'localhost',
        private ?int $port = null,
        private string $path = '/',
        private string $query = '',
        private string $fragment = '',
    ) {
    }

    /** @param array<string, mixed> $server */
    public static function fromServer(array $server): self
    {
        $https = (string) ($server['HTTPS'] ?? '');
        $scheme = $https !== '' && $https !== 'off' ? 'https' : 'http';
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');

        return self::fromString($scheme . '://' . $host . $requestUri);
    }

    public static function fromString(string $uri): self
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URI.');
        }

        return new self(
            scheme: (string) ($parts['scheme'] ?? 'http'),
            host: (string) ($parts['host'] ?? 'localhost'),
            port: isset($parts['port']) ? (int) $parts['port'] : null,
            path: self::normalizePath((string) ($parts['path'] ?? '/')),
            query: (string) ($parts['query'] ?? ''),
            fragment: (string) ($parts['fragment'] ?? ''),
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        parse_str($this->query, $parsed);
        $params = [];

        foreach ($parsed as $key => $value) {
            $params[(string) $key] = $value;
        }

        return $params;
    }

    public function withPath(string $path): self
    {
        return new self($this->scheme, $this->host, $this->port, self::normalizePath($path), $this->query, $this->fragment);
    }

    public function withQuery(string $query): self
    {
        return new self($this->scheme, $this->host, $this->port, $this->path, ltrim($query, '?'), $this->fragment);
    }

    public function __toString(): string
    {
        $authority = $this->host;

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        $uri = $this->scheme . '://' . $authority . $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $normalized = '/' . ltrim($path, '/');

        return $normalized === '//' ? '/' : (rtrim($normalized, '/') ?: '/');
    }
}
