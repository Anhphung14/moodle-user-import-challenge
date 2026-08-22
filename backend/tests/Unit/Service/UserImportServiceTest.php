<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Csv\CsvUserParser;
use Application\Domain\ValidatedUserRecord;
use Application\Repository\UserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserImportServiceTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testPreviewReturnsNormalisedSummaryForValidFile(): void
    {
        $repository = new PreviewUserRepository();
        $preview = $this->service($repository)->preview($this->csv(<<<'CSV'
            name,surname,email
             john , smith ,JOHN@EXAMPLE.COM
            jane,doe,JANE@EXAMPLE.COM
            CSV));

        self::assertSame(2, $preview->totalCount());
        self::assertSame(2, $preview->validCount());
        self::assertSame(0, $preview->invalidCount());
        self::assertSame('John', $preview->records[0]->name);
        self::assertSame('Smith', $preview->records[0]->surname);
        self::assertSame('john@example.com', $preview->records[0]->email);
        self::assertSame(['john@example.com', 'jane@example.com'], $repository->lookups[0]);
        self::assertSame(0, $repository->insertCalls);
    }

    public function testPreviewReturnsCorrectCountsForMixedFile(): void
    {
        $preview = $this->service(new PreviewUserRepository())->preview($this->csv(<<<'CSV'
            name,surname,email
            john,smith,john@example.com
            ,doe,jane@example.com
            invalid,email,not-an-email
            john,again,JOHN@EXAMPLE.COM
            CSV));

        self::assertSame(4, $preview->totalCount());
        self::assertSame(1, $preview->validCount());
        self::assertSame(3, $preview->invalidCount());
        self::assertCount(3, $preview->errors());
        self::assertSame(
            ['required', 'invalid_email', 'duplicate_email_in_file'],
            array_map(static fn($error): string => $error->code, $preview->errors()),
        );
    }

    public function testPreviewHandlesFileWhereEveryRecordIsInvalid(): void
    {
        $preview = $this->service(new PreviewUserRepository())->preview($this->csv(<<<'CSV'
            name,surname,email
            ,,invalid
            john,,john@example.com@example.com
            CSV));

        self::assertSame(2, $preview->totalCount());
        self::assertSame(0, $preview->validCount());
        self::assertSame(2, $preview->invalidCount());
    }

    public function testPreviewIncludesDatabaseDuplicateErrorsWithoutInserting(): void
    {
        $repository = new PreviewUserRepository(['john@example.com' => true]);
        $preview = $this->service($repository)->preview($this->csv(<<<'CSV'
            name,surname,email
            john,smith,john@example.com
            jane,doe,jane@example.com
            CSV));

        self::assertSame(1, $preview->validCount());
        self::assertSame(1, $preview->invalidCount());
        self::assertSame('duplicate_email_in_database', $preview->records[0]->errors[0]->code);
        self::assertSame(0, $repository->insertCalls);
    }

    public function testImportInsertsOnlyValidRecordsAndReturnsCounts(): void
    {
        $repository = new PreviewUserRepository();
        $connection = new TransactionTrackingPdo();
        $result = $this->service($repository, $connection)->import($this->csv(<<<'CSV'
            name,surname,email
            john,smith,JOHN@EXAMPLE.COM
            ,doe,jane@example.com
            invalid,email,not-an-email
            CSV));

        self::assertSame(1, $result->importedCount);
        self::assertSame(2, $result->rejectedCount);
        self::assertSame(3, $result->totalCount());
        self::assertCount(2, $result->errors);
        self::assertSame(['john@example.com'], array_map(
            static fn(ValidatedUserRecord $record): string => $record->email,
            $repository->insertedUsers,
        ));
        self::assertSame(1, $connection->beginCalls);
        self::assertSame(1, $connection->commitCalls);
        self::assertSame(0, $connection->rollbackCalls);
    }

    public function testImportWithNoValidRecordsDoesNotCallInsert(): void
    {
        $repository = new PreviewUserRepository();
        $result = $this->service($repository, new TransactionTrackingPdo())->import(
            $this->csv("name,surname,email\n,,invalid"),
        );

        self::assertSame(0, $result->importedCount);
        self::assertSame(1, $result->rejectedCount);
        self::assertSame(0, $repository->insertCalls);
    }

    public function testDatabaseFailureRollsBackAndIsRethrown(): void
    {
        $repository = new PreviewUserRepository();
        $repository->insertException = new RuntimeException('Database failed.');
        $connection = new TransactionTrackingPdo();

        try {
            $this->service($repository, $connection)->import(
                $this->csv("name,surname,email\njohn,smith,john@example.com"),
            );
            self::fail('The database failure was expected to be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Database failed.', $exception->getMessage());
            self::assertSame(1, $connection->beginCalls);
            self::assertSame(0, $connection->commitCalls);
            self::assertSame(1, $connection->rollbackCalls);
        }
    }

    public function testUniqueRaceIsRepreviewedOnceAndRemainingUsersAreImported(): void
    {
        $repository = new PreviewUserRepository();
        $repository->raceEmail = 'john@example.com';
        $connection = new TransactionTrackingPdo();
        $result = $this->service($repository, $connection)->import($this->csv(<<<'CSV'
            name,surname,email
            john,smith,john@example.com
            jane,doe,jane@example.com
            CSV));

        self::assertSame(1, $result->importedCount);
        self::assertSame(1, $result->rejectedCount);
        self::assertSame('duplicate_email_in_database', $result->errors[0]->code);
        self::assertSame(['jane@example.com'], array_map(
            static fn(ValidatedUserRecord $record): string => $record->email,
            $repository->insertedUsers,
        ));
        self::assertSame(2, $connection->beginCalls);
        self::assertSame(1, $connection->commitCalls);
        self::assertSame(1, $connection->rollbackCalls);
    }

    private function service(
        UserRepository $repository,
        ?PDO $connection = null,
    ): UserImportService {
        return new UserImportService(
            new CsvUserParser(),
            new UserNormalizer(),
            new UserValidator(),
            new DuplicateEmailDetector(),
            new DatabaseDuplicateEmailDetector($repository),
            $repository,
            $connection ?? $this->createStub(PDO::class),
        );
    }

    private function csv(string $contents): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'moodle-preview-');
        self::assertNotFalse($filePath);
        $this->temporaryFiles[] = $filePath;
        self::assertNotFalse(file_put_contents($filePath, $contents . "\n"));

        return $filePath;
    }
}

