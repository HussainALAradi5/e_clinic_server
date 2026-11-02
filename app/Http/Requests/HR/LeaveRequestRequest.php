<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\LeaveRequestData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Leave request: one calendar year; a half day is a single date. HR may file it for another employee.
 */
final class LeaveRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'uuid', Rule::exists('leave_types', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if (substr((string) $this->input('start_date'), 0, 4) !== substr((string) $this->input('end_date'), 0, 4)) {
                $v->errors()->add('end_date', __('errors.LEAVE_ONE_YEAR'));
            }
            if ($this->boolean('is_half_day') && $this->input('start_date') !== $this->input('end_date')) {
                $v->errors()->add('is_half_day', __('errors.LEAVE_HALF_DAY'));
            }
        }];
    }

    public function toData(): LeaveRequestData
    {
        return new LeaveRequestData(
            $this->string('leave_type_id')->toString(),
            CarbonImmutable::parse($this->string('start_date')->toString()),
            CarbonImmutable::parse($this->string('end_date')->toString()),
            $this->boolean('is_half_day'),
            $this->filled('reason') ? $this->string('reason')->toString() : null,
        );
    }
}
