<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Events;

use App\Modules\HR\Leaves\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent for approval, approved, rejected or cancelled (the request's status tells which).
 */
final class LeaveStatusChanged
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request) {}
}
