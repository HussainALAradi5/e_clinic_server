<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Invoice line discount kind.
 */
enum DiscountType: string implements HasLabel
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
