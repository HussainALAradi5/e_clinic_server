<?php

declare(strict_types=1);

namespace App\Events\Appointments;

use App\Models\Appointments\Treatment;
use Illuminate\Foundation\Events\Dispatchable;

final class TreatmentCreated
{
    use Dispatchable;

    public function __construct(public readonly Treatment $treatment) {}
}
