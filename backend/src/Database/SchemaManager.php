<?php

declare(strict_types=1);

namespace Application\Database;

use Application\Database\Exception\SchemaManagementException;
use PDO;
use Throwable;

final class SchemaManager
{
    private readonly string $schemaFile;

    public function __construct(
        private readonly PDO $connection,
        ?string $schemaFile = null,
    ) {
        $this->schemaFile = $schemaFile ?? dirname(__DIR__, 3) . '/database/schema.sql';
    }

    public function create(): void
    {
        $this->executeSchema($this->readSchema());
    }

    public function rebuild(): void
    {
        $schema = $this->readSchema();
        $ownsTransaction = !$this->connection->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }

            $this->connection->exec('DROP TABLE IF EXISTS users');
            $this->connection->exec($schema);

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new SchemaManagementException(
                'Unable to rebuild the users database schema.',
                previous: $exception,
            );
        }
    }

    private function readSchema(): string
    {
        if (!is_file($this->schemaFile) || !is_readable($this->schemaFile)) {
            throw new SchemaManagementException('The users schema file is not readable.');
        }

        $schema = file_get_contents($this->schemaFile);

        if ($schema === false || trim($schema) === '') {
            throw new SchemaManagementException('The users schema file is empty.');
        }

        return $schema;
    }

    private function executeSchema(string $schema): void
    {
        try {
            $this->connection->exec($schema);
        } catch (Throwable $exception) {
            throw new SchemaManagementException(
                'Unable to create the users database schema.',
                previous: $exception,
            );
        }
    }
}
