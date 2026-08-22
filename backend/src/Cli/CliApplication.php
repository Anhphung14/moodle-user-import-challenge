<?php

declare(strict_types=1);

namespace Application\Cli;

use Application\Cli\Exception\InvalidCliArgumentsException;
use Application\Csv\Exception\CsvParsingException;
use Application\Domain\ImportPreview;
use Closure;
use PDOException;
use Throwable;

final class CliApplication
{
    public const int SUCCESS = 0;

    public const int RUNTIME_ERROR = 1;

    public const int INVALID_ARGUMENTS = 2;

    public function __construct(
        private readonly CliOptionParser $parser = new CliOptionParser(),
        private readonly Usage $usage = new Usage(),
        private readonly ?Closure $rebuildUsersTable = null,
        private readonly ?Closure $previewUsers = null,
    ) {
    }

    /**
     * @param list<string> $arguments
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function run(array $arguments, mixed $stdout = null, mixed $stderr = null): int
    {
        $stdout ??= STDOUT;
        $stderr ??= STDERR;

        try {
            $options = $this->parser->parse($arguments);
        } catch (InvalidCliArgumentsException $exception) {
            fwrite($stderr, sprintf("Error: %s\n\n%s", $exception->getMessage(), $this->usage->text()));

            return self::INVALID_ARGUMENTS;
        }

        if ($options->help) {
            fwrite($stdout, $this->usage->text());

            return self::SUCCESS;
        }

        if ($options->createTable) {
            return $this->rebuildUsersTable($stdout, $stderr);
        }

        if ($options->dryRun && $options->file !== null) {
            return $this->previewUsers($options->file, $stdout, $stderr);
        }

        fwrite($stderr, "Error: Command execution is not available yet.\n");

        return self::RUNTIME_ERROR;
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    private function rebuildUsersTable(mixed $stdout, mixed $stderr): int
    {
        fwrite($stderr, "Warning: rebuilding the users table deletes all existing users.\n");

        if ($this->rebuildUsersTable === null) {
            fwrite($stderr, "Error: Users table rebuild is not configured.\n");

            return self::RUNTIME_ERROR;
        }

        try {
            ($this->rebuildUsersTable)();
        } catch (Throwable) {
            fwrite($stderr, "Error: Unable to rebuild the users table.\n");

            return self::RUNTIME_ERROR;
        }

        fwrite($stdout, "Users table rebuilt successfully.\n");

        return self::SUCCESS;
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    private function previewUsers(string $filePath, mixed $stdout, mixed $stderr): int
    {
        if ($this->previewUsers === null) {
            fwrite($stderr, "Error: CSV dry-run is not configured.\n");

            return self::RUNTIME_ERROR;
        }

        try {
            $preview = ($this->previewUsers)($filePath);

            if (!$preview instanceof ImportPreview) {
                throw new \UnexpectedValueException('Preview handler returned an invalid result.');
            }
        } catch (CsvParsingException $exception) {
            fwrite($stderr, sprintf("Error: %s\n", $exception->getMessage()));

            return self::RUNTIME_ERROR;
        } catch (PDOException $exception) {
            $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();

            if ($sqlState === '42P01') {
                fwrite(
                    $stderr,
                    "Error: The users table does not exist. Run --create-table first.\n",
                );

                return self::RUNTIME_ERROR;
            }

            fwrite($stderr, "Error: Unable to preview the CSV file.\n");

            return self::RUNTIME_ERROR;
        } catch (Throwable) {
            fwrite($stderr, "Error: Unable to preview the CSV file.\n");

            return self::RUNTIME_ERROR;
        }

        fwrite($stdout, "Dry run complete.\n");
        fwrite($stdout, sprintf("Users found: %d\n", $preview->totalCount()));
        fwrite($stdout, sprintf("Valid: %d\n", $preview->validCount()));
        fwrite($stdout, sprintf("Invalid: %d\n", $preview->invalidCount()));

        if ($preview->errors() !== []) {
            fwrite($stdout, "Errors:\n");

            foreach ($preview->errors() as $error) {
                fwrite($stdout, sprintf(
                    "  Row %d [%s] %s: %s\n",
                    $error->rowNumber,
                    $error->field,
                    $error->code,
                    $error->message,
                ));
            }
        }

        fwrite($stdout, sprintf("Would import %d users.\n", $preview->validCount()));

        return self::SUCCESS;
    }
}
