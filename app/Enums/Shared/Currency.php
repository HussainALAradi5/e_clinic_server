<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * ISO-4217 currency codes; base currency is BHD (3 decimals).
 */
enum Currency: string implements HasLabel
{
    use EnumHelpers;

    case BHD = 'BHD';
    case USD = 'USD';
    case SAR = 'SAR';
}
