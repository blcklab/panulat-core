<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Support\AsciiBanner;
use PHPUnit\Framework\TestCase;

use function panulat_ascii;
use function panulat_ascii_lines;
use function panulat_bool;
use function panulat_int;
use function panulat_path;
use function panulat_string;

final class SupportHelperTest extends TestCase
{
    public function testAsciiBannerRendersLettersNumbersAndSymbols(): void
    {
        $lines = AsciiBanner::render('API-1!');

        self::assertCount(5, $lines);
        self::assertSame($lines, panulat_ascii_lines('API-1!'));
        self::assertStringContainsString('█', panulat_ascii('API-1!'));
    }

    public function testScalarHelpersNormalizeValues(): void
    {
        self::assertTrue(panulat_bool('true'));
        self::assertFalse(panulat_bool('', false));
        self::assertSame(42, panulat_int('42'));
        self::assertSame(7, panulat_int('bad', 7));
        self::assertSame('Panulat', panulat_string(' Panulat '));
        self::assertSame('fallback', panulat_string('', 'fallback'));
    }

    public function testPathHelperJoinsPaths(): void
    {
        self::assertStringEndsWith('root' . DIRECTORY_SEPARATOR . 'storage', panulat_path('storage', '/tmp/root'));
    }
}
