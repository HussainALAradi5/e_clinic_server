<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

/**
 * Rendered code image.
 */
final readonly class CodeImage
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $payload,
    ) {}
}
