<?php

declare(strict_types=1);

use Application\Http\HttpApplication;

require dirname(__DIR__) . '/vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

(new HttpApplication())->handle($method, $requestUri)->send();
