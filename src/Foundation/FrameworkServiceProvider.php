<?php

declare(strict_types=1);

namespace Panulat\Foundation;

use Panulat\Auth\ApiKeyMiddleware;
use Panulat\Authorization\Gate;
use Panulat\Cache\ArrayCache;
use Panulat\Cache\CacheInterface;
use Panulat\Cache\FileCache;
use Panulat\Config\ConfigRepository;
use Panulat\Container\Container;
use Panulat\Cors\CorsMiddleware;
use Panulat\Database\Connection;
use Panulat\Events\EventDispatcher;
use Panulat\Log\FileLogger;
use Panulat\Log\LoggerInterface;
use Panulat\Log\NullLogger;
use Panulat\Middleware\MiddlewareRegistry;
use Panulat\Middleware\RequestLoggingMiddleware;
use Panulat\RateLimit\RateLimiter;
use Panulat\RateLimit\RateLimitMiddleware;
use PDO;

final readonly class FrameworkServiceProvider implements ServiceProviderInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function register(Container $container): void
    {
        $container->singleton(CacheInterface::class, function (Container $container): CacheInterface {
            $config = $container->get(ConfigRepository::class);
            $driver = strtolower((string) $config->get('cache.default', 'array'));

            return match ($driver) {
                'array', 'memory' => new ArrayCache(),
                'file' => new FileCache($this->basePath . '/storage/cache'),
                default => throw new \RuntimeException(sprintf(
                    'Cache driver [%s] is not supported by panulat-core. Install and register an adapter package or use CACHE_DRIVER=array/file.',
                    $driver,
                )),
            };
        });

        $container->singleton(Connection::class, function (Container $container): Connection {
            $config = $container->get(ConfigRepository::class);
            $default = (string) $config->get('database.default', 'sqlite');
            $connection = (array) $config->get('database.connections.' . $default, []);
            $dsn = $this->resolveDsn($default, $connection);
            $username = isset($connection['username']) ? (string) $connection['username'] : null;
            $password = isset($connection['password']) ? (string) $connection['password'] : null;

            if (str_starts_with($dsn, 'sqlite:')) {
                $path = substr($dsn, 7);
                if ($path !== ':memory:' && $path !== '' && ! is_file($path)) {
                    $directory = dirname($path);
                    if (! is_dir($directory)) {
                        mkdir($directory, 0775, true);
                    }
                    touch($path);
                }
            }

            $logger = $container->get(LoggerInterface::class);

            if (! $logger instanceof LoggerInterface) {
                $logger = null;
            }

            return Connection::make(
                dsn: $dsn,
                username: $username,
                password: $password,
                options: [PDO::ATTR_EMULATE_PREPARES => false],
                logger: $logger,
                logQueries: (bool) $config->get('database.log_queries', false),
            );
        });

        $container->singleton(LoggerInterface::class, function (Container $container): LoggerInterface {
            $config = $container->get(ConfigRepository::class);
            $channel = strtolower((string) $config->get('logging.channel', 'stderr'));

            if (in_array($channel, ['none', 'null', 'disabled', 'off'], true)) {
                return new NullLogger();
            }

            $path = $channel === 'stderr'
                ? 'php://stderr'
                : (string) $config->get('logging.path', $this->basePath . '/storage/logs/app.log');

            return new FileLogger(
                path: $path,
                maxBytes: (int) $config->get('logging.max_bytes', 5242880),
                lock: (bool) $config->get('logging.lock', false),
            );
        });

        $container->singleton(RateLimiter::class, fn (Container $container): RateLimiter => new RateLimiter($container->get(CacheInterface::class)));

        $container->factory(RateLimitMiddleware::class, function (Container $container): RateLimitMiddleware {
            $config = $container->get(ConfigRepository::class);
            $limiter = $container->get(RateLimiter::class);

            if (! $limiter instanceof RateLimiter) {
                throw new \RuntimeException('Rate limiter service is not available.');
            }

            return new RateLimitMiddleware(
                limiter: $limiter,
                maxAttempts: (int) $config->get('rate_limit.max_attempts', 60),
                windowSeconds: (int) $config->get('rate_limit.window_seconds', 60),
            );
        });

        $container->singleton(MiddlewareRegistry::class, function (Container $container): MiddlewareRegistry {
            $config = $container->get(ConfigRepository::class);
            $registry = new MiddlewareRegistry($container);

            $registry->aliases([
                'api-key' => ApiKeyMiddleware::class,
            ]);

            $registry->throttle(
                'default',
                (int) $config->get('rate_limit.max_attempts', 60),
                (int) $config->get('rate_limit.window_seconds', 60),
            );

            /** @var array<string, array<string, mixed>> $profiles */
            $profiles = (array) $config->get('rate_limit.profiles', []);
            $registry->throttles($profiles);

            return $registry;
        });

        $container->singleton(Gate::class, Gate::class);
        $container->singleton(EventDispatcher::class, EventDispatcher::class);

        $container->factory(ApiKeyMiddleware::class, static function (Container $container): ApiKeyMiddleware {
            $config = $container->get(ConfigRepository::class);
            $raw = trim((string) $config->get('auth.api_keys', ''));
            $keys = [];

            foreach (array_filter(array_map('trim', explode(',', $raw))) as $key) {
                $keys[$key] = $key;
            }

            return new ApiKeyMiddleware($keys);
        });

        $container->factory(CorsMiddleware::class, function (Container $container): CorsMiddleware {
            $config = $container->get(ConfigRepository::class);
            return new CorsMiddleware(
                allowedOrigins: $this->stringList((array) $config->get('cors.allowed_origins', ['*'])),
                allowedMethods: $this->stringList((array) $config->get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])),
                allowedHeaders: $this->stringList((array) $config->get('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-API-Key'])),
                credentials: (bool) $config->get('cors.credentials', false),
            );
        });
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_map('strval', $values));
    }

    /** @param array<string, mixed> $connection */
    private function resolveDsn(string $name, array $connection): string
    {
        if (isset($connection['dsn']) && is_string($connection['dsn']) && trim($connection['dsn']) !== '') {
            return $connection['dsn'];
        }

        $driver = (string) ($connection['driver'] ?? $name);

        if ($driver === 'mysql') {
            $host = (string) ($connection['host'] ?? '127.0.0.1');
            $port = (string) ($connection['port'] ?? '3306');
            $database = (string) ($connection['database'] ?? 'panulat');
            $charset = (string) ($connection['charset'] ?? 'utf8mb4');

            return sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host,
                $port,
                $database,
                $charset,
            );
        }

        if ($driver === 'sqlite') {
            $database = (string) ($connection['database'] ?? ':memory:');

            if ($database !== ':memory:' && ! str_starts_with($database, '/')) {
                $database = $this->basePath . '/' . ltrim($database, '/');
            }

            return 'sqlite:' . $database;
        }

        return 'sqlite::memory:';
    }

    public function boot(Application $app): void
    {
        if ((bool) $app->config()->get('logging.requests', false)) {
            $app->middleware(RequestLoggingMiddleware::class);
        }

        if ((bool) $app->config()->get('cors.enabled', true)) {
            $app->middleware(CorsMiddleware::class);
        }
    }
}
