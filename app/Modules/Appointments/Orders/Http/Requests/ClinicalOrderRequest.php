<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Http\Requests;

use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Appointments\TreatmentPriority;
use App\Modules\Appointments\Orders\Data\ClinicalOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClinicalOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $create = $this->isMethod('POST');
        $required = $create ? 'required' : 'sometimes';
        $onCreate = $create ? 'nullable' : 'prohibited';
        $referral = $this->input('type') === ClinicalOrderType::Referral->value;

        return [
            'version' => [$create ? 'prohibited' : 'required', 'integer'],
            'type' => [$create ? 'required' : 'prohibited', Rule::enum(ClinicalOrderType::class)],
            'patient_id' => [$create ? 'required' : 'prohibited', 'uuid', Rule::exists('patients', 'id')],
            'ordered_by' => [$onCreate, 'uuid', Rule::exists('doctors', 'id')],
            'appointment_id' => [$onCreate, 'uuid', Rule::exists('appointments', 'id')->where('patient_id', $this->input('patient_id'))],
            'treatment_id' => ['nullable', 'uuid', Rule::exists('treatments', 'id')->where('patient_id', $this->input('patient_id'))],
            'billable_service_id' => [$create && ! $referral ? 'required' : 'nullable', 'uuid', Rule::exists('billable_services', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'referred_to_doctor_id' => ['nullable', 'uuid', Rule::exists('doctors', 'id')],
            'referred_to_specialty_id' => ['nullable', 'uuid', Rule::exists('specialties', 'id')->where('is_deleted', 0)],
            'referred_to_company_id' => ['nullable', 'uuid', Rule::exists('companies', 'id')->where('is_deleted', 0)],
            'body_part' => ['nullable', 'string', 'max:100'],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
            'priority' => [$required, Rule::enum(TreatmentPriority::class)],
        ];
    }

    public function toData(): ClinicalOrderData
    {
        return ClinicalOrderData::fromArray($this->validated());
    }
}
