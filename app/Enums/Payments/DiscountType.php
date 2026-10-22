<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Invoice line discount kind.
 */
enum DiscountType: string implements HasLabel
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
