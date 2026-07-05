<?php

declare(strict_types=1);

namespace Panulat\Log;

final readonly class FileLogger implements LoggerInterface
{
    public function __construct(
        private string $path,
        private int $maxBytes = 5242880,
        private bool $lock = false,
    ) {
        if ($this->isStreamPath()) {
            return;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->rotateIfNeeded();

        $line = json_encode([
            'timestamp' => gmdate('c'),
            'level' => $level->value,
            'message' => $message,
            'context' => $this->normalizeContext($context),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $flags = FILE_APPEND | ($this->lock ? LOCK_EX : 0);
        file_put_contents($this->path, $line, $flags);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    private function rotateIfNeeded(): void
    {
        if ($this->isStreamPath()) {
            return;
        }

        if (is_file($this->path) && filesize($this->path) !== false && filesize($this->path) >= $this->maxBytes) {
            rename($this->path, $this->path . '.' . gmdate('YmdHis'));
        }
    }

    private function isStreamPath(): bool
    {
        return str_starts_with($this->path, 'php://');
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];
            }
        }

        return $context;
    }
}
