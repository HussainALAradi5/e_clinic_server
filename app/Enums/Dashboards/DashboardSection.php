<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * The area of the clinic a dashboard reports on. A new section is one case here plus a DashboardMetricProvider
 * registered in DashboardsServiceProvider; the model, controller and client page stay as they are.
 */
enum DashboardSection: string implements HasLabel
{
    use EnumHelpers;

    case Attendance = 'attendance';
}
