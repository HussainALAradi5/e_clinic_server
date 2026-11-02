<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Contracts;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Models\Attendance;

/**
 * One kind of staff request. It reads the minutes attendance already worked out for the day (never recomputes them)
 * and, once HR approves, applies the decision to the day through the attendance service.
 */
interface StaffRequestHandler
{
    public function type(): StaffRequestType;

    /** The day's minutes the request is about (late or overtime); 0 = nothing to ask. */
    public function minutes(Attendance $day): int;

    /** Already settled on the day itself (late excused, overtime approved). */
    public function isSettled(Attendance $day): bool;

    /**
     * Snapshot kept on the request: the minutes and, for overtime, the rate and amount.
     *
     * @return array<string, mixed>
     */
    public function details(Attendance $day): array;

    public function approve(Attendance $day): Attendance;
}
