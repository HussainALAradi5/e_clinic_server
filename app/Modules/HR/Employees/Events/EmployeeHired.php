<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Events;

use App\Modules\HR\Employees\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

final class EmployeeHired
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
