<?php

declare(strict_types=1);

namespace App\Enums\Identity;

/** How a person reads times across the apps. */
enum TimeFormat: string
{
    case TwelveHour = '12h';
    case TwentyFourHour = '24h';
}
