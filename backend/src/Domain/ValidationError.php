<?php

declare(strict_types=1);

namespace Application\Domain;

use InvalidArgumentException;

final readonly class ValidationError
{
    public function __construct(
        public int $rowNumber,
        public string $field,
        public string $code,
        public string $message,
    ) {
        if ($this->rowNumber < 1) {
            throw new InvalidArgumentException('Row number must be greater than zero.');
        }

        if ($this->field === '' || $this->code === '' || $this->message === '') {
            throw new InvalidArgumentException('Validation error details must not be empty.');
        }
    }
}
