<?php

declare(strict_types=1);

namespace Panulat\Config;

final readonly class ConfigLoader
{
    public function __construct(private string $basePath)
    {
    }

    public function load(bool $useCache = false): ConfigRepository
    {
        $cache = $this->basePath . '/bootstrap/cache/config.php';

        if ($useCache && is_file($cache)) {
            $data = require $cache;
            return new ConfigRepository(is_array($data) ? $data : []);
        }

        $items = [];
        $configPath = $this->basePath . '/config';

        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            $items[$key] = is_array($value) ? $value : [];
        }

        return new ConfigRepository($items);
    }

    public function cache(ConfigRepository $config): void
    {
        $directory = $this->basePath . '/bootstrap/cache';

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($directory . '/config.php', '<?php return ' . var_export($config->all(), true) . ';' . PHP_EOL);
    }
}
