<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Http\Resources;

use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Http\Resources\PersonResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Patient file with the person's data. Blood group, allergies and chronic conditions only with patients.view_medical;
 * staff notes are not shown to the patient themselves.
 *
 * @mixin Patient
 */
final class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = $this->person;
        $medical = $request->user()?->can('patients.view_medical') ?? false;

        return [
            'id' => $this->id,
            'code' => $person->code,
            'full_name' => $person->full_name,
            'gender' => $person->gender->value,
            'date_of_birth' => $person->date_of_birth->toDateString(),
            'age' => $person->date_of_birth->age,
            'phone' => $person->phone,
            'email' => $person->email,
            'person' => new PersonResource($person),
            'blood_group' => $this->when($medical, fn (): string => $this->blood_group->value),
            'allergies' => $this->when($medical, fn (): ?string => $this->allergies),
            'chronic_conditions' => $this->when($medical, fn (): ?string => $this->chronic_conditions),
            'has_medical_alerts' => $medical ? filled($this->allergies) : null,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'insurance_company_id' => $this->insurance_company_id,
            'insurer' => $this->whenLoaded('insurer', fn (): ?array => $this->insurer === null ? null : ['id' => $this->insurer->id, 'name' => $this->insurer->name]),
            'insurance_policy_number' => $this->insurance_policy_number,
            'insurance_expiry_date' => $this->insurance_expiry_date?->toDateString(),
            'insurance_coverage_percent' => $this->insurance_coverage_percent,
            // Front-desk notes are for staff, not for the patient reading their own file in the portal.
            'notes' => $this->when($request->user()?->getAuthIdentifier() !== $this->id, fn (): ?string => $this->notes),
            'has_portal_account' => $this->whenLoaded('user', fn (): bool => $this->user !== null),
            'appointments_count' => $this->whenCounted('appointments'),
            'upcoming_appointments_count' => $this->whenCounted('upcoming_appointments'),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
