<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Console\DbSeedCommand;
use Panulat\Database\Connection;
use PHPUnit\Framework\TestCase;

final class SeederCommandTest extends TestCase
{
    public function testDbSeedRunsSpecificSeeder(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $basePath = sys_get_temp_dir() . '/panulat_db_seed_' . bin2hex(random_bytes(6));
        mkdir($basePath . '/database/seeders', 0775, true);
        $class = 'Temp' . bin2hex(random_bytes(4)) . 'Seeder';

        file_put_contents($basePath . '/database/seeders/' . $class . '.php', <<<PHP
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Panulat\Database\Seeder;

final class {$class} extends Seeder
{
    public function run(): void
    {
        \$this->statement('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        \$this->table('users')->insert(['email' => 'seeded@example.test']);
    }
}
PHP);

        try {
            $connection = Connection::make('sqlite::memory:');
            $command = new DbSeedCommand($basePath, $connection);

            ob_start();
            $exitCode = $command->execute([$class]);
            ob_end_clean();

            self::assertSame(0, $exitCode);
            $row = $connection->table('users')
                ->select(['email'])
                ->where('email', '=', 'seeded@example.test')
                ->first();

            self::assertSame('seeded@example.test', $row['email'] ?? null);
        } finally {
            $this->removeDirectory($basePath);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
