<?php

declare(strict_types=1);

namespace Application\Tests\Feature\Http;

use Application\Csv\Exception\CsvParsingException;
use Application\Domain\ImportResult;
use Application\Domain\ValidationError;
use Application\Http\ErrorHandler;
use Application\Http\HttpApplication;
use Application\Http\ImportController;
use Application\Http\Router;
use Application\Http\UploadedFile;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ImportControllerTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testImportReturnsCountsErrorsAndCleansOwnedFile(): void
    {
        $sourcePath = $this->csv("name,surname,email\nnew,user,new@example.com\nbad,user,bad-email\n");
        $ownedPath = null;
        $result = new ImportResult(1, 1, [
            new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.'),
        ]);
        $application = $this->application(
            static function (string $path) use ($result, $sourcePath, &$ownedPath): ImportResult {
                $ownedPath = $path;
                self::assertFileExists($path);
                self::assertNotSame($sourcePath, $path);

                return $result;
            },
        );

        $response = $application->handle('POST', '/api/imports', [
            'file' => $this->upload($sourcePath),
        ]);

        self::assertSame(200, $response->status);
        self::assertSame(2, $response->body['data']['total']);
        self::assertSame(1, $response->body['data']['imported']);
        self::assertSame(1, $response->body['data']['rejected']);
        self::assertSame('invalid_email', $response->body['data']['errors'][0]['code']);
        self::assertIsString($ownedPath);
        self::assertFileDoesNotExist($ownedPath);
        self::assertFileExists($sourcePath);
    }

    public function testImportWithZeroValidRecordsReturnsSuccessfulResult(): void
    {
        $sourcePath = $this->csv("name,surname,email\nbad,user,bad-email\n");
        $application = $this->application(static fn(): ImportResult => new ImportResult(0, 1, [
            new ValidationError(2, 'email', 'invalid_email', 'Email is invalid.'),
        ]));

        $response = $application->handle('POST', '/api/imports', [
            'file' => $this->upload($sourcePath),
        ]);

        self::assertSame(200, $response->status);
        self::assertSame(0, $response->body['data']['imported']);
        self::assertSame(1, $response->body['data']['rejected']);
    }

    public function testImportRequiresOriginalCsvFileInsteadOfClientRecordList(): void
    {
        $response = $this->application(static fn(): ImportResult => new ImportResult(0, 0))
            ->handle('POST', '/api/imports');

        self::assertSame(400, $response->status);
        self::assertSame('file_required', $response->body['error']['code']);
    }

    public function testInvalidCsvReturnsUnprocessableEntity(): void
    {
        $sourcePath = $this->csv("wrong,header\nvalue,value\n");
        $application = $this->application(static function (): never {
            throw new CsvParsingException('CSV header must be exactly: name, surname, email.');
        });

        $response = $application->handle('POST', '/api/imports', [
            'file' => $this->upload($sourcePath),
        ]);

        self::assertSame(422, $response->status);
        self::assertSame('invalid_csv', $response->body['error']['code']);
    }

    public function testDatabaseErrorReturnsSafeServiceUnavailableResponse(): void
    {
        $sourcePath = $this->csv("name,surname,email\nnew,user,new@example.com\n");
        $application = $this->application(static function (): never {
            throw new PDOException('password=secret; SQLSTATE details');
        });

        $response = $application->handle('POST', '/api/imports', [
            'file' => $this->upload($sourcePath),
        ]);
        $json = $response->json();

        self::assertSame(503, $response->status);
        self::assertSame('database_unavailable', $response->body['error']['code']);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('SQLSTATE', $json);
    }

    /** @param \Closure(string): ImportResult $importUsers */
    private function application(\Closure $importUsers): HttpApplication
    {
        $router = new Router();
        $controller = new ImportController(
            $importUsers,
            new ErrorHandler(static function (string $_message): void {}),
        );
        $router->add('POST', '/api/imports', $controller(...));

        return new HttpApplication($router);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'moodle-http-import-');
        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $contents));
        $this->files[] = $path;

        return $path;
    }

    private function upload(string $path): UploadedFile
    {
        $size = filesize($path);
        self::assertIsInt($size);

        return new UploadedFile($path, 'users.csv', $size, UPLOAD_ERR_OK);
    }
}
