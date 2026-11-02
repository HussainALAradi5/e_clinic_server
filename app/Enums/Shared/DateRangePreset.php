<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;
use Carbon\CarbonImmutable;

/**
 * Rolling date ranges accepted by every list endpoint (`date_range=this_month`). Fixed days use the same parameter
 * (`date_range=2026-10-01..2026-10-22`, either side open) or `date_from` / `date_to`; `custom` is kept for saved views.
 */
enum DateRangePreset: string implements HasLabel
{
    use EnumHelpers;

    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisQuarter = 'this_quarter';
    case ThisYear = 'this_year';
    case Custom = 'custom';

    /**
     * Inclusive clinic-local date range for the preset (weeks start on Sunday); null for `custom`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function range(CarbonImmutable $today): ?array
    {
        $today = $today->startOfDay();
        $weekStart = $today->startOfWeek(CarbonImmutable::SUNDAY);

        return match ($this) {
            self::Today => [$today, $today],
            self::Yesterday => [$today->subDay(), $today->subDay()],
            self::ThisWeek => [$weekStart, $weekStart->addDays(6)],
            self::LastWeek => [$weekStart->subWeek(), $weekStart->subDay()],
            self::ThisMonth => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            self::LastMonth => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            self::Last7Days => [$today->subDays(6), $today],
            self::Last30Days => [$today->subDays(29), $today],
            self::ThisQuarter => [$today->startOfQuarter(), $today->endOfQuarter()->startOfDay()],
            self::ThisYear => [$today->startOfYear(), $today->endOfYear()->startOfDay()],
            self::Custom => null,
        };
    }
}
