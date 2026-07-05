<?php

declare(strict_types=1);

namespace Panulat\Container;

final readonly class MetadataCache
{
    public function __construct(private string $path)
    {
    }

    public function write(Container $container): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->path, '<?php return ' . var_export($container->exportMetadata(), true) . ';' . PHP_EOL);
    }

    public function load(Container $container): void
    {
        if (! is_file($this->path)) {
            return;
        }

        $metadata = require $this->path;
        if (is_array($metadata)) {
            $container->loadMetadata($metadata);
        }
    }
}
