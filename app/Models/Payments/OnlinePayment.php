<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Enums\Payments\OnlinePaymentStatus;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Appointments\Patient;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay an invoice online (OPY-2026-000001): the gateway charge, where the payer was sent, and — once
 * the gateway confirms the capture — the payment booked on the invoice. Any surplus (the invoice was paid at the
 * desk meanwhile) goes to the patient's balance.
 *
 * @property string $id
 * @property string $code
 * @property string $invoice_id
 * @property string $patient_id
 * @property Money $amount
 * @property string $currency
 * @property OnlinePaymentStatus $status
 * @property string $provider
 * @property ?string $charge_id
 * @property ?string $checkout_url
 * @property ?string $gateway_status
 * @property ?string $failure_reason
 * @property ?string $payment_id
 * @property ?string $started_by
 * @property ?string $idempotency_key
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $captured_at
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Invoice $invoice
 * @property-read Patient $patient
 * @property-read ?Payment $payment
 */
class OnlinePayment extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'OPY';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('online_payments')
            ->sortable('created_at', 'code', 'amount', 'status')
            ->filterable('status', ['eq', 'in'], enum: OnlinePaymentStatus::class)
            ->filterable('invoice_id', ['eq'])
            ->filterable('patient_id', ['eq'])
            ->column('code', 'Code')
            ->column('patient', 'Patient')
            ->column('invoice', 'Invoice')
            ->column('amount', 'Amount')
            ->column('status', 'Status')
            ->column('created_at', 'Started')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->patient_id === $user->getAuthIdentifier();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('online_payments.patient_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'status' => OnlinePaymentStatus::class,
            'amount' => MoneyCast::class.':currency',
            'expires_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
