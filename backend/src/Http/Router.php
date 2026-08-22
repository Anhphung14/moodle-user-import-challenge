<?php

declare(strict_types=1);

namespace Application\Http;

use Closure;

final class Router
{
    /** @var array<string, Closure(HttpRequest): JsonResponse> */
    private array $routes = [];

    /** @param Closure(HttpRequest): JsonResponse $handler */
    public function add(string $method, string $path, Closure $handler): void
    {
        $this->routes[$this->key($method, $path)] = $handler;
    }

    public function dispatch(HttpRequest $request): ?JsonResponse
    {
        $handler = $this->routes[$this->key($request->method, $request->path)] ?? null;

        return $handler === null ? null : $handler($request);
    }

    private function key(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . $path;
    }
}
