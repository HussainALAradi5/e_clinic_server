<?php

declare(strict_types=1);

namespace App\Events\HR;

use App\Models\HR\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Calculated, approved, paid or cancelled (the run's status tells which).
 */
final class PayrollStatusChanged
{
    use Dispatchable;

    public function __construct(public readonly PayrollRun $run) {}
}
