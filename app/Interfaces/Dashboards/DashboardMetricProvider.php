<?php

declare(strict_types=1);

namespace App\Interfaces\Dashboards;

use App\Dtos\Dashboards\DashboardMetric;
use App\Enums\Dashboards\DashboardSection;

/** A section's catalog of metrics. Implement it and register the class in DashboardsServiceProvider. */
interface DashboardMetricProvider
{
    public function section(): DashboardSection;

    /**
     * @return list<DashboardMetric>
     */
    public function metrics(): array;
}
