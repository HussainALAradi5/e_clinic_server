<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

use Brick\Money\Money;

/**
 * How an invoice splits between the insurer and the patient (the co-pay). When `covered` is false the patient
 * owes the whole balance and `reason` says why (no_insurance, expired, nothing_insurable, claim_rejected).
 */
final readonly class CoverageSplit
{
    public function __construct(
        public bool $covered,
        public ?string $reason,
        public ?string $insurerName,
        public string $coveragePercent,
        public Money $insurableTotal,
        public Money $insurerShare,
        public Money $patientShare,
        public Money $patientPaid,
        /** What the desk may still take from the patient: their share less what they paid, within the balance. */
        public Money $patientDue,
    ) {}
}
