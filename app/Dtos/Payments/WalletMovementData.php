<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

use App\Enums\Shared\PaymentMethod;

/**
 * Money in or out of a patient's balance: the amount (BHD, 3 decimals), how it moved, a bank / card reference and the
 * reason (required for pay-backs and adjustments).
 */
final readonly class WalletMovementData
{
    public function __construct(
        public string $amount,
        public ?PaymentMethod $method = null,
        public ?string $referenceNumber = null,
        public ?string $reason = null,
    ) {}
}
