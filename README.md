<p align="left">
  <img src="https://img.shields.io/packagist/v/blcklab/panulat-core?style=flat-square" alt="Packagist version" />
  <img src="https://img.shields.io/packagist/dm/blcklab/panulat-core?style=flat-square" alt="downloads" />
  <a href="https://github.com/blcklab/panulat-core/actions/workflows/ci.yml">
    <img src="https://github.com/blcklab/panulat-core/actions/workflows/ci.yml/badge.svg" alt="CI" />
  </a>
  <img src="https://img.shields.io/github/license/blcklab/panulat-core?v=2" alt="license" />
</p>

# Panulat Core

Panulat Core is the modular foundation of Panulat, a lightweight PHP framework for building clean REST APIs and API-first applications.

## Features

* HTTP request and response handling
* Routing and route groups
* Dependency injection container
* Middleware pipeline
* Application kernel and service providers
* Configuration and environment loading
* Error handling with safe JSON responses
* Validation
* Database connection and query builder
* Migrations and seeders
* Lightweight model support
* Cache contracts and local cache drivers
* Rate limiting
* CORS support
* API-key middleware
* Resources and pagination
* Console command foundation
* File upload support
* Health and readiness endpoints

Optional features such as JWT authentication, developer scaffolding, Redis, queues, and OpenAPI support are provided through separate packages instead of being bundled into the core.

## Install

```bash
composer require blcklab/panulat-core
```

For new applications, use the Panulat starter project:

```bash
composer create-project blcklab/panulat my-api
```

## Requirements

* PHP 8.3 or higher
* `ext-json`
* `ext-pdo`
* A PDO database driver such as `pdo_mysql` or `pdo_sqlite`

## Basic Usage

```php
use Panulat\Foundation\Application;
use Panulat\Foundation\FrameworkServiceProvider;
use Panulat\Http\Response;

$app = new Application(__DIR__);

$app->register(new FrameworkServiceProvider(__DIR__));

$app->router()->get('/health', function () {
    return Response::json([
        'status' => 'ok',
    ]);
});

$app->run();
```

For full applications, the starter project includes the recommended structure, bootstrap files, configuration, routes, and development tooling.

## Routing

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
```

Route groups can share prefixes and middleware:

```php
$router->group('/v1', function ($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);
}, ['api']);
```

## Middleware

Middleware can be registered as aliases or grouped together:

```php
$app->middlewareAlias('api-key', ApiKeyMiddleware::class);

$app->middlewareGroup('api', [
    'api-key',
    'throttle:api',
]);

$router->get('/users', [UserController::class, 'index'], [
    'api',
]);
```

Named throttles are also supported:

```php
$app->throttle('login', maxAttempts: 5, windowSeconds: 60);

$router->post('/auth/login', [AuthController::class, 'login'], [
    'throttle:login',
]);
```

## Query Builder

```php
$users = $db->table('users')
    ->select(['id', 'name', 'email'])
    ->whereNull('deleted_at')
    ->orderBy('id', 'desc')
    ->paginate(page: 1, perPage: 20);
```

```php
$userId = $db->table('users')->insertGetId([
    'name' => 'Avelino',
    'email' => 'avelino@example.test',
]);
```

The query builder supports common API data operations, including filtering, pagination, inserts, updates, deletes, joins, aggregates, transactions, and raw queries with bindings.

## Responses

```php
return Response::json([
    'message' => 'Created',
], 201);

return Response::text('ok');

return Response::noContent();
```

## File Uploads

```php
$file = $request->file('avatar');

if ($request->hasFile('avatar')) {
    $file->moveTo(__DIR__ . '/storage/uploads/avatar.png');
}
```

Uploaded files are represented by `Panulat\Http\UploadedFile`.

## Production

Use safe production settings:

```env
APP_ENV=production
APP_DEBUG=false
```

For optimized production installs:

```bash
composer install --no-dev --optimize-autoloader
```

In production, errors are returned as safe JSON responses without stack traces.

## Related Packages

* `blcklab/panulat-core` — framework core
* `blcklab/panulat` — starter API project
* `blcklab/panulat-jwt` — optional JWT authentication package
* `blcklab/panulat-cli` — optional developer CLI and scaffolding commands

## License

MIT
