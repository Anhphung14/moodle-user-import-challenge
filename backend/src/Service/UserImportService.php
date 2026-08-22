<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Csv\CsvUserParser;
use Application\Domain\ImportPreview;

final class UserImportService
{
    public function __construct(
        private readonly CsvUserParser $parser,
        private readonly UserNormalizer $normalizer,
        private readonly UserValidator $validator,
        private readonly DuplicateEmailDetector $fileDuplicateDetector,
        private readonly DatabaseDuplicateEmailDetector $databaseDuplicateDetector,
    ) {
    }

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
}
