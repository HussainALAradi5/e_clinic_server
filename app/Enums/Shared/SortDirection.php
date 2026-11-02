<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Sort direction for list endpoints.
 */
enum SortDirection: string implements HasLabel
{
    use EnumHelpers;

    case Asc = 'asc';
    case Desc = 'desc';
}
