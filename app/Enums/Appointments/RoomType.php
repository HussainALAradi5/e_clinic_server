<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of room: what happens there (and which rooms a booking may use).
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
    case Ultrasound = 'ultrasound';
    case Dental = 'dental';
    /** Minor surgery and procedures (excisions, suturing). */
    case Procedure = 'procedure';
    case Vaccination = 'vaccination';
    case Pharmacy = 'pharmacy';
}
