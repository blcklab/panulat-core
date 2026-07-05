<?php

declare(strict_types=1);

namespace Panulat\Database;

/**
 * Explicit table model / repository helper.
 *
 * This is intentionally not ActiveRecord: it has no magic attributes,
 * no relationships, and no reflection serialization.
 */
abstract readonly class Model
{
    public function __construct(protected Connection $connection)
    {
    }

    abstract protected function table(): string;

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    abstract protected function columns(): array;

    /** @return list<string> */
    protected function fillable(): array
    {
        return [];
    }

    public function query(): QueryBuilder
    {
        return $this->connection->table($this->table());
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->query()
            ->select($this->columns())
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /** @return array<string, mixed>|null */
    public function find(string|int $id): ?array
    {
        return $this->query()
            ->select($this->columns())
            ->where($this->primaryKey(), '=', $id)
            ->first();
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->connection->quoteIdentifier($this->table());
        $row = $this->connection->select($sql)[0] ?? ['aggregate' => 0];

        return (int) $row['aggregate'];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        $id = $this->query()->insertGetId($this->onlyFillable($attributes));
        $created = $this->find($id);

        if ($created === null) {
            return [$this->primaryKey() => $id];
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    public function updateById(string|int $id, array $attributes): ?array
    {
        $this->query()
            ->where($this->primaryKey(), '=', $id)
            ->update($this->onlyFillable($attributes));

        return $this->find($id);
    }

    public function deleteById(string|int $id): bool
    {
        return $this->query()
            ->where($this->primaryKey(), '=', $id)
            ->delete() > 0;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function onlyFillable(array $attributes): array
    {
        $fillable = $this->fillable();

        if ($fillable === []) {
            return $attributes;
        }

        return array_intersect_key($attributes, array_flip($fillable));
    }
}
