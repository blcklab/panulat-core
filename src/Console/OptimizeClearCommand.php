<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class OptimizeClearCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'optimize:clear';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.optimize_clear', 'Remove compiled config, route, and container cache files.');
    }

    public function execute(array $arguments): int
    {
        foreach ([
            $this->basePath . '/bootstrap/cache/config.php',
            $this->basePath . '/bootstrap/cache/routes.php',
            $this->basePath . '/bootstrap/cache/container.php',
        ] as $file) {
            if (is_file($file)) {
                unlink($file);
                echo CommandTranslator::text($this->basePath, 'console.removed', 'Removed :path', ['path' => $file]) . PHP_EOL;
            }
        }

        echo CommandTranslator::text($this->basePath, 'console.optimization_cleared', 'Optimization cache cleared.') . PHP_EOL;

        return 0;
    }
}
