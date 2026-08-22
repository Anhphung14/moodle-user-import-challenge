<?php

declare(strict_types=1);

namespace Application\Domain;

use InvalidArgumentException;

final readonly class UserRecord
{
    public function __construct(
        public int $rowNumber,
        public string $name,
        public string $surname,
        public string $email,
    ) {
        if ($this->rowNumber < 1) {
            throw new InvalidArgumentException('Row number must be greater than zero.');
        }
    }
}
