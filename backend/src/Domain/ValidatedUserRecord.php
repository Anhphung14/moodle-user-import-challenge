<?php

declare(strict_types=1);

namespace Application\Domain;

use InvalidArgumentException;

final readonly class ValidatedUserRecord
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public int $rowNumber,
        public string $name,
        public string $surname,
        public string $email,
        public array $errors = [],
    ) {
        if ($this->rowNumber < 1) {
            throw new InvalidArgumentException('Row number must be greater than zero.');
        }

        foreach ($this->errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new InvalidArgumentException('Errors must contain only ValidationError instances.');
            }

            if ($error->rowNumber !== $this->rowNumber) {
                throw new InvalidArgumentException('Validation error row must match the record row.');
            }
        }
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
