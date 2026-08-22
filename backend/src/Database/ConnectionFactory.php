<?php

declare(strict_types=1);

namespace Application\Database;

use Application\Database\Exception\DatabaseConfigurationException;
use Application\Database\Exception\DatabaseConnectionException;
use PDO;
use PDOException;

final class ConnectionFactory
{
    /**
     * @param array<string, mixed>|null $environment
     */
    public function create(?array $environment = null): PDO
    {
        $databaseUrl = $this->databaseUrl($environment);
        $connection = $this->parseDatabaseUrl($databaseUrl);

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $connection['host'],
            $connection['port'],
            $connection['database'],
        );

        try {
            return new PDO(
                $dsn,
                $connection['user'],
                $connection['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $exception) {
            throw new DatabaseConnectionException(
                'Unable to connect to the PostgreSQL database.',
                previous: $exception,
            );
        }
    }

    /**
     * @param array<string, mixed>|null $environment
     */
    private function databaseUrl(?array $environment): string
    {
        $value = $environment === null
            ? ($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL'))
            : ($environment['DATABASE_URL'] ?? null);

        if (!is_string($value) || trim($value) === '') {
            throw new DatabaseConfigurationException(
                'The DATABASE_URL environment variable is required.',
            );
        }

        return trim($value);
    }

    /**
     * @return array{host: string, port: int, database: string, user: string, password: string}
     */
    private function parseDatabaseUrl(string $databaseUrl): array
    {
        $parts = parse_url($databaseUrl);

        if ($parts === false || !in_array($parts['scheme'] ?? null, ['postgres', 'postgresql'], true)) {
            throw $this->invalidDatabaseUrl();
        }

        $host = $parts['host'] ?? '';
        $database = rawurldecode(ltrim($parts['path'] ?? '', '/'));
        $user = rawurldecode($parts['user'] ?? '');

        if ($host === '' || $database === '' || $user === '') {
            throw $this->invalidDatabaseUrl();
        }

        if (str_contains($host, ';') || str_contains($database, ';')) {
            throw $this->invalidDatabaseUrl();
        }

        return [
            'host' => $host,
            'port' => $parts['port'] ?? 5432,
            'database' => $database,
            'user' => $user,
            'password' => rawurldecode($parts['pass'] ?? ''),
        ];
    }

    private function invalidDatabaseUrl(): DatabaseConfigurationException
    {
        return new DatabaseConfigurationException(
            'DATABASE_URL must be a valid PostgreSQL connection string.',
        );
    }
}
