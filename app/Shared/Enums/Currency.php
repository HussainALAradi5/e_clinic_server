<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

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
