<?php

declare(strict_types=1);

namespace Panulat\Auth;

final readonly class TokenUser implements Authenticatable
{
    /** @param array<string, mixed> $claims */
    public function __construct(private string|int $id, private array $claims = [])
    {
    }

    public function getAuthIdentifier(): string|int
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function claims(): array
    {
        return $this->claims;
    }
}
