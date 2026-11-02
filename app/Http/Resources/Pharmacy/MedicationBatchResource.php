<?php

declare(strict_types=1);

namespace App\Http\Resources\Pharmacy;

use App\Models\Pharmacy\MedicationBatch;
use App\Utilities\Pharmacy\Qty;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/**
 * Lot with on-hand quantity, stock value and expiry status: `expired`, `expiring` (within pharmacy.near_expiry_days)
 * or `ok`.
 *
 * @mixin MedicationBatch
 */
final class MedicationBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = app(ClinicClock::class)->today();
        $status = match (true) {
            $this->isExpired($today) => 'expired',
            $this->expiry_date->lte($today->addDays(Config::integer('pharmacy.near_expiry_days'))) => 'expiring',
            default => 'ok',
        };

        return [
            'id' => $this->id,
            'code' => $this->code,
            'medication_id' => $this->medication_id,
            'medication' => $this->whenLoaded('medication', fn (): array => [
                'id' => $this->medication->id,
                'code' => $this->medication->code,
                'name' => $this->medication->displayName(),
                'unit' => $this->medication->unit->value,
                'is_controlled' => $this->medication->is_controlled,
            ]),
            'batch_number' => $this->batch_number,
            'location' => $this->location->value,
            'expiry_date' => $this->expiry_date->toDateString(),
            'expiry_status' => $status,
            'qty_received' => Qty::str($this->qty_received),
            'qty_on_hand' => Qty::str($this->qty_on_hand),
            'unit_cost' => MoneyHelper::toApi($this->unit_cost),
            'stock_value' => MoneyHelper::toApi($this->unit_cost->multipliedBy(Qty::of($this->qty_on_hand), RoundingMode::HALF_UP)),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn (): ?array => $this->supplier === null ? null : ['id' => $this->supplier->id, 'name' => $this->supplier->name]),
            'expense' => $this->whenLoaded('expense', fn (): ?array => $this->expense === null ? null : ['id' => $this->expense->id, 'code' => $this->expense->code, 'status' => $this->expense->status->value]),
            'received_at' => $this->received_at->toIso8601String(),
            'is_quarantined' => $this->is_quarantined,
            'quarantine_reason' => $this->quarantine_reason,
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
