<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Cli;

use Application\Cli\CliApplication;
use Application\Csv\Exception\CsvParsingException;
use Application\Domain\ImportPreview;
use Application\Domain\ImportResult;
use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CliApplicationTest extends TestCase
{
    public function testImportDisplaysImportedRejectedAndValidationErrors(): void
    {
        $receivedFile = null;
        $result = new ImportResult(
            1,
            1,
            [new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.')],
        );
        $application = new CliApplication(
            importUsers: static function (string $filePath) use ($result, &$receivedFile): ImportResult {
                $receivedFile = $filePath;

                return $result;
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'users.csv'],
            $application,
        );

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertSame('users.csv', $receivedFile);
        self::assertStringContainsString("Users found: 2\n", $stdout);
        self::assertStringContainsString("Imported: 1\n", $stdout);
        self::assertStringContainsString("Rejected: 1\n", $stdout);
        self::assertStringContainsString('Row 3 [email] invalid_email: Email is invalid.', $stdout);
        self::assertSame('', $stderr);
    }

    public function testImportFileErrorIsWrittenToStderr(): void
    {
        $application = new CliApplication(
            importUsers: static function (): never {
                throw new CsvParsingException('CSV file does not exist or is not readable.');
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'missing.csv'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: CSV file does not exist or is not readable.\n", $stderr);
    }

    public function testImportUnexpectedErrorDoesNotExposeTechnicalDetails(): void
    {
        $application = new CliApplication(
            importUsers: static function (): never {
                throw new RuntimeException('password=secret; SQL details');
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'users.csv'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: Unable to import the CSV file. No users were imported.\n", $stderr);
    }

    public function testImportWithoutConfiguredHandlerReturnsRuntimeError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli([
            'user_upload.php',
            '--file',
            'users.csv',
        ]);

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: CSV import is not configured.\n", $stderr);
    }

    public function testHelpWritesUsageToStdoutAndReturnsSuccess(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli(['user_upload.php', '--help']);

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertStringContainsString('Moodle User Import', $stdout);
        self::assertStringContainsString('--file <filename>', $stdout);
        self::assertSame('', $stderr);
    }

    public function testInvalidArgumentsWriteErrorToStderrAndReturnTwo(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli(['user_upload.php', '--unknown']);

        self::assertSame(CliApplication::INVALID_ARGUMENTS, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Error: Unknown argument: --unknown', $stderr);
        self::assertStringContainsString('Usage:', $stderr);
    }

    public function testCreateTableRunsRebuildAndReturnsSuccess(): void
    {
        $calls = 0;
        $application = new CliApplication(
            rebuildUsersTable: static function () use (&$calls): void {
                ++$calls;
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--create-table'],
            $application,
        );

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertSame(1, $calls);
        self::assertSame("Users table rebuilt successfully.\n", $stdout);
        self::assertStringContainsString('deletes all existing users', $stderr);
    }

    public function testCreateTableFailureReturnsRuntimeErrorWithoutTechnicalDetails(): void
    {
        $application = new CliApplication(
            rebuildUsersTable: static function (): never {
                throw new RuntimeException('password=secret; SQL details');
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--create-table'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Unable to rebuild the users table.', $stderr);
        self::assertStringNotContainsString('secret', $stderr);
        self::assertStringNotContainsString('SQL', $stderr);
    }

    public function testCreateTableWithoutConfiguredHandlerReturnsRuntimeError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli([
            'user_upload.php',
            '--create-table',
        ]);

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('rebuild is not configured', $stderr);
    }

    public function testDryRunDisplaysSummaryErrorsAndWouldImportCount(): void
    {
        $preview = new ImportPreview([
            new ValidatedUserRecord(2, 'John', 'Smith', 'john@example.com'),
            new ValidatedUserRecord(
                3,
                'Jane',
                'Doe',
                'invalid-email',
                [new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.')],
            ),
        ]);
        $receivedFile = null;
        $application = new CliApplication(
            previewUsers: static function (string $filePath) use ($preview, &$receivedFile): ImportPreview {
                $receivedFile = $filePath;

                return $preview;
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'users.csv', '--dry-run'],
            $application,
        );

        self::assertSame(CliApplication::SUCCESS, $exitCode);
        self::assertSame('users.csv', $receivedFile);
        self::assertStringContainsString("Users found: 2\n", $stdout);
        self::assertStringContainsString("Valid: 1\n", $stdout);
        self::assertStringContainsString("Invalid: 1\n", $stdout);
        self::assertStringContainsString('Row 3 [email] invalid_email: Email is invalid.', $stdout);
        self::assertStringContainsString("Would import 1 users.\n", $stdout);
        self::assertSame('', $stderr);
    }

    public function testDryRunFileErrorIsWrittenToStderr(): void
    {
        $application = new CliApplication(
            previewUsers: static function (): never {
                throw new CsvParsingException('CSV file does not exist or is not readable.');
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'missing.csv', '--dry-run'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: CSV file does not exist or is not readable.\n", $stderr);
    }

    public function testDryRunUnexpectedErrorDoesNotExposeTechnicalDetails(): void
    {
        $application = new CliApplication(
            previewUsers: static function (): never {
                throw new RuntimeException('password=secret; SQL details');
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'users.csv', '--dry-run'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: Unable to preview the CSV file.\n", $stderr);
    }

    public function testDryRunMissingUsersTableProvidesActionableError(): void
    {
        $application = new CliApplication(
            previewUsers: static function (): never {
                $exception = new PDOException('relation users does not exist');
                $exception->errorInfo = ['42P01'];

                throw $exception;
            },
        );

        [$exitCode, $stdout, $stderr] = $this->executeCli(
            ['user_upload.php', '--file', 'users.csv', '--dry-run'],
            $application,
        );

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame(
            "Error: The users table does not exist. Run --create-table first.\n",
            $stderr,
        );
    }

    public function testDryRunWithoutConfiguredHandlerReturnsRuntimeError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli([
            'user_upload.php',
            '--file',
            'users.csv',
            '--dry-run',
        ]);

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: CSV dry-run is not configured.\n", $stderr);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string, string}
     */
    private function executeCli(
        array $arguments,
        ?CliApplication $application = null,
    ): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = ($application ?? new CliApplication())->run($arguments, $stdout, $stderr);
        rewind($stdout);
        rewind($stderr);
        $stdoutContents = stream_get_contents($stdout);
        $stderrContents = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);

        self::assertIsString($stdoutContents);
        self::assertIsString($stderrContents);

        return [$exitCode, $stdoutContents, $stderrContents];
    }
}
