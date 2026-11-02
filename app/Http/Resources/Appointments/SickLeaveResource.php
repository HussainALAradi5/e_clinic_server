<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\SickLeave;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sick leave certificate. The reason is shown to the patient and the doctors; it is printed only when
 * `show_reason` (else the certificate says "for medical reasons").
 *
 * @mixin SickLeave
 */
final class SickLeaveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = (string) $request->user()?->getAuthIdentifier();
        $mayReadReason = $viewer === $this->patient_id || ($request->user()?->can('patients.view_history') ?? false);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'patient' => [
                'id' => $this->patient_id,
                'code' => $this->patient->person->code,
                'full_name' => $this->patient->person->full_name,
                'date_of_birth' => $this->patient->person->date_of_birth->toDateString(),
                'gender' => $this->patient->person->gender->value,
            ],
            'doctor' => ['id' => $this->doctor_id, 'full_name' => $this->doctor->person->full_name, 'job_title' => $this->doctor->job_title],
            'appointment_id' => $this->appointment_id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days,
            'reason' => $this->when($mayReadReason || $this->show_reason, fn (): ?string => $this->reason),
            'show_reason' => $this->show_reason,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at->toIso8601String(),
            'is_cancelled' => $this->isCancelled(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
