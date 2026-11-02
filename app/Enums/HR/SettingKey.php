<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * HR settings (one row each in `settings`):
 * - late_excuse: late arrivals are deducted from pay unless HR accepts the employee's excuse request.
 * - overtime: overtime is paid only through an approved overtime request; `amount` is the pay per hour (empty = the
 *   employee's own hourly wage × the overtime multiplier).
 */
enum SettingKey: string implements HasLabel
{
    use EnumHelpers;

    case LateExcuse = 'late_excuse';
    case Overtime = 'overtime';

    /** Whether the setting carries an amount (the overtime pay per hour). */
    public function hasAmount(): bool
    {
        return $this === self::Overtime;
    }
}
