<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/** In a group, admins rename it and add, remove or promote members; members chat. */
enum ParticipantRole: string implements HasLabel
{
    use EnumHelpers;

    case Admin = 'admin';
    case Member = 'member';
}
