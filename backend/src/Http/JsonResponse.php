<?php

declare(strict_types=1);

namespace Application\Http;

use JsonException;

final readonly class JsonResponse
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $body,
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    /**
     * @throws JsonException
     */
    public function json(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->json();
    }
}
