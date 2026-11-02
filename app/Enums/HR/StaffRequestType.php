<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * What a staff request asks HR for, about one attendance day: excuse the late arrival (then it is not deducted), or
 * pay the overtime worked.
 */
enum StaffRequestType: string implements HasLabel
{
    use EnumHelpers;

    case LateExcuse = 'late_excuse';
    case Overtime = 'overtime';

    /** The HR setting that switches this kind of request on. */
    public function setting(): SettingKey
    {
        return match ($this) {
            self::LateExcuse => SettingKey::LateExcuse,
            self::Overtime => SettingKey::Overtime,
        };
    }
}
