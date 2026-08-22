<?php

declare(strict_types=1);

namespace Application\Domain;

use InvalidArgumentException;

final readonly class ImportResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public int $importedCount,
        public int $rejectedCount,
        public array $errors = [],
    ) {
        if ($this->importedCount < 0 || $this->rejectedCount < 0) {
            throw new InvalidArgumentException('Import result counts must not be negative.');
        }

        foreach ($this->errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new InvalidArgumentException('Errors must contain only ValidationError instances.');
            }
        }
    }

    public function totalCount(): int
    {
        return $this->importedCount + $this->rejectedCount;
    }
}
