<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Database;

use Application\Database\ConnectionFactory;
use Application\Database\Exception\DatabaseConnectionException;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class ConnectionFactoryTest extends TestCase
{
    public function testItConnectsToConfiguredPostgreSqlDatabase(): void
    {
        if (!isset($_ENV['DATABASE_URL']) || $_ENV['DATABASE_URL'] === '') {
            self::markTestSkipped('DATABASE_URL is not configured.');
        }

        $connection = (new ConnectionFactory())->create();

        self::assertSame('pgsql', $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        self::assertSame(1, $connection->query('SELECT 1')->fetchColumn());
    }

    public function testConnectionFailureUsesSafeErrorMessage(): void
    {
        $databaseUrl = 'postgresql://sensitive-user:sensitive-password@127.0.0.1:1/sensitive-database';

        try {
            (new ConnectionFactory())->create(['DATABASE_URL' => $databaseUrl]);
            self::fail('The connection was expected to fail.');
        } catch (DatabaseConnectionException $exception) {
            self::assertSame(
                'Unable to connect to the PostgreSQL database.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }
}
