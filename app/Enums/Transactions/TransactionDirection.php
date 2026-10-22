<?php

declare(strict_types=1);

namespace App\Enums\Transactions;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Effect of a movement on the clinic's cash position.
 */
enum TransactionDirection: string implements HasLabel
{
    use EnumHelpers;

    case In = 'in';
    case Out = 'out';
    case Internal = 'internal';
}
