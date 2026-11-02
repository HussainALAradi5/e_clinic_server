<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Enums\Shared\ApprovalStatus;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Appointments\Patient;
use App\Models\Base\BaseModel;
use App\Models\Identity\Attachment;
use App\Models\Reference\Company;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Claim of the insurer's share of an issued invoice: draft → pending (submitted) → approved (full / partial) or
 * rejected. Approval records an `insurance` payment; the remittance clears the insurance receivable.
 *
 * @property string $id
 * @property string $code
 * @property string $invoice_id
 * @property string $patient_id
 * @property string $insurer_id
 * @property string $policy_number
 * @property string $coverage_percent
 * @property ?string $pre_authorization_number
 * @property Money $claimed_amount
 * @property ?Money $approved_amount
 * @property ApprovalStatus $status
 * @property ?CarbonImmutable $submitted_at
 * @property ?CarbonImmutable $decided_at
 * @property ?string $insurer_reference
 * @property ?string $rejection_reason
 * @property ?string $cancellation_reason
 * @property ?string $payment_id
 * @property ?CarbonImmutable $remitted_at
 * @property ?string $remittance_reference
 * @property ?string $remittance_journal_entry_id
 * @property ?string $created_by
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Invoice $invoice
 * @property-read Patient $patient
 * @property-read Company $insurer
 * @property-read ?Payment $payment
 * @property-read Collection<int, Attachment> $attachments
 */
class InsuranceClaim extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'CLM';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('claims')
            ->sortable('created_at', 'code', 'claimed_amount', 'approved_amount', 'status', 'submitted_at', 'remitted_at')
            ->filterable('status', ['eq', 'in'], enum: ApprovalStatus::class)
            ->filterable('insurer_id', ['eq', 'in'], resource: 'companies')
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('invoice_id', ['eq'])
            ->filterable('remitted_at', ['null'], type: 'date')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('policy_number', 'pre_authorization_number', 'insurer_reference')
            ->includes('patient.person', 'insurer', 'invoice')
            ->column('code', 'Claim')
            ->column('patient', 'Patient')
            ->column('insurer', 'Insurer')
            ->column('invoice', 'Invoice')
            ->column('claimed_amount', 'Claimed')
            ->column('approved_amount', 'Approved')
            ->column('status', 'Status')
            ->column('remitted_at', 'Paid by insurer')
            ->dateFields('created_at', 'submitted_at', 'decided_at', 'remitted_at')
            ->defaultSort('-created_at');
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
     * @return BelongsTo<Company, $this>
     */
    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'insurer_id')->withDeleted();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Portal patient: own claims (diagnoses never shown to the portal). */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->created_by], true);
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

        return $query->where(fn (Builder $q) => $q->where('insurance_claims.patient_id', $id)->orWhere('insurance_claims.created_by', $id));
    }

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'coverage_percent' => 'decimal:2',
            'claimed_amount' => MoneyCast::class,
            'approved_amount' => MoneyCast::class,
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'remitted_at' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
