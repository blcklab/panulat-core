<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Database\Connection;
use Panulat\Database\Seeder;
use Panulat\Foundation\Application;

final readonly class DbSeedCommand implements CommandInterface
{
    public function __construct(
        private string $basePath,
        private ?Connection $connection = null,
    ) {
    }

    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.db_seed', 'Run database seeders from database/seeders.');
    }

    public function execute(array $arguments): int
    {
        $connection = $this->connection ?? $this->resolveConnection();

        if (! $connection instanceof Connection) {
            fwrite(STDERR, CommandTranslator::text($this->basePath, 'console.no_database', 'No database connection is configured.') . PHP_EOL);
            return 1;
        }

        $this->requireSeederFiles();
        $class = $this->seederClass($arguments[0] ?? 'DatabaseSeeder');

        if (! class_exists($class)) {
            fwrite(STDERR, 'Seeder class was not found: ' . $class . PHP_EOL);
            return 1;
        }

        $seeder = new $class($connection);

        if (! $seeder instanceof Seeder) {
            fwrite(STDERR, 'Seeder must extend ' . Seeder::class . ': ' . $class . PHP_EOL);
            return 1;
        }

        $seeder->run();
        echo CommandTranslator::text($this->basePath, 'console.seeded', 'Seeded :class', ['class' => $class]) . PHP_EOL;

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

    private function requireSeederFiles(): void
    {
        $directory = $this->basePath . '/database/seeders';

        if (! is_dir($directory)) {
            return;
        }

        foreach ($this->phpFiles($directory) as $file) {
            require_once $file;
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                array_push($files, ...$this->phpFiles($path));
                continue;
            }

            if (str_ends_with($path, '.php')) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private function seederClass(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        if (str_contains($name, '\\')) {
            return $name;
        }

        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?: 'DatabaseSeeder';

        if (! str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        return 'Database\\Seeders\\' . $name;
    }
}
