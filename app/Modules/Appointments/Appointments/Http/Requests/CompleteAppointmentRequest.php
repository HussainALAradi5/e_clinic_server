<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Http\Requests;

use App\Modules\Appointments\Appointments\Data\CompleteAppointmentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billable_service_ids' => ['nullable', 'array', 'max:50'],
            'billable_service_ids.*' => ['uuid', Rule::exists('billable_services', 'id')->where('is_deleted', 0)],
            'follow_up_in_days' => ['nullable', 'integer', 'between:1,180'],
        ];
    }

    public function toData(): CompleteAppointmentData
    {
        return new CompleteAppointmentData(
            array_values(array_map('strval', (array) $this->input('billable_service_ids', []))),
            $this->filled('follow_up_in_days') ? $this->integer('follow_up_in_days') : null,
        );
    }
}
