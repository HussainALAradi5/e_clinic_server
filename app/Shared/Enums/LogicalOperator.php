<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

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
