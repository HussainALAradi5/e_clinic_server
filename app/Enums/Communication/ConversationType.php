<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/** A chat between two colleagues, or a named group. */
enum ConversationType: string implements HasLabel
{
    use EnumHelpers;

    case Direct = 'direct';
    case Group = 'group';
}
