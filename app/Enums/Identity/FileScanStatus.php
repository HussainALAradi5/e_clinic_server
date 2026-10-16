<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Antivirus result of an uploaded file; only clean files can be downloaded.
 */
enum FileScanStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
}
