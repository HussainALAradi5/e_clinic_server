<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Payment state: completed, or voided the same day.
 */
enum PaymentStatus: string implements HasLabel
{
    use EnumHelpers;

    case Completed = 'completed';
    case Voided = 'voided';
}
