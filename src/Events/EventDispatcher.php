<?php

declare(strict_types=1);

namespace Panulat\Events;

final class EventDispatcher
{
    /** @var array<class-string, list<callable>> */
    private array $listeners = [];

    /** @param class-string $eventClass */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        return $event;
    }
}
