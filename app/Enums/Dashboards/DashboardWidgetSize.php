<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** How much of the dashboard grid a widget takes on wide screens (a phone shows one column whatever the size). */
enum DashboardWidgetSize: string implements HasLabel
{
    use EnumHelpers;

    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
}
