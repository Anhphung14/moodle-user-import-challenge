<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use Application\Repository\UserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use PHPUnit\Framework\TestCase;

final class DatabaseDuplicateEmailDetectorTest extends TestCase
{
    public function testNewEmailRemainsUnchanged(): void
    {
        $record = $this->record(2, 'new@example.com');
        $repository = new RecordingUserRepository([]);

        $records = (new DatabaseDuplicateEmailDetector($repository))->detect([$record]);

        self::assertSame([$record], $records);
        self::assertSame([['new@example.com']], $repository->lookups);
    }

    public function testExistingEmailIsMarkedInvalidAfterNormalisation(): void
    {
        $repository = new RecordingUserRepository(['john@example.com' => true]);

        $records = (new DatabaseDuplicateEmailDetector($repository))->detect([
            $this->record(4, ' JOHN@EXAMPLE.COM '),
        ]);

        self::assertFalse($records[0]->isValid());
        self::assertEquals([
            new ValidationError(
                4,
                'email',
                DatabaseDuplicateEmailDetector::DUPLICATE_EMAIL_IN_DATABASE,
                'Email already exists in the database.',
            ),
        ], $records[0]->errors);
    }

    public function testFileAndDatabaseDuplicatesAreCombinedWithoutNoisyExtraErrors(): void
    {
        $fileDuplicate = new ValidationError(
            6,
            'email',
            DuplicateEmailDetector::DUPLICATE_EMAIL_IN_FILE,
            'Email duplicates row 5 in the CSV file.',
        );
        $repository = new RecordingUserRepository(['john@example.com' => true]);

        $records = (new DatabaseDuplicateEmailDetector($repository))->detect([
            $this->record(5, 'john@example.com'),
            $this->record(6, 'john@example.com', [$fileDuplicate]),
        ]);

        self::assertSame(
            DatabaseDuplicateEmailDetector::DUPLICATE_EMAIL_IN_DATABASE,
            $records[0]->errors[0]->code,
        );
        self::assertSame([$fileDuplicate], $records[1]->errors);
        self::assertSame([['john@example.com']], $repository->lookups);
    }

    public function testInvalidEmailsAreNotLookedUp(): void
    {
        $emailError = new ValidationError(7, 'email', 'invalid_email', 'Email is invalid.');
        $repository = new RecordingUserRepository([]);
        $record = $this->record(7, 'invalid-email', [$emailError]);

        $records = (new DatabaseDuplicateEmailDetector($repository))->detect([$record]);

        self::assertSame([$record], $records);
        self::assertSame([], $repository->lookups);
    }

    public function testAllEligibleEmailsAreLookedUpInOneRepositoryCall(): void
    {
        $repository = new RecordingUserRepository([]);
        $records = [];

        for ($index = 0; $index < 1_001; ++$index) {
            $records[] = $this->record(
                $index + 2,
                sprintf('user%04d@example.com', $index),
            );
        }

        (new DatabaseDuplicateEmailDetector($repository))->detect($records);

        self::assertCount(1, $repository->lookups);
        self::assertCount(1_001, $repository->lookups[0]);
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function record(int $rowNumber, string $email, array $errors = []): ValidatedUserRecord
    {
        return new ValidatedUserRecord($rowNumber, 'John', 'Smith', $email, $errors);
    }
}

final class RecordingUserRepository implements UserRepository
{
    /** @var list<list<string>> */
    public array $lookups = [];

    /**
     * @param array<string, true> $existingEmails
     */
    public function __construct(private readonly array $existingEmails) {}

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
        return 0;
    }
}
