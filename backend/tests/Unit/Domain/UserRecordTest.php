<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Domain;

use Application\Domain\UserRecord;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserRecordTest extends TestCase
{
    public function testItKeepsSourceRowAndRawValues(): void
    {
        $record = new UserRecord(2, ' john ', ' smith ', 'JOHN@example.com');

        self::assertSame(2, $record->rowNumber);
        self::assertSame(' john ', $record->name);
        self::assertSame(' smith ', $record->surname);
        self::assertSame('JOHN@example.com', $record->email);
    }

    public function testRowNumberMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UserRecord(0, 'John', 'Smith', 'john@example.com');
    }
}
