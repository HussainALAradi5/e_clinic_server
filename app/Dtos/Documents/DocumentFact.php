<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

use App\Enums\Documents\DocumentTone;

/**
 * One label / value pair (e.g. "Doctor" → "Omar Khalil"). `wide` spans the whole row; a `tone` colours its
 * highlight tile, or shows the value as a coloured badge in a facts card (e.g. sex).
 */
final readonly class DocumentFact
{
    public function __construct(
        public string $label,
        public string $value,
        public bool $wide = false,
        public ?DocumentTone $tone = null,
    ) {}

    /** Null when the value is empty, so optional facts can be listed inline and dropped by the block. */
    public static function optional(string $label, ?string $value, bool $wide = false): ?self
    {
        return $value === null || trim($value) === '' ? null : new self($label, $value, $wide);
    }
}
