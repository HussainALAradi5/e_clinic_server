<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Logical group operators of the generic list filter.
 */
enum LogicalOperator: string implements HasLabel
{
    use EnumHelpers;

    case And = 'and';
    case Or = 'or';
    case Not = 'not';
}
