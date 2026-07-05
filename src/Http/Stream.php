<?php

declare(strict_types=1);

namespace Panulat\Http;

final readonly class Stream
{
    public function __construct(private string $contents = '')
    {
    }

    public static function fromString(string $contents): self
    {
        return new self($contents);
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    public function isEmpty(): bool
    {
        return $this->contents === '';
    }

    public function __toString(): string
    {
        return $this->contents;
    }
}
