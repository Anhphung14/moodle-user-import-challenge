<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Repository;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use Application\Repository\PostgresUserRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class PostgresUserRepositoryTest extends TestCase
{
    public function testInvalidRecordsCannotBeInserted(): void
    {
        $connection = $this->createStub(PDO::class);
        $repository = new PostgresUserRepository($connection);
        $record = new ValidatedUserRecord(
            2,
            'John',
            'Smith',
            'invalid-email',
            [new ValidationError(2, 'email', 'invalid_email', 'Email is invalid.')],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only valid user records can be inserted.');

        $repository->insertUsers([$record]);
    }
}
