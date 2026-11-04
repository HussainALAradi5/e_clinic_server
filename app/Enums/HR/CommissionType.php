<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * How a doctor earns on top of the salary: nothing, a percentage of the revenue booked to them in the month (net of
 * cancellations and returns, package sessions included), or a fixed amount per completed visit.
 */
enum CommissionType: string implements HasLabel
{
    use EnumHelpers;

    case None = 'none';
    case Percent = 'percent';
    case PerVisit = 'per_visit';
}
