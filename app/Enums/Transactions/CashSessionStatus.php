<?php

declare(strict_types=1);

namespace App\Enums\Transactions;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Cash drawer shift status (closing = difference awaiting supervisor approval).
 */
enum CashSessionStatus: string implements HasLabel
{
    use EnumHelpers;

    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
}
