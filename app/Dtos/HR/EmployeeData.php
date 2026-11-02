<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Dtos\Identity\PersonData;
use App\Enums\Identity\UserType;
use Illuminate\Support\Arr;

/**
 * Hire input (person reused by id or created, account type, roles) or employment changes (only provided keys).
 */
final readonly class EmployeeData
{
    private const FIELDS = ['department_id', 'job_title', 'employment_type', 'hire_date', 'probation_end_date', 'work_start_time', 'work_end_time', 'weekly_off_days', 'basic_salary', 'bank_name', 'iban', 'social_insurance_number'];

    /**
     * @param  array<string, mixed>  $values  employee columns
     * @param  list<string>  $roles
     */
    private function __construct(
        public array $values,
        public ?string $personId,
        public ?PersonData $person,
        public bool $forcePerson,
        public ?UserType $userType,
        public array $roles,
        public bool $canLogin,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS));
        if (isset($values['basic_salary'])) {
            $values['basic_salary'] = (string) $values['basic_salary'];
        }
        if (isset($values['weekly_off_days'])) {
            $values['weekly_off_days'] = array_values(array_unique(array_map('intval', (array) $values['weekly_off_days'])));
        }

        return new self(
            values: $values,
            personId: isset($input['person_id']) ? (string) $input['person_id'] : null,
            person: is_array($input['person'] ?? null) ? PersonData::fromArray(Arr::except($input['person'], [])) : null,
            forcePerson: (bool) ($input['force_create'] ?? false),
            userType: isset($input['user_type']) ? UserType::from((string) $input['user_type']) : null,
            roles: array_values(array_map('strval', (array) ($input['roles'] ?? []))),
            canLogin: (bool) ($input['can_login'] ?? true),
        );
    }

    public function changesSalary(): bool
    {
        return Arr::hasAny($this->values, ['basic_salary', 'bank_name', 'iban', 'social_insurance_number']);
    }
}
