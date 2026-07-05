<?php

declare(strict_types=1);

namespace Panulat\Resource;

final readonly class OffsetPaginator
{
    /** @param list<mixed> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
        public string $path,
    ) {
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->offset + $this->limit < $this->total,
        ];
    }

    /** @return array<string, string|null> */
    public function links(): array
    {
        $next = $this->offset + $this->limit < $this->total ? $this->path . '?limit=' . $this->limit . '&offset=' . ($this->offset + $this->limit) : null;
        $prevOffset = max(0, $this->offset - $this->limit);
        $prev = $this->offset > 0 ? $this->path . '?limit=' . $this->limit . '&offset=' . $prevOffset : null;

        return ['next' => $next, 'prev' => $prev];
    }
}
