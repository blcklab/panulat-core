<?php

declare(strict_types=1);

namespace Panulat\Support;

use Panulat\Config\ConfigRepository;

final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    /** @param list<string> $supported */
    public function __construct(
        private readonly string $basePath,
        private readonly string $locale = 'en',
        private readonly string $fallback = 'en',
        private readonly array $supported = ['en', 'fil'],
    ) {
    }

    public static function fromConfig(string $basePath, ConfigRepository $config): self
    {
        $locale = (string) $config->get('locale.default', 'en');
        $fallback = (string) $config->get('locale.fallback', 'en');
        $supported = array_values(array_filter(
            (array) $config->get('locale.supported', ['en', 'fil']),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        return new self(
            basePath: $basePath,
            locale: $locale,
            fallback: $fallback,
            supported: $supported === [] ? ['en'] : $supported,
        );
    }

    public function locale(): string
    {
        return $this->normalizeLocale($this->locale);
    }

    public function fallback(): string
    {
        return $this->normalizeLocale($this->fallback);
    }

    /** @param array<string, scalar|null> $replace */
    public function get(string $key, array $replace = [], ?string $default = null): string
    {
        [$group, $item] = $this->splitKey($key);
        $value = $this->line($this->locale(), $group, $item);

        if (! is_string($value)) {
            $value = $this->line($this->fallback(), $group, $item);
        }

        if (! is_string($value)) {
            $value = $default ?? $key;
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    /** @return array{0: string, 1: string} */
    private function splitKey(string $key): array
    {
        $segments = explode('.', $key, 2);

        return [$segments[0], $segments[1] ?? ''];
    }

    private function line(string $locale, string $group, string $item): mixed
    {
        $catalog = $this->catalog($locale, $group);

        if (array_key_exists($item, $catalog)) {
            return $catalog[$item];
        }

        $value = $catalog;

        foreach (explode('.', $item) as $segment) {
            if ($segment === '') {
                return null;
            }

            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function catalog(string $locale, string $group): array
    {
        $key = $locale . ':' . $group;

        if (array_key_exists($key, $this->loaded)) {
            return $this->loaded[$key];
        }

        $path = $this->basePath . '/resources/lang/' . $locale . '/' . $group . '.php';

        if (! is_file($path)) {
            return $this->loaded[$key] = [];
        }

        $lines = require $path;

        return $this->loaded[$key] = is_array($lines) ? $lines : [];
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        if (in_array($locale, $this->supported, true)) {
            return $locale;
        }

        return in_array($this->fallback, $this->supported, true) ? $this->fallback : 'en';
    }
}
