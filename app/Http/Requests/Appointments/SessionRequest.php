<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\SessionBookingData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SessionRequest extends FormRequest
{
    /**
     * Shared by the booking of one session and the first sessions of a new plan.
     *
     * @return array<string, mixed>
     */
    public static function sessionRules(string $prefix = ''): array
    {
        return [
            "{$prefix}provider_id" => ['required', 'uuid', Rule::exists('employees', 'id')],
            "{$prefix}scheduled_date" => ['required', 'date_format:Y-m-d'],
            "{$prefix}start_time" => ['required', 'date_format:H:i'],
            "{$prefix}end_time" => ['nullable', 'date_format:H:i', "after:{$prefix}start_time"],
            "{$prefix}room_id" => ['nullable', 'uuid', Rule::exists('rooms', 'id')],
            "{$prefix}reason" => ['nullable', 'string', 'max:500'],
            "{$prefix}notes" => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...self::sessionRules(), 'override_priority_deadline' => ['boolean']];
    }

    public function toData(): SessionBookingData
    {
        return SessionBookingData::fromArray($this->validated());
    }
}
