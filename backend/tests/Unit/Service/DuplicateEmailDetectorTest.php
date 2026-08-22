<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use Application\Service\DuplicateEmailDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateEmailDetectorTest extends TestCase
{
    public function testFileWithoutDuplicatesIsUnchanged(): void
    {
        $records = [
            $this->record(2, 'john@example.com'),
            $this->record(3, 'jane@example.com'),
        ];

        self::assertSame($records, (new DuplicateEmailDetector())->detect($records));
    }

    public function testSecondAndLaterOccurrencesAreMarkedAsDuplicates(): void
    {
        $records = (new DuplicateEmailDetector())->detect([
            $this->record(2, 'john@example.com'),
            $this->record(3, 'john@example.com'),
            $this->record(4, 'john@example.com'),
        ]);

        self::assertTrue($records[0]->isValid());
        $this->assertDuplicateError($records[1], 3, 2);
        $this->assertDuplicateError($records[2], 4, 2);
    }

    public function testComparisonIgnoresCasingAndSurroundingWhitespace(): void
    {
        $records = (new DuplicateEmailDetector())->detect([
            $this->record(5, 'john@example.com'),
            $this->record(6, ' JOHN@EXAMPLE.COM '),
        ]);

        $this->assertDuplicateError($records[1], 6, 5);
    }

    public function testExistingNonEmailErrorsArePreservedAndEmailIsStillReserved(): void
    {
        $nameError = new ValidationError(7, 'name', 'required', 'Name is required.');
        $records = (new DuplicateEmailDetector())->detect([
            $this->record(7, 'john@example.com', [$nameError]),
            $this->record(8, 'john@example.com'),
        ]);

        self::assertSame([$nameError], $records[0]->errors);
        $this->assertDuplicateError($records[1], 8, 7);
    }

    public function testInvalidEmailsDoNotReceiveDuplicateErrors(): void
    {
        $firstError = new ValidationError(9, 'email', 'invalid_email', 'Email is invalid.');
        $secondError = new ValidationError(10, 'email', 'invalid_email', 'Email is invalid.');
        $records = (new DuplicateEmailDetector())->detect([
            $this->record(9, 'invalid-email', [$firstError]),
            $this->record(10, 'invalid-email', [$secondError]),
        ]);

        self::assertSame([$firstError], $records[0]->errors);
        self::assertSame([$secondError], $records[1]->errors);
    }

    public function testItAcceptsAnyIterable(): void
    {
        $records = (static function (): iterable {
            yield new ValidatedUserRecord(2, 'John', 'Smith', 'john@example.com');
            yield new ValidatedUserRecord(3, 'Jane', 'Doe', 'jane@example.com');
        })();

        self::assertCount(2, (new DuplicateEmailDetector())->detect($records));
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function record(int $rowNumber, string $email, array $errors = []): ValidatedUserRecord
    {
        return new ValidatedUserRecord($rowNumber, 'John', 'Smith', $email, $errors);
    }

    private function assertDuplicateError(
        ValidatedUserRecord $record,
        int $expectedRow,
        int $expectedFirstRow,
    ): void {
        self::assertFalse($record->isValid());
        self::assertCount(1, $record->errors);
        self::assertSame($expectedRow, $record->errors[0]->rowNumber);
        self::assertSame('email', $record->errors[0]->field);
        self::assertSame(
            DuplicateEmailDetector::DUPLICATE_EMAIL_IN_FILE,
            $record->errors[0]->code,
        );
        self::assertSame(
            sprintf('Email duplicates row %d in the CSV file.', $expectedFirstRow),
            $record->errors[0]->message,
        );
    }
}
