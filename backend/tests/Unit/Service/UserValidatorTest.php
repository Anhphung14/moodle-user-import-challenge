<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Domain\UserRecord;
use Application\Domain\ValidationError;
use Application\Service\UserValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    public function testValidRecordHasNoErrors(): void
    {
        $record = new UserRecord(2, 'John', 'Smith', 'john@example.com');

        $validated = (new UserValidator())->validate($record);

        self::assertTrue($validated->isValid());
        self::assertSame(2, $validated->rowNumber);
        self::assertSame('John', $validated->name);
        self::assertSame('Smith', $validated->surname);
        self::assertSame('john@example.com', $validated->email);
        self::assertSame([], $validated->errors);
    }

    #[DataProvider('missingFields')]
    public function testRequiredFieldIsReported(
        UserRecord $record,
        string $field,
        string $message,
    ): void {
        $validated = (new UserValidator())->validate($record);

        self::assertFalse($validated->isValid());
        self::assertEquals(
            [new ValidationError($record->rowNumber, $field, UserValidator::REQUIRED, $message)],
            $validated->errors,
        );
    }

    /**
     * @return iterable<string, array{UserRecord, string, string}>
     */
    public static function missingFields(): iterable
    {
        yield 'name' => [
            new UserRecord(3, '', 'Smith', 'john@example.com'),
            'name',
            'Name is required.',
        ];
        yield 'surname' => [
            new UserRecord(4, 'John', '  ', 'john@example.com'),
            'surname',
            'Surname is required.',
        ];
        yield 'email' => [
            new UserRecord(5, 'John', 'Smith', ''),
            'email',
            'Email is required.',
        ];
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailIsReported(string $email): void
    {
        $record = new UserRecord(7, 'John', 'Smith', $email);

        $validated = (new UserValidator())->validate($record);

        self::assertEquals(
            [
                new ValidationError(
                    7,
                    'email',
                    UserValidator::INVALID_EMAIL,
                    'Email must be a valid email address.',
                ),
            ],
            $validated->errors,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'missing domain' => ['john@'];
        yield 'missing at sign' => ['john.example.com'];
        yield 'two at signs' => ['john@example.com@example.com'];
        yield 'spaces' => ['john smith@example.com'];
    }

    public function testAllMissingFieldsProduceThreeErrorsWithoutDuplicateEmailError(): void
    {
        $validated = (new UserValidator())->validate(new UserRecord(9, '', '', ''));

        self::assertFalse($validated->isValid());
        self::assertSame(
            ['name', 'surname', 'email'],
            array_map(
                static fn(ValidationError $error): string => $error->field,
                $validated->errors,
            ),
        );
        self::assertSame(
            [UserValidator::REQUIRED, UserValidator::REQUIRED, UserValidator::REQUIRED],
            array_map(
                static fn(ValidationError $error): string => $error->code,
                $validated->errors,
            ),
        );
    }

    public function testRecordCanContainMultipleDifferentErrors(): void
    {
        $validated = (new UserValidator())->validate(
            new UserRecord(12, '', 'Smith', 'john@example.com@example.com'),
        );

        self::assertFalse($validated->isValid());
        self::assertSame(['name', 'email'], array_map(
            static fn(ValidationError $error): string => $error->field,
            $validated->errors,
        ));
        self::assertSame([12, 12], array_map(
            static fn(ValidationError $error): int => $error->rowNumber,
            $validated->errors,
        ));
    }
}
