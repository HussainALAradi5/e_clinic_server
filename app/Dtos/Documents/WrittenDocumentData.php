<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

use App\Enums\Documents\WrittenDocumentKind;

/**
 * A document written at a visit: its kind, title, rich-text body and the template it started from (if any).
 */
final readonly class WrittenDocumentData
{
    public function __construct(
        public WrittenDocumentKind $kind,
        public string $title,
        public string $body,
        public ?string $templateId = null,
    ) {}
}
