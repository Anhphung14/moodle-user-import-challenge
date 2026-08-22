<?php

declare(strict_types=1);

namespace Application\Cli;

final readonly class CliOptions
{
    public function __construct(
        public ?string $file,
        public bool $dryRun,
        public bool $createTable,
        public bool $help,
    ) {}
}
