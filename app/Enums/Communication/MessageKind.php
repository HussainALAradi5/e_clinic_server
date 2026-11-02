<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** What a chat message carries: text, photos / files, or a voice note. */
enum MessageKind: string implements HasLabel
{
    use EnumHelpers;

    case Text = 'text';
    case Media = 'media';
    case Voice = 'voice';
}
