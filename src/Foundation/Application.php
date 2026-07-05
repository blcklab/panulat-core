<?php

declare(strict_types=1);

namespace Panulat\Foundation;

use Panulat\Config\ConfigLoader;
use Panulat\Config\ConfigRepository;
use Panulat\Config\Env;
use Panulat\Container\Container;
use Panulat\Container\MetadataCache;
use Panulat\Foundation\Exception\NotFoundException;
use Panulat\Http\Emitter;
use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Middleware\CallableRequestHandler;
use Panulat\Middleware\MiddlewareRegistry;
use Panulat\Middleware\Pipeline;
use Panulat\Routing\RouteCache;
use Panulat\Routing\RouteMatch;
use Panulat\Routing\Router;
use Panulat\Support\Translator;

final class Application
{
    private Container $container;

    private Router $router;

    private ConfigRepository $config;

    private ErrorHandler $errors;

    private Translator $translator;

    private bool $production;

    /** @var list<class-string|string|object|callable> */
    private array $globalMiddleware = [];

    /** @var list<ServiceProviderInterface> */
    private array $providers = [];

    public function __construct(
        private string $basePath,
        private bool $ignoreCaches = false,
    ) {
        $this->container = new Container();
        $this->router = new Router();

        if ($this->shouldLoadEnvironmentFile()) {
            (new Env())->load($this->basePath . '/.env');
        }

        $production = $this->environmentName() === 'production' || $this->cachedProductionEnvironment();
        $this->production = $production;
        $this->config = (new ConfigLoader($this->basePath))->load(! $this->ignoreCaches && $production);
        $this->translator = Translator::fromConfig($this->basePath, $this->config);
        $this->errors = new ErrorHandler((bool) $this->config->get('app.debug', false), $this->translator);

        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(ConfigRepository::class, $this->config);
        $this->container->instance(Translator::class, $this->translator);
        $this->container->instance(ErrorHandler::class, $this->errors);

        if (! $this->ignoreCaches && (string) $this->config->get('app.env', 'local') === 'production') {
            (new MetadataCache($this->basePath('/bootstrap/cache/container.php')))->load($this->container);
        }

        (new ProductionSafety($this->basePath, $this->config, requireCaches: ! $this->ignoreCaches))->assertSafe();
    }

    public static function boot(string $basePath, bool $ignoreCaches = false): self
    {
        $app = new self($basePath, $ignoreCaches);
        $app->register(new FrameworkServiceProvider($basePath));
        $app->loadConfiguredProviders();
        $app->bootProviders();
        $app->loadRoutes();

        return $app;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(): ConfigRepository
    {
        return $this->config;
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function middlewareRegistry(): MiddlewareRegistry
    {
        $registry = $this->container->get(MiddlewareRegistry::class);

        if (! $registry instanceof MiddlewareRegistry) {
            throw new \RuntimeException('Middleware registry service is not available.');
        }

        return $registry;
    }

    /** @return list<class-string|string|object|callable> */
    public function globalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    public function register(ServiceProviderInterface $provider): void
    {
        $provider->register($this->container);
        $this->providers[] = $provider;
    }

    /** @param class-string|string|object|callable $middleware */
    public function middleware(mixed $middleware): void
    {
        if (
            $this->production
            && (bool) $this->config->get('performance.pre_resolve_global_middleware', true)
            && is_string($middleware)
            && class_exists($middleware)
        ) {
            $this->globalMiddleware[] = $this->container->get($middleware);
            return;
        }

        $this->globalMiddleware[] = $middleware;
    }

    public function middlewareAlias(string $name, mixed $middleware): void
    {
        $this->middlewareRegistry()->alias($name, $middleware);
    }

    /** @param array<string, mixed> $aliases */
    public function middlewareAliases(array $aliases): void
    {
        $this->middlewareRegistry()->aliases($aliases);
    }

    /** @param list<mixed> $middleware */
    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareRegistry()->group($name, $middleware);
    }

    /** @param array<string, list<mixed>> $groups */
    public function middlewareGroups(array $groups): void
    {
        $this->middlewareRegistry()->groups($groups);
    }

    public function throttle(string $name, int $maxAttempts, int $windowSeconds = 60): void
    {
        $this->middlewareRegistry()->throttle($name, $maxAttempts, $windowSeconds);
    }

    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request->getMethod(), $request->getUri()->getPath());
            $request = $this->withRouteAttributes($request, $match);
            /** @var list<class-string|string|object|callable> $middleware */
            $middleware = [...$this->globalMiddleware, ...$match->route->middleware()];
            $handler = new CallableRequestHandler(fn (Request $r): Response => $this->dispatchRoute($match, $r));

