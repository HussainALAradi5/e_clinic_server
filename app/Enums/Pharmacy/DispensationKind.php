<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * `take_home` = handed over by the pharmacy (with or without a prescription); `administered` = given during an appointment / treatment session by its clinician.
 */
enum DispensationKind: string implements HasLabel
{
    use EnumHelpers;

    case TakeHome = 'take_home';
    case Administered = 'administered';
}
