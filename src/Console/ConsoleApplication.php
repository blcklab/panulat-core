<?php

declare(strict_types=1);

namespace Panulat\Console;

use Panulat\Support\Translator;

final class ConsoleApplication
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public function __construct(private ?Translator $translator = null)
    {
    }

    public function add(CommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    public function alias(string $alias, string $commandName): void
    {
        $alias = trim($alias);
        $commandName = trim($commandName);

        if ($alias === '' || $commandName === '') {
            return;
        }

        if ($commandName !== 'list' && ! isset($this->commands[$commandName])) {
            return;
        }

        $this->aliases[$alias] = $commandName;
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        $name = $this->aliases[$name] ?? $name;

        if ($name === 'list') {
            echo $this->trans('console.list_title', 'Available Panulat commands:') . PHP_EOL;

            foreach ($this->commands as $command) {
                $aliases = $this->aliasesFor($command->name());
                $aliasText = $aliases === []
                    ? ''
                    : ' [' . $this->trans('console.alias_label', 'aliases') . ': ' . implode(', ', $aliases) . ']';

                echo $command->name() . $aliasText . ' - ' . $command->description() . PHP_EOL;
            }

            return 0;
        }

        $command = $this->commands[$name] ?? null;

        if (! $command instanceof CommandInterface) {
            fwrite(STDERR, $this->trans('console.command_not_found', "Command [{$name}] not found.", ['name' => $name]) . PHP_EOL);
            return 1;
        }

        return $command->execute(array_slice($argv, 2));
    }

    /** @return list<string> */
    private function aliasesFor(string $commandName): array
    {
        $aliases = [];

        foreach ($this->aliases as $alias => $mapped) {
            if ($mapped === $commandName) {
                $aliases[] = $alias;
            }
        }

        sort($aliases);

        return $aliases;
    }

    /** @param array<string, scalar|null> $replace */
    private function trans(string $key, string $default, array $replace = []): string
    {
        return $this->translator?->get($key, $replace, $default) ?? $default;
    }
}
