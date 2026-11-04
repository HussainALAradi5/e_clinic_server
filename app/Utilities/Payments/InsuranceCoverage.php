<?php

declare(strict_types=1);

namespace App\Utilities\Payments;

use App\Dtos\Payments\CoverageSplit;
use App\Enums\Payments\PaymentStatus;
use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\InvoiceItem;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The insurer / patient split of an invoice, worked out the same way for the desk (co-pay at check-in) and for the
 * claim: the insurer pays `coverage %` of the insurable lines while the patient's policy is valid on the day of
 * service; the patient pays the rest. Once claimed, the claim's amount (or the insurer's approved amount) is used, so
 * a shortfall falls back on the patient; a rejected claim puts the whole bill back on the patient.
 */
final class InsuranceCoverage
{
    public static function split(Invoice $invoice, CarbonImmutable $today): CoverageSplit
    {
        $invoice->loadMissing(['patient.insurer', 'items.billableService', 'items.chargeable', 'claims', 'payments']);
        $currency = $invoice->currency;
        $zero = MoneyHelper::zero($currency);
        $patient = $invoice->patient;
        $insurable = self::insurableItems($invoice);
        $insurableTotal = $insurable->reduce(fn (Money $sum, InvoiceItem $i): Money => $sum->plus($i->line_total), $zero);
        $patientPaid = $invoice->payments
            ->filter(fn ($p): bool => $p->status === PaymentStatus::Completed && $p->method !== PaymentMethod::Insurance)
            ->reduce(fn (Money $sum, $p): Money => $sum->plus($p->amount), $zero);

        // The latest claim that still counts: once the insurer decided, its answer replaces the estimate.
        $claim = $invoice->claims->reject(fn (InsuranceClaim $c): bool => $c->status === ApprovalStatus::Cancelled)->last();
        $reason = match (true) {
            ! self::hasPolicy($patient) => 'no_insurance',
            ! self::validOn($patient, self::lastServiceDate($insurable, $invoice, $today)) => 'expired',
            $insurable->isEmpty() => 'nothing_insurable',
            $claim?->status === ApprovalStatus::Rejected => 'claim_rejected',
            default => null,
        };
        $percent = (string) ($patient->insurance_coverage_percent ?? '100');
        $insurerShare = match (true) {
            $reason !== null => $zero,
            $claim?->status === ApprovalStatus::Approved => $claim->approved_amount ?? $zero,
            $claim !== null => $claim->claimed_amount,
            default => MoneyHelper::percentage($insurableTotal, $percent),
        };
        $patientShare = $invoice->total->minus($insurerShare);
        $due = $patientShare->minus($patientPaid);
        if ($due->isNegative()) {
            $due = $zero;
        }
        if ($due->isGreaterThan($invoice->balance_due)) {
            $due = $invoice->balance_due;
        }

        return new CoverageSplit(
            covered: $reason === null,
            reason: $reason,
            insurerName: $patient->insurer?->name,
            coveragePercent: $reason === null ? $percent : '0',
            insurableTotal: $insurableTotal,
            insurerShare: $insurerShare,
            patientShare: $patientShare,
            patientPaid: $patientPaid,
            patientDue: $due,
        );
    }

    /** @return Collection<int, InvoiceItem> */
    public static function insurableItems(Invoice $invoice): Collection
    {
        return $invoice->items->filter(fn (InvoiceItem $i): bool => $i->billableService?->is_insurable === true)->values();
    }

    public static function hasPolicy(Patient $patient): bool
    {
        return $patient->insurance_company_id !== null && $patient->insurance_policy_number !== null;
    }

    public static function validOn(Patient $patient, ?CarbonImmutable $day): bool
    {
        return $patient->insurance_expiry_date === null || $day === null || $patient->insurance_expiry_date->gte($day);
    }

    /**
     * The latest day of service among the lines (a visit's date, else the invoice's issue date or today).
     *
     * @param  Collection<int, InvoiceItem>  $items
     */
    public static function lastServiceDate(Collection $items, Invoice $invoice, CarbonImmutable $today): ?CarbonImmutable
    {
        return $items->map(fn (InvoiceItem $i): CarbonImmutable => $i->chargeable instanceof Appointment
            ? $i->chargeable->scheduled_date
            : ($invoice->issue_date ?? $today))->max() ?? $invoice->issue_date;
    }
}
