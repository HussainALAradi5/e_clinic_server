<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Events;

use App\Modules\Appointments\Treatments\Models\Treatment;
use Illuminate\Foundation\Events\Dispatchable;

/** Missed sessions in a row reached `treatments.absence_alert_after` (the plan is not cancelled; the doctor decides). */
final class TreatmentAttendanceAlert
{
    use Dispatchable;

    public function __construct(public readonly Treatment $treatment, public readonly int $consecutiveAbsences) {}
}
