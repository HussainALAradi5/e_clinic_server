<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\Payments\InvoiceData;
use App\Dtos\Payments\InvoiceItemData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Payments\DiscountType;
use App\Enums\Payments\InvoicePaymentState;
use App\Enums\Payments\InvoiceStatus;
use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\GenericStatus;
use App\Enums\Shared\PaymentMethod;
use App\Events\Payments\InvoiceCancelled;
use App\Events\Payments\InvoiceIssued;
use App\Exceptions\Payments\AlreadyChargedException;
use App\Exceptions\Payments\DiscountLimitExceededException;
use App\Exceptions\Payments\EmptyInvoiceException;
use App\Exceptions\Payments\InvoiceHasPaymentsException;
use App\Exceptions\Payments\InvoiceNotEditableException;
use App\Exceptions\Payments\NotChargeableException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Shared\Chargeable;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Payments\BillableService;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\InvoiceItem;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\DocumentNumberGenerator;
use App\Utilities\Shared\MoneyHelper;
use App\Utilities\Shared\RoleLimit;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Patient invoices — the single billing path. Visits, sessions and orders are charged onto the patient's open draft;
 * reception reviews and issues it (number + revenue entry); payments and refunds keep its balance.
 *
 * @extends BaseEntityService<Invoice>
 */
final class InvoiceService extends BaseEntityService implements InvoiceServiceInterface
{
    public const RELATIONS = ['patient.person', 'patient.insurer', 'payments'];

    public const DETAIL = [
        ...self::RELATIONS,
        'items.billableService', 'items.doctor.person', 'items.chargeable', 'issuer.person',
        'payments.receiver.person', 'refunds', 'claims.insurer',
    ];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly InvoiceCalculator $calculator,
        private readonly JournalEntryServiceInterface $journal,
        private readonly DocumentNumberGenerator $numbers,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function list(ListQuery $query, ?InvoicePaymentState $state = null, ?PaymentMethod $method = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->query();
        if ($state !== null) {
            $builder->paymentState($state, $this->clock->today());
        }
        if ($method !== null) {
            $builder->paidBy($method);
        }

        return $this->listQuery->paginate($builder, $query);
    }

    public function outstanding(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('balance_due', '>', 0);

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): Invoice
    {
        return $this->query()->with(self::DETAIL)->findOrFail($id);
    }

