<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\Payments\ClaimDecisionData;
use App\Dtos\Payments\RecordPaymentData;
use App\Dtos\Payments\RemittanceData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Events\Payments\InsuranceClaimDecided;
use App\Exceptions\Payments\ClaimAlreadyExistsException;
use App\Exceptions\Payments\ClaimMissingDiagnosisException;
use App\Exceptions\Payments\InsuranceNotValidException;
use App\Exceptions\Payments\RemittanceMismatchException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Payments\InsuranceClaimServiceInterface;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\MedicalRecord;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\InvoiceItem;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Insurance claims of the insurer's share of an issued invoice. Approval records a normal payment (method
 * insurance: Dr insurance receivable / Cr receivable) so the invoice balance drops; the insurer's bank remittance
 * then clears the insurance receivable.
 *
 * @extends BaseEntityService<InsuranceClaim>
 */
final class InsuranceClaimService extends BaseEntityService implements InsuranceClaimServiceInterface
{
    public const RELATIONS = ['patient.person', 'insurer', 'invoice'];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly PaymentServiceInterface $payments,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function find(string $id): InsuranceClaim
    {
        return $this->query()->with(['invoice.items.billableService', 'payment', 'attachments'])->findOrFail($id);
    }

    public function createForInvoice(Invoice $invoice, ?string $preAuthorizationNumber): InsuranceClaim
    {
        return DB::transaction(function () use ($invoice, $preAuthorizationNumber): InsuranceClaim {
            $invoice = Invoice::query()->with(['patient', 'items.billableService', 'items.chargeable'])->lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->status->isPayable()) {
                throw new InvalidStateTransitionException(context: ['from' => $invoice->status->value, 'to' => 'claimed']);
            }
            $open = InsuranceClaim::query()->where('invoice_id', $invoice->id)
                ->whereIn('status', [ApprovalStatus::Draft->value, ApprovalStatus::Pending->value, ApprovalStatus::Approved->value])
                ->exists();
            if ($open) {
                throw new ClaimAlreadyExistsException;
            }

            $patient = $invoice->patient;
            $insurable = $invoice->items->filter(fn (InvoiceItem $i): bool => $i->billableService?->is_insurable === true);
            $lastService = $insurable->map(fn (InvoiceItem $i): CarbonImmutable => $this->serviceDate($i, $invoice))->max() ?? $invoice->issue_date;
            $valid = $patient->insurance_company_id !== null && $patient->insurance_policy_number !== null
                && ($patient->insurance_expiry_date === null || $lastService === null || $patient->insurance_expiry_date->gte($lastService));
            if (! $valid || $insurable->isEmpty()) {
                throw new InsuranceNotValidException;
            }
            $this->assertDiagnosed($insurable);

            $coverage = $patient->insurance_coverage_percent ?? '100';
            $insured = $insurable->reduce(fn (Money $sum, InvoiceItem $i): Money => $sum->plus($i->line_total), MoneyHelper::zero($invoice->currency));
            $claimed = MoneyHelper::percentage($insured, (string) $coverage);
            if ($claimed->isGreaterThan($invoice->balance_due)) {
                $claimed = $invoice->balance_due;
            }
            if (! $claimed->isPositive()) {
                throw new InsuranceNotValidException;
            }

            $claim = InsuranceClaim::query()->create([
                'invoice_id' => $invoice->id,
                'patient_id' => $patient->id,
                'insurer_id' => $patient->insurance_company_id,
                'policy_number' => $patient->insurance_policy_number,
                'coverage_percent' => (string) $coverage,
                'pre_authorization_number' => $preAuthorizationNumber,
                'claimed_amount' => $claimed,
                'status' => ApprovalStatus::Draft,
            ]);

            return $this->find($claim->id);
        });
    }

    public function submit(InsuranceClaim $claim): InsuranceClaim
    {
        return DB::transaction(function () use ($claim): InsuranceClaim {
            $claim = $this->lock($claim, ApprovalStatus::Draft, ApprovalStatus::Pending);
            $claim->forceFill(['status' => ApprovalStatus::Pending, 'submitted_at' => $this->clock->now()])->save();

            return $this->find($claim->id);
        });
    }

    public function decide(InsuranceClaim $claim, ClaimDecisionData $data): InsuranceClaim
    {
        $to = $data->approved ? ApprovalStatus::Approved : ApprovalStatus::Rejected;
        $claim = DB::transaction(function () use ($claim, $data, $to): InsuranceClaim {
            $claim = $this->lock($claim, ApprovalStatus::Pending, $to);
            $values = ['status' => $to, 'decided_at' => $this->clock->now(), 'insurer_reference' => $data->insurerReference];
            if ($data->approved) {
                $approved = MoneyHelper::of($data->approvedAmount ?? MoneyHelper::toDb($claim->claimed_amount));
                if ($approved->isGreaterThan($claim->claimed_amount) || ! $approved->isPositive()) {
                    throw new RemittanceMismatchException(__('validation.lte.numeric', ['attribute' => 'approved amount', 'value' => MoneyHelper::toDb($claim->claimed_amount)]), ['errors' => ['approved_amount' => [__('validation.lte.numeric', ['attribute' => 'approved amount', 'value' => MoneyHelper::toDb($claim->claimed_amount)])]]]);
                }
                $payment = $this->payments->record(
                    new RecordPaymentData($claim->invoice_id, MoneyHelper::toDb($approved), PaymentMethod::Insurance, $claim->code, null, $data->insurerReference),
                    (string) Str::uuid(),
                );
                $values += ['approved_amount' => $approved, 'payment_id' => $payment->id];
            } else {
                $values += ['approved_amount' => MoneyHelper::zero(), 'rejection_reason' => $data->rejectionReason];
            }
            $claim->forceFill($values)->save();

            return $claim;
        });
        InsuranceClaimDecided::dispatch($claim);

        return $this->find($claim->id);
    }

    public function recordRemittance(RemittanceData $data): Collection
    {
        return DB::transaction(function () use ($data): Collection {
            $claims = InsuranceClaim::query()->whereIn('id', $data->claimIds)->lockForUpdate()->get();
            $total = $claims->reduce(fn (Money $sum, InsuranceClaim $c): Money => $sum->plus($c->approved_amount ?? MoneyHelper::zero()), MoneyHelper::zero());
            $usable = $claims->count() === count(array_unique($data->claimIds))
                && $claims->every(fn (InsuranceClaim $c): bool => $c->status === ApprovalStatus::Approved && $c->remitted_at === null && $c->payment_id !== null)
                && $claims->pluck('insurer_id')->unique()->count() === 1
                && $total->isEqualTo(MoneyHelper::of($data->amount));
            if (! $usable) {
                throw new RemittanceMismatchException(context: ['expected' => MoneyHelper::toDb($total)]);
            }

            /** @var InsuranceClaim $first */
            $first = $claims->first();
            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $first,
                $data->date,
                __('accounting.insurance_remittance', ['reference' => $data->reference, 'count' => $claims->count()]),
                [JournalLineData::debit(AccountMappingKey::Bank, $total), JournalLineData::credit(AccountMappingKey::InsuranceReceivable, $total)],
            ));
            $claims->each(fn (InsuranceClaim $c) => $c->forceFill([
                'remitted_at' => $data->date,
                'remittance_reference' => $data->reference,
                'remittance_journal_entry_id' => $entry->id,
            ])->save());

            return $this->query()->whereIn('id', $claims->modelKeys())->get();
        });
    }

    public function cancel(InsuranceClaim $claim, string $reason): InsuranceClaim
    {
        return DB::transaction(function () use ($claim, $reason): InsuranceClaim {
            $claim = InsuranceClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! in_array($claim->status, [ApprovalStatus::Draft, ApprovalStatus::Pending], true)) {
                throw new InvalidStateTransitionException(context: ['from' => $claim->status->value, 'to' => ApprovalStatus::Cancelled->value]);
            }
            $claim->forceFill(['status' => ApprovalStatus::Cancelled, 'cancellation_reason' => $reason])->save();

            return $this->find($claim->id);
        });
    }

    public function diagnoses(InsuranceClaim $claim): array
    {
        $visits = InvoiceItem::query()->where('invoice_id', $claim->invoice_id)
            ->where('chargeable_type', (new Appointment)->getMorphClass())
            ->pluck('chargeable_id');
        $rows = [];
        MedicalRecord::query()->with(['appointment', 'diagnoses.code'])->whereIn('id', $visits)->get()
            ->each(function (MedicalRecord $record) use (&$rows): void {
                foreach ($record->diagnoses as $d) {
                    $rows[] = ['code' => $d->code->code, 'title' => $d->code->title, 'is_primary' => $d->is_primary, 'visit' => $record->appointment->code];
                }
            });

        return $rows;
    }

    /**
     * @return Builder<InsuranceClaim>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(InsuranceClaim::query()->with(self::RELATIONS), 'claims', Auth::user());
    }

    private function lock(InsuranceClaim $claim, ApprovalStatus $from, ApprovalStatus $to): InsuranceClaim
    {
        $claim = InsuranceClaim::query()->lockForUpdate()->findOrFail($claim->id);
        if ($claim->status !== $from) {
            throw new InvalidStateTransitionException(context: ['from' => $claim->status->value, 'to' => $to->value]);
        }

        return $claim;
    }

    /** When the service was given: the visit's date, else the invoice date. */
    private function serviceDate(InvoiceItem $item, Invoice $invoice): CarbonImmutable
    {
        $source = $item->chargeable;

        return $source instanceof Appointment ? $source->scheduled_date : ($invoice->issue_date ?? $this->clock->today());
    }

    /**
     * Every visit on the claim needs a signed note with a primary diagnosis.
     *
     * @param  Collection<int, InvoiceItem>  $items
     */
    private function assertDiagnosed(Collection $items): void
    {
        $visits = $items->filter(fn (InvoiceItem $i): bool => $i->chargeable instanceof Appointment)->pluck('chargeable_id')->filter()->values();
        if ($visits->isEmpty()) {
            return;
        }
        $diagnosed = MedicalRecord::query()->whereIn('id', $visits)->whereNotNull('signed_at')
            ->whereHas('diagnoses', fn (Builder $q) => $q->where('is_primary', true))
            ->count();
        if ($diagnosed < $visits->unique()->count()) {
            throw new ClaimMissingDiagnosisException;
        }
    }
}
