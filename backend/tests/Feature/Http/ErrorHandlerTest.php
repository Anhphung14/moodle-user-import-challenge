<?php

declare(strict_types=1);

namespace Application\Tests\Feature\Http;

use Application\Csv\Exception\CsvParsingException;
use Application\Database\Exception\DatabaseConnectionException;
use Application\Http\ErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    public function testRequestErrorUsesStandardEnvelope(): void
    {
        $response = (new ErrorHandler())->response(
            400,
            'file_required',
            'A CSV file is required.',
            ['field' => 'file'],
        );

        self::assertSame(400, $response->status);
        self::assertSame([
            'code' => 'file_required',
            'message' => 'A CSV file is required.',
            'details' => ['field' => 'file'],
        ], $response->body['error']);
    }

    public function testCsvErrorIncludesSafeRowDetail(): void
    {
        $response = (new ErrorHandler(static function (string $_message): void {}))->handle(new CsvParsingException('CSV row has the wrong number of columns.', 7));

        self::assertSame(422, $response->status);
        self::assertSame('invalid_csv', $response->body['error']['code']);
        self::assertSame(['rowNumber' => 7], $response->body['error']['details']);
    }

    public function testProjectDatabaseExceptionMapsToSafeServiceUnavailableError(): void
    {
        $response = (new ErrorHandler(static function (string $_message): void {}))->handle(new DatabaseConnectionException('postgres://user:secret@localhost/database'));
        $json = $response->json();

        self::assertSame(503, $response->status);
        self::assertSame('database_unavailable', $response->body['error']['code']);
        self::assertNull($response->body['error']['details']);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('postgres://', $json);
    }

    public function testUnexpectedExceptionIsLoggedButTechnicalDetailsAreHiddenFromClient(): void
    {
        $log = null;
        $handler = new ErrorHandler(static function (string $message) use (&$log): void {
            $log = $message;
        });

        $response = $handler->handle(new RuntimeException('SQL password=secret at /private/app.php'));
        $json = $response->json();

        self::assertSame(500, $response->status);
        self::assertSame('internal_error', $response->body['error']['code']);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('/private', $json);
        self::assertIsString($log);
        self::assertStringContainsString('password=secret', $log);
        self::assertStringContainsString(RuntimeException::class, $log);
    }
}
