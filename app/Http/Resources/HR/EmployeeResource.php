<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Http\Resources\Identity\PersonResource;
use App\Models\HR\Employee;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee with person and account summary. Salary data only with employees.view_salary (IBAN always masked).
 *
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = $this->person;
        $canSeeSalary = $request->user()?->can('employees.view_salary') ?? false;

        return [
            'id' => $this->id,
            'code' => $person->code,
            'full_name' => $person->full_name,
            'person' => new PersonResource($person),
            'account' => $this->whenLoaded('user', fn (): array => [
                'user_type' => $this->user->user_type->value,
                'status' => $this->user->status->value,
                'roles' => $this->user->relationLoaded('roles') ? $this->user->getRoleNames()->values()->all() : [],
            ]),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (): array => ['id' => $this->department->id, 'code' => $this->department->code, 'name' => $this->department->name]),
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type->value,
            'status' => $this->status->value,
            'hire_date' => $this->hire_date->toDateString(),
            'probation_end_date' => $this->probation_end_date?->toDateString(),
            'termination_date' => $this->termination_date?->toDateString(),
            'termination_reason' => $this->termination_reason?->value,
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift', fn (): ?array => $this->shift === null ? null : ['id' => $this->shift->id, 'code' => $this->shift->code, 'name' => $this->shift->name, 'type' => $this->shift->type->value]),
            'work_start_time' => substr($this->work_start_time, 0, 5),
            'work_end_time' => substr($this->work_end_time, 0, 5),
            'weekly_off_days' => $this->weekly_off_days,
            'basic_salary' => $this->when($canSeeSalary, fn (): array => MoneyHelper::toApi($this->basic_salary)),
            'bank_name' => $this->when($canSeeSalary, fn (): ?string => $this->bank_name),
            'iban' => $this->when($canSeeSalary, fn (): ?string => $this->iban !== null ? mask($this->iban) : null),
            'social_insurance_number' => $this->when($canSeeSalary, fn (): ?string => $this->social_insurance_number),
            'salary_components' => $this->when($canSeeSalary, fn () => SalaryComponentResource::collection($this->whenLoaded('salaryComponents'))),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
