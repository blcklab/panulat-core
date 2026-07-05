<?php

declare(strict_types=1);

namespace Panulat\Cache;

final readonly class FileCache implements CounterCacheInterface
{
    public function __construct(private string $directory)
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->readPayload($key);

        if ($payload === null) {
            return $default;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->writePayload($key, [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ]);
    }

    public function increment(string $key, int $amount = 1, int $ttl = 0): int
    {
        $path = $this->path($key);
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new \RuntimeException(sprintf('Unable to open cache lock [%s].', $lockPath));
        }

        try {
            flock($lock, LOCK_EX);

            $payload = $this->readPayloadFromPath($path, deleteExpired: true);
            $value = (int) ($payload['value'] ?? 0) + $amount;

            $this->writePayloadToPath($path, [
                'expires' => $ttl > 0 ? time() + $ttl : 0,
                'value' => $value,
            ]);

            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        return ! is_file($path) || unlink($path);
    }

    public function clear(): bool
    {
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->directory . '/*.lock') ?: [] as $file) {
            unlink($file);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->readPayload($key) !== null;
    }

    /** @return array{expires: int, value: mixed}|null */
    private function readPayload(string $key): ?array
    {
        return $this->readPayloadFromPath($this->path($key), deleteExpired: true);
    }

    /** @return array{expires: int, value: mixed}|null */
    private function readPayloadFromPath(string $path, bool $deleteExpired): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $payload = require $path;

        if (! is_array($payload) || ! array_key_exists('value', $payload)) {
            return null;
        }

        $expires = (int) ($payload['expires'] ?? 0);

        if ($expires !== 0 && $expires < time()) {
            if ($deleteExpired) {
                unlink($path);
            }

            return null;
        }

        return [
            'expires' => $expires,
            'value' => $payload['value'],
        ];
    }

    /** @param array{expires: int, value: mixed} $payload */
    private function writePayload(string $key, array $payload): bool
    {
        return $this->writePayloadToPath($this->path($key), $payload);
    }

    /** @param array{expires: int, value: mixed} $payload */
    private function writePayloadToPath(string $path, array $payload): bool
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $written = file_put_contents($temporary, '<?php return ' . var_export($payload, true) . ';' . PHP_EOL, LOCK_EX);

        if ($written === false) {
            return false;
        }

        return rename($temporary, $path);
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('xxh128', $key) . '.php';
    }
}
