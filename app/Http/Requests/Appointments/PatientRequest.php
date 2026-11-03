<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\EmergencyContactData;
use App\Dtos\Appointments\PatientData;
use App\Enums\Appointments\BloodGroup;
use App\Http\Requests\Identity\PersonRules;
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
        // Only doctors write the histories (others never see them).
        $history = $this->user()?->can('patients.view_history') ? ['nullable', 'string', 'max:2000'] : ['prohibited'];
        $patient = [
            'blood_group' => ['sometimes', Rule::enum(BloodGroup::class)],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'chronic_conditions' => $history,
            'family_history' => $history,
            'surgical_history' => $history,
            ...EmergencyContactData::rules(),
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
