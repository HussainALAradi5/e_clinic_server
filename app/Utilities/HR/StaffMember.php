<?php

declare(strict_types=1);

namespace App\Utilities\HR;

use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * The employee behind the signed-in account, and the "own record unless you may read everyone's" rule for
 * per-employee screens (balances, monthly summary).
 */
final class StaffMember
{
    /** The signed-in user's employee record (404 when the account is not staff). */
    public static function current(): Employee
    {
        return Employee::query()->with('person')->findOrFail(Auth::id());
    }

    /** An employee the user may look at for a resource: anyone with `{resource}.read_all`, else only themselves. */
    public static function visible(string $employeeId, string $resource): Employee
    {
        if ($employeeId !== Auth::id() && Auth::user()?->can("{$resource}.read_all") !== true) {
            throw (new ModelNotFoundException)->setModel(Employee::class, [$employeeId]);
        }

        return Employee::query()->with('person')->findOrFail($employeeId);
    }
}
