<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Http\Requests;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentType;
use App\Modules\Appointments\Appointments\Data\BookAppointmentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BookAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(AppointmentType::class)->except([AppointmentType::TreatmentSession])],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'source' => ['required', Rule::enum(AppointmentSource::class)],
            'room_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): BookAppointmentData
    {
        return BookAppointmentData::fromArray($this->validated());
    }
}
