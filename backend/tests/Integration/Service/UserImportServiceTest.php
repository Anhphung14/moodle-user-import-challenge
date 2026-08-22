<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Service;

use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ValidatedUserRecord;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class UserImportServiceTest extends TestCase
{
    private PDO $connection;

    private PostgresUserRepository $repository;

    /** @var list<string> */
    private array $temporaryFiles = [];

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
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testPreviewDetectsDatabaseDuplicatesWithoutChangingDatabase(): void
    {
        $filePath = $this->csv(<<<'CSV'
            name,surname,email
            existing,user,EXISTING@EXAMPLE.COM
            new,user,NEW@EXAMPLE.COM
            CSV);
        $countBefore = $this->userCount();

        $preview = $this->service()->preview($filePath);

        self::assertSame(2, $preview->totalCount());
        self::assertSame(1, $preview->validCount());
        self::assertSame(1, $preview->invalidCount());
        self::assertSame('duplicate_email_in_database', $preview->records[0]->errors[0]->code);
        self::assertSame('new@example.com', $preview->records[1]->email);
        self::assertSame($countBefore, $this->userCount());
    }

    private function service(): UserImportService
    {
        return new UserImportService(
            new CsvUserParser(),
            new UserNormalizer(),
            new UserValidator(),
            new DuplicateEmailDetector(),
            new DatabaseDuplicateEmailDetector($this->repository),
        );
    }

    private function userCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function csv(string $contents): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'moodle-preview-integration-');
        self::assertNotFalse($filePath);
        $this->temporaryFiles[] = $filePath;
        self::assertNotFalse(file_put_contents($filePath, $contents . "\n"));

        return $filePath;
    }
}
