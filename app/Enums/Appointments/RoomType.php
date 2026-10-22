<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of room.
 */
enum RoomType: string implements HasLabel
{
    use EnumHelpers;

    case Consultation = 'consultation';
    case Physical = 'physical';
    case TreatmentRoom = 'treatment_room';
    case SessionRoom = 'session_room';
    case Xray = 'xray';
    case Laboratory = 'laboratory';
}
