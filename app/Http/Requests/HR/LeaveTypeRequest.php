<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\LeaveTypeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LeaveTypeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('type');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('leave_types', 'code')->ignore(is_string($type) ? $type : null)],
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'annual_quota_days' => ['required', 'numeric', 'between:0,366', 'multiple_of:0.5'],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'max_carry_forward_days' => ['required', 'numeric', 'between:0,366', 'multiple_of:0.5'],
            'min_notice_days' => ['required', 'integer', 'between:0,365'],
        ];
    }

    public function toData(): LeaveTypeData
    {
        return new LeaveTypeData($this->safe()->all());
    }
}
