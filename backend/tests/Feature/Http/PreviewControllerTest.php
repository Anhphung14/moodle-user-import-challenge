<?php

declare(strict_types=1);

namespace Application\Tests\Feature\Http;

use Application\Csv\Exception\CsvParsingException;
use Application\Domain\ImportPreview;
use Application\Domain\ValidatedUserRecord;
use Application\Domain\ValidationError;
use Application\Http\HttpApplication;
use Application\Http\HttpRequest;
use Application\Http\PreviewController;
use Application\Http\Router;
use Application\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

final class PreviewControllerTest extends TestCase
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

    public function testPreviewReturnsNormalisedRecordsAndValidationErrors(): void
    {
        $sourcePath = $this->csv("name,surname,email\nnew,user,NEW@EXAMPLE.COM\n");
        $ownedPath = null;
        $ownedPathExistedDuringPreview = false;
        $preview = new ImportPreview([
            new ValidatedUserRecord(2, 'New', 'User', 'new@example.com'),
            new ValidatedUserRecord(3, 'Bad', 'User', 'bad-email', [
                new ValidationError(3, 'email', 'invalid_email', 'Email is invalid.'),
            ]),
        ]);
        $application = $this->application(
            static function (string $path) use (
                $preview,
                $sourcePath,
                &$ownedPath,
                &$ownedPathExistedDuringPreview,
            ): ImportPreview {
                $ownedPath = $path;
                $ownedPathExistedDuringPreview = is_file($path);
                self::assertNotSame($sourcePath, $path);

                return $preview;
            },
        );

        $response = $application->handle('POST', '/api/imports/preview', [
            'file' => $this->upload($sourcePath),
        ]);

        self::assertSame(200, $response->status);
        self::assertSame(2, $response->body['data']['total']);
        self::assertSame(1, $response->body['data']['valid']);
        self::assertSame(1, $response->body['data']['invalid']);
        self::assertSame('new@example.com', $response->body['data']['records'][0]['email']);
        self::assertTrue($response->body['data']['records'][0]['valid']);
        self::assertSame('invalid_email', $response->body['data']['records'][1]['errors'][0]['code']);
        self::assertTrue($ownedPathExistedDuringPreview);
        self::assertIsString($ownedPath);
        self::assertFileDoesNotExist($ownedPath);
        self::assertFileExists($sourcePath);
    }

    public function testPreviewRequiresMultipartFileField(): void
    {
        $response = $this->application(static fn (): ImportPreview => new ImportPreview([]))
            ->handle('POST', '/api/imports/preview');

        self::assertSame(400, $response->status);
        self::assertSame('file_required', $response->body['error']['code']);
    }

    public function testPreviewRejectsFilesOverFiveMebibytesBeforeProcessing(): void
    {
        $sourcePath = $this->csv("name,surname,email\n");
        $called = false;
        $application = $this->application(
            static function () use (&$called): ImportPreview {
                $called = true;

                return new ImportPreview([]);
            },
        );

        $response = $application->handle('POST', '/api/imports/preview', [
            'file' => new UploadedFile($sourcePath, 'users.csv', 5 * 1024 * 1024 + 1, UPLOAD_ERR_OK),
        ]);

        self::assertSame(413, $response->status);
        self::assertSame('file_too_large', $response->body['error']['code']);
        self::assertFalse($called);
    }

    public function testPreviewReturnsStructuredErrorForInvalidCsvHeader(): void
    {
        $sourcePath = $this->csv("first,last,address\nNew,User,new@example.com\n");
        $application = $this->application(static function (): never {
            throw new CsvParsingException('CSV header must be exactly: name, surname, email.');
        });

        $response = $application->handle('POST', '/api/imports/preview', [
            'file' => $this->upload($sourcePath),
        ]);

        self::assertSame(422, $response->status);
        self::assertSame('invalid_csv', $response->body['error']['code']);
        self::assertStringContainsString('name, surname, email', $response->body['error']['message']);
    }

    public function testPreviewRejectsFailedUpload(): void
    {
        $response = $this->application(static fn (): ImportPreview => new ImportPreview([]))
            ->handle('POST', '/api/imports/preview', [
                'file' => new UploadedFile('', 'users.csv', 0, UPLOAD_ERR_PARTIAL),
            ]);

        self::assertSame(400, $response->status);
        self::assertSame('upload_failed', $response->body['error']['code']);
    }

    /** @param \Closure(string): ImportPreview $previewUsers */
    private function application(\Closure $previewUsers): HttpApplication
    {
        $router = new Router();
        $controller = new PreviewController($previewUsers);
        $router->add('POST', '/api/imports/preview', $controller(...));

        return new HttpApplication($router);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'moodle-http-source-');
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
