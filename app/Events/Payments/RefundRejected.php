<?php

declare(strict_types=1);

namespace App\Events\Payments;

use App\Models\Payments\Refund;
use Illuminate\Foundation\Events\Dispatchable;

final class RefundRejected
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
