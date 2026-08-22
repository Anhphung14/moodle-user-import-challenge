#!/usr/bin/env php
<?php

declare(strict_types=1);

use Application\Cli\CliApplication;
use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ImportPreview;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2);
Dotenv::createImmutable($projectRoot)->safeLoad();

$rebuildUsersTable = static function (): void {
    $connection = (new ConnectionFactory())->create();
    (new SchemaManager($connection))->rebuild();
};

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

$previewUsers = static fn (string $filePath): ImportPreview => $createImportService()->preview($filePath);

exit((new CliApplication(
    rebuildUsersTable: $rebuildUsersTable,
    previewUsers: $previewUsers,
))->run($argv));
