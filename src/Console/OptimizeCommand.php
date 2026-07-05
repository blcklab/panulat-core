<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class OptimizeCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'optimize';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.optimize', 'Cache config, routes, and container metadata for production.');
    }

    public function execute(array $arguments): int
    {
        foreach ([
            new ConfigCacheCommand($this->basePath),
            new RouteCacheCommand($this->basePath),
            new ContainerCacheCommand($this->basePath),
        ] as $command) {
            $status = $command->execute([]);
            if ($status !== 0) {
                return $status;
            }
        }

        echo CommandTranslator::text($this->basePath, 'console.optimized', 'Panulat optimized.') . PHP_EOL;

        return 0;
    }
}
