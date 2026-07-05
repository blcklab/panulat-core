<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Resource\ResourceCollection;
use PHPUnit\Framework\TestCase;

final class ResourceTest extends TestCase
{
    public function testCollectionShape(): void
    {
        $collection = new ResourceCollection([['id' => 1]], static fn (array $row): array => ['id' => $row['id']], ['total' => 1], ['self' => '/v1/users']);
        $payload = $collection->toArray();

        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertArrayHasKey('links', $payload);
    }
}
