<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Http\Resources;

use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Medication
 */
final class MedicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = $this->stock_on_hand;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->displayName(),
            'generic_name' => $this->generic_name,
            'brand_name' => $this->brand_name,
            'name_ar' => $this->name_ar,
            'form' => $this->form->value,
            'strength' => $this->strength,
            'unit' => $this->unit->value,
            'gtin' => $this->gtin,
            'manufacturer_id' => $this->manufacturer_id,
            'manufacturer' => $this->whenLoaded('manufacturer', fn (): ?array => $this->manufacturer === null ? null : ['id' => $this->manufacturer->id, 'name' => $this->manufacturer->name]),
            'origin_country_code' => $this->origin_country_code,
            'origin_country' => $this->whenLoaded('originCountry', fn (): ?array => $this->originCountry === null ? null : ['code' => $this->originCountry->code, 'name' => $this->originCountry->name]),
            'description' => $this->description,
            'billable_service_id' => $this->billable_service_id,
            'price' => $this->whenLoaded('billableService', fn (): array => MoneyHelper::toApi($this->billableService->price)),
            'tax_rate' => $this->whenLoaded('billableService', fn (): string => (string) $this->billableService->tax_rate),
            'requires_prescription' => $this->requires_prescription,
            'is_controlled' => $this->is_controlled,
            'reorder_level' => $this->reorder_level,
            'storage_notes' => $this->storage_notes,
            'stock_on_hand' => $stock === null ? null : Qty::str($stock),
            'stock_treatment_room' => $this->stock_treatment_room === null ? null : Qty::str($this->stock_treatment_room),
            'next_expiry' => $this->next_expiry === null ? null : substr((string) $this->next_expiry, 0, 10),
            'is_low_stock' => $stock !== null && $this->reorder_level > 0 && Qty::of($stock)->isLessThanOrEqualTo($this->reorder_level),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
