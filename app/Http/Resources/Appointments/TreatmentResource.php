<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Dtos\Appointments\TreatmentProgress;
use App\Models\Appointments\Treatment;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Treatment plan with its computed progress; diagnosis and plan only with patients.view_medical.
 *
 * @mixin Treatment
 */
final class TreatmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $medical = $request->user()?->can('patients.view_medical') ?? false;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'title' => $this->title,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => [
                'id' => $this->patient->id,
                'code' => $this->patient->person->code,
                'full_name' => $this->patient->person->full_name,
                'phone' => $this->patient->person->phone,
            ]),
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn (): array => [
                'id' => $this->doctor->id,
                'full_name' => $this->doctor->person->full_name,
                'specialty' => $this->doctor->relationLoaded('specialty') ? $this->doctor->specialty->name : null,
            ]),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment', fn (): ?array => $this->appointment === null ? null : [
                'id' => $this->appointment->id,
                'code' => $this->appointment->code,
                'scheduled_date' => $this->appointment->scheduled_date->toDateString(),
            ]),
            'billable_service_id' => $this->billable_service_id,
            'billable_service' => $this->whenLoaded('billableService', fn (): ?array => $this->billableService === null ? null : [
                'id' => $this->billableService->id,
                'name' => $this->billableService->name,
                'price' => MoneyHelper::toApi($this->billableService->price),
            ]),
            'diagnosis' => $this->when($medical, fn (): ?string => $this->diagnosis),
            'plan' => $this->when($medical, fn (): ?string => $this->plan),
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn ($s): string => $s->value, $this->status::transitions()[$this->status->value]),
            'planned_sessions' => $this->planned_sessions,
            'session_duration_minutes' => $this->session_duration_minutes,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'cancellation_reason' => $this->cancellation_reason,
            'progress' => $this->whenLoaded('sessions', fn (): array => TreatmentProgress::of($this->planned_sessions, $this->sessions, app(ClinicClock::class)->now())->toArray()),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
