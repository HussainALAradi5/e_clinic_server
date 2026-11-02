<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Where a stock lot is kept: the pharmacy (dispensing to patients, deliveries arrive here) or the treatment room
 * (ward stock the nurses give during a session, moved over from the pharmacy).
 */
enum StockLocation: string implements HasLabel
{
    use EnumHelpers;

    case Pharmacy = 'pharmacy';
    case TreatmentRoom = 'treatment_room';
}
