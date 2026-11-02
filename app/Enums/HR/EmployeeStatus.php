<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Employment status. "On leave" is derived from approved leave requests, not a status.
 */
enum EmployeeStatus: string implements HasLabel
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
