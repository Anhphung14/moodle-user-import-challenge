<?php

declare(strict_types=1);

namespace Application\Http;

use Application\Domain\ImportResult;
use Application\Domain\ValidationError;
use Closure;
use Throwable;

final readonly class ImportController
{
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** @param Closure(string): ImportResult $importUsers */
    public function __construct(
        private Closure $importUsers,
        private ErrorHandler $errors = new ErrorHandler(),
    ) {
    }

    public function __invoke(HttpRequest $request): JsonResponse
    {
        $file = $request->files['file'] ?? null;

        if ($file === null || $file->error === UPLOAD_ERR_NO_FILE) {
            return $this->errors->response(400, 'file_required', 'A CSV file is required in the file field.');
        }

        if ($file->error === UPLOAD_ERR_INI_SIZE || $file->error === UPLOAD_ERR_FORM_SIZE) {
            return $this->errors->response(413, 'file_too_large', 'The CSV file exceeds the 5 MiB limit.');
        }

        if ($file->error !== UPLOAD_ERR_OK || $file->temporaryPath === '') {
            return $this->errors->response(400, 'upload_failed', 'The CSV file could not be uploaded.');
        }

        $actualSize = is_file($file->temporaryPath) ? filesize($file->temporaryPath) : false;

        if ($file->size > self::MAX_FILE_SIZE || $actualSize === false || $actualSize > self::MAX_FILE_SIZE) {
            return $actualSize === false
                ? $this->errors->response(400, 'upload_failed', 'The CSV file could not be uploaded.')
                : $this->errors->response(413, 'file_too_large', 'The CSV file exceeds the 5 MiB limit.');
        }

        $ownedPath = tempnam(sys_get_temp_dir(), 'moodle-import-');

        if ($ownedPath === false || !copy($file->temporaryPath, $ownedPath)) {
            if (is_string($ownedPath) && is_file($ownedPath)) {
                unlink($ownedPath);
            }

            return $this->errors->response(500, 'internal_error', 'The CSV file could not be processed.');
        }

        try {
            $result = ($this->importUsers)($ownedPath);

            if (!$result instanceof ImportResult) {
                throw new \UnexpectedValueException('Import handler returned an invalid result.');
            }

            return new JsonResponse(['data' => $this->serialize($result)]);
        } catch (Throwable $exception) {
            return $this->errors->handle($exception);
        } finally {
            if (is_file($ownedPath)) {
                unlink($ownedPath);
            }
        }
    }

    /** @return array<string, mixed> */
    private function serialize(ImportResult $result): array
    {
        return [
            'total' => $result->totalCount(),
            'imported' => $result->importedCount,
            'rejected' => $result->rejectedCount,
            'errors' => array_map(
                static fn (ValidationError $error): array => [
                    'rowNumber' => $error->rowNumber,
                    'field' => $error->field,
                    'code' => $error->code,
                    'message' => $error->message,
                ],
                $result->errors,
            ),
        ];
    }

}
