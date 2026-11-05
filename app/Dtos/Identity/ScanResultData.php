<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

use App\Enums\Documents\DocumentType;

/**
 * What a scanned code points to; `document` is the DocumentType whose PDF the reader may open (`GET /documents/
 * {document}/{id}/file`, still checked against their rights), null when the record has no document.
 * `hidden`: a genuine code whose record is for clinic staff only (public check) — no id, details or document.
 */
final readonly class ScanResultData
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $entity,
        public string $id,
        public string $code,
        public array $summary,
        public bool $valid = true,
        public bool $hidden = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity, 'id' => $this->id, 'code' => $this->code, 'valid' => $this->valid, 'summary' => $this->summary,
            'hidden' => $this->hidden,
            'document' => $this->hidden ? null : DocumentType::forCodeEntity($this->entity)?->value,
        ];
    }
}
