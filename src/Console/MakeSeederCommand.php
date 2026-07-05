<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class MakeSeederCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'make:seeder';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.make_seeder', 'Create a database seeder class in database/seeders.');
    }

    public function execute(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null) {
            fwrite(STDERR, 'Seeder name is required. Example: php bin/console make:seeder UserSeeder' . PHP_EOL);
            return 1;
        }

        [$namespace, $class, $directory] = $this->classParts((string) $name);
        $file = $directory . '/' . $class . '.php';

        if (is_file($file)) {
            fwrite(STDERR, 'Seeder already exists: ' . $file . PHP_EOL);
            return 1;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($file, $this->template($namespace, $class) . PHP_EOL);
        echo CommandTranslator::text($this->basePath, 'console.created', 'Created :path', ['path' => $file]) . PHP_EOL;

        return 0;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function classParts(string $name): array
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $segments = array_values(array_filter(explode('/', $name), static fn (string $segment): bool => $segment !== ''));
        $segments = array_map(static fn (string $segment): string => preg_replace('/[^A-Za-z0-9_]/', '', $segment) ?: 'Generated', $segments);
        $class = array_pop($segments) ?: 'GeneratedSeeder';

        if (! str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $namespace = 'Database\\Seeders' . ($segments === [] ? '' : '\\' . implode('\\', $segments));
        $directory = $this->basePath . '/database/seeders' . ($segments === [] ? '' : '/' . implode('/', $segments));

        return [$namespace, $class, $directory];
    }

    private function template(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Panulat\Database\Seeder;

final class {$class} extends Seeder
{
    public function run(): void
    {
        // Example:
        // if (
        //     \$this->table('users')
        //         ->select(['id'])
        //         ->where('email', '=', 'avelino@example.test')
        //         ->first() === null
        // ) {
        //     \$this->table('users')->insert([
        //         'name' => 'Avelino',
        //         'email' => 'avelino@example.test',
        //     ]);
        // }
    }
}
PHP;
    }
}
