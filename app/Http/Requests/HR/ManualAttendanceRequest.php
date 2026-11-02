<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\ManualAttendanceData;
use App\Enums\HR\AttendanceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HR correction: times of the day (clinic time) or, without a check-in, what the day was; always with a reason.
 */
final class ManualAttendanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'check_in' => ['nullable', 'date_format:H:i', 'required_with:check_out'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class), 'required_without:check_in'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function toData(): ManualAttendanceData
    {
        return new ManualAttendanceData(
            $this->string('employee_id')->toString(),
            CarbonImmutable::parse($this->string('date')->toString()),
            $this->filled('check_in') ? $this->string('check_in')->toString() : null,
            $this->filled('check_out') ? $this->string('check_out')->toString() : null,
            $this->filled('status') ? AttendanceStatus::from($this->string('status')->toString()) : null,
            $this->string('reason')->toString(),
        );
    }
}
