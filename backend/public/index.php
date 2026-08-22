<?php

declare(strict_types=1);

use Application\Http\HttpApplication;
use Application\Http\HttpRequest;
use Application\Http\PreviewController;
use Application\Http\Router;
use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Domain\ImportPreview;
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
$previewUsers = static function (string $filePath): ImportPreview {
    $connection = (new ConnectionFactory())->create();
    $repository = new PostgresUserRepository($connection);
    $service = new UserImportService(
        new CsvUserParser(),
        new UserNormalizer(),
        new UserValidator(),
        new DuplicateEmailDetector(),
        new DatabaseDuplicateEmailDetector($repository),
        $repository,
        $connection,
    );

    return $service->preview($filePath);
};
$controller = new PreviewController($previewUsers);
$router->add('POST', '/api/imports/preview', $controller(...));

(new HttpApplication($router))->handle($request->method, $request->path, $request->files)->send();
