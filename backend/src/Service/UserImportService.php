<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Csv\CsvUserParser;
use Application\Domain\ImportPreview;
use Application\Domain\ImportResult;
use Application\Domain\ValidatedUserRecord;
use Application\Repository\UserRepository;
use PDO;
use PDOException;
use Throwable;

final class UserImportService
{
    public function __construct(
        private readonly CsvUserParser $parser,
        private readonly UserNormalizer $normalizer,
        private readonly UserValidator $validator,
        private readonly DuplicateEmailDetector $fileDuplicateDetector,
        private readonly DatabaseDuplicateEmailDetector $databaseDuplicateDetector,
        private readonly UserRepository $repository,
        private readonly PDO $connection,
    ) {}

    public function preview(string $filePath): ImportPreview
    {
        $validatedRecords = [];

        foreach ($this->parser->parse($filePath) as $record) {
            $normalised = $this->normalizer->normalize($record);
            $validatedRecords[] = $this->validator->validate($normalised);
        }

        $fileCheckedRecords = $this->fileDuplicateDetector->detect($validatedRecords);
        $databaseCheckedRecords = $this->databaseDuplicateDetector->detect($fileCheckedRecords);

        return new ImportPreview($databaseCheckedRecords);
    }

    public function import(string $filePath): ImportResult
    {
        $mayRetryUniqueViolation = !$this->connection->inTransaction();

        try {
            return $this->executeImport($filePath);
        } catch (PDOException $exception) {
            if (!$mayRetryUniqueViolation || (string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            return $this->executeImport($filePath);
        }
    }

    private function executeImport(string $filePath): ImportResult
    {
        $ownsTransaction = !$this->connection->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }

            $preview = $this->preview($filePath);
            $validRecords = array_values(array_filter(
                $preview->records,
                static fn(ValidatedUserRecord $record): bool => $record->isValid(),
            ));
            $importedCount = $validRecords === []
                ? 0
                : $this->repository->insertUsers($validRecords);

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return new ImportResult(
                $importedCount,
                $preview->invalidCount(),
                $preview->errors(),
            );
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
