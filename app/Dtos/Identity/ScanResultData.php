<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

use App\Enums\Documents\DocumentType;

/**
 * What a scanned code points to; `document` is the DocumentType whose PDF the reader may open (`GET /documents/
 * {document}/{id}/file`, still checked against their rights), null when the record has no document.
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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity, 'id' => $this->id, 'code' => $this->code, 'valid' => $this->valid, 'summary' => $this->summary,
            'document' => DocumentType::forCodeEntity($this->entity)?->value,
        ];
    }
}
