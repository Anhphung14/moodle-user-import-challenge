<?php

declare(strict_types=1);

namespace Application\Tests\Feature\Http;

use Application\Http\HttpApplication;
use Application\Http\HttpRequest;
use Application\Http\JsonResponse;
use Application\Http\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpApplicationTest extends TestCase
{
    public function testHealthEndpointReturnsJsonResponse(): void
    {
        $response = (new HttpApplication())->handle('GET', '/api/health?source=test');

        self::assertSame(200, $response->status);
        self::assertSame(['data' => ['status' => 'ok']], $response->body);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame('{"data":{"status":"ok"}}', $response->json());
    }

    public function testUnknownRouteReturnsJson404(): void
    {
        $response = (new HttpApplication())->handle('GET', '/api/unknown');

        self::assertSame(404, $response->status);
        self::assertSame('not_found', $response->body['error']['code']);
        self::assertArrayHasKey('message', $response->body['error']);
    }

    public function testUnexpectedExceptionReturnsSafeJsonWithoutStackTrace(): void
    {
        $router = new Router();
        $router->add('GET', '/api/failure', static function (HttpRequest $_request): never {
            throw new RuntimeException('password=secret; internal path=/private/app.php');
        });

        $response = (new HttpApplication($router))->handle('GET', '/api/failure');
        $json = $response->json();

        self::assertSame(500, $response->status);
        self::assertSame('internal_error', $response->body['error']['code']);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('/private', $json);
        self::assertStringNotContainsString('<html', strtolower($json));
    }

    public function testOptionsRequestReturnsCorsPreflightResponse(): void
    {
        $response = (new HttpApplication())->handle('OPTIONS', '/api/imports/preview');

        self::assertSame(204, $response->status);
        self::assertSame([], $response->body);
        self::assertSame('http://localhost:5173', $response->headers['Access-Control-Allow-Origin']);
        self::assertStringContainsString('POST', $response->headers['Access-Control-Allow-Methods']);
    }

    public function testWrongMethodDoesNotMatchHealthRoute(): void
    {
        $response = (new HttpApplication())->handle('POST', '/api/health');

        self::assertSame(404, $response->status);
        self::assertSame('not_found', $response->body['error']['code']);
    }
}
