<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Recurring salary component kind.
 */
enum SalaryComponentType: string implements HasLabel
{
    use EnumHelpers;

    case Allowance = 'allowance';
    case Deduction = 'deduction';
}
