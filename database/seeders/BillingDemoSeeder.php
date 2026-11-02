<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dtos\Payments\RecordPaymentData;
use App\Dtos\Payments\RefundRequestData;
use App\Dtos\Transactions\CloseSessionData;
use App\Dtos\Transactions\DepositData;
use App\Dtos\Transactions\ExpenseData;
use App\Dtos\Transactions\PayExpenseData;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Payments\InvoiceStatus;
use App\Enums\Shared\GenericStatus;
use App\Enums\Shared\PaymentMethod;
use App\Enums\Transactions\ExpenseCategory;
use App\Exceptions\Payments\AlreadyChargedException;
use App\Exceptions\Shared\DomainException;
use App\Interfaces\Payments\InsuranceClaimServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Interfaces\Payments\RefundServiceInterface;
use App\Interfaces\Transactions\CashSessionServiceInterface;
use App\Interfaces\Transactions\ExpenseServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Icd10Code;
use App\Models\Appointments\MedicalRecord;
use App\Models\Identity\User;
use App\Models\Payments\Invoice;
use App\Models\Payments\Payment;
use App\Models\Reference\Company;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Demo money (Phase 4), through the real services so every amount is posted to the journal:
 * completed visits and orders billed onto drafts; yesterday the cashier issued and took payments (cash, card,
 * a partial payment), refunded part of one, closed an exact drawer and the accountant banked it, while reception
 * closed a drawer 3 BHD short that waits for a supervisor; today the cashier's drawer is open with a payment, one
 * invoice waits for payment, drafts wait to be issued, a refund waits for approval; and three expenses.
 */
final class BillingDemoSeeder extends Seeder
{
    public function run(
        InvoiceServiceInterface $invoices,
        PaymentServiceInterface $payments,
        RefundServiceInterface $refunds,
        CashSessionServiceInterface $drawers,
        ExpenseServiceInterface $expenses,
        InsuranceClaimServiceInterface $claims,
        ClinicClock $clock,
    ): void {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Invoice::query()->exists()) {
            return;
        }
        $this->call(FiscalYearSeeder::class);
        $cashier = $this->user('cashier');
        $reception = $this->user('reception');
        $accountant = $this->user('accountant');
        $admin = $this->user('admin');
        if ($cashier === null || $reception === null || $accountant === null || $admin === null) {
            return;
        }

        // Every completed visit and order is billed onto its patient's draft (newest work last).
        Auth::setUser($reception);
        $work = [
            ...Appointment::query()->where('status', AppointmentStatus::Completed->value)->whereNotNull('billable_service_id')->orderBy('scheduled_date')->get()->all(),
            ...ClinicalOrder::query()->where('status', GenericStatus::Completed->value)->whereNotNull('billable_service_id')->orderBy('completed_at')->get()->all(),
        ];
        foreach ($work as $source) {
            try {
                $invoices->charge($source);
            } catch (AlreadyChargedException) {
                // Billed already.
            }
        }
        $drafts = Invoice::query()->where('status', InvoiceStatus::Draft->value)->orderBy('created_at')->get()->values();
        if ($drafts->count() < 4) {
            Auth::forgetUser();

            return;
        }

        // Yesterday, 09:00 clinic time.
        $yesterday = $clock->today()->subDay()->setTime(9, 0)->utc();
        CarbonImmutable::setTestNow($yesterday);
        Auth::setUser($cashier);
        $drawer = $drawers->open($cashier, MoneyHelper::of('20.000'));
        [$paidCash, $paidCard, $partly, $atReception] = array_map(fn (Invoice $i): Invoice => $invoices->issue($i), $drafts->take(4)->all());
        $cash = $this->pay($payments, $paidCash, PaymentMethod::Cash);
        $this->pay($payments, $paidCard, PaymentMethod::Card, card: '4417');
        $this->pay($payments, $partly, PaymentMethod::Cash, MoneyHelper::toDb($partly->total->dividedBy(2, RoundingMode::DOWN)));
        $refund = $refunds->request(new RefundRequestData($cash->id, '2.000', null, 'Dressing charged but not used'));
        Auth::setUser($accountant);
        $refunds->approve($refund);
        Auth::setUser($cashier);
        $refunds->process($refund, null);
        $counted = MoneyHelper::toDb($drawers->report($drawer)->expected);
        $drawer = $drawers->close($drawer, new CloseSessionData($counted, null, 'Exact count'));

        Auth::setUser($reception);
        $short = $drawers->open($reception, MoneyHelper::of('10.000'));
        $this->pay($payments, $atReception, PaymentMethod::Cash);
        $expected = $drawers->report($short)->expected;
        $drawers->close($short, new CloseSessionData(MoneyHelper::toDb($expected->minus(MoneyHelper::of('3.000'))), null, 'Short — maybe change given twice'));

