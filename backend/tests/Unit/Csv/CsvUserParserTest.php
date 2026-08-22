<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Csv;

use Application\Csv\CsvUserParser;
use Application\Csv\Exception\CsvParsingException;
use Application\Domain\UserRecord;
use PHPUnit\Framework\TestCase;

final class CsvUserParserTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testItParsesRecordsAndPreservesRawValuesAndRowNumbers(): void
    {
        $records = $this->parse("name,surname,email\n john , smith ,JOHN@example.com\n");

        self::assertEquals([
            new UserRecord(2, ' john ', ' smith ', 'JOHN@example.com'),
        ], $records);
    }

    public function testItSupportsUtf8BomAndQuotedCommas(): void
    {
        $records = $this->parse(
            "\xEF\xBB\xBFname,surname,email\n\"Mary, Jane\",Smith,mary@example.com\n",
        );

        self::assertSame('Mary, Jane', $records[0]->name);
        self::assertSame(2, $records[0]->rowNumber);
    }

    public function testItSkipsBlankLinesAndKeepsPhysicalRowNumbers(): void
    {
        $records = $this->parse(
            "name,surname,email\nJohn,Smith,john@example.com\n\nJane,Doe,jane@example.com\n",
        );

        self::assertSame([2, 4], array_map(
            static fn (UserRecord $record): int => $record->rowNumber,
            $records,
        ));
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(CsvParsingException::class);
        $this->expectExceptionMessage('CSV file does not exist or is not readable.');

        iterator_to_array((new CsvUserParser())->parse(__DIR__ . '/missing.csv'));
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->expectException(CsvParsingException::class);
        $this->expectExceptionMessage('CSV file is empty.');

        $this->parse('');
    }

    public function testInvalidHeaderIsRejected(): void
    {
        try {
            $this->parse("email,name,surname\njohn@example.com,John,Smith\n");
            self::fail('The invalid header was expected to fail.');
        } catch (CsvParsingException $exception) {
            self::assertSame(1, $exception->rowNumber);
            self::assertSame(
                'CSV header must contain exactly: name,surname,email.',
                $exception->getMessage(),
            );
        }
    }

    public function testMissingColumnIsRejectedWithSourceRow(): void
    {
        $this->assertInvalidColumnCount(
            "name,surname,email\nJohn,Smith\n",
            'CSV row 2 must contain exactly 3 columns; 2 found.',
        );
    }

    public function testExtraColumnIsRejectedWithSourceRow(): void
    {
        $this->assertInvalidColumnCount(
            "name,surname,email\nJohn,Smith,john@example.com,extra\n",
            'CSV row 2 must contain exactly 3 columns; 4 found.',
        );
    }

    public function testInvalidUtf8IsRejectedWithSourceRow(): void
    {
        try {
            $this->parse("name,surname,email\nJo\xFFhn,Smith,john@example.com\n");
            self::fail('Invalid UTF-8 was expected to fail.');
        } catch (CsvParsingException $exception) {
            self::assertSame(2, $exception->rowNumber);
            self::assertSame('CSV row 2 contains invalid UTF-8 data.', $exception->getMessage());
        }
    }

    /**
     * @return list<UserRecord>
     */
    private function parse(string $contents): array
    {
        $filePath = tempnam(sys_get_temp_dir(), 'moodle-users-');
        self::assertNotFalse($filePath);
        $this->temporaryFiles[] = $filePath;
        self::assertNotFalse(file_put_contents($filePath, $contents));

        return iterator_to_array((new CsvUserParser())->parse($filePath), false);
    }

    private function assertInvalidColumnCount(string $contents, string $message): void
    {
        try {
            $this->parse($contents);
            self::fail('Invalid column count was expected to fail.');
        } catch (CsvParsingException $exception) {
            self::assertSame(2, $exception->rowNumber);
            self::assertSame($message, $exception->getMessage());
        }
    }
}
