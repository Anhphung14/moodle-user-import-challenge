<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Database;

use Application\Database\ConnectionFactory;
use Application\Database\Exception\DatabaseConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testDatabaseUrlIsRequired(): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        $this->expectExceptionMessage('The DATABASE_URL environment variable is required.');

        (new ConnectionFactory())->create([]);
    }

    #[DataProvider('invalidDatabaseUrls')]
    public function testDatabaseUrlMustContainRequiredPostgreSqlParts(string $databaseUrl): void
    {
        $this->expectException(DatabaseConfigurationException::class);
        $this->expectExceptionMessage('DATABASE_URL must be a valid PostgreSQL connection string.');

        (new ConnectionFactory())->create(['DATABASE_URL' => $databaseUrl]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDatabaseUrls(): iterable
    {
        yield 'malformed' => ['not-a-url'];
        yield 'wrong scheme' => ['mysql://user:password@127.0.0.1/database'];
        yield 'missing host' => ['postgresql:///database'];
        yield 'missing user' => ['postgresql://127.0.0.1/database'];
        yield 'missing database' => ['postgresql://user@127.0.0.1'];
    }
}
