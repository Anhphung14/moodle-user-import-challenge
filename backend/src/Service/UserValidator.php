<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Domain\UserRecord;
use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;

final class UserValidator
{
    public const string REQUIRED = 'required';

    public const string INVALID_EMAIL = 'invalid_email';

    public function validate(UserRecord $record): ValidatedUserRecord
    {
        $errors = [];

        if (trim($record->name) === '') {
            $errors[] = $this->error($record, 'name', self::REQUIRED, 'Name is required.');
        }

        if (trim($record->surname) === '') {
            $errors[] = $this->error($record, 'surname', self::REQUIRED, 'Surname is required.');
        }

        if (trim($record->email) === '') {
            $errors[] = $this->error($record, 'email', self::REQUIRED, 'Email is required.');
        } elseif (filter_var($record->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = $this->error(
                $record,
                'email',
                self::INVALID_EMAIL,
                'Email must be a valid email address.',
            );
        }

        return new ValidatedUserRecord(
            $record->rowNumber,
            $record->name,
            $record->surname,
            $record->email,
            $errors,
        );
    }

    private function error(
        UserRecord $record,
        string $field,
        string $code,
        string $message,
    ): ValidationError {
        return new ValidationError($record->rowNumber, $field, $code, $message);
    }
}
