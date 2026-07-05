<?php

declare(strict_types=1);

namespace Panulat\Database;

/**
 * Simple database seeder base class.
 *
 * Seeders are explicit PHP classes. They receive the native Panulat
 * Connection and may use raw SQL, QueryBuilder, or table models.
 */
abstract class Seeder
{
    public function __construct(protected Connection $connection)
    {
    }

    abstract public function run(): void;

    /**
     * Run another seeder class with the same database connection.
     *
     * @param class-string<Seeder> $seeder
     */
    protected function call(string $seeder): void
    {
        if (! class_exists($seeder)) {
            throw new \InvalidArgumentException('Seeder class was not found: ' . $seeder);
        }

        $instance = new $seeder($this->connection);

        $instance->run();
    }

    protected function table(string $table): QueryBuilder
    {
        return $this->connection->table($table);
    }

    /** @param array<string|int, mixed> $bindings */
    protected function statement(string $sql, array $bindings = []): void
    {
        $this->connection->statement($sql, $bindings);
    }

    /**
     * @param array<string|int, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    protected function select(string $sql, array $bindings = []): array
    {
        return $this->connection->select($sql, $bindings);
    }
}
