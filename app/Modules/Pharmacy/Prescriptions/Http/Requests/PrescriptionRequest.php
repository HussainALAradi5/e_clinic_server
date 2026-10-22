<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Http\Requests;

use App\Enums\Pharmacy\MedicationRoute;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Write / edit a prescription (PUT replaces the medicines of a not-yet-dispensed one).
 */
final class PrescriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid', Rule::exists('patients', 'id')],
            'doctor_id' => ['nullable', 'uuid', Rule::exists('doctors', 'id')],
            'appointment_id' => ['nullable', 'uuid', Rule::exists('appointments', 'id')->where('is_deleted', 0)],
            'treatment_id' => ['nullable', 'uuid', Rule::exists('treatments', 'id')->where('is_deleted', 0)],
            'valid_until' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.medication_id' => ['required', 'uuid', 'distinct', Rule::exists('medications', 'id')->where('is_archived', 0)->where('is_deleted', 0)],
            'items.*.dose' => ['required', 'string', 'max:50'],
            'items.*.frequency' => ['required', 'string', 'max:50'],
            'items.*.route' => ['required', Rule::enum(MedicationRoute::class)],
            'items.*.duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'items.*.quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:100000'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
            'override_duplicate' => ['sometimes', 'boolean'],
            'override_allergy' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): PrescriptionData
    {
        return PrescriptionData::fromArray($this->validated());
    }
}
