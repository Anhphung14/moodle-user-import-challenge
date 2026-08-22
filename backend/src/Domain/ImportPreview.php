<?php

declare(strict_types=1);

namespace Application\Domain;

use InvalidArgumentException;

final readonly class ImportPreview
{
    /**
     * @param list<ValidatedUserRecord> $records
     */
    public function __construct(public array $records)
    {
        foreach ($this->records as $record) {
            if (!$record instanceof ValidatedUserRecord) {
                throw new InvalidArgumentException(
                    'Preview records must contain only ValidatedUserRecord instances.',
                );
            }
        }
    }

    public function totalCount(): int
    {
        return count($this->records);
    }

    public function validCount(): int
    {
        return count(array_filter(
            $this->records,
            static fn(ValidatedUserRecord $record): bool => $record->isValid(),
        ));
    }

    public function invalidCount(): int
    {
        return $this->totalCount() - $this->validCount();
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return array_values(array_merge(
            ...array_map(
                static fn(ValidatedUserRecord $record): array => $record->errors,
                $this->records,
            ),
        ));
    }
}
