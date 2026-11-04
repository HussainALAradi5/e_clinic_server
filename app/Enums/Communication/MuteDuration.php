<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;
use Carbon\CarbonImmutable;

/**
 * How long a member mutes a chat.
 */
enum MuteDuration: string implements HasLabel
{
    use EnumHelpers;

    case EightHours = '8h';
    case OneWeek = '1w';
    case Always = 'always';

    /** When the mute ends by itself; null = until the member unmutes. */
    public function until(CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($this) {
            self::EightHours => $now->addHours(8),
            self::OneWeek => $now->addWeek(),
            self::Always => null,
        };
    }
}
