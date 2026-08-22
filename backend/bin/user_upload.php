#!/usr/bin/env php
<?php

declare(strict_types=1);

use Application\Cli\CliApplication;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2);
Dotenv::createImmutable($projectRoot)->safeLoad();

$rebuildUsersTable = static function (): void {
    $connection = (new ConnectionFactory())->create();
    (new SchemaManager($connection))->rebuild();
};

exit((new CliApplication(rebuildUsersTable: $rebuildUsersTable))->run($argv));
