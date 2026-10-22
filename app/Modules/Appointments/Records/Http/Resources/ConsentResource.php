<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Http\Resources;

use App\Modules\Appointments\Records\Models\PatientConsent;
use App\Modules\Identity\Attachments\Http\Resources\AttachmentResource;
use App\Shared\Support\ClinicClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientConsent
 */
final class ConsentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn (string $relation): ?array => $this->{$relation} === null ? null : ['id' => $this->{$relation}->id, 'full_name' => $this->{$relation}->person->full_name];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'patient_id' => $this->patient_id,
            'type' => $this->type->value,
            'scope' => match (true) {
                $this->appointment_id !== null => ['kind' => 'appointment', 'id' => $this->appointment_id, 'code' => $this->appointment?->code],
                $this->treatment_id !== null => ['kind' => 'treatment', 'id' => $this->treatment_id, 'code' => $this->treatment?->code],
                $this->clinical_order_id !== null => ['kind' => 'clinical_order', 'id' => $this->clinical_order_id, 'code' => $this->clinicalOrder?->code],
                default => null,
            },
            'description' => $this->description,
            'signed_by_name' => $this->signed_by_name,
            'signer_relationship' => $this->signer_relationship,
            'explained_by' => $this->whenLoaded('explainer', fn (): ?array => $person('explainer')),
            'witness' => $this->whenLoaded('witness', fn (): ?array => $person('witness')),
            'signed_at' => $this->signed_at->toIso8601String(),
            'valid_until' => $this->valid_until?->toDateString(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revocation_reason' => $this->revocation_reason,
            'is_valid' => $this->isValid(app(ClinicClock::class)->today()),
            'attachments' => $this->whenLoaded('attachments', fn () => AttachmentResource::collection($this->attachments)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
