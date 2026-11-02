<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** How chat bubbles are drawn (a personal choice). */
enum BubbleStyle: string implements HasLabel
{
    use EnumHelpers;

    case Rounded = 'rounded';
    case Square = 'square';
    case Minimal = 'minimal';
}
