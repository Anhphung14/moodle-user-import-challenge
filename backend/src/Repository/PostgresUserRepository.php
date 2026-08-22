<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Domain\ValidatedUserRecord;
use InvalidArgumentException;
use PDO;
use Throwable;

final class PostgresUserRepository implements UserRepository
{
    private const int BATCH_SIZE = 500;

    public function __construct(private readonly PDO $connection) {}

    public function findExistingEmails(iterable $emails): array
    {
        $existing = [];

        foreach ($this->emailBatches($emails) as $batch) {
            $placeholders = [];
            $parameters = [];

            foreach ($batch as $index => $email) {
                $placeholder = ':email_' . $index;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = $email;
            }

            $statement = $this->connection->prepare(sprintf(
                'SELECT email FROM users WHERE email IN (%s)',
                implode(', ', $placeholders),
            ));
            $statement->execute($parameters);

            while (($email = $statement->fetchColumn()) !== false) {
                $existing[(string) $email] = true;
            }
        }

        return $existing;
    }

    public function insertUsers(iterable $users): int
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $insertedCount = 0;
        $batch = [];

        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }

            foreach ($users as $user) {
                if (!$user instanceof ValidatedUserRecord) {
                    throw new InvalidArgumentException(
                        'Users must contain only ValidatedUserRecord instances.',
                    );
                }

                if (!$user->isValid()) {
                    throw new InvalidArgumentException('Only valid user records can be inserted.');
                }

                $batch[] = $user;

                if (count($batch) === self::BATCH_SIZE) {
                    $insertedCount += $this->insertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $insertedCount += $this->insertBatch($batch);
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $insertedCount;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param iterable<string> $emails
     *
     * @return iterable<list<string>>
     */
    private function emailBatches(iterable $emails): iterable
    {
        $seen = [];
        $batch = [];

        foreach ($emails as $email) {
            $normalised = mb_strtolower(trim($email), 'UTF-8');

            if ($normalised === '' || isset($seen[$normalised])) {
                continue;
            }

            $seen[$normalised] = true;
            $batch[] = $normalised;

            if (count($batch) === self::BATCH_SIZE) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * @param list<ValidatedUserRecord> $users
     */
    private function insertBatch(array $users): int
    {
        $rows = [];
        $parameters = [];

        foreach ($users as $index => $user) {
            $rows[] = sprintf('(:name_%1$d, :surname_%1$d, :email_%1$d)', $index);
            $parameters[':name_' . $index] = $user->name;
            $parameters[':surname_' . $index] = $user->surname;
            $parameters[':email_' . $index] = $user->email;
        }

        $statement = $this->connection->prepare(sprintf(
            'INSERT INTO users (name, surname, email) VALUES %s',
            implode(', ', $rows),
        ));
        $statement->execute($parameters);

        return count($users);
    }
}
