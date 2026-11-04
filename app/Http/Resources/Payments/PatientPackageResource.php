<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\PatientPackage;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package a patient bought: sessions used and left, validity, and the invoice that sold it.
 *
 * @mixin PatientPackage
 */
final class PatientPackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status->value,
            'patient' => ['id' => $this->patient_id, 'full_name' => $this->patient->person->full_name],
            'covered_service' => ['id' => $this->covered_service_id, 'name' => $this->coveredService->name],
            'sessions_total' => $this->sessions_total,
            'sessions_used' => $this->sessions_used,
            'sessions_left' => $this->sessionsLeft(),
            'price' => MoneyHelper::toApi($this->price),
            'remaining_value' => MoneyHelper::toApi($this->remainingValue()),
            'starts_on' => $this->starts_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'invoice' => $this->invoice === null ? null : ['id' => $this->invoice->id, 'number' => $this->invoice->invoice_number ?? $this->invoice->code, 'status' => $this->invoice->status->value],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
