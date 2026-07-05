<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Console\MakeMigrationCommand;
use PHPUnit\Framework\TestCase;

final class MakeMigrationCommandTest extends TestCase
{
    public function testItCreatesCreateTableMigration(): void
    {
        $basePath = sys_get_temp_dir() . '/panulat_make_migration_' . bin2hex(random_bytes(6));
        mkdir($basePath . '/database/migrations', 0775, true);

        try {
            $command = new MakeMigrationCommand($basePath);

            ob_start();
            $exitCode = $command->execute(['create_post_table']);
            ob_end_clean();

            self::assertSame(0, $exitCode);

            $files = glob($basePath . '/database/migrations/*_create_post_table.php') ?: [];
            self::assertCount(1, $files);

            $contents = file_get_contents($files[0]);

            self::assertIsString($contents);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS posts', $contents);
            self::assertStringContainsString('return static function (Connection $connection): void', $contents);
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
