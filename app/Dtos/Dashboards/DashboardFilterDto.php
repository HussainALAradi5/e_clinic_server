<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

use Carbon\CarbonImmutable;

/** What every metric resolver is asked for: the clinic-local day. New filters are added here, once. */
final readonly class DashboardFilterDto
{
    public function __construct(public CarbonImmutable $date) {}
}
