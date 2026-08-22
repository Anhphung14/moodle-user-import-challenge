<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Domain\ValidatedUserRecord;

interface UserRepository
{
    /**
     * @param iterable<string> $emails
     *
     * @return array<string, true> Normalised email lookup set.
     */
    public function findExistingEmails(iterable $emails): array;

    /**
     * @param iterable<ValidatedUserRecord> $users
     */
    public function insertUsers(iterable $users): int;
}
