<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

/**
 * An image embedded in a PDF as a data URI (nothing is fetched over the network while rendering).
 */
final readonly class DocumentImage
{
    public function __construct(
        public string $dataUri,
        public string $caption,
    ) {}

    public static function fromBytes(string $bytes, string $mimeType, string $caption): self
    {
        return new self('data:'.$mimeType.';base64,'.base64_encode($bytes), $caption);
    }
}
