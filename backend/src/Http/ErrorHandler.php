<?php

declare(strict_types=1);

namespace Application\Http;

use Application\Csv\Exception\CsvParsingException;
use Application\Database\Exception\DatabaseConfigurationException;
use Application\Database\Exception\DatabaseConnectionException;
use Application\Database\Exception\SchemaManagementException;
use Closure;
use PDOException;
use Throwable;

final readonly class ErrorHandler
{
    /** @var Closure(string): void */
    private Closure $logger;

    /** @param (Closure(string): void)|null $logger */
    public function __construct(?Closure $logger = null)
    {
        $this->logger = $logger ?? static function (string $message): void {
            error_log($message);
        };
    }

    /** @param array<string, mixed>|null $details */
    public function response(
        int $status,
        string $code,
        string $message,
        ?array $details = null,
    ): JsonResponse {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public function handle(Throwable $exception): JsonResponse
    {
        if ($exception instanceof CsvParsingException) {
            $details = $exception->rowNumber === null
                ? null
                : ['rowNumber' => $exception->rowNumber];

            return $this->response(422, 'invalid_csv', $exception->getMessage(), $details);
        }

        if (
            $exception instanceof PDOException
            || $exception instanceof DatabaseConfigurationException
            || $exception instanceof DatabaseConnectionException
            || $exception instanceof SchemaManagementException
        ) {
            $this->log($exception);

            return $this->response(
                503,
                'database_unavailable',
                'The database service is temporarily unavailable.',
            );
        }

        $this->log($exception);

        return $this->response(500, 'internal_error', 'An unexpected error occurred.');
    }

    private function log(Throwable $exception): void
    {
        ($this->logger)(sprintf(
            '[HTTP] %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));
    }
}
