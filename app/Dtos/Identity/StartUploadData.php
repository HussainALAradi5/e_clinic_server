<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

use App\Enums\Identity\AttachmentCategory;

final readonly class StartUploadData
{
    public function __construct(
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
        public AttachmentCategory $category,
        public ?string $sha256 = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            fileName: (string) $input['file_name'],
            mimeType: (string) $input['mime_type'],
            sizeBytes: (int) $input['size_bytes'],
            category: AttachmentCategory::from((string) $input['category']),
            sha256: isset($input['sha256']) ? strtolower((string) $input['sha256']) : null,
        );
    }
}
