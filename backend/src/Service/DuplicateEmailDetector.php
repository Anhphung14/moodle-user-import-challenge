<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;

final class DuplicateEmailDetector
{
    public const string DUPLICATE_EMAIL_IN_FILE = 'duplicate_email_in_file';

    /**
     * @param iterable<ValidatedUserRecord> $records
     *
     * @return list<ValidatedUserRecord>
     */
    public function detect(iterable $records): array
    {
        /** @var array<string, int> $firstRowsByEmail */
        $firstRowsByEmail = [];
        $results = [];

        foreach ($records as $record) {
            if ($this->hasEmailError($record)) {
                $results[] = $record;
                continue;
            }

            $emailKey = mb_strtolower(trim($record->email), 'UTF-8');

            if (array_key_exists($emailKey, $firstRowsByEmail)) {
                $firstRow = $firstRowsByEmail[$emailKey];
                $results[] = new ValidatedUserRecord(
                    $record->rowNumber,
                    $record->name,
                    $record->surname,
                    $record->email,
                    [
                        ...$record->errors,
                        new ValidationError(
                            $record->rowNumber,
                            'email',
                            self::DUPLICATE_EMAIL_IN_FILE,
                            sprintf('Email duplicates row %d in the CSV file.', $firstRow),
                        ),
                    ],
                );
                continue;
            }

            $firstRowsByEmail[$emailKey] = $record->rowNumber;
            $results[] = $record;
        }

        return $results;
    }

    private function hasEmailError(ValidatedUserRecord $record): bool
    {
        foreach ($record->errors as $error) {
            if ($error->field === 'email') {
                return true;
            }
        }

        return false;
    }
}
