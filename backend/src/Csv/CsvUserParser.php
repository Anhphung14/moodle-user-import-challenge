<?php

declare(strict_types=1);

namespace Application\Csv;

use Application\Csv\Exception\CsvParsingException;
use Application\Domain\UserRecord;
use Generator;

final class CsvUserParser
{
    private const array EXPECTED_HEADER = ['name', 'surname', 'email'];

    /**
     * @return Generator<int, UserRecord>
     */
    public function parse(string $filePath): Generator
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new CsvParsingException('CSV file does not exist or is not readable.');
        }

        $handle = @fopen($filePath, 'rb');

        if ($handle === false) {
            throw new CsvParsingException('CSV file could not be opened.');
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header === false) {
                throw new CsvParsingException('CSV file is empty.');
            }

            $this->assertValidEncoding($header, 1);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];
            $normalisedHeader = array_map(
                static fn(mixed $column): string => trim((string) $column),
                $header,
            );

            if ($normalisedHeader !== self::EXPECTED_HEADER) {
                throw new CsvParsingException(
                    'CSV header must contain exactly: name,surname,email.',
                    1,
                );
            }

            $rowNumber = 1;

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                ++$rowNumber;

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $this->assertValidEncoding($row, $rowNumber);

                if (count($row) !== count(self::EXPECTED_HEADER)) {
                    throw new CsvParsingException(
                        sprintf(
                            'CSV row %d must contain exactly %d columns; %d found.',
                            $rowNumber,
                            count(self::EXPECTED_HEADER),
                            count($row),
                        ),
                        $rowNumber,
                    );
                }

                yield new UserRecord(
                    $rowNumber,
                    (string) $row[0],
                    (string) $row[1],
                    (string) $row[2],
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string|null> $row
     */
    private function isBlankRow(array $row): bool
    {
        return count($row) === 1 && trim((string) $row[0]) === '';
    }

    /**
     * @param list<string|null> $row
     */
    private function assertValidEncoding(array $row, int $rowNumber): void
    {
        foreach ($row as $value) {
            if (!mb_check_encoding((string) $value, 'UTF-8')) {
                throw new CsvParsingException(
                    sprintf('CSV row %d contains invalid UTF-8 data.', $rowNumber),
                    $rowNumber,
                );
            }
        }
    }
}
