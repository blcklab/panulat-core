<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Log\FileLogger;
use Panulat\Log\LogLevel;
use Panulat\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    public function testNullLoggerDiscardsMessages(): void
    {
        $logger = new NullLogger();
        $logger->info('request.completed', ['status' => 200]);

        self::assertInstanceOf(NullLogger::class, $logger);
    }

    public function testFileLoggerWritesJsonLineWithoutRequiringLock(): void
    {
        $path = sys_get_temp_dir() . '/panulat_log_' . bin2hex(random_bytes(6)) . '.log';

        try {
            $logger = new FileLogger($path, lock: false);
            $logger->log(LogLevel::Info, 'test.message', ['ok' => true]);

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringContainsString('test.message', $contents);
            self::assertStringContainsString('"ok":true', $contents);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
