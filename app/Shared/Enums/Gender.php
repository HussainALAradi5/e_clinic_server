<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Sex as recorded on ID documents.
 */
enum Gender: string implements HasLabel
{
    use EnumHelpers;

    case Male = 'male';
    case Female = 'female';
}
