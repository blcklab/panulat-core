<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class MakeMigrationCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.make_migration', 'Create a native PHP migration file in database/migrations.');
    }

    public function execute(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null) {
            fwrite(STDERR, 'Migration name is required. Example: php bin/console make:migration create_posts_table' . PHP_EOL);
            return 1;
        }

        $migrationName = $this->normalizeName((string) $name);

        if ($migrationName === '') {
            fwrite(STDERR, 'Migration name may only contain letters, numbers, dashes, and underscores.' . PHP_EOL);
            return 1;
        }

        $directory = $this->basePath . '/database/migrations';

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = $directory . '/' . date('Y_m_d_His') . '_' . $migrationName . '.php';

        if (is_file($file)) {
            fwrite(STDERR, 'Migration already exists: ' . $file . PHP_EOL);
            return 1;
        }

        file_put_contents($file, $this->template($migrationName) . PHP_EOL);
        echo CommandTranslator::text($this->basePath, 'console.created', 'Created :path', ['path' => $file]) . PHP_EOL;

        return 0;
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim(str_replace(['\\', '/', '-'], '_', $name), '_'));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?: '';
        $name = preg_replace('/_+/', '_', $name) ?: '';

        return trim($name, '_');
    }

    private function template(string $migrationName): string
    {
        $table = $this->tableFromMigrationName($migrationName);

        if ($table !== null) {
            return <<<PHP
<?php

declare(strict_types=1);

use Panulat\Database\Connection;

return static function (Connection \$connection): void {
    \$connection->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS {$table} (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
};
PHP;
        }

        return <<<'PHP'
<?php

declare(strict_types=1);

use Panulat\Database\Connection;

return static function (Connection $connection): void {
    $connection->statement(<<<'SQL'
-- Write your migration SQL here.
SQL);
};
PHP;
    }

    private function tableFromMigrationName(string $migrationName): ?string
    {
        if (preg_match('/^create_(.+)_table$/', $migrationName, $matches) === 1) {
            return $this->pluralizeTableName($matches[1]);
        }

        if (preg_match('/^create_(.+)$/', $migrationName, $matches) === 1) {
            return $this->pluralizeTableName($matches[1]);
        }

        return null;
    }

    private function pluralizeTableName(string $name): string
    {
        if (str_ends_with($name, 's')) {
            return $name;
        }

        if (str_ends_with($name, 'y') && ! preg_match('/[aeiou]y$/', $name)) {
            return substr($name, 0, -1) . 'ies';
        }

        return $name . 's';
    }
}
