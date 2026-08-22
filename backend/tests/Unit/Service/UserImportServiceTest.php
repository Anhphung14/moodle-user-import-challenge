<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Csv\CsvUserParser;
use Application\Repository\UserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use PHPUnit\Framework\TestCase;

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
            array_map(static fn ($error): string => $error->code, $preview->errors()),
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

    private function service(UserRepository $repository): UserImportService
    {
        return new UserImportService(
            new CsvUserParser(),
            new UserNormalizer(),
            new UserValidator(),
            new DuplicateEmailDetector(),
            new DatabaseDuplicateEmailDetector($repository),
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

    /**
     * @param array<string, true> $existingEmails
     */
    public function __construct(private readonly array $existingEmails = [])
    {
    }

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

        return 0;
    }
}
