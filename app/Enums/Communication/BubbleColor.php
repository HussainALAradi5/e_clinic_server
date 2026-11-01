<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/** Colours a user may pick for their own and others' chat bubbles (group leaders always show in amber). */
enum BubbleColor: string implements HasLabel
{
    use EnumHelpers;

    case Blue = 'blue';
    case Green = 'green';
    case Teal = 'teal';
    case Purple = 'purple';
    case Rose = 'rose';
    case Slate = 'slate';
}
