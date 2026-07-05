# Panulat Core

Panulat Core is the modular framework core behind Panulat, a lightweight PHP 8.3+ framework for building REST APIs.

It provides the essential pieces needed to build API applications while keeping the core small, focused, and easy to extend.

## Features

* HTTP request and response handling
* Routing
* Dependency injection container
* Middleware pipeline
* Application kernel
* Config and environment loading
* Error handling
* Logging
* Database connection and query builder
* Base model support
* Validation
* API-key middleware
* CORS and rate limiting
* Resources and pagination
* Console command support

Optional features, such as JWT authentication, are available through separate packages instead of being bundled into the core.

## Install

```bash
composer require blcklab/panulat-core
```

For new projects, start with the Panulat starter application:

```bash
composer create-project blcklab/panulat my-api
```

## Requirements

```json
{
  "require": {
    "php": "^8.3",
    "ext-json": "*",
    "ext-pdo": "*"
  }
}
```

You will also need the PDO driver for your database, such as `pdo_mysql` or `pdo_sqlite`.

## Basic Usage

```php
use Panulat\Foundation\Application;
use Panulat\Http\Response;

$app = new Application(__DIR__);

$app->router()->get('/health', function () {
    return Response::json([
        'status' => 'ok',
    ]);
});

$app->run();
```

## Routing

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
```

You can also group routes with a shared prefix and middleware:

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

Named throttles are supported as well:

```php
$app->throttle('login', maxAttempts: 5, windowSeconds: 60);

$router->post('/auth/login', [AuthController::class, 'login'], [
    'throttle:login',
]);
```

## Requests

```php
$request->json();          // decoded JSON body
$request->body();          // raw request body
$request->getParsedBody(); // form body
$request->post('name');    // form value
$request->query('page');   // query value
$request->input('name');   // JSON, form, then query fallback
```

Request bodies are read lazily, so they are only loaded when needed.

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

$photos = $request->fileList('photos');
```

Uploaded files are represented by `Panulat\Http\UploadedFile`.

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

```php
$total = $db->table('orders')
    ->where('status', '=', 'paid')
    ->sum('amount');
```

The query builder covers the common database operations needed for API development, including selecting, filtering, pagination, inserts, updates, deletes, joins, aggregates, transactions, and raw queries with bindings.

## Helpers

Panulat Core includes a small set of framework helpers:

```php
panulat_env('APP_NAME', 'Panulat API');
panulat_bool('true');
panulat_int('42');
panulat_string(' Panulat ');
panulat_path('storage/logs/app.log', $basePath);
panulat_ascii('PANULAT API');
```

The core does not include application-level helpers such as `app()`, `request()`, `auth()`, `view()`, or session helpers.

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

## Runtime Compatibility

Panulat Core is PHP-FPM-first. It can also be used with long-running runtimes such as Swoole, RoadRunner, and FrankenPHP, as long as the application code is written safely.

For long-running runtimes:

* Keep controllers stateless
* Keep singleton services stateless
* Avoid storing request-specific data in shared objects
* Create a fresh request object for each request

## Optional Packages

JWT authentication is available as a separate package:

```bash
composer require blcklab/panulat-jwt
```

## Related Packages

* `blcklab/panulat-core` — modular framework core
* `blcklab/panulat` — starter project
* `blcklab/panulat-jwt` — optional JWT authentication package
