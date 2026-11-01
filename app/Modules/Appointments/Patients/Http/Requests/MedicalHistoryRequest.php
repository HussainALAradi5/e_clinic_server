<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Http\Requests;

use App\Modules\Appointments\Patients\Data\PatientData;
use Illuminate\Foundation\Http\FormRequest;

/** A clinician updates the medical history of a patient in their care (absent fields stay as they are). */
final class MedicalHistoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'allergies' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'chronic_conditions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'family_history' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'surgical_history' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): PatientData
    {
        return PatientData::fromArray($this->safe()->except('version'));
    }
}
