<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Config\ConfigLoader;
use Panulat\Config\Env;
use Panulat\Support\Translator;

final class CommandTranslator
{
    /** @var array<string, Translator> */
    private static array $translators = [];

    /** @param array<string, scalar|null> $replace */
    public static function text(string $basePath, string $key, string $default, array $replace = []): string
    {
        return self::translator($basePath)->get($key, $replace, $default);
    }

    private static function translator(string $basePath): Translator
    {
        if (! isset(self::$translators[$basePath])) {
            (new Env())->load($basePath . '/.env');
            self::$translators[$basePath] = Translator::fromConfig($basePath, (new ConfigLoader($basePath))->load(false));
        }

        return self::$translators[$basePath];
    }
}
