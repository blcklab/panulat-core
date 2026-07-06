<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Foundation\Application;
use Panulat\Http\Request;
use Panulat\Http\Response;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testControllersAreResolvedFreshPerRequest(): void
    {
        FreshController::$instances = 0;
        $basePath = $this->createBasePath();

        try {
            $app = new Application($basePath, ignoreCaches: true);
            $app->router()->get('/fresh', [FreshController::class, 'show']);

            $request = Request::fromServer([
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.test',
                'REQUEST_URI' => '/fresh',
            ]);

            $first = $app->handle($request);
            $second = $app->handle($request);

            self::assertSame(200, $first->getStatusCode());
            self::assertSame(200, $second->getStatusCode());
            self::assertSame(2, FreshController::$instances);
        } finally {
            $this->removeDirectory($basePath);
        }
    }

    private function createBasePath(): string
    {
        $basePath = sys_get_temp_dir() . '/panulat_app_' . bin2hex(random_bytes(6));
        mkdir($basePath . '/config', 0775, true);
        mkdir($basePath . '/routes', 0775, true);
        mkdir($basePath . '/bootstrap/cache', 0775, true);
        mkdir($basePath . '/storage/cache', 0775, true);
        mkdir($basePath . '/storage/logs', 0775, true);

        file_put_contents($basePath . '/config/app.php', "<?php return ['env' => 'local', 'debug' => false, 'providers' => []];\n");

        return $basePath;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $item) {
            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}

final class FreshController
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public function show(): Response
    {
        return Response::json(['data' => ['instances' => self::$instances]]);
    }
}
