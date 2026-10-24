<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Models;

use App\Enums\Payments\InvoiceStatus;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patient invoice. One open draft per patient collects every charge; issuing numbers it (gap-free) and posts
 * revenue. `balance_due = total − paid_amount`; refunds raise `refunded_amount` and never reopen the balance.
 *
 * @property string $id
 * @property string $code
 * @property ?string $invoice_number
 * @property string $patient_id
 * @property ?CarbonImmutable $issue_date
 * @property ?CarbonImmutable $due_date
 * @property string $currency
 * @property Money $subtotal
 * @property Money $discount_total
 * @property Money $tax_total
 * @property Money $total
 * @property Money $paid_amount
 * @property Money $refunded_amount
 * @property Money $balance_due
 * @property InvoiceStatus $status
 * @property ?string $notes
 * @property ?string $issued_by
 * @property ?CarbonImmutable $issued_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancellation_reason
 * @property ?string $journal_entry_id
 * @property ?string $reversal_journal_entry_id
 * @property ?string $created_by
 * @property int $version
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 * @property-read Collection<int, InsuranceClaim> $claims
 * @property-read ?User $issuer
 * @property-read ?JournalEntry $journalEntry
 */
class Invoice extends BaseModel implements OwnedByUser
{
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'BIL';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('invoices')
            ->sortable('code', 'invoice_number', 'issue_date', 'total', 'balance_due', 'status', 'created_at')
            ->filterable('status', ['eq', 'in', 'nin'], enum: InvoiceStatus::class)
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('invoice_number', ['eq', 'starts'])
            ->filterable('code', ['eq', 'starts'])
            ->filterable('total', ['gte', 'lte', 'between'], type: 'money')
            ->filterable('balance_due', ['gt', 'gte', 'lte', 'between'], type: 'money')
            ->includes('patient.person')
            ->column('invoice_number', 'Number')
            ->column('patient', 'Patient')
            ->column('issue_date', 'Issued')
            ->column('total', 'Total')
            ->column('balance_due', 'Balance')
            ->column('status', 'Status')
            ->column('code', 'Code')
            ->dateFields('issue_date', 'created_at', 'due_date')
            ->defaultSort('-created_at');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<InsuranceClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class)->orderBy('created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** Patient (portal, once issued) or the staff member who created / issued it. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        $id = $user->getAuthIdentifier();

        return in_array($id, [$this->created_by, $this->issued_by], true) || ($id === $this->patient_id && $this->status !== InvoiceStatus::Draft);
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

        // Patients see an invoice once it is issued; drafts are still being put together at the desk.
        return $query->where(fn (Builder $q) => $q->where(fn (Builder $own) => $own->where('invoices.patient_id', $id)->where('invoices.status', '!=', InvoiceStatus::Draft->value))
            ->orWhere('invoices.created_by', $id)->orWhere('invoices.issued_by', $id));
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => MoneyCast::class.':currency',
            'discount_total' => MoneyCast::class.':currency',
            'tax_total' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'paid_amount' => MoneyCast::class.':currency',
            'refunded_amount' => MoneyCast::class.':currency',
            'balance_due' => MoneyCast::class.':currency',
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