            return (new Pipeline($middleware, $handler, $this->container, $this->middlewareRegistry()))->handle($request);
        } catch (\Throwable $throwable) {
            return $this->errors->render($throwable);
        }
    }

    public function run(): void
    {
        (new Emitter((bool) $this->config->get('performance.emit_content_length', true)))->emit($this->handle(Request::fromGlobals(
            maxBodyBytes: (int) $this->config->get('http.max_body_bytes', 1048576),
        )));
    }

    private function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }
    }

    private function loadConfiguredProviders(): void
    {
        foreach ((array) $this->config->get('app.providers', []) as $providerClass) {
            if (is_string($providerClass) && class_exists($providerClass)) {
                $provider = $this->container->get($providerClass);
                if ($provider instanceof ServiceProviderInterface) {
                    $this->register($provider);
                }
            }
        }
    }

    private function loadRoutes(): void
    {
        $cache = $this->basePath('/bootstrap/cache/routes.php');
        if (! $this->ignoreCaches && (string) $this->config->get('app.env', 'local') === 'production' && is_file($cache)) {
            $this->router = (new RouteCache($cache))->read();
            $this->container->instance(Router::class, $this->router);
            return;
        }

        $routes = $this->basePath('/routes/api.php');
        if (is_file($routes)) {
            $router = $this->router;
            require $routes;
        }
    }

    private function withRouteAttributes(Request $request, RouteMatch $match): Request
    {
        return $request->withAttributes(array_merge(
            $request->getAttributes(),
            [
                'route' => $match->route,
                'route_params' => $match->parameters,
            ],
            $match->parameters,
        ));
    }

    private function dispatchRoute(RouteMatch $match, Request $request): Response
    {
        $handler = $match->route->handler();

        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->container->get($handler);
        }

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && class_exists($handler[0])) {
            $handler = [$this->container->get($handler[0]), $handler[1]];
        }

        if (! is_callable($handler)) {
            throw new NotFoundException('Route handler is not callable.');
        }

        $result = $handler($request, $match->parameters);

        return $result instanceof Response ? $result : Response::json($result);
    }

    private function shouldLoadEnvironmentFile(): bool
    {
        if ($this->ignoreCaches) {
            return true;
        }

        $configCache = $this->basePath('/bootstrap/cache/config.php');
        if (! is_file($configCache)) {
            return true;
        }

        $processEnv = $this->processEnvironmentName();
        if ($processEnv === 'production') {
            return false;
        }

        return $this->cachedEnvironmentName($configCache) !== 'production';
    }

    private function environmentName(): string
    {
        return $this->processEnvironmentName() ?? 'local';
    }

    private function cachedProductionEnvironment(): bool
    {
        if ($this->ignoreCaches) {
            return false;
        }

        $configCache = $this->basePath('/bootstrap/cache/config.php');

        return is_file($configCache) && $this->cachedEnvironmentName($configCache) === 'production';
    }

    private function processEnvironmentName(): ?string
    {
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV');

        return is_string($env) && $env !== '' ? $env : null;
    }

    private function cachedEnvironmentName(string $configCache): ?string
    {
        $data = require $configCache;
        if (! is_array($data)) {
            return null;
        }

        $env = $data['app']['env'] ?? null;

        return is_string($env) && $env !== '' ? $env : null;
    }
}
