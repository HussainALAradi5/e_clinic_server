<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

use App\Enums\Documents\DocumentTone;

/**
 * Coloured pill next to the title (status, type, payment state).
 */
final readonly class DocumentBadge
{
    public function __construct(
        public string $label,
        public DocumentTone $tone = DocumentTone::Neutral,
    ) {}
}
