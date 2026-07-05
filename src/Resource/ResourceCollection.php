<?php

declare(strict_types=1);

namespace Panulat\Resource;

use Panulat\Http\Response;

final readonly class ResourceCollection
{
    /**
     * @param iterable<mixed> $items
     * @param callable(mixed): array<string, mixed> $transformer
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $links
     */
    public function __construct(
        private iterable $items,
        private mixed $transformer,
        private array $meta = [],
        private array $links = [],
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, links: array<string, mixed>} */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->items as $item) {
            $data[] = ($this->transformer)($item);
        }

        return ['data' => $data, 'meta' => $this->meta, 'links' => $this->links];
    }

    public function response(int $status = 200): Response
    {
        return Response::json($this->toArray(), $status);
    }
}
