<?php

declare(strict_types=1);

namespace Application\Http;

final class HttpApplication
{
    /** @var array<string, string> */
    private const array JSON_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Access-Control-Allow-Origin' => 'http://localhost:5173',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Vary' => 'Origin',
    ];

    public function __construct(
        private readonly Router $router = new Router(),
        private readonly ErrorHandler $errors = new ErrorHandler(),
    ) {
        $this->router->add('GET', '/api/health', static fn (HttpRequest $_request): JsonResponse => new JsonResponse([
            'data' => ['status' => 'ok'],
        ]));
    }

    /** @param array<string, UploadedFile> $files */
    public function handle(string $method, string $requestUri, array $files = []): JsonResponse
    {
        if (strtoupper($method) === 'OPTIONS') {
            return new JsonResponse([], 204, self::JSON_HEADERS);
        }

        $request = new HttpRequest(strtoupper($method), $this->path($requestUri), $files);

        try {
            $response = $this->router->dispatch($request);

            if ($response === null) {
                return $this->withJsonHeaders($this->errors->response(
                    404,
                    'not_found',
                    'The requested endpoint was not found.',
                ));
            }

            return $this->withJsonHeaders($response);
        } catch (\Throwable $exception) {
            return $this->withJsonHeaders($this->errors->handle($exception));
        }
    }

    private function path(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function withJsonHeaders(JsonResponse $response): JsonResponse
    {
        return new JsonResponse(
            $response->body,
            $response->status,
            array_merge(self::JSON_HEADERS, $response->headers),
        );
    }

}
