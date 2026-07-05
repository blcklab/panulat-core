<?php

declare(strict_types=1);

namespace Panulat\Console;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /** @param list<string> $arguments */
    public function execute(array $arguments): int;
}
