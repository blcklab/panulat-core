<?php

declare(strict_types=1);

namespace Panulat\Database;

final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $wheres = [];

    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
    ) {
    }

    /** @param list<string> $columns */
    public function select(array $columns): self
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('Select columns must be explicit.');
        }

        $this->columns = $columns;

        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER',
    ): self {
        $type = $this->normalizeJoinType($type);

        $this->joins[] = sprintf(
            ' %s JOIN %s ON %s %s %s',
            $type,
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($first),
            $this->normalizeOperator($operator),
            $this->quoteIdentifier($second),
        );

        return $this;
    }

    public function innerJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'INNER');
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $key = $this->nextBindingKey('w');
        $this->addWhere(
            $this->quoteIdentifier($column) . ' ' . $this->normalizeOperator($operator) . ' :' . $key,
        );
        $this->bindings[$key] = $value;

        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $key = $this->nextBindingKey('w');
        $this->addWhere(
            $this->quoteIdentifier($column) . ' ' . $this->normalizeOperator($operator) . ' :' . $key,
            'OR',
        );
        $this->bindings[$key] = $value;

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        return $this->whereInInternal($column, $values, false, 'AND');
    }

    /** @param list<mixed> $values */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereInInternal($column, $values, false, 'OR');
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereInInternal($column, $values, true, 'AND');
    }

    public function whereNull(string $column): self
    {
        $this->addWhere($this->quoteIdentifier($column) . ' IS NULL');

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->addWhere($this->quoteIdentifier($column) . ' IS NULL', 'OR');

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->addWhere($this->quoteIdentifier($column) . ' IS NOT NULL');

        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        $fromKey = $this->nextBindingKey('w');
        $this->bindings[$fromKey] = $from;
        $toKey = $this->nextBindingKey('w');
        $this->bindings[$toKey] = $to;

        $this->addWhere($this->quoteIdentifier($column) . ' BETWEEN :' . $fromKey . ' AND :' . $toKey);

        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function orWhereLike(string $column, string $pattern): self
    {
        return $this->orWhere($column, 'LIKE', $pattern);
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->quoteIdentifier($column) . ' ' . $direction;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $query = clone $this;
        $rows = $query->limit(1)->get();

        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function find(string|int $id, string $column = 'id'): ?array
    {
        $query = clone $this;

        if ($query->columns === []) {
            $query->select(['*']);
        }

        return $query->where($column, '=', $id)->first();
    }

    /** @param array<string, mixed> $values */
    public function insert(array $values): int
    {
        return $this->connection->statement($this->insertSql($values), $values)->rowCount();
    }

    /** @param array<string, mixed> $values */
    public function insertGetId(array $values): int
    {
        $this->connection->statement($this->insertSql($values), $values);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Update values cannot be empty.');
        }

        $sets = [];
        $bindings = $this->bindings;

        foreach ($values as $column => $value) {
            $key = $this->nextBindingKey('u', $bindings);
            $sets[] = $this->quoteIdentifier($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }

        $sql = 'UPDATE ' . $this->quoteIdentifier($this->table) . ' SET ' . implode(', ', $sets) . $this->whereSql();

        return $this->connection->statement($sql, $bindings)->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($this->table) . $this->whereSql();

        return $this->connection->statement($sql, $this->bindings)->rowCount();
    }

    public function count(string $column = '*'): int
    {
        $value = $this->aggregate('COUNT', $column);

        return (int) ($value ?? 0);
    }

    public function exists(): bool
    {
        $sql = 'SELECT 1 AS exists_value FROM ' . $this->quoteIdentifier($this->table)
            . implode('', $this->joins)
            . $this->whereSql()
            . ' LIMIT 1';

        return $this->connection->select($sql, $this->bindings) !== [];
    }

    public function sum(string $column): int|float|null
    {
        return $this->numericAggregate('SUM', $column);
    }

    public function avg(string $column): int|float|null
    {
        return $this->numericAggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{total: int, per_page: int, current_page: int, last_page: int, from: int|null, to: int|null}
     * }
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = (clone $this)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $data = (clone $this)->limit($perPage)->offset($offset)->get();
        $from = $data === [] ? null : $offset + 1;
        $to = $data === [] ? null : $offset + count($data);

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    public function toSql(): string
    {
        if ($this->columns === []) {
            throw new \LogicException('No columns selected. Call select([\'id\', ...]) before reading.');
        }

        $sql = 'SELECT ' . implode(', ', array_map($this->quoteIdentifier(...), $this->columns))
            . ' FROM ' . $this->quoteIdentifier($this->table)
            . implode('', $this->joins)
            . $this->whereSql();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /** @param array<string, mixed> $values */
    private function insertSql(array $values): string
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Insert values cannot be empty.');
        }

        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        return 'INSERT INTO ' . $this->quoteIdentifier($this->table)
            . ' (' . implode(', ', array_map($this->quoteIdentifier(...), $columns)) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
    }

    private function whereSql(): string
    {
        return $this->wheres === [] ? '' : ' WHERE ' . implode(' ', $this->wheres);
    }

    private function addWhere(string $condition, string $boolean = 'AND'): void
    {
        $boolean = strtoupper(trim($boolean));

        if (! in_array($boolean, ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('Where boolean must be AND or OR.');
        }

        $this->wheres[] = $this->wheres === [] ? $condition : $boolean . ' ' . $condition;
    }

    /** @param list<mixed> $values */
    private function whereInInternal(string $column, array $values, bool $not, string $boolean): self
    {
        if ($values === []) {
            $this->addWhere($not ? '1 = 1' : '1 = 0', $boolean);

            return $this;
        }

        $placeholders = [];

        foreach ($values as $value) {
            $key = $this->nextBindingKey('w');
            $placeholders[] = ':' . $key;
            $this->bindings[$key] = $value;
        }

        $this->addWhere(
            $this->quoteIdentifier($column) . ($not ? ' NOT IN ' : ' IN ') . '(' . implode(', ', $placeholders) . ')',
            $boolean,
        );

        return $this;
    }

    private function aggregate(string $function, string $column): mixed
    {
        $function = strtoupper($function);
        $columnSql = $column === '*' ? '*' : $this->quoteIdentifier($column);
        $sql = 'SELECT ' . $function . '(' . $columnSql . ') AS aggregate FROM ' . $this->quoteIdentifier($this->table)
            . implode('', $this->joins)
            . $this->whereSql();
        $row = $this->connection->select($sql, $this->bindings)[0] ?? ['aggregate' => null];

        return $row['aggregate'] ?? null;
    }

    private function numericAggregate(string $function, string $column): int|float|null
    {
        $value = $this->aggregate($function, $column);

        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return (float) $value;
    }

    /** @param array<string, mixed>|null $bindings */
    private function nextBindingKey(string $prefix, ?array $bindings = null): string
    {
        $bindings ??= $this->bindings;
        $index = count($bindings);

        do {
            $key = $prefix . $index;
            $index++;
        } while (array_key_exists($key, $bindings));

        return $key;
    }

    private function normalizeJoinType(string $type): string
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'INNER', 'LEFT', 'RIGHT' => $type,
            default => throw new \InvalidArgumentException('Join type must be INNER, LEFT, or RIGHT.'),
        };
    }

    private function normalizeOperator(string $operator): string
    {
        $operator = strtoupper(trim($operator));

        return match ($operator) {
            '=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE' => $operator,
            default => throw new \InvalidArgumentException('Unsupported SQL operator: ' . $operator),
        };
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }
}
