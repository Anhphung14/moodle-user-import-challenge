<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Domain;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValidatedUserRecordTest extends TestCase
{
    public function testRecordWithoutErrorsIsValidAndKeepsNormalisedValues(): void
    {
        $record = new ValidatedUserRecord(2, 'John', 'Smith', 'john@example.com');

        self::assertTrue($record->isValid());
        self::assertSame('John', $record->name);
        self::assertSame('Smith', $record->surname);
        self::assertSame('john@example.com', $record->email);
    }

    public function testRecordCanContainMultipleValidationErrors(): void
    {
        $errors = [
            new ValidationError(3, 'surname', 'required', 'Surname is required.'),
            new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.'),
        ];

        $record = new ValidatedUserRecord(3, 'Jane', '', 'invalid-email', $errors);

        self::assertFalse($record->isValid());
        self::assertSame($errors, $record->errors);
    }

    public function testErrorMustBelongToTheSameSourceRow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValidatedUserRecord(
            3,
            'Jane',
            'Doe',
            'jane@example.com',
            [new ValidationError(4, 'email', 'invalid_email', 'Email is invalid.')],
        );
    }
}
