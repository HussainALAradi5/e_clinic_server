<?php

declare(strict_types=1);

namespace App\Events\Payments;

use App\Models\Payments\Payment;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentReceived
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
