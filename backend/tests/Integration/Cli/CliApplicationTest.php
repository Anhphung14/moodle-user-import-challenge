<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Cli;

use Application\Cli\CliApplication;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class CliApplicationTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        if (!isset($_ENV['DATABASE_URL']) || $_ENV['DATABASE_URL'] === '') {
            self::markTestSkipped('DATABASE_URL is not configured.');
        }

        $this->connection = (new ConnectionFactory())->create();
        $this->connection->beginTransaction();
        (new SchemaManager($this->connection))->rebuild();
        $this->connection->exec(<<<'SQL'
            INSERT INTO users (name, surname, email)
            VALUES ('Existing', 'User', 'existing@example.com')
            SQL);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testCreateTableRebuildsExistingTable(): void
    {
        $application = new CliApplication(
            rebuildUsersTable: function (): void {
                (new SchemaManager($this->connection))->rebuild();
            },
        );
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = $application->run(
            ['user_upload.php', '--create-table'],
            $stdout,
            $stderr,
        );

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertSame(0, $this->userCount());
        self::assertSame('users', $this->connection->query("SELECT TO_REGCLASS('public.users')")->fetchColumn());
        fclose($stdout);
        fclose($stderr);
    }

    private function userCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