        Auth::setUser($accountant);
        $drawers->deposit($drawer, new DepositData($clock->today(), 'NBB-'.Str::upper(Str::random(6)), $counted));
        CarbonImmutable::setTestNow();

        // Today: the cashier's drawer is open; one invoice paid in cash, one waiting; a card refund waits for approval.
        Auth::setUser($cashier);
        $drawers->open($cashier, MoneyHelper::of('20.000'));
        $today = $drafts->slice(4, 2)->map(fn (Invoice $i): Invoice => $invoices->issue($i))->values();
        if ($today->isNotEmpty()) {
            $this->pay($payments, $today->first(), PaymentMethod::Cash);
        }
        $card = Payment::query()->where('method', PaymentMethod::Card->value)->firstOrFail();
        Auth::setUser($reception);
        $refunds->request(new RefundRequestData($card->id, '5.000', null, 'Follow-up price applied late'));

        // Insurance: claims prepared for insured patients' unpaid invoices (when their visits carry signed diagnoses).
        Auth::setUser($accountant);
        $sent = false;
        foreach (Invoice::query()->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])->get() as $invoice) {
            try {
                if ($invoice->patient()->whereNotNull('insurance_company_id')->exists()) {
                    $this->noteVisits($invoice);
                }
                $claim = $claims->createForInvoice($invoice, null);
                if (! $sent) {
                    $claims->submit($claim);
                    $sent = true;
                }
            } catch (DomainException) {
                // Not insured, nothing insurable, or a visit without a signed diagnosis.
            }
        }

        // Expenses: one paid, one waiting for approval, one draft.
        Auth::setUser($accountant);
        $supplier = Company::query()->where('name', 'Yiaco Medical Supplies')->value('id');
        $paid = $expenses->create(ExpenseData::fromArray([
            'category' => ExpenseCategory::MedicalSupplies->value, 'payee_company_id' => $supplier, 'description' => 'Gloves, gauze and syringes',
            'amount' => '84.700', 'tax_amount' => '7.700', 'expense_date' => $clock->today()->subDays(3)->toDateString(), 'vendor_invoice_number' => 'YM-55102',
        ]));
        $expenses->submit($paid);
        Auth::setUser($admin);
        $expenses->approve($paid);
        Auth::setUser($accountant);
        $expenses->pay($paid, new PayExpenseData($clock->today(), PaymentMethod::BankTransfer, 'TRX-88213'));
        $expenses->submit($expenses->create(ExpenseData::fromArray([
            'category' => ExpenseCategory::Utilities->value, 'payee' => 'Electricity & Water Authority', 'description' => 'Electricity, September',
            'amount' => '312.400', 'tax_amount' => '0', 'expense_date' => $clock->today()->subDays(1)->toDateString(),
        ])));
        $expenses->create(ExpenseData::fromArray([
            'category' => ExpenseCategory::Maintenance->value, 'payee' => 'CoolAir Services', 'description' => 'Air-conditioning service, 3 units',
            'amount' => '45.000', 'tax_amount' => '0', 'expense_date' => $clock->today()->toDateString(),
        ]));

        Auth::forgetUser();
    }

    /** Demo only: visits on an insured invoice get a signed note with a primary diagnosis, as a doctor would. */
    private function noteVisits(Invoice $invoice): void
    {
        $code = Icd10Code::query()->where('code', 'J06.9')->first();
        $visits = Appointment::query()->whereIn('id', $invoice->items()->where('chargeable_type', (new Appointment)->getMorphClass())->pluck('chargeable_id'))->get();
        foreach ($visits as $visit) {
            if ($code === null || MedicalRecord::query()->whereKey($visit->id)->whereNotNull('signed_at')->exists()) {
                continue;
            }
            $record = MedicalRecord::query()->firstOrCreate(['id' => $visit->id], [
                'chief_complaint' => 'Sore throat and cough', 'assessment' => 'Upper respiratory infection', 'plan' => 'Fluids and paracetamol',
            ]);
            $record->diagnoses()->create(['icd10_code_id' => $code->id, 'is_primary' => true]);
            $record->forceFill(['signed_by' => $visit->provider_id, 'signed_at' => $visit->endsAt()])->save();
        }
    }

    private function user(string $name): ?User
    {
        return User::query()->whereHas('person', fn ($q) => $q->where('email', $name.DemoSeeder::EMAIL_DOMAIN))->first();
    }

    private function pay(PaymentServiceInterface $payments, Invoice $invoice, PaymentMethod $method, ?string $amount = null, ?string $card = null): Payment
    {
        return $payments->record(
            new RecordPaymentData($invoice->id, $amount ?? MoneyHelper::toDb($invoice->balance_due), $method, null, $card),
            (string) Str::uuid(),
        );
    }
}
