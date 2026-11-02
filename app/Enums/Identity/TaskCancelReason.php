<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Why a task ended as cancelled (failed / timeout = system).
 */
enum TaskCancelReason: string implements HasLabel
{
    use EnumHelpers;

    case User = 'user';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
