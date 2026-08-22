<?php

declare(strict_types=1);

namespace Application\Csv\Exception;

use RuntimeException;
use Throwable;

final class CsvParsingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $rowNumber = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
