#!/usr/bin/env php
<?php

declare(strict_types=1);

use Application\Cli\CliApplication;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new CliApplication())->run($argv));
