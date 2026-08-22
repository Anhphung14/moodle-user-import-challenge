<?php

declare(strict_types=1);

namespace Application\Http;

final readonly class UploadedFile
{
    public function __construct(
        public string $temporaryPath,
        public string $clientFilename,
        public int $size,
        public int $error,
    ) {}
}
