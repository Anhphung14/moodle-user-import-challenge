<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Cli;

use Application\Cli\CliOptionParser;
use Application\Cli\Exception\InvalidCliArgumentsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CliOptionParserTest extends TestCase
{
    public function testHelpOption(): void
    {
        $options = (new CliOptionParser())->parse(['user_upload.php', '--help']);

        self::assertTrue($options->help);
        self::assertNull($options->file);
        self::assertFalse($options->dryRun);
        self::assertFalse($options->createTable);
    }

    public function testFileImportOptions(): void
    {
        $options = (new CliOptionParser())->parse(['user_upload.php', '--file', 'users.csv']);

        self::assertSame('users.csv', $options->file);
        self::assertFalse($options->dryRun);
    }

    public function testFileEqualsSyntaxAndDryRun(): void
    {
        $options = (new CliOptionParser())->parse([
            'user_upload.php',
            '--dry-run',
            '--file=users.csv',
        ]);

        self::assertSame('users.csv', $options->file);
        self::assertTrue($options->dryRun);
    }

    public function testCreateTableIsStandaloneOperation(): void
    {
        $options = (new CliOptionParser())->parse(['user_upload.php', '--create-table']);

        self::assertTrue($options->createTable);
        self::assertNull($options->file);
    }

    /** @param list<string> $arguments */
    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsAreRejected(array $arguments, string $message): void
    {
        $this->expectException(InvalidCliArgumentsException::class);
        $this->expectExceptionMessage($message);

        (new CliOptionParser())->parse($arguments);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'no operation' => [
            ['user_upload.php'],
            'No operation was specified.',
        ];
        yield 'unknown option' => [
            ['user_upload.php', '--unknown'],
            'Unknown argument: --unknown',
        ];
        yield 'positional argument' => [
            ['user_upload.php', 'users.csv'],
            'Unknown argument: users.csv',
        ];
        yield 'missing file value' => [
            ['user_upload.php', '--file'],
            '--file requires a filename.',
        ];
        yield 'file followed by option' => [
            ['user_upload.php', '--file', '--dry-run'],
            '--file requires a filename.',
        ];
        yield 'empty equals file value' => [
            ['user_upload.php', '--file='],
            '--file requires a filename.',
        ];
        yield 'dry run without file' => [
            ['user_upload.php', '--dry-run'],
            '--dry-run requires --file.',
        ];
        yield 'create table with file' => [
            ['user_upload.php', '--create-table', '--file', 'users.csv'],
            '--create-table cannot be combined with --file or --dry-run.',
        ];
        yield 'help with another option' => [
            ['user_upload.php', '--help', '--create-table'],
            '--help cannot be combined with other options.',
        ];
        yield 'duplicate option' => [
            ['user_upload.php', '--file', 'one.csv', '--file', 'two.csv'],
            'Option may only be used once: --file',
        ];
    }
}