final class PreviewUserRepository implements UserRepository
{
    /** @var list<list<string>> */
    public array $lookups = [];

    public int $insertCalls = 0;

    /** @var list<ValidatedUserRecord> */
    public array $insertedUsers = [];

    public ?RuntimeException $insertException = null;

    public ?string $raceEmail = null;

    private bool $raceTriggered = false;

    /**
     * @param array<string, true> $existingEmails
     */
    public function __construct(private array $existingEmails = []) {}

    public function findExistingEmails(iterable $emails): array
    {
        $lookup = [];

        foreach ($emails as $email) {
            $lookup[] = $email;
        }

        $this->lookups[] = $lookup;

        return $this->existingEmails;
    }

    public function insertUsers(iterable $users): int
    {
        ++$this->insertCalls;

        if ($this->insertException !== null) {
            throw $this->insertException;
        }

        if ($this->raceEmail !== null && !$this->raceTriggered) {
            $this->raceTriggered = true;
            $this->existingEmails[$this->raceEmail] = true;

            throw new PDOException('Unique violation.', 23505);
        }

        $this->insertedUsers = [];

        foreach ($users as $user) {
            $this->insertedUsers[] = $user;
        }

        return count($this->insertedUsers);
    }
}

final class TransactionTrackingPdo extends PDO
{
    public int $beginCalls = 0;

    public int $commitCalls = 0;

    public int $rollbackCalls = 0;

    private bool $activeTransaction = false;

    public function __construct() {}

    public function beginTransaction(): bool
    {
        ++$this->beginCalls;
        $this->activeTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        ++$this->commitCalls;
        $this->activeTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        ++$this->rollbackCalls;
        $this->activeTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->activeTransaction;
    }
}
