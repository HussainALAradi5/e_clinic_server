<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Data;

use App\Enums\Appointments\CancelledBy;

final readonly class CancelAppointmentData
{
    public function __construct(public CancelledBy $cancelledBy, public string $reason) {}
}
