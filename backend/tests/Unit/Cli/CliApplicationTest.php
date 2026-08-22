<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Cli;

use Application\Cli\CliApplication;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CliApplicationTest extends TestCase
{
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

    public function testValidPendingCommandReturnsRuntimeError(): void
    {
        [$exitCode, $stdout, $stderr] = $this->executeCli([
            'user_upload.php',
            '--file',
            'users.csv',
        ]);

        self::assertSame(CliApplication::RUNTIME_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertSame("Error: Command execution is not available yet.\n", $stderr);
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
