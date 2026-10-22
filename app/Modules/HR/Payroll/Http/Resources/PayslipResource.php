<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Http\Resources;

use App\Modules\HR\Payroll\Models\Payslip;
use App\Modules\HR\Payroll\Models\PayslipItem;
use App\Shared\Http\Resources\Summary;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee payslip; lines, bank details and the run when loaded.
 *
 * @mixin Payslip
 */
final class PayslipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'run' => $this->whenLoaded('run', fn (): array => [
                'id' => $this->run->id, 'code' => $this->run->code, 'status' => $this->run->status->value,
                'period_start' => $this->run->periodStart()->toDateString(), 'paid_on' => $this->run->paid_on?->toDateString(),
            ]),
            'employee' => $this->whenLoaded('employee', fn (): array => [
                ...Summary::employee($this->employee),
                'bank_name' => $this->employee->bank_name,
                'iban_last4' => $this->employee->iban !== null ? substr($this->employee->iban, -4) : null,
                'social_insurance_number' => $this->employee->social_insurance_number,
            ]),
            'department' => $this->whenLoaded('department', fn (): array => ['id' => $this->department->id, 'name' => $this->department->name]),
            'basic_salary' => MoneyHelper::toApi($this->basic_salary),
            'working_days' => $this->working_days,
            'present_days' => (string) $this->present_days,
            'absent_days' => (string) $this->absent_days,
            'paid_leave_days' => (string) $this->paid_leave_days,
            'unpaid_leave_days' => (string) $this->unpaid_leave_days,
            'overtime_minutes' => $this->overtime_minutes,
            'total_allowances' => MoneyHelper::toApi($this->total_allowances),
            'overtime_amount' => MoneyHelper::toApi($this->overtime_amount),
            'gross_salary' => MoneyHelper::toApi($this->gross_salary),
            'total_deductions' => MoneyHelper::toApi($this->total_deductions),
            'net_salary' => MoneyHelper::toApi($this->net_salary),
            'carried_forward' => MoneyHelper::toApi($this->carried_forward),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn (PayslipItem $i): array => [
                'type' => $i->type->value, 'is_earning' => $i->type->isEarning(), 'name' => $i->name, 'amount' => MoneyHelper::toApi($i->amount),
            ])->all()),
        ];
    }
}
