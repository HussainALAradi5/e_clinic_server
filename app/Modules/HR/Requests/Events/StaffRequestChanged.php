<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Events;

use App\Modules\HR\Requests\Models\StaffRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Asked, approved, rejected or cancelled (the request's status tells which).
 */
final class StaffRequestChanged
{
    use Dispatchable;

    public function __construct(public readonly StaffRequest $request) {}
}
