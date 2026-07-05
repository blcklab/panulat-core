<?php

declare(strict_types=1);

namespace Panulat\Container;

interface ContainerInterface
{
    public function get(string $id): mixed;

    public function has(string $id): bool;
}
