<?php

declare(strict_types=1);

namespace App\Events\HR;

use App\Models\HR\Employee;
use Illuminate\Foundation\Events\Dispatchable;

final class EmployeeHired
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
