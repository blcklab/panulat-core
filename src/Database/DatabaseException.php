<?php

declare(strict_types=1);

namespace Panulat\Database;

final class DatabaseException extends \RuntimeException
{
    public static function fromThrowable(\Throwable $throwable, string $sql): self
    {
        return new self(
            message: 'Database query failed: ' . $throwable->getMessage(),
            code: (int) $throwable->getCode(),
            previous: $throwable,
        );
    }
}
