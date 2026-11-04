<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Enums\Payments\AppointmentFeeType;
use App\Models\Appointments\Appointment;
use App\Models\Payments\AppointmentFee;
use App\Utilities\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Fees for visits that did not happen. Rules: a no-show is charged; a cancellation is charged only when the patient
 * cancelled within `payments.fees.late_cancel_hours` of the start (never when the clinic, the doctor or the system
 * cancelled). The amount is the fee's price-list price (archived or 0 = not charged). A fee goes on the patient's
 * draft invoice and can be waived — by admins and accountants, with a reason — until that invoice is issued.
 */
interface AppointmentFeeServiceInterface
{
    /** Fees I may see (patients: their own), newest first. */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): AppointmentFee;

    /** The fee this appointment's outcome calls for, if any (no side effects). */
    public function feeTypeFor(Appointment $appointment): ?AppointmentFeeType;

    /** Charges the fee its outcome calls for, once; null when none is due or the fee is switched off. */
    public function chargeFor(Appointment $appointment): ?AppointmentFee;

    /** What the patient would be charged for cancelling now (to warn before confirming); null = free. */
    public function cancellationFee(Appointment $appointment): ?Money;

    /**
     * Documents that cost money now, by kind, from the price list (archived or 0 = free).
     *
     * @return list<array{kind: string, name: string, amount: array{amount: string, currency: string}}>
     */
    public function documentFees(): array;

    /** Takes the fee off the draft invoice, recording who waived it and why. */
    public function waive(AppointmentFee $fee, string $reason): AppointmentFee;
}
