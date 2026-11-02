<?php

declare(strict_types=1);

namespace App\Events\Appointments;

use App\Models\Appointments\ClinicalOrder;
use Illuminate\Foundation\Events\Dispatchable;

final class ClinicalOrderCompleted
{
    use Dispatchable;

    public function __construct(public readonly ClinicalOrder $order) {}
}
