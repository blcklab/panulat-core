<?php

declare(strict_types=1);

namespace Panulat\Resource;

final readonly class CursorPaginator
{
    /** @param list<mixed> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public ?string $previousCursor,
        public int $limit,
        public string $path,
    ) {
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return ['limit' => $this->limit, 'next_cursor' => $this->nextCursor, 'previous_cursor' => $this->previousCursor];
    }

    /** @return array<string, string|null> */
    public function links(): array
    {
        return [
            'next' => $this->nextCursor === null ? null : $this->path . '?cursor=' . rawurlencode($this->nextCursor) . '&limit=' . $this->limit,
            'prev' => $this->previousCursor === null ? null : $this->path . '?cursor=' . rawurlencode($this->previousCursor) . '&limit=' . $this->limit,
        ];
    }
}
