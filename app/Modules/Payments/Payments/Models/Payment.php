<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Models;

use App\Enums\Payments\PaymentStatus;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\PaymentMethod;
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
 * Money received against an invoice; its code is the receipt number. Never edited — voided the same day or refunded.
 *
 * @property string $id
 * @property string $code
 * @property string $invoice_id
 * @property string $patient_id
 * @property Money $amount
 * @property string $currency
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property ?string $reference_number
 * @property ?string $card_last4
 * @property CarbonImmutable $paid_at
 * @property string $received_by
 * @property ?string $cash_session_id
 * @property ?string $journal_entry_id
 * @property Money $refunded_amount
 * @property string $idempotency_key
 * @property ?CarbonImmutable $voided_at
 * @property ?string $void_reason
 * @property ?string $notes
 * @property ?CarbonImmutable $created_at
 * @property-read Invoice $invoice
 * @property-read Patient $patient
 * @property-read User $receiver
 * @property-read ?CashSession $cashSession
 * @property-read Collection<int, Refund> $refunds
 */
class Payment extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'PAY';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('payments')
            ->sortable('paid_at', 'code', 'amount', 'method', 'status')
            ->filterable('method', ['eq', 'in'], enum: PaymentMethod::class)
            ->filterable('status', ['eq', 'in'], enum: PaymentStatus::class)
            ->filterable('invoice_id', ['eq'])
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('received_by', ['eq', 'in'], resource: 'users')
            ->filterable('cash_session_id', ['eq'])
            ->filterable('amount', ['gte', 'lte', 'between'], type: 'money')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('code', 'reference_number')
            ->searchInRelation('patient.person', 'first_name', 'last_name', 'code', 'phone')
            ->includes('patient.person', 'invoice', 'receiver.person')
            ->column('code', 'Receipt')
            ->column('paid_at', 'Paid')
            ->column('patient', 'Patient')
            ->column('invoice', 'Invoice')
            ->column('method', 'Method')
            ->column('amount', 'Amount')
            ->column('status', 'Status')
            ->column('received_by', 'Received by')
            ->dateFields('paid_at', 'created_at')
            ->defaultSort('-paid_at');
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }

    /** Amount still refundable. */
    public function refundable(): Money
    {
        return $this->amount->minus($this->refunded_amount);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('created_at');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->received_by], true);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        $id = $user->getAuthIdentifier();

        return $query->where(fn (Builder $q) => $q->where('payments.patient_id', $id)->orWhere('payments.received_by', $id));
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => MoneyCast::class.':currency',
            'refunded_amount' => MoneyCast::class.':currency',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
