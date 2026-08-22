<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Cli;

use Application\Cli\CliApplication;
use PHPUnit\Framework\TestCase;

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

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string, string}
     */
    private function executeCli(array $arguments): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = (new CliApplication())->run($arguments, $stdout, $stderr);
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
