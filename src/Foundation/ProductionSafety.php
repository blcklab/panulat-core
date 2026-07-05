<?php

declare(strict_types=1);

namespace Panulat\Foundation;

use Panulat\Config\ConfigRepository;

final readonly class ProductionSafety
{
    public function __construct(
        private string $basePath,
        private ConfigRepository $config,
        private bool $requireCaches = true,
    ) {
    }

    public function assertSafe(): void
    {
        if ((string) $this->config->get('app.env', 'local') !== 'production') {
            return;
        }

        $this->assertDebugDisabled();
        $this->assertCorsIsSafe();
        $this->assertStorageIsWritable();

        if ($this->requireCaches) {
            $this->assertOptimizedCachesExist();
        }
    }


    private function assertOptimizedCachesExist(): void
    {
        $required = [
            'config' => (bool) $this->config->get('performance.require_cached_config', true),
            'routes' => (bool) $this->config->get('performance.require_cached_routes', true),
            'container' => (bool) $this->config->get('performance.require_cached_container', true),
        ];

        foreach ($required as $name => $enabled) {
            if (! $enabled) {
                continue;
            }

            $path = $this->basePath . '/bootstrap/cache/' . $name . '.php';
            if (! is_file($path)) {
                throw new \RuntimeException(sprintf(
                    'Missing production cache [%s]. Run [php bin/console optimize] before serving production traffic.',
                    $path,
                ));
            }
        }
    }

    private function assertDebugDisabled(): void
    {
        if ((bool) $this->config->get('app.debug', false)) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }
    }

    private function assertCorsIsSafe(): void
    {
        $credentials = (bool) $this->config->get('cors.credentials', false);
        $origins = (array) $this->config->get('cors.allowed_origins', ['*']);

        if ($credentials && in_array('*', $origins, true)) {
            throw new \RuntimeException('CORS cannot allow credentials with a wildcard origin in production.');
        }
    }

    private function assertStorageIsWritable(): void
    {
        foreach (['/storage/cache', '/storage/logs', '/bootstrap/cache'] as $path) {
            $fullPath = $this->basePath . $path;

            if (! is_dir($fullPath)) {
                throw new \RuntimeException(sprintf('Directory [%s] must exist before serving production traffic.', $fullPath));
            }

            if (! is_writable($fullPath)) {
                throw new \RuntimeException(sprintf('Directory [%s] must be writable in production.', $fullPath));
            }
        }
    }
}
