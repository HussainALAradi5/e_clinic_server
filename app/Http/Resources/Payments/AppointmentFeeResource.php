<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\AppointmentFee;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fee for a missed or late-cancelled visit: the visit, the amount, its invoice and, when waived, who and why.
 *
 * @mixin AppointmentFee
 */
final class AppointmentFeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'amount' => MoneyHelper::toApi($this->amount),
            'appointment' => [
                'id' => $this->appointment_id,
                'code' => $this->appointment->code,
                'scheduled_date' => $this->appointment->scheduled_date->toDateString(),
                'start_time' => $this->appointment->start_time,
            ],
            'patient' => ['id' => $this->patient_id, 'full_name' => $this->patient->person->full_name],
            'invoice' => $this->invoice === null ? null : [
                'id' => $this->invoice->id,
                'number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status->value,
            ],
            'waived_by' => $this->waiver?->person->full_name,
            'waived_at' => $this->waived_at?->toIso8601String(),
            'waiver_reason' => $this->waiver_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
