<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** In a group, admins rename it and add, remove or promote members; members chat. */
enum ParticipantRole: string implements HasLabel
{
    use EnumHelpers;

    case Admin = 'admin';
    case Member = 'member';
}
