<?php

declare(strict_types=1);

namespace Application\Cli;

use Application\Cli\Exception\InvalidCliArgumentsException;

final class CliOptionParser
{
    /**
     * @param list<string> $arguments Full argv including the script name.
     */
    public function parse(array $arguments): CliOptions
    {
        array_shift($arguments);

        $file = null;
        $dryRun = false;
        $createTable = false;
        $help = false;
        $seen = [];

        while ($arguments !== []) {
            $argument = array_shift($arguments);

            if ($argument === '--file') {
                $this->assertNotSeen($seen, '--file');
                $value = array_shift($arguments);

                if ($value === null || $value === '' || str_starts_with($value, '--')) {
                    throw new InvalidCliArgumentsException('--file requires a filename.');
                }

                $file = $value;
                continue;
            }

            if (str_starts_with($argument, '--file=')) {
                $this->assertNotSeen($seen, '--file');
                $file = substr($argument, strlen('--file='));

                if ($file === '') {
                    throw new InvalidCliArgumentsException('--file requires a filename.');
                }

                continue;
            }

            if ($argument === '--dry-run') {
                $this->assertNotSeen($seen, '--dry-run');
                $dryRun = true;
                continue;
            }

            if ($argument === '--create-table') {
                $this->assertNotSeen($seen, '--create-table');
                $createTable = true;
                continue;
            }

            if ($argument === '--help') {
                $this->assertNotSeen($seen, '--help');
                $help = true;
                continue;
            }

            throw new InvalidCliArgumentsException(sprintf('Unknown argument: %s', $argument));
        }

        if ($help && ($file !== null || $dryRun || $createTable)) {
            throw new InvalidCliArgumentsException('--help cannot be combined with other options.');
        }

        if ($createTable && ($file !== null || $dryRun)) {
            throw new InvalidCliArgumentsException(
                '--create-table cannot be combined with --file or --dry-run.',
            );
        }

        if ($dryRun && $file === null) {
            throw new InvalidCliArgumentsException('--dry-run requires --file.');
        }

        if (!$help && !$createTable && $file === null) {
            throw new InvalidCliArgumentsException('No operation was specified.');
        }

        return new CliOptions($file, $dryRun, $createTable, $help);
    }

    /**
     * @param array<string, true> $seen
     */
    private function assertNotSeen(array &$seen, string $option): void
    {
        if (isset($seen[$option])) {
            throw new InvalidCliArgumentsException(sprintf('Option may only be used once: %s', $option));
        }

        $seen[$option] = true;
    }
}
