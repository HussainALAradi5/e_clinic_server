<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Events;

use App\Modules\Payments\Payments\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentVoided
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
