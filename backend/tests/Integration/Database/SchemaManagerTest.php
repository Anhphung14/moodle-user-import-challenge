<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Database;

use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class SchemaManagerTest extends TestCase
{
    private PDO $connection;

    private SchemaManager $manager;

    protected function setUp(): void
    {
        if (!isset($_ENV['DATABASE_URL']) || $_ENV['DATABASE_URL'] === '') {
            self::markTestSkipped('DATABASE_URL is not configured.');
        }

        $this->connection = (new ConnectionFactory())->create();
        $this->connection->beginTransaction();
        $this->manager = new SchemaManager($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testCreateIsRepeatableAndCreatesRequiredColumns(): void
    {
        $this->manager->rebuild();
        $this->manager->create();
        $this->manager->create();

        $statement = $this->connection->query(<<<'SQL'
            SELECT column_name, is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'users'
            ORDER BY ordinal_position
            SQL);

        self::assertSame(
            [
                ['column_name' => 'id', 'is_nullable' => 'NO'],
                ['column_name' => 'name', 'is_nullable' => 'NO'],
                ['column_name' => 'surname', 'is_nullable' => 'NO'],
                ['column_name' => 'email', 'is_nullable' => 'NO'],
                ['column_name' => 'created_at', 'is_nullable' => 'NO'],
            ],
            $statement->fetchAll(),
        );
    }

    public function testRebuildRemovesExistingUsers(): void
    {
        $this->manager->rebuild();
        $this->insertUser('john@example.com');

        $this->manager->rebuild();

        self::assertSame(0, $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testDatabaseRejectsDuplicateEmail(): void
    {
        $this->manager->rebuild();
        $this->insertUser('john@example.com');

        $this->expectException(PDOException::class);
        $this->expectExceptionCode('23505');

        $this->insertUser('john@example.com');
    }

    public function testDatabaseRejectsNonNormalisedEmail(): void
    {
        $this->manager->rebuild();

        $this->expectException(PDOException::class);
        $this->expectExceptionCode('23514');

        $this->insertUser('JOHN@example.com');
    }

    private function insertUser(string $email): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)',
        );
        $statement->execute([
            'name' => 'John',
            'surname' => 'Smith',
            'email' => $email,
        ]);
    }
}
