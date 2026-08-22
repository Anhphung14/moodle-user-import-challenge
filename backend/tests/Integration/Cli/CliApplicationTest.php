<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Cli;

use Application\Cli\CliApplication;
use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ImportPreview;
use Application\Domain\ImportResult;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class CliApplicationTest extends TestCase
{
    private PDO $connection;

    private PostgresUserRepository $repository;

    /** @var list<string> */
    private array $temporaryFiles = [];

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
        $this->repository = new PostgresUserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

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

    public function testDryRunReportsRecordsWithoutChangingDatabase(): void
    {
        $filePath = $this->csv(<<<'CSV'
            name,surname,email
            new,user,NEW@EXAMPLE.COM
            existing,user,existing@example.com
            invalid,user,not-an-email
            CSV);
        $countBefore = $this->userCount();
        $application = new CliApplication(
            previewUsers: fn (string $path): ImportPreview => $this->service()->preview($path),
        );
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = $application->run(
            ['user_upload.php', '--file', $filePath, '--dry-run'],
            $stdout,
            $stderr,
        );
        rewind($stdout);
        $output = stream_get_contents($stdout);

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertIsString($output);
        self::assertStringContainsString("Users found: 3\n", $output);
        self::assertStringContainsString("Valid: 1\n", $output);
        self::assertStringContainsString("Invalid: 2\n", $output);
        self::assertStringContainsString("Would import 1 users.\n", $output);
        self::assertSame($countBefore, $this->userCount());
        fclose($stdout);
        fclose($stderr);
    }

    public function testImportPersistsOnlyValidRecordsAndReportsRejectedRecords(): void
    {
        $filePath = $this->csv(<<<'CSV'
            name,surname,email
            new,user,NEW@EXAMPLE.COM
            existing,user,existing@example.com
            invalid,user,not-an-email
            CSV);
        $application = new CliApplication(
            importUsers: fn (string $path): ImportResult => $this->service()->import($path),
        );
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = $application->run(
            ['user_upload.php', '--file', $filePath],
            $stdout,
            $stderr,
        );
        rewind($stdout);
        rewind($stderr);
        $output = stream_get_contents($stdout);
        $errorOutput = stream_get_contents($stderr);

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertIsString($output);
        self::assertSame('', $errorOutput);
        self::assertStringContainsString("Users found: 3\n", $output);
        self::assertStringContainsString("Imported: 1\n", $output);
        self::assertStringContainsString("Rejected: 2\n", $output);
        self::assertSame(2, $this->userCount());
        self::assertSame(
            1,
            (int) $this->connection
                ->query("SELECT COUNT(*) FROM users WHERE email = 'new@example.com'")
                ->fetchColumn(),
        );
        fclose($stdout);
        fclose($stderr);
    }

    private function service(): UserImportService
    {
        return new UserImportService(
            new CsvUserParser(),
            new UserNormalizer(),
            new UserValidator(),
            new DuplicateEmailDetector(),
            new DatabaseDuplicateEmailDetector($this->repository),
            $this->repository,
            $this->connection,
        );
    }

    private function csv(string $contents): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'moodle-cli-dry-run-');
        self::assertNotFalse($filePath);
        $this->temporaryFiles[] = $filePath;
        self::assertNotFalse(file_put_contents($filePath, $contents . "\n"));

        return $filePath;
    }

    private function userCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
