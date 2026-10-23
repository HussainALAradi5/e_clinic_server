<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Models;

use App\Enums\HR\PayrollStatus;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Casts\MoneyCast;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly payroll: draft → calculated → approved (salary accrual posted, attendance locked) → paid (bank settlement
 * posted); cancelled before payment. Code `PR-2026-10` (a re-run after a cancellation gets `-2`, `-3` …).
 *
 * @property string $id
 * @property string $code
 * @property int $period_year
 * @property int $period_month
 * @property PayrollStatus $status
 * @property Money $total_gross
 * @property Money $total_deductions
 * @property Money $total_net
 * @property int $employee_count
 * @property ?CarbonImmutable $calculated_at
 * @property ?string $calculated_by
 * @property ?string $approved_by
 * @property ?CarbonImmutable $approved_at
 * @property ?CarbonImmutable $paid_on
 * @property ?string $payment_reference
 * @property ?string $accrual_journal_entry_id
 * @property ?string $payment_journal_entry_id
 * @property ?string $cancellation_reason
 * @property ?string $notes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Collection<int, Payslip> $payslips
 * @property-read ?User $calculator
 * @property-read ?User $approver
 * @property-read ?JournalEntry $accrualEntry
 * @property-read ?JournalEntry $paymentEntry
 */
class PayrollRun extends BaseModel
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'PR';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('payroll_runs')
            ->sortable('period_year', 'period_month', 'code', 'status', 'total_net', 'created_at')
            ->filterable('status', ['eq', 'in', 'nin'], enum: PayrollStatus::class)
            ->filterable('period_year', ['eq'], type: 'number', label: 'Year')
            ->searchIn('code', 'payment_reference')
            ->column('code', 'Payroll no.')
            ->column('period', 'Month')
            ->column('employee_count', 'Employees')
            ->column('total_gross', 'Gross')
            ->column('total_deductions', 'Deductions')
            ->column('total_net', 'Net')
            ->column('status', 'Status')
            ->dateFields('created_at', 'paid_on')
            ->defaultSort('-period_year,-period_month');
    }

    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%04d-%02d-01', $this->period_year, $this->period_month))->startOfDay();
    }

    public function periodEnd(): CarbonImmutable
    {
        return $this->periodStart()->endOfMonth()->startOfDay();
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function accrualEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'accrual_journal_entry_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function paymentEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'status' => PayrollStatus::class,
            'total_gross' => MoneyCast::class,
            'total_deductions' => MoneyCast::class,
            'total_net' => MoneyCast::class,
            'employee_count' => 'integer',
            'calculated_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
