<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Portal\Http\Requests;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentType;
use App\Modules\Appointments\Appointments\Data\BookAppointmentData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /portal/appointments — the signed-in patient books a visit with a doctor (never for someone else). */
final class PortalBookingRequest extends FormRequest
{
    /** Kinds a patient may book online; procedures, emergencies and sessions go through the clinic. */
    public const TYPES = [AppointmentType::Consultation, AppointmentType::FollowUp];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(AppointmentType::class)->only(self::TYPES)],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(string $patientId): BookAppointmentData
    {
        return new BookAppointmentData(
            patientId: $patientId,
            providerId: $this->string('doctor_id')->toString(),
            type: AppointmentType::from($this->string('type')->toString()),
            date: CarbonImmutable::parse($this->string('scheduled_date')->toString())->startOfDay(),
            startTime: $this->string('start_time')->toString(),
            source: AppointmentSource::Portal,
            reason: $this->filled('reason') ? $this->string('reason')->toString() : null,
        );
    }
}
