<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Http\Requests;

use App\Enums\Appointments\BloodGroup;
use App\Modules\Appointments\Patients\Data\PatientData;
use App\Modules\Identity\Users\Http\Requests\PersonRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /patients (existing `person_id` or a new `person`) and PUT (medical / insurance data with `version`).
 */
final class PatientRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patient = [
            'blood_group' => ['sometimes', Rule::enum(BloodGroup::class)],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'chronic_conditions' => ['nullable', 'string', 'max:2000'],
            'family_history' => ['nullable', 'string', 'max:2000'],
            'surgical_history' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'insurance_company_id' => ['nullable', 'uuid'],
            'insurance_policy_number' => ['nullable', 'string', 'max:100'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'insurance_coverage_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->isMethod('PUT')) {
            return ['version' => ['required', 'integer', 'min:1'], ...$patient];
        }

        return [
            'person_id' => ['nullable', 'uuid', Rule::exists('persons', 'id')->where('is_deleted', 0)],
            ...PersonRules::nested('person'),
            'force_create' => ['sometimes', 'boolean'],
            ...$patient,
        ];
    }

    public function toData(): PatientData
    {
        return PatientData::fromArray($this->validated());
    }
}
