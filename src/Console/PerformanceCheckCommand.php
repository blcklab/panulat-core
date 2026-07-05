<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class PerformanceCheckCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'performance:check';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.performance_check', 'Check whether production performance caches and PHP settings are ready.');
    }

    public function execute(array $arguments): int
    {
        $status = 0;

        foreach ([
            'config' => '/bootstrap/cache/config.php',
            'routes' => '/bootstrap/cache/routes.php',
            'container' => '/bootstrap/cache/container.php',
        ] as $name => $path) {
            $fullPath = $this->basePath . $path;
            if (is_file($fullPath)) {
                echo '[ok] ' . $name . ' cache exists.' . PHP_EOL;
                continue;
            }

            echo '[missing] ' . $name . ' cache is missing. Run php bin/console optimize.' . PHP_EOL;
            $status = 1;
        }

        if (function_exists('opcache_get_status')) {
            $opcache = opcache_get_status(false);
            if (is_array($opcache) && ($opcache['opcache_enabled'] ?? false)) {
                echo '[ok] OPcache is enabled.' . PHP_EOL;
            } else {
                echo '[warn] OPcache is not enabled for this SAPI.' . PHP_EOL;
            }
        } else {
            echo '[warn] OPcache extension is unavailable.' . PHP_EOL;
        }

        if (is_file($this->basePath . '/vendor/autoload.php')) {
            echo '[ok] Composer autoloader exists.' . PHP_EOL;
        } else {
            echo '[missing] vendor/autoload.php is missing. Run composer install.' . PHP_EOL;
            $status = 1;
        }

        echo $status === 0
            ? 'Panulat performance checks passed.' . PHP_EOL
            : 'Panulat performance checks found issues.' . PHP_EOL;

        return $status;
    }
}
