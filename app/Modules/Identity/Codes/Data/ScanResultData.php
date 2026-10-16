<?php

declare(strict_types=1);

namespace App\Modules\Identity\Codes\Data;

/**
 * What a scanned code points to.
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
        return ['entity' => $this->entity, 'id' => $this->id, 'code' => $this->code, 'valid' => $this->valid, 'summary' => $this->summary];
    }
}
