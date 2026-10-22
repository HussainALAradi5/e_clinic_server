<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Models;

use App\Enums\Transactions\CashSessionStatus;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cash drawer shift of one cashier. Expected cash is computed from its documents (no running totals stored);
 * `closing` = the counted difference waits for a supervisor.
 *
 * @property string $id
 * @property string $code
 * @property string $cashier_id
 * @property CarbonImmutable $opened_at
 * @property Money $opening_float
 * @property ?Money $expected_closing
 * @property ?Money $counted_closing
 * @property ?Money $difference
 * @property ?array<string, int> $denominations
 * @property CashSessionStatus $status
 * @property ?CarbonImmutable $closed_at
 * @property ?string $approved_by
 * @property ?Money $deposit_amount
 * @property ?CarbonImmutable $deposited_at
 * @property ?string $deposit_reference
 * @property ?string $adjustment_journal_entry_id
 * @property ?string $deposit_journal_entry_id
 * @property ?string $notes
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read User $cashier
 * @property-read ?User $approver
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 * @property-read Collection<int, Expense> $expenses
 */
class CashSession extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'CSH';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('cash_sessions')
            ->sortable('opened_at', 'closed_at', 'code', 'status', 'difference')
            ->filterable('status', ['eq', 'in'], enum: CashSessionStatus::class)
            ->filterable('cashier_id', ['eq', 'in'], resource: 'users')
            ->filterable('code', ['eq', 'starts'])
            ->filterable('deposited_at', ['null'], type: 'date')
            ->searchIn('code')
            ->includes('cashier.person')
            ->column('code', 'Session')
            ->column('cashier', 'Cashier')
            ->column('opened_at', 'Opened')
            ->column('closed_at', 'Closed')
            ->column('status', 'Status')
            ->column('difference', 'Difference')
            ->column('deposited_at', 'Deposited')
            ->dateFields('opened_at', 'closed_at', 'deposited_at')
            ->defaultSort('-opened_at');
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->cashier_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('cash_sessions.cashier_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_float' => MoneyCast::class,
            'expected_closing' => MoneyCast::class,
            'counted_closing' => MoneyCast::class,
            'difference' => MoneyCast::class,
            'deposit_amount' => MoneyCast::class,
            'denominations' => 'array',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'deposited_at' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
