<?php

declare(strict_types=1);

use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Domain\ImportPreview;
use Application\Domain\ImportResult;
use Application\Http\ErrorHandler;
use Application\Http\HttpApplication;
use Application\Http\HttpRequest;
use Application\Http\ImportController;
use Application\Http\PreviewController;
use Application\Http\Router;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$request = HttpRequest::fromGlobals($method, $requestUri, $_FILES);
$router = new Router();
$errors = new ErrorHandler();
$createImportService = static function (): UserImportService {
    $connection = (new ConnectionFactory())->create();
    $repository = new PostgresUserRepository($connection);

    return new UserImportService(
        new CsvUserParser(),
        new UserNormalizer(),
        new UserValidator(),
        new DuplicateEmailDetector(),
        new DatabaseDuplicateEmailDetector($repository),
        $repository,
        $connection,
    );
};
$previewUsers = static fn(string $filePath): ImportPreview => $createImportService()->preview($filePath);
$importUsers = static fn(string $filePath): ImportResult => $createImportService()->import($filePath);
$previewController = new PreviewController($previewUsers, $errors);
$importController = new ImportController($importUsers, $errors);
$router->add('POST', '/api/imports/preview', $previewController(...));
$router->add('POST', '/api/imports', $importController(...));

(new HttpApplication($router, $errors))->handle($request->method, $request->path, $request->files)->send();
