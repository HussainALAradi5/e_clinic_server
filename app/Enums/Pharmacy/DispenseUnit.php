<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Unit a medication is stocked and dispensed in.
 */
enum DispenseUnit: string implements HasLabel
{
    use EnumHelpers;

    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Ml = 'ml';
    case Vial = 'vial';
    case Ampoule = 'ampoule';
    case Tube = 'tube';
    case Bottle = 'bottle';
    case Pack = 'pack';
    case Piece = 'piece';
}
