<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

use Carbon\CarbonImmutable;

/** A dashboard's widgets for one day, already limited to what the signed-in user may see. */
final readonly class DashboardDataResult
{
    /**
     * @param  list<DashboardWidgetResult>  $widgets
     */
    public function __construct(
        public string $dashboardId,
        public CarbonImmutable $date,
        public array $widgets,
    ) {}
}
