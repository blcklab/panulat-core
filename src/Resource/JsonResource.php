<?php

declare(strict_types=1);

namespace Panulat\Resource;

use Panulat\Http\Response;

abstract readonly class JsonResource
{
    public function __construct(protected mixed $resource)
    {
    }

    /** @return array<string, mixed> */
    abstract public function toArray(): array;

    public function response(int $status = 200): Response
    {
        return Response::json(['data' => $this->toArray()], $status);
    }
}
