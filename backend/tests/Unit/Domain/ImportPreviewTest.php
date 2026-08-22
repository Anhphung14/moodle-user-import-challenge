<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Domain;

use Application\Domain\ImportPreview;
use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use PHPUnit\Framework\TestCase;

final class ImportPreviewTest extends TestCase
{
    public function testItCalculatesCountsAndCollectsErrors(): void
    {
        $firstError = new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.');
        $secondError = new ValidationError(4, 'name', 'required', 'Name is required.');
        $preview = new ImportPreview([
            new ValidatedUserRecord(2, 'John', 'Smith', 'john@example.com'),
            new ValidatedUserRecord(3, 'Jane', 'Doe', 'invalid', [$firstError]),
            new ValidatedUserRecord(4, '', 'Jones', 'sam@example.com', [$secondError]),
        ]);

        self::assertSame(3, $preview->totalCount());
        self::assertSame(1, $preview->validCount());
        self::assertSame(2, $preview->invalidCount());
        self::assertSame([$firstError, $secondError], $preview->errors());
    }

    public function testEmptyPreviewHasZeroCountsAndNoErrors(): void
    {
        $preview = new ImportPreview([]);

        self::assertSame(0, $preview->totalCount());
        self::assertSame(0, $preview->validCount());
        self::assertSame(0, $preview->invalidCount());
        self::assertSame([], $preview->errors());
    }
}