    public function createDraft(InvoiceData $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $invoice = $this->openDraft($data->patientId);
            if ($data->notes !== null) {
                $invoice->notes = $data->notes;
            }
            $this->writeItems($invoice, $data->items, replace: false);

            return $this->find($invoice->id);
        });
    }

    public function charge(Chargeable $source): ?Invoice
    {
        /** @var Model&Chargeable $source */
        $lines = $source->chargeLines();
        if ($lines === []) {
            return null;
        }

        return DB::transaction(function () use ($source, $lines): Invoice {
            $invoice = $this->openDraft($source->chargePatientId());
            $charged = InvoiceItem::query()
                ->where('chargeable_type', $source->getMorphClass())
                ->where('chargeable_id', $source->getKey())
                ->whereHas('invoice', fn (Builder $q) => $q->where('status', '!=', InvoiceStatus::Cancelled->value))
                ->exists();
            if ($charged) {
                throw new AlreadyChargedException;
            }

            foreach ($lines as $line) {
                $service = BillableService::query()->withDeleted()->findOrFail($line->billableServiceId);
                $item = new InvoiceItem([
                    'billable_service_id' => $service->id,
                    'chargeable_type' => $source->getMorphClass(),
                    'chargeable_id' => $source->getKey(),
                    'doctor_id' => $line->doctorId,
                    'description' => $line->description ?? $service->name,
                ]);
                $this->price($item, $service, $line->quantity, null, '0');
                $invoice->items()->save($item);
            }
            $this->calculator->refresh($invoice);
            $invoice->save();

            // Charging is internal (a pharmacist's dispensation, a completed visit): not limited to invoices the caller may open.
            return Invoice::query()->with(self::DETAIL)->findOrFail($invoice->id);
        });
    }

    public function uncharge(Chargeable $source): void
    {
        /** @var Model&Chargeable $source */
        DB::transaction(function () use ($source): void {
            $items = InvoiceItem::query()
                ->where('chargeable_type', $source->getMorphClass())
                ->where('chargeable_id', $source->getKey())
                ->whereHas('invoice', fn (Builder $q) => $q->where('status', '!=', InvoiceStatus::Cancelled->value))
                ->get();
            foreach ($items->groupBy('invoice_id') as $invoiceId => $lines) {
                $invoice = $this->lockDraft(Invoice::query()->findOrFail($invoiceId));
                InvoiceItem::query()->whereKey($lines->modelKeys())->delete();
                if ($invoice->items()->doesntExist()) {
                    $invoice->softDelete();

                    continue;
                }
                $this->calculator->refresh($invoice);
                $invoice->save();
            }
        });
    }

    public function chargeSource(string $type, string $id): Invoice
    {
        $source = match ($type) {
            'appointment' => Appointment::query()->findOrFail($id),
            'clinical_order' => ClinicalOrder::query()->findOrFail($id),
            default => throw new NotChargeableException,
        };
        $ready = $source instanceof Appointment
            ? in_array($source->status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed], true)
            : $source->status !== GenericStatus::Cancelled;
        if (! $ready) {
            throw new NotChargeableException;
        }

        return $this->charge($source) ?? throw new NotChargeableException;
    }

    public function updateDraft(Invoice $invoice, InvoiceData $data, int $version): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $version): Invoice {
            $invoice = $this->lockDraft($invoice);
            $invoice->assertVersion($version);
            $invoice->notes = $data->notes;
            $this->writeItems($invoice, $data->items, replace: true);

            return $this->find($invoice->id);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice): Invoice {
            $invoice = $this->lockDraft($invoice);
            $items = $invoice->items()->get();
            if ($items->isEmpty() || $invoice->total->isNegative()) {
                throw new EmptyInvoiceException;
            }

            $today = $this->clock->today();
            $invoice->forceFill([
                'invoice_number' => $this->numbers->next('invoice', 'INV', $today),
                'issue_date' => $today,
                'due_date' => $today->addDays(Config::integer('payments.payment_terms_days')),
                'issued_by' => Auth::id(),
                'issued_at' => $this->clock->now(),
                'balance_due' => $invoice->total,
                // A zero total (fully discounted) is settled at once and posts nothing.
                'status' => $invoice->total->isZero() ? InvoiceStatus::Paid : InvoiceStatus::Issued,
            ]);
            if (! $invoice->total->isZero()) {
                $invoice->journal_entry_id = $this->postRevenue($invoice, $items)->id;
            }
            $invoice->save();

            return $invoice;
        });
        InvoiceIssued::dispatch($invoice);

        return $this->find($invoice->id);
    }

    public function cancel(Invoice $invoice, string $reason): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice, $reason): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === InvoiceStatus::Cancelled) {
                throw new InvalidStateTransitionException(context: ['from' => $invoice->status->value, 'to' => InvoiceStatus::Cancelled->value]);
            }
            if ($invoice->paid_amount->isPositive()) {
                throw new InvoiceHasPaymentsException;
            }

            if ($invoice->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($invoice->journal_entry_id);
                $invoice->reversal_journal_entry_id = $this->journal->reverse($entry, $this->clock->today(), $reason, fromSource: true)->id;
            }
            // Claims still being prepared or with the insurer end with the invoice.
            InsuranceClaim::query()->where('invoice_id', $invoice->id)
                ->whereIn('status', [ApprovalStatus::Draft->value, ApprovalStatus::Pending->value])
                ->get()
                ->each(fn (InsuranceClaim $c) => $c->forceFill(['status' => ApprovalStatus::Cancelled, 'cancellation_reason' => $reason])->save());

            $invoice->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => $this->clock->now(),
                'cancellation_reason' => $reason,
                'balance_due' => MoneyHelper::zero($invoice->currency),
            ])->save();

            return $invoice;
        });
        InvoiceCancelled::dispatch($invoice);

        return $this->find($invoice->id);
    }

    public function applyPayment(Invoice $invoice, Money $amount): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $paid = $invoice->paid_amount->plus($amount);
        $balance = $invoice->total->minus($paid);
        $invoice->forceFill([
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'status' => match (true) {
                $balance->isZero() => InvoiceStatus::Paid,
                $paid->isZero() => InvoiceStatus::Issued,
                default => InvoiceStatus::PartiallyPaid,
            },
        ])->save();

        return $invoice;
    }

    public function applyRefund(Invoice $invoice, Money $amount): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $invoice->forceFill(['refunded_amount' => $invoice->refunded_amount->plus($amount)])->save();

        return $invoice;
    }

    /**
     * @return Builder<Invoice>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Invoice::query()->with(self::RELATIONS), 'invoices', Auth::user());
    }

    /** The patient's open draft (locked), created when missing. */
    private function openDraft(string $patientId): Invoice
    {
        $draft = Invoice::query()->where('patient_id', $patientId)->where('status', InvoiceStatus::Draft->value)->lockForUpdate()->first();

        return $draft ?? Invoice::query()->create([
            'patient_id' => $patientId,
            'currency' => MoneyHelper::defaultCurrency(),
            'status' => InvoiceStatus::Draft,
        ]);
    }

    private function lockDraft(Invoice $invoice): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        if (! $invoice->isDraft()) {
            throw new InvoiceNotEditableException;
        }

        return $invoice;
    }

    /**
     * Adds (or, when replacing, syncs) typed lines. Existing lines keep their price snapshot and source;
     * new or changed discounts are checked against the user's limit.
     *
     * @param  list<InvoiceItemData>  $items
     */
    private function writeItems(Invoice $invoice, array $items, bool $replace): void
    {
        $existing = $invoice->items()->get()->keyBy('id');
        $kept = [];
        $limit = RoleLimit::best(Auth::user(), Config::array('payments.discount_limits'));

        foreach ($items as $i => $data) {
            $item = $data->id !== null ? $existing->get($data->id) : null;
            $before = $item === null ? null : [$item->discount_type, (string) $item->discount_value];
            $item ??= new InvoiceItem(['doctor_id' => $data->doctorId]);

            $serviceChanged = $data->billableServiceId !== null && $data->billableServiceId !== $item->billable_service_id;
            if ($serviceChanged) {
                $service = BillableService::query()->where('is_archived', false)->findOrFail($data->billableServiceId);
                $item->billable_service_id = $service->id;
                $item->description = $data->description ?? $service->name;
                $this->price($item, $service, $data->quantity, $data->discountType, $data->discountValue);
            } else {
                $item->description = $data->description ?? $item->description;
                if ($item->billable_service_id === null) {
                    $item->unit_price = MoneyHelper::of($data->unitPrice ?? '0', $invoice->currency);
                    if (! $item->exists) {
                        $item->tax_rate = '0';
                        $item->revenue_account_id = $this->freeLineAccount();
                    }
                }
                $this->recalculate($item, $data->quantity, $data->discountType, $data->discountValue);
            }

            if ($item->discount_amount->isPositive() && $before !== [$item->discount_type, (string) $item->discount_value]) {
                $gross = $item->unit_price->multipliedBy($item->quantity, RoundingMode::HALF_UP);
                $percent = $this->calculator->discountPercent($gross, $item->discount_amount);
                if ($limit !== null && $percent->isGreaterThan($limit)) {
                    throw new DiscountLimitExceededException(__('errors.DISCOUNT_LIMIT_EXCEEDED', ['percent' => (string) $limit]), ['limit_percent' => (string) $limit, 'errors' => ["items.{$i}.discount_value" => [__('errors.DISCOUNT_LIMIT_EXCEEDED', ['percent' => (string) $limit])]]]);
                }
            }

            $invoice->items()->save($item);
            $kept[] = $item->id;
        }

        if ($replace) {
            $invoice->items()->whereNotIn('id', $kept)->delete();
        }
        $this->calculator->refresh($invoice);
        if ($replace && $invoice->items()->doesntExist()) {
            throw new EmptyInvoiceException;
        }
        $invoice->save();
    }

    /** Snapshot of the price list on the line. */
    private function price(InvoiceItem $item, BillableService $service, string $quantity, ?DiscountType $discountType, string $discountValue): void
    {
        $item->unit_price = $service->price;
        $item->tax_rate = (string) $service->tax_rate;
        $item->revenue_account_id = $service->revenue_account_id;
        $this->recalculate($item, $quantity, $discountType, $discountValue);
    }

    private function recalculate(InvoiceItem $item, string $quantity, ?DiscountType $discountType, string $discountValue): void
    {
        $line = $this->calculator->line($quantity, $item->unit_price, $discountType, $discountValue, (string) $item->tax_rate);
        $item->forceFill([
            'quantity' => $quantity,
            'discount_type' => $discountType,
            'discount_value' => $discountType === null ? '0' : $discountValue,
            'discount_amount' => $line['discount'],
            'tax_amount' => $line['tax'],
            'line_total' => $line['total'],
        ]);
    }

    private function freeLineAccount(): string
    {
        return Account::query()->where('code', Config::string('payments.free_line_revenue_account'))->valueOrFail('id');
    }

    /**
     * Dr accounts receivable / Cr revenue per account and doctor / Cr VAT payable.
     *
     * @param  Collection<int, InvoiceItem>  $items
     */
    private function postRevenue(Invoice $invoice, Collection $items): JournalEntry
    {
        $patient = $invoice->patient_id;
        $lines = [JournalLineData::debit(AccountMappingKey::AccountsReceivable, $invoice->total, null, $patient)];
        foreach ($items->groupBy(fn (InvoiceItem $i): string => $i->revenue_account_id.'|'.$i->doctor_id) as $group) {
            /** @var InvoiceItem $first */
            $first = $group->first();
            $net = $group->reduce(fn (Money $sum, InvoiceItem $i): Money => $sum->plus($i->net()), MoneyHelper::zero($invoice->currency));
            $lines[] = JournalLineData::credit($first->revenue_account_id, $net, null, $patient, $first->doctor_id);
        }
        $lines[] = JournalLineData::credit(AccountMappingKey::VatPayable, $invoice->tax_total, null, $patient);

        $invoice->loadMissing('patient.person');

        return $this->journal->postAutomatic(new AutomaticEntryData(
            $invoice,
            $invoice->issue_date ?? $this->clock->today(),
            __('accounting.invoice_issued', ['number' => $invoice->invoice_number, 'patient' => $invoice->patient->person->full_name]),
            $lines,
        ));
    }
}
