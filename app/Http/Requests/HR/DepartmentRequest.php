<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\DepartmentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST (code and name required) and PUT (`sometimes`).
 */
final class DepartmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'alpha_dash:ascii', 'max:20'],
            'name' => [$required, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'parent_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where('is_deleted', 0)],
            'manager_id' => ['nullable', 'uuid'],
            'cost_center_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function toData(): DepartmentData
    {
        return DepartmentData::fromArray($this->validated());
    }
}
