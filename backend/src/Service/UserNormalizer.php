<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Domain\UserRecord;

final class UserNormalizer
{
    public function normalize(UserRecord $record): UserRecord
    {
        return new UserRecord(
            $record->rowNumber,
            $this->normalizeName($record->name),
            $this->normalizeName($record->surname),
            mb_strtolower(trim($record->email), 'UTF-8'),
        );
    }

    private function normalizeName(string $value): string
    {
        $lowercase = mb_strtolower(trim($value), 'UTF-8');

        return preg_replace_callback(
            "/(^|[\\s'’-])\\p{L}/u",
            static fn(array $match): string => mb_strtoupper($match[0], 'UTF-8'),
            $lowercase,
        ) ?? $lowercase;
    }
}
