<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Http\Requests;

use App\Modules\Appointments\Appointments\Data\RescheduleData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class RescheduleAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'provider_id' => ['nullable', 'uuid'],
            'room_id' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): RescheduleData
    {
        return new RescheduleData(
            date: CarbonImmutable::parse($this->string('scheduled_date')->toString())->startOfDay(),
            startTime: $this->string('start_time')->toString(),
            providerId: $this->filled('provider_id') ? $this->string('provider_id')->toString() : null,
            roomId: $this->filled('room_id') ? $this->string('room_id')->toString() : null,
        );
    }
}
