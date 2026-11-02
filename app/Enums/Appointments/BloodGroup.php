<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * ABO / Rh blood group.
 */
enum BloodGroup: string implements HasLabel
{
    use EnumHelpers {
        label as private defaultLabel;
    }

    case A_POS = 'A+';
    case A_NEG = 'A-';
    case B_POS = 'B+';
    case B_NEG = 'B-';
    case AB_POS = 'AB+';
    case AB_NEG = 'AB-';
    case O_POS = 'O+';
    case O_NEG = 'O-';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return $this === self::UNKNOWN ? 'Unknown' : $this->value;
    }
}
