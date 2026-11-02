<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Carbon\CarbonImmutable;

/**
 * One event of the unified patient timeline (appointments now; records, orders, prescriptions and invoices join
 * as their modules arrive).
 */
final readonly class PatientTimelineEntry
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $kind,
        public string $id,
        public CarbonImmutable $at,
        public string $title,
        public ?string $status = null,
        public array $data = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->id,
            'at' => $this->at->toIso8601String(),
            'title' => $this->title,
            'status' => $this->status,
            'data' => $this->data,
        ];
    }
}
