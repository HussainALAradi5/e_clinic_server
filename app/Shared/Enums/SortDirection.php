<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Sort direction for list endpoints.
 */
enum SortDirection: string implements HasLabel
{
    use EnumHelpers;

    case Asc = 'asc';
    case Desc = 'desc';
}
