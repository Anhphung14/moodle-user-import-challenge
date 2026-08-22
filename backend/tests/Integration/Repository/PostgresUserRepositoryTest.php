<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Repository;

use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ValidatedUserRecord;
use Application\Repository\PostgresUserRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgresUserRepositoryTest extends TestCase
{
    private PDO $connection;

    private PostgresUserRepository $repository;

    protected function setUp(): void
    {
        if (!isset($_ENV['DATABASE_URL']) || $_ENV['DATABASE_URL'] === '') {
            self::markTestSkipped('DATABASE_URL is not configured.');
        }

        $this->connection = (new ConnectionFactory())->create();
        $this->connection->beginTransaction();
        (new SchemaManager($this->connection))->rebuild();
        $this->repository = new PostgresUserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testItInsertsUsersAndStoresTheirValues(): void
    {
        $users = [
            $this->user(2, "Robert'); DROP TABLE users; --", 'Smith', 'robert@example.com'),
            $this->user(3, 'Jane', 'Doe', 'jane@example.com'),
        ];

        self::assertSame(2, $this->repository->insertUsers($users));

        $rows = $this->connection
            ->query('SELECT name, surname, email FROM users ORDER BY email')
            ->fetchAll();

        self::assertSame([
            ['name' => 'Jane', 'surname' => 'Doe', 'email' => 'jane@example.com'],
            [
                'name' => "Robert'); DROP TABLE users; --",
                'surname' => 'Smith',
                'email' => 'robert@example.com',
            ],
        ], $rows);
    }

    public function testItFindsExistingEmailsUsingNormalisedLookupSet(): void
    {
        $this->repository->insertUsers([
            $this->user(2, 'John', 'Smith', 'john@example.com'),
            $this->user(3, 'Jane', 'Doe', 'jane@example.com'),
        ]);

        $existing = $this->repository->findExistingEmails([
            ' JOHN@EXAMPLE.COM ',
            'jane@example.com',
            'new@example.com',
            'john@example.com',
        ]);

        self::assertSame([
            'john@example.com' => true,
            'jane@example.com' => true,
        ], $existing);
    }

    public function testEmptyLookupReturnsWithoutQueryFailure(): void
    {
        self::assertSame([], $this->repository->findExistingEmails([]));
    }

    public function testDatabaseUniqueConstraintRejectsDuplicateEmail(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionCode('23505');

        $this->repository->insertUsers([
            $this->user(2, 'John', 'Smith', 'john@example.com'),
            $this->user(3, 'Jane', 'Doe', 'john@example.com'),
        ]);
    }

    public function testLookupAndInsertAreChunkedForLargeInputs(): void
    {
        $users = [];
        $emails = [];

        for ($index = 0; $index < 501; ++$index) {
            $email = sprintf('user%03d@example.com', $index);
            $users[] = $this->user($index + 2, 'User', (string) $index, $email);
            $emails[] = $email;
        }

        self::assertSame(501, $this->repository->insertUsers($users));
        self::assertCount(501, $this->repository->findExistingEmails($emails));
    }

    private function user(
        int $rowNumber,
        string $name,
        string $surname,
        string $email,
    ): ValidatedUserRecord {
        return new ValidatedUserRecord($rowNumber, $name, $surname, $email);
    }
}
