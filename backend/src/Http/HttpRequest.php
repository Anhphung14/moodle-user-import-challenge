<?php

declare(strict_types=1);

namespace Application\Http;

final readonly class HttpRequest
{
    /** @param array<string, UploadedFile> $files */
    public function __construct(
        public string $method,
        public string $path,
        public array $files = [],
    ) {}

    /** @param array<string, mixed> $files */
    public static function fromGlobals(string $method, string $requestUri, array $files): self
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $uploadedFiles = [];

        foreach ($files as $field => $file) {
            if (!is_string($field) || !is_array($file)) {
                continue;
            }

            $uploadedFiles[$field] = new UploadedFile(
                is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '',
                is_string($file['name'] ?? null) ? $file['name'] : '',
                is_int($file['size'] ?? null) ? $file['size'] : 0,
                is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE,
            );
        }

        return new self(strtoupper($method), $path, $uploadedFiles);
    }
}
