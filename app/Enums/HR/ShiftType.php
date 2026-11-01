<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * How a shift counts time: fixed hours (late after the start, overtime after the end), or flexible (arrive by the
 * latest start inside a window; overtime is time worked beyond the hours due).
 */
enum ShiftType: string implements HasLabel
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Flexible = 'flexible';
}
