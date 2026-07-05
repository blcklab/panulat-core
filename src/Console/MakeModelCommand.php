<?php

declare(strict_types=1);

namespace Panulat\Console;

final readonly class MakeModelCommand implements CommandInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return CommandTranslator::text($this->basePath, 'console.description.make_model', 'Create an application table model in app/Models.');
    }

    public function execute(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null) {
            fwrite(STDERR, 'Model name is required.' . PHP_EOL);
            return 1;
        }

        [$namespace, $class, $directory] = $this->classParts((string) $name, 'App\\Models', 'app/Models');
        $file = $directory . '/' . $class . '.php';

        if (is_file($file)) {
            fwrite(STDERR, 'Model already exists: ' . $file . PHP_EOL);
            return 1;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class) ?: $class) . 's';

        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Panulat\Database\Model;

final readonly class {$class} extends Model
{
    protected function table(): string
    {
        return '{$table}';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id'];
    }

    /** @return list<string> */
    protected function fillable(): array
    {
        return [];
    }
}
PHP;

        file_put_contents($file, $code . PHP_EOL);
        echo CommandTranslator::text($this->basePath, 'console.created', 'Created :path', ['path' => $file]) . PHP_EOL;

        return 0;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function classParts(string $name, string $baseNamespace, string $baseDirectory): array
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $segments = array_values(array_filter(explode('/', $name), static fn (string $segment): bool => $segment !== ''));
        $segments = array_map(static fn (string $segment): string => preg_replace('/[^A-Za-z0-9_]/', '', $segment) ?: 'Generated', $segments);
        $class = array_pop($segments) ?: 'GeneratedModel';
        $namespace = $baseNamespace . ($segments === [] ? '' : '\\' . implode('\\', $segments));
        $directory = $this->basePath . '/' . $baseDirectory . ($segments === [] ? '' : '/' . implode('/', $segments));

        return [$namespace, $class, $directory];
    }
}
