<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Http\Resources;

use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Identity\Attachments\Http\Resources\AttachmentResource;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order / referral; clinical notes and the report only with patients.view_medical — the report and images of a
 * completed order also to its own patient (portal results).
 *
 * @mixin ClinicalOrder
 */
final class ClinicalOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $medical = $request->user()?->can('patients.view_medical') ?? false;
        // Portal: a completed order's report and images are released to the patient it belongs to.
        $user = $request->user();
        $released = $medical || ($user !== null && $this->isReleasedTo($user));
        $doctor = fn (string $relation): ?array => $this->{$relation} === null ? null : ['id' => $this->{$relation}->id, 'full_name' => $this->{$relation}->person->full_name];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'description' => $this->description(),
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => [
                'id' => $this->patient->id,
                'code' => $this->patient->person->code,
                'full_name' => $this->patient->person->full_name,
                'date_of_birth' => $this->patient->person->date_of_birth->toDateString(),
                'gender' => $this->patient->person->gender->value,
            ]),
            'ordered_by' => $this->ordered_by,
            'ordering_doctor' => $this->whenLoaded('orderedBy', fn (): ?array => $doctor('orderedBy')),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment', fn (): ?array => $this->appointment === null ? null : ['id' => $this->appointment->id, 'code' => $this->appointment->code]),
            'treatment_id' => $this->treatment_id,
            'treatment' => $this->whenLoaded('treatment', fn (): ?array => $this->treatment === null ? null : ['id' => $this->treatment->id, 'code' => $this->treatment->code, 'title' => $this->treatment->title]),
            'billable_service_id' => $this->billable_service_id,
            'billable_service' => $this->whenLoaded('billableService', fn (): ?array => $this->billableService === null ? null : [
                'id' => $this->billableService->id,
                'code' => $this->billableService->code,
                'name' => $this->billableService->name,
                'price' => MoneyHelper::toApi($this->billableService->price),
                'consent_type' => $this->billableService->consent_type?->value,
            ]),
            'referred_to_doctor_id' => $this->referred_to_doctor_id,
            'referred_to_doctor' => $this->whenLoaded('referredToDoctor', fn (): ?array => $doctor('referredToDoctor')),
            'referred_to_specialty_id' => $this->referred_to_specialty_id,
            'referred_to_specialty' => $this->whenLoaded('referredToSpecialty', fn (): ?array => $this->referredToSpecialty === null ? null : ['id' => $this->referredToSpecialty->id, 'name' => $this->referredToSpecialty->name]),
            'referred_to_company_id' => $this->referred_to_company_id,
            'referred_to_company' => $this->whenLoaded('referredToCompany', fn (): ?array => $this->referredToCompany === null ? null : ['id' => $this->referredToCompany->id, 'name' => $this->referredToCompany->name]),
            'referral_appointment_id' => $this->referral_appointment_id,
            'referral_appointment' => $this->whenLoaded('referralAppointment', fn (): ?array => $this->referralAppointment === null ? null : [
                'id' => $this->referralAppointment->id,
                'code' => $this->referralAppointment->code,
                'scheduled_date' => $this->referralAppointment->scheduled_date->toDateString(),
                'start_time' => substr($this->referralAppointment->start_time, 0, 5),
                'status' => $this->referralAppointment->status->value,
            ]),
            'body_part' => $this->body_part,
            'clinical_notes' => $this->when($medical, fn (): ?string => $this->clinical_notes),
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn ($s): string => $s->value, $this->status::transitions()[$this->status->value]),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn (): ?array => $this->room === null ? null : ['id' => $this->room->id, 'code' => $this->room->code, 'name' => $this->room->name]),
            'performer' => $this->whenLoaded('performer', fn (): ?array => $this->performer === null ? null : ['id' => $this->performer->id, 'full_name' => $this->performer->person->full_name]),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'invoice' => $this->whenLoaded('invoiceLine', fn (): ?array => $this->invoiceSummary()),
            'report' => $this->when($released, fn (): ?string => $this->report),
            'reporter' => $this->whenLoaded('reporter', fn (): ?array => $doctor('reporter')),
            'is_abnormal' => $this->is_abnormal,
            'cancellation_reason' => $this->cancellation_reason,
            'attachments' => $this->when($released && $this->relationLoaded('attachments'), fn () => AttachmentResource::collection($this->attachments)),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** One line for lists: service name / body part, or the referral target. */
    private function description(): string
    {
        if ($this->isReferral()) {
            $target = $this->referredToDoctor->person->full_name ?? $this->referredToSpecialty->name ?? $this->referredToCompany->name ?? '';

            return trim('Referral to '.$target);
        }

        return trim(($this->billableService->name ?? $this->type->label()).($this->body_part !== null ? ' — '.$this->body_part : ''));
    }
}
