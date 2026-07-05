<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Database\Connection;
use Panulat\Foundation\Application;

final readonly class MigrateCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ?Connection $connection = null,
    ) {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.migrate', 'Run pending native PHP migration files.');
    }

    public function execute(array $arguments): int
    {
        $connection = $this->connection ?? $this->resolveConnection();

        if (! $connection instanceof Connection) {
            fwrite(STDERR, CommandTranslator::text($this->basePath, 'console.no_database', 'No database connection is configured.') . PHP_EOL);
            return 1;
        }

        $path = $this->basePath . '/database/migrations';
        $files = glob($path . '/*.php') ?: [];
        sort($files);

        $this->ensureMigrationRepository($connection);
        $ran = $this->ranMigrations($connection);
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);

            if (isset($ran[$name])) {
                continue;
            }

            $migration = require $file;

            if (! is_callable($migration)) {
                fwrite(STDERR, 'Migration must return a callable: ' . $name . PHP_EOL);
                return 1;
            }

            $migration($connection);
            $connection->statement(
                'INSERT INTO migrations (migration, created_at) VALUES (:migration, :created_at)',
                [
                    'migration' => $name,
                    'created_at' => date('c'),
                ],
            );

            echo CommandTranslator::text($this->basePath, 'console.migrated', 'Migrated :name', ['name' => $name]) . PHP_EOL;
            $count++;
        }

        if ($count === 0) {
            echo CommandTranslator::text($this->basePath, 'console.nothing_to_migrate', 'Nothing to migrate.') . PHP_EOL;
        }

        return 0;
    }

    private function resolveConnection(): ?Connection
    {
        $app = Application::boot($this->basePath);

        if (! $app->container()->has(Connection::class)) {
            return null;
        }

        $connection = $app->container()->get(Connection::class);

        return $connection instanceof Connection ? $connection : null;
    }

    private function ensureMigrationRepository(Connection $connection): void
    {
        $connection->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS migrations (
    migration VARCHAR(255) NOT NULL PRIMARY KEY,
    created_at VARCHAR(64) NOT NULL
)
SQL);
    }

    /** @return array<string, true> */
    private function ranMigrations(Connection $connection): array
    {
        $rows = $connection->select('SELECT migration FROM migrations');
        $ran = [];

        foreach ($rows as $row) {
            $migration = $row['migration'] ?? null;

            if (is_string($migration)) {
                $ran[$migration] = true;
            }
        }

        return $ran;
    }
}
