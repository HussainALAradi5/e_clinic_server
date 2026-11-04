<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\WalletMovementData;
use App\Models\Appointments\Patient;
use App\Models\Payments\Invoice;
use App\Models\Payments\PatientWalletEntry;
use App\Models\Payments\Payment;
use App\Utilities\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WalletServiceInterface
{
    public function balance(Patient $patient): Money;

    /** The patient's balance movements, newest first. */
    public function entries(Patient $patient, ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Money paid in at the desk; cash needs the receiver's open drawer. Retried requests return the first entry. */
    public function deposit(Patient $patient, WalletMovementData $data, string $idempotencyKey): PatientWalletEntry;

    /** Money paid back to the patient (accountant, with a reason); never more than the balance. */
    public function withdraw(Patient $patient, WalletMovementData $data, string $idempotencyKey): PatientWalletEntry;

    /** A correction (accountant / admin, with a reason, within the role's limit); a debit never below zero. */
    public function adjust(Patient $patient, WalletMovementData $data, bool $credit): PatientWalletEntry;

    /** Pays an issued invoice from the balance (all that is due, or `amount`); never more than either. */
    public function payInvoice(Invoice $invoice, ?string $amount, string $idempotencyKey): Payment;

    /** A balance payment was voided: its amount goes back to the balance. */
    public function returnVoided(Payment $payment): void;

    /** Puts what an online payment brought beyond the invoice's balance on the patient's balance (no deposit limits). */
    public function creditOnlineSurplus(Patient $patient, Money $amount, string $reference, string $idempotencyKey): PatientWalletEntry;
}
