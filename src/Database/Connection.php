<?php

declare(strict_types=1);

namespace Panulat\Database;

use Panulat\Log\LoggerInterface;
use Panulat\Log\LogLevel;
use PDO;
use PDOException;
use PDOStatement;

final readonly class Connection
{
    public function __construct(
        private PDO $pdo,
        private ?LoggerInterface $logger = null,
        private bool $logQueries = false,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @param array<int|string, mixed> $options */
    public static function make(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
        ?LoggerInterface $logger = null,
        bool $logQueries = false,
    ): self {
        return new self(new PDO($dsn, $username, $password, $options), $logger, $logQueries);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driverName(): string
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return is_string($driver) ? $driver : '';
    }

    public function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '*') {
            return '*';
        }

        if (str_ends_with($identifier, '.*')) {
            return $this->quoteIdentifier(substr($identifier, 0, -2)) . '.*';
        }

        if (preg_match('/^(.+)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $identifier, $matches) === 1) {
            return $this->quoteIdentifier($matches[1]) . ' AS ' . $this->quoteIdentifier($matches[2]);
        }

        if (preg_match('/^([^\s]+)\s+([A-Za-z_][A-Za-z0-9_]*)$/', $identifier, $matches) === 1) {
            return $this->quoteIdentifier($matches[1]) . ' AS ' . $this->quoteIdentifier($matches[2]);
        }

        if (str_contains($identifier, '.')) {
            return implode('.', array_map($this->quoteIdentifierPart(...), explode('.', $identifier)));
        }

        return $this->quoteIdentifierPart($identifier);
    }

    private function quoteIdentifierPart(string $identifier): string
    {
        $identifier = trim($identifier);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        if ($this->driverName() === 'mysql') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /** @param array<string|int, mixed> $bindings */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);

        try {
            $statement = $this->pdo->prepare($sql);

            foreach ($bindings as $key => $value) {
                $statement->bindValue(
                    is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':'),
                    $value,
                    $this->bindingType($value),
                );
            }

            $statement->execute();
            $this->logQuery($sql, $bindings, $start);

            return $statement;
        } catch (PDOException $exception) {
            $this->logQuery($sql, $bindings, $start, $exception);

            throw DatabaseException::fromThrowable($exception, $sql);
        }
    }

    /**
     * @param array<string|int, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->statement($sql, $bindings)->fetchAll();

        return $rows;
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    private function bindingType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /** @param array<string|int, mixed> $bindings */
    private function logQuery(string $sql, array $bindings, float $start, ?\Throwable $exception = null): void
    {
        if (! $this->logQueries || $this->logger === null) {
            return;
        }

        $context = [
            'sql' => $sql,
            'binding_count' => count($bindings),
            'duration_ms' => round((microtime(true) - $start) * 1000, 3),
        ];

        if ($exception !== null) {
            $context['exception'] = $exception;
            $this->logger->error('database.query_failed', $context);

            return;
        }

        $this->logger->log(LogLevel::Debug, 'database.query', $context);
    }
}
