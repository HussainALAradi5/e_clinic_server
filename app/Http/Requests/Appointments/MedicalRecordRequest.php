<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\MedicalRecordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MedicalRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer'],
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'assessment' => ['nullable', 'string', 'max:10000'],
            'plan' => ['nullable', 'string', 'max:10000'],
            'vitals' => ['nullable', 'array'],
            'vitals.temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'vitals.pulse_bpm' => ['nullable', 'integer', 'between:20,300'],
            'vitals.systolic_bp' => ['nullable', 'integer', 'between:50,300'],
            'vitals.diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'vitals.spo2_percent' => ['nullable', 'integer', 'between:0,100'],
            'vitals.weight_kg' => ['nullable', 'numeric', 'between:0.3,500'],
            'vitals.height_cm' => ['nullable', 'numeric', 'between:20,260'],
            'diagnoses' => ['sometimes', 'array', 'max:20'],
            'diagnoses.*.icd10_code_id' => ['required', 'uuid', Rule::exists('icd10_codes', 'id')],
            'diagnoses.*.is_primary' => ['boolean'],
            'diagnoses.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): MedicalRecordData
    {
        return MedicalRecordData::fromArray($this->validated());
    }
}
