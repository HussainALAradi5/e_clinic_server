<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Http\Requests;

use App\Enums\Payments\ServiceCategory;
use App\Modules\Appointments\Doctors\Data\DoctorData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /doctors (profile for an existing employee) and PUT (`sometimes`).
 */
final class DoctorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        $service = Rule::exists('billable_services', 'id')->where('is_deleted', 0)->where('is_archived', 0)->where('category', ServiceCategory::Consultation->value);
        $doctorId = $this->route('doctor');

        return [
            'employee_id' => $creating ? ['required', 'uuid'] : ['prohibited'],
            'specialty_id' => [$required, 'uuid', Rule::exists('specialties', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'license_number' => [$required, 'string', 'max:50', Rule::unique('doctors', 'license_number')->ignore(is_string($doctorId) ? $doctorId : null)],
            'license_expiry_date' => [$required, 'date', 'after:today'],
            'title' => ['nullable', 'string', 'max:50'],
            'consultation_service_id' => [$required, 'uuid', $service],
            'follow_up_service_id' => [$required, 'uuid', $service],
            'default_slot_minutes' => [$required, 'integer', 'between:5,240'],
            'max_daily_appointments' => ['nullable', 'integer', 'min:1', 'max:200'],
            'default_room_id' => ['nullable', 'uuid', Rule::exists('rooms', 'id')->where('is_deleted', 0)],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): DoctorData
    {
        return DoctorData::fromArray($this->validated());
    }
}
