<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use Application\Repository\UserRepository;

final class DatabaseDuplicateEmailDetector
{
    public const string DUPLICATE_EMAIL_IN_DATABASE = 'duplicate_email_in_database';

    public function __construct(private readonly UserRepository $repository)
    {
    }

    /**
     * @param iterable<ValidatedUserRecord> $records
     *
     * @return list<ValidatedUserRecord>
     */
    public function detect(iterable $records): array
    {
        $recordList = [];
        $emails = [];

        foreach ($records as $record) {
            $recordList[] = $record;

            if (!$this->hasEmailError($record)) {
                $emails[] = mb_strtolower(trim($record->email), 'UTF-8');
            }
        }

        if ($emails === []) {
            return $recordList;
        }

        $existingEmails = $this->repository->findExistingEmails($emails);
        $results = [];

        foreach ($recordList as $record) {
            $emailKey = mb_strtolower(trim($record->email), 'UTF-8');

            if ($this->hasEmailError($record) || !isset($existingEmails[$emailKey])) {
                $results[] = $record;
                continue;
            }

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
                        self::DUPLICATE_EMAIL_IN_DATABASE,
                        'Email already exists in the database.',
                    ),
                ],
            );
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
