<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Http\Requests;

use App\Enums\HR\EmploymentType;
use App\Enums\Identity\UserType;
use App\Modules\HR\Employees\Data\EmployeeData;
use App\Modules\Identity\Users\Http\Requests\PersonRules;
use App\Shared\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /hr/employees (hire: person + account + employment) and PUT (employment data only, with `version`).
 */
final class EmployeeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hiring = $this->isMethod('POST');
        $required = $hiring ? 'required' : 'sometimes';
        $staffTypes = array_values(array_filter(UserType::cases(), fn (UserType $t): bool => ! $t->isPatient()));

        $employment = [
            'department_id' => [$required, 'uuid', Rule::exists('departments', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'job_title' => [$required, 'string', 'max:150'],
            'employment_type' => [$required, Rule::enum(EmploymentType::class)],
            'hire_date' => [$required, 'date'],
            'probation_end_date' => ['nullable', 'date', 'after:hire_date'],
            'work_start_time' => [$required, 'date_format:H:i'],
            'work_end_time' => [$required, 'date_format:H:i', 'after:work_start_time'],
            'weekly_off_days' => ['sometimes', 'array', 'max:6'],
            'weekly_off_days.*' => ['integer', Rule::enum(DayOfWeek::class)],
            'basic_salary' => [$required, 'decimal:0,3', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'regex:/^BH\d{2}[A-Z]{4}[A-Z0-9]{14}$/'],
            'social_insurance_number' => ['nullable', 'string', 'max:30'],
        ];

        if (! $hiring) {
            return ['version' => ['required', 'integer', 'min:1'], ...$employment];
        }

        return [
            'person_id' => ['nullable', 'uuid', Rule::exists('persons', 'id')->where('is_deleted', 0)],
            ...PersonRules::nested('person', staff: true),
            'force_create' => ['sometimes', 'boolean'],
            ...$employment,
            'user_type' => ['required', Rule::enum(UserType::class)->only($staffTypes)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('is_deleted', 0)],
            'can_login' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): EmployeeData
    {
        return EmployeeData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('iban')) {
            $this->merge(['iban' => strtoupper(str_replace(' ', '', $this->string('iban')->toString()))]);
        }
    }
}
