<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Domain;

use Application\Domain\ImportResult;
use Application\Domain\ValidationError;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportResultTest extends TestCase
{
    public function testItKeepsCountsAndValidationErrors(): void
    {
        $error = new ValidationError(4, 'email', 'invalid_email', 'Email is invalid.');
        $result = new ImportResult(3, 1, [$error]);

        self::assertSame(3, $result->importedCount);
        self::assertSame(1, $result->rejectedCount);
        self::assertSame(4, $result->totalCount());
        self::assertSame([$error], $result->errors);
    }

    public function testCountsMustNotBeNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImportResult(-1, 0);
    }
}
