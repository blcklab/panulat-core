<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Database\Connection;
use Panulat\Database\DatabaseException;
use Panulat\Log\LogLevel;
use Panulat\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testQueryBuilderWithSqliteMemory(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $id = $db->table('users')->insertGetId(['email' => 'a@example.test']);
        $user = $db->table('users')->select(['id', 'email'])->where('id', '=', $id)->first();

        self::assertSame('a@example.test', $user['email'] ?? null);
    }

    public function testQueryBuilderSupportsJoins(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $db->statement('CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, bio TEXT NULL)');

        $userId = $db->table('users')->insertGetId(['email' => 'a@example.test']);
        $db->table('profiles')->insert(['user_id' => $userId, 'bio' => 'API developer']);

        $user = $db->table('users')
            ->select([
                'users.id as id',
                'users.email as email',
                'profiles.bio as profile_bio',
            ])
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.id', '=', $userId)
            ->first();

        self::assertSame('a@example.test', $user['email'] ?? null);
        self::assertSame('API developer', $user['profile_bio'] ?? null);
    }

    public function testQueryBuilderCompilesJoinSqlWithAliases(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $sql = $db->table('users as u')
            ->select(['u.id as id', 'p.bio as profile_bio'])
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.id', '=', 1)
            ->orderBy('u.id', 'desc')
            ->limit(10)
            ->offset(20)
            ->toSql();

        self::assertSame(
            'SELECT "u"."id" AS "id", "p"."bio" AS "profile_bio" FROM "users" AS "u" LEFT JOIN "profiles" AS "p" ON "p"."user_id" = "u"."id" WHERE "u"."id" = :w0 ORDER BY "u"."id" DESC LIMIT 10 OFFSET 20',
            $sql,
        );
    }

    public function testQueryBuilderSupportsFilteringAggregatesExistsAndPagination(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $db->statement('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL, amount INTEGER NOT NULL, paid_at TEXT NULL)');
        $db->table('orders')->insert(['status' => 'paid', 'amount' => 100, 'paid_at' => '2026-01-01']);
        $db->table('orders')->insert(['status' => 'paid', 'amount' => 250, 'paid_at' => '2026-01-02']);
        $db->table('orders')->insert(['status' => 'pending', 'amount' => 75, 'paid_at' => null]);

        self::assertSame(2, $db->table('orders')->whereIn('status', ['paid'])->whereBetween('amount', 100, 250)->count());
        self::assertTrue($db->table('orders')->whereLike('status', 'pa%')->exists());
        self::assertSame(1, $db->table('orders')->whereNull('paid_at')->count());
        self::assertSame(350.0, (float) $db->table('orders')->where('status', '=', 'paid')->sum('amount'));
        self::assertSame(175.0, (float) $db->table('orders')->where('status', '=', 'paid')->avg('amount'));
        self::assertSame(75, (int) $db->table('orders')->min('amount'));
        self::assertSame(250, (int) $db->table('orders')->max('amount'));
        
        $page = $db->table('orders')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->paginate(page: 2, perPage: 2);

        self::assertCount(1, $page['data']);
        self::assertSame(3, $page['meta']['total']);
        self::assertSame(2, $page['meta']['current_page']);
        self::assertSame(2, $page['meta']['last_page']);
    }

    public function testOrWhereAndFindHelpers(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, role TEXT NOT NULL)');
        $adminId = $db->table('users')->insertGetId(['email' => 'admin@example.test', 'role' => 'admin']);
        $db->table('users')->insert(['email' => 'user@example.test', 'role' => 'user']);

        $rows = $db->table('users')
            ->select(['id', 'email'])
            ->where('role', '=', 'admin')
            ->orWhere('email', '=', 'user@example.test')
            ->orderBy('id')
            ->get();

        self::assertCount(2, $rows);
        self::assertSame('admin@example.test', $db->table('users')->find($adminId)['email'] ?? null);
    }

    public function testDatabaseExceptionsAndQueryLogging(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $logger = new QueryLoggerSpy();
        $db = Connection::make('sqlite::memory:', logger: $logger, logQueries: true);
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');

        self::assertSame('database.query', $logger->messages[0] ?? null);

        $this->expectException(DatabaseException::class);
        $db->select('SELECT * FROM missing_table');
    }
}

final class QueryLoggerSpy implements LoggerInterface
{
    /** @var list<string> */
    public array $messages = [];

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->messages[] = $message;
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->messages[] = $message;
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->messages[] = $message;
    }
}
