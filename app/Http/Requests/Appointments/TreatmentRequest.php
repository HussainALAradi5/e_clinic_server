<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\TreatmentData;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Appointments\TreatmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TreatmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $create = $this->isMethod('POST');
        $required = $create ? 'required' : 'sometimes';

        return [
            'version' => [$create ? 'prohibited' : 'required', 'integer'],
            'patient_id' => [$create ? 'required' : 'prohibited', 'uuid', Rule::exists('patients', 'id')],
            'doctor_id' => [$create ? 'required' : 'prohibited', 'uuid', Rule::exists('doctors', 'id')],
            'appointment_id' => [$create ? 'nullable' : 'prohibited', 'uuid', Rule::exists('appointments', 'id')->where('patient_id', $this->input('patient_id'))],
            'type' => [$required, Rule::enum(TreatmentType::class)],
            'billable_service_id' => ['nullable', 'uuid', Rule::exists('billable_services', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'title' => [$required, 'string', 'max:150'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
            'priority' => [$create ? 'required' : 'prohibited', Rule::enum(TreatmentPriority::class)],
            'planned_sessions' => [$required, 'integer', 'min:1', 'max:100'],
            'session_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'sessions' => [$create ? 'nullable' : 'prohibited', 'array', 'max:20'],
            ...SessionRequest::sessionRules('sessions.*.'),
            'override_priority_deadline' => ['boolean'],
        ];
    }

    public function toData(): TreatmentData
    {
        return TreatmentData::fromArray($this->validated());
    }
}
