<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Models;

use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Payments\Models\Payment;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refund of (part of) a payment: requested → approved by someone else → paid out (`processed_at`).
 *
 * @property string $id
 * @property string $code
 * @property string $payment_id
 * @property string $invoice_id
 * @property Money $amount
 * @property PaymentMethod $method
 * @property string $reason
 * @property ApprovalStatus $status
 * @property string $requested_by
 * @property ?string $approved_by
 * @property ?CarbonImmutable $approved_at
 * @property ?string $rejection_reason
 * @property ?string $processed_by
 * @property ?CarbonImmutable $processed_at
 * @property ?string $reference_number
 * @property ?string $cash_session_id
 * @property ?string $journal_entry_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Payment $payment
 * @property-read Invoice $invoice
 * @property-read User $requester
 * @property-read ?User $approver
 * @property-read ?User $processor
 */
class Refund extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'RFD';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('refunds')
            ->sortable('created_at', 'code', 'amount', 'status', 'processed_at')
            ->filterable('status', ['eq', 'in'], enum: ApprovalStatus::class)
            ->filterable('method', ['eq', 'in'], enum: PaymentMethod::class)
            ->filterable('payment_id', ['eq'])
            ->filterable('invoice_id', ['eq'])
            ->filterable('processed_at', ['null'], type: 'datetime')
            ->filterable('requested_by', ['eq'], resource: 'users')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('code', 'reason')
            ->searchInRelation('invoice.patient.person', 'first_name', 'last_name', 'code', 'phone')
            ->includes('invoice.patient.person', 'payment', 'requester.person')
            ->column('code', 'Refund')
            ->column('created_at', 'Requested')
            ->column('patient', 'Patient')
            ->column('amount', 'Amount')
            ->column('method', 'Method')
            ->column('status', 'Status')
            ->column('processed_at', 'Paid out')
            ->column('reason', 'Reason')
            ->dateFields('created_at', 'processed_at')
            ->defaultSort('-created_at');
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** Requester, processor or the invoice's patient. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        $id = $user->getAuthIdentifier();

        return in_array($id, [$this->requested_by, $this->processed_by], true) || $this->invoice()->where('patient_id', $id)->exists();
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

        return $query->where(fn (Builder $q) => $q->where('refunds.requested_by', $id)
            ->orWhere('refunds.processed_by', $id)
            ->orWhereIn('refunds.invoice_id', Invoice::query()->select('id')->where('patient_id', $id)));
    }

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'method' => PaymentMethod::class,
            'amount' => MoneyCast::class,
            'approved_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
