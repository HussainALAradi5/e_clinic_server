<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Events;

use App\Modules\Payments\Refunds\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

final class RefundRequested
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
