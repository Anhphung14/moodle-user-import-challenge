<?php

declare(strict_types=1);

namespace Application\Cli;

use Application\Cli\Exception\InvalidCliArgumentsException;

final class CliApplication
{
    public const int SUCCESS = 0;

    public const int RUNTIME_ERROR = 1;

    public const int INVALID_ARGUMENTS = 2;

    public function __construct(
        private readonly CliOptionParser $parser = new CliOptionParser(),
        private readonly Usage $usage = new Usage(),
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

        fwrite($stderr, "Error: Command execution is not available yet.\n");

        return self::RUNTIME_ERROR;
    }
}
