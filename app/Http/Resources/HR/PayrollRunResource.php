<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Http\Resources\Shared\Summary;
use App\Models\HR\PayrollRun;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Monthly payroll run with totals (payslips when loaded).
 *
 * @mixin PayrollRun
 */
final class PayrollRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'period_start' => $this->periodStart()->toDateString(),
            'period_end' => $this->periodEnd()->toDateString(),
            'status' => $this->status->value,
            'total_gross' => MoneyHelper::toApi($this->total_gross),
            'total_deductions' => MoneyHelper::toApi($this->total_deductions),
            'total_net' => MoneyHelper::toApi($this->total_net),
            'employee_count' => $this->employee_count,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'calculated_by' => $this->whenLoaded('calculator', fn (): ?array => Summary::user($this->calculator)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->whenLoaded('approver', fn (): ?array => Summary::user($this->approver)),
            'paid_on' => $this->paid_on?->toDateString(),
            'payment_reference' => $this->payment_reference,
            'accrual_journal_entry_id' => $this->accrual_journal_entry_id,
            'payment_journal_entry_id' => $this->payment_journal_entry_id,
            'cancellation_reason' => $this->cancellation_reason,
            'payslips' => PayslipResource::collection($this->whenLoaded('payslips')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
