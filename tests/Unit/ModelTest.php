<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Database\Connection;
use Panulat\Database\Model;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testModelProvidesExplicitCrudHelpers(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for this test.');
        }

        $db = Connection::make('sqlite::memory:');
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)');
        $model = new readonly class ($db) extends Model {
            protected function table(): string
            {
                return 'users';
            }

            protected function columns(): array
            {
                return ['id', 'name', 'email'];
            }

            protected function fillable(): array
            {
                return ['name', 'email'];
            }
        };

        $created = $model->create(['name' => 'Avelino', 'email' => 'avelino@example.test', 'ignored' => true]);

        self::assertSame('Avelino', $created['name'] ?? null);
        self::assertSame(1, $model->count());
        self::assertSame('avelino@example.test', $model->find((int) $created['id'])['email'] ?? null);
    }
}
