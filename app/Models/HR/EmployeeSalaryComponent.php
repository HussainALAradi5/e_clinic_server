<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Casts\MoneyCast;
use App\Enums\HR\SalaryComponentType;
use App\Models\Base\BaseModel;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurring monthly allowance or deduction of an employee (applied by payroll).
 *
 * @property string $id
 * @property string $code
 * @property string $employee_id
 * @property SalaryComponentType $type
 * @property string $name
 * @property Money $amount
 * @property CarbonImmutable $effective_from
 * @property ?CarbonImmutable $effective_to
 * @property bool $is_taxable
 * @property-read Employee $employee
 */
class EmployeeSalaryComponent extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'ESC';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'amount' => MoneyCast::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'is_taxable' => 'boolean',
        ];
    }
}
