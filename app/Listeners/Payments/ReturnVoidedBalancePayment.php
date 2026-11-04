<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Payments\PaymentVoided;
use App\Interfaces\Payments\WalletServiceInterface;

/**
 * A payment made from the patient's balance was voided: its amount goes back to the balance.
 */
final readonly class ReturnVoidedBalancePayment
{
    public function __construct(private WalletServiceInterface $wallet) {}

    public function handle(PaymentVoided $event): void
    {
        $this->wallet->returnVoided($event->payment);
    }
}
