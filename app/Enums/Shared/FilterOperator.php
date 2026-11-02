<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Operators of the generic list filter (filter[field][op]=value).
 */
enum FilterOperator: string implements HasLabel
{
    use EnumHelpers;

    case Eq = 'eq';
    case Ne = 'ne';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case In = 'in';
    case Nin = 'nin';
    case Between = 'between';
    case Like = 'like';
    case Starts = 'starts';
    case Null = 'null';

    /**
     * Operators whose value is a list (comma separated in the query string).
     */
    public function expectsList(): bool
    {
        return in_array($this, [self::In, self::Nin, self::Between], true);
    }
}
