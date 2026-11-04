<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Enums\HR\CommissionType;
use Brick\Money\Money;

/**
 * What a doctor's work brought in a month and their commission on it.
 */
final readonly class DoctorEarning
{
    public function __construct(
        public string $doctorId,
        public string $doctorName,
        public CommissionType $type,
        public string $rate,
        /** Revenue booked to the doctor (credits less debits on revenue accounts, posted entries). */
        public Money $revenue,
        /** Completed visits with the doctor as provider. */
        public int $visits,
        public Money $commission,
    ) {}
}
