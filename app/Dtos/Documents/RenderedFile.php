<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

/**
 * A generated file ready to send: its bytes, download name and MIME type.
 */
final readonly class RenderedFile
{
    public function __construct(
        public string $content,
        public string $fileName,
        public string $mimeType = 'application/pdf',
    ) {}
}
