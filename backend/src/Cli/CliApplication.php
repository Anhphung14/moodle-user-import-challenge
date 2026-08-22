<?php

declare(strict_types=1);

namespace Application\Cli;

use Application\Cli\Exception\InvalidCliArgumentsException;
use Closure;
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
}
