<?php

declare(strict_types=1);

namespace Application\Http;

use Closure;

final class Router
{
    /** @var array<string, Closure(): JsonResponse> */
    private array $routes = [];

    /** @param Closure(): JsonResponse $handler */
    public function add(string $method, string $path, Closure $handler): void
    {
        $this->routes[$this->key($method, $path)] = $handler;
    }

    public function dispatch(string $method, string $path): ?JsonResponse
    {
        $handler = $this->routes[$this->key($method, $path)] ?? null;

        return $handler === null ? null : $handler();
    }

    private function key(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . $path;
    }
}
