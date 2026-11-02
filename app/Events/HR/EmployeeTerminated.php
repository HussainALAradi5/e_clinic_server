<?php

declare(strict_types=1);

namespace App\Events\HR;

use App\Models\HR\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/** Other modules react (a doctor stops accepting new bookings). */
final class EmployeeTerminated
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
