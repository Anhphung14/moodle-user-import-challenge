<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Service;

use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ValidatedUserRecord;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class DatabaseDuplicateEmailDetectorTest extends TestCase
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
        $this->repository->insertUsers([
            new ValidatedUserRecord(1, 'Existing', 'User', 'existing@example.com'),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testItMarksOnlyEmailsAlreadyStoredInPostgreSql(): void
    {
        $records = (new DatabaseDuplicateEmailDetector($this->repository))->detect([
            new ValidatedUserRecord(2, 'Existing', 'Again', 'existing@example.com'),
            new ValidatedUserRecord(3, 'New', 'User', 'new@example.com'),
        ]);

        self::assertSame(
            DatabaseDuplicateEmailDetector::DUPLICATE_EMAIL_IN_DATABASE,
            $records[0]->errors[0]->code,
        );
        self::assertTrue($records[1]->isValid());
    }
}
