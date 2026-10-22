<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Events;

use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use Illuminate\Foundation\Events\Dispatchable;

final class ClinicalOrderCompleted
{
    use Dispatchable;

    public function __construct(public readonly ClinicalOrder $order) {}
}
