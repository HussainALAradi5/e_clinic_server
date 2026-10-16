<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Data;

use App\Enums\Identity\AttachmentCategory;

/**
 * The record a file is attached to (whitelisted morph alias) plus its category and caption.
 */
final readonly class AttachToData
{
    public function __construct(
        public string $attachableType,
        public string $attachableId,
        public AttachmentCategory $category,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input, ?AttachmentCategory $category = null): self
    {
        return new self(
            attachableType: (string) $input['attachable_type'],
            attachableId: (string) $input['attachable_id'],
            category: $category ?? AttachmentCategory::from((string) $input['category']),
            description: isset($input['description']) ? (string) $input['description'] : null,
        );
    }
}
