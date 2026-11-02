<?php

declare(strict_types=1);

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\Shared\Summary;
use App\Models\Pharmacy\StockMovement;
use App\Utilities\Pharmacy\Qty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
final class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => Qty::str($this->quantity),
            'medication_id' => $this->medication_id,
            'medication' => $this->whenLoaded('medication', fn (): array => ['id' => $this->medication->id, 'code' => $this->medication->code, 'name' => $this->medication->displayName(), 'unit' => $this->medication->unit->value]),
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn (): array => ['id' => $this->batch->id, 'code' => $this->batch->code, 'batch_number' => $this->batch->batch_number]),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reason' => $this->reason,
            'created_by' => $this->whenLoaded('creator', fn (): ?array => Summary::user($this->creator)),
            'approved_by' => $this->whenLoaded('approver', fn (): ?array => Summary::user($this->approver)),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
