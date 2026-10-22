<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Http\Requests;

use App\Enums\Appointments\ScheduleExceptionType;
use App\Modules\Appointments\Schedules\Data\ScheduleExceptionData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleExceptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'start_time' => ['required_if:type,custom_hours', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:type,custom_hours', 'nullable', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
            'cancel_affected' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): ScheduleExceptionData
    {
        $type = ScheduleExceptionType::from($this->string('type')->toString());
        $custom = $type === ScheduleExceptionType::CustomHours;

        return new ScheduleExceptionData(
            date: CarbonImmutable::parse($this->string('date')->toString())->startOfDay(),
            type: $type,
            startTime: $custom ? $this->string('start_time')->toString() : null,
            endTime: $custom ? $this->string('end_time')->toString() : null,
            reason: $this->filled('reason') ? $this->string('reason')->toString() : null,
            cancelAffected: $this->boolean('cancel_affected'),
        );
    }
}
