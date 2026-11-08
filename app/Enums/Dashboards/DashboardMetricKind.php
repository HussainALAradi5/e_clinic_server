<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** How a metric is shown: one figure, or a bounded list of rows. */
enum DashboardMetricKind: string implements HasLabel
{
    use EnumHelpers;

    case Stat = 'stat';
    case List = 'list';
}
