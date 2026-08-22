<?php

declare(strict_types=1);

namespace Application\Cli;

final class Usage
{
    public function text(): string
    {
        return <<<'TEXT'
            Moodle User Import

            Usage:
              php backend/bin/user_upload.php --file <filename> [--dry-run]
              php backend/bin/user_upload.php --create-table
              php backend/bin/user_upload.php --help

            Options:
              --file <filename>  CSV file to process
              --dry-run          Parse and validate without importing
              --create-table     Create or rebuild the users table
              --help             Display available options

            TEXT;
    }
}
