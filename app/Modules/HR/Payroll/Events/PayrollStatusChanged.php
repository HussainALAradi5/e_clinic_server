<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Events;

use App\Modules\HR\Payroll\Models\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Calculated, approved, paid or cancelled (the run's status tells which).
 */
final class PayrollStatusChanged
{
    use Dispatchable;

    public function __construct(public readonly PayrollRun $run) {}
}
