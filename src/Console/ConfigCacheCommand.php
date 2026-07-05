<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Config\ConfigLoader;
use Panulat\Config\Env;
use Panulat\Foundation\ProductionSafety;

final readonly class ConfigCacheCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'config:cache';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.config_cache', 'Compile configuration into bootstrap/cache/config.php.');
    }

    public function execute(array $arguments): int
    {
        (new Env())->load($this->basePath . '/.env');

        $loader = new ConfigLoader($this->basePath);
        $config = $loader->load(false);
        (new ProductionSafety($this->basePath, $config, requireCaches: false))->assertSafe();
        $loader->cache($config);

        echo CommandTranslator::text($this->basePath, 'console.config_cached', 'Configuration cached.') . PHP_EOL;

        return 0;
    }
}
