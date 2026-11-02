<?php

declare(strict_types=1);

namespace App\Events\HR;

use App\Models\HR\StaffRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Asked, approved, rejected or cancelled (the request's status tells which).
 */
final class StaffRequestChanged
{
    use Dispatchable;

    public function __construct(public readonly StaffRequest $request) {}
}
