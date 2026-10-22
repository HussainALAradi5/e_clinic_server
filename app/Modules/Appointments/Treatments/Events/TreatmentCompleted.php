<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Events;

use App\Modules\Appointments\Treatments\Models\Treatment;
use Illuminate\Foundation\Events\Dispatchable;

final class TreatmentCompleted
{
    use Dispatchable;

    public function __construct(public readonly Treatment $treatment) {}
}
