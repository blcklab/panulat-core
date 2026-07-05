<?php

declare(strict_types=1);

namespace Panulat\Authorization;

final class Gate
{
    /** @var array<string, callable> */
    private array $abilities = [];

    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    public function can(mixed $user, string $ability, mixed $resource = null): bool
    {
        $callback = $this->abilities[$ability] ?? null;

        if ($callback === null) {
            return false;
        }

        return (bool) $callback($user, $resource);
    }
}
