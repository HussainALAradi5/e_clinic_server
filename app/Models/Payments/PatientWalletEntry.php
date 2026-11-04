<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Enums\Payments\WalletEntryType;
use App\Enums\Shared\PaymentMethod;
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
 * One movement of a patient's balance (WLT-2026-000001): deposit, invoice paid from the balance, payment returned,
 * paid back, adjustment or fee — with the balance after it and the journal entry that posted it. Never changed:
 * a mistake is corrected by a new movement.
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property WalletEntryType $type
 * @property Money $amount
 * @property Money $balance_after
 * @property string $currency
 * @property ?PaymentMethod $method
 * @property ?string $reference_number
 * @property ?string $payment_id
 * @property ?string $invoice_id
 * @property ?string $appointment_id
 * @property ?string $cash_session_id
 * @property ?string $journal_entry_id
 * @property ?string $reason
 * @property ?string $idempotency_key
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read ?Invoice $invoice
 */
class PatientWalletEntry extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'WLT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('wallet')
            ->sortable('created_at', 'code', 'amount', 'type')
            ->filterable('type', ['eq', 'in'], enum: WalletEntryType::class)
            ->filterable('method', ['eq', 'in'], enum: PaymentMethod::class)
            ->column('code', 'Code')
            ->column('type', 'Movement')
            ->column('amount', 'Amount')
            ->column('balance_after', 'Balance')
            ->column('created_at', 'Date')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
        return $query->where('patient_wallet_entries.patient_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'method' => PaymentMethod::class,
            'amount' => MoneyCast::class.':currency',
            'balance_after' => MoneyCast::class.':currency',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
