<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Sex as recorded on ID documents.
 */
enum Gender: string implements HasLabel
{
    use EnumHelpers;

    case Male = 'male';
    case Female = 'female';
}
