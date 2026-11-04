<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\Appointment;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Booking with patient / provider names, room and billed service; `allowed_transitions` drives the action buttons.
 *
 * @mixin Appointment
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => [
                'id' => $this->patient->id,
                'code' => $this->patient->person->code,
                'full_name' => $this->patient->person->full_name,
                'phone' => $this->patient->person->phone,
                'gender' => $this->patient->person->gender->value,
                'date_of_birth' => $this->patient->person->date_of_birth->toDateString(),
            ]),
            'provider_id' => $this->provider_id,
            'provider' => $this->whenLoaded('provider', fn (): array => [
                'id' => $this->provider->id,
                'full_name' => $this->provider->person->full_name,
                'job_title' => $this->provider->job_title,
            ]),
            'type' => $this->type->value,
            'treatment_id' => $this->treatment_id,
            'session_number' => $this->session_number,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'starts_at' => $this->startsAt()->format('Y-m-d\TH:i:s'),
            'ends_at' => $this->endsAt()->format('Y-m-d\TH:i:s'),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn (): ?array => $this->room === null ? null : ['id' => $this->room->id, 'code' => $this->room->code, 'name' => $this->room->name]),
            'billable_service' => $this->whenLoaded('billableService', fn (): ?array => $this->billableService === null ? null : [
                'id' => $this->billableService->id,
                'name' => $this->billableService->name,
                'price' => MoneyHelper::toApi($this->billableService->price),
            ]),
            'status' => $this->status->value,
            'timing' => $this->timing(app(ClinicClock::class)->today())->value,
            'allowed_transitions' => array_map(fn ($s): string => $s->value, $this->status::transitions()[$this->status->value]),
            'is_attended' => $this->is_attended,
            'source' => $this->source->value,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoiceLine', fn (): ?array => $this->invoiceSummary()),
            'rescheduled_from_id' => $this->rescheduled_from_id,
            'rescheduled_from' => $this->whenLoaded('rescheduledFrom', fn (): ?array => $this->rescheduledFrom === null ? null : [
                'id' => $this->rescheduledFrom->id,
                'code' => $this->rescheduledFrom->code,
                'scheduled_date' => $this->rescheduledFrom->scheduled_date->toDateString(),
                'start_time' => substr($this->rescheduledFrom->start_time, 0, 5),
            ]),
            'cancelled_by' => $this->cancelled_by?->value,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            // Desk and apps show Check in from this moment (1 hour before, on the day) and the patient's own
            // Cancel / Reschedule only while patient_can_change (3 days or more before).
            'check_in_opens_at' => $this->checkInOpensAt()->toIso8601String(),
            'patient_can_change' => $this->patientMayChange(app(ClinicClock::class)->now()),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
